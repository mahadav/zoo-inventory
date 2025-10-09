<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Animal implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $animalName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $scientificName;

    #[ORM\OneToMany(targetEntity: DietItem::class, mappedBy: 'animal', cascade: ['persist', 'remove'])]
    private Collection $dietItems;

    #[ORM\ManyToOne(targetEntity: AnimalCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AnimalCategory $category;

    #[ORM\Embedded(class: AnimalCount::class)]
    private AnimalCount $currentStock;

    public function __construct()
    {
        $this->dietItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnimalName(): string
    {
        return $this->animalName;
    }

    public function setAnimalName(string $animalName): self
    {
        $this->animalName = $animalName;
        return $this;
    }

    public function getScientificName(): string
    {
        return $this->scientificName;
    }

    public function setScientificName(string $scientificName): self
    {
        $this->scientificName = $scientificName;
        return $this;
    }

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
            $dietItem->setAnimal($this);
        }
        return $this;
    }

    public function removeDietItem(DietItem $dietItem): self
    {
        if ($this->dietItems->removeElement($dietItem)) {
            // set the owning side to null (unless already changed)
            if ($dietItem->getAnimal() === $this) {
                $dietItem->setAnimal(null);
            }
        }
        return $this;
    }

    public function getCategory(): AnimalCategory
    {
        return $this->category;
    }

    public function setCategory(AnimalCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getCurrentStock(): AnimalCount
    {
        return $this->currentStock;
    }

    public function setCurrentStock(AnimalCount $currentStock): self
    {
        $this->currentStock = $currentStock;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'animalName' => $this->animalName,
            'scientificName' => $this->scientificName,
            'dietItems' => $this->dietItems->toArray(),
            'category' => $this->category,
            'currentStock' => $this->currentStock
        ];
    }
}