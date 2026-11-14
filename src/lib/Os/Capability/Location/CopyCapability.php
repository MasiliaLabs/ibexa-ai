<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class CopyCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.copy';
    }

    public function summary(): string
    {
        return 'Copy the content of a location below another parent (not what is under it).';
    }

    public function description(): string
    {
        return 'Copies the content at the location, alone, as a new content below the target parent, which must be a container. What is under the location is not copied: use location.copy_subtree for that. The answer has the location_id of the copy.';
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
        return [['description' => 'Copy a page below another folder', 'arguments' => ['location_id' => 1364, 'target_parent_location_id' => 1020]]];
    }

    public function validate(array $arguments): ?string
    {
        $problem = $this->missing((int) $arguments['location_id'], (int) $arguments['target_parent_location_id']);
        if ($problem !== null) {
            return $problem;
        }

        return $this->rules->isContainer($this->load((int) $arguments['target_parent_location_id'])) ? null : 'Cannot copy a location to a parent that is not a container.';
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Copy a location', [['Copy', $this->label((int) $arguments['location_id'])], ['Below', $this->label((int) $arguments['target_parent_location_id'])]], ['Only this content is copied, not what is under it.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $locations = $this->repository->getLocationService();
        $contents = $this->repository->getContentService();
        $copy = $contents->copyContent(
            $this->load((int) $arguments['location_id'])->getContentInfo(),
            $locations->newLocationCreateStruct((int) $arguments['target_parent_location_id']),
        );
        $mainLocationId = $copy->getContentInfo()->getMainLocationId();

        return CapabilityResult::ok(sprintf('Copied to location %d', (int) $mainLocationId), ['location_id' => $mainLocationId, 'content_id' => $copy->getContentInfo()->getId()]);
    }
}
