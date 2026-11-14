<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Tool;

use Ibexa\Contracts\Core\Repository\Repository;
use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\References;
use Masilia\Contracts\IbexaAi\Os\Risk;
use RuntimeException;
use Throwable;

/**
 * Several capabilities that belong together, approved once and applied together: if a step fails, none of the changes
 * is kept (the repository transaction is rolled back). A step can use what an earlier one produced ("$folder.location_id").
 */
final readonly class ProposeChangesTool implements ToolInterface, GatedToolInterface
{
    public const MAX_STEPS = 15;
    private const ID = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private CapabilityRunner $runner,
        private Repository $repository,
    ) {
    }

    public function getName(): string
    {
        return 'propose_changes';
    }

    public function getDescription(): string
    {
        return sprintf(
            'Apply several capabilities as one change: the user approves the whole list once, and if a step fails none of them is kept. '
            . 'Each step is {capability, arguments, id?}. A step can use what an earlier step answered: write "$id.field" (for example '
            . '"$folder.location_id", where the step with id "folder" created a folder) instead of a value. At most %d steps. '
            . 'Use run_capability for a single step.',
            self::MAX_STEPS,
        );
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'required' => ['steps'],
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'description' => 'The steps, in the order they run',
                    'items' => [
                        'type' => 'object',
                        'required' => ['capability'],
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'A name other steps can refer to: lower case letters, digits and _ (default: s1, s2…)'],
                            'capability' => ['type' => 'string'],
                            'arguments' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function approvalReason(array $arguments): ?string
    {
        [$steps, $error] = $this->prepare($arguments);
        if ($error !== null || !array_filter($steps, static fn (array $step): bool => $step['capability']->risk()->needsApproval())) {
            return null;
        }

        $rows = [];
        $notes = [];
        $highest = Risk::READ;
        foreach ($steps as $number => $step) {
            /** @var Capability $capability */
            $capability = $step['capability'];
            $highest = self::higher($highest, $capability->risk());

            if ($step['uses'] === []) {
                $preview = $this->runner->preview($capability, $step['args']);
                $detail = implode(', ', array_map(static fn (array $row): string => sprintf('%s %s', $row[0], $row[1]), array_slice($preview->rows, 0, 4)));
                $text = $preview->title . ($detail !== '' ? ': ' . $detail : '');
                if (in_array($capability->risk(), [Risk::DESTRUCTIVE, Risk::ACCESS], true)) {
                    array_push($notes, ...$preview->notes);
                }
            } else {
                // A step that uses an earlier answer cannot be previewed yet: it is shown by what it is given.
                $text = $capability->name() . ': ' . self::arguments($step['args']);
                if (in_array($capability->risk(), [Risk::DESTRUCTIVE, Risk::ACCESS], true)) {
                    $notes[] = sprintf('Step %d cannot be undone.', $number + 1);
                }
            }
            $rows[] = [sprintf('Step %d', $number + 1), mb_substr($text, 0, 220)];
        }
        $notes[] = 'The steps are applied together: if one fails, none of the changes is kept.';

        return (new Preview(sprintf('Apply %d changes', count($steps)), $rows, array_values(array_unique($notes))))->toApprovalText($highest);
    }

    public function execute(array $params): ToolResult
    {
        [$steps, $error] = $this->prepare($params);
        if ($error !== null) {
            return ToolResult::error($error);
        }

        $answers = [];
        /** @var list<array{step: array<string, mixed>, args: array<string, mixed>, result: CapabilityResult}> $done */
        $done = [];
        $failure = null;

        $this->repository->beginTransaction();
        try {
            foreach ($steps as $step) {
                /** @var Capability $capability */
                $capability = $step['capability'];
                try {
                    $resolved = References::resolve($step['args'], $answers);
                } catch (RuntimeException $e) {
                    $failure = [$step, $e->getMessage()];
                    break;
                }
                // What was a reference is now a value: it is checked again, as typed.
                [, $typed, $problem] = $this->runner->prepare($capability->name(), $resolved);
                if ($problem !== null) {
                    $failure = [$step, $problem];
                    break;
                }

                $result = $this->runner->run($capability, $typed, false);
                $done[] = ['step' => $step, 'args' => $typed, 'result' => $result];
                if (!$result->success) {
                    $failure = [$step, $result->message];
                    break;
                }
                $answers[$step['id']] = $result->data;
            }
        } catch (Throwable $e) {
            $failure ??= [$steps[count($done)] ?? $steps[0], $e->getMessage()];
        }

        if ($failure !== null) {
            $this->repository->rollback();
            $this->recordAll($done, $failure);

            return ToolResult::error(sprintf('Step "%s" (%s) failed: %s None of the changes was kept.', $failure[0]['id'], $failure[0]['capability']->name(), rtrim($failure[1], '.') . '.'));
        }

        $this->repository->commit();
        $this->recordAll($done, null);

        return ToolResult::ok(sprintf('%d steps applied.', count($done)), [
            'applied' => true,
            'steps' => array_map(static fn (array $row): array => ['id' => $row['step']['id'], 'capability' => $row['step']['capability']->name(), 'message' => $row['result']->message, 'data' => $row['result']->data], $done),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{0: list<array{id: string, capability: Capability, args: array<string, mixed>, uses: list<string>}>, 1: string|null}
     */
    private function prepare(array $params): array
    {
        $raw = $params['steps'] ?? null;
        if (!is_array($raw) || $raw === [] || !array_is_list($raw)) {
            return [[], 'steps must be a list of at least one step: {capability, arguments, id?}.'];
        }
        if (count($raw) > self::MAX_STEPS) {
            return [[], sprintf('There are %d steps: at most %d at once. Split them.', count($raw), self::MAX_STEPS)];
        }

        $steps = [];
        $seen = [];
        $changes = false;
        foreach ($raw as $index => $step) {
            $id = is_array($step) && isset($step['id']) ? (string) $step['id'] : sprintf('s%d', $index + 1);
            if (!is_array($step) || !is_string($step['capability'] ?? null)) {
                return [[], sprintf('Step %d needs a "capability".', $index + 1)];
            }
            if (preg_match(self::ID, $id) !== 1) {
                return [[], sprintf('Step %d: the id "%s" must be lower case letters, digits and _, starting with a letter.', $index + 1, $id)];
            }
            if (isset($seen[$id])) {
                return [[], sprintf('Step %d: the id "%s" is already used.', $index + 1, $id)];
            }

            $arguments = $step['arguments'] ?? [];
            $uses = is_array($arguments) ? References::steps($arguments) : [];
            foreach ($uses as $used) {
                if (!isset($seen[$used])) {
                    return [[], sprintf('Step "%s" uses the result of "%s", which is not an earlier step.', $id, $used)];
                }
            }

            // After a step that changes something, the repository is no longer what it is now: what a step needs from it is checked when it runs.
            [$capability, $typed, $problem] = $this->runner->prepare($step['capability'], $arguments, static fn (array $schema, array $args): array => (array) References::standIn($schema, $args), !$changes);
            if ($capability === null || $problem !== null) {
                return [[], sprintf('Step "%s": %s', $id, $problem ?? 'Unknown capability.')];
            }

            $seen[$id] = true;
            $changes = $changes || $capability->risk() !== Risk::READ;
            $steps[] = ['id' => $id, 'capability' => $capability, 'args' => $typed, 'uses' => $uses];
        }

        return [$steps, null];
    }

    /**
     * The arguments of a step in a line: what is a reference reads "result of step.field", a structure is named by its keys.
     *
     * @param array<string, mixed> $args
     */
    private static function arguments(array $args): string
    {
        $parts = [];
        foreach ($args as $name => $value) {
            $reference = References::parse($value);
            $parts[] = sprintf('%s: %s', $name, match (true) {
                $reference !== null => sprintf('result of %s.%s', $reference[0], $reference[1]),
                is_array($value) && array_is_list($value) => sprintf('%d item(s)', count($value)),
                is_array($value) => implode('/', array_map('strval', array_slice(array_keys($value), 0, 5))),
                default => (string) $value,
            });
        }

        return implode(', ', $parts);
    }

    private static function higher(Risk $a, Risk $b): Risk
    {
        $order = [Risk::READ->value => 0, Risk::WRITE->value => 1, Risk::DESTRUCTIVE->value => 2, Risk::ACCESS->value => 3];

        return $order[$b->value] > $order[$a->value] ? $b : $a;
    }

    /**
     * What ran is recorded once the transaction is over: the record is in the same database and must outlive a rollback.
     *
     * @param list<array{step: array<string, mixed>, args: array<string, mixed>, result: CapabilityResult}> $done
     * @param array{0: array<string, mixed>, 1: string}|null                                              $failure
     */
    private function recordAll(array $done, ?array $failure): void
    {
        foreach ($done as $row) {
            /** @var Capability $capability */
            $capability = $row['step']['capability'];
            if (!$capability->risk()->needsApproval() && $row['result']->success && $failure === null) {
                continue;
            }
            $rolledBack = $failure !== null && $row['result']->success;
            $this->runner->record(
                $capability,
                $row['args'],
                $failure === null && $row['result']->success,
                $rolledBack ? sprintf('Rolled back because step "%s" failed: %s', $failure[0]['id'], $failure[1]) : $row['result']->message,
            );
        }
        if ($failure !== null && !array_filter($done, static fn (array $row): bool => !$row['result']->success)) {
            // The failure came before the step ran (a reference or its arguments): it is recorded too.
            $this->runner->record($failure[0]['capability'], $failure[0]['args'], false, $failure[1]);
        }
    }
}
