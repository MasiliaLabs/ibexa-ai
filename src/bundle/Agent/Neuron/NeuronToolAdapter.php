<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Neuron;

use ArrayObject;
use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use NeuronAI\Tools\Tool;

/**
 * Exposes one of the assistant's tools to the Neuron agent.
 *
 * The JSON schema and the result contract of the tool are kept as they are; the adapter only
 * bridges the call. Gated tools (see {@see GatedToolInterface}) make Neuron pause for approval,
 * and the approval reason is the summary the user reads.
 *
 * Each tool has a call budget per run. Past it, the tool is not executed any more: the model is
 * told to answer with what it has. Neuron's own limit, which aborts the whole run, is kept a few
 * calls above the budget as a last resort.
 */
final class NeuronToolAdapter extends Tool
{
    public const CALL_BUDGET = 8;
    private const HARD_LIMIT_MARGIN = 4;

    /** @var ArrayObject<string, int> shared by the copies Neuron makes of this tool during a run */
    private readonly ArrayObject $calls;

    public function __construct(
        private readonly ToolInterface $tool,
    ) {
        $this->calls = new ArrayObject(['count' => 0]);
        $this->setName($tool->getName());
        $this->setDescription($tool->getDescription());
        $this->setMaxRuns(self::CALL_BUDGET + self::HARD_LIMIT_MARGIN);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return $this->tool->getParameters();
    }

    protected function approvalPolicy(): bool|string
    {
        if (!$this->tool instanceof GatedToolInterface) {
            return false;
        }

        return $this->tool->approvalReason($this->getInputs()) ?? false;
    }

    public function execute(): void
    {
        $this->calls['count'] += 1;

        $result = $this->calls['count'] > self::CALL_BUDGET
            ? ToolResult::error(sprintf(
                'Call budget reached: %s was already called %d times in this turn and was NOT run again. ' .
                'Do not call it again. Answer the user now with what you found, and say plainly what you could not check.',
                $this->tool->getName(),
                self::CALL_BUDGET,
            ))
            : $this->tool->execute($this->getInputs());

        $this->setResult(json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
