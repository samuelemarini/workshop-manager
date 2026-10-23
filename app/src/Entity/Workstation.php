<?php

namespace App\Entity;

use App\Repository\WorkstationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Postazione di lavoro: ponte sollevatore, smontagomme, area diagnosi.
 */
#[ORM\Entity(repositoryClass: WorkstationRepository::class)]
class Workstation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sort = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getSort(): int { return $this->sort; }
    public function setSort(int $sort): static { $this->sort = $sort; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    /** "P1" per "Ponte 1", "SM" per "Smontagomme" */
    public function getShortName(): string
    {
        if (preg_match('/(\d+)\s*$/', (string) $this->name, $m)) {
            return mb_strtoupper(mb_substr($this->name, 0, 1)).$m[1];
        }

        return mb_strtoupper(mb_substr((string) $this->name, 0, 2));
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
