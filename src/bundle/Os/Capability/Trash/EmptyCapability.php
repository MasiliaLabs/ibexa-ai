<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Trash;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Query;
use Masilia\Bundle\IbexaAi\Os\Capability\BaseCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class EmptyCapability extends BaseCapability
{
    public function __construct(private readonly Repository $repository)
    {
    }

    public function name(): string
    {
        return 'trash.empty';
    }

    public function summary(): string
    {
        return 'Delete everything in the trash for good.';
    }

    public function description(): string
    {
        return 'Permanently deletes every content in the trash. It cannot be undone: restore first what must be kept. '
            . 'Use it only when the user asks for the trash to be emptied.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'cleantrash']];
    }

    public function preview(array $arguments): Preview
    {
        $count = $this->repository->getTrashService()->findTrashItems(new Query(['limit' => 0]))->totalCount;

        return new Preview('Empty the trash', [['Items', (string) $count]], ['These contents are deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $trash = $this->repository->getTrashService();
        $count = $trash->findTrashItems(new Query(['limit' => 0]))->totalCount;
        $trash->emptyTrash();

        return CapabilityResult::ok(sprintf('The trash was emptied (%d items deleted)', $count), ['deleted' => $count]);
    }
}
