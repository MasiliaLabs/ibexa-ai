<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AddCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.add';
    }

    public function summary(): string
    {
        return 'Show a content at one more place of the tree.';
    }

    public function description(): string
    {
        return 'Adds a location for the content under another parent: the content then appears in both places (its main location does not change). The answer has the new location_id.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'parent_location_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'parent_location_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'manage_locations']];
    }

    public function examples(): array
    {
        return [['description' => 'Also show a page under another folder', 'arguments' => ['content_id' => 1353, 'parent_location_id' => 1020]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missing((int) $arguments['parent_location_id']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Add a location', [['Content', $this->contentName((int) $arguments['content_id'])], ['Under', $this->label((int) $arguments['parent_location_id'])]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getLocationService();
        $info = $this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']);
        $new = $service->createLocation($info, $service->newLocationCreateStruct((int) $arguments['parent_location_id']));

        return CapabilityResult::ok(sprintf('"%s" now also appears under location %d', $info->name, $new->parentLocationId), ['location_id' => $new->id, 'content_id' => $info->id]);
    }
}
