<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class CopySubtreeCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.copy_subtree';
    }

    public function summary(): string
    {
        return 'Copy a location with everything under it below another parent.';
    }

    public function description(): string
    {
        return 'Copies the content at the location and everything under it as new contents below the target parent. The answer has the location_id of the copy.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['location_id', 'target_parent_location_id'], 'properties' => ['location_id' => ['type' => 'integer', 'minimum' => 1], 'target_parent_location_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['content', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'Copy a folder and what is in it', 'arguments' => ['location_id' => 1364, 'target_parent_location_id' => 1020]]];
    }

    public function validate(array $arguments): ?string
    {
        $problem = $this->missing((int) $arguments['location_id'], (int) $arguments['target_parent_location_id']);
        if ($problem !== null) {
            return $problem;
        }
        $location = $this->load((int) $arguments['location_id']);
        $target = $this->load((int) $arguments['target_parent_location_id']);
        // The rules of the admin's "copy subtree" form, in its order.
        if (!$this->rules->isWithinCopyLimit($location)) {
            return sprintf('Copy subtree limit exceeded. Current limit: %d.', $this->rules->copyLimit());
        }
        if ($this->rules->isRoot($location)) {
            return 'A root location cannot be copied.';
        }
        if (!$this->rules->isContainer($target)) {
            return 'Cannot copy a subtree to a parent that is not a container.';
        }

        return $this->rules->isInside($target, $location) ? 'Cannot copy a subtree to its own descendant location.' : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Copy a location', [['Copy', $this->label((int) $arguments['location_id'])], ['Below', $this->label((int) $arguments['target_parent_location_id'])]], ['Everything under it is copied too: the copy can be large.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getLocationService();
        $copy = $service->copySubtree($this->load((int) $arguments['location_id']), $this->load((int) $arguments['target_parent_location_id']));

        return CapabilityResult::ok(sprintf('Copied to location %d', $copy->id), ['location_id' => $copy->id, 'content_id' => $copy->contentInfo->id]);
    }
}
