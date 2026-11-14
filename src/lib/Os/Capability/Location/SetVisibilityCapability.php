<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class SetVisibilityCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.set_visibility';
    }

    public function summary(): string
    {
        return 'Hide a location (and what is under it) from visitors, or show it again.';
    }

    public function description(): string
    {
        return 'Hiding makes the location and its subtree invisible on the site; the contents are kept. visible=false hides, visible=true shows it again.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'visible'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'visible' => ['type' => 'boolean']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'hide']];
    }

    public function examples(): array
    {
        return [['description' => 'Hide a section of the site', 'arguments' => ['location_id' => 1364, 'visible' => false]]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missing((int) $arguments['location_id']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview(($arguments['visible'] ?? true) ? 'Show a location' : 'Hide a location', [['Location', $this->label((int) $arguments['location_id'])]], ($arguments['visible'] ?? true) ? [] : ['Visitors can no longer see it, nor what is under it.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getLocationService();
        $location = $this->load((int) $arguments['location_id']);
        $visible = (bool) $arguments['visible'];
        if ($visible === !$location->hidden) {
            return CapabilityResult::ok(sprintf('It was already %s', $visible ? 'visible' : 'hidden'), ['location_id' => $location->id]);
        }
        $visible ? $service->unhideLocation($location) : $service->hideLocation($location);

        return CapabilityResult::ok(sprintf('%s is now %s', $location->getContent()->getName(), $visible ? 'visible' : 'hidden'), ['location_id' => $location->id]);
    }
}
