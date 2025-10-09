<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Entity]
class AnimalCategory implements JsonSerializable
{
    // WeekDays constants


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fastingDay = null;



    public function getId(): ?int
    {
        return $this->id;
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

    public function getFastingDay(): ?int
    {
        return $this->fastingDay;
    }

    public function setFastingDay(?int $fastingDay): self
    {
        $this->fastingDay = $fastingDay;
        return $this;
    }


    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fastingDay' => $this->fastingDay,
        ];
    }
}