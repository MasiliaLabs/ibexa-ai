<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\IbexaAi\Os\Audit\AuditLog;
use Masilia\IbexaAi\Os\Audit\RunContext;
use Masilia\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\IbexaAi\Os\Neuron\CapabilityToolPool;
use Masilia\IbexaAi\Os\Neuron\OsAgent;
use Masilia\Contracts\IbexaAi\Os\AbstractCapability;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use NeuronAI\Chat\History\InMemoryMessageStore;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The agent as the chat runs it, on a model that follows a script: the model finds a capability with tool_search, calls
 * it, the system asks the user, and the capability runs only once the user says yes.
 */
final class OsAgentTest extends TestCase
{
    private Connection $connection;
    /** @var list<string> */
    private array $ran = [];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE app_ai_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, thread_id VARCHAR(64) NULL, capability VARCHAR(80) NOT NULL, risk VARCHAR(12) NOT NULL,
            arguments TEXT NOT NULL, success TINYINT NOT NULL, message VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL)');
        $this->ran = [];
    }

    private function capability(string $name, string $summary, Risk $risk): AbstractCapability
    {
        $ran = &$this->ran;

        return new class($name, $summary, $risk, $ran) extends AbstractCapability {
            public function __construct(private string $name, private string $summary, private Risk $risk, private array &$ran)
            {
            }

            public function name(): string { return $this->name; }
            public function summary(): string { return $this->summary; }
            public function description(): string { return $this->summary . ' (details)'; }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer']]]; }
            public function risk(): Risk { return $this->risk; }
            public function examples(): array { return [['description' => 'Example', 'arguments' => ['id' => 1]]]; }
            public function preview(array $arguments): Preview { return new Preview('Do ' . $this->name, [['Id', (string) $arguments['id']]]); }

            public function execute(array $arguments): CapabilityResult
            {
                $this->ran[] = $this->name;

                return CapabilityResult::ok('Done', ['id' => $arguments['id']]);
            }
        };
    }

    /** @param list<ToolInterface> $searchable */
    private function agent(FakeAIProvider $model, array $searchable): OsAgent
    {
        return new class($model, [], $searchable, new PDO('sqlite::memory:'), 'You help.') extends OsAgent {
            private ?InMemoryMessageStore $memoryStore = null;
            private ?InMemoryPersistence $memoryPersistence = null;

            protected function messageStore(): MessageStoreInterface
            {
                return $this->memoryStore ??= new InMemoryMessageStore();
            }

            protected function persistence(): PersistenceInterface
            {
                return $this->memoryPersistence ??= new InMemoryPersistence();
            }
        };
    }

    public function testTheModelFindsACapabilityCallsItAndTheUserApprovesBeforeItRuns(): void
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturn(true);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));
        $registry = new CapabilityRegistry([
            $this->capability('trash.wipe', 'Wipe an item of the trash for good.', Risk::DESTRUCTIVE),
            $this->capability('location.move', 'Move a location below another parent.', Risk::WRITE),
        ]);
        $access = new CapabilityAccess($permissions, []);
        $runner = new CapabilityRunner($registry, $access, new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger());
        $tools = (new CapabilityToolPool($registry, $access, $runner))->forCurrentUser();

        // The script of the model: look for what it needs, then call it, then answer.
        $model = new FakeAIProvider(
            new ToolCallMessage('I look for it.', [new ToolCall('tool_search', 'call-1', ['query' => 'wipe trash'])]),
            new ToolCallMessage('I wipe it.', [new ToolCall('trash__wipe', 'call-2', ['id' => 7])]),
            new AssistantMessage('The item is gone.'),
        );
        $agent = $this->agent($model, $tools)->setThreadId('thread-aaaa-0001');

        // The run stops at the change: nothing ran, and the card is the preview of the capability.
        $agent->chat(new UserMessage('Wipe the item 7 of the trash'));
        $pending = $agent->pendingApprovals();
        self::assertCount(1, $pending);
        self::assertSame('trash__wipe', $pending[0]->name);
        self::assertStringContainsString("Risk: destructive\n", (string) $pending[0]->reason);
        self::assertStringContainsString('Id: 7', (string) $pending[0]->reason);
        self::assertSame([], $this->ran, 'asked, not run');

        // The user says yes: it runs, is recorded, and the model answers.
        $state = $agent->submitApprovalDecisions(['call-2' => 'approve'])->run();
        self::assertSame(['trash.wipe'], $this->ran);
        self::assertSame('trash.wipe', $this->connection->fetchOne('SELECT capability FROM app_ai_audit'));
        self::assertSame('The item is gone.', $state->getResponse()?->message()->getContent());
    }

    public function testARefusedChangeDoesNotRun(): void
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturn(true);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));
        $registry = new CapabilityRegistry([$this->capability('location.move', 'Move a location below another parent.', Risk::WRITE)]);
        $access = new CapabilityAccess($permissions, []);
        $runner = new CapabilityRunner($registry, $access, new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger());
        $tools = (new CapabilityToolPool($registry, $access, $runner))->forCurrentUser();

        $model = new FakeAIProvider(
            new ToolCallMessage('Looking.', [new ToolCall('tool_search', 'call-1', ['query' => 'move'])]),
            new ToolCallMessage('Moving.', [new ToolCall('location__move', 'call-2', ['id' => 3])]),
            new AssistantMessage('I left it where it is.'),
        );
        $agent = $this->agent($model, $tools)->setThreadId('thread-aaaa-0002');

        $agent->chat(new UserMessage('Move the page'));
        self::assertCount(1, $agent->pendingApprovals());

        $agent->submitApprovalDecisions(['call-2' => ['reject', 'Not now']])->run();

        self::assertSame([], $this->ran);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_ai_audit'));
    }

    public function testAToolCalledPastItsBudgetIsNotRunAndTheModelIsToldToAnswer(): void
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturn(true);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));
        $registry = new CapabilityRegistry([$this->capability('content.look', 'Look at a content.', Risk::READ)]);
        $access = new CapabilityAccess($permissions, []);
        $runner = new CapabilityRunner($registry, $access, new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger());
        $tools = (new CapabilityToolPool($registry, $access, $runner))->forCurrentUser();

        // The model keeps calling the same tool, one call per message, one more time than it may.
        $script = [];
        for ($call = 1; $call <= OsAgent::CALL_BUDGET + 1; ++$call) {
            $script[] = new ToolCallMessage('Again.', [new ToolCall('content__look', 'call-' . $call, ['id' => $call])]);
        }
        $script[] = new AssistantMessage('Here is what I found.');
        $agent = $this->agent(new FakeAIProvider(...$script), []);
        // The read tool is among the ones the agent starts with.
        $agent->addTool($tools);
        $agent->setThreadId('thread-aaaa-0003');

        $state = $agent->chat(new UserMessage('Look at everything'));

        self::assertSame(array_fill(0, OsAgent::CALL_BUDGET, 'content.look'), $this->ran, 'the call over the budget was not run');
        self::assertSame('Here is what I found.', $state->getResponse()?->message()->getContent(), 'the turn went on to an answer, no exception');
        $answers = [];
        foreach ($state->getSteps() as $step) {
            if ($step instanceof \NeuronAI\Chat\Messages\ToolResultMessage) {
                foreach ($step->getToolCalls() as $result) {
                    $answers[] = (string) $result->getResult();
                }
            }
        }
        self::assertStringContainsString('Call budget reached', end($answers));
        self::assertStringContainsString('"success":false', end($answers), 'in the answer the chat reads');
    }
}
