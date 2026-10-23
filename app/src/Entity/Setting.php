<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Parametri dell'officina modificabili dalle impostazioni (tariffa oraria, orari).
 * Le chiavi e i valori di default stanno in SettingRepository::DEFAULTS.
 */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(length: 40)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $value;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string { return $this->name; }

    public function getValue(): string { return $this->value; }
    public function setValue(string $value): static { $this->value = $value; return $this; }
}
