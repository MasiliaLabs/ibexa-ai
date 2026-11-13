<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\EventListener;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds "AI Assistant" (settings) and "What the assistant can do" under the Admin menu group of the Ibexa sidebar.
 *
 * The menu keys 'main__admin' and 'main__admin__ai_settings' are Ibexa internal conventions,
 * not a public API: if they change, the entry silently disappears (the null check below
 * keeps the rest of the menu working).
 */
final class MainMenuBuilderListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ConfigureMenuEvent::MAIN_MENU => 'onMainMenuBuild'];
    }

    public function onMainMenuBuild(ConfigureMenuEvent $event): void
    {
        $adminMenu = $event->getMenu()->getChild('main__admin');
        if (!$adminMenu instanceof ItemInterface) {
            return;
        }

        $adminMenu->addChild('main__admin__ai_settings', [
            'route' => 'masilia.ibexa_ai.admin.settings_page',
        ])
            ->setLabel('AI Assistant')
            ->setExtra('translation_domain', 'ibexa_menu')
            ->setExtra('icon', 'settings-block')
            ->setExtra('orderNumber', 150);

        $adminMenu->addChild('main__admin__ai_capabilities', [
            'route' => 'masilia.ibexa_ai.admin.capabilities_page',
        ])
            ->setLabel('What the assistant can do')
            ->setExtra('translation_domain', 'ibexa_menu')
            ->setExtra('icon', 'settings-block')
            ->setExtra('orderNumber', 151);
    }
}
