<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class MoveCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.move';
    }

    public function summary(): string
    {
        return 'Move a location, with everything under it, below another parent.';
    }

    public function description(): string
    {
        return 'Moves the location and its whole subtree under a new parent location. A location cannot be moved under itself or one of its own descendants.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'new_parent_location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'new_parent_location_id' => ['type' => 'integer', 'minimum' => 1]]];
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
        return [['description' => 'Move a folder', 'arguments' => ['location_id' => 1364, 'new_parent_location_id' => 1020]]];
    }

    public function validate(array $arguments): ?string
    {
        $problem = $this->missing((int) $arguments['location_id'], (int) $arguments['new_parent_location_id']);
        if ($problem !== null) {
            return $problem;
        }
        $path = $this->load((int) $arguments['new_parent_location_id'])->pathString;

        return str_contains($path, sprintf('/%d/', (int) $arguments['location_id'])) ? 'A location cannot be moved under itself or one of its descendants.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Move a location', [['Move', $this->label((int) $arguments['location_id'])], ['Below', $this->label((int) $arguments['new_parent_location_id'])]], ['Everything under it moves too, and its URLs change.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getLocationService();
        $location = $this->load((int) $arguments['location_id']);
        $service->moveSubtree($location, $this->load((int) $arguments['new_parent_location_id']));

        return CapabilityResult::ok(sprintf('"%s" was moved below location %d', $location->getContent()->getName(), (int) $arguments['new_parent_location_id']), ['location_id' => $location->id]);
    }
}
