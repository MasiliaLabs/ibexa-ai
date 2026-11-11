<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent\Neuron;

use Masilia\Bundle\IbexaAi\Agent\Tool\GatedToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use NeuronAI\Tools\Tool;

/**
 * Exposes one of the assistant's tools to the Neuron agent.
 *
 * The JSON schema and the result contract of the tool are kept as they are; the adapter only
 * bridges the call. Gated tools (see {@see GatedToolInterface}) make Neuron pause for approval,
 * and the approval reason is the summary the user reads.
 */
final class NeuronToolAdapter extends Tool
{
    public function __construct(
        private readonly ToolInterface $tool,
    ) {
        $this->setName($tool->getName());
        $this->setDescription($tool->getDescription());
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
        $result = $this->tool->execute($this->getInputs());

        $this->setResult(json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
