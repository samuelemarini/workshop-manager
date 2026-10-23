<?php

namespace App\Entity;

use App\Repository\WorkOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ordine di lavoro: un veicolo entrato in officina con le sue lavorazioni.
 * Lo stato si muove da solo con le lavorazioni (WorkflowManager) e si può cambiare a mano dalla bacheca.
 */
#[ORM\Entity(repositoryClass: WorkOrderRepository::class)]
#[ORM\UniqueConstraint(fields: ['year', 'number'])]
class WorkOrder
{
    public const ACCEPTED = 'ACCEPTED';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const WAITING = 'WAITING';
    public const READY = 'READY';
    public const DELIVERED = 'DELIVERED';
    public const CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::ACCEPTED => 'Da iniziare',
        self::IN_PROGRESS => 'In lavorazione',
        self::WAITING => 'In attesa',
        self::READY => 'Pronta',
        self::DELIVERED => 'Consegnata',
        self::CANCELLED => 'Annullato',
    ];

    public const FUEL_LEVELS = ['Riserva', '1/4', '1/2', '3/4', 'Pieno'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $year;

    // progressivo nell'anno: OL-0187
    #[ORM\Column]
    private int $number = 0;

    #[ORM\Column(length: 15)]
    private string $status = self::ACCEPTED;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $waitingReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Scegli il cliente', groups: ['complete'])]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(inversedBy: 'workOrders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Scegli il veicolo', groups: ['complete'])]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $acceptedAt;

    // consegna promessa al cliente
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $promisedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readyAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $customerNotifiedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $kmIn = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $fuelLevel = null;

    // "cosa dice il cliente", si vede anche sul tablet
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerRequest = null;

    // danni già presenti, oggetti lasciati in auto
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $intakeNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $quoteAmount = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $courtesyCar = false;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'workOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sort' => 'ASC', 'id' => 'ASC'])]
    private Collection $tasks;

    #[ORM\OneToMany(targetEntity: Part::class, mappedBy: 'workOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $parts;

    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'workOrder')]
    #[ORM\OrderBy(['uploadedAt' => 'ASC'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->acceptedAt = new \DateTime();
        $this->year = (int) date('Y');
        $this->tasks = new ArrayCollection();
        $this->parts = new ArrayCollection();
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getYear(): int { return $this->year; }
    public function setYear(int $year): static { $this->year = $year; return $this; }

    public function getNumber(): int { return $this->number; }
    public function setNumber(int $number): static { $this->number = $number; return $this; }

    public function getCode(): string
    {
        return 'OL-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getStatusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function isOpen(): bool
    {
        return !in_array($this->status, [self::DELIVERED, self::CANCELLED], true);
    }

    public function getWaitingReason(): ?string { return $this->waitingReason; }
    public function setWaitingReason(?string $reason): static { $this->waitingReason = $reason; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): static { $this->customer = $customer; return $this; }

    public function getVehicle(): ?Vehicle { return $this->vehicle; }
    public function setVehicle(?Vehicle $vehicle): static { $this->vehicle = $vehicle; return $this; }

    public function getAcceptedAt(): \DateTimeInterface { return $this->acceptedAt; }
    public function setAcceptedAt(\DateTimeInterface $date): static { $this->acceptedAt = $date; return $this; }

    public function getPromisedAt(): ?\DateTimeInterface { return $this->promisedAt; }
    public function setPromisedAt(?\DateTimeInterface $date): static { $this->promisedAt = $date; return $this; }

    public function getReadyAt(): ?\DateTimeInterface { return $this->readyAt; }
    public function setReadyAt(?\DateTimeInterface $date): static { $this->readyAt = $date; return $this; }

    public function getCustomerNotifiedAt(): ?\DateTimeInterface { return $this->customerNotifiedAt; }
    public function setCustomerNotifiedAt(?\DateTimeInterface $date): static { $this->customerNotifiedAt = $date; return $this; }

    public function getDeliveredAt(): ?\DateTimeInterface { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeInterface $date): static { $this->deliveredAt = $date; return $this; }

    public function getKmIn(): ?int { return $this->kmIn; }
    public function setKmIn(?int $km): static { $this->kmIn = $km; return $this; }

    public function getFuelLevel(): ?string { return $this->fuelLevel; }
    public function setFuelLevel(?string $level): static { $this->fuelLevel = $level; return $this; }

    public function getCustomerRequest(): ?string { return $this->customerRequest; }
    public function setCustomerRequest(?string $text): static { $this->customerRequest = $text; return $this; }

    public function getIntakeNotes(): ?string { return $this->intakeNotes; }
    public function setIntakeNotes(?string $text): static { $this->intakeNotes = $text; return $this; }

    public function getInternalNotes(): ?string { return $this->internalNotes; }
    public function setInternalNotes(?string $text): static { $this->internalNotes = $text; return $this; }

    public function getQuoteAmount(): ?float { return $this->quoteAmount === null ? null : (float) $this->quoteAmount; }
    public function setQuoteAmount(float|string|null $amount): static { $this->quoteAmount = $amount === null || $amount === '' ? null : (string) $amount; return $this; }

    public function isCourtesyCar(): bool { return $this->courtesyCar; }
    public function setCourtesyCar(bool $courtesyCar): static { $this->courtesyCar = $courtesyCar; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $user): static { $this->createdBy = $user; return $this; }

    /** @return Collection<int, Task> */
    public function getTasks(): Collection { return $this->tasks; }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $task->setWorkOrder($this);
            $task->setSort(count($this->tasks) + 1);
            $this->tasks->add($task);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        $this->tasks->removeElement($task);

        return $this;
    }

    /** @return Task[] lavorazioni che contano (non proposte in attesa di OK, non rifiutate) */
    public function getActiveTasks(): array
    {
        return array_values(array_filter($this->tasks->toArray(), fn (Task $t) => $t->counts()));
    }

    /** @return Task[] */
    public function getProposedTasks(): array
    {
        return array_values(array_filter($this->tasks->toArray(), fn (Task $t) => $t->getStatus() === Task::PROPOSED));
    }

    public function getDoneCount(): int
    {
        return count(array_filter($this->getActiveTasks(), fn (Task $t) => $t->getStatus() === Task::DONE));
    }

    public function getProgressPct(): int
    {
        $total = count($this->getActiveTasks());

        return $total ? (int) round($this->getDoneCount() / $total * 100) : 0;
    }

    public function getEstimatedMinutes(): int
    {
        return array_sum(array_map(fn (Task $t) => $t->getEstimatedMinutes(), $this->getActiveTasks()));
    }

    public function getWorkedSeconds(?\DateTimeInterface $now = null): int
    {
        return array_sum(array_map(fn (Task $t) => $t->getWorkedSeconds($now), $this->tasks->toArray()));
    }

    public function getRunningTask(): ?Task
    {
        foreach ($this->tasks as $task) {
            if ($task->getStatus() === Task::RUNNING) {
                return $task;
            }
        }

        return null;
    }

    /** @return Mechanic[] meccanici assegnati alle lavorazioni, senza doppioni */
    public function getMechanics(): array
    {
        $list = [];
        foreach ($this->getActiveTasks() as $task) {
            if ($task->getMechanic()) {
                $list[$task->getMechanic()->getId()] = $task->getMechanic();
            }
        }

        return array_values($list);
    }

    public function hasUnassignedTasks(): bool
    {
        foreach ($this->getActiveTasks() as $task) {
            if (!$task->getMechanic() && $task->getStatus() !== Task::DONE) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Part> */
    public function getParts(): Collection { return $this->parts; }

    public function addPart(Part $part): static
    {
        if (!$this->parts->contains($part)) {
            $part->setWorkOrder($this);
            $this->parts->add($part);
        }

        return $this;
    }

    public function removePart(Part $part): static
    {
        $this->parts->removeElement($part);

        return $this;
    }

    public function getPartsTotal(): float
    {
        return array_sum(array_map(fn (Part $p) => $p->getTotal(), $this->parts->toArray()));
    }

    /** @return Collection<int, Attachment> */
    public function getAttachments(): Collection { return $this->attachments; }

    /** Consegna promessa già passata e auto non ancora pronta */
    public function isLate(?\DateTimeInterface $now = null): bool
    {
        return $this->promisedAt !== null
            && in_array($this->status, [self::ACCEPTED, self::IN_PROGRESS, self::WAITING], true)
            && $this->promisedAt < ($now ?? new \DateTime());
    }

    public function isPromisedOn(\DateTimeInterface $day): bool
    {
        return $this->promisedAt !== null && $this->promisedAt->format('Y-m-d') === $day->format('Y-m-d');
    }

    public function __toString(): string
    {
        return $this->getCode();
    }
}
