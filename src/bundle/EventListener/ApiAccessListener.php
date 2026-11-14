<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\EventListener;

use Masilia\Contracts\IbexaAi\Security\RequiresAccess;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Contracts\IbexaAi\Api\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Refuses, before the action runs, what the user may not do: a controller that names an access (#[RequiresAccess]) is only
 * run for a user who has it.
 *
 * A request that changes something (POST, PUT, PATCH, DELETE) must also come from the admin interface: it is JSON, or it
 * carries X-Requested-With. A page of another site cannot send either without the browser first asking this server (CORS),
 * which is how a forged form or a forged upload is told from the admin's own requests. The admin client sets one of them.
 */
final readonly class ApiAccessListener
{
    public const SAME_ORIGIN_HEADER = 'X-Requested-With';
    public const SAME_ORIGIN_VALUE = 'XMLHttpRequest';

    public function __construct(private PermissionResolver $permissions)
    {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function onController(ControllerEvent $event): void
    {
        $required = $event->getAttributes(RequiresAccess::class);
        if ($required === []) {
            return;
        }

        $refusal = $this->refusalForRequest($event->getRequest());
        if ($refusal === null) {
            foreach ($required as $attribute) {
                [$module, $function] = $attribute->access->policy();
                if (!$this->permissions->hasAccess($module, $function)) {
                    $refusal = new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
                    break;
                }
            }
        }

        if ($refusal !== null) {
            $event->setController(static fn (): Response => $refusal);
        }
    }

    private function refusalForRequest(Request $request): ?JsonResponse
    {
        if ($request->isMethodSafe() || $request->getContentTypeFormat() === 'json' || $request->headers->get(self::SAME_ORIGIN_HEADER) === self::SAME_ORIGIN_VALUE) {
            return null;
        }

        return new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
    }
}
