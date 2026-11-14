<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Ibexa\Schema;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Values\ContentType\ContentTypeGroup;
use Ibexa\Core\Repository\Values\ContentType\ContentType as CoreContentType;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition as CoreFieldDefinition;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinitionCollection;
use Masilia\Bundle\IbexaAi\Ibexa\Schema\ContentTypeSchemas;

/**
 * Test double built on Ibexa's own value object: only properties are set, no method is overridden,
 * so it keeps matching the Ibexa 5 signatures.
 *
 * @internal test-only
 */
final class FakeContentType extends CoreContentType
{
    public function __construct(
        string $identifier,
        string $name,
        FieldDefinitionCollection $fieldDefinitions,
    ) {
        parent::__construct([
            'identifier' => $identifier,
            'names' => ['eng-GB' => $name],
            'descriptions' => [],
            'mainLanguageCode' => 'eng-GB',
            'fieldDefinitions' => $fieldDefinitions,
        ]);
    }
}

/**
 * Concrete subclass of the abstract NotFoundException for testing.
 */
final class TestNotFoundException extends \Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException
{
}

/**
 * @internal test-only
 */
final class FakeFieldDefinition extends CoreFieldDefinition
{
    /**
     * @param array<string, mixed> $fieldSettings
     * @param array<string, mixed> $validatorConfiguration
     */
    public function __construct(
        string $identifier,
        string $fieldTypeIdentifier,
        array $fieldSettings = [],
        array $validatorConfiguration = [],
        bool $isRequired = false,
        string $description = '',
        bool $isTranslatable = false,
    ) {
        parent::__construct([
            'identifier' => $identifier,
            'fieldTypeIdentifier' => $fieldTypeIdentifier,
            'fieldSettings' => $fieldSettings,
            'validatorConfiguration' => $validatorConfiguration,
            'isRequired' => $isRequired,
            'isTranslatable' => $isTranslatable,
            'names' => ['eng-GB' => $identifier],
            'descriptions' => $description !== '' ? ['eng-GB' => $description] : [],
            'mainLanguageCode' => 'eng-GB',
        ]);
    }
}

trait BlockCatalogFactoryTrait
{
    /**
     * Build a real {@see ContentTypeSchemas} from a plain array of content type definitions.
     *
     * ContentTypeSchemas is final, so tests cannot mock it: it is backed by a stubbed ContentTypeService.
     *
     * Field definitions can be declared as:
     *   'field_id' => 'ibexa_string'
     * or, when field settings / validator / required are needed:
     *   'field_id' => [
     *     'type' => 'ibexa_string',
     *     'settings' => ['...'],
     *     'validator' => ['StringLengthValidator' => ['minStringLength' => 1]],
     *     'required' => true,
     *   ]
     *
     * @param array<string, array{name: string, fields: array<string, string|array{type: string, settings?: array<string, mixed>, validator?: array<string, mixed>, required?: bool}>}> $types
     */
    private function createBlockCatalog(array $types): ContentTypeSchemas
    {
        $contentTypes = [];
        foreach ($types as $identifier => $type) {
            $fieldDefinitions = [];
            foreach ($type['fields'] as $fieldIdentifier => $fieldConfig) {
                if (is_string($fieldConfig)) {
                    $fieldConfig = ['type' => $fieldConfig];
                }

                $fieldDefinitions[] = new FakeFieldDefinition(
                    $fieldIdentifier,
                    $fieldConfig['type'],
                    $fieldConfig['settings'] ?? [],
                    $fieldConfig['validator'] ?? [],
                    $fieldConfig['required'] ?? false,
                    $fieldConfig['description'] ?? '',
                    $fieldConfig['translatable'] ?? false,
                );
            }

            $contentTypes[$identifier] = new FakeContentType($identifier, $type['name'], new FieldDefinitionCollection($fieldDefinitions));
        }

        $contentTypeService = $this->createStub(ContentTypeService::class);
        $contentTypeService->method('loadContentTypeByIdentifier')->willReturnCallback(
            static fn (string $identifier) => $contentTypes[$identifier] ?? throw new TestNotFoundException(),
        );
        $group = $this->createStub(ContentTypeGroup::class);
        $contentTypeService->method('loadContentTypeGroups')->willReturn([$group]);
        $contentTypeService->method('loadContentTypes')->willReturn(array_values($contentTypes));

        return new ContentTypeSchemas($contentTypeService);
    }
}
