<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Content\Write;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentCreator;
use Masilia\Bundle\IbexaAi\Agent\Tool\ContentUpdater;
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

        return new PublishedContent($created['content'], $created['location']);
    }

    public function update(int $contentId, array $fields, string $language): Content
    {
        return $this->updater->update($contentId, $fields, $language);
    }
}
