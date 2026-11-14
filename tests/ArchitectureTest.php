<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The layout of the Ibexa packages: the bundle is the integration with Symfony, the lib is the implementation, the contracts are
 * the public API. Each holds what it should and knows only what it may.
 */
final class ArchitectureTest extends TestCase
{
    private const SRC = __DIR__ . '/../src';

    /** What a bundle may hold: the integration, nothing else. */
    private const BUNDLE_MAY_HOLD = ['Command', 'Controller', 'DependencyInjection', 'EventListener', 'Migrations', 'Resources', 'Security', 'MasiliaIbexaAiBundle.php'];

    /** What the lib may not name: the bundle, and the parts of Symfony that wire or serve an application. */
    private const LIB_MAY_NOT_USE = [
        'Masilia\\Bundle\\',
        'Symfony\\Component\\HttpKernel\\',
        'Symfony\\Bundle\\',
        'Symfony\\Component\\Config\\',
        'Symfony\\Component\\DependencyInjection\\ContainerBuilder',
        'Symfony\\Component\\DependencyInjection\\Extension\\',
        'Symfony\\Component\\DependencyInjection\\Compiler\\',
        'Symfony\\Component\\DependencyInjection\\Loader\\',
        'Symfony\\Component\\HttpFoundation\\',
    ];

    /** @return list<string> */
    private function files(string $directory): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function testTheBundleHoldsTheSymfonyIntegrationOnly(): void
    {
        $found = array_values(array_diff(scandir(self::SRC . '/bundle') ?: [], ['.', '..']));

        self::assertSame([], array_values(array_diff($found, self::BUNDLE_MAY_HOLD)), 'The bundle holds what is not integration: it belongs in src/lib.');
    }

    public function testNoCapabilityNorServiceOfTheImplementationIsInTheBundle(): void
    {
        $misplaced = [];
        foreach ($this->files('bundle') as $file) {
            $code = (string) file_get_contents($file);
            if (preg_match('/implements\s+[^{]*\b(Capability|Skill|SelftestScenario|FieldInput)\b/', $code) === 1 || str_contains($file, '/Capability/')) {
                $misplaced[] = substr($file, strlen(self::SRC) + 1);
            }
        }

        self::assertSame([], $misplaced, 'These are implementation: they belong in src/lib.');
    }

    public function testTheLibKnowsNeitherTheBundleNorTheWiringOfSymfony(): void
    {
        $wrong = [];
        foreach ($this->files('lib') as $file) {
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file));
            preg_match_all('/^use ([A-Za-z0-9_\\\\]+)/m', $code, $uses);
            foreach ($uses[1] as $name) {
                foreach (self::LIB_MAY_NOT_USE as $forbidden) {
                    if (str_starts_with($name, $forbidden)) {
                        $wrong[] = sprintf('%s uses %s', substr($file, strlen(self::SRC) + 1), $name);
                    }
                }
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    /**
     * The OS is generic: it knows Ibexa, not the project it runs in. How a project builds its sites (its siteaccesses, content types,
     * locations) lives in the skills of the packages built on it (ibexa-ai-craft), never here.
     */
    public function testTheOsNamesNothingOfAnyProject(): void
    {
        $project = '/fossil_exit|fossil exit|africa_integrates|africa_v2x|mattcch|fuelexit|\bimal\b|\b1018\b/i';
        $found = [];
        foreach (['bundle', 'lib', 'contracts'] as $part) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SRC . '/' . $part, FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || str_contains($file->getPathname(), '/node_modules/') || !in_array($file->getExtension(), ['php', 'ts', 'tsx', 'twig', 'yaml', 'xlf'], true)) {
                    continue;
                }
                if (preg_match($project, (string) file_get_contents($file->getPathname()), $match) === 1) {
                    $found[] = substr($file->getPathname(), strlen(self::SRC) + 1) . ' (' . $match[0] . ')';
                }
            }
        }

        self::assertSame([], $found, "These files name a project:\n" . implode("\n", $found));
    }
}
