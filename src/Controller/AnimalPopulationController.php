<?php

namespace App\Controller;

use App\Entity\AnimalPopulation;
use App\Entity\AnimalSpecies;
use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
use App\Service\AnimalPopulationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/animal-population', name: 'api_animal_population_')]
class AnimalPopulationController extends AbstractController
{
    public function __construct(
        private AnimalPopulationManager $populationManager,
        private AnimalSpeciesRepository $speciesRepository
    ) {
    }

    // -------------------------
    // LIST BY SPECIES
    // -------------------------
    #[Route('/{speciesId}', name: 'list', methods: ['GET'])]
    public function list(int $speciesId, Request $request): JsonResponse
    {
        try {
            $date = $request->query->get('date');
            $records = $this->populationManager->getRecordsBySpecies($speciesId, $date);

            if (!$records) {
                return $this->json(['message' => 'No records found for this species.'], 404);
            }

            return $this->json(array_map(fn($r) => $r->jsonSerialize(), $records));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // -------------------------
    // CREATE NEW RECORD
    // -------------------------
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate
        $errors = $this->populationManager->validateData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        // Find species
        $species = $this->speciesRepository->find($data['speciesId']);
        if (!$species) {
            return $this->json(['error' => 'Invalid speciesId'], 400);
        }

        try {
            $population = $this->populationManager->savePopulationRecord($data, null, $species);

            return $this->json([
                'success' => true,
                'record' => $population->jsonSerialize(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // -------------------------
    // UPDATE EXISTING RECORD
    // -------------------------
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        AnimalPopulationRepository $popRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Find existing population record
        $population = $popRepo->find($id);
        if (!$population) {
            return $this->json(['error' => 'Record not found'], 404);
        }

        // Validate
        $errors = $this->populationManager->validateData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        try {
            $population = $this->populationManager->savePopulationRecord($data, $population);

            return $this->json([
                'success' => true,
                'record' => $population->jsonSerialize(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}