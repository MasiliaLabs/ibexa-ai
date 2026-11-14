<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Neuron;

use Masilia\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Contracts\IbexaAi\Os\Capability;
use NeuronAI\Tools\Tool;

/**
 * One capability as a tool of the Neuron agent. The model calls it by its own arguments; whatever changes something is
 * shown to the user for approval by Neuron (the card is the preview of the capability) before it runs, and what ran is
 * recorded. Every check (it exists, the user may use it, the arguments follow the schema) is the runner's.
 *
 * The tool is named after the capability with "__" for the dots ("content.update" is content__update): providers accept
 * letters, digits, "_" and "-" in a tool name.
 */
final class CapabilityTool extends Tool
{
    public function __construct(
        private readonly Capability $capability,
        private readonly CapabilityRunner $runner,
    ) {
        $this->setName(self::toolName($capability->name()));
        $this->setDescription(self::describe($capability));
    }

    public static function toolName(string $capability): string
    {
        return str_replace('.', '__', $capability);
    }

    public static function capabilityName(string $toolName): string
    {
        return str_replace('__', '.', $toolName);
    }

    public function capability(): Capability
    {
        return $this->capability;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return $this->capability->inputSchema();
    }

    protected function approvalPolicy(): bool|string
    {
        [$capability, $args, $error] = $this->runner->prepare($this->capability->name(), $this->getInputs());
        if ($capability === null || $error !== null || !$capability->risk()->needsApproval()) {
            return false;
        }

        return $this->runner->preview($capability, $args)->toApprovalText($capability->risk());
    }

    public function execute(): void
    {
        [$capability, $args, $error] = $this->runner->prepare($this->capability->name(), $this->getInputs());
        if ($capability === null || $error !== null) {
            $result = ToolResult::error($error ?? 'Unknown capability.');
        } else {
            $outcome = $this->runner->run($capability, $args);
            $result = $outcome->success ? ToolResult::ok($outcome->message, $outcome->data) : ToolResult::error($outcome->message);
        }

        $this->setResult($result->toOutput());
    }

    private static function describe(Capability $capability): string
    {
        $text = trim($capability->summary() . ' ' . $capability->description());
        if ($capability->risk()->needsApproval()) {
            $text .= sprintf(' Changes something (%s): the user approves it first, never ask in your reply.', $capability->risk()->value);
        }
        $example = $capability->examples()[0] ?? null;
        if ($example !== null) {
            $text .= sprintf(' Example (%s): %s', $example['description'], json_encode($example['arguments'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $text;
    }
}
