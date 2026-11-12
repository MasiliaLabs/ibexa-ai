<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Field;

use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierInterface;
use Masilia\Bundle\IbexaAi\Field\FieldValueStringifierRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A stringifier whose class cannot be loaded is silently dropped from the container, and the registry then
 * returns an empty string for its field types. This guards every stringifier on disk.
 */
final class StringifiersTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../../../src/bundle/Field/Stringifier';

    /**
     * @return iterable<string, array{string}>
     */
    public static function stringifierClasses(): iterable
    {
        foreach (glob(self::DIRECTORY . '/*Stringifier.php') ?: [] as $file) {
            $name = basename($file, '.php');

            yield $name => ['Masilia\\Bundle\\IbexaAi\\Field\\Stringifier\\' . $name];
        }
    }

    #[DataProvider('stringifierClasses')]
    public function testEveryStringifierLoadsAndImplementsTheInterface(string $class): void
    {
        self::assertTrue(class_exists($class), sprintf('%s cannot be loaded.', $class));
        self::assertTrue(is_subclass_of($class, FieldValueStringifierInterface::class), sprintf('%s must implement the interface.', $class));
    }

    public function testThereIsAStringifierPerFileAndAFallback(): void
    {
        $classes = array_map(static fn (array $row): string => $row[0], iterator_to_array(self::stringifierClasses(), false));

        self::assertNotEmpty($classes);

        $supported = [];
        foreach ($classes as $class) {
            foreach ($class::getSupportedFieldTypes() as $type) {
                $supported[] = $type;
            }
        }
        self::assertContains(FieldValueStringifierRegistry::FALLBACK_TYPE, $supported, 'Without a fallback the registry returns "" for every field type that has no dedicated stringifier.');
    }
}
