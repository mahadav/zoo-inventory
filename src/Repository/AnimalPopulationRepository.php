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

    public function findLatestBySpecies(int $speciesId): ?AnimalPopulation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.species = :speciesId')
            ->setParameter('speciesId', $speciesId)
            ->orderBy('r.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getPopulationReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.species', 's')
            ->join('s.category', 'c')
            ->andWhere('p.recordedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.commonName', 'ASC');

        $records = $qb->getQuery()->getResult();

        $data = [
            'categories'  => [],   // holds each category block
            'grand_total' => $this->emptyTotals(),
        ];

        $serials = []; // category-wise serial numbers

        foreach ($records as $record) {
            /** @var AnimalPopulation $record */
            $species  = $record->getSpecies();
            $category = $species->getCategory()->getName(); // from DB

            // Initialize category if not already created
            if (!isset($data['categories'][$category])) {
                $data['categories'][$category] = [
                    'rows'   => [],
                    'total'  => $this->emptyTotals(),
                ];
                $serials[$category] = 1;
            }

            $row = [
                'sl' => $serials[$category],
                'name' => $species->getCommonName(),
                'scientific' => $species->getScientificName(),
                'schedule' => $species->getSchedule(),
                'opening' => $this->formatGroup($record->getOpening()),
                'births' => $this->formatGroup($record->getBirths()),
                'acquisitions' => $this->formatGroup($record->getAcquisitions()),
                'disposals' => $this->formatGroup($record->getDisposals()),
                'deaths' => $this->formatGroup($record->getDeaths()),
                'closing' => $this->formatGroup($record->getClosing()),
            ];

            // Add row to category
            $data['categories'][$category]['rows'][] = $row;

            // Accumulate category total
            $this->accumulateTotals($data['categories'][$category]['total'], $row);

            // Accumulate grand total
            $this->accumulateTotals($data['grand_total'], $row);

            $serials[$category]++;
        }

        return $data;
    }


    private function formatGroup($group): array
    {
        return [
            'm' => $group->getMale(),
            'f' => $group->getFemale(),
            'u' => $group->getUnderage(),
            't' => $group->getTotal(),
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'opening' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
            'births' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
            'acquisitions' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
            'disposals' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
            'deaths' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
            'closing' => ['m'=>0,'f'=>0,'u'=>0,'t'=>0],
        ];
    }

    private function accumulateTotals(array &$total, array $row): void
    {
        foreach (['opening','births','acquisitions','disposals','deaths','closing'] as $key) {
            $total[$key]['m'] += $row[$key]['m'];
            $total[$key]['f'] += $row[$key]['f'];
            $total[$key]['u'] += $row[$key]['u'];
            $total[$key]['t'] += $row[$key]['t'];
        }
    }


}
