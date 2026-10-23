<?php

namespace App\Entity;

use App\Repository\MechanicRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Meccanico dell'officina. Non è un utente: lavora dal tablet condiviso, dove si sceglie
 * toccando il proprio nome (con PIN facoltativo).
 */
#[ORM\Entity(repositoryClass: MechanicRepository::class)]
class Mechanic
{
    // colori dell'avatar, uno per meccanico (planning, bacheca, tablet)
    public const COLORS = [
        'red' => 'Rosso',
        'black' => 'Nero',
        'grey' => 'Grigio',
        'blue' => 'Blu',
        'green' => 'Verde',
        'orange' => 'Arancio',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $surname = null;

    #[ORM\Column(length: 10, options: ['default' => 'red'])]
    private string $color = 'red';

    // ore pianificabili al giorno (carico del planning)
    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, options: ['default' => 8])]
    private string $dailyHours = '8';

    // hash del PIN a 4 cifre; null = accesso dal tablet con un tocco
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pinHash = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sort = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getSurname(): ?string { return $this->surname; }
    public function setSurname(?string $surname): static { $this->surname = $surname; return $this; }

    public function getFullName(): string
    {
        return trim($this->name.' '.$this->surname);
    }

    /** "AN" per Antonio, "RB" per Riccardo Bianchi */
    public function getInitials(): string
    {
        $initials = $this->surname
            ? mb_substr($this->name, 0, 1).mb_substr($this->surname, 0, 1)
            : mb_substr((string) $this->name, 0, 2);

        return mb_strtoupper($initials);
    }

    public function getColor(): string { return $this->color; }
    public function setColor(string $color): static { $this->color = $color; return $this; }

    public function getDailyHours(): float { return (float) $this->dailyHours; }
    public function setDailyHours(float|string|null $hours): static { $this->dailyHours = (string) ($hours ?? 8); return $this; }

    public function hasPin(): bool { return $this->pinHash !== null; }

    public function setPin(?string $pin): static
    {
        $this->pinHash = $pin === null || $pin === '' ? null : password_hash($pin, PASSWORD_DEFAULT);

        return $this;
    }

    public function checkPin(?string $pin): bool
    {
        return $this->pinHash === null || ($pin !== null && password_verify($pin, $this->pinHash));
    }

    public function getSort(): int { return $this->sort; }
    public function setSort(int $sort): static { $this->sort = $sort; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
