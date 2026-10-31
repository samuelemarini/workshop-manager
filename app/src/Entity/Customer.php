<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use App\Util\Trans;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cliente dell'officina: privato o azienda.
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    public const CONTACT_PREFERENCES = ['SMS', 'WhatsApp', 'Telefonata', 'E-mail'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $zip = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    // come avvisarlo quando l'auto è pronta
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $contactPreference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getVatNumber(): ?string { return $this->vatNumber; }
    public function setVatNumber(?string $vatNumber): static { $this->vatNumber = $vatNumber; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getZip(): ?string { return $this->zip; }
    public function setZip(?string $zip): static { $this->zip = $zip; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): static { $this->city = $city; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): static { $this->country = $country; return $this; }

    public function getContactPerson(): ?string { return $this->contactPerson; }
    public function setContactPerson(?string $v): static { $this->contactPerson = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getContactPreference(): ?string { return $this->contactPreference; }
    /** Da stampare: "Telefonata" tradotta (getContactPreference() resta il valore del form) */
    public function getContactPreferenceLabel(): ?string { return $this->contactPreference === null ? null : Trans::label($this->contactPreference); }
    public function setContactPreference(?string $v): static { $this->contactPreference = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
