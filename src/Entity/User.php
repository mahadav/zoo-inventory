<?php
// src/Entity/User.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    // when password was last changed
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordChangedAt = null;

    // number of consecutive failed attempts
    #[ORM\Column(type: 'integer')]
    private int $failedLoginCount = 0;

    // if set, user is locked until this datetime (null = not locked)
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    public function __construct(string $email = '', string $password = '')
    {
        $this->email = $email;
        $this->password = $password;
        $this->roles = ['ROLE_ADMIN'];
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return $this->email; }
    public function getUsername(): string { return $this->email; } // legacy
    public function getRoles(): array { return array_unique($this->roles); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function eraseCredentials(): void { /* no-op */ }

    public function getPasswordChangedAt(): ?\DateTimeImmutable { return $this->passwordChangedAt; }
    public function setPasswordChangedAt(\DateTimeImmutable $dt): self { $this->passwordChangedAt = $dt; return $this; }

    public function getFailedLoginCount(): int { return $this->failedLoginCount; }
    public function setFailedLoginCount(int $c): self { $this->failedLoginCount = $c; return $this; }
    public function incrementFailedLoginCount(): self { $this->failedLoginCount++; return $this; }
    public function resetFailedLoginCount(): self { $this->failedLoginCount = 0; return $this; }

    public function getLockedUntil(): ?\DateTimeImmutable { return $this->lockedUntil; }
    public function setLockedUntil(?\DateTimeImmutable $dt): self { $this->lockedUntil = $dt; return $this; }
    public function isLocked(): bool {
        return $this->lockedUntil !== null && $this->lockedUntil > new \DateTimeImmutable();
    }
}
