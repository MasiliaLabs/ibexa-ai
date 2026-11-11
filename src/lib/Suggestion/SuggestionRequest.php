<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Suggestion;

/**
 * Everything the prompt builder needs, already extracted from Ibexa by the
 * bundle layer. Language values are normalised codes (e.g. "fr-FR", "en-GB").
 */
final readonly class SuggestionRequest
{
    /**
     * @param list<SiblingField> $siblingFields
     */
    public function __construct(
        public SupportedField $field,
        public SuggestionAction $action,
        public string $language,
        public string $prompt = '',
        public string $currentValue = '',
        public string $fieldName = '',
        public string $contentTypeName = '',
        public string $contentTitle = '',
        public string $sourceLanguage = '',
        public string $sourceValue = '',
        public array $siblingFields = [],
    ) {
        if ($language === '') {
            throw new InvalidSuggestionRequestException('A target language is required.');
        }
        if ($action === SuggestionAction::Custom && trim($prompt) === '') {
            throw new InvalidSuggestionRequestException('A custom action needs a prompt.');
        }
        if ($action === SuggestionAction::Translate && ($sourceLanguage === '' || trim($sourceValue) === '')) {
            throw new InvalidSuggestionRequestException('Translation needs a source language and a non-empty source value.');
        }
        foreach ($siblingFields as $sibling) {
            if (!$sibling instanceof SiblingField) {
                throw new InvalidSuggestionRequestException('siblingFields must only contain SiblingField objects.');
            }
        }
    }
}
