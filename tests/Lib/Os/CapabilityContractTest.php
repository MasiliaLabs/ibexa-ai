<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\IbexaAi\Os\CapabilityRegistry;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\IbexaAi\Os\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The rules every capability follows, checked for each one the package ships: it is found by scanning the folder, so a
 * capability added tomorrow is held to them without a new test.
 */
final class CapabilityContractTest extends TestCase
{
    use EveryCapability;

    #[DataProvider('capabilities')]
    public function testItIsNamedAndDescribedForTheModel(string $class): void
    {
        $capability = $this->make($class);

        self::assertSame(1, preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $capability->name()), 'a dotted, lower case name');
        self::assertNotSame('', trim($capability->summary()));
        self::assertLessThanOrEqual(120, mb_strlen($capability->summary()), 'a summary is one line');
        self::assertNotSame('', trim($capability->description()));
        new CapabilityRegistry([$capability]);
    }

    #[DataProvider('capabilities')]
    public function testItsSchemaIsAnObjectAndItsExamplesFollowIt(string $class): void
    {
        $capability = $this->make($class);
        $schema = $capability->inputSchema();

        self::assertSame('object', $schema['type'] ?? null);
        foreach ($capability->examples() as $example) {
            self::assertSame([], SchemaValidator::validate($schema, $example['arguments']), sprintf('example "%s" of %s', $example['description'], $capability->name()));
        }
        foreach ((array) ($schema['required'] ?? []) as $required) {
            self::assertArrayHasKey($required, (array) ($schema['properties'] ?? []), sprintf('%s requires "%s" but does not describe it', $capability->name(), $required));
        }
    }

    #[DataProvider('capabilities')]
    public function testWhatChangesSomethingNamesItsPoliciesAndPreviewsItself(string $class): void
    {
        $capability = $this->make($class);
        if ($capability->risk() === Risk::READ) {
            self::assertSame([], array_filter($capability->requires(), static fn (array $policy): bool => $policy[0] === 'masilia_ai'), 'the permission of the risk is added by the access, not declared');

            return;
        }

        self::assertNotSame([], $capability->requires(), sprintf('%s changes something: it names the Ibexa policies it needs', $capability->name()));
        // The default preview only repeats the summary: a change shows what it will touch.
        $arguments = $capability->examples()[0]['arguments'] ?? [];
        $preview = $capability->preview($arguments);
        self::assertNotSame([], $preview->rows, sprintf('the preview of %s shows what it will touch', $capability->name()));
    }

    #[DataProvider('capabilities')]
    public function testADestructiveCapabilityWarnsInItsPreview(string $class): void
    {
        $capability = $this->make($class);
        if ($capability->risk() !== Risk::DESTRUCTIVE && $capability->risk() !== Risk::ACCESS) {
            self::assertTrue(true);

            return;
        }

        self::assertNotSame([], $capability->preview($capability->examples()[0]['arguments'] ?? [])->notes, sprintf('%s warns before it runs', $capability->name()));
    }
}
