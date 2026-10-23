<?php

namespace App\Entity;

use App\Repository\TaskTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lavorazione tipo (tagliando, cambio gomme, pastiglie...): in accettazione precompila
 * descrizione, tempo stimato e controlli da spuntare sul tablet.
 */
#[ORM\Entity(repositoryClass: TaskTemplateRepository::class)]
class TaskTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => 60])]
    #[Assert\PositiveOrZero]
    private int $estimatedMinutes = 60;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $sort = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getEstimatedMinutes(): int { return $this->estimatedMinutes; }
    public function setEstimatedMinutes(?int $minutes): static { $this->estimatedMinutes = (int) $minutes; return $this; }

    /** @return string[] */
    public function getChecklist(): array { return $this->checklist; }

    /** @param string[] $checklist */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = array_values(array_filter(array_map('trim', $checklist), 'strlen'));

        return $this;
    }

    /** Una voce per riga: è così che si modifica nelle impostazioni */
    public function getChecklistText(): string { return implode("\n", $this->checklist); }
    public function setChecklistText(?string $text): static { return $this->setChecklist(preg_split('/\R/', (string) $text)); }

    public function getSort(): int { return $this->sort; }
    public function setSort(int $sort): static { $this->sort = $sort; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
