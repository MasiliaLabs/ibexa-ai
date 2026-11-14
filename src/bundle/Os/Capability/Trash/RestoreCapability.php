<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Trash;

use Masilia\Bundle\IbexaAi\Agent\Tool\TrashRestorer;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

/** Puts trashed contents back where they were. Writes through the trash service, with the rights of the current user. */
final class RestoreCapability extends AbstractCapability
{
    public function __construct(private readonly TrashRestorer $restorer)
    {
    }

    public function name(): string
    {
        return 'trash.restore';
    }

    public function summary(): string
    {
        return 'Restore contents from the trash to where they were.';
    }

    public function description(): string
    {
        return 'Restores the given contents from the trash (ids as trash.list gives them). Ids that are not in the trash are ignored.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['content_ids'],
            'properties' => ['content_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'description' => 'Content ids, as trash.list gives them']],
        ];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'restore']];
    }

    public function examples(): array
    {
        return [['description' => 'Restore two contents', 'arguments' => ['content_ids' => [1353, 1354]]]];
    }

    public function validate(array $arguments): ?string
    {
        return $arguments['content_ids'] === [] ? 'trash.restore requires at least one content id (content_ids).' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Restore from the trash', [['Contents', implode(', ', array_map('strval', $arguments['content_ids']))]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $restored = $this->restorer->restore(array_values(array_map('intval', $arguments['content_ids'])));

        return CapabilityResult::ok(sprintf('Restored %d items', count($restored)), ['restored' => $restored, 'count' => count($restored)]);
    }
}
