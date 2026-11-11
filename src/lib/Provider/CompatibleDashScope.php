<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Provider;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Providers\Alibaba\DashScopeOpenAI;

/**
 * DashScope (Qwen) provider with the same tolerance as {@see CompatibleOpenAi}.
 */
final class CompatibleDashScope extends DashScopeOpenAI
{
    /**
     * @param array<string, mixed> $message
     */
    protected function createAssistantMessage(array $message): AssistantMessage
    {
        return new AssistantMessage($message['content'] ?? '');
    }
}
