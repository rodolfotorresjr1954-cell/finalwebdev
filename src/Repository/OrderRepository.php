<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function getTotalSales(): float
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.Total), 0) as total');

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * @return Order[]
     */
    public function findPlacedByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithDetails(int $id): ?Order
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.Customer', 'cust')->addSelect('cust')
            ->leftJoin('o.products', 'p')->addSelect('p')
            ->leftJoin('p.Category', 'pc')->addSelect('pc')
            ->leftJoin('o.createdBy', 'u')->addSelect('u')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
