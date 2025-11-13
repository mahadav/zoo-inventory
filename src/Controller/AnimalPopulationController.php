<?php
namespace App\Controller;

use App\Entity\AnimalPopulation;
use App\Repository\AnimalPopulationRepository;
use App\Repository\AnimalSpeciesRepository;
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
        AnimalSpeciesRepository $speciesRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['species_id'])) {
            return $this->json(['error' => 'Missing species_id'], 400);
        }

        $species = $speciesRepo->find($data['species_id']);
        if (!$species) {
            return $this->json(['error' => 'Invalid species_id'], 400);
        }

        $population = new AnimalPopulation();
        $population->setSpecies($species);

        $this->fillPopulationData($population, $data);

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
        AnimalPopulationRepository $popRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $population = $popRepo->find($id);

        if (!$population) {
            return $this->json(['error' => 'Record not found'], 404);
        }

        $this->fillPopulationData($population, $data);

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
    private function fillPopulationData(AnimalPopulation $population, array $data): void
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
    }
}
