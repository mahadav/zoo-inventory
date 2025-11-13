<?php

namespace App\Repository;

use App\Entity\AnimalCountGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnimalCountGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalCountGroup::class);
    }

    // Example placeholder for future logic
    public function findByFeedEligibleGreaterThan(int $count): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.male + a.female > :count')
            ->setParameter('count', $count)
            ->getQuery()
            ->getResult();
    }

    // Add save/remove helpers if needed
    public function save(AnimalCountGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AnimalCountGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
