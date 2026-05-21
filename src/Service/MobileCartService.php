<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MobileCartService
{
    public function __construct(
        private readonly CartItemRepository $cartItemRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, itemCount: int, subtotal: float}
     */
    public function getCartSnapshot(User $user): array
    {
        $rows = $this->cartItemRepository->findByUser($user);
        $items = [];
        $itemCount = 0;
        $subtotal = 0.0;

        foreach ($rows as $row) {
            $product = $row->getProduct();
            if (!$product instanceof Product) {
                continue;
            }
            $qty = $row->getQuantity();
            $price = (float) ($product->getPrice() ?? 0);
            $lineTotal = round($price * $qty, 2);
            $itemCount += $qty;
            $subtotal += $lineTotal;

            $category = $product->getCategory();
            $items[] = [
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'price' => $price,
                'quantity' => $qty,
                'lineTotal' => $lineTotal,
                'image' => $product->getimage(),
                'category' => $category ? ['id' => $category->getId(), 'name' => $category->getName()] : null,
                'stock' => $product->getStock(),
            ];
        }

        return [
            'items' => $items,
            'itemCount' => $itemCount,
            'subtotal' => round($subtotal, 2),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, itemCount: int, subtotal: float}
     */
    public function addItem(User $user, int $productId, int $quantity = 1): array
    {
        $product = $this->requireProduct($productId);
        $this->assertStockAvailable($product, $quantity);

        $existing = $this->cartItemRepository->findOneByUserAndProduct($user, $product);
        if ($existing instanceof CartItem) {
            $newQty = $existing->getQuantity() + $quantity;
            $this->assertStockAvailable($product, $newQty);
            $existing->setQuantity($newQty);
        } else {
            $item = new CartItem();
            $item->setUser($user);
            $item->setProduct($product);
            $item->setQuantity($quantity);
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();

        return $this->getCartSnapshot($user);
    }

    /**
     * @return array{items: list<array<string, mixed>>, itemCount: int, subtotal: float}
     */
    public function updateQuantity(User $user, int $productId, int $quantity): array
    {
        $product = $this->requireProduct($productId);
        $row = $this->cartItemRepository->findOneByUserAndProduct($user, $product);
        if (!$row instanceof CartItem) {
            throw new \InvalidArgumentException('Item not in cart.');
        }

        if ($quantity <= 0) {
            $this->entityManager->remove($row);
        } else {
            $this->assertStockAvailable($product, $quantity);
            $row->setQuantity($quantity);
        }

        $this->entityManager->flush();

        return $this->getCartSnapshot($user);
    }

    /**
     * @return array{items: list<array<string, mixed>>, itemCount: int, subtotal: float}
     */
    public function removeItem(User $user, int $productId): array
    {
        $product = $this->requireProduct($productId);
        $row = $this->cartItemRepository->findOneByUserAndProduct($user, $product);
        if ($row instanceof CartItem) {
            $this->entityManager->remove($row);
            $this->entityManager->flush();
        }

        return $this->getCartSnapshot($user);
    }

    /**
     * @return array{items: list<array<string, mixed>>, itemCount: int, subtotal: float}
     */
    public function clear(User $user): array
    {
        $this->cartItemRepository->clearForUser($user);

        return $this->getCartSnapshot($user);
    }

    private function requireProduct(int $productId): Product
    {
        $product = $this->productRepository->find($productId);
        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('Product not found.');
        }

        return $product;
    }

    private function assertStockAvailable(Product $product, int $requestedQty): void
    {
        $stock = $product->getStock();
        if ($stock > 0 && $requestedQty > $stock) {
            throw new \InvalidArgumentException(sprintf(
                'Only %d of "%s" available in stock.',
                $stock,
                $product->getName() ?? 'item',
            ));
        }
    }
}
