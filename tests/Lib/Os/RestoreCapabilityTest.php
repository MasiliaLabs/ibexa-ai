<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Masilia\IbexaAi\Ibexa\Content\Trash\TrashRestorer;
use Masilia\IbexaAi\Os\Capability\Trash\RestoreCapability;
use Masilia\Contracts\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;

final class RestoreCapabilityTest extends TestCase
{
    public function testItRestoresWhatTheTrashGivesBackAndSaysHowMany(): void
    {
        $restorer = $this->createMock(TrashRestorer::class);
        $restorer->expects(self::once())->method('restore')->with([99, 100])->willReturn([99]);

        $result = (new RestoreCapability($restorer))->execute(['content_ids' => [99, 100]]);

        self::assertTrue($result->success);
        self::assertSame('Restored 1 items', $result->message);
        self::assertSame(['restored' => [99], 'count' => 1], $result->data);
    }

    public function testAnEmptyListIsRefusedBeforeAnythingIsRestored(): void
    {
        $restorer = $this->createMock(TrashRestorer::class);
        $restorer->expects(self::never())->method('restore');
        $capability = new RestoreCapability($restorer);

        self::assertSame('trash.restore requires at least one content id (content_ids).', $capability->validate(['content_ids' => []]));
        self::assertNull($capability->validate(['content_ids' => [1]]));
    }

    public function testRestoringIsAWriteWithTheRestorePolicy(): void
    {
        $capability = new RestoreCapability($this->createMock(TrashRestorer::class));

        self::assertSame(Risk::WRITE, $capability->risk());
        self::assertSame([['content', 'restore']], $capability->requires());
        self::assertSame('Restore from the trash', $capability->preview(['content_ids' => [1, 2]])->title);
    }
}
