<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaAi\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\Bundle\IbexaAi\Entity\AiProvider;

/**
 * @extends ServiceEntityRepository<AiProvider>
 */
class AiProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProvider::class);
    }

    /**
     * The provider assigned to a siteaccess that has an active chat model.
     */
    public function findActiveChatForSiteaccess(string $siteaccess): ?AiProvider
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccesses', 'sa')
            ->innerJoin('p.activeChatModel', 'm')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The provider assigned to a siteaccess that has an active image model.
     */
    public function findActiveImageForSiteaccess(string $siteaccess): ?AiProvider
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.siteaccesses', 'sa')
            ->innerJoin('p.activeImageModel', 'm')
            ->where('sa.siteaccess = :siteaccess')
            ->setParameter('siteaccess', $siteaccess)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
