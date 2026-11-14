<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Trash\ContentTrasher;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\TrashCapability;
use Masilia\Contracts\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;

final class TrashCapabilityTest extends TestCase
{
    private function capability(ContentTrasher $trasher, bool $exists = true): TrashCapability
    {
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturnCallback(static fn (int $id) => $exists ? new \stdClass() : throw new NotFoundException('content', $id));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);

        return new TrashCapability($repository, $trasher);
    }

    public function testItTrashesTheContentAndSaysWhatWasUnlinked(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->expects(self::once())->method('trash')->with(1353)->willReturn([
            'content_id' => 1353, 'location_id' => 2000, 'trashed' => true, 'unlinked_from' => ['77:Home'],
        ]);

        $result = $this->capability($trasher)->execute(['content_id' => 1353]);

        self::assertTrue($result->success);
        self::assertSame('Trashed content 1353 (unlinked from 1 content item(s): 77:Home)', $result->message);
        self::assertSame(2000, $result->data['location_id']);
    }

    public function testAFailingTrashIsAnAnswerNotAnException(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->method('trash')->willThrowException(new \RuntimeException('the trash is full'));

        $result = $this->capability($trasher)->execute(['content_id' => 5]);

        self::assertFalse($result->success);
        self::assertSame('Trashing content 5 failed: the trash is full', $result->message);
    }

    public function testAContentThatDoesNotExistIsRefusedBeforeApproval(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->expects(self::never())->method('trash');
        $capability = $this->capability($trasher, false);

        self::assertSame('The content 9 does not exist.', $capability->validate(['content_id' => 9]));
    }

    public function testTrashingIsAWriteAndSaysItIsReversible(): void
    {
        $capability = $this->capability($this->createMock(ContentTrasher::class));

        self::assertSame(Risk::WRITE, $capability->risk());
        self::assertSame([['content', 'remove']], $capability->requires());
        self::assertStringContainsString('trash.restore', $capability->preview(['content_id' => 1])->notes[0]);
    }
}
