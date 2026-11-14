<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Worker;

use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Agent\Attachment\AttachmentStore;
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

    private function imageExecutor(?array &$received, int $ownerId = 14, int $userId = 14): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE app_ai_attachment (
            id CHAR(36) PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(160) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL,
            width INT NOT NULL, height INT NOT NULL, filename VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL)');
        $store = new AttachmentStore($connection, sys_get_temp_dir() . '/ai-exec-test-' . bin2hex(random_bytes(4)));
        $path = tempnam(sys_get_temp_dir(), 'img') ?: '';
        imagepng(imagecreatetruecolor(64, 32), $path);
        $attachment = $store->store($path, 'logo.png', $ownerId);

        $content = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Content::class);
        $content->method('getId')->willReturn(900);
        $location = $this->createMock(\Ibexa\Contracts\Core\Repository\Values\Content\Location::class);
        $location->method('getId')->willReturn(901);

        $factory = new class($content, $location, $received) implements ContentFactoryInterface {
            public function __construct(private $content, private $location, private ?array &$received) {}

            public function createAndPublish(string $contentTypeIdentifier, array $parentLocationIds, array $attributes, string $languageCode): array
            {
                $this->received = compact('contentTypeIdentifier', 'parentLocationIds', 'attributes', 'languageCode');

                return ['content' => $this->content, 'location' => $this->location];
            }
        };

        $languages = $this->createMock(\Ibexa\Contracts\Core\Repository\LanguageService::class);
        $languages->method('getDefaultLanguageCode')->willReturn('eng-GB');
        $repository = $this->createMock(\Ibexa\Contracts\Core\Repository\Repository::class);
        $repository->method('getContentLanguageService')->willReturn($languages);
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference($userId));

        $executor = new PlanExecutor(new ToolRegistry(), new NullLogger(), $repository, $factory, $store, $permissions);

        return [$executor, $attachment->id, $store->path($attachment)];
    }

    public function testAddImageCreatesAnImageContentInTheFolderWithItsAlternativeText(): void
    {
        $received = null;
        [$executor, $id, $path] = $this->imageExecutor($received);

        $result = $executor->execute(new Plan(intent: Plan::INTENT_ADD_IMAGE, title: 'Company logo', parentLocationId: 1020, description: 'Blue logo', attachmentId: $id));

        self::assertTrue($result->success);
        self::assertSame([900, 901], [$result->contentId, $result->locationId]);
        self::assertSame('image', $received['contentTypeIdentifier']);
        self::assertSame([1020], $received['parentLocationIds']);
        self::assertSame('eng-GB', $received['languageCode']);
        self::assertSame('Company logo', $received['attributes']['name']);
        $value = $received['attributes']['image'];
        self::assertInstanceOf(ImageValue::class, $value);
        self::assertSame($path, $value->inputUri);
        self::assertSame('Blue logo', $value->alternativeText);
        self::assertSame('logo.png', $value->fileName);
    }

    public function testAddImageNamesTheContentAfterTheFileWhenThePlanGivesNoTitle(): void
    {
        $received = null;
        [$executor, $id] = $this->imageExecutor($received);

        $executor->execute(new Plan(intent: Plan::INTENT_ADD_IMAGE, parentLocationId: 1020, attachmentId: $id));

        self::assertSame('logo', $received['attributes']['name']);
    }

    public function testAddImageDoesNotUseAnotherUsersImage(): void
    {
        $received = null;
        [$executor, $id] = $this->imageExecutor($received, ownerId: 15, userId: 14);

        $result = $executor->execute(new Plan(intent: Plan::INTENT_ADD_IMAGE, parentLocationId: 1020, attachmentId: $id));

        self::assertFalse($result->success);
        self::assertNull($received, 'nothing was created');
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
}
