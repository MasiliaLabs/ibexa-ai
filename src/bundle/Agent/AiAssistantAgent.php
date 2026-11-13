<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent;

use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Chat\History\SQLMessageStore;
use NeuronAI\Chat\Messages\SystemMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Workflow\Persistence\DatabasePersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use PDO;

/**
 * The content agent. Conversation history and suspended approvals are stored in the database
 * (Neuron's PDO stores, tables created by the bundle migration), so a page reload or a second
 * request continues the same thread.
 */
class AiAssistantAgent extends Agent
{
    public const MESSAGES_TABLE = 'app_ai_agent_messages';
    public const WORKFLOW_TABLE = 'app_ai_agent_workflow';

    private ?PageContext $page = null;

    /**
     * @param list<\NeuronAI\Tools\ToolInterface> $exposedTools the tools the model can call
     */
    public function __construct(
        private readonly AIProviderInterface $llm,
        private readonly array $exposedTools,
        private readonly PDO $pdo,
    ) {
        parent::__construct();
    }

    /**
     * Tells the agent where the editor is, for the runs of this instance.
     */
    public function withPageContext(?PageContext $page): static
    {
        $this->page = $page;

        return $this;
    }

    protected function provider(): AIProviderInterface
    {
        return $this->llm;
    }

    protected function instructions(): SystemMessage|string
    {
        return $this->page === null ? AgentSystemPrompt::get() : AgentSystemPrompt::get() . "\n\n" . $this->page->toPrompt();
    }

    protected function tools(): array
    {
        return $this->exposedTools;
    }

    protected function messageStore(): MessageStoreInterface
    {
        return new SQLMessageStore($this->pdo, self::MESSAGES_TABLE);
    }

    protected function persistence(): PersistenceInterface
    {
        return new DatabasePersistence($this->pdo, self::WORKFLOW_TABLE);
    }
}
