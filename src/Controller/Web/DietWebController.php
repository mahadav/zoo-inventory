<?php

namespace App\Controller\Web;

use App\Service\DietService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/web/diet')]
class DietWebController extends AbstractController
{
    public function __construct(
        private DietService $dietService
    ) {
    }

    /**
     * Show diet items for a specific animal
     */
    #[Route('/animal/{animalId}', name: 'web_diet_items_list', methods: ['GET'])]
    public function listByAnimal(int $animalId): Response
    {
        try {
            $dietItems = $this->dietService->getDietItemsByAnimal($animalId);

            return $this->render('diet/list.html.twig', [
                'dietItems' => $dietItems,
                'animalId' => $animalId
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_diet_animals');
        }
    }

    /**
     * Show form to add diet items
     */
    #[Route('/animal/{animalId}/add', name: 'web_diet_items_add_form', methods: ['GET'])]
    public function addForm(int $animalId): Response
    {
        try {
            $feedItems = $this->dietService->getAllFeedItems();

            return $this->render('diet/add.html.twig', [
                'animalId' => $animalId,
                'feedItems' => $feedItems
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_diet_items_list', ['animalId' => $animalId]);
        }
    }

    /**
     * Handle form submission to add diet items
     */
    #[Route('/animal/{animalId}/add', name: 'web_diet_items_add_submit', methods: ['POST'])]
    public function addSubmit(Request $request, int $animalId): Response
    {
        try {
            $data = [
                'dietItems' => [
                    [
                        'feedId' => $request->request->get('feedId'),
                        'quantity' => (float) $request->request->get('quantity')
                    ]
                ]
            ];

            $this->dietService->addDietItems($animalId, $data);
            $this->addFlash('success', 'Diet item added successfully');

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('web_diet_items_list', ['animalId' => $animalId]);
    }

    /**
     * Show form to edit diet item
     */
    #[Route('/edit/{id}', name: 'web_diet_item_edit_form', methods: ['GET'])]
    public function editForm(int $id): Response
    {
        try {
            // Get diet item
            $dietItems = $this->dietService->getDietItemsByAnimal(0); // We need a method to get single item
            $dietItem = null;
            foreach ($dietItems as $item) {
                if ($item->getId() === $id) {
                    $dietItem = $item;
                    break;
                }
            }

            if (!$dietItem) {
                throw new \Exception('Diet item not found');
            }

            $feedItems = $this->dietService->getAllFeedItems();

            return $this->render('diet/edit.html.twig', [
                'dietItem' => $dietItem,
                'feedItems' => $feedItems
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_diet_animals');
        }
    }

    /**
     * Handle form submission to update diet item
     */
    #[Route('/update/{id}', name: 'web_diet_item_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        try {
            $data = [
                'feedId' => $request->request->get('feedId'),
                'quantity' => (float) $request->request->get('quantity')
            ];

            $dietItem = $this->dietService->updateDietItem($id, $data);
            $this->addFlash('success', 'Diet item updated successfully');

            // Redirect back to the animal's diet list
            $animalId = $dietItem->getSpecies()->getId();
            return $this->redirectToRoute('web_diet_items_list', ['animalId' => $animalId]);

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_diet_item_edit_form', ['id' => $id]);
        }
    }

    /**
     * Delete a diet item
     */
    #[Route('/delete/{id}', name: 'web_diet_item_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        try {
            $animalId = $request->request->get('animalId');
            $this->dietService->deleteDietItem($id);
            $this->addFlash('success', 'Diet item deleted successfully');

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('web_diet_items_list', ['animalId' => $animalId]);
    }

    /**
     * List all animals for selection
     */
    #[Route('/animals', name: 'web_diet_animals', methods: ['GET'])]
    public function listAnimals(): Response
    {
        try {
            $animals = $this->dietService->getAllAnimalSpecies();

            return $this->render('diet/animals.html.twig', [
                'animals' => $animals
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->render('diet/animals.html.twig', ['animals' => []]);
        }
    }
}