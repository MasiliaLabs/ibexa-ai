<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaAi\Observability;

use Masilia\Contracts\IbexaAi\Provider\ChatConnection;
use NeuronAI\Workflow\WorkflowInterface;

/**
 * Records every call a Neuron agent makes to the model (which model, how long, how many tokens, whether it worked) for the usage
 * dashboard of the admin. No prompt and no answer is kept. A package that runs an agent attaches it, and its calls are counted
 * with those of the chat.
 */
interface UsageRecorder
{
    public function attach(WorkflowInterface $workflow, ChatConnection $connection, ?string $siteaccess): void;
}
