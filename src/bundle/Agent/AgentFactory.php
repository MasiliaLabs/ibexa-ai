<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Agent;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\IbexaAi\Agent\Neuron\NeuronToolAdapter;
use Masilia\Bundle\IbexaAi\Agent\Tool\Control\BrowseLocationTool;
use Masilia\Bundle\IbexaAi\Agent\Tool\Control\ExploreSiteTool;
use Masilia\Bundle\IbexaAi\Agent\Tool\Control\ProposePlanTool;
use Masilia\Bundle\IbexaAi\Llm\ProviderResolver;
use Masilia\IbexaAi\Provider\ProviderFactory;
use NeuronAI\Chat\History\SQLMessageStore;
use PDO;

/**
 * Builds the content agent for a siteaccess and a conversation thread.
 *
 * The model sees three tools (explore_site, browse_location, propose_plan); the other content
 * tools are used internally by those three and are not exposed.
 */
final readonly class AgentFactory
{
    public function __construct(
        private ProviderResolver $resolver,
        private ProviderFactory $providers,
        private EntityManagerInterface $entities,
        private ExploreSiteTool $explore,
        private BrowseLocationTool $browse,
        private ProposePlanTool $proposePlan,
    ) {
    }

    public function create(string $siteaccess, string $threadId): AiAssistantAgent
    {
        $llm = $this->providers->create($this->resolver->resolveChat($siteaccess));

        $agent = new AiAssistantAgent($llm, [
            new NeuronToolAdapter($this->explore),
            new NeuronToolAdapter($this->browse),
            new NeuronToolAdapter($this->proposePlan),
        ], $this->pdo());

        return $agent->setThreadId($threadId);
    }

    /**
     * Read access to a thread's stored messages, without running the agent.
     */
    public function messages(string $threadId): SQLMessageStore
    {
        return new SQLMessageStore($this->pdo(), AiAssistantAgent::MESSAGES_TABLE);
    }

    /**
     * Removes the suspended-run state of a thread (used when the conversation is cleared).
     */
    public function forgetWorkflow(string $threadId): void
    {
        $pattern = '%' . addcslashes($threadId, '%_\\') . '%';

        $statement = $this->pdo()->prepare(
            'DELETE FROM ' . AiAssistantAgent::WORKFLOW_TABLE . ' WHERE `partition` LIKE ?',
        );
        $statement->execute([$pattern]);
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
