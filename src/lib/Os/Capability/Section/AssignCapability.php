<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Section;

use Masilia\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class AssignCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'section.assign';
    }

    public function summary(): string
    {
        return 'Put a content, or a location and everything under it, in a section.';
    }

    public function description(): string
    {
        return 'Give section_id and either content_id (that content only) or location_id (that location and its whole subtree). The section decides which roles can read or edit the content.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['section_id'], 'properties' => ['section_id' => ['type' => 'integer', 'minimum' => 1], 'content_id' => ['type' => 'integer', 'minimum' => 1], 'location_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['section', 'assign']];
    }

    public function examples(): array
    {
        return [['description' => 'Put a whole subtree in a section', 'arguments' => ['section_id' => 5, 'location_id' => 1364]]];
    }

    public function validate(array $arguments): ?string
    {
        if (isset($arguments['content_id']) === isset($arguments['location_id'])) {
            return 'Give either content_id or location_id, not both and not neither.';
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
        return new Preview('Assign to a section', [['Section', $this->sectionLabel((int) $arguments['section_id'])], isset($arguments['location_id']) ? ['Location', (string) (int) $arguments['location_id']] : ['Content', (string) (int) $arguments['content_id']]], isset($arguments['location_id']) ? ['Everything under the location is assigned too.'] : []);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getSectionService();
        $section = $service->loadSection((int) $arguments['section_id']);
        if (isset($arguments['location_id'])) {
            $service->assignSectionToSubtree($this->repository->getLocationService()->loadLocation((int) $arguments['location_id']), $section);
        } else {
            $service->assignSection($this->repository->getContentService()->loadContentInfo((int) $arguments['content_id']), $section);
        }

        return CapabilityResult::ok(sprintf('Assigned to the section "%s"', $section->getName()), ['section_id' => $section->getId()]);
    }

    private function sectionLabel(int $sectionId): string
    {
        try {
            return sprintf('%s (section %d)', $this->repository->getSectionService()->loadSection($sectionId)->getName(), $sectionId);
        } catch (\Throwable) {
            return sprintf('section %d', $sectionId);
        }
    }
}
