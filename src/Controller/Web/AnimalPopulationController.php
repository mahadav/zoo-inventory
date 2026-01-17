<?php

namespace App\Controller\Web;

use App\Entity\AnimalPopulation;
use App\Entity\AnimalSpecies;
use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
use App\Service\AnimalPopulationManager;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/web/animal-population', name: 'web_animal_population_')]
class AnimalPopulationController extends AbstractController
{
    public function __construct(
        private AnimalPopulationManager $populationManager,
        private AnimalSpeciesRepository $speciesRepository,
        private AnimalPopulationRepository $populationRepository
    ) {
    }

    // -------------------------
    // LIST BY SPECIES (Web)
    // -------------------------
    #[Route('/species/{speciesId}', name: 'list', methods: ['GET'])]
    public function list(int $speciesId, Request $request): Response
    {
        try {
            $date = $request->query->get('date');
            $species = $this->speciesRepository->find($speciesId);

            if (!$species) {
                $this->addFlash('error', 'Species not found');
                return $this->redirectToRoute('web_animal_population_index');
            }

            $records = $this->populationManager->getRecordsBySpecies($speciesId, $date);

            return $this->render('population/list.html.twig', [
                'species' => $species,
                'records' => $records,
                'selectedDate' => $date,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('web_animal_population_index');
        }
    }

    // -------------------------
    // CREATE FORM (Web) - RENAMED from createForm to showCreateForm
    // -------------------------
// -------------------------
// CREATE FORM (Web) - RENAMED from createForm to showCreateForm
// -------------------------
// -------------------------
// CREATE FORM (Web) - RENAMED from createForm to showCreateForm
// -------------------------
// -------------------------
// CREATE FORM (Web) - RENAMED from createForm to showCreateForm
// -------------------------
    #[Route('/create', name: 'create_form', methods: ['GET'])]
    public function showCreateForm(Request $request): Response
    {
        $speciesId = $request->query->get('speciesId');
        $species = null;
        $lastOpeningData = null;

        if ($speciesId) {
            $species = $this->speciesRepository->find($speciesId);

            // Get the latest population record for this species
            if ($species) {
                $lastRecord = $this->populationRepository->findOneBy(
                    ['species' => $species],
                    ['recordedAt' => 'DESC']
                );

                // If we have a last record, extract the closing data for opening
                if ($lastRecord) {
                    // Access the closing group through getter methods
                    $closingGroup = $lastRecord->getClosing();
                    if ($closingGroup) {
                        $lastOpeningData = [
                            'male' => $closingGroup->getMale(),
                            'female' => $closingGroup->getFemale(),
                            'underage' => $closingGroup->getUnderage(),
                        ];
                    }
                }
            }
        }

        $allSpecies = $this->speciesRepository->findBy(['active' => true], ['commonName' => 'ASC']);

        return $this->render('population/create.html.twig', [
            'species' => $species,
            'allSpecies' => $allSpecies,
            'record' => null,
            'lastOpeningData' => $lastOpeningData, // Pass extracted data
            'lastRecord' => $lastRecord ?? null, // Pass the whole record if needed
        ]);
    }


    // -------------------------
    // CREATE NEW RECORD (Web)
    // -------------------------
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = $request->request->all();

        // Validate
        $errors = $this->populationManager->validateData($data);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('web_animal_population_create_form', [
                'speciesId' => $data['speciesId'] ?? null
            ]);
        }

        // Find species
        $species = $this->speciesRepository->find($data['speciesId']);
        if (!$species) {
            $this->addFlash('error', 'Invalid species selected');
            return $this->redirectToRoute('web_animal_population_create_form');
        }

        try {
            $population = $this->populationManager->savePopulationRecord($data, null, $species);

            $this->addFlash('success', 'Population record created successfully!');
            return $this->redirectToRoute('web_animal_population_view', ['id' => $population->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error creating record: ' . $e->getMessage());
            return $this->redirectToRoute('web_animal_population_create_form', [
                'speciesId' => $data['speciesId']
            ]);
        }
    }

    // -------------------------
    // EDIT FORM (Web) - RENAMED from editForm to showEditForm
    // -------------------------
    #[Route('/{id}/edit', name: 'edit_form', methods: ['GET'])]
    public function showEditForm(int $id): Response
    {
        $record = $this->populationRepository->find($id);

        if (!$record) {
            $this->addFlash('error', 'Record not found');
            return $this->redirectToRoute('web_animal_population_index');
        }

        $allSpecies = $this->speciesRepository->findBy(['active' => true], ['commonName' => 'ASC']);

        return $this->render('population/edit.html.twig', [
            'record' => $record,
            'allSpecies' => $allSpecies,
        ]);
    }

    // -------------------------
    // UPDATE RECORD (Web)
    // -------------------------
    #[Route('/{id}/edit', name: 'edit', methods: ['POST'])]
    public function edit(int $id, Request $request): Response
    {
        $record = $this->populationRepository->find($id);

        if (!$record) {
            $this->addFlash('error', 'Record not found');
            return $this->redirectToRoute('web_animal_population_index');
        }

        $data = $request->request->all();

        // Validate
        $errors = $this->populationManager->validateData($data);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('web_animal_population_edit_form', ['id' => $id]);
        }

        try {
            $this->populationManager->savePopulationRecord($data, $record);

            $this->addFlash('success', 'Record updated successfully!');
            return $this->redirectToRoute('web_animal_population_view', ['id' => $record->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error updating record: ' . $e->getMessage());
            return $this->redirectToRoute('web_animal_population_edit_form', ['id' => $id]);
        }
    }



    // -------------------------
    // INDEX/OVERVIEW (Web)
    // -------------------------
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $allSpecies = $this->speciesRepository->findBy(['active' => true], ['commonName' => 'ASC']);

        // Get recent records for each species
        $recentRecords = [];
        foreach ($allSpecies as $species) {
            $records = $this->populationRepository->findBy(
                ['species' => $species],
                ['recordedAt' => 'DESC'],
                5
            );
            if ($records) {
                $recentRecords[$species->getId()] = $records;
            }
        }

        return $this->render('population/index.html.twig', [
            'allSpecies' => $allSpecies,
            'recentRecords' => $recentRecords,
        ]);
    }

    #[Route('/all', name: 'all_animals', methods: ['GET'])]
    public function all(
        Request $request,
        AnimalPopulationRepository $populationRepository
    ): Response {

        // Default dates (first load)
        $fromDateStr = $request->query->get('from_date', '2022-07-01');
        $toDateStr   = $request->query->get('to_date', '2026-09-30');

        $fromDate = new \DateTime($fromDateStr);
        $toDate   = new \DateTime($toDateStr);

        $report = $populationRepository->getPopulationReport($fromDate, $toDate);

        return $this->render('report/population_record.html.twig', [
            'from_date'   => $fromDate->format('Y-m-d'), // for input value
            'to_date'     => $toDate->format('Y-m-d'),
            'from_label'  => $fromDate->format('d-m-Y'), // for display
            'to_label'    => $toDate->format('d-m-Y'),
            'categories'  => $report['categories'],
            'grand_total' => $report['grand_total'],
        ]);
    }


    #[Route('/all/print', name: 'print_animals', methods: ['GET'])]
    public function print(
        Request $request,
        AnimalPopulationRepository $populationRepository
    ): Response {

        $fromDateStr = $request->query->get('from_date');
        $toDateStr   = $request->query->get('to_date');

        $fromDate = new \DateTime($fromDateStr);
        $toDate   = new \DateTime($toDateStr);

        $report = $populationRepository->getPopulationReport($fromDate, $toDate);

        // Render PDF HTML
        $html = $this->renderView('report/print_population_record.html.twig', [
            'from_label'  => $fromDate->format('d-m-Y'),
            'to_label'    => $toDate->format('d-m-Y'),
            'categories'  => $report['categories'],
            'grand_total' => $report['grand_total'],
        ]);

        // Dompdf config
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="animal_stock_register.pdf"',
            ]
        );
    }



    // -------------------------
    // VIEW SINGLE RECORD (Web)
    // -------------------------
    #[Route('/{id}', name: 'view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $record = $this->populationRepository->find($id);

        if (!$record) {
            $this->addFlash('error', 'Record not found');
            return $this->redirectToRoute('web_animal_population_index');
        }

        return $this->render('population/view.html.twig', [
            'record' => $record,
        ]);
    }

}