<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class PolicyAddCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.policy.add';
    }

    public function summary(): string
    {
        return 'Add a policy to a role.';
    }

    public function description(): string
    {
        return 'Adds one policy (module/function, optionally with limitations) to the role. Use function "*" for every function of the module. The role\'s users get the permission at once.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role', 'policy'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)'], 'policy' => ['type' => 'object', 'required' => ['module', 'function'], 'properties' => ['module' => ['type' => 'string'], 'function' => ['type' => 'string', 'description' => 'A function of the module, or * for all'], 'limitations' => ['type' => 'object', 'description' => 'Limitation => values: Section: section ids; Class: content type ids; Node: location ids; Subtree: paths like "/1/2/"; State: state ids; Language: language codes; Owner: [1]']]]]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['role', 'update']];
    }

    public function examples(): array
    {
        return [['description' => 'Let a role edit one section', 'arguments' => ['role' => 'Press editor', 'policy' => ['module' => 'content', 'function' => 'edit', 'limitations' => ['Section' => [3]]]]]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingRole((string) $arguments['role'])) !== null) {
            return $problem;
        }

        return $this->policyProblem((array) $arguments['policy']);
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Add a policy', [['Role', (string) $arguments['role']], ['Policy', self::policyLabel((array) $arguments['policy'])]], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $this->editRole((string) $arguments['role'], function ($draft) use ($arguments): void {
            $this->roles()->addPolicyByRoleDraft($draft, $this->policyStruct((array) $arguments['policy']));
        });

        return CapabilityResult::ok(sprintf('Policy %s added to "%s"', self::policyLabel((array) $arguments['policy']), $arguments['role']), ['role' => $arguments['role']]);
    }
}
