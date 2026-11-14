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
     */
    public function create(string $contentType, array $parentLocationIds, array $fields, string $language): PublishedContent;

    /**
     * Changes the given fields of a content in one language (adding the translation when the content has none in it) and
     * publishes the new version. The fields not given keep their value.
     *
     * @param array<string, mixed> $fields Field identifier => value, as the assistant gives it
     */
    public function update(int $contentId, array $fields, string $language): Content;
}
