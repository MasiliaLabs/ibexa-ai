<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Lib\Os;

use FilesystemIterator;
use Masilia\Contracts\IbexaAi\Os\Capability;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/** Every capability the package ships, found by scanning its folder: one added tomorrow is in without a new test. */
trait EveryCapability
{
    /**
     * @return iterable<string, array{class-string<Capability>}>
     */
    public static function capabilities(): iterable
    {
        $base = (string) realpath(__DIR__ . '/../../../src/lib/Os/Capability');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'Masilia\\IbexaAi\\Os\\Capability\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($base) + 1, -4));
            $reflection = new ReflectionClass($class);
            if (!$reflection->isAbstract() && $reflection->implementsInterface(Capability::class)) {
                yield $class => [$class];
            }
        }
    }

    /** The capability, built on test doubles of what it takes. */
    private function make(string $class): Capability
    {
        $arguments = [];
        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = (string) $parameter->getType();
            $arguments[] = $type === 'array' ? [] : $this->createMock($type);
        }

        return new $class(...$arguments);
    }
}
