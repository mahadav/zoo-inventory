<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FeedItem implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $estimatedPrice = 0;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: FeedUnit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FeedUnit $unit;

    #[ORM\ManyToOne(targetEntity: FeedCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FeedCategory $category;


    #[ORM\ManyToOne(targetEntity: AnimalCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AnimalCategory $animalCategory;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEstimatedPrice(): int
    {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice(int $estimatedPrice): self
    {
        $this->estimatedPrice = $estimatedPrice;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getUnit(): FeedUnit
    {
        return $this->unit;
    }

    public function setUnit(FeedUnit $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getCategory(): FeedCategory
    {
        return $this->category;
    }

    public function setCategory(FeedCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getAnimalCategory(): AnimalCategory
    {
        return $this->animalCategory;
    }

    public function setAnimalCategory(AnimalCategory $animalCategory): self
    {
        $this->animalCategory = $animalCategory;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'estimatedPrice' => $this->estimatedPrice,
            'name' => $this->name,
            'unit' => $this->unit,
            'category' => $this->category
        ];
    }
}