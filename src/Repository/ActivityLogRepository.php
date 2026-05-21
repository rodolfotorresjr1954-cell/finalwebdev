<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find logs with filters
     */
    public function findWithFilters(?int $userId = null, ?string $action = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->addSelect('u')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($userId !== null) {
            $qb->andWhere('al.user = :userId')
               ->setParameter('userId', $userId);
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $action);
        }

        if ($startDate !== null) {
            $qb->andWhere('al.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('al.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count logs with filters
     */
    public function countWithFilters(?int $userId = null, ?string $action = null, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): int
    {
        $qb = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)');

        if ($userId !== null) {
            $qb->andWhere('al.user = :userId')
               ->setParameter('userId', $userId);
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('al.action = :action')
               ->setParameter('action', $action);
        }

        if ($startDate !== null) {
            $qb->andWhere('al.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('al.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

