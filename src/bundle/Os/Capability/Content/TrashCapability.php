<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Content\Trash\ContentTrasher;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Throwable;

/** Moves a content to the trash: it can be restored with trash.restore, nothing is deleted for good. */
final class TrashCapability extends AbstractCapability
{
    public function __construct(
        private readonly Repository $repository,
        private readonly ContentTrasher $trasher,
    ) {
    }

    public function name(): string
    {
        return 'content.trash';
    }

    public function summary(): string
    {
        return 'Move a content to the trash (it can be restored).';
    }

    public function description(): string
    {
        return 'Moves the content, and what is under its location, to the trash. The contents that point at it through a relation field are unlinked first. '
            . 'Nothing is deleted for good: trash.restore brings it back.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'remove']];
    }

    public function examples(): array
    {
        return [['description' => 'Trash a page', 'arguments' => ['content_id' => 1353]]];
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
        return new Preview('Move to the trash', [['Content', (string) $arguments['content_id']]], ['Nothing is deleted: trash.restore brings it back.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $trashed = $this->trasher->trash((int) $arguments['content_id']);
        } catch (Throwable $e) {
            return CapabilityResult::error(sprintf('Trashing content %d failed: %s', (int) $arguments['content_id'], $e->getMessage()));
        }

        $message = sprintf('Trashed content %d', $trashed['content_id']);
        if ($trashed['unlinked_from'] !== []) {
            $message .= sprintf(' (unlinked from %d content item(s): %s)', count($trashed['unlinked_from']), implode(', ', $trashed['unlinked_from']));
        }

        return CapabilityResult::ok($message, $trashed);
    }
}
