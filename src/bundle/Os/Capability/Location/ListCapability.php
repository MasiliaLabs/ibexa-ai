<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class ListCapability extends LocationCapability
{
    public function name(): string { return 'location.list'; }

    public function summary(): string { return 'List the locations of a content (where it appears in the tree) and which one is its main one.'; }

    public function description(): string
    {
        return 'A content can appear at several places of the tree. Returns each location with its parent, the path, whether it is the main one, '
            . 'and whether it is hidden (by an editor) or invisible (hidden by an ancestor).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk { return Risk::READ; }

    public function requires(): array { return [['content', 'read']]; }

    public function examples(): array { return [['description' => 'Where a page appears', 'arguments' => ['content_id' => 1353]]]; }

    public function execute(array $arguments): CapabilityResult
    {
        $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        $rows = array_map(self::row(...), iterator_to_array($this->repository->getLocationService()->loadLocations($info), false));

        return CapabilityResult::ok(sprintf('%d location(s) for "%s"', count($rows), $info->name), ['locations' => $rows]);
    }
}
