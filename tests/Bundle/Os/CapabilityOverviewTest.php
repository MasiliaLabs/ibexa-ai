<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Masilia\Bundle\IbexaAi\Os\CapabilityOverview;
use Masilia\Bundle\IbexaAi\Os\Access\CapabilityAccess;
use Masilia\Bundle\IbexaAi\Os\Capability\Content\LoadCapability;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\Contracts\IbexaAi\Os\CapabilityResult;
use Masilia\Contracts\IbexaAi\Os\Preview;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaAi\Os\Skill;
use PHPUnit\Framework\TestCase;

final class CapabilityOverviewTest extends TestCase
{
    private function capability(string $name, Risk $risk, array $requires = [['content', 'read']]): Capability
    {
        return new class($name, $risk, $requires) implements Capability {
            public function __construct(private string $name, private Risk $risk, private array $requires) {}

            public function name(): string { return $this->name; }
            public function summary(): string { return 'Summary of ' . $this->name; }
            public function description(): string { return ''; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function risk(): Risk { return $this->risk; }
            public function requires(): array { return $this->requires; }
            public function examples(): array { return []; }
            public function validate(array $arguments): ?string { return null; }
            public function preview(array $arguments): Preview { return new Preview('x'); }
            public function execute(array $arguments): CapabilityResult { return CapabilityResult::ok('ok'); }
        };
    }

    /**
     * @param list<string> $denied "module/function" the current user does not have
     */
    private function overview(array $capabilities, array $domains = [], array $denied = [], iterable $skills = []): CapabilityOverview
    {
        $permissions = $this->createMock(PermissionResolver::class);
        $permissions->method('hasAccess')->willReturnCallback(static fn (string $module, string $function): bool => !in_array($module . '/' . $function, $denied, true));

        return new CapabilityOverview(new CapabilityRegistry($capabilities), new CapabilityAccess($permissions, $domains), $skills);
    }

    public function testCapabilitiesAreGroupedByDomainWithTheirRisksAndWhatTheUserMayUse(): void
    {
        $overview = $this->overview(
            [$this->capability('trash.empty', Risk::DESTRUCTIVE, [['content', 'cleantrash']]), $this->capability('content.load', Risk::READ), $this->capability('trash.list', Risk::READ)],
            [],
            ['content/cleantrash'],
        )->build();

        self::assertSame(['content', 'trash'], array_column($overview['domains'], 'name'));
        $trash = $overview['domains'][1];
        self::assertSame(['read' => 1, 'destructive' => 1], $trash['risks']);
        self::assertSame(1, $trash['usable']);
        self::assertSame(['trash.empty', 'trash.list'], array_column($trash['capabilities'], 'name'), 'sorted by name');

        $empty = $trash['capabilities'][0];
        self::assertFalse($empty['usable']);
        self::assertSame('You do not have the permission content/cleantrash.', $empty['denial']);
        self::assertSame('content/cleantrash, masilia_ai/destructive', $empty['requires']);
        self::assertSame(['capabilities' => 3, 'domains' => 2, 'enabled_domains' => 2, 'usable' => 2], $overview['totals']);
    }

    public function testADomainThatIsTurnedOffIsShownOffAndNothingInItIsUsable(): void
    {
        $overview = $this->overview([$this->capability('access.role.list', Risk::READ), $this->capability('content.load', Risk::READ)], ['access' => false])->build();

        [$access, $content] = $overview['domains'];
        self::assertFalse($access['enabled']);
        self::assertSame(0, $access['usable']);
        self::assertSame('The "access" capabilities are turned off.', $access['capabilities'][0]['denial']);
        self::assertTrue($content['enabled']);
        self::assertSame(1, $overview['totals']['enabled_domains']);
    }

    public function testThePackageIsTheOneOfTheClassAndSkillsAreListed(): void
    {
        $skill = new class() implements Skill {
            public function name(): string { return 'How we build'; }
            public function instructions(): string { return 'x'; }
        };

        self::assertSame('AI OS', CapabilityOverview::packageOf((new \ReflectionClass(LoadCapability::class))->newInstanceWithoutConstructor()));
        // An anonymous class is named after its interface: what matters is that it is not "AI OS", the bundle's own.
        self::assertSame('Masilia\Contracts\IbexaAi', CapabilityOverview::packageOf($this->capability('a.b', Risk::READ)));

        $overview = $this->overview([$this->capability('a.b', Risk::READ)], [], [], [$skill])->build();
        self::assertSame([['name' => 'How we build', 'package' => 'Masilia\Contracts\IbexaAi']], $overview['skills']);
    }
}
