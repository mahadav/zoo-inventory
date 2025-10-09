<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FeedInventory implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FeedItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    private FeedItem $feedItem;

    #[ORM\Column(type: 'float')]
    private float $consumptionPerDay = 0;

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

    public function getConsumptionPerDay(): float
    {
        return $this->consumptionPerDay;
    }

    public function setConsumptionPerDay(float $consumptionPerDay): self
    {
        $this->consumptionPerDay = $consumptionPerDay;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'feedItem' => $this->feedItem,
            'consumptionPerDay' => $this->consumptionPerDay
        ];
    }
}