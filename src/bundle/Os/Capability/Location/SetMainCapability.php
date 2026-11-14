<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Location;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class SetMainCapability extends LocationCapability
{
    public function name(): string
    {
        return 'location.set_main';
    }

    public function summary(): string
    {
        return 'Choose which location of a content is its main one.';
    }

    public function description(): string
    {
        return 'The main location gives the content its canonical URL. The location must be one of the content\'s own (see location.list).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['content_id', 'location_id'], 'properties' => ['content_id' => ['type' => 'integer', 'minimum' => 1], 'location_id' => ['type' => 'integer', 'minimum' => 1]]];
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
        return [['description' => 'Make another location the main one', 'arguments' => ['content_id' => 1353, 'location_id' => 1366]]];
    }

    public function validate(array $arguments): ?string
    {
        $problem = $this->missing((int) $arguments['location_id']);

        return $problem ?? ($this->load((int) $arguments['location_id'])->contentInfo->id !== (int) $arguments['content_id'] ? 'That location belongs to another content.' : null);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Change the main location', [['Content', $this->contentName((int) $arguments['content_id'])], ['New main location', $this->label((int) $arguments['location_id'])]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $content = $this->repository->getContentService();
        $info = $content->loadContentInfo((int) $arguments['content_id']);
        $struct = $content->newContentMetadataUpdateStruct();
        $struct->mainLocationId = (int) $arguments['location_id'];
        $content->updateContentMetadata($info, $struct);

        return CapabilityResult::ok(sprintf('Location %d is now the main location of "%s"', (int) $arguments['location_id'], $info->name), ['content_id' => $info->id]);
    }
}
