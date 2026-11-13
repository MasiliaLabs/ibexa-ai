<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class SwapCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.swap';
    }

    public function summary(): string
    {
        return 'Swap the contents shown at two locations.';
    }

    public function description(): string
    {
        return 'Exchanges the contents of two locations: each location keeps its place in the tree and its children, and shows the other content.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'other_location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'other_location_id' => ['type' => 'integer', 'minimum' => 1]]];
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
        return [['description' => 'Swap two items', 'arguments' => ['location_id' => 1364, 'other_location_id' => 1365]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missing((int) $arguments['location_id'], (int) $arguments['other_location_id']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Swap two locations', [['First', $this->label((int) $arguments['location_id'])], ['Second', $this->label((int) $arguments['other_location_id'])]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $this->repository->getLocationService()->swapLocation($this->load((int) $arguments['location_id']), $this->load((int) $arguments['other_location_id']));

        return CapabilityResult::ok('The two locations were swapped', []);
    }
}
