<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Section;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class UpdateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'section.update';
    }

    public function summary(): string
    {
        return 'Rename a section or change its identifier.';
    }

    public function description(): string
    {
        return 'Changes the name and/or the identifier of a section. Role limitations refer to sections by id, so a new identifier breaks nothing.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['section_id'], 'properties' => ['section_id' => ['type' => 'integer', 'minimum' => 1], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190], 'identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['section', 'edit']];
    }

    public function examples(): array
    {
        return [['description' => 'Rename a section', 'arguments' => ['section_id' => 5, 'name' => 'Press room']]];
    }

    public function validate(array $arguments): ?string
    {
        if (!isset($arguments['name']) && !isset($arguments['identifier'])) {
            return 'Give a name or an identifier to change.';
        }
        try {
            $this->repository->getSectionService()->loadSection((int) $arguments['section_id']);
        } catch (\Throwable) {
            return sprintf('The section %d does not exist.', (int) $arguments['section_id']);
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Update a section', array_values(array_filter([['Section', (string) (int) $arguments['section_id']], isset($arguments['name']) ? ['Name', (string) $arguments['name']] : null, isset($arguments['identifier']) ? ['Identifier', (string) $arguments['identifier']] : null])));
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getSectionService();
        $struct = $service->newSectionUpdateStruct();
        $struct->name = $arguments['name'] ?? null;
        $struct->identifier = $arguments['identifier'] ?? null;
        $section = $service->updateSection($service->loadSection((int) $arguments['section_id']), $struct);

        return CapabilityResult::ok(sprintf('Section %d is now "%s"', $section->getId(), $section->getName()), ['section_id' => $section->getId()]);
    }
}
