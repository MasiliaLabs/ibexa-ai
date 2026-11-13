<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Tests\Bundle\Os;

use InvalidArgumentException;
use Masilia\Bundle\IbexaAi\Os\Coverage\CoverageMatrix;
use PHPUnit\Framework\TestCase;

final class CoverageMatrixTest extends TestCase
{
    public function testRoutesAreCoveredPlannedOutOfScopeOrUnmappedAndEntriesWithoutRouteAreStale(): void
    {
        $matrix = new CoverageMatrix(
            ['ibexa.trash.list' => 'trash.list', 'ibexa.section.create' => 'section.create', 'ibexa.gone' => 'x.y'],
            ['ibexa.udw.*' => 'a picker', 'ibexa.udw.special' => 'its own reason', 'ibexa.never.*' => 'unused'],
        );

        $result = $matrix->classify(
            ['ibexa.trash.list', 'ibexa.section.create', 'ibexa.udw.data', 'ibexa.udw.special', 'ibexa.new_screen'],
            static fn (string $name): bool => $name === 'trash.list',
        );

        self::assertSame(['ibexa.trash.list' => 'trash.list'], $result['covered']);
        self::assertSame(['ibexa.section.create' => 'section.create'], $result['planned']);
        self::assertSame(['ibexa.udw.data' => 'a picker', 'ibexa.udw.special' => 'its own reason'], $result['out_of_scope']);
        self::assertSame(['ibexa.new_screen'], $result['unmapped']);
        self::assertEqualsCanonicalizing(['ibexa.gone', 'ibexa.never.*'], $result['stale']);
    }

    public function testARouteCannotBeMappedAndOutOfScope(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cov');
        file_put_contents($file, "capabilities:\n  ibexa.a: x.y\nout_of_scope:\n  ibexa.a: no\n");

        try {
            $this->expectException(InvalidArgumentException::class);
            CoverageMatrix::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testTheShippedFileIsValidAndEveryCapabilityNameInItIsWellFormed(): void
    {
        $file = __DIR__ . '/../../../src/bundle/Resources/config/os_coverage.yaml';
        $matrix = CoverageMatrix::fromFile($file);

        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => preg_match('/^  ibexa\.[\w.]+: ([\w.]+)$/', $line, $m) === 1 ? $m[1] : '',
            file($file, FILE_IGNORE_NEW_LINES),
        ))));
        self::assertGreaterThan(60, count($names));
        foreach ($names as $name) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $name);
        }
        self::assertInstanceOf(CoverageMatrix::class, $matrix);
    }
}
