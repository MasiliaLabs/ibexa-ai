<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Ibexa\Content\Write;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use InvalidArgumentException;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\ContentCreator;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\ContentUpdater;
use Masilia\Contracts\IbexaAi\Content\ContentWriter;
use Masilia\Contracts\IbexaAi\Content\PublishedContent;

/**
 * Writes contents with the field value transformers of the assistant, as its own capabilities do.
 */
final readonly class RepositoryContentWriter implements ContentWriter
{
    public function __construct(
        private ContentCreator $creator,
        private ContentUpdater $updater,
    ) {
    }

    public function create(string $contentType, array $parentLocationIds, array $fields, string $language): PublishedContent
    {
        $created = $this->creator->createAndPublish($contentType, $parentLocationIds, $fields, $language);
        // The creator answers with nothing for a content type that does not exist.
        if (!isset($created['content'])) {
            throw new InvalidArgumentException(sprintf('There is no content type "%s".', $contentType));
        }

        return new PublishedContent($created['content'], $created['location'] ?? null);
    }

    public function update(int $contentId, array $fields, string $language): Content
    {
        return $this->updater->update($contentId, $fields, $language);
    }
}
