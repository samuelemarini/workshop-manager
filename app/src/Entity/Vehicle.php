<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use App\Util\Trans;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Veicolo di un cliente. La targa si salva senza spazi e in maiuscolo.
 */
#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[UniqueEntity(fields: ['plate'], message: 'Targa già presente')]
class Vehicle
{
    public const FUELS = ['Benzina', 'Diesel', 'GPL', 'Metano', 'Ibrida', 'Elettrica'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 15, unique: true)]
    #[Assert\NotBlank]
    private ?string $plate = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private ?string $make = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $model = null;

    // motorizzazione: "1.2 69 CV", "2.0 TDI 150 CV"
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $fuel = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1950, max: 2100)]
    private ?int $year = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $vin = null;

    // ultimo chilometraggio letto (accettazione o tablet)
    #[ORM\Column(nullable: true)]
    private ?int $km = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $revisionDue = null;

    #[ORM\ManyToOne]
    private ?Customer $owner = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    #[ORM\OneToMany(targetEntity: WorkOrder::class, mappedBy: 'vehicle')]
    #[ORM\OrderBy(['acceptedAt' => 'DESC'])]
    private Collection $workOrders;

    public function __construct()
    {
        $this->workOrders = new ArrayCollection();
    }

    public static function normalizePlate(?string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $plate));
    }

    public function getId(): ?int { return $this->id; }

    public function getPlate(): ?string { return $this->plate; }
    public function setPlate(?string $plate): static { $this->plate = self::normalizePlate($plate) ?: null; return $this; }

    /** Targhe italiane AA123BB → "AA 123 BB"; le altre come sono */
    public function getPlateLabel(): string
    {
        return preg_match('/^([A-Z]{2})(\d{3})([A-Z]{2})$/', (string) $this->plate, $m) ? "$m[1] $m[2] $m[3]" : (string) $this->plate;
    }

    public function getMake(): ?string { return $this->make; }
    public function setMake(?string $make): static { $this->make = $make; return $this; }

    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): static { $this->model = $model; return $this; }

    public function getVersion(): ?string { return $this->version; }
    public function setVersion(?string $version): static { $this->version = $version; return $this; }

    /** "Fiat Panda" */
    public function getName(): string
    {
        return trim($this->make.' '.$this->model);
    }

    public function getFuel(): ?string { return $this->fuel; }
    /** Da stampare: alimentazione tradotta (getFuel() resta il valore del form) */
    public function getFuelLabel(): ?string { return $this->fuel === null ? null : Trans::label($this->fuel); }
    public function setFuel(?string $fuel): static { $this->fuel = $fuel; return $this; }

    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $year): static { $this->year = $year; return $this; }

    public function getVin(): ?string { return $this->vin; }
    public function setVin(?string $vin): static { $this->vin = $vin ? strtoupper(trim($vin)) : null; return $this; }

    public function getKm(): ?int { return $this->km; }
    public function setKm(?int $km): static { $this->km = $km; return $this; }

    public function getRevisionDue(): ?\DateTimeInterface { return $this->revisionDue; }
    public function setRevisionDue(?\DateTimeInterface $date): static { $this->revisionDue = $date; return $this; }

    public function getOwner(): ?Customer { return $this->owner; }
    public function setOwner(?Customer $owner): static { $this->owner = $owner; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    /** @return Collection<int, WorkOrder> */
    public function getWorkOrders(): Collection { return $this->workOrders; }

    public function __toString(): string
    {
        return $this->getPlateLabel().' · '.$this->getName();
    }
}
