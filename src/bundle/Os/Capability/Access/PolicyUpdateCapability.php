<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class PolicyUpdateCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.policy.update';
    }

    public function summary(): string
    {
        return 'Replace the limitations of a policy.';
    }

    public function description(): string
    {
        return 'Sets the limitations of the policy to exactly the ones given (an empty object removes every limitation, which widens the policy).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role', 'policy_id', 'limitations'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)'], 'policy_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'As access.role.describe gives it'], 'limitations' => ['type' => 'object', 'description' => 'Limitation => values: Section: section ids; Class: content type ids; Node: location ids; Subtree: paths like "/1/2/"; State: state ids; Language: language codes; Owner: [1]']]];
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
        return [['description' => 'Widen a policy to a second section', 'arguments' => ['role' => 'Press editor', 'policy_id' => 41, 'limitations' => ['Section' => [3, 4]]]]];
    }

    public function validate(array $arguments): ?string
    {
        if (($problem = $this->missingRole((string) $arguments['role'])) !== null) {
            return $problem;
        }
        $ids = array_map(static fn ($p): int => $p->id, iterator_to_array($this->roles()->loadRoleByIdentifier((string) $arguments['role'])->getPolicies(), false));

        return in_array((int) $arguments['policy_id'], $ids, true) ? null : sprintf('The role "%s" has no policy %d (it has: %s).', $arguments['role'], (int) $arguments['policy_id'], implode(', ', $ids));
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Change a policy', [['Role', (string) $arguments['role']], ['Policy', (string) (int) $arguments['policy_id']], ['New limitations', self::limitationsLabel((array) $arguments['limitations'])]], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $this->editRole((string) $arguments['role'], function ($draft) use ($arguments): void {
            foreach ($draft->getPolicies() as $policy) {
                if ((int) $policy->originalId === (int) $arguments['policy_id']) {
                    $struct = $this->roles()->newPolicyUpdateStruct();
                    foreach ($this->limitations((array) $arguments['limitations']) as $limitation) {
                        $struct->addLimitation($limitation);
                    }
                    $this->roles()->updatePolicyByRoleDraft($draft, $policy, $struct);

                    return;
                }
            }
            throw new \RuntimeException('The policy was not found in the draft of the role.');
        });

        return CapabilityResult::ok(sprintf('Policy %d of "%s" updated', (int) $arguments['policy_id'], $arguments['role']), ['role' => $arguments['role']]);
    }
}
