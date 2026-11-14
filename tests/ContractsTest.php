<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests;

use FilesystemIterator;
use Masilia\Contracts\IbexaAi\Image\ImageGenerator;
use Masilia\Contracts\IbexaAi\Os\Capability;
use Masilia\Contracts\IbexaAi\Os\Risk;
use Masilia\Contracts\IbexaAi\Os\Selftest\SelftestScenario;
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

    /** Names a type of the contracts may use besides its own namespace and PHP's. */
    private const ALLOWED = ['Masilia\\Contracts\\IbexaAi\\', 'Ibexa\\Contracts\\'];

    /** The names of 0.5 and what they are now. */
    private const OLD_NAMES = [
        'Masilia\\IbexaAi\\Os\\Capability' => Capability::class,
        'Masilia\\IbexaAi\\Os\\CapabilityResult' => \Masilia\Contracts\IbexaAi\Os\CapabilityResult::class,
        'Masilia\\IbexaAi\\Os\\Preview' => \Masilia\Contracts\IbexaAi\Os\Preview::class,
        'Masilia\\IbexaAi\\Os\\Risk' => Risk::class,
        'Masilia\\IbexaAi\\Os\\Skill' => \Masilia\Contracts\IbexaAi\Os\Skill::class,
        'Masilia\\Bundle\\IbexaAi\\Os\\Selftest\\SelftestScenario' => SelftestScenario::class,
        'Masilia\\Bundle\\IbexaAi\\Os\\Selftest\\SelftestContext' => \Masilia\Contracts\IbexaAi\Os\Selftest\SelftestContext::class,
        'Masilia\\Bundle\\IbexaAi\\Support\\ImageGeneratorInterface' => ImageGenerator::class,
        'Masilia\\Bundle\\IbexaAi\\Support\\ImageGenerationResult' => \Masilia\Contracts\IbexaAi\Image\ImageGenerationResult::class,
    ];

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

    public function testTheOldNamesAreTheNewTypesAndSaySo(): void
    {
        $said = [];
        set_error_handler(static function (int $level, string $message) use (&$said): bool {
            $said[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            foreach (self::OLD_NAMES as $old => $new) {
                self::assertTrue(class_exists($old) || interface_exists($old) || enum_exists($old), $old);
                self::assertSame($new, (new \ReflectionClass($old))->getName(), $old . ' is ' . $new);
            }
        } finally {
            restore_error_handler();
        }

        self::assertCount(count(self::OLD_NAMES), $said, 'each old name says once that it is deprecated');
        self::assertStringContainsString('use Masilia\\Contracts\\IbexaAi\\Os\\Capability instead', implode("\n", $said));
        self::assertSame(Risk::WRITE, \Masilia\IbexaAi\Os\Risk::WRITE, 'the cases of the enum are the same');
    }
}
