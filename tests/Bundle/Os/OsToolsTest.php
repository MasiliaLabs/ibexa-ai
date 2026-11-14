<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\Core\Base\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Bundle\IbexaAi\Os\Audit\AuditLog;
use Masilia\Bundle\IbexaAi\Os\Audit\RunContext;
use Masilia\Bundle\IbexaAi\Os\Execution\CapabilityRunner;
use Masilia\Bundle\IbexaAi\Os\Tool\DescribeCapabilityTool;
use Masilia\Bundle\IbexaAi\Os\Tool\FindCapabilitiesTool;
use Masilia\Bundle\IbexaAi\Os\Tool\RunCapabilityTool;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use PHPUnit\Framework\TestCase;
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
     * @return array{RunCapabilityTool, FindCapabilitiesTool, DescribeCapabilityTool}
     */
    private function tools(array $capabilities, array $domains = [], array $denied = []): array
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturnCallback(static fn (string $module, string $function): bool => !in_array($module . '/' . $function, $denied, true));
        $permissions->method('getCurrentUserReference')->willReturn(new UserReference(14));

        $registry = new CapabilityRegistry($capabilities);
        $access = new CapabilityAccess($permissions, $domains);

        return [
            new RunCapabilityTool(new CapabilityRunner($registry, $access, new AuditLog($this->connection), new RunContext(), $permissions, new NullLogger())),
            new FindCapabilitiesTool($registry, $access),
            new DescribeCapabilityTool($registry, $access),
        ];
    }

    private function audit(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM app_ai_audit ORDER BY id');
    }

    public function testAReadRunsAtOnceWithoutApprovalAndLeavesNoRecord(): void
    {
        [$run] = $this->tools([$this->capability('content.look', Risk::READ)]);

        self::assertNull($run->approvalReason(['name' => 'content.look', 'arguments' => ['id' => 5]]));
        $result = $run->execute(['name' => 'content.look', 'arguments' => ['id' => 5]]);

        self::assertTrue($result->success);
        self::assertSame(['content.look'], $this->ran);
        self::assertSame([], $this->audit());
    }

    public function testAWriteIsApprovedFirstOnACardMadeOfItsPreviewThenRecorded(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE)]);

        $reason = $run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => 5]]);
        self::assertSame("Action: Do content.change\nRisk: write\nId: 5\n\nThe change runs only if you approve it.", $reason);
        self::assertSame([], $this->ran, 'asking does not run it');

        self::assertTrue($run->execute(['name' => 'content.change', 'arguments' => ['id' => 5]])->success);
        $rows = $this->audit();
        self::assertCount(1, $rows);
        self::assertSame(['content.change', 'write', '1', 14], [$rows[0]['capability'], $rows[0]['risk'], (string) $rows[0]['success'], (int) $rows[0]['user_id']]);
        self::assertSame('{"id":5}', $rows[0]['arguments']);
    }

    public function testADestructiveCallNamesItsRiskOnTheCard(): void
    {
        [$run] = $this->tools([$this->capability('trash.wipe', Risk::DESTRUCTIVE)]);

        self::assertStringContainsString("Risk: destructive\n", (string) $run->approvalReason(['name' => 'trash.wipe', 'arguments' => ['id' => 1]]));
    }

    public function testAWriteIsNeverRunWithoutACardEvenWhenItsPreviewFails(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE, ['previewFails' => true])]);

        $reason = $run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => 5]]);

        self::assertNotNull($reason, 'the call is still approved before it runs');
        self::assertStringContainsString('Action: Summary of content.change', $reason);
        self::assertStringContainsString('Risk: write', $reason);
    }

    public function testBadArgumentsAreAnsweredNotApprovedNorRun(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE)]);

        self::assertNull($run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => 'five']]));
        $result = $run->execute(['name' => 'content.change', 'arguments' => ['id' => 'five']]);

        self::assertFalse($result->success);
        self::assertStringContainsString('arguments.id must be a whole number', $result->message);
        self::assertStringContainsString('describe_capability', $result->message);
        self::assertSame([], $this->ran);
        self::assertSame([], $this->audit(), 'a call that ran nothing leaves no record');
    }

    public function testAProblemTheSchemaCannotSeeIsAnsweredNotApprovedNorRun(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE, ['problem' => 'The parent is in another site.'])]);

        self::assertNull($run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => 5]]));
        $result = $run->execute(['name' => 'content.change', 'arguments' => ['id' => 5]]);

        self::assertFalse($result->success);
        self::assertSame('content.change: The parent is in another site.', $result->message);
        self::assertSame([], $this->ran);
    }

    public function testANumberWrittenAsTextIsStillANumber(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE)]);

        self::assertStringContainsString('Id: 5', (string) $run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => '5']]));
        self::assertTrue($run->execute(['name' => 'content.change', 'arguments' => ['id' => '5']])->success);
    }

    public function testAnUnknownCapabilityAndAMissingOneAreAnswered(): void
    {
        [$run] = $this->tools([$this->capability('content.look', Risk::READ)]);

        self::assertStringContainsString('There is no capability "nope.x"', $run->execute(['name' => 'nope.x'])->message);
        self::assertStringContainsString('arguments.id is required', $run->execute(['name' => 'content.look'])->message);
    }

    public function testWhatTheUserMayNotDoIsNeitherListedNorRunNorApproved(): void
    {
        $capabilities = [$this->capability('content.look', Risk::READ, ['requires' => [['content', 'read']]]), $this->capability('content.change', Risk::WRITE)];
        [$run, $find, $describe] = $this->tools($capabilities, [], ['masilia_ai/write']);

        $names = array_column($find->execute([])->data['capabilities'], 'name');
        self::assertSame(['content.look'], $names);
        self::assertNull($run->approvalReason(['name' => 'content.change', 'arguments' => ['id' => 5]]));
        $result = $run->execute(['name' => 'content.change', 'arguments' => ['id' => 5]]);
        self::assertStringContainsString('masilia_ai/write', $result->message);
        self::assertSame([], $this->ran);
        self::assertStringContainsString('not available', $describe->execute(['name' => 'content.change'])->message);
    }

    public function testAPolicyOfTheThingTouchedIsRequiredToo(): void
    {
        [$run] = $this->tools([$this->capability('content.look', Risk::READ, ['requires' => [['content', 'read']]])], [], ['content/read']);

        self::assertStringContainsString('content/read', $run->execute(['name' => 'content.look', 'arguments' => ['id' => 1]])->message);
    }

    public function testADomainTurnedOffIsGoneAndAccessIsOffUntilTurnedOn(): void
    {
        $capabilities = [$this->capability('content.look', Risk::READ), $this->capability('trash.look', Risk::READ), $this->capability('access.look', Risk::READ)];
        [, $find] = $this->tools($capabilities, ['trash' => false, 'access' => false]);

        self::assertSame(['content.look'], array_column($find->execute([])->data['capabilities'], 'name'));
        self::assertSame(['content' => 1], $find->execute([])->data['domains']);
    }

    public function testAFailureOfTheCapabilityIsAnsweredAndRecordedWithoutLeakingAnException(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE, ['throws' => new \RuntimeException('database is gone')])]);

        $result = $run->execute(['name' => 'content.change', 'arguments' => ['id' => 5]]);

        self::assertFalse($result->success);
        self::assertStringContainsString('content.change failed: database is gone', $result->message);
        self::assertSame('0', (string) $this->audit()[0]['success']);
    }

    public function testARefusalOfTheRepositoryIsAnsweredInWords(): void
    {
        [$run] = $this->tools([$this->capability('content.change', Risk::WRITE, ['throws' => new UnauthorizedException('content', 'edit', ['contentId' => 5])])]);

        $result = $run->execute(['name' => 'content.change', 'arguments' => ['id' => 5]]);

        self::assertFalse($result->success);
        self::assertStringStartsWith('You are not allowed to do this', $result->message);
    }

    public function testFindingByWordsAndDescribingGiveWhatTheModelNeeds(): void
    {
        [, $find, $describe] = $this->tools([$this->capability('content.look', Risk::READ), $this->capability('trash.wipe', Risk::DESTRUCTIVE)]);

        self::assertSame('trash.wipe', $find->execute(['query' => 'wipe'])->data['capabilities'][0]['name']);
        self::assertSame([], $find->execute(['query' => 'zzzzz'])->data['capabilities']);

        $described = $describe->execute(['name' => 'trash.wipe']);
        self::assertTrue($described->success);
        self::assertTrue($described->data['user_approves_first']);
        self::assertSame('integer', $described->data['input_schema']['properties']['id']['type']);
        self::assertSame(1, $described->data['examples'][0]['arguments']['id']);
        self::assertStringContainsString('Did you mean: trash.wipe', $describe->execute(['name' => 'trash.wip'])->message);
    }

    public function testTheAuditLogKeepsLongArgumentsCut(): void
    {
        (new AuditLog($this->connection))->record(14, 'thread-aaaa-0001', 'content.update', Risk::WRITE, ['text' => str_repeat('é', 30000)], true, str_repeat('m', 900));

        $row = (new AuditLog($this->connection))->latest(1)[0];
        self::assertLessThan(20100, mb_strlen($row['arguments']));
        self::assertSame(500, mb_strlen($row['message']));
        self::assertSame('thread-aaaa-0001', $row['thread_id']);
    }
}
