<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Ibexa\Field\Input;

use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\Author\Author;
use Ibexa\Core\FieldType\Author\Value as AuthorValue;

/**
 * Transforms LLM output into Ibexa Author\Value for ibexa_author fields (the sender and the recipients of a form, an article's authors).
 *
 * Accepts: "Name <email>", "email" or "Name"; an object {name, email}; or a list of any of those.
 * Returns: an Author\Value holding an Author for each.
 */
readonly class AuthorInput implements FieldInput
{
    public function getFieldTypeIdentifier(): string
    {
        return 'ibexa_author';
    }

    public function transform(FieldDefinition $fieldDef, mixed $value): mixed
    {
        if ($value instanceof AuthorValue) {
            return $value;
        }
        if (is_string($value) || (is_array($value) && !array_is_list($value) && (isset($value['name']) || isset($value['email'])))) {
            $value = [$value];
        }
        if (!is_array($value)) {
            return $value;
        }

        $authors = [];
        foreach ($value as $item) {
            $author = $this->author($item);
            if ($author !== null) {
                $authors[] = $author;
            }
        }

        return new AuthorValue($authors);
    }

    private function author(mixed $item): ?Author
    {
        if (is_array($item)) {
            $name = trim((string) ($item['name'] ?? ''));
            $email = trim((string) ($item['email'] ?? ''));

            return $name === '' && $email === '' ? null : new Author(['name' => $name, 'email' => $email]);
        }
        if (!is_string($item) || trim($item) === '') {
            return null;
        }
        $text = trim($item);
        if (preg_match('/^(.*?)\s*<([^<>]+)>$/', $text, $match) === 1) {
            return new Author(['name' => trim($match[1], " \t\"'"), 'email' => trim($match[2])]);
        }

        return filter_var($text, FILTER_VALIDATE_EMAIL) !== false
            ? new Author(['name' => $text, 'email' => $text])
            : new Author(['name' => $text, 'email' => '']);
    }
}
