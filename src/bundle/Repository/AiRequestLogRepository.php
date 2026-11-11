<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\Bundle\IbexaAi\Entity\AiRequestLog;

/**
 * @extends ServiceEntityRepository<AiRequestLog>
 */
class AiRequestLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiRequestLog::class);
    }

    /**
     * Totals and per-provider breakdown since a point in time, in the shape the usage panel reads.
     *
     * @return array{totals: array<string, int|float>, perProvider: list<array<string, int|float|string>>}
     */
    public function aggregateSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.providerIdentifier AS providerIdentifier')
            ->addSelect('COUNT(l.id) AS total')
            ->addSelect('SUM(CASE WHEN l.success = true THEN 1 ELSE 0 END) AS success')
            ->addSelect('COALESCE(SUM(l.tokensIn), 0) AS tokensIn')
            ->addSelect('COALESCE(SUM(l.tokensOut), 0) AS tokensOut')
            ->addSelect('COALESCE(AVG(l.latencyMs), 0) AS avgLatencyMs')
            ->where('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('l.providerIdentifier')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $totals = ['total' => 0, 'success' => 0, 'error' => 0, 'tokensIn' => 0, 'tokensOut' => 0, 'avgLatencyMs' => 0];
        $perProvider = [];
        $latencySum = 0.0;

        foreach ($rows as $row) {
            $row = [
                'providerIdentifier' => (string) $row['providerIdentifier'],
                'total' => (int) $row['total'],
                'success' => (int) $row['success'],
                'error' => (int) $row['total'] - (int) $row['success'],
                'tokensIn' => (int) $row['tokensIn'],
                'tokensOut' => (int) $row['tokensOut'],
                'avgLatencyMs' => (int) round((float) $row['avgLatencyMs']),
            ];
            $perProvider[] = $row;

            foreach (['total', 'success', 'error', 'tokensIn', 'tokensOut'] as $key) {
                $totals[$key] += $row[$key];
            }
            $latencySum += $row['avgLatencyMs'] * $row['total'];
        }

        if ($totals['total'] > 0) {
            $totals['avgLatencyMs'] = (int) round($latencySum / $totals['total']);
        }

        return ['totals' => $totals, 'perProvider' => $perProvider];
    }

    /**
     * Most recent outcomes for one provider, newest first (used for the health banner).
     *
     * @return list<bool>
     */
    public function recentOutcomes(string $providerIdentifier, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.success')
            ->where('l.providerIdentifier = :provider')
            ->setParameter('provider', $providerIdentifier)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): bool => (bool) $row['success'], $rows);
    }
}
