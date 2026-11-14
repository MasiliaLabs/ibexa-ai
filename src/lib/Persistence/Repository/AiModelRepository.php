<?php

declare(strict_types=1);

namespace Masilia\IbexaAi\Persistence\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Masilia\IbexaAi\Persistence\Entity\AiModel;

/**
 * @extends ServiceEntityRepository<AiModel>
 */
class AiModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiModel::class);
    }
}
