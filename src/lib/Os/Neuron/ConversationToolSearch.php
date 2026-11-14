<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Neuron;

use NeuronAI\Agent\Middleware\ToolSearchMiddleware;

/**
 * Neuron's tool search, with what it found staying found for the whole conversation. Neuron re-derives the tools a model found
 * from the messages after the last user message only, so a tool found at one turn is gone at the next, while its name is still
 * in the history the model reads: it calls it, and the run fails ("non-existing tool"). Here every message counts.
 */
final class ConversationToolSearch extends ToolSearchMiddleware
{
    protected function currentTurn(array $messages): array
    {
        return $messages;
    }
}
