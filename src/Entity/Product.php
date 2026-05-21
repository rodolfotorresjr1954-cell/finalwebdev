<?php

namespace App\Entity;

use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Get(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['Product:read']],
    denormalizationContext: ['groups' => ['Product:write']]
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['Product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['Product:read', 'Product:write'])]
    private ?string $Name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['Product:read', 'Product:write'])]

    private ?string $Description = null;

    #[ORM\Column]
    #[Groups(['Product:read', 'Product:write'])]

    private ?float $Price = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['Product:read', 'Product:write'])]
    private int $stock = 0;

    #[ORM\Column]
    #[Groups(['Product:read', 'Product:write'])]
    private ?\DateTimeImmutable $Datetime = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[Groups(['Product:read', 'Product:write'])]
    private ?Category $Category = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

     #[ORM\Column(length: 255)]
    #[Groups(['Product:read', 'Product:write'])]
    private ?string $image = null;

     /**
      * @var Collection<int, Order>
      */
     #[ORM\ManyToMany(targetEntity: Order::class, mappedBy: 'products')]
     private Collection $orders;

     public function __construct()
     {
         $this->orders = new ArrayCollection();
         $this->stock = 0;
     }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): static
    {
        $this->Name = $Name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->Price;
    }

    public function setPrice(float $Price): static
    {
        $this->Price = $Price;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getDatetime(): ?\DateTimeImmutable
    {
        return $this->Datetime;
    }

    public function setDatetime(\DateTimeImmutable $Datetime): static
    {
        $this->Datetime = $Datetime;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->Category;
    }

    public function setCategory(?Category $Category): static
    {
        $this->Category = $Category;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getimage()
    {
    return $this->image;
    }

    public function setimage($image): static
    {
    $this->image = $image;
    return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->addProduct($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            $order->removeProduct($this);
        }

        return $this;
    }

}
