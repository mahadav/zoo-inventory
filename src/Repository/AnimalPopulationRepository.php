<?php

namespace App\Repository;

use App\Entity\AnimalPopulation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnimalPopulationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalPopulation::class);
    }

    // Example custom query:
    // public function findLatestBySpecies(int $speciesId): ?AnimalPopulation
    // {
    //     return $this->createQueryBuilder('r')
    //         ->andWhere('r.species = :speciesId')
    //         ->setParameter('speciesId', $speciesId)
    //         ->orderBy('r.recordedAt', 'DESC')
    //         ->setMaxResults(1)
    //         ->getQuery()
    //         ->getOneOrNullResult();
    // }
}
