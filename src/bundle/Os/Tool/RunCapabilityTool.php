<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Tool;

use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Bundle\IbexaAi\Os\Audit\AuditLog;
use Masilia\Bundle\IbexaAi\Os\Audit\RunContext;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\SchemaCoercer;
use Masilia\IbexaAi\Os\SchemaValidator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs a capability with the rights of the current user. What changes something is approved first: whether it is
 * approved is decided by the risk of the capability, never by the model.
 */
final readonly class RunCapabilityTool implements ToolInterface, GatedToolInterface
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

    public function getName(): string
    {
        return 'run_capability';
    }

    public function getDescription(): string
    {
        return 'Run a capability found with find_capabilities and read with describe_capability. Whatever changes something is shown '
            . 'to the user for approval by the system: never ask for confirmation in your reply. A refused call comes back with the reason.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The capability, e.g. "content.update"'],
                'arguments' => ['type' => 'object', 'description' => 'Its arguments, as described by describe_capability'],
            ],
        ];
    }

    /**
     * The reason shown to the user, for a call that changes something and can run; null when nothing is to be approved
     * (a read, or a call that cannot run and will say why).
     */
    public function approvalReason(array $arguments): ?string
    {
        [$capability, $args, $error] = $this->prepare($arguments);
        if ($capability === null || $error !== null || !$capability->risk()->needsApproval()) {
            return null;
        }

        try {
            $preview = $capability->preview($args);
        } catch (Throwable) {
            // The call is still approved before it runs, with what is known of it.
            $preview = new Preview($capability->summary(), [['Capability', $capability->name()], ['Arguments', mb_substr((string) json_encode($args, JSON_UNESCAPED_UNICODE), 0, 300)]]);
        }

        return $preview->toApprovalText($capability->risk());
    }

    public function execute(array $params): ToolResult
    {
        [$capability, $args, $error] = $this->prepare($params);
        if ($capability === null || $error !== null) {
            return ToolResult::error($error ?? 'Unknown capability.');
        }

        $message = '';
        try {
            $result = $capability->execute($args);
        } catch (UnauthorizedException $e) {
            $result = null;
            $message = sprintf('You are not allowed to do this: %s', mb_substr($e->getMessage(), 0, 300));
        } catch (Throwable $e) {
            $this->aiLogger->error('[OS] {capability} failed: {message}', ['capability' => $capability->name(), 'message' => $e->getMessage(), 'exception' => $e]);
            $result = null;
            $message = sprintf('%s failed: %s', $capability->name(), mb_substr($e->getMessage(), 0, 300));
        }

        $success = $result?->success ?? false;
        $text = $result?->message ?? $message;
        if ($capability->risk()->needsApproval() || !$success) {
            $this->record($capability, $args, $success, $text);
        }

        return $result !== null && $success ? ToolResult::ok($result->message, $result->data) : ToolResult::error($text);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{0: Capability|null, 1: array<string, mixed>, 2: string|null} The capability, its arguments, and what is wrong
     */
    private function prepare(array $params): array
    {
        $name = is_string($params['name'] ?? null) ? trim($params['name']) : '';
        $capability = $this->registry->get($name);
        if ($capability === null) {
            return [null, [], sprintf('There is no capability "%s". Use find_capabilities.', $name)];
        }
        $denial = $this->access->denial($capability);
        if ($denial !== null) {
            return [$capability, [], sprintf('%s is not available: %s', $name, $denial)];
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            return [$capability, [], 'arguments must be an object.'];
        }
        // A value that plainly means what the schema asks for is turned into it: "50" for 50.
        $coerced = SchemaCoercer::coerce($capability->inputSchema(), $arguments);
        $arguments = is_array($coerced) ? $coerced : $arguments;
        $errors = SchemaValidator::validate($capability->inputSchema(), $arguments);
        if ($errors !== []) {
            return [$capability, $arguments, sprintf('%s: %s Read it with describe_capability.', $name, implode(' ', $errors))];
        }

        return [$capability, $arguments, null];
    }

    /**
     * @param array<string, mixed> $args
     */
    private function record(Capability $capability, array $args, bool $success, string $message): void
    {
        try {
            $this->audit->record((int) $this->permissions->getCurrentUserReference()->getUserId(), $this->context->threadId, $capability->name(), $capability->risk(), $args, $success, $message);
        } catch (Throwable $e) {
            // The action is done: a failure of the log is reported, not thrown at the user.
            $this->aiLogger->error('[OS] The audit log could not be written: {message}', ['message' => $e->getMessage()]);
        }
    }
}
