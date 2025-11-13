<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AnimalCountGroup implements \JsonSerializable
{
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $male = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $female = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $underage = 0;

    public function getMale(): int { return $this->male; }
    public function setMale(int $value): self { $this->male = $value; return $this; }

    public function getFemale(): int { return $this->female; }
    public function setFemale(int $value): self { $this->female = $value; return $this; }

    public function getUnderage(): int { return $this->underage; }
    public function setUnderage(int $value): self { $this->underage = $value; return $this; }

    public function getTotal(): int { return $this->male + $this->female + $this->underage; }

    // Feed count excludes underage
    public function getFeedEligible(): int { return $this->male + $this->female; }


    public function fill(array $data): void
    {
        if (isset($data['male'])) {
            $this->setMale((int) $data['male']);
        }
        if (isset($data['female'])) {
            $this->setFemale((int) $data['female']);
        }
        if (isset($data['underAge']) || isset($data['underage'])) {
            // Support both 'underAge' and 'underage' keys from JSON
            $this->setUnderage((int) ($data['underAge'] ?? $data['underage']));
        }
    }
    public function jsonSerialize(): array
    {
        return [
            'male' => $this->male,
            'female' => $this->female,
            'underage' => $this->underage,
            'total' => $this->getTotal(),
            'feedEligible' => $this->getFeedEligible(),
        ];
    }
}
