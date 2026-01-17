<?php

namespace App\Controller;

use App\Service\AnimalManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/animals')]
class AnimalController extends AbstractController
{
    public function __construct(
        private AnimalManager $animalManager
    ) {}

    #[Route('', name: 'api_animal_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $animal = $this->animalManager->createAnimal($data);

            return $this->json($animal, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_animal_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $animal = $this->animalManager->getAnimalWithPopulation($id)['animal'] ?? null;
            if (!$animal) {
                return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $animal = $this->animalManager->updateAnimal($animal, $data);

            return $this->json($animal);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/diet', name: 'api_animal_diet_replace', methods: ['POST', 'PUT'])]
    public function replaceDiet(Request $request, int $id): JsonResponse
    {
        try {
            $animal = $this->animalManager->getAnimalWithPopulation($id)['animal'] ?? null;
            if (!$animal) {
                return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            if (!isset($data['dietItems']) || !is_array($data['dietItems'])) {
                return $this->json(['error' => 'dietItems array is required'], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->animalManager->replaceDiet($animal, $data['dietItems']);

            return $this->json([
                'status' => 'success',
                'message' => $result['itemsCount'] . ' diet items replaced',
                'animalId' => $result['animalId'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_animal_get', methods: ['GET'])]
    public function getAnimal(int $id): JsonResponse
    {
        $animalData = $this->animalManager->getAnimalWithPopulation($id);
        if (!$animalData) {
            return $this->json(['error' => 'Animal not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($animalData['animal']);
    }

    #[Route('', name: 'api_animal_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $animals = $this->animalManager->getAllAnimalsWithPopulation();
        return $this->json($animals);
    }
}