<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Masilia\IbexaAi\Provider\HealthChecker;
use Masilia\IbexaAi\Provider\ProviderCatalog;
use Masilia\IbexaAi\Provider\ProviderTester;
use Masilia\IbexaAi\Provider\UsageReport;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Admin API behind the AI settings dashboard and the usage tab. Every route requires setup/administrate, and its writes the CSRF token of the admin.
 */
final class AdminSettingsController extends Controller implements CsrfProtectedController
{
    public function __construct(
        private readonly SiteAccessServiceInterface $siteAccess,
        private readonly ProviderCatalog $catalog,
        private readonly ProviderTester $tester,
        private readonly HealthChecker $health,
        private readonly UsageReport $usage,
    ) {
    }

    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('setup', 'administrate'));
    }

    #[Route('/ai/settings/api/data', name: 'masilia.ibexa_ai.admin.data', methods: ['GET'], options: ['expose' => true])]
    public function data(): JsonResponse
    {
        $siteaccesses = [];
        foreach ($this->siteAccess->getAll() as $siteAccess) {
            $siteaccesses[] = $siteAccess->name;
        }
        sort($siteaccesses);

        return new JsonResponse($this->catalog->snapshot() + [
            'siteaccesses' => $siteaccesses,
            'currentSiteaccess' => $this->siteAccess->getCurrent()->name,
        ]);
    }

    #[Route('/ai/settings/api/provider', name: 'masilia.ibexa_ai.admin.provider_save', methods: ['POST'], options: ['expose' => true])]
    public function saveProvider(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->catalog->saveProvider($data));
    }

    #[Route('/ai/settings/api/provider/{id}', name: 'masilia.ibexa_ai.admin.provider_delete', requirements: ['id' => '\d+'], methods: ['DELETE'], options: ['expose' => true])]
    public function deleteProvider(int $id): JsonResponse
    {
        return $this->mutate(fn () => $this->catalog->deleteProvider($id));
    }

    #[Route('/ai/settings/api/provider/{id}/siteaccesses', name: 'masilia.ibexa_ai.admin.provider_siteaccesses', requirements: ['id' => '\d+'], methods: ['PUT'], options: ['expose' => true])]
    public function setSiteaccesses(int $id, Request $request): JsonResponse
    {
        $data = $request->getPayload()->all();
        if (!isset($data['siteaccesses']) || !is_array($data['siteaccesses'])) {
            return $this->error('siteaccesses array is required', Response::HTTP_BAD_REQUEST);
        }

        return $this->mutate(fn () => $this->catalog->setSiteaccesses($id, $data['siteaccesses']));
    }

    #[Route('/ai/settings/api/provider/{id}/chat-model', name: 'masilia.ibexa_ai.admin.chat_model', requirements: ['id' => '\d+'], methods: ['PUT'], options: ['expose' => true])]
    public function setChatModel(int $id, Request $request): JsonResponse
    {
        $data = $request->getPayload()->all();

        return $this->mutate(fn () => $this->catalog->setActiveChatModel($id, isset($data['modelId']) ? (int) $data['modelId'] : null));
    }

    #[Route('/ai/settings/api/provider/{id}/image-model', name: 'masilia.ibexa_ai.admin.image_model', requirements: ['id' => '\d+'], methods: ['PUT'], options: ['expose' => true])]
    public function setImageModel(int $id, Request $request): JsonResponse
    {
        $data = $request->getPayload()->all();

        return $this->mutate(fn () => $this->catalog->setActiveImageModel($id, isset($data['modelId']) ? (int) $data['modelId'] : null));
    }

    #[Route('/ai/settings/api/provider/{id}/test', name: 'masilia.ibexa_ai.admin.provider_test', requirements: ['id' => '\d+'], methods: ['POST'], options: ['expose' => true])]
    public function testProvider(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->tester->test($id, $request->query->getBoolean('stream'));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[Route('/ai/settings/api/model', name: 'masilia.ibexa_ai.admin.model_save', methods: ['POST'], options: ['expose' => true])]
    public function saveModel(Request $request): JsonResponse
    {
        return $this->apply($request, fn (array $data) => $this->catalog->saveModel($data));
    }

    #[Route('/ai/settings/api/model/{id}', name: 'masilia.ibexa_ai.admin.model_delete', requirements: ['id' => '\d+'], methods: ['DELETE'], options: ['expose' => true])]
    public function deleteModel(int $id): JsonResponse
    {
        return $this->mutate(fn () => $this->catalog->deleteModel($id));
    }

    #[Route('/ai/settings/api/health', name: 'masilia.ibexa_ai.admin.health', methods: ['GET'], options: ['expose' => true])]
    public function healthCheck(): JsonResponse
    {
        return new JsonResponse($this->health->check($this->siteAccess->getCurrent()->name));
    }

    #[Route('/ai/usage/api/data', name: 'masilia.ibexa_ai.admin.usage', methods: ['GET'], options: ['expose' => true])]
    public function usageData(): JsonResponse
    {
        return new JsonResponse($this->usage->data());
    }

    /**
     * Runs a catalog write and maps its validation errors to 400 / 404 envelopes.
     */
    private function apply(Request $request, callable $write): JsonResponse
    {
        $data = $request->getPayload()->all();

        return $this->mutate(fn () => $write($data));
    }

    private function mutate(callable $write): JsonResponse
    {
        try {
            $write();
        } catch (\InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'not found') ? Response::HTTP_NOT_FOUND : Response::HTTP_BAD_REQUEST;

            return $this->error($e->getMessage(), $status);
        } catch (Throwable) {
            return $this->error('The AI settings could not be saved. Check the server log.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(ApiError::validation($message)->toArray(), $status);
    }
}
