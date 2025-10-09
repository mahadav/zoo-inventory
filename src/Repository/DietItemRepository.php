<?php

namespace App\Repository;

use App\Entity\DietItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DietItem>
 */
class DietItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DietItem::class);
    }

    // Example of a custom method
    public function findByAnimalId(int $animalId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.animal = :animalId')
            ->setParameter('animalId', $animalId)
            ->getQuery()
            ->getResult();
    }

    public function findDietItemsForAdultAnimals()
    {
        return $this->createQueryBuilder('d')
            ->join('d.animal', 'a')
            ->where('a.isUnderage = false')
            ->getQuery()
            ->getResult();
    }

    public function getAnimalWiseFeedEstimate(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.animal', 'a')
            ->leftJoin('d.feedItem', 'f')
            ->addSelect('a', 'f')
            ->getQuery();

        /** @var DietItem[] $dietItems */
        $dietItems = $qb->getResult();

        $animalMap = [];

        foreach ($dietItems as $dietItem) {
            $animal = $dietItem->getAnimal();
            if (!$animal) {
                continue;
            }

            $animalId = $animal->getId();
            $animalName = $animal->getAnimalName();
            $stock = $animal->getCurrentStock();
            $adultCount = ($stock->getMale() ?? 0) + ($stock->getFemale() ?? 0);

            if (!isset($animalMap[$animalId])) {
                $animalMap[$animalId] = [
                    'animalId' => $animalId,
                    'animalName' => $animalName,
                    'adultCount' => $adultCount,
                    'dietItems' => []
                ];
            }

            $animalMap[$animalId]['dietItems'][] = [
                'feedItem' => $dietItem->getFeedItem()->getId(),
                'unitQuantity' => $dietItem->getQuantity(),
                'totalQuantity' => $dietItem->getQuantity() * $adultCount
            ];
        }

        return array_values($animalMap);
    }



    public function getDailyFeedConsumption(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('f.id AS feedItemId, f.name AS feedItemName, u.name AS unitName, SUM(d.quantity * d.adultCount) AS totalQuantity')
            ->join('d.feedItem', 'f')
            ->join('f.unit', 'u')
            ->groupBy('f.id')
            ->orderBy('f.name', 'ASC');

        return $qb->getQuery()->getArrayResult();
    }




}
