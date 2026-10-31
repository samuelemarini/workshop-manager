<?php

namespace App\Entity;

use App\Repository\PartRepository;
use App\Util\Trans;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ricambio o materiale usato su un ordine di lavoro (eventualmente legato a una lavorazione).
 */
#[ORM\Entity(repositoryClass: PartRepository::class)]
class Part
{
    public const STOCK = 'STOCK';
    public const TO_ORDER = 'TO_ORDER';
    public const ORDERED = 'ORDERED';
    public const ARRIVED = 'ARRIVED';

    public const STATUSES = [
        self::STOCK => 'Magazzino',
        self::TO_ORDER => 'Da ordinare',
        self::ORDERED => 'Ordinato',
        self::ARRIVED => 'Arrivato',
    ];

    public const UNITS = ['pz', 'l', 'set', 'kg', 'g', 'm'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkOrder $workOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Task $task = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $quantity = '1';

    #[ORM\Column(length: 5, options: ['default' => 'pz'])]
    private string $unit = 'pz';

    // prezzo unitario al cliente, IVA esclusa
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(length: 10, options: ['default' => 'STOCK'])]
    private string $status = self::STOCK;

    // consegna prevista dal fornitore (ricambi ordinati)
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $expectedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $supplier = null;

    // il meccanico che l'ha aggiunto dal tablet
    #[ORM\ManyToOne]
    private ?Mechanic $addedBy = null;

    public function getId(): ?int { return $this->id; }

    public function getWorkOrder(): ?WorkOrder { return $this->workOrder; }
    public function setWorkOrder(?WorkOrder $workOrder): static { $this->workOrder = $workOrder; return $this; }

    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): static { $this->task = $task; return $this; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getQuantity(): float { return (float) $this->quantity; }
    public function setQuantity(float|string|null $quantity): static { $this->quantity = (string) max(0, (float) $quantity); return $this; }

    public function getUnit(): string { return $this->unit; }
    public function setUnit(?string $unit): static { $this->unit = $unit ?: 'pz'; return $this; }

    public function getUnitPrice(): ?float { return $this->unitPrice === null ? null : (float) $this->unitPrice; }
    public function setUnitPrice(float|string|null $price): static { $this->unitPrice = $price === null || $price === '' ? null : (string) $price; return $this; }

    public function getTotal(): float
    {
        return round($this->getQuantity() * (float) $this->unitPrice, 2);
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    /** Tradotta nella lingua dell'utente */
    public function getStatusLabel(): string { return Trans::label(self::STATUSES[$this->status] ?? $this->status); }

    /** Il pezzo non è ancora in officina */
    public function isMissing(): bool
    {
        return in_array($this->status, [self::TO_ORDER, self::ORDERED], true);
    }

    public function getExpectedAt(): ?\DateTimeInterface { return $this->expectedAt; }
    public function setExpectedAt(?\DateTimeInterface $date): static { $this->expectedAt = $date; return $this; }

    public function getSupplier(): ?string { return $this->supplier; }
    public function setSupplier(?string $supplier): static { $this->supplier = $supplier; return $this; }

    public function getAddedBy(): ?Mechanic { return $this->addedBy; }
    public function setAddedBy(?Mechanic $mechanic): static { $this->addedBy = $mechanic; return $this; }
}
