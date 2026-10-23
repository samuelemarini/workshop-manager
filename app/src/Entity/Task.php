<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lavorazione di un ordine: assegnata a un meccanico e a una postazione, pianificata a un'ora.
 * Il tempo effettivo è la somma delle TimeEntry registrate dal tablet.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Index(fields: ['plannedStart'])]
class Task
{
    public const TODO = 'TODO';
    public const RUNNING = 'RUNNING';
    public const PAUSED = 'PAUSED';
    public const DONE = 'DONE';
    // segnalata dal meccanico, in attesa dell'OK del cliente
    public const PROPOSED = 'PROPOSED';
    public const REJECTED = 'REJECTED';

    public const STATUSES = [
        self::TODO => 'Da iniziare',
        self::RUNNING => 'In corso',
        self::PAUSED => 'In pausa',
        self::DONE => 'Completata',
        self::PROPOSED => 'Da autorizzare',
        self::REJECTED => 'Rifiutata',
    ];

    // motivi di pausa proposti sul tablet; quelli in WAITING_REASONS mettono l'ordine "in attesa"
    public const PAUSE_REASONS = [
        'Attesa ricambi' => 'fa-box',
        'Pausa pranzo' => 'fa-mug-hot',
        'Urgenza su un’altra auto' => 'fa-triangle-exclamation',
        'Attesa OK del cliente' => 'fa-phone',
        'Ponte o attrezzo occupato' => 'fa-screwdriver-wrench',
        'Fine giornata' => 'fa-moon',
        'Altro' => 'fa-ellipsis',
    ];
    public const WAITING_REASONS = ['Attesa ricambi', 'Attesa OK del cliente'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkOrder $workOrder = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    private ?Mechanic $mechanic = null;

    #[ORM\ManyToOne]
    private ?Workstation $workstation = null;

    #[ORM\Column(options: ['default' => 60])]
    #[Assert\PositiveOrZero]
    private int $estimatedMinutes = 60;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $plannedStart = null;

    #[ORM\Column(length: 10)]
    private string $status = self::TODO;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pauseReason = null;

    /** @var array<int, array{label: string, done: bool}> */
    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    // note del meccanico per l'ufficio
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    private ?Mechanic $proposedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $proposedAt = null;

    // segnalazione di un problema di sicurezza (freni, gomme...)
    #[ORM\Column(options: ['default' => false])]
    private bool $urgent = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sort = 0;

    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'task', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startedAt' => 'ASC'])]
    private Collection $timeEntries;

    public function __construct()
    {
        $this->timeEntries = new ArrayCollection();
    }

    public static function fromTemplate(TaskTemplate $template): self
    {
        return (new self())
            ->setTitle($template->getName())
            ->setEstimatedMinutes($template->getEstimatedMinutes())
            ->setChecklistLabels($template->getChecklist());
    }

    public function getId(): ?int { return $this->id; }

    public function getWorkOrder(): ?WorkOrder { return $this->workOrder; }
    public function setWorkOrder(?WorkOrder $workOrder): static { $this->workOrder = $workOrder; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMechanic(): ?Mechanic { return $this->mechanic; }
    public function setMechanic(?Mechanic $mechanic): static { $this->mechanic = $mechanic; return $this; }

    public function getWorkstation(): ?Workstation { return $this->workstation; }
    public function setWorkstation(?Workstation $workstation): static { $this->workstation = $workstation; return $this; }

    public function getEstimatedMinutes(): int { return $this->estimatedMinutes; }
    public function setEstimatedMinutes(?int $minutes): static { $this->estimatedMinutes = (int) $minutes; return $this; }

    public function getPlannedStart(): ?\DateTimeInterface { return $this->plannedStart; }
    public function setPlannedStart(?\DateTimeInterface $date): static { $this->plannedStart = $date; return $this; }

    public function getPlannedEnd(): ?\DateTimeInterface
    {
        return $this->plannedStart ? \DateTime::createFromInterface($this->plannedStart)->modify('+'.max(15, $this->estimatedMinutes).' minutes') : null;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStatusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    /** Conta per l'ordine: avanzamento, stime, chiusura */
    public function counts(): bool
    {
        return !in_array($this->status, [self::PROPOSED, self::REJECTED], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::RUNNING, self::PAUSED], true);
    }

    public function getPauseReason(): ?string { return $this->pauseReason; }
    public function setPauseReason(?string $reason): static { $this->pauseReason = $reason; return $this; }

    /** @return array<int, array{label: string, done: bool}> */
    public function getChecklist(): array { return $this->checklist; }

    /** @param string[] $labels */
    public function setChecklistLabels(array $labels): static
    {
        $this->checklist = array_map(fn (string $l) => ['label' => $l, 'done' => false], array_values($labels));

        return $this;
    }

    /** Una voce per riga (modifica dall'ufficio): le voci già spuntate restano spuntate */
    public function getChecklistText(): string
    {
        return implode("\n", array_column($this->checklist, 'label'));
    }

    public function setChecklistText(?string $text): static
    {
        $done = array_column($this->checklist, 'done', 'label');
        $labels = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), 'strlen'));
        $this->checklist = array_map(fn (string $l) => ['label' => $l, 'done' => $done[$l] ?? false], $labels);

        return $this;
    }

    public function toggleCheck(int $index): bool
    {
        if (!isset($this->checklist[$index])) {
            throw new \OutOfRangeException();
        }
        $this->checklist[$index]['done'] = !$this->checklist[$index]['done'];

        return $this->checklist[$index]['done'];
    }

    public function getChecklistDone(): int
    {
        return count(array_filter($this->checklist, fn (array $c) => $c['done']));
    }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getProposedBy(): ?Mechanic { return $this->proposedBy; }
    public function setProposedBy(?Mechanic $mechanic): static { $this->proposedBy = $mechanic; return $this; }

    public function getProposedAt(): ?\DateTimeInterface { return $this->proposedAt; }
    public function setProposedAt(?\DateTimeInterface $date): static { $this->proposedAt = $date; return $this; }

    public function isUrgent(): bool { return $this->urgent; }
    public function setUrgent(bool $urgent): static { $this->urgent = $urgent; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $date): static { $this->completedAt = $date; return $this; }

    public function getSort(): int { return $this->sort; }
    public function setSort(int $sort): static { $this->sort = $sort; return $this; }

    /** @return Collection<int, TimeEntry> */
    public function getTimeEntries(): Collection { return $this->timeEntries; }

    public function addTimeEntry(TimeEntry $entry): static
    {
        if (!$this->timeEntries->contains($entry)) {
            $entry->setTask($this);
            $this->timeEntries->add($entry);
        }

        return $this;
    }

    public function getOpenEntry(): ?TimeEntry
    {
        foreach ($this->timeEntries as $entry) {
            if ($entry->getEndedAt() === null) {
                return $entry;
            }
        }

        return null;
    }

    public function getWorkedSeconds(?\DateTimeInterface $now = null): int
    {
        return array_sum(array_map(fn (TimeEntry $e) => $e->getSeconds($now), $this->timeEntries->toArray()));
    }

    /** Inizio e fine reali (prima e ultima registrazione), per il planning */
    public function getFirstStart(): ?\DateTimeInterface
    {
        return $this->timeEntries->first() ? $this->timeEntries->first()->getStartedAt() : null;
    }

    public function getLastEnd(): ?\DateTimeInterface
    {
        $last = $this->timeEntries->last();

        return $last ? ($last->getEndedAt() ?? new \DateTime()) : null;
    }

    public function isOverEstimate(?\DateTimeInterface $now = null): bool
    {
        return $this->estimatedMinutes > 0 && $this->getWorkedSeconds($now) > $this->estimatedMinutes * 60;
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }
}
