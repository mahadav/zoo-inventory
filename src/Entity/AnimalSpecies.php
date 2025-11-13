<?php

namespace App\Entity;

use App\Repository\AnimalSpeciesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use JsonSerializable;

#[ORM\Entity(repositoryClass: AnimalSpeciesRepository::class)]
#[ORM\Table(name: 'animal_species')]
#[ORM\HasLifecycleCallbacks]
class AnimalSpecies implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnimalCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private AnimalCategory $category;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $commonName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $scientificName = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $schedule = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(targetEntity: DietItem::class, mappedBy: 'species', cascade: ['persist', 'remove'])]
    private Collection $dietItems;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->dietItems = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ---------- Getters & Setters ----------

    public function getId(): ?int { return $this->id; }

    public function getCategory(): AnimalCategory { return $this->category; }
    public function setCategory(AnimalCategory $category): self { $this->category = $category; return $this; }

    public function getCommonName(): string { return $this->commonName; }
    public function setCommonName(string $commonName): self { $this->commonName = $commonName; return $this; }

    public function getScientificName(): ?string { return $this->scientificName; }
    public function setScientificName(?string $scientificName): self { $this->scientificName = $scientificName; return $this; }

    public function getSchedule(): ?string { return $this->schedule; }
    public function setSchedule(?string $schedule): self { $this->schedule = $schedule; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /**
     * @return Collection<int, DietItem>
     */
    public function getDietItems(): Collection
    {
        return $this->dietItems;
    }

    public function addDietItem(DietItem $dietItem): self
    {
        if (!$this->dietItems->contains($dietItem)) {
            $this->dietItems[] = $dietItem;
            $dietItem->setSpecies($this);
        }
        return $this;
    }

    public function removeDietItem(DietItem $dietItem): self
    {
        if ($this->dietItems->removeElement($dietItem)) {
            if ($dietItem->getSpecies() === $this) {
                $dietItem->setSpecies(null);
            }
        }
        return $this;
    }

    // ---------- JSON Serialization ----------
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->jsonSerialize(),
            'commonName' => $this->commonName,
            'scientificName' => $this->scientificName,
            'schedule' => $this->schedule,
            'active' => $this->active,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
            'dietItems' => array_map(fn($d) => $d->jsonSerialize(), $this->dietItems->toArray()),
        ];
    }
}
