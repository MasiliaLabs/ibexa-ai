<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The AI settings page (providers, models, siteaccesses, health and usage).
 */
final class AdminPageController extends AbstractController
{
    public function __construct(
        private readonly PermissionResolver $permissions,
    ) {
    }

    #[Route('/admin/ai/settings', name: 'masilia.ibexa_ai.admin.settings_page', methods: ['GET'])]
    public function settings(): Response
    {
        if (!$this->permissions->hasAccess('setup', 'administrate')) {
            throw $this->createAccessDeniedException('You do not have permission to access AI settings.');
        }

        return $this->render('@ibexadesign/ai_settings/index.html.twig');
    }
}
