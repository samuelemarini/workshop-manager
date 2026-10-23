<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Storico delle modifiche e dei cambi di stato degli ordini di lavoro.
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Index(fields: ['subjectType', 'subjectId'])]
class ActivityLog
{
    public const SUBJECT_ORDER = 'ORDER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    #[ORM\Column(length: 255)]
    private string $message;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    // azioni dal tablet: il meccanico che ha toccato il pulsante
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Mechanic $mechanic = null;

    public function __construct(string $subjectType, int $subjectId, string $message, ?User $user, ?Mechanic $mechanic = null)
    {
        $this->mechanic = $mechanic;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->message = mb_substr($message, 0, 255);
        $this->user = $user;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubjectType(): string { return $this->subjectType; }
    public function getSubjectId(): int { return $this->subjectId; }
    public function getMessage(): string { return $this->message; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUser(): ?User { return $this->user; }
    public function getMechanic(): ?Mechanic { return $this->mechanic; }

    public function getAuthorName(): ?string
    {
        return $this->mechanic?->getName() ?? $this->user?->getFullName();
    }
}
