<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Neuron;

use Masilia\IbexaAi\Chat\PageContext;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\Middleware\Summarization;
use NeuronAI\Agent\Middleware\ToolSearchMiddleware;
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
class OsAgent extends Agent
{
    public const MESSAGES_TABLE = 'app_ai_agent_messages';
    public const WORKFLOW_TABLE = 'app_ai_agent_workflow';

    /** How many tools one tool_search brings. */
    public const SEARCH_RESULTS = 8;

    private ?PageContext $page = null;
    private bool $vision = false;

    /**
     * @param list<\NeuronAI\Tools\ToolInterface> $exposedTools    the tools the model has from the start of every turn
     * @param list<\NeuronAI\Tools\ToolInterface> $searchableTools the others: the model finds them with tool_search, within the turn
     */
    public function __construct(
        private readonly AIProviderInterface $llm,
        private readonly array $exposedTools,
        private readonly array $searchableTools,
        private readonly PDO $pdo,
        /** The system prompt: the OS's own text and the fragments of the skills (see OsSystemPrompt). */
        private readonly string $systemPrompt = '',
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

    /**
     * Whether the model reads the images attached to the messages: it is told which, so that it does not pretend.
     */
    public function withVision(bool $vision): static
    {
        $this->vision = $vision;

        return $this;
    }

    protected function provider(): AIProviderInterface
    {
        return $this->llm;
    }

    protected function instructions(): SystemMessage|string
    {
        $prompt = ($this->systemPrompt !== '' ? $this->systemPrompt : OsSystemPrompt::get()) . "\n\n" . ($this->vision
            ? "## Images\nYou can see the images attached to a message: they come with it."
            : "## Images\nYou cannot see images. An `<attachment-image …/>` in a message only tells you the name and size of the image the user attached: if asked what is in it, say you cannot see it.");

        return $this->page === null ? $prompt : $prompt . "\n\n" . $this->page->toPrompt();
    }

    protected function tools(): array
    {
        return $this->exposedTools;
    }

    protected function globalMiddleware(): array
    {
        return [
            new ToolSearchMiddleware($this->searchableTools, self::SEARCH_RESULTS),
            // A long conversation is summarised before it outgrows the model's context.
            new Summarization($this->llm),
        ];
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
