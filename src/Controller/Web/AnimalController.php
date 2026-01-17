<?php

namespace App\Controller\Web;

use App\Constants\AnimalSchedule;
use App\Service\AnimalManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/web/animals')]
class AnimalController extends AbstractController
{
    public function __construct(
        private AnimalManager $animalManager
    ) {}

    #[Route('', name: 'web_animal_list', methods: ['GET'])]
    public function list(): Response
    {
        $animals = $this->animalManager->getAllAnimalsWithPopulation();
        $categories = $this->animalManager->getAllCategories();

        return $this->render('animal/list.html.twig', [
            'animals' => $animals,
            'categories' => $categories,
        ]);
    }

    #[Route('/create', name: 'web_animal_create_form', methods: ['GET'])]
    public function showCreateForm(): Response
    {
        $categories = $this->animalManager->getAllCategories();

        return $this->render('animal/create.html.twig', [
            'categories' => $categories,
            'schedules'=>AnimalSchedule::ALL
        ]);
    }

    #[Route('/create', name: 'web_animal_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        try {
            $data = [
                'commonName' => $request->request->get('commonName'),
                'scientificName' => $request->request->get('scientificName'),
                'categoryId' => (int) $request->request->get('categoryId'),
                'schedule' => $request->request->get('schedule'),
                'active' => $request->request->getBoolean('active', true),
            ];

            $animal = $this->animalManager->createAnimal($data);

            $this->addFlash('success', 'Animal created successfully!');
            return $this->redirectToRoute('web_animal_edit', ['id' => $animal->getId()]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_animal_create_form');
        }
    }

    #[Route('/{id}/edit', name: 'web_animal_edit', methods: ['GET'])]
    public function edit(int $id): Response
    {
        $animalData = $this->animalManager->getAnimalWithPopulation($id);
        if (!$animalData) {
            throw $this->createNotFoundException('Animal not found');
        }

        $categories = $this->animalManager->getAllCategories();

        return $this->render('animal/edit.html.twig', [
            'animalData' => $animalData,
            'categories' => $categories,
            'schedules'=>AnimalSchedule::ALL
        ]);
    }

    #[Route('/{id}/update', name: 'web_animal_update', methods: ['POST'])]
    public function update(Request $request, int $id): RedirectResponse
    {
        try {
            $animalData = $this->animalManager->getAnimalWithPopulation($id);
            if (!$animalData) {
                throw $this->createNotFoundException('Animal not found');
            }

            $data = [
                'commonName' => $request->request->get('commonName'),
                'scientificName' => $request->request->get('scientificName'),
                'categoryId' => (int) $request->request->get('categoryId'),
                'schedule' => $request->request->get('schedule'),
                'active' => $request->request->getBoolean('active', true),
            ];

            $this->animalManager->updateAnimal($animalData['animal'], $data);

            $this->addFlash('success', 'Animal updated successfully!');
            return $this->redirectToRoute('web_animal_edit', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_animal_edit', ['id' => $id]);
        }
    }

    #[Route('/{id}/diet', name: 'web_animal_diet', methods: ['POST'])]
    public function updateDiet(Request $request, int $id): RedirectResponse
    {
        try {
            $animalData = $this->animalManager->getAnimalWithPopulation($id);
            if (!$animalData) {
                throw $this->createNotFoundException('Animal not found');
            }

            $dietItems = json_decode($request->request->get('dietItems'), true) ?? [];

            $result = $this->animalManager->replaceDiet($animalData['animal'], $dietItems);

            $this->addFlash('success', 'Diet updated successfully! ' . $result['itemsCount'] . ' items replaced.');
            return $this->redirectToRoute('web_animal_edit', ['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_animal_edit', ['id' => $id]);
        }
    }

    #[Route('/{id}/delete', name: 'web_animal_delete', methods: ['POST'])]
    public function delete(int $id): RedirectResponse
    {
        // Note: You might want to add delete functionality to AnimalManager
        // For now, this is a placeholder
        $this->addFlash('warning', 'Delete functionality not implemented yet');
        return $this->redirectToRoute('web_animal_list');
    }
}