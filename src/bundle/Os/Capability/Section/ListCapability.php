<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Section;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class ListCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'section.list';
    }

    public function summary(): string
    {
        return 'List the sections and how many contents each holds.';
    }

    public function description(): string
    {
        return 'Sections group contents for permissions: a role can be limited to a section. Returns id, identifier, name and the number of contents of each.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function risk(): Risk
    {
        return Risk::READ;
    }

    public function requires(): array
    {
        return [['section', 'view']];
    }

    public function examples(): array
    {
        return [['description' => 'All sections', 'arguments' => []]];
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getSectionService();
        $rows = [];
        foreach ($service->loadSections() as $section) {
            $rows[] = ['section_id' => $section->getId(), 'identifier' => $section->getIdentifier(), 'name' => $section->getName(), 'contents' => $service->countAssignedContents($section)];
        }

        return CapabilityResult::ok(sprintf('%d section(s)', count($rows)), ['sections' => $rows]);
    }
}
