<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Providers\OpenAILike;

/**
 * OpenAI-protocol provider that tolerates tool-call turns without a "content" key
 * (MiniMax and Moonshot send them; Neuron's default reads the key unconditionally).
 */
final class CompatibleOpenAi extends OpenAILike
{
    /**
     * @param array<string, mixed> $message
     */
    protected function createAssistantMessage(array $message): AssistantMessage
    {
        return new AssistantMessage($message['content'] ?? '');
    }
}
