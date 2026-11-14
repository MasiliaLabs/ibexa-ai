<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Os\Neuron;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Contracts\IbexaAi\Observability\UsageRecorder;
use Masilia\Contracts\IbexaAi\Provider\ChatProviders;
use NeuronAI\Chat\History\SQLMessageStore;
use PDO;

/**
 * Builds the content agent for a siteaccess and a conversation thread.
 *
 * The model has propose_changes, the capabilities that look around, and tool_search to find the rest of what the user may do:
 * every capability is a tool of its own (see CapabilityTool). The system prompt is the OS's text and the skills of the packages
 * built on it.
 */
readonly class OsAgentFactory
{
    /** The capabilities the model has from the start of every turn: the ones that look around. */
    private const ALWAYS = ['content.search', 'content.load', 'tree.browse'];

    public function __construct(
        private ChatProviders $chat,
        private UsageRecorder $usage,
        private EntityManagerInterface $entities,
        private CapabilityToolPool $pool,
        private ProposeChangesTool $proposeChanges,
        /** @var iterable<\Masilia\Contracts\IbexaAi\Os\Skill> What the packages on the OS teach the assistant, added to its prompt */
        private iterable $skills = [],
    ) {
    }

    /**
     * Whether the chat model of the siteaccess reads images (the admin says so, per model).
     */
    public function supportsVision(string $siteaccess): bool
    {
        try {
            return $this->chat->forSiteaccess($siteaccess)->supportsVision;
        } catch (\Throwable) {
            return false;
        }
    }

    public function create(string $siteaccess, string $threadId): OsAgent
    {
        $connection = $this->chat->forSiteaccess($siteaccess);
        $llm = $connection->provider;

        // What the user may use, as tools: the few that look around are there from the start, the rest is found with tool_search.
        $always = [$this->proposeChanges];
        $searchable = [];
        foreach ($this->pool->forCurrentUser() as $tool) {
            if (in_array($tool->capability()->name(), self::ALWAYS, true)) {
                $always[] = $tool;
            } else {
                $searchable[] = $tool;
            }
        }

        $agent = new OsAgent($llm, $always, $searchable, $this->pdo(), OsSystemPrompt::get($this->skills));

        $this->usage->attach($agent, $connection, $siteaccess);

        return $agent->setThreadId($threadId);
    }

    /**
     * Read access to a thread's stored messages, without running the agent.
     */
    public function messages(string $threadId): SQLMessageStore
    {
        return new SQLMessageStore($this->pdo(), OsAgent::MESSAGES_TABLE);
    }

    /**
     * Removes the suspended-run state of a thread (used when the conversation is cleared).
     */
    public function forgetWorkflow(string $threadId): void
    {
        // Neuron's DatabasePersistence stores the partition (the workflow id, here the thread id) hex-encoded.
        // Exact match only: one thread id can be the prefix of another ("…-admin" and "…-admin_shop").
        $statement = $this->pdo()->prepare(
            'DELETE FROM ' . OsAgent::WORKFLOW_TABLE . ' WHERE `partition` = ?',
        );
        $statement->execute([bin2hex($threadId)]);
    }

    public function pdo(): PDO
    {
        $connection = $this->entities->getConnection()->getNativeConnection();
        if (!$connection instanceof PDO) {
            throw new \RuntimeException('The agent needs a PDO connection.');
        }

        return $connection;
    }
}
