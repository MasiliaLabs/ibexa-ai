<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Os\Neuron;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\IbexaAi\Agent\Neuron\NeuronToolAdapter;
use Masilia\Bundle\IbexaAi\Llm\ProviderResolver;
use Masilia\Bundle\IbexaAi\Os\Tool\DescribeCapabilityTool;
use Masilia\Bundle\IbexaAi\Os\Tool\FindCapabilitiesTool;
use Masilia\Bundle\IbexaAi\Os\Tool\ProposeChangesTool;
use Masilia\Bundle\IbexaAi\Os\Tool\RunCapabilityTool;
use Masilia\IbexaAi\Provider\ProviderFactory;
use NeuronAI\Chat\History\SQLMessageStore;
use PDO;

/**
 * Builds the content agent for a siteaccess and a conversation thread.
 *
 * The model sees the four tools of the AI OS (find_capabilities, describe_capability, run_capability, propose_changes):
 * everything else, including what the packages on the OS bring, is a capability found through them. The system prompt is
 * the OS's text and the skills of those packages.
 */
readonly class OsAgentFactory
{
    public function __construct(
        private ProviderResolver $resolver,
        private ProviderFactory $providers,
        private EntityManagerInterface $entities,
        private FindCapabilitiesTool $findCapabilities,
        private DescribeCapabilityTool $describeCapability,
        private RunCapabilityTool $runCapability,
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
            return $this->resolver->resolveChat($siteaccess)->supportsVision;
        } catch (\Throwable) {
            return false;
        }
    }

    public function create(string $siteaccess, string $threadId): OsAgent
    {
        $llm = $this->providers->create($this->resolver->resolveChat($siteaccess));

        $agent = new OsAgent($llm, [
            new NeuronToolAdapter($this->findCapabilities),
            new NeuronToolAdapter($this->describeCapability),
            new NeuronToolAdapter($this->runCapability),
            new NeuronToolAdapter($this->proposeChanges),
        ], $this->pdo(), OsSystemPrompt::get($this->skills));

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
        // Exact match only: one thread id can be the prefix of another ("…-admin" and "…-admin_fossil_exit").
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
