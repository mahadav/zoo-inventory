<?php


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DietItem implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FeedItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FeedItem $feedItem;

    #[ORM\Column(type: 'float')]
    private float $quantity;

    #[ORM\Column(type: 'integer')]
    private int $adultCount;

    #[ORM\ManyToOne(targetEntity: Animal::class, inversedBy: 'dietItems')]
    private ?Animal $animal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeedItem(): FeedItem
    {
        return $this->feedItem;
    }

    public function setFeedItem(FeedItem $feedItem): self
    {
        $this->feedItem = $feedItem;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getAnimal(): ?Animal
    {
        return $this->animal;
    }

    public function setAnimal(?Animal $animal): self
    {
        $this->animal = $animal;
        return $this;
    }

    public function getAdultCount(): int
    {
        return $this->adultCount;
    }

    public function setAdultCount(int $adultCount): void
    {
        $this->adultCount = $adultCount;
    }



    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'feedItem' => $this->feedItem,
            'quantity' => $this->quantity
        ];
    }
}