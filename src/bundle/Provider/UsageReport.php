<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Provider;

use DateTimeImmutable;
use Masilia\Bundle\IbexaAi\Repository\AiRequestLogRepository;

/**
 * Usage for the three windows of the admin usage tab.
 */
final readonly class UsageReport
{
    private const WINDOWS = [
        '24h' => '-24 hours',
        '7d' => '-7 days',
        '30d' => '-30 days',
    ];

    public function __construct(
        private AiRequestLogRepository $logs,
    ) {
    }

    /**
     * @return array{windows: array<string, array{since: string, totals: array<string, int|float>, perProvider: list<array<string, int|float|string>>}>}
     */
    public function data(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $windows = [];

        foreach (self::WINDOWS as $key => $modifier) {
            $since = $now->modify($modifier);
            $aggregate = $this->logs->aggregateSince($since);

            $windows[$key] = [
                'since' => $since->format(DATE_ATOM),
                'totals' => $aggregate['totals'],
                'perProvider' => $aggregate['perProvider'],
            ];
        }

        return ['windows' => $windows];
    }
}
