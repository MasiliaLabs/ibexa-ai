<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\Bundle\IbexaAi\Agent\Tool\Content\LoadContentTypeTool;
use Masilia\IbexaAi\Tests\Agent\Block\BlockCatalogFactoryTrait;
use Masilia\IbexaAi\Tests\Agent\Block\FakeFieldDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The tool reads a content type to describe its fields to the model. It catches every exception, so a broken
 * read shows up only as a failed tool result: these tests use real value objects (whose magic __get throws on
 * an unknown property) instead of mocks that would answer null.
 */
final class LoadContentTypeToolTest extends TestCase
{
    private function tool(): LoadContentTypeTool
    {
        // FakeFieldDefinition lives in the file of this trait: loading the trait loads it.
        trait_exists(BlockCatalogFactoryTrait::class);

        $contentType = new ContentType([
            'identifier' => 'event',
            'names' => ['eng-GB' => 'Event'],
            'prioritizedLanguages' => ['eng-GB'],
            'mainLanguageCode' => 'eng-GB',
            'isContainer' => false,
            'contentTypeGroups' => [new ContentTypeGroup(['identifier' => 'Content'])],
            'fieldDefinitions' => new FieldDefinitionCollection([
                new FakeFieldDefinition('title', 'ibexa_string', [], [], true),
                new FakeFieldDefinition('starts_at', 'ibexa_datetime'),
            ]),
        ]);

        $service = $this->createMock(ContentTypeService::class);
        $service->method('loadContentTypeByIdentifier')->with('event')->willReturn($contentType);
        $repository = $this->createMock(Repository::class);
        $repository->method('getContentTypeService')->willReturn($service);

        return new LoadContentTypeTool($repository, new NullLogger());
    }

    public function testItDescribesTheTypeAndItsFields(): void
    {
        $result = $this->tool()->execute(['identifier' => 'event']);

        self::assertTrue($result->success, $result->message);
        self::assertSame('event', $result->data['identifier']);
        self::assertSame('Event', $result->data['name']);
        self::assertSame('Content', $result->data['group']);
        self::assertFalse($result->data['is_container']);
        self::assertSame(
            [
                'title' => ['type' => 'ibexa_string', 'name' => 'title', 'required' => true, 'identifier' => 'title'],
                'starts_at' => ['type' => 'ibexa_datetime', 'name' => 'starts_at', 'required' => false, 'identifier' => 'starts_at'],
            ],
            $result->data['fields'],
        );
    }
}
