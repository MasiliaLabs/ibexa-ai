<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The public API of the package (Masilia\Contracts\IbexaAi) is what other packages build on: it stands on its own, and the
 * names it had before keep working, saying they are deprecated, until 1.0.
 */
final class ContractsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../src/contracts';

    /** Names a type of the contracts may use besides its own namespace and PHP's: Ibexa's contracts, Neuron (the OS is built on it) and Symfony's HttpFoundation. */
    private const ALLOWED = ['Masilia\\Contracts\\IbexaAi\\', 'Ibexa\\Contracts\\', 'NeuronAI\\', 'Symfony\\Component\\HttpFoundation\\'];

    public function testTheContractsNameNothingButThemselvesIbexaContractsAndPhp(): void
    {
        $wrong = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT, FilesystemIterator::SKIP_DOTS)) as $file) {
            $code = (string) file_get_contents($file->getPathname());
            preg_match_all('/(?:^use\s+|\\\\?)((?:[A-Z][A-Za-z0-9]*\\\\)+[A-Z][A-Za-z0-9]*)/m', $code, $names);
            foreach (array_unique($names[1]) as $name) {
                if (str_starts_with($code, '<?php') && !$this->allowed($name)) {
                    $wrong[] = sprintf('%s names %s', substr($file->getPathname(), strlen(self::ROOT) + 1), $name);
                }
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    private function allowed(string $name): bool
    {
        foreach (self::ALLOWED as $prefix) {
            if (str_starts_with(ltrim($name, '\\'), $prefix)) {
                return true;
            }
        }

        // A namespace declaration of the contracts themselves.
        return str_starts_with($name, 'Masilia\\Contracts\\');
    }

    public function testTheContractsAreInterfacesEnumsExceptionsAbstractBasesAndValueObjectsOnly(): void
    {
        $impure = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'Masilia\\Contracts\\IbexaAi\\' . str_replace('/', '\\', substr((string) $file->getRealPath(), strlen((string) realpath(self::ROOT)) + 1, -4));
            $reflection = new \ReflectionClass($class);
            $allowed = $reflection->isInterface() || $reflection->isEnum() || $reflection->isAbstract() || $reflection->isSubclassOf(\Throwable::class) || $reflection->isReadOnly();
            if (!$allowed) {
                $impure[] = $class . ' is a class with behaviour: the implementation belongs in src/lib';
            }
        }

        self::assertSame([], $impure, implode("\n", $impure));
    }
}
