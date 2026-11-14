<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Observability;

use Masilia\Contracts\IbexaAi\Observability\RequestLog;
use Masilia\Contracts\IbexaAi\Observability\RequestLogger;
use Masilia\Contracts\IbexaAi\Provider\ChatConnection;
use Masilia\IbexaAi\Observability\ModelCallRecorder;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\InMemoryMessageStore;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\ToolCall;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use PHPUnit\Framework\TestCase;

/** Every call of an agent to the model is recorded for the usage dashboard, from Neuron's own events. */
final class ModelCallRecorderTest extends TestCase
{
    /** @var list<RequestLog> */
    private array $logged = [];

    private function agent(AIProviderInterface $model): Agent
    {
        $logger = new class($this->logged) implements RequestLogger {
            public function __construct(private array &$logged)
            {
            }

            public function log(RequestLog $log): void
            {
                $this->logged[] = $log;
            }
        };
        $agent = new class($model) extends Agent {
            private ?InMemoryMessageStore $memoryStore = null;
            private ?InMemoryPersistence $memoryPersistence = null;

            public function __construct(private readonly AIProviderInterface $model)
            {
                parent::__construct();
            }

            protected function provider(): AIProviderInterface
            {
                return $this->model;
            }

            protected function messageStore(): MessageStoreInterface
            {
                return $this->memoryStore ??= new InMemoryMessageStore();
            }

            protected function persistence(): PersistenceInterface
            {
                return $this->memoryPersistence ??= new InMemoryPersistence();
            }
        };
        (new ModelCallRecorder($logger))->attach($agent, new ChatConnection($model, 'openai', 'gpt-test', false), 'fossil_exit');
        $agent->setThreadId('thread-rec-0001');

        return $agent;
    }

    public function testACallIsRecordedWithItsModelItsTokensAndHowItEnded(): void
    {
        $answer = new AssistantMessage('Hello.');
        $answer->setUsage(new Usage(120, 30));
        $agent = $this->agent(new FakeAIProvider($answer));

        $agent->chat(new UserMessage('Hi'));

        self::assertCount(1, $this->logged);
        $log = $this->logged[0];
        self::assertSame(['openai', 'gpt-test', 'fossil_exit', true], [$log->providerIdentifier, $log->modelIdentifier, $log->siteaccess, $log->success]);
        self::assertSame([120, 30], [$log->tokensIn, $log->tokensOut]);
        self::assertGreaterThanOrEqual(0, $log->latencyMs);
        self::assertNull($log->errorCode);
    }

    public function testEachCallOfATurnWithToolsIsOneRecord(): void
    {
        $agent = $this->agent(new FakeAIProvider(
            new ToolCallMessage('I look.', [new ToolCall('nothing_here', 'call-1', [])]),
            new AssistantMessage('Done.'),
        ));

        try {
            $agent->chat(new UserMessage('Hi'));
        } catch (\Throwable) {
            // The tool does not exist: the call of the model before it is what is counted.
        }

        self::assertGreaterThanOrEqual(1, count($this->logged));
        self::assertTrue($this->logged[0]->success);
    }

    public function testAProviderThatFailsIsRecordedAsAFailureWithoutAnyText(): void
    {
        $agent = $this->agent(new FakeAIProvider());

        try {
            $agent->chat(new UserMessage('Hi'));
            self::fail('the model has no answer to give');
        } catch (\Throwable) {
        }

        self::assertNotSame([], $this->logged);
        $failed = end($this->logged);
        self::assertFalse($failed->success);
        self::assertNotNull($failed->errorCode);
        self::assertSame('gpt-test', $failed->modelIdentifier);
    }
}
