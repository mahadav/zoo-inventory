<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\AnimalPopulationRepository;

#[ORM\Entity(repositoryClass: AnimalPopulationRepository::class)]
#[ORM\Table(name: 'animal_population_record')]
#[ORM\HasLifecycleCallbacks]
class AnimalPopulation implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnimalSpecies::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private AnimalSpecies $species;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $recordedAt;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'opening_')]
    private AnimalCountGroup $opening;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'births_')]
    private AnimalCountGroup $births;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'acquisitions_')]
    private AnimalCountGroup $acquisitions;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'disposals_')]
    private AnimalCountGroup $disposals;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'deaths_')]
    private AnimalCountGroup $deaths;

    #[ORM\Embedded(class: AnimalCountGroup::class, columnPrefix: 'closing_')]
    private AnimalCountGroup $closing;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->recordedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;

        $this->opening = new AnimalCountGroup();
        $this->births = new AnimalCountGroup();
        $this->acquisitions = new AnimalCountGroup();
        $this->disposals = new AnimalCountGroup();
        $this->deaths = new AnimalCountGroup();
        $this->closing = new AnimalCountGroup();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->recordedAt = $this->recordedAt ?? $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ----- Getters -----
    public function getId(): ?int { return $this->id; }
    public function getSpecies(): AnimalSpecies { return $this->species; }
    public function setSpecies(AnimalSpecies $species): self { $this->species = $species; return $this; }

    public function getOpening(): AnimalCountGroup { return $this->opening; }
    public function getBirths(): AnimalCountGroup { return $this->births; }
    public function getAcquisitions(): AnimalCountGroup { return $this->acquisitions; }
    public function getDisposals(): AnimalCountGroup { return $this->disposals; }
    public function getDeaths(): AnimalCountGroup { return $this->deaths; }
    public function getClosing(): AnimalCountGroup { return $this->closing; }

    public function getRecordedAt(): \DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'species' => $this->species->jsonSerialize(),
            'recordedAt' => $this->recordedAt->format('Y-m-d'),
            'opening' => $this->opening->jsonSerialize(),
            'births' => $this->births->jsonSerialize(),
            'acquisitions' => $this->acquisitions->jsonSerialize(),
            'disposals' => $this->disposals->jsonSerialize(),
            'deaths' => $this->deaths->jsonSerialize(),
            'closing' => $this->closing->jsonSerialize(),
            'remarks' => $this->remarks,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
