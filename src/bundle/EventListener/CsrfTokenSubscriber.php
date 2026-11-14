<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\EventListener;

use Masilia\Contracts\IbexaAi\Api\ApiError;
use Masilia\Contracts\IbexaAi\Http\CsrfProtectedController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The CSRF protection of the admin's own requests, the way Ibexa's REST layer does it: the token the admin page carries (its
 * CSRF-Token meta tag) comes back in the X-CSRF-Token header and is checked against the same intention. It answers 403 before the
 * action of a CsrfProtectedController runs.
 */
final readonly class CsrfTokenSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-CSRF-Token';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        #[Autowire('%ibexa.rest.csrf_token_intention%')]
        private string $intention,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $instance = is_array($controller) ? $controller[0] : $controller;
        if (!$instance instanceof CsrfProtectedController || in_array($event->getRequest()->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $value = (string) $event->getRequest()->headers->get(self::HEADER, '');
        if ($value !== '' && $this->tokens->isTokenValid(new CsrfToken($this->intention, $value))) {
            return;
        }

        $refusal = new JsonResponse(ApiError::accessDenied()->toArray(), Response::HTTP_FORBIDDEN);
        $event->setController(static fn (): Response => $refusal);
    }
}
