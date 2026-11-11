<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Suggestion;

/**
 * Cleans a model answer before it is handed back to the editor.
 *
 * Models often wrap output in code fences or quotes despite instructions.
 * This removes those wrappers without touching the content itself.
 */
final class ResponseSanitizer
{
    public function clean(string $raw, FieldFormat $format): string
    {
        $text = trim($raw);
        $text = $this->stripCodeFence($text);

        if ($format === FieldFormat::PlainText) {
            $text = trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $text) ?? $text);
            $text = $this->stripMatchingQuotes($text);
        }

        return $text;
    }

    private function stripCodeFence(string $text): string
    {
        if (preg_match('/^```[a-zA-Z0-9_-]*\s*\n?(.*?)\n?```$/s', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $text;
    }

    private function stripMatchingQuotes(string $text): string
    {
        if (mb_strlen($text) >= 2) {
            $first = mb_substr($text, 0, 1);
            $last = mb_substr($text, -1);
            $pairs = ['"' => '"', "'" => "'", '«' => '»', '“' => '”'];
            if (isset($pairs[$first]) && $pairs[$first] === $last) {
                return trim(mb_substr($text, 1, -1));
            }
        }

        return $text;
    }
}
