<?php

namespace App\Controller;

use App\Service\DietService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/diet-items')]
class DietItemController extends AbstractController
{
    public function __construct(
        private DietService $dietService
    ) {
    }

    /**
     * List all diet items for a specific animal
     */
    #[Route('/animal/{animalId}', name: 'api_diet_items_list_by_animal', methods: ['GET'])]
    public function listByAnimal(int $animalId): JsonResponse
    {
        try {
            $dietItems = $this->dietService->getDietItemsByAnimal($animalId);
            return $this->json($dietItems);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add new diet items for a specific animal (one or multiple)
     */
    #[Route('/animal/{animalId}', name: 'api_diet_items_add', methods: ['POST'])]
    public function add(Request $request, int $animalId): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $addedItems = $this->dietService->addDietItems($animalId, $data);

            return $this->json([
                'status' => 'success',
                'message' => count($addedItems) . ' diet items added successfully',
                'items' => $addedItems
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a specific diet item
     */
    #[Route('/{id}', name: 'api_diet_item_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $dietItem = $this->dietService->updateDietItem($id, $data);

            return $this->json([
                'status' => 'success',
                'message' => 'Diet item updated successfully',
                'dietItem' => $dietItem
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a diet item
     */
    #[Route('/{id}', name: 'api_diet_item_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->dietService->deleteDietItem($id);

            return $this->json([
                'status' => 'success',
                'message' => 'Diet item deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}