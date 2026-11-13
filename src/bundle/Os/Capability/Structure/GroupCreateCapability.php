<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Structure;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class GroupCreateCapability extends TypeCapability
{
    public function name(): string
    {
        return 'structure.group.create';
    }

    public function summary(): string
    {
        return 'Create a group of content types.';
    }

    public function description(): string
    {
        return 'Creates an empty group. Create the content types in it with structure.type.create.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['identifier'], 'properties' => ['identifier' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50, 'description' => 'Lower case letters, digits and _']]];
    }

    public function risk(): Risk
    {
        return Risk::WRITE;
    }

    public function requires(): array
    {
        return [['class', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'A group for landing page types', 'arguments' => ['identifier' => 'landing']]];
    }

    public function validate(array $arguments): ?string
    {
        foreach ($this->types()->loadContentTypeGroups() as $group) {
            if ($group->identifier === $arguments['identifier']) {
                return sprintf('There is already a group "%s".', $arguments['identifier']);
            }
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a group of content types', [['Identifier', (string) $arguments['identifier']]]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $group = $this->types()->createContentTypeGroup($this->types()->newContentTypeGroupCreateStruct((string) $arguments['identifier']));

        return CapabilityResult::ok(sprintf('Group "%s" created', $group->identifier), ['group' => $group->identifier]);
    }
}
