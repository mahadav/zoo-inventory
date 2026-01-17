<?php

namespace App\Repository;

use App\Entity\AnimalSpecies;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnimalSpeciesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalSpecies::class);
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
