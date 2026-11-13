<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os;

/**
 * What a change will do, written for the person who approves it. Computed without writing anything.
 *
 * It travels as lines of "Label: value" (see {@see toApprovalText}) which the chat reads into a card.
 */
final readonly class Preview
{
    public const CLOSING = 'The change runs only if you approve it.';

    /**
     * @param list<array{0: string, 1: string}> $rows  (label, value) pairs
     * @param list<string>                      $notes Sentences under the rows: warnings, consequences
     */
    public function __construct(
        public string $title,
        public array $rows = [],
        public array $notes = [],
    ) {
    }

    public function toApprovalText(Risk $risk): string
    {
        $lines = [sprintf('Action: %s', self::line($this->title)), sprintf('Risk: %s', $risk->value)];
        foreach ($this->rows as [$label, $value]) {
            $lines[] = sprintf('%s: %s', self::line($label), self::line($value));
        }
        foreach ($this->notes as $note) {
            $lines[] = self::line($note);
        }

        return implode("\n", $lines) . "\n\n" . self::CLOSING;
    }

    /** One line: what is shown is never able to start a new row of the card. */
    private static function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
