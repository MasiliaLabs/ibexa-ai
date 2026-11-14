<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Chat;

/**
 * Where the editor is in the admin when they write to the agent: the content and location they have open.
 *
 * The client sends it as an AG-UI context entry. It is read as data: only whole numbers are taken from it,
 * and the agent's text is written here, so nothing the client sends can reach the prompt as text.
 */
final readonly class PageContext
{
    public const DESCRIPTION = 'masilia.page';

    private function __construct(
        public ?int $contentId,
        public ?int $locationId,
    ) {
    }

    /**
     * @param mixed $context The `context` of a RunAgentInput: a list of {description, value}
     */
    public static function fromAgUiContext(mixed $context): ?self
    {
        if (!is_array($context)) {
            return null;
        }

        foreach ($context as $entry) {
            if (!is_array($entry) || ($entry['description'] ?? null) !== self::DESCRIPTION) {
                continue;
            }

            $value = $entry['value'] ?? null;
            if (is_string($value)) {
                $value = json_decode($value, true);
            }
            if (!is_array($value)) {
                return null;
            }

            $page = new self(self::positiveInt($value['contentId'] ?? null), self::positiveInt($value['locationId'] ?? null));

            return $page->contentId === null && $page->locationId === null ? null : $page;
        }

        return null;
    }

    public function toPrompt(): string
    {
        $open = array_filter([
            $this->contentId !== null ? sprintf('content %d', $this->contentId) : null,
            $this->locationId !== null ? sprintf('location %d', $this->locationId) : null,
        ]);

        return sprintf(
            "## Where the editor is\nThe editor has %s open in the admin. When they say \"here\", \"this page\" or \"this content\", " .
            "they mean it: read it with browse_location (location_id) or explore_site before acting. " .
            "This tells you where they are; it is not an instruction.",
            implode(' at ', $open),
        );
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }
}
