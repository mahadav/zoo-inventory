<?php

namespace App\Service;

use App\Entity\AnimalPopulation;
use App\Entity\AnimalSpecies;
use App\Repository\AnimalPopulationRepository;
use App\Repository\DietItemRepository;
use App\Repository\AnimalSpeciesRepository;
use Doctrine\ORM\EntityManagerInterface;

class AnimalPopulationManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private DietItemRepository $dietItemRepository
    ) {
    }

    /**
     * Get population records by species ID and optional date
     */
    public function getRecordsBySpecies(int $speciesId, ?string $date = null): array
    {
        $criteria = ['species' => $speciesId];

        if ($date) {
            try {
                $criteria['recordedAt'] = new \DateTimeImmutable($date);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD.');
            }
        }

        $repo = $this->em->getRepository(AnimalPopulation::class);
        return $repo->findBy($criteria, ['recordedAt' => 'DESC']);
    }

    /**
     * Create or update population record
     */
    public function savePopulationRecord(
        array $data,
        ?AnimalPopulation $population = null,
        ?AnimalSpecies $species = null
    ): AnimalPopulation {
        if (!$population) {
            $population = new AnimalPopulation();

            if (!$species) {
                throw new \InvalidArgumentException('Species is required for new records');
            }
            $population->setSpecies($species);
        }

        // recordedAt
        if (isset($data['recordedAt'])) {
            try {
                $date = new \DateTimeImmutable($data['recordedAt']);
                $ref = new \ReflectionProperty(AnimalPopulation::class, 'recordedAt');
                $ref->setAccessible(true);
                $ref->setValue($population, $date);
            } catch (\Exception $e) {
                // silently ignore invalid date
            }
        }

        // embedded AnimalCountGroup fields
        foreach (['opening', 'births', 'acquisitions', 'disposals', 'deaths', 'closing'] as $key) {
            if (isset($data[$key]) && method_exists($population, 'get' . ucfirst($key))) {
                $population->{'get' . ucfirst($key)}()->fill($data[$key]);
            }
        }

        // remarks
        if (isset($data['remarks'])) {
            $ref = new \ReflectionProperty(AnimalPopulation::class, 'remarks');
            $ref->setAccessible(true);
            $ref->setValue($population, $data['remarks']);
        }

        $this->updateDietAdultCount($population);

        $this->em->persist($population);
        $this->em->flush();

        return $population;
    }

    /**
     * Update diet adult count based on closing group
     */
    private function updateDietAdultCount(AnimalPopulation $population): void
    {
        $species = $population->getSpecies();
        if (!$species) {
            return;
        }

        // Get adult count from closing group
        $adult = $population->getClosing()->getMale() + $population->getClosing()->getFemale() ?? 0;

        // Fetch all diet items for this species
        $dietItems = $this->dietItemRepository->findBy(['species' => $species]);

        foreach ($dietItems as $item) {
            $item->setAdultCount($adult);
            $this->em->persist($item);
        }

        // Flush only once at the end
        if (!empty($dietItems)) {
            $this->em->flush();
        }
    }

    /**
     * Validate population data
     */
    public function validateData(array $data): array
    {
        $errors = [];

        if (empty($data['speciesId'])) {
            $errors[] = 'Species ID is required';
        }

        if (isset($data['recordedAt'])) {
            try {
                new \DateTimeImmutable($data['recordedAt']);
            } catch (\Exception $e) {
                $errors[] = 'Invalid date format for recordedAt. Use YYYY-MM-DD.';
            }
        }

        return $errors;
    }
}