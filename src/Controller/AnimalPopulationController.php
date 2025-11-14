<?php
namespace App\Controller;

use App\Entity\AnimalPopulation;
use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
use App\Repository\DietItemRepository;
use App\ValueObject\AnimalCountGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/animal-population', name: 'animal_population_')]
class AnimalPopulationController extends AbstractController
{
    // -------------------------
    // LIST BY SPECIES
    // -------------------------
    #[Route('/{speciesId}', name: 'list', methods: ['GET'])]
    public function list(
        int $speciesId,
        Request $request,
        AnimalPopulationRepository $repo
    ): JsonResponse {
        $date = $request->query->get('date');
        $criteria = ['species' => $speciesId];

        if ($date) {
            try {
                $criteria['recordedAt'] = new \DateTimeImmutable($date);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
            }
        }

        $records = $repo->findBy($criteria, ['recordedAt' => 'DESC']);
        if (!$records) {
            return $this->json(['message' => 'No records found for this species.'], 404);
        }

        return $this->json(array_map(fn($r) => $r->jsonSerialize(), $records));
    }

    // -------------------------
    // CREATE NEW RECORD
    // -------------------------
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        DietItemRepository $dietItemRepository,
        AnimalSpeciesRepository $speciesRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['speciesId'])) {
            return $this->json(['error' => 'Missing speciesId'], 400);
        }

        $species = $speciesRepo->find($data['speciesId']);
        if (!$species) {
            return $this->json(['error' => 'Invalid speciesId'], 400);
        }

        $population = new AnimalPopulation();
        $population->setSpecies($species);

        $this->fillPopulationData($dietItemRepository,$population,$em, $data);

        $em->persist($population);
        $em->flush();

        return $this->json([
            'success' => true,
            'record' => $population->jsonSerialize(),
        ], 201);
    }

    // -------------------------
    // UPDATE EXISTING RECORD
    // -------------------------
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AnimalSpeciesRepository $speciesRepository,
        AnimalPopulationRepository $popRepo,
        DietItemRepository $dietItemRepository,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        //$population = $popRepo->find($id);

        $population = $popRepo->findOneBy(
            ['species' => $id],
            ['recordedAt' => 'DESC']
        );

        $species = $speciesRepository->find($data['speciesId']);
        if (!$species) {
            return $this->json(['error' => 'Invalid speciesId'], 400);
        }
        $population = new AnimalPopulation();
        $population->setSpecies($species);

        if (!$population) {
            return $this->json(['error' => 'Record not found'], 404);
        }

        $this->fillPopulationData($dietItemRepository,$population,$em,$data);

        $em->persist($population);
        $em->flush();

        return $this->json([
            'success' => true,
            'record' => $population->jsonSerialize(),
        ]);
    }

    // -------------------------
    // PRIVATE SHARED MAPPER
    // -------------------------
    private function fillPopulationData(DietItemRepository $dietItemRepository,
                                        AnimalPopulation $population,$entityManager, array $data): void
    {
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
        $this->updateDietAdultCount($population,$dietItemRepository,$entityManager);
    }

    private function updateDietAdultCount(
        AnimalPopulation $population,
        DietItemRepository $dietRepo,
        EntityManagerInterface $em
    ): void {
        $species = $population->getSpecies();
        if (!$species) return;

        // Get adult count from closing group
        $adult = $population->getClosing()->getMale()+$population->getClosing()->getFemale() ?? 0;

        // Fetch all diet items for this species
        $dietItems = $dietRepo->findBy(['species' => $species]);

        foreach ($dietItems as $item) {
            $item->setAdultCount($adult);
            $em->persist($item);
        }

        // Flush only once at the end
        if (!empty($dietItems)) {
            $em->flush();
        }
    }

}
