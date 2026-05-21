<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function index(Request $request, CustomerRepository $customerRepository, ProductRepository $productRepository, OrderRepository $orderRepository, UserRepository $userRepository, ActivityLogRepository $activityLogRepository): Response
    {
        $customerCount = $customerRepository->count([]);
        $productCount = $productRepository->count([]);
        $orderCount = $orderRepository->count([]);
        $totalSales = $orderRepository->getTotalSales();
        
        // Admin-specific statistics
        $totalUsers = $userRepository->count([]);
        $totalStaff = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalRecords = $customerCount + $productCount + $orderCount;
        
        // Recent activities (last 10)
        $recentActivities = $activityLogRepository->findWithFilters(null, null, null, null, 10, 0);

        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $totalOrders = $orderCount;
        $totalPages = (int) ceil($totalOrders / $perPage) ?: 1;
        $offset = ($page - 1) * $perPage;

        $qb = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.Customer', 'c')->addSelect('c')
            ->leftJoin('o.products', 'p')->addSelect('p')
            ->leftJoin('p.Category', 'cat')->addSelect('cat');

        if ($q !== '') {
            $qb->andWhere('LOWER(o.Name) LIKE :q OR LOWER(c.Name) LIKE :q OR LOWER(p.Name) LIKE :q OR LOWER(cat.name) LIKE :q')
               ->setParameter('q', '%'.strtolower($q).'%');
        }

        switch ($sort) {
            case 'name':
                $qb->orderBy('o.Name', $dir);
                break;
            case 'customer':
                $qb->orderBy('c.Name', $dir);
                break;
            case 'amount':
                $qb->orderBy('o.Total', $dir);
                break;
            case 'status':
                $qb->orderBy('o.Status', $dir);
                break;
            case 'date':
            default:
                $qb->orderBy('o.createAt', $dir);
                break;
        }

        $recentOrders = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/index.html.twig', [
            'customerCount' => $customerCount,
            'productCount' => $productCount,
            'orderCount' => $orderCount,
            'totalSales' => $totalSales,
            'recentOrders' => $recentOrders,
            'q' => $q,
            'sort' => $sort,
            'dir' => strtolower($dir),
            'page' => $page,
            'perPage' => $perPage,
            'totalOrders' => $totalOrders,
            'totalPages' => $totalPages,
            // Admin statistics
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalRecords' => $totalRecords,
            'recentActivities' => $recentActivities,
        ]);
    }
}
