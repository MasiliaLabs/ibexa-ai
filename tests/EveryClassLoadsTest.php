<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * A class that cannot be loaded (a missing `use`, a wrong namespace) is not an error at build time: Symfony
 * silently leaves its service out of the container, and the feature that needs it quietly does nothing.
 * This loads every class of the package.
 */
final class EveryClassLoadsTest extends TestCase
{
    private const NAMESPACES = [
        'lib' => 'Masilia\\IbexaAi\\',
        'contracts' => 'Masilia\\Contracts\\IbexaAi\\',
        'bundle' => 'Masilia\\Bundle\\IbexaAi\\',
    ];

    public function testEveryClassInterfaceTraitAndEnumLoads(): void
    {
        $root = dirname(__DIR__) . '/src';
        $loaded = 0;
        $failures = [];

        foreach (self::NAMESPACES as $directory => $namespace) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root . '/' . $directory . '/'), -4);
                $class = $namespace . str_replace('/', '\\', $relative);
                if (str_contains($class, '\\Resources\\') || str_contains($class, '\\Migrations\\')) {
                    continue;
                }
                // An old name of a type of the public API: checked by ContractsTest, it says it is deprecated when loaded.
                if (str_contains((string) file_get_contents($file->getPathname()), 'class_alias(')) {
                    continue;
                }

                try {
                    if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                        ++$loaded;
                    } else {
                        $failures[] = sprintf('%s: not found (does the namespace match the file?)', $class);
                    }
                } catch (Throwable $e) {
                    $failures[] = sprintf('%s: %s', $class, $e->getMessage());
                }
            }
        }

        self::assertSame([], $failures, implode("\n", $failures));
        self::assertGreaterThan(100, $loaded);
    }
}
