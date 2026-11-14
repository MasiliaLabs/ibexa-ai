<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Read;

use Ibexa\Contracts\Core\Repository\Repository;
use InvalidArgumentException;
use Masilia\Bundle\IbexaAi\Ibexa\Field\Text\FieldTexts;

/**
 * Reads one content by id, remote id or location, with the value of each field as text, in one language: the one asked for,
 * or the content's main language (not the repository default, which may not have its fields).
 */
readonly class ContentLoader
{
    public function __construct(
        private Repository $repository,
        private FieldTexts $stringifiers,
    ) {
    }

    /**
     * @param array<string, mixed> $params content_id, remote_id or location_id, and language
     *
     * @return array<string, mixed> content_id, content_type, name, remote_id, main_location_id, languages, language, fields
     *
     * @throws InvalidArgumentException When none of the three ids is given
     */
    public function load(array $params): array
    {
        $contents = $this->repository->getContentService();
        $requestedLanguage = $params['language'] ?? null;
        // A language asked for is loaded alone. Otherwise the main translation is loaded, and the fields are read in the
        // content's main language: this prevents empty fields when the content is not in the repository's default language.
        $languages = $requestedLanguage !== null ? [$requestedLanguage] : null;

        if (isset($params['content_id'])) {
            $content = $contents->loadContent((int) $params['content_id'], $languages);
        } elseif (isset($params['remote_id'])) {
            $content = $contents->loadContentByRemoteId($params['remote_id'], $languages);
        } elseif (isset($params['location_id'])) {
            $location = $this->repository->getLocationService()->loadLocation((int) $params['location_id']);
            $content = $contents->loadContent($location->getContentId(), $languages);
        } else {
            throw new InvalidArgumentException('Must provide content_id, remote_id, or location_id');
        }

        $languageCode = $requestedLanguage ?? $content->getContentInfo()->getMainLanguageCode();
        $fields = [];
        foreach ($content->getFieldsByLanguage($languageCode) as $field) {
            $definition = $content->getContentType()->getFieldDefinition($field->getFieldDefinitionIdentifier());
            $fields[$field->getFieldDefinitionIdentifier()] = $this->stringifiers->toString($field, $definition);
        }

        return [
            'content_id' => $content->getId(),
            'content_type' => $content->getContentInfo()->contentTypeId,
            'name' => $content->getContentInfo()->getName(),
            'remote_id' => $content->getContentInfo()->remoteId,
            'main_location_id' => $content->getContentInfo()->getMainLocationId(),
            // Which translations exist, and which one the fields are in.
            'languages' => array_values($content->getVersionInfo()->getLanguageCodes()),
            'language' => $languageCode,
            'fields' => $fields,
        ];
    }
}
