<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\PrePersist;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Delete()
    ],

normalizationContext: ['groups' => ['ActivityLog:read']],
    denormalizationContext: ['groups' => ['ActivityLog:write']]

)]

#[ORM\Table(name: 'activity_logs')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_id')]
#[ORM\Index(columns: ['action'], name: 'idx_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ActivityLog:read'])]
    private ?int $id = null;


    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?User $user = null;

    #[ORM\Column(length: 180)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?string $username = null;

    #[ORM\Column(length: 50)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?string $role = null;

    #[ORM\Column(length: 50)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?string $action = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]

    private ?string $affectedData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['ActivityLog:read', 'ActivityLog:write'])]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getAffectedData(): ?string
    {
        return $this->affectedData;
    }

    public function setAffectedData(?string $affectedData): static
    {
        $this->affectedData = $affectedData;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}

