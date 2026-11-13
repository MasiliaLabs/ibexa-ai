<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Doctrine\DBAL\DriverManager;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Bundle\IbexaAi\Os\Audit\AuditLog;
use Masilia\Bundle\IbexaAi\Os\Audit\RunContext;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Bundle\IbexaAi\Os\Tool\ProposeChangesTool;
use Masilia\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\IbexaAi\Os\CapabilityResult;
use Masilia\IbexaAi\Os\Preview;
use Masilia\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProposeChangesToolTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];
    /** @var array<string, int> */
    private array $transaction = ['begin' => 0, 'commit' => 0, 'rollback' => 0];
    private \Doctrine\DBAL\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE app_ai_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, thread_id VARCHAR(64) NULL, capability VARCHAR(80) NOT NULL, risk VARCHAR(12) NOT NULL,
            arguments TEXT NOT NULL, success TINYINT NOT NULL, message VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL)');
        $this->log = [];
        $this->transaction = ['begin' => 0, 'commit' => 0, 'rollback' => 0];
    }

    /**
     * @param array<string, mixed> $behaviour answers (data), fails
     */
    private function capability(string $name, Risk $risk, array $behaviour = []): Capability
    {
        $log = &$this->log;

        return new class($name, $risk, $behaviour, $log) implements Capability {
            public function __construct(private string $name, private Risk $risk, private array $behaviour, private array &$log) {}

            public function name(): string { return $this->name; }
            public function summary(): string { return 'Summary of ' . $this->name; }
            public function description(): string { return ''; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => ['parent' => ['type' => 'integer', 'minimum' => 1], 'title' => ['type' => 'string']]]; }
            public function risk(): Risk { return $this->risk; }
            public function requires(): array { return []; }
            public function examples(): array { return []; }
            public function validate(array $arguments): ?string { return $this->behaviour['problem'] ?? null; }

            public function preview(array $arguments): Preview
            {
                return new Preview('Do ' . $this->name, [['Title', (string) ($arguments['title'] ?? '')]], $this->risk === Risk::DESTRUCTIVE ? ['It cannot be undone.'] : []);
            }

            public function execute(array $arguments): CapabilityResult
            {
                $this->log[] = $this->name . ':' . json_encode($arguments);

                return ($this->behaviour['fails'] ?? false) ? CapabilityResult::error('It did not work') : CapabilityResult::ok('Done ' . $this->name, $this->behaviour['data'] ?? []);
            }
        };
    }

    /**
     * @param list<Capability> $capabilities
     */
    private function tool(array $capabilities): ProposeChangesTool
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturn(true);
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));
        $repository = $this->createMock(Repository::class);
        $repository->method('beginTransaction')->willReturnCallback(function (): void { ++$this->transaction['begin']; });
        $repository->method('commit')->willReturnCallback(function (): void { ++$this->transaction['commit']; });
        $repository->method('rollback')->willReturnCallback(function (): void { ++$this->transaction['rollback']; });

        $registry = new CapabilityRegistry($capabilities);

        return new ProposeChangesTool(new CapabilityRunner($registry, new CapabilityAccess($permissions, []), new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger()), $repository);
    }

    private function audit(): array
    {
        return $this->connection->fetchAllAssociative('SELECT capability, risk, success, message FROM app_ai_audit ORDER BY id');
    }

    public function testSeveralChangesAreApprovedOnceOnOneCardWithTheHighestRisk(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE), $this->capability('trash.wipe', Risk::DESTRUCTIVE)]);

        $reason = $tool->approvalReason(['steps' => [['capability' => 'folder.create', 'arguments' => ['title' => 'A']], ['capability' => 'trash.wipe']]]);

        self::assertSame(
            "Action: Apply 2 changes\nRisk: destructive\nStep 1: Do folder.create: Title A\nStep 2: Do trash.wipe: Title\nNote: It cannot be undone.\nNote: The steps are applied together: if one fails, none of the changes is kept.\n\nThe change runs only if you approve it.",
            $reason,
        );
        self::assertSame([], $this->log, 'asking runs nothing');
    }

    public function testABatchThatOnlyReadsIsNotApproved(): void
    {
        $tool = $this->tool([$this->capability('content.look', Risk::READ)]);

        self::assertNull($tool->approvalReason(['steps' => [['capability' => 'content.look']]]));
        self::assertTrue($tool->execute(['steps' => [['capability' => 'content.look']]])->success);
    }

    public function testAStepUsesWhatAnEarlierStepAnsweredAndAllAreCommittedTogether(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE, ['data' => ['location_id' => 77]]), $this->capability('page.create', Risk::WRITE)]);
        $steps = ['steps' => [
            ['id' => 'folder', 'capability' => 'folder.create', 'arguments' => ['title' => 'News']],
            ['capability' => 'page.create', 'arguments' => ['parent' => '$folder.location_id', 'title' => 'Hello']],
        ]];

        $card = (string) $tool->approvalReason($steps);
        self::assertStringContainsString('Step 2: page.create: parent: result of folder.location_id, title: Hello', $card);

        $result = $tool->execute($steps);

        self::assertTrue($result->success);
        self::assertSame(['folder.create:{"title":"News"}', 'page.create:{"parent":77,"title":"Hello"}'], $this->log);
        self::assertSame(['begin' => 1, 'commit' => 1, 'rollback' => 0], $this->transaction);
        self::assertSame(['folder', 's2'], array_column($result->data['steps'], 'id'));
        self::assertSame(['success' => '1', 'capability' => 'folder.create'], ['success' => (string) $this->audit()[0]['success'], 'capability' => $this->audit()[0]['capability']]);
    }

    public function testWhenAStepFailsNoneIsKeptAndEverythingIsRecorded(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE), $this->capability('page.create', Risk::WRITE, ['fails' => true]), $this->capability('page.publish', Risk::WRITE)]);

        $result = $tool->execute(['steps' => [['capability' => 'folder.create'], ['id' => 'page', 'capability' => 'page.create'], ['capability' => 'page.publish']]]);

        self::assertFalse($result->success);
        self::assertStringContainsString('Step "page" (page.create) failed: It did not work. None of the changes was kept.', $result->message);
        self::assertSame(['begin' => 1, 'commit' => 0, 'rollback' => 1], $this->transaction);
        self::assertCount(2, $this->log, 'the steps after the failure did not run');

        $rows = $this->audit();
        self::assertSame(['folder.create', '0'], [$rows[0]['capability'], (string) $rows[0]['success']]);
        self::assertStringContainsString('Rolled back because step "page" failed', $rows[0]['message']);
        self::assertSame(['page.create', '0', 'It did not work'], [$rows[1]['capability'], (string) $rows[1]['success'], $rows[1]['message']]);
    }

    public function testAReferenceToAnythingButAnEarlierStepIsRefusedBeforeAnythingRuns(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE), $this->capability('page.create', Risk::WRITE)]);

        foreach ([
            [['capability' => 'page.create', 'arguments' => ['parent' => '$folder.location_id']], ['id' => 'folder', 'capability' => 'folder.create']],
            [['capability' => 'page.create', 'arguments' => ['parent' => '$nope.location_id']]],
        ] as $steps) {
            self::assertNull($tool->approvalReason(['steps' => $steps]));
            self::assertStringContainsString('not an earlier step', $tool->execute(['steps' => $steps])->message);
        }
        self::assertSame([], $this->log);
        self::assertSame(['begin' => 0, 'commit' => 0, 'rollback' => 0], $this->transaction);
    }

    public function testAReferenceToAFieldTheStepDidNotAnswerFailsAndRollsBack(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE, ['data' => ['content_id' => 5]]), $this->capability('page.create', Risk::WRITE)]);

        $result = $tool->execute(['steps' => [['id' => 'folder', 'capability' => 'folder.create'], ['capability' => 'page.create', 'arguments' => ['parent' => '$folder.location_id']]]]);

        self::assertFalse($result->success);
        self::assertStringContainsString('did not answer a field "location_id" (it answered: content_id)', $result->message);
        self::assertSame(1, $this->transaction['rollback']);
        self::assertSame(['folder.create:[]'], $this->log);
    }

    public function testAStepWithAProblemIsRefusedUpFrontWhenNothingBeforeItChangesAnything(): void
    {
        $tool = $this->tool([$this->capability('content.look', Risk::READ), $this->capability('page.create', Risk::WRITE, ['problem' => 'The type does not exist.'])]);

        $upFront = $tool->execute(['steps' => [['capability' => 'content.look'], ['capability' => 'page.create']]]);

        self::assertStringContainsString('Step "s2": page.create: The type does not exist.', $upFront->message);
        self::assertSame([], $this->log);
        self::assertSame(['begin' => 0, 'commit' => 0, 'rollback' => 0], $this->transaction);
    }

    public function testAProblemFoundWhenAStepRunsUndoesEarlierSteps(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE, ['data' => ['location_id' => 7]]), $this->capability('page.create', Risk::WRITE, ['problem' => 'The type does not exist.'])]);

        // With a reference, or after a step that changes something, the arguments are checked when the step runs.
        $late = $tool->execute(['steps' => [['id' => 'f', 'capability' => 'folder.create'], ['capability' => 'page.create', 'arguments' => ['parent' => '$f.location_id']]]]);

        self::assertFalse($late->success);
        self::assertStringContainsString('The type does not exist.', $late->message);
        self::assertSame(1, $this->transaction['rollback']);
    }

    public function testWhatAStepNeedsFromTheRepositoryIsCheckedWhenItRunsIfAnEarlierStepChangesSomething(): void
    {
        // The second step needs what the first creates (a group by its name): it cannot be checked up front.
        $tool = $this->tool([$this->capability('group.create', Risk::WRITE), $this->capability('type.create', Risk::WRITE, ['problem' => 'There is no group.'])]);
        $steps = ['steps' => [['capability' => 'group.create'], ['capability' => 'type.create']]];

        self::assertNotNull($tool->approvalReason($steps), 'the batch is shown for approval');
        $result = $tool->execute($steps);

        self::assertFalse($result->success, 'and it is checked, and undone, when the step runs');
        self::assertStringContainsString('There is no group.', $result->message);
        self::assertSame(1, $this->transaction['rollback']);

        // After reads only, the state is the one that is checked up front.
        $reads = $this->tool([$this->capability('a.read', Risk::READ), $this->capability('type.create', Risk::WRITE, ['problem' => 'There is no group.'])]);
        self::assertNull($reads->approvalReason(['steps' => [['capability' => 'a.read'], ['capability' => 'type.create']]]));
    }

    public function testBadBatchesAreAnsweredNotRun(): void
    {
        $tool = $this->tool([$this->capability('folder.create', Risk::WRITE)]);

        $cases = [
            [[], 'at least one step'],
            [[['capability' => 'folder.create', 'arguments' => ['parent' => 'x']]], 'Step "s1": folder.create: arguments.parent must be a whole number'],
            [[['capability' => 'nope.x']], 'There is no capability "nope.x"'],
            [[['id' => 'a', 'capability' => 'folder.create'], ['id' => 'a', 'capability' => 'folder.create']], 'already used'],
            [[['id' => 'Bad Id', 'capability' => 'folder.create']], 'must be lower case'],
            [array_fill(0, 16, ['capability' => 'folder.create']), 'at most 15'],
            [[['arguments' => []]], 'needs a "capability"'],
        ];
        foreach ($cases as [$steps, $expected]) {
            $result = $tool->execute(['steps' => $steps]);
            self::assertFalse($result->success);
            self::assertStringContainsString($expected, $result->message);
        }
        self::assertSame([], $this->log);
    }
}
