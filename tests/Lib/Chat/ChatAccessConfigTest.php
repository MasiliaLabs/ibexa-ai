<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Chat;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\IbexaAi\Chat\ChatAccessConfig;
use PHPUnit\Framework\TestCase;

/** The assistant's button is only there for whoever the role lets use it. */
final class ChatAccessConfigTest extends TestCase
{
    public function testTheScriptsAreToldWhetherTheUserMayUseTheAssistant(): void
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->expects(self::exactly(2))->method('hasAccess')->with('masilia_ai', 'use')->willReturnOnConsecutiveCalls(true, false);

        self::assertSame(['canUse' => true], (new ChatAccessConfig($permissions))->getConfig());
        self::assertSame(['canUse' => false], (new ChatAccessConfig($permissions))->getConfig());
    }
}
