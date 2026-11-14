<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class GroupDeleteCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.group.delete';
    }

    public function summary(): string
    {
        return 'Delete an empty group of content types.';
    }

    public function description(): string
    {
        return 'Deletes the group for good. A group that still holds content types cannot be deleted.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of content types']]];
    }

    public function risk(): Risk
    {
        return Risk::DESTRUCTIVE;
    }

    public function requires(): array
    {
        return [['class', 'delete']];
    }

    public function examples(): array
    {
        return [['description' => 'Delete an empty group', 'arguments' => ['group' => 'landing']]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingGroup((string) $arguments['group'])) !== null) {
            return $problem;
        }
        $service = $this->types();
        $types = iterator_to_array($service->loadContentTypes($service->loadContentTypeGroupByIdentifier((string) $arguments['group'])), false);

        return $types !== [] ? sprintf('The group still holds %d content type(s): %s. Delete or move them first.', count($types), implode(', ', array_map(static fn ($t): string => $t->identifier, $types))) : null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Delete a group of content types', [['Group', (string) $arguments['group']]], ['The group is deleted for good. This cannot be undone.']);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $service->deleteContentTypeGroup($service->loadContentTypeGroupByIdentifier((string) $arguments['group']));

        return CapabilityResult::ok(sprintf('Group "%s" was deleted', $arguments['group']), []);
    }
}
