<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Suggestion;

/**
 * Builds the system and user prompts for a suggestion request.
 *
 * Pure string logic: no Ibexa, no Symfony, no HTTP.
 */
final class PromptBuilder
{
    private const BASE = 'You are a professional content writing assistant for a CMS.';

    public function systemPrompt(SuggestionRequest $request): string
    {
        $prompt = self::BASE . $this->contextLine($request) . $this->contentContext($request);

        if ($request->field === SupportedField::Image) {
            return $prompt . $this->altTextRules();
        }

        return $prompt . $request->field->format()->rules();
    }

    public function userPrompt(SuggestionRequest $request): string
    {
        if ($request->action === SuggestionAction::Translate) {
            return sprintf(
                "Translate the following %s content to %s. Only output the translated text, "
                . "nothing else. Preserve the tone and style of the original.\n\n%s",
                $request->sourceLanguage,
                $request->language,
                $request->sourceValue,
            );
        }

        $instruction = $request->action === SuggestionAction::Custom
            ? trim($request->prompt)
            : $request->action->instruction();

        if ($request->currentValue === '') {
            return $instruction;
        }

        $current = PromptText::truncate($request->currentValue, PromptText::MAX_CURRENT_VALUE_CHARS);

        return "{$instruction}\n\nCurrent content for context (do not repeat it unless asked):\n\"\"\"{$current}\"\"\"";
    }

    private function contextLine(SuggestionRequest $request): string
    {
        $context = '';

        if ($request->contentTypeName !== '') {
            $context .= sprintf(' The content type is "%s".', $request->contentTypeName);
        }
        if ($request->fieldName !== '') {
            $context .= sprintf(' You are writing for the field "%s".', $request->fieldName);
        }
        if (!self::isEnglish($request->language)) {
            $context .= sprintf(' Write in language code: %s.', $request->language);
        }

        return $context;
    }

    private function contentContext(SuggestionRequest $request): string
    {
        $lines = '';

        if ($request->contentTitle !== '') {
            $lines .= sprintf("\nContent title: \"%s\".", PromptText::scrub($request->contentTitle));
        }

        $siblings = '';
        foreach ($request->siblingFields as $sibling) {
            $label = PromptText::scrub($sibling->label);
            $value = PromptText::scrub(mb_substr($sibling->value, 0, PromptText::MAX_SIBLING_CHARS));
            if ($label !== '' && $value !== '') {
                $siblings .= sprintf("\n  - %s: \"%s\"", $label, $value);
            }
        }
        if ($siblings !== '') {
            $lines .= "\nOther fields already filled in this content item (use for context, do not repeat):" . $siblings;
        }

        return $lines === '' ? '' : "\n\nContent context:" . $lines;
    }

    private function altTextRules(): string
    {
        return "\n\nAlt text generation rules:"
            . "\n- Generate concise, descriptive alt text for screen readers."
            . sprintf("\n- Keep it under %d characters.", PromptText::MAX_ALT_TEXT_CHARS)
            . "\n- Be specific and factual about the image content."
            . "\n- Do not start with \"Image of\" or \"Photo of\"."
            . "\n- Focus on what the image shows, not its style."
            . "\n- Output ONLY the alt text, no quotes, no commentary."
            . "\n- Plain text only. No HTML.";
    }

    private static function isEnglish(string $language): bool
    {
        return str_starts_with(strtolower($language), 'en');
    }
}
