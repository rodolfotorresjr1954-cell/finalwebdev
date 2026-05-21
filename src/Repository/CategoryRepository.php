<?php

namespace App\Repository;

use App\Entity\Category;
use App\DataFixtures\CategoryFixtures;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function createMenuCategoriesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->where('LOWER(c.name) IN (:names)')
            ->setParameter('names', CategoryFixtures::MENU_CATEGORIES)
            ->orderBy('c.name', 'ASC');
    }

    /**
     * Match a canonical menu label (Burger, Fries, Drinks) to burger / fries / milktea.
     */
    public function findBestMatchForMenuLabel(string $canonicalLabel): ?Category
    {
        $canonicalLabel = trim($canonicalLabel);
        if (!in_array($canonicalLabel, ['Burger', 'Fries', 'Drinks'], true)) {
            return null;
        }

        $dbName = match ($canonicalLabel) {
            'Burger' => 'burger',
            'Fries' => 'fries',
            'Drinks' => 'milktea',
            default => null,
        };

        if ($dbName === null) {
            return null;
        }

        foreach ($this->findAll() as $cat) {
            if (strcasecmp(trim($cat->getName() ?? ''), $dbName) === 0) {
                return $cat;
            }
        }

        return null;
    }

    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
