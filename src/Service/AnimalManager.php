<?php

namespace App\Service;

use App\Entity\AnimalSpecies;
use App\Entity\DietItem;
use App\Repository\AnimalSpeciesRepository;
use App\Repository\AnimalCategoryRepository;
use App\Repository\FeedItemRepository;
use App\Repository\AnimalPopulationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AnimalManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AnimalSpeciesRepository $animalRepository,
        private AnimalCategoryRepository $categoryRepository,
        private FeedItemRepository $feedRepository,
        private AnimalPopulationRepository $populationRepository,
        private ValidatorInterface $validator
    ) {}

    /**
     * Create a new animal species
     */
    public function createAnimal(array $data): AnimalSpecies
    {
        // Validate required fields
        $required = ['commonName', 'scientificName', 'categoryId'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }

        // Find category
        $category = $this->categoryRepository->find($data['categoryId']);
        if (!$category) {
            throw new \InvalidArgumentException('Category not found');
        }

        // Create entity
        $animal = new AnimalSpecies();
        $animal->setCommonName($data['commonName']);
        $animal->setScientificName($data['scientificName']);
        $animal->setCategory($category);
        $animal->setSchedule($data['schedule'] ?? null);
        $animal->setActive($data['active'] ?? true);

        // Validate
        $errors = $this->validator->validate($animal);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->em->persist($animal);
        $this->em->flush();

        return $animal;
    }

    /**
     * Update an existing animal species
     */
    public function updateAnimal(AnimalSpecies $animal, array $data): AnimalSpecies
    {
        if (isset($data['commonName'])) {
            $animal->setCommonName($data['commonName']);
        }
        if (isset($data['scientificName'])) {
            $animal->setScientificName($data['scientificName']);
        }
        if (array_key_exists('schedule', $data)) {
            $animal->setSchedule($data['schedule']);
        }
        if (array_key_exists('active', $data)) {
            $animal->setActive((bool) $data['active']);
        }
        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }
            $animal->setCategory($category);
        }

        $errors = $this->validator->validate($animal);
        if (count($errors) > 0) {
            throw new \InvalidArgumentException((string) $errors);
        }

        $this->em->flush();

        return $animal;
    }

    /**
     * Replace diet items for an animal
     */
    public function replaceDiet(AnimalSpecies $animal, array $dietItems): array
    {
        if (!is_array($dietItems)) {
            throw new \InvalidArgumentException('dietItems must be an array');
        }

        // Remove existing diet items
        foreach ($animal->getDietItems() as $existingItem) {
            $this->em->remove($existingItem);
        }

        // Get current population for adult count
        $population = $this->populationRepository->findOneBy(
            ['species' => $animal],
            ['recordedAt' => 'DESC']
        );

        $adultCount = 0;
        if ($population) {
            $closing = $population->getClosing();
            $adultCount = $closing->getMale() + $closing->getFemale();
        }

        $newItems = [];
        foreach ($dietItems as $index => $itemData) {
            if (empty($itemData['feedId']) || !isset($itemData['quantity'])) {
                throw new \InvalidArgumentException("Diet item at index $index is missing required fields");
            }

            $feedItem = $this->feedRepository->find($itemData['feedId']);
            if (!$feedItem) {
                throw new \InvalidArgumentException("Feed item not found for ID: " . $itemData['feedId']);
            }

            $dietItem = new DietItem();
            $dietItem->setFeedItem($feedItem);
            $dietItem->setQuantity((float) $itemData['quantity']);
            $dietItem->setSpecies($animal);
            $dietItem->setAdultCount($adultCount);

            $errors = $this->validator->validate($dietItem);
            if (count($errors) > 0) {
                throw new \InvalidArgumentException((string) $errors);
            }

            $this->em->persist($dietItem);
            $newItems[] = $dietItem;
        }

        $this->em->flush();

        return [
            'animalId' => $animal->getId(),
            'itemsCount' => count($newItems),
            'adultCount' => $adultCount
        ];
    }

    /**
     * Get all animals with their latest population counts
     */
    public function getAllAnimalsWithPopulation(): array
    {
        $animals = $this->animalRepository->findAll();
        $result = [];

        foreach ($animals as $animal) {
            $result[] = $this->enrichAnimalWithPopulation($animal);
        }

        return $result;
    }

    /**
     * Get single animal with population data
     */
    public function getAnimalWithPopulation(int $id): ?array
    {
        $animal = $this->animalRepository->find($id);
        if (!$animal) {
            return null;
        }

        return $this->enrichAnimalWithPopulation($animal);
    }

    /**
     * Enrich animal entity with population data
     */
    private function enrichAnimalWithPopulation(AnimalSpecies $animal): array
    {
        $population = $this->populationRepository->findOneBy(
            ['species' => $animal],
            ['recordedAt' => 'DESC']
        );

        $maleCount = 0;
        $femaleCount = 0;
        $underageCount = 0;

        if ($population) {
            $closing = $population->getClosing();
            $maleCount = $closing->getMale();
            $femaleCount = $closing->getFemale();
            $underageCount = $closing->getUnderage();
        }

        return [
            'animal' => $animal,
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
            'underageCount' => $underageCount,
            'totalCount' => $maleCount + $femaleCount + $underageCount,
            'adultCount' => $maleCount + $femaleCount
        ];
    }

    /**
     * Get all categories for forms
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findAll();
    }
}