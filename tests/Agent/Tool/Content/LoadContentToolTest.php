<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool\Content;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\ContentInfo;
use Ibexa\Contracts\Core\Repository\Values\Content\Field;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\Repository\Values\Content\VersionInfo;
use Masilia\Bundle\IbexaAi\Agent\Tool\Content\LoadContentTool;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class LoadContentToolTest extends TestCase
{
    public function testFieldsAreKeyedByFieldIdentifierAndLanguagesAreReported(): void
    {
        $tool = $this->toolReturning($this->content(
            languages: ['fre-FR'],
            mainLanguage: 'fre-FR',
            fields: ['title' => 'Nos objectifs', 'description' => 'Trois objectifs.'],
        ));

        $result = $tool->execute(['content_id' => 1295]);

        self::assertTrue($result->success);
        self::assertSame(['title' => 'Nos objectifs', 'description' => 'Trois objectifs.'], $result->data['fields']);
        self::assertSame(['fre-FR'], $result->data['languages']);
        self::assertSame('fre-FR', $result->data['language']);
    }

    public function testAContentWithSeveralTranslationsListsThemAll(): void
    {
        $tool = $this->toolReturning($this->content(
            languages: ['eng-GB', 'fre-FR'],
            mainLanguage: 'fre-FR',
            fields: ['title' => 'Conditions'],
        ));

        $result = $tool->execute(['content_id' => 1285]);

        self::assertSame(['eng-GB', 'fre-FR'], $result->data['languages']);
    }

    private function toolReturning(Content $content): LoadContentTool
    {
        $contentService = $this->createMock(ContentService::class);
        $contentService->method('loadContent')->willReturn($content);

        $repository = $this->createMock(Repository::class);
        $repository->method('getContentService')->willReturn($contentService);

        $stringifier = new class() implements FieldValueStringifierInterface {
            public static function getSupportedFieldTypes(): array
            {
                return ['ibexa_string'];
            }

            public function toString(Field $field, FieldDefinition $fieldDefinition): string
            {
                return (string) $field->value;
            }
        };

        return new LoadContentTool($repository, new NullLogger(), new FieldValueStringifierRegistry([$stringifier]));
    }

    /**
     * @param list<string> $languages
     * @param array<string, string> $fields field identifier => value
     */
    private function content(array $languages, string $mainLanguage, array $fields): Content
    {
        $fieldDefinition = $this->createMock(FieldDefinition::class);
        $fieldDefinition->method('getFieldTypeIdentifier')->willReturn('ibexa_string');

        $contentType = $this->createMock(ContentType::class);
        $contentType->method('getFieldDefinition')->willReturn($fieldDefinition);

        $fieldObjects = [];
        foreach ($fields as $identifier => $value) {
            $fieldObjects[] = new Field([
                'fieldDefIdentifier' => $identifier,
                'languageCode' => $mainLanguage,
                'fieldTypeIdentifier' => 'ibexa_string',
                'value' => $value,
            ]);
        }

        $contentInfo = new ContentInfo([
            'id' => 1295,
            'name' => 'Nos objectifs',
            'mainLanguageCode' => $mainLanguage,
            'contentTypeId' => 42,
            'remoteId' => 'remote-1295',
            'mainLocationId' => 1288,
        ]);

        $content = $this->createMock(Content::class);
        $content->method('getFieldsByLanguage')->willReturn($fieldObjects);
        $content->method('getContentType')->willReturn($contentType);
        $content->method('getVersionInfo')->willReturn(new VersionInfo(['languageCodes' => $languages]));
        $content->method('getContentInfo')->willReturn($contentInfo);
        $content->method('getId')->willReturn(1295);

        return $content;
    }
}
