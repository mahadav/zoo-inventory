<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'diet_item')]
class DietItem implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FeedItem::class, inversedBy: 'dietItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private FeedItem $feedItem;

    #[ORM\Column(type: 'float')]
    private float $quantity = 0.0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $adultCount = 0;

    #[ORM\ManyToOne(targetEntity: AnimalSpecies::class, inversedBy: 'dietItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?AnimalSpecies $species = null;

    public function __construct()
    {
        $this->quantity = 0.0;
        $this->adultCount = 0;
    }

    // ---------- Getters & Setters ----------
    public function getId(): ?int { return $this->id; }

    public function getFeedItem(): FeedItem { return $this->feedItem; }
    public function setFeedItem(FeedItem $feedItem): self { $this->feedItem = $feedItem; return $this; }

    public function getQuantity(): float { return $this->quantity; }
    public function setQuantity(float $quantity): self { $this->quantity = $quantity; return $this; }

    public function getAdultCount(): int { return $this->adultCount; }
    public function setAdultCount(int $adultCount): self { $this->adultCount = $adultCount; return $this; }

    public function getSpecies(): ?AnimalSpecies { return $this->species; }
    public function setSpecies(?AnimalSpecies $species): self { $this->species = $species; return $this; }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'species' => $this->species ? $this->species->getCommonName() : null,
            'feedItem' => $this->feedItem->jsonSerialize(),
            'quantity' => $this->quantity,
            'adultCount' => $this->adultCount,
        ];
    }
}
