<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\IbexaAi\Os\Audit\AuditLog;
use Masilia\IbexaAi\Os\Audit\RunContext;
use Masilia\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\IbexaAi\Os\Neuron\CapabilityTool;
use Masilia\IbexaAi\Os\Neuron\CapabilityToolPool;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;
use NeuronAI\Agent\Middleware\ToolSearchTool;
use Psr\Log\NullLogger;

final class OsToolsTest extends TestCase
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

    /**
     * @param array<string, mixed> $behaviour
     */
    private function capability(string $name, Risk $risk, array $behaviour = []): Capability
    {
        $ran = &$this->ran;

        return new class($name, $risk, $behaviour, $ran) implements Capability {
            public function __construct(private string $name, private Risk $risk, private array $behaviour, private array &$ran) {}

            public function name(): string { return $this->name; }
            public function summary(): string { return 'Summary of ' . $this->name; }
            public function description(): string { return 'Description of ' . $this->name; }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]]]; }
            public function risk(): Risk { return $this->risk; }
            public function requires(): array { return $this->behaviour['requires'] ?? []; }
            public function examples(): array { return [['description' => 'Example', 'arguments' => ['id' => 1]]]; }
            public function validate(array $arguments): ?string { return $this->behaviour['problem'] ?? null; }

            public function preview(array $arguments): Preview
            {
                if ($this->behaviour['previewFails'] ?? false) {
                    throw new \RuntimeException('no preview');
                }

                return new Preview('Do ' . $this->name, [['Id', (string) $arguments['id']]]);
            }

            public function execute(array $arguments): CapabilityResult
            {
                $this->ran[] = $this->name;
                if (isset($this->behaviour['throws'])) {
                    throw $this->behaviour['throws'];
                }

                return CapabilityResult::ok('Done ' . $this->name, ['id' => $arguments['id']]);
            }
        };
    }

    /**
     * @param list<Capability>     $capabilities
     * @param array<string, bool>  $domains
     * @param list<string>         $denied "module/function" the user does not have
     *
     * @return array{CapabilityToolPool, CapabilityRunner}
     */
    private function os(array $capabilities, array $domains = [], array $denied = []): array
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturnCallback(static fn (string $module, string $function): bool => !in_array($module . '/' . $function, $denied, true));
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));

        $registry = new CapabilityRegistry($capabilities);
        $access = new CapabilityAccess($permissions, $domains);
        $runner = new CapabilityRunner($registry, $access, new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger());

        return [new CapabilityToolPool($registry, $access, $runner), $runner];
    }

    /** The tool of a capability, whether or not the user may use it. */
    private function tool(Capability $capability, array $denied = []): CapabilityTool
    {
        [, $runner] = $this->os([$capability], [], $denied);

        return new CapabilityTool($capability, $runner);
    }

    /**
     * What the agent sees of a call: the approval it asks for (false, or the card), and the answer once it runs.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{0: bool|string, 1: array{success: bool, message: string, data: array<string, mixed>}}
     */
    private function call(CapabilityTool $tool, array $arguments): array
    {
        $tool->setInputs($arguments);
        $approval = $tool->requiresApproval();
        $tool->execute();

        return [$approval, json_decode((string) $tool->getResult(), true, 512, JSON_THROW_ON_ERROR)];
    }

    private function audit(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM app_ai_audit ORDER BY id');
    }

    public function testACapabilityIsATool(): void
    {
        $tool = $this->tool($this->capability('location.set_main', Risk::WRITE));

        self::assertSame('location__set_main', $tool->getName());
        self::assertSame('location.set_main', CapabilityTool::capabilityName($tool->getName()));
        self::assertSame(1, preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tool->getName()), 'a name every provider accepts');
        self::assertSame('integer', $tool->getInputSchema()['properties']['id']['type']);
        self::assertStringContainsString('Summary of location.set_main Description of location.set_main', (string) $tool->getDescription());
        self::assertStringContainsString('the user approves it first', (string) $tool->getDescription());
        self::assertStringContainsString('Example (Example): {"id":1}', (string) $tool->getDescription());
    }

    public function testAReadDoesNotSayItNeedsApproval(): void
    {
        self::assertStringNotContainsString('approves', (string) $this->tool($this->capability('content.look', Risk::READ))->getDescription());
    }

    public function testAReadRunsAtOnceWithoutApprovalAndLeavesNoRecord(): void
    {
        [$approval, $result] = $this->call($this->tool($this->capability('content.look', Risk::READ)), ['id' => 5]);

        self::assertFalse($approval);
        self::assertTrue($result['success']);
        self::assertSame(['content.look'], $this->ran);
        self::assertSame([], $this->audit());
    }

    public function testAWriteIsApprovedFirstOnACardMadeOfItsPreviewThenRecorded(): void
    {
        $tool = $this->tool($this->capability('content.change', Risk::WRITE));
        $tool->setInputs(['id' => 5]);

        self::assertSame("Action: Do content.change\nRisk: write\nId: 5\n\nThe change runs only if you approve it.", $tool->requiresApproval());
        self::assertSame([], $this->ran, 'asking does not run it');

        $tool->execute();
        self::assertTrue(json_decode((string) $tool->getResult(), true)['success']);
        $rows = $this->audit();
        self::assertCount(1, $rows);
        self::assertSame(['content.change', 'write', '1', 14], [$rows[0]['capability'], $rows[0]['risk'], (string) $rows[0]['success'], (int) $rows[0]['user_id']]);
        self::assertSame('{"id":5}', $rows[0]['arguments']);
    }

    public function testADestructiveCallNamesItsRiskOnTheCard(): void
    {
        $tool = $this->tool($this->capability('trash.wipe', Risk::DESTRUCTIVE));
        $tool->setInputs(['id' => 1]);

        self::assertStringContainsString("Risk: destructive\n", (string) $tool->requiresApproval());
    }

    public function testAWriteIsNeverRunWithoutACardEvenWhenItsPreviewFails(): void
    {
        $tool = $this->tool($this->capability('content.change', Risk::WRITE, ['previewFails' => true]));
        $tool->setInputs(['id' => 5]);

        $reason = $tool->requiresApproval();

        self::assertIsString($reason, 'the call is still approved before it runs');
        self::assertStringContainsString('Action: Summary of content.change', $reason);
        self::assertStringContainsString('Risk: write', $reason);
    }

    public function testBadArgumentsAreAnsweredNotApprovedNorRun(): void
    {
        [$approval, $result] = $this->call($this->tool($this->capability('content.change', Risk::WRITE)), ['id' => 'five']);

        self::assertFalse($approval);
        self::assertFalse($result['success']);
        self::assertStringContainsString('arguments.id must be a whole number', $result['message']);
        self::assertStringContainsString('Check the arguments against its definition', $result['message']);
        self::assertSame([], $this->ran);
        self::assertSame([], $this->audit(), 'a call that ran nothing leaves no record');
    }

    public function testAProblemTheSchemaCannotSeeIsAnsweredNotApprovedNorRun(): void
    {
        [$approval, $result] = $this->call($this->tool($this->capability('content.change', Risk::WRITE, ['problem' => 'The parent is in another site.'])), ['id' => 5]);

        self::assertFalse($approval);
        self::assertFalse($result['success']);
        self::assertSame('content.change: The parent is in another site.', $result['message']);
        self::assertSame([], $this->ran);
    }

    public function testANumberWrittenAsTextIsStillANumber(): void
    {
        $tool = $this->tool($this->capability('content.change', Risk::WRITE));
        $tool->setInputs(['id' => '5']);

        self::assertStringContainsString('Id: 5', (string) $tool->requiresApproval());
        $tool->execute();
        self::assertTrue(json_decode((string) $tool->getResult(), true)['success']);
    }

    public function testAMissingArgumentIsAnswered(): void
    {
        [, $result] = $this->call($this->tool($this->capability('content.look', Risk::READ)), []);

        self::assertStringContainsString('arguments.id is required', $result['message']);
    }

    public function testEitherSpellingOfACapabilityNamesIt(): void
    {
        [, $runner] = $this->os([$this->capability('content.look', Risk::READ)]);

        self::assertSame('content.look', $runner->prepare('content__look', ['id' => 1])[0]?->name());
        self::assertSame('content.look', $runner->prepare('content.look', ['id' => 1])[0]?->name());
        self::assertStringContainsString('There is no capability "nope.x"', (string) $runner->prepare('nope__x', [])[2]);
        self::assertStringContainsString('tool_search', (string) $runner->prepare('nope__x', [])[2]);
    }

    public function testWhatTheUserMayNotDoIsNeitherInThePoolNorRunNorApproved(): void
    {
        $look = $this->capability('content.look', Risk::READ, ['requires' => [['content', 'read']]]);
        $change = $this->capability('content.change', Risk::WRITE);
        [$pool] = $this->os([$look, $change], [], ['masilia_ai/write']);

        self::assertSame(['content__look'], array_map(static fn (CapabilityTool $tool): string => $tool->getName(), $pool->forCurrentUser()));

        [$approval, $result] = $this->call($this->tool($change, ['masilia_ai/write']), ['id' => 5]);
        self::assertFalse($approval);
        self::assertStringContainsString('masilia_ai/write', $result['message']);
        self::assertSame([], $this->ran);
    }

    public function testAPolicyOfTheThingTouchedIsRequiredToo(): void
    {
        [, $result] = $this->call($this->tool($this->capability('content.look', Risk::READ, ['requires' => [['content', 'read']]]), ['content/read']), ['id' => 1]);

        self::assertStringContainsString('content/read', $result['message']);
    }

    public function testADomainTurnedOffIsGoneAndAccessIsOffUntilTurnedOn(): void
    {
        $capabilities = [$this->capability('content.look', Risk::READ), $this->capability('trash.look', Risk::READ), $this->capability('access.look', Risk::READ)];
        [$pool] = $this->os($capabilities, ['trash' => false, 'access' => false]);

        self::assertSame(['content__look'], array_map(static fn (CapabilityTool $tool): string => $tool->getName(), $pool->forCurrentUser()));
    }

    public function testAFailureOfTheCapabilityIsAnsweredAndRecordedWithoutLeakingAnException(): void
    {
        [, $result] = $this->call($this->tool($this->capability('content.change', Risk::WRITE, ['throws' => new \RuntimeException('database is gone')])), ['id' => 5]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('content.change failed: database is gone', $result['message']);
        self::assertSame('0', (string) $this->audit()[0]['success']);
    }

    public function testARefusalOfTheRepositoryIsAnsweredInWords(): void
    {
        [, $result] = $this->call($this->tool($this->capability('content.change', Risk::WRITE, ['throws' => new UnauthorizedException('content', 'edit', ['contentId' => 5])])), ['id' => 5]);

        self::assertFalse($result['success']);
        self::assertStringStartsWith('You are not allowed to do this', $result['message']);
    }

    public function testToolSearchFindsACapabilityByWhatItDoes(): void
    {
        [$pool] = $this->os([$this->capability('content.look', Risk::READ), $this->capability('trash.wipe', Risk::DESTRUCTIVE), $this->capability('location.move', Risk::WRITE)]);
        $search = new ToolSearchTool($pool->forCurrentUser(), 3);

        self::assertSame('trash__wipe', $search->search('wipe')[0]->getName());
        self::assertSame('location__move', $search->search('location move')[0]->getName());
        self::assertSame([], $search->search('zzzzz'));
    }

    public function testTheAuditLogKeepsLongArgumentsCut(): void
    {
        (new AuditLog($this->connection))->record(14, 'thread-aaaa-0001', 'content.update', Risk::WRITE, ['text' => str_repeat('é', 30000)], true, str_repeat('m', 900));

        $row = (new AuditLog($this->connection))->latest(1)[0];
        self::assertLessThan(20100, mb_strlen($row['arguments']));
        self::assertSame(500, mb_strlen($row['message']));
        self::assertSame('thread-aaaa-0001', $row['thread_id']);
    }

    public function testACallPastTheBudgetIsRefusedWithAWordToTheModelNotRunNorThrown(): void
    {
        $tool = $this->tool($this->capability('content.look', Risk::READ));
        $answers = [];
        for ($call = 1; $call <= CapabilityTool::CALL_BUDGET + 2; ++$call) {
            [, $answers[$call]] = $this->call(clone $tool, ['id' => $call]);
        }

        self::assertTrue($answers[CapabilityTool::CALL_BUDGET]['success']);
        self::assertFalse($answers[CapabilityTool::CALL_BUDGET + 1]['success']);
        self::assertStringContainsString('Call budget reached', $answers[CapabilityTool::CALL_BUDGET + 1]['message']);
        self::assertCount(CapabilityTool::CALL_BUDGET, $this->ran, 'what is over the budget does not run');
        self::assertSame(CapabilityTool::CALL_BUDGET + 4, $tool->getMaxRuns(), "Neuron's own limit stays above, against a loop");
    }
}
