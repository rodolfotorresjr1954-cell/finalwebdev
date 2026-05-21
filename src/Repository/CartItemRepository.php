<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * @return list<CartItem>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->innerJoin('c.product', 'p')
            ->addSelect('p')
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndProduct(User $user, Product $product): ?CartItem
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }

    public function clearForUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
