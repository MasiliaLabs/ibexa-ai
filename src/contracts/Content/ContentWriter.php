<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Content;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;

/**
 * Writes contents from field values given the way the assistant gives them: text for a text line, HTML for rich text, a
 * description for an image (the image is made from it), the label of an option, a list of ids for relations… one value per
 * field identifier. Each value is turned into the value of its field type before Ibexa sees it.
 *
 * It writes with the rights of the current user, and publishes: a package that builds pages uses it instead of making the
 * create and update structs itself, so that what the assistant writes and what a package writes go through the same rules.
 */
interface ContentWriter
{
    /**
     * @param list<int>            $parentLocationIds Where the content goes (its first parent is its main location)
     * @param array<string, mixed> $fields            Field identifier => value, as the assistant gives it
     * @param string               $language          The language the content is written in ("fre-FR")
     * @param bool                 $publish           False to keep it as a draft ("Save draft" of the editing form): it has no location yet
     *                                                and is published later (see Masilia\Contracts\IbexaAi\Content\PublishedContent)
     *
     * @throws \InvalidArgumentException For a content type that does not exist
     * @throws \RuntimeException         When Ibexa refuses the content (a field value it does not accept, no right to create it…)
     */
    public function create(string $contentType, array $parentLocationIds, array $fields, string $language, bool $publish = true): PublishedContent;

    /**
     * Changes the given fields of a content in one language (adding the translation when the content has none in it) and
     * publishes the new version. The fields not given keep their value.
     *
     * @param array<string, mixed> $fields       Field identifier => value, as the assistant gives it
     * @param bool                 $publish      False to leave the changes in a new draft instead of publishing them
     * @param string|null          $baseLanguage For a new translation: the language its translatable fields start from, as in the
     *                                           translation form of the admin; what $fields gives replaces it
     */
    public function update(int $contentId, array $fields, string $language, bool $publish = true, ?string $baseLanguage = null): Content;

    /**
     * What Ibexa itself would refuse in a content about to be created (the validation of the editing form: required fields, the
     * limits of each field type), found before the user is asked to approve it. Nothing is written.
     *
     * @param array<string, mixed> $fields Field identifier => value, as the assistant gives it
     *
     * @return list<string> The problems, in words for the model; empty when Ibexa would accept the content
     */
    public function validateCreate(string $contentType, array $fields, string $language): array;

    /**
     * The same for the changes of a content: what Ibexa would refuse once they are applied.
     *
     * @param array<string, mixed> $fields Field identifier => value, as the assistant gives it
     *
     * @return list<string>
     */
    public function validateUpdate(int $contentId, array $fields, string $language, ?string $baseLanguage = null): array;
}
