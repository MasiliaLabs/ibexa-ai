<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Masilia\Bundle\IbexaAi\Security\Access;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\UserService;
use Masilia\Bundle\IbexaAi\Admin\CapabilityOverview;
use Masilia\Bundle\IbexaAi\Os\Audit\AuditLog;
use Masilia\Bundle\IbexaAi\Os\Coverage\CoverageReport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * "What the assistant can do": the capabilities by domain, what the current user may use, the coverage of the admin and the
 * latest things the assistant did. Reading it needs the permission to use the assistant; the activity needs setup/administrate.
 */
final class CapabilitiesPageController extends AbstractController
{
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly CapabilityOverview $overview,
        private readonly CoverageReport $coverage,
        private readonly AuditLog $audit,
        private readonly UserService $users,
    ) {
    }

    #[Route('/admin/ai/capabilities', name: 'masilia.ibexa_ai.admin.capabilities_page', methods: ['GET'])]
    public function page(): Response
    {
        if (!$this->permissions->hasAccess(...Access::Assistant->policy())) {
            throw $this->createAccessDeniedException('You do not have permission to use the AI assistant.');
        }

        $isAdministrator = (bool) $this->permissions->hasAccess(...Access::Administrator->policy());

        return $this->render('@ibexadesign/ai_settings/capabilities.html.twig', [
            'overview' => $this->overview->build(),
            'coverage' => $isAdministrator ? $this->coverage->build() : null,
            'activity' => $isAdministrator ? $this->activity() : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(): array
    {
        $logins = [];
        $rows = [];
        foreach ($this->audit->latest(25) as $row) {
            $userId = (int) $row['user_id'];
            if (!isset($logins[$userId])) {
                try {
                    $logins[$userId] = $this->users->loadUser($userId)->login;
                } catch (Throwable) {
                    $logins[$userId] = '#' . $userId;
                }
            }
            $rows[] = [
                'when' => (string) $row['created_at'],
                'user' => $logins[$userId],
                'capability' => (string) $row['capability'],
                'risk' => (string) $row['risk'],
                'success' => (bool) $row['success'],
                'message' => (string) $row['message'],
            ];
        }

        return $rows;
    }
}
