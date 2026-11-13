<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Section;

use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class DeleteCapability extends RepositoryCapability
{
    public function name(): string
    {
        return 'section.delete';
    }

    public function summary(): string
    {
        return 'Delete a section that no content uses.';
    }

    public function description(): string
    {
        return 'Deletes a section for good. A section that still holds contents cannot be deleted: assign them elsewhere first with section.assign.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['section_id'], 'properties' => ['section_id' => ['type' => 'integer', 'minimum' => 1]]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['section', 'edit']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete an unused section', 'arguments' => ['section_id' => 9]]];
    }

    public function validate(array $arguments): ?string
    {
        $service = $this->repository->getSectionService();
        try {
            $section = $service->loadSection((int) $arguments['section_id']);
        } catch (\Throwable) {
            return sprintf('The section %d does not exist.', (int) $arguments['section_id']);
        }

        return $service->isSectionUsed($section) ? sprintf('The section "%s" still holds %d content(s): assign them to another section first.', $section->getName(), $service->countAssignedContents($section)) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a section', [['Section', $this->sectionLabel((int) $arguments['section_id'])]], ['The section is deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->repository->getSectionService();
        $section = $service->loadSection((int) $arguments['section_id']);
        $service->deleteSection($section);

        return CapabilityResult::ok(sprintf('Section "%s" was deleted', $section->getName()), []);
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
