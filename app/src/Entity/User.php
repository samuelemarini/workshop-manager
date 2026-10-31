<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 180)]
    private ?string $name = null;

    #[ORM\Column(length: 180)]
    private ?string $surname = null;

    // Usata per gli avvisi e-mail sulle scadenze
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'users_roles')]
    private Collection $customroles;

    #[ORM\Column]
    private ?string $password = null;

    private ?string $plainPassword = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $updatedBy = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $cancel = 0;

    /** Lingua dell'interfaccia, chiave di Language::LANGUAGES; null = quella del browser. */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    public function __construct()
    {
        $this->customroles = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->customroles as $role) {
            $roles[] = $role->getCode();
        }

        return array_values(array_unique($roles));
    }

    public function hasRoleCode(string $code): bool
    {
        foreach ($this->customroles as $role) {
            if ($role->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getLocale(): ?string { return $this->locale; }
    public function setLocale(?string $locale): static
    {
        $this->locale = isset(\App\Service\Language::LANGUAGES[$locale ?? '']) ? $locale : null;

        return $this;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSurname(): ?string { return $this->surname; }
    public function setSurname(string $surname): static { $this->surname = $surname; return $this; }

    public function getFullName(): string
    {
        return trim($this->name.' '.$this->surname);
    }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getCreatedBy(): ?self { return $this->createdBy; }
    public function setCreatedBy(?self $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getUpdatedBy(): ?self { return $this->updatedBy; }
    public function setUpdatedBy(?self $updatedBy): static { $this->updatedBy = $updatedBy; return $this; }

    public function getCancel(): int { return $this->cancel; }
    public function setCancel(int $cancel): static { $this->cancel = $cancel; return $this; }

    /** @return Collection<int, Role> */
    public function getCustomroles(): Collection { return $this->customroles; }

    public function addCustomrole(Role $role): static
    {
        if (!$this->customroles->contains($role)) {
            $this->customroles->add($role);
        }

        return $this;
    }

    public function removeCustomrole(Role $role): static
    {
        $this->customroles->removeElement($role);

        return $this;
    }

    public function getPlainPassword(): ?string { return $this->plainPassword; }
    public function setPlainPassword(?string $password): static { $this->plainPassword = $password; return $this; }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
