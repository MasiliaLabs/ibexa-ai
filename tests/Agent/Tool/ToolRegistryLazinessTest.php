<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Agent\Tool;

use Masilia\Bundle\IbexaAi\Agent\Tool\ToolInterface;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolRegistry;
use Masilia\Bundle\IbexaAi\Agent\Tool\ToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Building the registry must not instantiate or query its tools: some tools depend on the registry
 * themselves, and eager indexing made that a construction cycle.
 */
#[CoversClass(ToolRegistry::class)]
final class ToolRegistryLazinessTest extends TestCase
{
    public function testTheToolsAreNotTouchedUntilTheRegistryIsUsed(): void
    {
        $tool = new class implements ToolInterface {
            public int $nameReads = 0;

            public function getName(): string
            {
                $this->nameReads++;

                return 'lazy_tool';
            }

            public function getDescription(): string
            {
                return 'lazy';
            }

            public function getParameters(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function execute(array $params): ToolResult
            {
                return ToolResult::ok('done');
            }
        };

        $registry = new ToolRegistry([$tool]);
        self::assertSame(0, $tool->nameReads, 'construction must not index the tools');

        self::assertNotNull($registry->get('lazy_tool'));
        self::assertGreaterThan(0, $tool->nameReads);
    }

    public function testRegisterReturnsANewRegistryAndLeavesTheOriginalUnchanged(): void
    {
        $original = new ToolRegistry([]);

        $extended = $original->register($this->named('extra'));

        self::assertNull($original->get('extra'));
        self::assertNotNull($extended->get('extra'));
    }

    private function named(string $name): ToolInterface
    {
        return new class ($name) implements ToolInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getDescription(): string
            {
                return '';
            }

            public function getParameters(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function execute(array $params): ToolResult
            {
                return ToolResult::ok();
            }
        };
    }
}
