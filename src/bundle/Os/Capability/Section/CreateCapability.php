<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Section;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class CreateCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'section.create';
    }

    public function summary(): string
    {
        return 'Create a section.';
    }

    public function description(): string
    {
        return 'Creates an empty section. Assign contents to it with section.assign.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['identifier', 'name'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _'], 'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190]]];
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
        return [['description' => 'A section for the press room', 'arguments' => ['identifier' => 'press', 'name' => 'Press']]];
    }

    public function validate(array $arguments): ?string
    {
        foreach ($this->repository->getSectionService()->loadSections() as $section) {
            if ($section->getIdentifier() === $arguments['identifier']) {
                return sprintf('There is already a section "%s".', $arguments['identifier']);
            }
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a section', [['Identifier', (string) $arguments['identifier']], ['Name', (string) $arguments['name']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getSectionService();
        $struct = $service->newSectionCreateStruct();
        $struct->identifier = (string) $arguments['identifier'];
        $struct->name = (string) $arguments['name'];
        $section = $service->createSection($struct);

        return CapabilityResult::ok(sprintf('Section "%s" created', $section->getName()), ['section_id' => $section->getId()]);
    }
}
