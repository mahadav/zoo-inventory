<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AnimalCount implements \JsonSerializable
{
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $male = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $female = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $underAge = null;

    #[ORM\Column(type: 'integer')]
    private int $total;


    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastCountDate = null;

    public function getMale(): ?int
    {
        return $this->male;
    }

    public function setMale(?int $male): self
    {
        $this->male = $male;
        return $this;
    }

    public function getFemale(): ?int
    {
        return $this->female;
    }

    public function setFemale(?int $female): self
    {
        $this->female = $female;
        return $this;
    }

    public function getUnderAge(): ?int
    {
        return $this->underAge;
    }

    public function setUnderAge(?int $underAge): self
    {
        $this->underAge = $underAge;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getLastCountDate(): ?\DateTimeInterface
    {
        return $this->lastCountDate;
    }

    public function setLastCountDate(?\DateTimeInterface $lastCountDate): self
    {
        $this->lastCountDate = $lastCountDate;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'male' => $this->male,
            'female' => $this->female,
            'underAge' => $this->underAge,
            'total' => $this->total,
            'lastCountDate' => $this->lastCountDate ? $this->lastCountDate->format('Y-m-d') : null
        ];
    }
}