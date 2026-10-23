<?php

namespace App\Entity;

use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Foto o documento di un ordine di lavoro: foto all'accettazione, foto scattate dal tablet
 * su una lavorazione, preventivi e fatture.
 * Il file è salvato fuori dalla public dir e servito solo tramite AttachmentController.
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $storedName;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $size = 0;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?WorkOrder $workOrder = null;

    // foto scattata dal tablet durante questa lavorazione
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Task $task = null;

    #[ORM\ManyToOne]
    private ?Mechanic $mechanic = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $uploadedAt;

    #[ORM\ManyToOne]
    private ?User $uploadedBy = null;

    public function __construct(string $originalName, string $storedName)
    {
        $this->originalName = $originalName;
        $this->storedName = $storedName;
        $this->uploadedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStoredName(): string { return $this->storedName; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getSize(): int { return $this->size; }
    public function setSize(int $size): static { $this->size = $size; return $this; }

    public function getWorkOrder(): ?WorkOrder { return $this->workOrder; }
    public function setWorkOrder(?WorkOrder $workOrder): static { $this->workOrder = $workOrder; return $this; }

    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): static { $this->task = $task; return $this; }

    public function getMechanic(): ?Mechanic { return $this->mechanic; }
    public function setMechanic(?Mechanic $mechanic): static { $this->mechanic = $mechanic; return $this; }

    /** Chi l'ha caricato: il meccanico dal tablet, altrimenti l'utente */
    public function getAuthorName(): ?string
    {
        return $this->mechanic?->getName() ?? $this->uploadedBy?->getFullName();
    }

    public function getUploadedAt(): \DateTimeInterface { return $this->uploadedAt; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $user): static { $this->uploadedBy = $user; return $this; }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }
}
