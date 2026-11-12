<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Worker;

use Masilia\Bundle\IbexaAi\Agent\Tool\ContentFactoryInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolName;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Agent\Worker\ExecutionResult;
use Masilia\Bundle\IbexaAi\Agent\Worker\Plan;
use Masilia\Bundle\IbexaAi\Agent\Worker\PlanExecutor;
use Masilia\IbexaAi\Tests\Agent\Block\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PlanExecutorTest extends TestCase
{
    /**
     * @param array<string, ToolResult> $toolResults map tool name → result
     */
    private function makeRegistry(array $toolResults): ToolRegistry
    {
        $registry = new ToolRegistry();
        foreach ($toolResults as $name => $result) {
            $tool = new class($name, $result) implements ToolInterface {
                public function __construct(private readonly string $name, private readonly ToolResult $result) {}

                public function getName(): string { return $this->name; }
                public function getDescription(): string { return 'mock'; }
                public function getParameters(): array { return []; }
                public function execute(array $params): ToolResult { return $this->result; }
            };
            $registry = $registry->register($tool);
        }
        return $registry;
    }

    public function testExecuteCreateContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_CONTENT => ToolResult::ok('Content created', [
                'content_id' => 100,
                'location_id' => 200,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 42,
            fields: ['title' => 'About Us'],
        ));

        self::assertTrue($result->success);
        self::assertSame(100, $result->contentId);
        self::assertSame(200, $result->locationId);
    }

    public function testTheLanguageOfThePlanReachesTheCreationTools(): void
    {
        $received = [];
        $registry = new ToolRegistry();
        foreach ([ToolName::CREATE_CONTENT, ToolName::CREATE_FOLDER] as $name) {
            $registry = $registry->register(new class($name, $received) implements ToolInterface {
                public function __construct(private readonly string $name, private array &$received) {}

                public function getName(): string { return $this->name; }
                public function getDescription(): string { return 'mock'; }
                public function getParameters(): array { return []; }
                public function execute(array $params): ToolResult
                {
                    $this->received[$this->name] = $params;

                    return ToolResult::ok('ok', ['content_id' => 1, 'location_id' => 2]);
                }
            });
        }

        $executor = new PlanExecutor($registry, new NullLogger());
        $executor->execute(new Plan(intent: Plan::INTENT_CREATE_CONTENT, contentType: 'page', parentLocationId: 42, fields: ['title' => 'Titre'], language: 'fre-FR'));
        $executor->execute(new Plan(intent: Plan::INTENT_CREATE_FOLDER, title: 'Dossier', parentLocationId: 42, language: 'fre-FR'));
        $executor->execute(new Plan(intent: Plan::INTENT_CREATE_FOLDER, title: 'Sans langue', parentLocationId: 42));

        self::assertSame('fre-FR', $received[ToolName::CREATE_CONTENT]['language']);
        self::assertArrayNotHasKey('language', $received[ToolName::CREATE_FOLDER], 'a plan with no language leaves the tool to its default (last call)');
    }

    public function testExecuteCreateContentToolFailure(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_CONTENT => ToolResult::error('Permission denied'),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 42,
            fields: ['title' => 'About Us'],
        ));

        self::assertFalse($result->success);
        self::assertStringContainsString('Permission denied', $result->message);
        self::assertSame('TOOL_FAILED', $result->errorCode);
    }

    public function testExecuteCreateFolderSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_FOLDER => ToolResult::ok('Folder created', [
                'content_id' => 50,
                'location_id' => 60,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_FOLDER,
            title: 'Media',
            parentLocationId: 1,
        ));

        self::assertTrue($result->success);
        self::assertSame(50, $result->contentId);
        self::assertSame(60, $result->locationId);
    }

    public function testExecuteTrashContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::TRASH_CONTENT => ToolResult::ok('Trashed', [
                'content_id' => 99,
                'location_id' => 100,
                'trashed' => true,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_TRASH_CONTENT,
            contentId: 99,
        ));

        self::assertTrue($result->success);
    }

    public function testExecuteRestoreContentSuccess(): void
    {
        $registry = $this->makeRegistry([
            ToolName::RESTORE_CONTENT => ToolResult::ok('Restored', [
                'restored' => [99, 100],
                'count' => 2,
            ]),
        ]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_RESTORE_CONTENT,
            fields: ['content_ids' => [99, 100]],
        ));

        self::assertTrue($result->success);
    }

    public function testExecuteReturnsToolUnavailableWhenToolMissing(): void
    {
        $registry = $this->makeRegistry([]);

        $executor = new PlanExecutor($registry, new NullLogger());
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_CONTENT,
            contentType: 'page',
            parentLocationId: 1,
            fields: ['title' => 'X'],
        ));

        self::assertFalse($result->success);
        self::assertSame('TOOL_UNAVAILABLE', $result->errorCode);
    }

    public function testExecuteUnknownIntent(): void
    {
        $registry = $this->makeRegistry([]);
        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(intent: 'fly_to_mars'));

        self::assertFalse($result->success);
        self::assertSame('UNKNOWN_INTENT', $result->errorCode);
    }

    public function testExecuteCreateItemsReturnsItemIds(): void
    {
        $registry = $this->makeRegistry([]);

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->method('warning');
        $logger->method('error');
        $logger->method('info');

        $contentService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $locationService = $this->createMock(\Ibexa\Contracts\Core\Repository\LocationService::class);
        $languageService = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $repository = $this->createMock(\Ibexa\Contracts\Core\Repository\Repository::class);
        $contentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $folderType = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType::class);
        $parentLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);

        $repository->method('getContentService')->willReturn($contentService);
        $repository->method('getLocationService')->willReturn($locationService);
        $repository->method('getContentTypeService')->willReturn($contentTypeService);
        $repository->method('getContentLanguageService')->willReturn($languageService);
        $languageService->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $contentInfo->method('__get')->willReturnMap([
            ['name', 'Info Cards'],
            ['mainLocation', $parentLocation],
        ]);
        $contentInfo->method('getName')->willReturn('Info Cards');
        $contentInfo->method('getMainLocation')->willReturn($parentLocation);
        $contentService->method('loadContentInfo')->with(200)->willReturn($contentInfo);

        $parentLocation->method('__get')->willReturnMap([
            ['id', 105],
            ['contentInfo', $contentInfo],
        ]);
        $parentLocation->method('getId')->willReturn(105);
        $parentLocation->method('getContentInfo')->willReturn($contentInfo);
        $locationService->method('loadLocation')->with(105)->willReturn($parentLocation);

        // Make loadContent throw to force the catch branch
        $contentService->method('loadContent')->willThrowException(new \RuntimeException('boom'));

        $locationList = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationList::class);
        $locationList->method('getIterator')->willReturn(new \ArrayIterator([]));
        $locationService->method('loadLocationChildren')->with($parentLocation)->willReturn($locationList);

        $folderType->method('__get')->willReturnMap([['id', 99]]);
        $contentTypeService->method('loadContentTypeByIdentifier')
            ->with(\Masilia\Bundle\IbexaAi\Support\ContentTypeId::FOLDER)
            ->willReturn($folderType);

        $createStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct::class);
        $locStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct::class);
        $draft = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $versionInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo::class);
        $published = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $folderContentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $folderLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);
        $folderLocation->method('__get')->willReturnMap([['id', 300]]);
        $folderLocation->method('getId')->willReturn(300);
        $folderContentInfo->method('getMainLocation')->willReturn($folderLocation);

        $contentService->method('newContentCreateStruct')->willReturn($createStruct);
        $locationService->method('newLocationCreateStruct')->willReturn($locStruct);
        $contentService->method('createContent')->willReturn($draft);
        $draft->method('__get')->willReturnCallback(function (string $name) use ($versionInfo) {
            return $name === 'versionInfo' ? $versionInfo : null;
        });
        $draft->method('getVersionInfo')->willReturn($versionInfo);
        $contentService->method('publishVersion')->willReturn($published);
        $published->method('__get')->willReturnMap([['contentInfo', $folderContentInfo]]);
        $published->method('getContentInfo')->willReturn($folderContentInfo);

        $itemContent1 = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $itemContent1->method('__get')->willReturnCallback(fn (string $name) => $name === 'id' ? 400 : null);
        $itemContent1->method('getId')->willReturn(400);
        $itemContent2 = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $itemContent2->method('__get')->willReturnCallback(fn (string $name) => $name === 'id' ? 401 : null);
        $itemContent2->method('getId')->willReturn(401);

        $callCount = 0;
        $contentFactory = new class($callCount, $itemContent1, $itemContent2) implements ContentFactoryInterface {
            private int $callCount = 0;
            public function __construct(
                private int $initialCount,
                private $itemContent1,
                private $itemContent2,
            ) {}
            public function createAndPublish(string $contentTypeIdentifier, array $parentLocationIds, array $attributes, string $languageCode): array
            {
                $this->callCount++;
                return [
                    'content' => $this->callCount === 1 ? $this->itemContent1 : $this->itemContent2,
                    'location' => null,
                ];
            }
            public function getCallCount(): int { return $this->callCount; }
        };

        $executor = new PlanExecutor(
            $registry,
            $logger,
            $repository,
            $contentTypeService,
            $contentFactory,
        );

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [
                ['type' => 'card_item', 'fields' => ['icon' => 'star', 'title' => 'Hello']],
                ['type' => 'card_item', 'fields' => ['icon' => 'check', 'title' => 'World']],
            ],
        ));

        self::assertTrue($result->success, $result->message);
        self::assertSame([400, 401], $result->data['item_ids']);
        self::assertSame(2, $contentFactory->getCallCount());
    }

    public function testExecuteCreateItemsFailsWhenExecutorNotConfigured(): void
    {
        $registry = $this->makeRegistry([]);

        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [['type' => 'card_item', 'fields' => []]],
        ));

        self::assertFalse($result->success);
        self::assertSame('EXECUTOR_NOT_CONFIGURED', $result->errorCode);
    }

    public function testExecuteCreateItemsSkipsItemsWithoutType(): void
    {
        $registry = $this->makeRegistry([]);
        $executor = new PlanExecutor($registry, new NullLogger());

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            items: [
                ['fields' => []],           // no type → skipped
                ['type' => '', 'fields' => []], // empty type → skipped
            ],
        ));

        // Without dependencies, the early "not configured" check fires first.
        self::assertFalse($result->success);
    }

    public function testCreateItemsWithLinkFieldDoesNotThrowWhenRepositoryMissing(): void
    {
        $registry = $this->makeRegistry([]);

        $executor = new PlanExecutor($registry, new NullLogger());

        // Without repository, linkItemsToParent returns early — items still created
        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            linkField: 'blocks',
            items: [
                ['fields' => []],  // no type → skipped
            ],
        ));

        // Without dependencies, the early "not configured" check fires first.
        self::assertFalse($result->success);
        self::assertSame('EXECUTOR_NOT_CONFIGURED', $result->errorCode);
    }

    public function testCreateItemsRejectsInvalidBlockTypeAgainstAllowedTypes(): void
    {
        $registry = $this->makeRegistry([]);

        $logger = new NullLogger();

        $contentService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $locationService = $this->createMock(\Ibexa\Contracts\Core\Repository\LocationService::class);
        $languageService = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $repository = $this->createMock(\Ibexa\Contracts\Core\Repository\Repository::class);
        $contentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $pageType = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType::class);
        $parentLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);

        $repository->method('getContentService')->willReturn($contentService);
        $repository->method('getLocationService')->willReturn($locationService);
        $repository->method('getContentTypeService')->willReturn($contentTypeService);
        $repository->method('getContentLanguageService')->willReturn($languageService);
        $languageService->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $contentInfo->method('__get')->willReturnCallback(function (string $name) use ($parentLocation) {
            return match ($name) {
                'name' => 'Page',
                'mainLocation' => $parentLocation,
                'contentTypeId' => 42,
                default => null,
            };
        });
        $contentInfo->method('getMainLocation')->willReturn($parentLocation);
        $contentInfo->method('getContentType')->willReturn($pageType);
        $contentInfo->method('getName')->willReturn('Page');
        $contentService->method('loadContentInfo')->with(200)->willReturn($contentInfo);

        $parentLocation->method('__get')->willReturnMap([['id', 105]]);
        $parentLocation->method('getId')->willReturn(105);

        $blocksFieldDef = new FakeFieldDefinition('blocks', 'ibexa_object_relation_list', ['selectionContentTypes' => ['hero_banner', 'text_block']]);
        $fieldDefCollection = new \Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection([$blocksFieldDef]);
        $pageType->method('getFieldDefinitions')->willReturn($fieldDefCollection);
        $contentTypeService->method('loadContentType')->with(42)->willReturn($pageType);

        $dummyFactory = new class implements ContentFactoryInterface {
            public function createAndPublish(string $contentTypeIdentifier, array $parentLocationIds, array $attributes, string $languageCode): array
            {
                return ['content' => null, 'location' => null];
            }
        };

        $executor = new PlanExecutor($registry, $logger, $repository, $contentTypeService, $dummyFactory);

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            linkField: 'blocks',
            items: [
                ['type' => 'hero_banner', 'fields' => ['title' => 'Hero']],
                ['type' => 'info_cards', 'fields' => ['title' => 'Cards']],  // invalid type
            ],
        ));

        self::assertFalse($result->success);
        self::assertSame('INVALID_RELATION_TARGET', $result->errorCode);
        self::assertStringContainsString('info_cards', $result->message);
        self::assertStringContainsString('hero_banner, text_block', $result->message);
    }

    public function testCreateItemsAcceptsValidBlockTypeAgainstAllowedTypes(): void
    {
        $registry = $this->makeRegistry([
            ToolName::CREATE_CONTENT => ToolResult::ok('Created', [
                'content_id' => 300,
                'location_id' => 400,
            ]),
        ]);

        $logger = new NullLogger();

        $contentService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentService::class);
        $contentTypeService = $this->createMock(\Ibexa\Contracts\Core\Repository\ContentTypeService::class);
        $locationService = $this->createMock(\Ibexa\Contracts\Core\Repository\LocationService::class);
        $languageService = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $repository = $this->createMock(\Ibexa\Contracts\Core\Repository\Repository::class);
        $contentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $pageType = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType::class);
        $parentLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);
        $folderType = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType::class);

        $repository->method('getContentService')->willReturn($contentService);
        $repository->method('getLocationService')->willReturn($locationService);
        $repository->method('getContentTypeService')->willReturn($contentTypeService);
        $repository->method('getContentLanguageService')->willReturn($languageService);
        $languageService->method('getDefaultLanguageCode')->willReturn('eng-GB');

        $contentInfo->method('__get')->willReturnCallback(function (string $name) use ($parentLocation) {
            return match ($name) {
                'name' => 'Page',
                'mainLocation' => $parentLocation,
                'contentTypeId' => 42,
                default => null,
            };
        });
        $contentInfo->method('getMainLocation')->willReturn($parentLocation);
        $contentInfo->method('getContentType')->willReturn($pageType);
        $contentInfo->method('getName')->willReturn('Page');
        $contentService->method('loadContentInfo')->willReturnMap([
            [200, $contentInfo],
        ]);

        $parentLocation->method('__get')->willReturnMap([['id', 105]]);
        $parentLocation->method('getId')->willReturn(105);
        $locationService->method('loadLocation')->with(105)->willReturn($parentLocation);

        $blocksFieldDef = new FakeFieldDefinition('blocks', 'ibexa_object_relation_list', ['selectionContentTypes' => ['hero_banner', 'text_block', 'info_cards']]);
        $fieldDefCollection = new \Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection([$blocksFieldDef]);
        $pageType->method('getFieldDefinitions')->willReturn($fieldDefCollection);
        $contentTypeService->method('loadContentType')->with(42)->willReturn($pageType);

        $contentService->method('loadContent')->willThrowException(new \RuntimeException('boom'));

        $locationList = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationList::class);
        $locationList->method('getIterator')->willReturn(new \ArrayIterator([]));
        $locationService->method('loadLocationChildren')->with($parentLocation)->willReturn($locationList);

        $folderType->method('__get')->willReturnMap([['id', 99]]);
        $contentTypeService->method('loadContentTypeByIdentifier')
            ->with(\Masilia\Bundle\IbexaAi\Support\ContentTypeId::FOLDER)
            ->willReturn($folderType);

        $createStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentCreateStruct::class);
        $locStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\LocationCreateStruct::class);
        $draft = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $versionInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\VersionInfo::class);
        $published = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $folderContentInfo = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo::class);
        $folderLocation = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);
        $folderLocation->method('__get')->willReturnMap([['id', 300]]);
        $folderLocation->method('getId')->willReturn(300);
        $folderContentInfo->method('getMainLocation')->willReturn($folderLocation);

        $contentService->method('newContentCreateStruct')->willReturn($createStruct);
        $locationService->method('newLocationCreateStruct')->willReturn($locStruct);
        $contentService->method('createContent')->willReturn($draft);
        $draft->method('__get')->willReturnCallback(function (string $name) use ($versionInfo) {
            return $name === 'versionInfo' ? $versionInfo : null;
        });
        $draft->method('getVersionInfo')->willReturn($versionInfo);
        $contentService->method('publishVersion')->willReturn($published);
        $published->method('__get')->willReturnMap([['contentInfo', $folderContentInfo]]);
        $published->method('getContentInfo')->willReturn($folderContentInfo);

        $contentService->method('createContentDraft')->willReturn($draft);
        $updateStruct = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\ContentUpdateStruct::class);
        $contentService->method('newContentUpdateStruct')->willReturn($updateStruct);
        $contentService->method('updateContent')->willReturn($draft);

        $itemContent = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $itemContent->method('__get')->willReturnCallback(fn (string $name) => $name === 'id' ? 500 : null);
        $itemContent->method('getId')->willReturn(500);

        $contentFactory = new class($itemContent) implements ContentFactoryInterface {
            public function __construct(private $itemContent) {}
            public function createAndPublish(string $contentTypeIdentifier, array $parentLocationIds, array $attributes, string $languageCode): array
            {
                return ['content' => $this->itemContent, 'location' => null];
            }
        };

        $executor = new PlanExecutor($registry, $logger, $repository, $contentTypeService, $contentFactory);

        $result = $executor->execute(new Plan(
            intent: Plan::INTENT_CREATE_ITEMS,
            contentId: 200,
            linkField: 'blocks',
            items: [
                ['type' => 'hero_banner', 'fields' => ['title' => 'Hero']],
                ['type' => 'text_block', 'fields' => ['body' => 'Text']],
            ],
        ));

        self::assertTrue($result->success, $result->message);
    }
}
