<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;

final class RoleCreateCapability extends AccessCapability
{
    public function name(): string
    {
        return 'access.role.create';
    }

    public function summary(): string
    {
        return 'Create a role, with its policies.';
    }

    public function description(): string
    {
        return 'Creates and publishes a role. policies is the list of {module, function, limitations?}; a role with no policy can do nothing. Assign it with access.assignment.add.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'required' => ['name'], 'properties' => ['name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'policies' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['module', 'function'], 'properties' => ['module' => ['type' => 'string'], 'function' => ['type' => 'string', 'description' => 'A function of the module, or * for all'], 'limitations' => ['type' => 'object', 'description' => 'Limitation => values: Section: section ids; Class: content type ids; Node: location ids; Subtree: paths like "/1/2/"; State: state ids; Language: language codes; Owner: [1]']]]]]];
    }

    public function risk(): Risk
    {
        return Risk::ACCESS;
    }

    public function requires(): array
    {
        return [['role', 'create']];
    }

    public function examples(): array
    {
        return [['description' => 'A role that reads everything and writes in one section', 'arguments' => ['name' => 'Press editor', 'policies' => [['module' => 'content', 'function' => 'read'], ['module' => 'content', 'function' => 'create', 'limitations' => ['Section' => [3]]]]]]];
    }

    public function validate(array $arguments): ?string
    {
        if ($this->missingRole((string) $arguments['name']) === null) {
            return sprintf('There is already a role "%s".', $arguments['name']);
        }
        foreach ((array) ($arguments['policies'] ?? []) as $policy) {
            if (($problem = $this->policyProblem((array) $policy)) !== null) {
                return $problem;
            }
        }

        return null;
    }

    public function preview(array $arguments): Preview
    {
        return new Preview('Create a role', [['Name', (string) $arguments['name']], ['Policies', implode('; ', array_map(static fn ($p): string => self::policyLabel((array) $p), (array) ($arguments['policies'] ?? []))) ?: 'none']], [self::NOTE]);
    }

    public function execute(array $arguments): CapabilityResult
    {
        $roles = $this->roles();
        $this->repository->beginTransaction();
        try {
            $draft = $roles->createRole($roles->newRoleCreateStruct((string) $arguments['name']));
            foreach ((array) ($arguments['policies'] ?? []) as $policy) {
                $draft = $roles->addPolicyByRoleDraft($draft, $this->policyStruct((array) $policy));
            }
            $roles->publishRoleDraft($draft);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return CapabilityResult::ok(sprintf('Role "%s" created', $arguments['name']), ['role' => $arguments['name']]);
    }
}
