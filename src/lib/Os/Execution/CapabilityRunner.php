<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Execution;

use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\IbexaAi\Os\Audit\AuditLog;
use Masilia\IbexaAi\Os\Audit\RunContext;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\SchemaCoercer;
use Masilia\IbexaAi\Os\SchemaValidator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Prepares, previews and runs a capability call: the one place where a call is checked (it exists, the user may use
 * it, its arguments follow its schema), where an exception becomes an answer, and where what changed is recorded.
 * The tool of a capability and propose_changes both go through it.
 */
final readonly class CapabilityRunner
{
    public function __construct(
        private CapabilityRegistry $registry,
        private CapabilityAccess $access,
        private AuditLog $audit,
        private RunContext $context,
        private PermissionResolver $permissions,
        private LoggerInterface $aiLogger,
    ) {
    }

    /**
     * @param array<string, mixed>|mixed $arguments
     * @param (callable(array<string, mixed>, array<string, mixed>): array<string, mixed>)|null $forValidation Turns arguments into ones that can be
     *        validated when some values are not known yet (a reference to an earlier step), given the schema
     * @param bool $checkState False to check the schema only: what the capability checks against the repository is left to when it runs
     *        (an earlier step of the same batch may create what this one needs)
     *
     * @return array{0: Capability|null, 1: array<string, mixed>, 2: string|null} The capability, its arguments (typed), and what is wrong
     */
    public function prepare(string $name, mixed $arguments, ?callable $forValidation = null, bool $checkState = true): array
    {
        // The tool of a capability is named with "__" for the dots: either spelling means the same capability.
        $name = str_replace('__', '.', trim($name));
        $capability = $this->registry->get($name);
        if ($capability === null) {
            return [null, [], sprintf('There is no capability "%s". Search for it with tool_search.', $name)];
        }
        $denial = $this->access->denial($capability);
        if ($denial !== null) {
            return [$capability, [], sprintf('%s is not available: %s', $name, $denial)];
        }

        $arguments ??= [];
        if (!is_array($arguments)) {
            return [$capability, [], 'arguments must be an object.'];
        }

        // A value that plainly means what the schema asks for is turned into it: "50" for 50.
        $schema = $capability->inputSchema();
        $coerced = SchemaCoercer::coerce($schema, $arguments);
        $arguments = is_array($coerced) ? $coerced : $arguments;
        $checked = $forValidation !== null ? $forValidation($schema, $arguments) : $arguments;
        $errors = SchemaValidator::validate($schema, $checked);
        if ($errors !== []) {
            return [$capability, $arguments, sprintf('%s: %s Check the arguments against its definition.', $name, implode(' ', $errors))];
        }
        // What the schema cannot see. Not when some values are references: they are checked when they are known.
        $problem = $checkState && $checked === $arguments ? $this->problem($capability, $arguments) : null;
        if ($problem !== null) {
            return [$capability, $arguments, sprintf('%s: %s', $name, $problem)];
        }

        return [$capability, $arguments, null];
    }

    private function problem(Capability $capability, array $arguments): ?string
    {
        try {
            return $capability->validate($arguments);
        } catch (Throwable $e) {
            return $e->getMessage() !== '' ? $e->getMessage() : null;
        }
    }

    /**
     * What the call will do. When the capability cannot say (it fails), the call is still shown with what is known
     * of it: it is never run without being shown.
     *
     * @param array<string, mixed> $args
     */
    public function preview(Capability $capability, array $args): Preview
    {
        try {
            return $capability->preview($args);
        } catch (Throwable) {
            return new Preview($capability->summary(), [['Capability', $capability->name()], ['Arguments', mb_substr((string) json_encode($args, JSON_UNESCAPED_UNICODE), 0, 300)]]);
        }
    }

    /**
     * Runs the capability. An exception is an answer, not a crash. A call that changes something, and any failure, is
     * recorded, unless the caller records it itself ($audit = false), for example when the record must be written after
     * a transaction ends.
     *
     * @param array<string, mixed> $args
     */
    public function run(Capability $capability, array $args, bool $audit = true): CapabilityResult
    {
        try {
            $result = $capability->execute($args);
        } catch (UnauthorizedException $e) {
            $result = CapabilityResult::error(sprintf('You are not allowed to do this: %s', mb_substr($e->getMessage(), 0, 300)));
        } catch (Throwable $e) {
            $this->aiLogger->error('[OS] {capability} failed: {message}', ['capability' => $capability->name(), 'message' => $e->getMessage(), 'exception' => $e]);
            $result = CapabilityResult::error(sprintf('%s failed: %s', $capability->name(), mb_substr($e->getMessage(), 0, 300)));
        }

        if ($audit && ($capability->risk()->needsApproval() || !$result->success)) {
            $this->record($capability, $args, $result->success, $result->message);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function record(Capability $capability, array $args, bool $success, string $message): void
    {
        try {
            $this->audit->record((int) $this->permissions->getCurrentUserReference()->getUserId(), $this->context->threadId, $capability->name(), $capability->risk(), $args, $success, $message);
        } catch (Throwable $e) {
            // The action is done: a failure of the log is reported, not thrown at the user.
            $this->aiLogger->error('[OS] The audit log could not be written: {message}', ['message' => $e->getMessage()]);
        }
    }
}
