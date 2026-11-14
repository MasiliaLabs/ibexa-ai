<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Trash\ContentTrasher;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\WriteChecks;
use Masilia\Bundle\IbexaAi\Ibexa\Rules\LocationRules;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\TrashCapability;
use Masilia\Contracts\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;

final class TrashCapabilityTest extends TestCase
{
    private function capability(ContentTrasher $trasher, bool $exists = true, bool $root = false, bool $user = false): TrashCapability
    {
        $info = $this->createMock(ContentInfo::class);
        $info->method('getMainLocationId')->willReturn(2000);
        $contents = $this->createMock(ContentService::class);
        $contents->method('loadContentInfo')->willReturnCallback(static fn (int $id) => $exists ? $info : throw new NotFoundException('content', $id));
        $locations = $this->createMock(LocationService::class);
        $locations->method('loadLocation')->willReturn($this->createMock(Location::class));
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contents);
        $repository->method('getLocationService')->willReturn($locations);
        $rules = $this->createMock(LocationRules::class);
        $rules->method('isRoot')->willReturn($root);

        $checks = $this->createMock(WriteChecks::class);
        $checks->method('notAUser')->willReturn($user ? 'Content 1 is a user: use access.user.delete (the admin has a user form for it), not a content change.' : null);

        return new TrashCapability($repository, $trasher, $rules, $checks);
    }

    public function testItTrashesTheContentAndSaysWhatWasUnlinked(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->expects(self::once())->method('trash')->with(1353, false)->willReturn([
            'content_id' => 1353, 'location_id' => 2000, 'trashed' => true, 'unlinked_from' => ['77:Home'], 'trashed_assets' => [],
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

    public function testARootLocationCannotBeTrashed(): void
    {
        $capability = $this->capability($this->createMock(ContentTrasher::class), true, true);

        self::assertSame('A root location cannot be trashed.', $capability->validate(['content_id' => 1]));
    }

    public function testTheImagesOnlyUsedByTheContentGoWithItOnlyWhenAsked(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->expects(self::once())->method('trash')->with(1353, true)->willReturn([
            'content_id' => 1353, 'location_id' => 2000, 'trashed' => true, 'unlinked_from' => [], 'trashed_assets' => [88],
        ]);

        self::assertTrue($this->capability($trasher)->execute(['content_id' => 1353, 'with_assets' => true])->success);
    }

    public function testThePreviewSaysWhichImagesStayOrGo(): void
    {
        $asset = $this->createMock(ContentInfo::class);
        $asset->method('getName')->willReturn('Hero image');
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->method('uniqueAssets')->willReturn([$asset]);
        $capability = $this->capability($trasher);

        self::assertStringContainsString('stay in the media (Hero image)', implode(' ', $capability->preview(['content_id' => 1])->notes));
        self::assertStringContainsString('go to the trash too: Hero image', implode(' ', $capability->preview(['content_id' => 1, 'with_assets' => true])->notes));
    }

    public function testAUserIsNotTrashedAsAContent(): void
    {
        $capability = $this->capability($this->createMock(ContentTrasher::class), true, false, true);

        self::assertStringContainsString('use access.user.delete', (string) $capability->validate(['content_id' => 1]));
    }

    public function testThePreviewWarnsAsTheTrashFormOfTheAdminDoes(): void
    {
        $trasher = $this->createMock(ContentTrasher::class);
        $trasher->method('warnings')->willReturn(['children' => 4, 'reverse_relations' => 2, 'drafts' => 1]);
        $notes = implode("\n", $this->capability($trasher)->preview(['content_id' => 1])->notes);

        self::assertStringContainsString('4 item(s) directly below it go to the trash', $notes);
        self::assertStringContainsString('2 content(s) point at it', $notes);
        self::assertStringContainsString('Its 1 draft(s)', $notes);
    }
}
