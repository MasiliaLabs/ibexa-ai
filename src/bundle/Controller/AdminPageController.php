<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Controller;

use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The AI settings page (providers, models, siteaccesses, health and usage).
 */
final class AdminPageController extends Controller
{
    public function performAccessCheck(): void
    {
        parent::performAccessCheck();
        $this->denyAccessUnlessGranted(new Attribute('setup', 'administrate'));
    }

    #[Route('/ai/settings', name: 'masilia.ibexa_ai.admin.settings_page', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('@ibexadesign/ai_settings/index.html.twig');
    }
}
