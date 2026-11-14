<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct;
use Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo as VersionInfoContract;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaAi\Ibexa\Rules\LocationRules;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\PublishCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\SetVisibilityCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Location\CopyCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Location\CopySubtreeCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Location\MoveCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Location\RemoveCapability;
use Masilia\Bundle\IbexaAi\Os\Capability\Location\SwapCapability;
use PHPUnit\Framework\TestCase;

/**
 * The locations and contents the capabilities act on, with the rules of the Ibexa admin: what its forms refuse, they refuse.
 * Each test names the action of the admin it follows.
 */
final class LocationCapabilitiesTest extends TestCase
{
    /** @var array<int, Location> */
    private array $locations = [];
    private LocationService $locationService;
    private ContentService $contentService;
    private Repository $repository;

    protected function setUp(): void
    {
        $this->locations = [];
        $this->locationService = $this->createMock(LocationService::class);
        $this->locationService->method('loadLocation')->willReturnCallback(fn (int $id): Location => $this->locations[$id] ?? throw new \RuntimeException('no location ' . $id));
        $this->contentService = $this->createMock(ContentService::class);
        $this->repository = $this->createMock(Repository::class);
        $this->repository->method('getLocationService')->willReturn($this->locationService);
        $this->repository->method('getContentService')->willReturn($this->contentService);
    }

    private function location(int $id, int $depth, bool $container = true, string $name = 'Item'): Location
    {
        $type = $this->createMock(ContentType::class);
        $type->method('isContainer')->willReturn($container);
        $info = $this->createMock(ContentInfo::class);
        $info->method('getContentType')->willReturn($type);
        $info->method('getName')->willReturn($name);
        $content = $this->createMock(Content::class);
        $content->method('getContentType')->willReturn($type);
        $content->method('getName')->willReturn($name);
        $location = $this->createMock(Location::class);
        $location->method('getDepth')->willReturn($depth);
        $location->method('getId')->willReturn($id);
        $location->method('getContent')->willReturn($content);
        $location->method('getContentInfo')->willReturn($info);
        $location->method('getPathString')->willReturn($this->path($id, $depth));

        return $this->locations[$id] = $location;
    }

    /** Paths of the fixtures: 1 is the root, 10 is under it, 100 under 10, 20 is another branch. */
    private function path(int $id, int $depth): string
    {
        return match ($id) {
            1 => '/1/',
            10 => '/1/10/',
            100 => '/1/10/100/',
            20 => '/1/20/',
            default => '/1/' . $id . '/',
        };
    }

    private function rules(int $limit = 100): LocationRules
    {
        $config = $this->createMock(ConfigResolverInterface::class);
        $config->method('getParameter')->willReturn($limit);

        return new LocationRules($this->locationService, $config);
    }

    public function testMovingARootLocationIsRefused(): void
    {
        // Admin: location move (LocationController::moveAction), which core refuses for a root.
        $this->location(1, 1);
        $this->location(20, 2);

        self::assertSame('A root location cannot be moved.', (new MoveCapability($this->repository, $this->rules()))->validate(['location_id' => 1, 'new_parent_location_id' => 20]));
    }

    public function testMovingBelowAnItemThatIsNotAContainerOrBelowItselfIsRefused(): void
    {
        $this->location(10, 2);
        $this->location(100, 3, false);
        $this->location(20, 2, true);
        $move = new MoveCapability($this->repository, $this->rules());

        self::assertSame('Cannot move a location to a parent that is not a container.', $move->validate(['location_id' => 20, 'new_parent_location_id' => 100]));
        $this->location(100, 3, true);
        self::assertSame('A location cannot be moved under itself or one of its descendants.', $move->validate(['location_id' => 10, 'new_parent_location_id' => 100]));
        self::assertNull($move->validate(['location_id' => 100, 'new_parent_location_id' => 20]));
    }

    public function testSwappingOrRemovingARootIsRefused(): void
    {
        $this->location(1, 1);
        $this->location(20, 2);

        self::assertSame('A root location cannot be swapped.', (new SwapCapability($this->repository, $this->rules()))->validate(['location_id' => 20, 'other_location_id' => 1]));
        self::assertSame('A root location cannot be removed.', (new RemoveCapability($this->repository, $this->rules()))->validate(['location_id' => 1]));
    }

    public function testCopyCopiesTheContentAloneBelowAContainer(): void
    {
        // Admin: location copy (LocationController::copyAction) is ContentService::copyContent of one content.
        $source = $this->location(100, 3, false, 'Page');
        $this->location(20, 2, true);
        $this->location(55, 3, false);
        $struct = $this->createMock(LocationCreateStruct::class);
        $this->locationService->expects(self::once())->method('newLocationCreateStruct')->with(20)->willReturn($struct);
        $copied = $this->createMock(ContentInfo::class);
        $copied->method('getMainLocationId')->willReturn(555);
        $copied->method('getId')->willReturn(9);
        $copy = $this->createMock(Content::class);
        $copy->method('getContentInfo')->willReturn($copied);
        $this->contentService->expects(self::once())->method('copyContent')->with($source->getContentInfo(), $struct)->willReturn($copy);
        $this->locationService->expects(self::never())->method('copySubtree');
        $capability = new CopyCapability($this->repository, $this->rules());

        $result = $capability->execute(['location_id' => 100, 'target_parent_location_id' => 20]);

        self::assertSame(555, $result->data['location_id']);
        self::assertSame('Cannot copy a location to a parent that is not a container.', $capability->validate(['location_id' => 20, 'target_parent_location_id' => 55]));
    }

    public function testCopySubtreeFollowsTheFourRulesOfTheAdminForm(): void
    {
        // Admin: LocationCopySubtreeData: within the limit, not a root, to a container, not into itself.
        $this->location(1, 1);
        $this->location(10, 2);
        $this->location(100, 3, true);
        $this->location(20, 2, true);
        $this->location(55, 3, false);
        $this->locationService->method('getSubtreeSize')->willReturnCallback(static fn (Location $location): int => $location->getId() === 10 ? 500 : 3);
        $copy = new CopySubtreeCapability($this->repository, $this->rules(100));

        self::assertSame('Copy subtree limit exceeded. Current limit: 100.', $copy->validate(['location_id' => 10, 'target_parent_location_id' => 20]));
        self::assertSame('A root location cannot be copied.', $copy->validate(['location_id' => 1, 'target_parent_location_id' => 20]));
        self::assertSame('Cannot copy a subtree to a parent that is not a container.', $copy->validate(['location_id' => 100, 'target_parent_location_id' => 55]));
        self::assertSame('Cannot copy a subtree to its own descendant location.', $copy->validate(['location_id' => 100, 'target_parent_location_id' => 100]));
        self::assertNull($copy->validate(['location_id' => 100, 'target_parent_location_id' => 20]));
        self::assertNull((new CopySubtreeCapability($this->repository, $this->rules(-1)))->validate(['location_id' => 10, 'target_parent_location_id' => 20]), 'no limit at all');
    }

    public function testAContentIsHiddenOrRevealedEverywhereAndNeverTwice(): void
    {
        // Admin: content visibility (ContentController::updateVisibilityAction): hideContent / revealContent, "already hidden/visible".
        $shown = $this->createMock(ContentInfo::class);
        $shown->method('getName')->willReturn('Home');
        $hidden = clone $shown;
        $infos = [1 => $this->info(1, false), 2 => $this->info(2, true)];
        $this->contentService->method('loadContentInfo')->willReturnCallback(static fn (int $id): ContentInfo => $infos[$id]);
        $capability = new SetVisibilityCapability($this->repository);

        self::assertNull($capability->validate(['content_id' => 1, 'visible' => false]));
        self::assertSame('"Item 1" is already visible.', $capability->validate(['content_id' => 1, 'visible' => true]));
        self::assertSame('"Item 2" is already hidden.', $capability->validate(['content_id' => 2, 'visible' => false]));

        $this->contentService->expects(self::once())->method('hideContent')->with($infos[1]);
        $this->contentService->expects(self::once())->method('revealContent')->with($infos[2]);
        self::assertTrue($capability->execute(['content_id' => 1, 'visible' => false])->success);
        self::assertTrue($capability->execute(['content_id' => 2, 'visible' => true])->success);
    }

    private function info(int $id, bool $hidden): ContentInfo
    {
        // ContentInfo exposes isHidden as a property.
        $info = new class($id, $hidden) extends ContentInfo {
            public function __construct(int $id, bool $hidden)
            {
                parent::__construct(['id' => $id, 'name' => 'Item ' . $id, 'isHidden' => $hidden]);
            }
        };

        return $info;
    }

    public function testADraftIsPublishedTheLatestOneUnlessOneIsNamed(): void
    {
        // Admin: "Publish" of the editing form is ContentService::publishVersion of the draft.
        $info = $this->info(7, false);
        $v2 = new VersionInfo(['versionNo' => 2, 'contentInfo' => $info]);
        $v3 = new VersionInfo(['versionNo' => 3, 'contentInfo' => $info]);
        $this->contentService->method('loadContentInfo')->willReturn($info);
        $this->contentService->method('loadVersions')->with($info, VersionInfoContract::STATUS_DRAFT)->willReturn([$v2, $v3]);
        $published = $this->createMock(Content::class);
        $published->method('getContentInfo')->willReturn($info);
        $published->method('getVersionInfo')->willReturn($v3);
        $this->contentService->expects(self::exactly(2))->method('publishVersion')->willReturnCallback(static function (VersionInfo $version) use ($published, $v2, $v3): Content {
            self::assertContains($version, [$v2, $v3]);

            return $published;
        });
        $capability = new PublishCapability($this->repository);

        self::assertNull($capability->validate(['content_id' => 7]));
        self::assertNull($capability->validate(['content_id' => 7, 'version_no' => 2]));
        self::assertSame('"Item 7" has no draft 9.', $capability->validate(['content_id' => 7, 'version_no' => 9]));
        $capability->execute(['content_id' => 7]);
        $capability->execute(['content_id' => 7, 'version_no' => 2]);
    }

    public function testAContentWithoutADraftSaysHowToStartOne(): void
    {
        $info = $this->info(8, false);
        $this->contentService->method('loadContentInfo')->willReturn($info);
        $this->contentService->method('loadVersions')->willReturn([]);

        self::assertStringContainsString('content.draft.create', (string) (new PublishCapability($this->repository))->validate(['content_id' => 8]));
    }
}
