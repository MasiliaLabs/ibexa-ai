<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Relations;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Ibexa\Content\Relation\RelationUnlinker;
use Throwable;

/** Removes a content from the relation fields that hold it: what the admin leaves to the editor when it warns that contents point at what is trashed. */
final class UnlinkCapability extends AbstractCapability
{
    public function __construct(
        private readonly Repository $repository,
        private readonly RelationUnlinker $unlinker,
    ) {
    }

    public function name(): string
    {
        return 'relations.unlink';
    }

    public function summary(): string
    {
        return 'Remove a content from the relation fields of the contents that point at it.';
    }

    public function description(): string
    {
        return 'Each content that holds this one in a relation field gets a new version without it (a relation list keeps its other entries, a single relation is emptied). '
            . 'Use it before content.trash when the contents that point at it (see relations.list) must not be left with a broken link, in the same propose_changes.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The content to unlink: the one the others point at'],
        ]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'edit']];
    }

    public function examples(): array
    {
        return [['description' => 'Unlink a page from the contents that point at it before trashing it', 'arguments' => ['content_id' => 1353]]];
    }

    public function validate(array $arguments): ?string
    {
        try {
            $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        } catch (NotFoundException) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        try {
            $sources = $this->unlinker->sources((int) $arguments['content_id']);
        } catch (Throwable) {
            $sources = [];
        }

        return new Preview(
            'Unlink a content',
            [['Content', (string) $arguments['content_id']], ['Held by', sprintf('%d content(s)', count($sources))]],
            $sources === [] ? ['No content holds it in a relation field: nothing changes.'] : ['Each of these contents gets a new version, published at once.'],
        );
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $done = $this->unlinker->unlink((int) $arguments['content_id']);
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('Unlinking content %d failed: %s', (int) $arguments['content_id'], $e->getMessage()));
        }

        if ($done['failed'] !== []) {
            return CapabilityResult::error(sprintf('Content %d could not be removed from: %s', $done['content_id'], implode(', ', $done['failed'])), $done);
        }

        return CapabilityResult::ok(
            $done['unlinked_from'] === []
                ? sprintf('No content held content %d in a relation field', $done['content_id'])
                : sprintf('Unlinked content %d from %d content item(s): %s', $done['content_id'], count($done['unlinked_from']), implode(', ', $done['unlinked_from'])),
            $done,
        );
    }
}
