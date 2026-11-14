<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Content;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Trash\ContentTrasher;
use Masilia\Bundle\IbexaAi\Ibexa\Content\Write\WriteChecks;
use Masilia\Bundle\IbexaAi\Ibexa\Rules\LocationRules;
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
        private readonly LocationRules $rules,
        private readonly WriteChecks $checks,
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
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => [
            'content_id' => ['type' => 'integer', 'minimum' => 1],
            'with_assets' => ['type' => 'boolean', 'description' => 'Also trash the image assets that only this content uses (the admin offers it on its trash form). Default false.'],
        ]];
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
            $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        } catch (NotFoundException) {
            return sprintf('The content %d does not exist.', (int) $arguments['content_id']);
        }
        if (($problem = $this->checks->notAUser((int) $arguments['content_id'], 'access.user.delete')) !== null) {
            return $problem;
        }
        $mainLocationId = $info->getMainLocationId();
        if ($mainLocationId !== null && $this->rules->isRoot($this->repository->getLocationService()->loadLocation($mainLocationId))) {
            return 'A root location cannot be trashed.';
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        $notes = ['Nothing is deleted: trash.restore brings it back.'];
        try {
            $warnings = array_merge(['children' => 0, 'reverse_relations' => 0, 'drafts' => 0], $this->trasher->warnings((int) $arguments['content_id']));
        } catch (Throwable) {
            $warnings = ['children' => 0, 'reverse_relations' => 0, 'drafts' => 0];
        }
        if ($warnings['children'] > 0) {
            $notes[] = sprintf('%d item(s) directly below it go to the trash with it (and what is under them).', $warnings['children']);
        }
        if ($warnings['reverse_relations'] > 0) {
            $notes[] = sprintf('%d content(s) point at it: they are unlinked from it first, and get a new version.', $warnings['reverse_relations']);
        }
        if ($warnings['drafts'] > 0) {
            $notes[] = sprintf('Its %d draft(s) are trashed with it.', $warnings['drafts']);
        }
        try {
            $assets = $this->trasher->uniqueAssets((int) $arguments['content_id']);
        } catch (Throwable) {
            $assets = [];
        }
        if ($assets !== []) {
            $names = implode(', ', array_map(static fn ($asset): string => (string) $asset->getName(), $assets));
            $notes[] = !empty($arguments['with_assets'])
                ? sprintf('The images only used here go to the trash too: %s.', $names)
                : sprintf('The images only used here stay in the media (%s): with_assets also trashes them.', $names);
        }

        return new Preview('Move to the trash', [['Content', (string) $arguments['content_id']]], $notes);
    }

    public function execute(array $arguments): CapabilityResult
    {
        try {
            $trashed = $this->trasher->trash((int) $arguments['content_id'], !empty($arguments['with_assets']));
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
