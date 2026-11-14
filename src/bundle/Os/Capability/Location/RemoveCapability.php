<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class RemoveCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.remove';
    }

    public function summary(): string
    {
        return 'Delete a location and everything under it for good (not the trash).';
    }

    public function description(): string
    {
        return 'Deletes the location and its subtree permanently, bypassing the trash; a content that has no other location is deleted with it. To be able to restore, use content.trash instead. Use this only to remove one of several locations of a content, or when the user asks for a permanent deletion.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['content', 'manage_locations'], ['content', 'remove']];
    }

    public function examples(): array
    {
        return [['description' => 'Remove an extra location of a page', 'arguments' => ['location_id' => 1366]]];
    }

    public function validate(array $arguments): ?string
    {
        $problem = $this->missing((int) $arguments['location_id']);
        if ($problem !== null) {
            return $problem;
        }

        return $this->rules->isRoot($this->load((int) $arguments['location_id'])) ? 'A root location cannot be removed.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a location for good', [['Location', $this->label((int) $arguments['location_id'])], ['Children', (string) $this->repository->getLocationService()->getLocationChildCount($this->load((int) $arguments['location_id']))]], ['It is deleted with everything under it, and it does not go to the trash. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $location = $this->load((int) $arguments['location_id']);
        $name = $location->getContent()->getName();
        $this->repository->getLocationService()->deleteLocation($location);

        return CapabilityResult::ok(sprintf('"%s" (location %d) was deleted for good', $name, (int) $arguments['location_id']), []);
    }
}
