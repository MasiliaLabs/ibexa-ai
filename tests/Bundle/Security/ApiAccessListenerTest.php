<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Security;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\IbexaAi\Security\Access;
use Masilia\Bundle\IbexaAi\Security\ApiAccessListener;
use Masilia\Bundle\IbexaAi\Security\RequiresAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** An endpoint that names an access is run only for a user who has it, and a request that changes something must come from the admin. */
final class ApiAccessListenerTest extends TestCase
{
    /** @param list<array{string, string}> $granted The [module, function] the user has */
    private function listener(array $granted): ApiAccessListener
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturnCallback(static fn (string $module, string $function): bool => in_array([$module, $function], $granted, true));

        return new ApiAccessListener($permissions);
    }

    /** @return array{0: ControllerEvent, 1: callable} The event and the controller it started with */
    private function event(object $controller, string $action, Request $request): array
    {
        $callable = [$controller, $action];
        $event = new ControllerEvent($this->createMock(HttpKernelInterface::class), $callable, $request, HttpKernelInterface::MAIN_REQUEST);

        return [$event, $callable];
    }

    private function answered(ControllerEvent $event): ?Response
    {
        $controller = $event->getController();

        return is_callable($controller) && !is_array($controller) ? $controller() : null;
    }

    private function json(string $method = 'POST'): Request
    {
        return Request::create('/x', $method, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
    }

    public function testAnEndpointWithoutAnAccessIsLeftAlone(): void
    {
        [$event, $callable] = $this->event(new OpenController(), 'open', Request::create('/x', 'POST'));

        $this->listener([])->onController($event);

        self::assertSame($callable, $event->getController());
    }

    public function testAUserWhoHasTheAccessRunsTheActionOnAReadAndOnAJsonWrite(): void
    {
        $listener = $this->listener([['setup', 'administrate']]);

        [$read, $readController] = $this->event(new AdminController(), 'read', Request::create('/x', 'GET'));
        $listener->onController($read);
        self::assertSame($readController, $read->getController());

        [$write, $writeController] = $this->event(new AdminController(), 'write', $this->json('POST'));
        $listener->onController($write);
        self::assertSame($writeController, $write->getController());
    }

    public function testAUserWhoLacksTheAccessGetsA403BeforeTheActionRuns(): void
    {
        [$event] = $this->event(new AdminController(), 'read', Request::create('/x', 'GET'));

        $this->listener([['content', 'edit']])->onController($event);

        $answer = $this->answered($event);
        self::assertInstanceOf(JsonResponse::class, $answer);
        self::assertSame(Response::HTTP_FORBIDDEN, $answer->getStatusCode());
        self::assertSame('ACCESS_DENIED', json_decode((string) $answer->getContent(), true)['error']['code']);
    }

    public function testTheAccessOfAMethodIsTheOneItNames(): void
    {
        $listener = $this->listener([['content', 'edit']]);

        [$editor] = $this->event(new MixedController(), 'forEditors', Request::create('/x', 'GET'));
        $listener->onController($editor);
        self::assertNull($this->answered($editor), 'an editor reaches the action of editors');

        [$admin] = $this->event(new MixedController(), 'forAdministrators', Request::create('/x', 'GET'));
        $listener->onController($admin);
        self::assertSame(Response::HTTP_FORBIDDEN, $this->answered($admin)?->getStatusCode(), 'but not the one of administrators');
    }

    public function testAWriteThatIsNeitherJsonNorFromTheAdminIsRefusedEvenForAnAdministrator(): void
    {
        $listener = $this->listener([['setup', 'administrate']]);

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            [$event] = $this->event(new AdminController(), 'write', Request::create('/x', $method, ['a' => 'b']));
            $listener->onController($event);
            self::assertSame(Response::HTTP_FORBIDDEN, $this->answered($event)?->getStatusCode(), $method . ' as a form is refused');
        }
    }

    public function testAnUploadOrADeleteFromTheAdminCarriesItsHeader(): void
    {
        $listener = $this->listener([['setup', 'administrate']]);

        foreach (['POST', 'DELETE'] as $method) {
            $request = Request::create('/x', $method, server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            [$event, $callable] = $this->event(new AdminController(), 'write', $request);
            $listener->onController($event);
            self::assertSame($callable, $event->getController(), $method . ' with the header is accepted');
        }

        [$wrong] = $this->event(new AdminController(), 'write', Request::create('/x', 'POST', server: ['HTTP_X_REQUESTED_WITH' => 'fetch']));
        $listener->onController($wrong);
        self::assertSame(Response::HTTP_FORBIDDEN, $this->answered($wrong)?->getStatusCode(), 'only the admin client\'s value counts');
    }

    public function testEveryAccessNamesAPolicy(): void
    {
        self::assertSame(['setup', 'administrate'], Access::Administrator->policy());
        self::assertSame(['content', 'edit'], Access::ContentEditor->policy());
        self::assertSame(['masilia_ai', 'use'], Access::Assistant->policy());
    }
}

final class OpenController
{
    public function open(): Response
    {
        return new Response('open');
    }
}

#[RequiresAccess(Access::Administrator)]
final class AdminController
{
    public function read(): Response
    {
        return new Response('read');
    }

    public function write(): Response
    {
        return new Response('write');
    }
}

final class MixedController
{
    #[RequiresAccess(Access::ContentEditor)]
    public function forEditors(): Response
    {
        return new Response('editors');
    }

    #[RequiresAccess(Access::Administrator)]
    public function forAdministrators(): Response
    {
        return new Response('administrators');
    }
}
