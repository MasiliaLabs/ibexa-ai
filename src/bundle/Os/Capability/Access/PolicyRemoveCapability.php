<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;

final class PolicyRemoveCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.policy.remove';
    }

    public function summary(): string
    {
        return 'Remove a policy from a role.';
    }

    public function description(): string
    {
        return 'Removes the policy: the users of the role lose that permission at once.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['role', 'policy_id'], 'properties' => ['role' => ['type' => 'string', 'minLength' => 1, 'description' => 'Name of the role (see access.role.list)'], 'policy_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'As access.role.describe gives it']]];
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
        return [['description' => 'Take a permission away', 'arguments' => ['role' => 'Press editor', 'policy_id' => 41]]];
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
        return new Preview('Remove a policy', [['Role', (string) $arguments['role']], ['Policy', (string) (int) $arguments['policy_id']]], ['The users of the role lose this permission at once. This cannot be undone.', self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $this->editRole((string) $arguments['role'], function ($draft) use ($arguments): void {
            foreach ($draft->getPolicies() as $policy) {
                if ((int) $policy->originalId === (int) $arguments['policy_id']) {
                    $this->roles()->removePolicyByRoleDraft($draft, $policy);

                    return;
                }
            }
            throw new \RuntimeException('The policy was not found in the draft of the role.');
        });

        return CapabilityResult::ok(sprintf('Policy %d removed from "%s"', (int) $arguments['policy_id'], $arguments['role']), ['role' => $arguments['role']]);
    }
}
