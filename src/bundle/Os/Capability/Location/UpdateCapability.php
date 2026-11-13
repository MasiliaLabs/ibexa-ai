<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class UpdateCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.update';
    }

    public function summary(): string
    {
        return 'Change the priority of a location (the order of children sorted by priority).';
    }

    public function description(): string
    {
        return 'Sets the priority of a location. Lower numbers come first when the parent sorts its children by priority.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'priority'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'priority' => ['type' => 'integer', 'minimum' => -9999, 'maximum' => 9999]]];
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
        return [['description' => 'Show an item first', 'arguments' => ['location_id' => 1364, 'priority' => 10]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missing((int) $arguments['location_id']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Change the priority', [['Location', $this->label((int) $arguments['location_id'])], ['Priority', (string) (int) $arguments['priority']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getLocationService();
        $struct = $service->newLocationUpdateStruct();
        $struct->priority = (int) $arguments['priority'];
        $location = $service->updateLocation($this->load((int) $arguments['location_id']), $struct);

        return CapabilityResult::ok(sprintf('The priority is now %d', $location->priority), ['location_id' => $location->id]);
    }
}
