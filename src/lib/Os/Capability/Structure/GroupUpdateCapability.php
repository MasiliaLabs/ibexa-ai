<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Capability\Structure;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class GroupUpdateCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.group.update';
    }

    public function summary(): string
    {
        return 'Rename a group of content types.';
    }

    public function description(): string
    {
        return 'Changes the identifier of a group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['group', 'new_identifier'], 'properties' => ['group' => ['type' => 'string', 'minLength' => 1, 'description' => 'Identifier of the group of content types'], 'new_identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['class', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Rename a group', 'arguments' => ['group' => 'landing', 'new_identifier' => 'campaign']]];
    }

    public function validate(array $arguments): ?string
    {
        return $this->missingGroup((string) $arguments['group']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Rename a group of content types', [['Group', (string) $arguments['group']], ['New identifier', (string) $arguments['new_identifier']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $service = $this->types();
        $struct = $service->newContentTypeGroupUpdateStruct();
        $struct->identifier = (string) $arguments['new_identifier'];
        $service->updateContentTypeGroup($service->loadContentTypeGroupByIdentifier((string) $arguments['group']), $struct);

        return CapabilityResult::ok(sprintf('Group renamed to "%s"', $arguments['new_identifier']), ['group' => $arguments['new_identifier']]);
    }
}
