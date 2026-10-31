<?php

namespace App\Entity;

use App\Repository\TimeEntryRepository;
use App\Util\Trans;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tratto di lavoro di un meccanico su una lavorazione: si apre con "Avvia"/"Riprendi" e si chiude
 * con "Pausa" (motivo) o "Termina". Un meccanico ha al massimo una registrazione aperta.
 */
#[ORM\Entity(repositoryClass: TimeEntryRepository::class)]
#[ORM\Index(fields: ['startedAt'])]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Task $task = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Mechanic $mechanic = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    // motivo della pausa, "Completata" o "Passato a un altro lavoro"
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $endReason = null;

    public function __construct(Mechanic $mechanic, ?\DateTimeInterface $startedAt = null)
    {
        $this->mechanic = $mechanic;
        $this->startedAt = $startedAt ?? new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): static { $this->task = $task; return $this; }

    public function getMechanic(): ?Mechanic { return $this->mechanic; }

    public function getStartedAt(): \DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(\DateTimeInterface $date): static { $this->startedAt = $date; return $this; }

    public function getEndedAt(): ?\DateTimeInterface { return $this->endedAt; }
    public function setEndedAt(?\DateTimeInterface $date): static { $this->endedAt = $date; return $this; }

    public function getEndReason(): ?string { return $this->endReason; }
    /** Motivo di chiusura tradotto, per lo schermo (getEndReason() resta la chiave italiana, es. "Completata") */
    public function getEndReasonLabel(): ?string { return $this->endReason === null ? null : Trans::label($this->endReason); }
    public function setEndReason(?string $reason): static { $this->endReason = $reason; return $this; }

    public function close(string $reason, ?\DateTimeInterface $at = null): void
    {
        $this->endedAt = $at ?? new \DateTime();
        $this->endReason = $reason;
    }

    public function getSeconds(?\DateTimeInterface $now = null): int
    {
        $end = $this->endedAt ?? $now ?? new \DateTime();

        return max(0, $end->getTimestamp() - $this->startedAt->getTimestamp());
    }
}
