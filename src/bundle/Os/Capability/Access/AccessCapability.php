<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Capability\Access;

use Ibexa\Contracts\Core\Repository\RoleService;
use Ibexa\Contracts\Core\Repository\Values\User\Limitation;
use Ibexa\Contracts\Core\Repository\Values\User\Role;
use Ibexa\Contracts\Core\Repository\Values\User\RoleDraft;
use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Os\Capability\RepositoryCapability;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * What the capabilities on roles, policies and users share. Everything here is ACCESS: it changes who can do what.
 */
abstract class AccessCapability extends RepositoryCapability
{
    /** Said on the approval card of everything that changes permissions. */
    protected const NOTE = 'This changes who can do what on the site.';

    /**
     * @param array<string, array<string, array<string, bool>>> $policyMap Module => function => the limitations it accepts
     */
    public function __construct(Repository $repository, #[Autowire(param: 'ibexa.api.role.policy_map')] private readonly array $policyMap)
    {
        parent::__construct($repository);
    }

    protected function roles(): RoleService
    {
        return $this->repository->getRoleService();
    }

    protected function missingRole(string $name): ?string
    {
        try {
            $this->roles()->loadRoleByIdentifier($name);

            return null;
        } catch (Throwable) {
            return sprintf('There is no role "%s": see access.role.list.', $name);
        }
    }

    /**
     * What is wrong with a policy and its limitations, without writing anything.
     *
     * @param array<string, mixed> $policy {module, function, limitations?}
     */
    protected function policyProblem(array $policy): ?string
    {
        $module = (string) ($policy['module'] ?? '');
        $function = (string) ($policy['function'] ?? '');
        if (!isset($this->policyMap[$module])) {
            return sprintf('There is no module "%s" (modules: %s).', $module, implode(', ', array_keys($this->policyMap)));
        }
        if ($function !== '*' && !isset($this->policyMap[$module][$function])) {
            return sprintf('The module "%s" has no function "%s" (functions: %s).', $module, $function, implode(', ', array_keys($this->policyMap[$module])));
        }
        foreach (array_keys((array) ($policy['limitations'] ?? [])) as $identifier) {
            if ($function === '*') {
                return 'A policy on every function (*) cannot have limitations.';
            }
            if (!isset($this->policyMap[$module][$function][$identifier])) {
                return sprintf('The policy %s/%s cannot be limited by "%s" (it accepts: %s).', $module, $function, $identifier, implode(', ', array_keys($this->policyMap[$module][$function])) ?: 'no limitation');
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $limitations Limitation identifier => values
     *
     * @return list<Limitation>
     */
    protected function limitations(array $limitations): array
    {
        $built = [];
        foreach ($limitations as $identifier => $values) {
            $built[] = $this->roles()->getLimitationType((string) $identifier)->buildValue(array_values((array) $values));
        }

        return $built;
    }

    /**
     * @param array<string, mixed> $policy
     */
    protected function policyStruct(array $policy): \Ibexa\Contracts\Core\Repository\Values\User\PolicyCreateStruct
    {
        $struct = $this->roles()->newPolicyCreateStruct((string) $policy['module'], (string) $policy['function']);
        foreach ($this->limitations((array) ($policy['limitations'] ?? [])) as $limitation) {
            $struct->addLimitation($limitation);
        }

        return $struct;
    }

    /** "Section: 1; Class: 5,7" */
    protected static function limitationsLabel(array $limitations): string
    {
        $parts = [];
        foreach ($limitations as $identifier => $values) {
            $parts[] = sprintf('%s: %s', $identifier, implode(',', (array) $values));
        }

        return $parts === [] ? 'none' : implode('; ', $parts);
    }

    /** "content/read [Section: 1]" */
    protected static function policyLabel(array $policy): string
    {
        $limits = [];
        foreach ((array) ($policy['limitations'] ?? []) as $identifier => $values) {
            $limits[] = sprintf('%s: %s', $identifier, implode(',', (array) $values));
        }

        return sprintf('%s/%s%s', $policy['module'], $policy['function'], $limits !== [] ? ' [' . implode('; ', $limits) . ']' : '');
    }

    /**
     * Changes a role through a draft that is published at the end, in one transaction.
     *
     * @param callable(RoleDraft, Role): void $change
     */
    protected function editRole(string $name, callable $change): Role
    {
        $roles = $this->roles();
        $this->repository->beginTransaction();
        try {
            $role = $roles->loadRoleByIdentifier($name);
            try {
                $draft = $roles->createRoleDraft($role);
            } catch (Throwable) {
                $draft = $roles->loadRoleDraftByRoleId($role->id);
            }
            $change($draft, $role);
            $roles->publishRoleDraft($roles->loadRoleDraftByRoleId($role->id));
            $this->repository->commit();
        } catch (Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }

        return $roles->loadRole($role->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describePolicy(\Ibexa\Contracts\Core\Repository\Values\User\Policy $policy): array
    {
        $limitations = [];
        foreach ($policy->getLimitations() as $limitation) {
            $limitations[$limitation->getIdentifier()] = array_map(static fn ($v) => is_string($v) && ctype_digit($v) ? (int) $v : $v, array_values($limitation->limitationValues));
        }

        return ['policy_id' => $policy->id, 'module' => $policy->module, 'function' => $policy->function, 'limitations' => $limitations];
    }

    /** "Name (user 12)" for a user id, or the id alone when it cannot be read. */
    protected function userLabel(int $id): string
    {
        try {
            $user = $this->repository->getUserService()->loadUser($id);

            return sprintf('%s (%s)', $user->login, $id);
        } catch (Throwable) {
            return (string) $id;
        }
    }
}
