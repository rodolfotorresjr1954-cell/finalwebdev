<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\ActivityLogRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function index(
        Request $request,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
    ): Response {
        $context = $this->buildDashboardContext(
            $request,
            $customerRepository,
            $productRepository,
            $orderRepository,
            $userRepository,
            $activityLogRepository,
        );

        return $this->render('dashboard/index.html.twig', $context);
    }

    #[Route('/dashboard/stats', name: 'app_dashboard_stats', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function stats(
        Request $request,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
    ): JsonResponse {
        $context = $this->buildDashboardContext(
            $request,
            $customerRepository,
            $productRepository,
            $orderRepository,
            $userRepository,
            $activityLogRepository,
        );

        return $this->json($this->buildStatsPayload($context));
    }

    #[Route('/dashboard/rows', name: 'app_dashboard_rows', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function rows(
        Request $request,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
    ): JsonResponse {
        $context = $this->buildDashboardContext(
            $request,
            $customerRepository,
            $productRepository,
            $orderRepository,
            $userRepository,
            $activityLogRepository,
        );

        return $this->json($this->buildRowsPayload($context));
    }

    #[Route('/dashboard/stream', name: 'app_dashboard_stream', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function liveStream(
        Request $request,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
    ): StreamedResponse {
        $response = new StreamedResponse(function () use (
            $request,
            $customerRepository,
            $productRepository,
            $orderRepository,
            $userRepository,
            $activityLogRepository,
        ): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $lastSignature = '';
            $maxSeconds = 25;
            $start = time();

            while ((time() - $start) < $maxSeconds) {
                $context = $this->buildDashboardContext(
                    $request,
                    $customerRepository,
                    $productRepository,
                    $orderRepository,
                    $userRepository,
                    $activityLogRepository,
                );
                $payload = [
                    'stats' => $this->buildStatsPayload($context),
                    'rows' => $this->buildRowsPayload($context),
                ];
                $signature = $payload['stats']['statsSignature'].'|'.$payload['rows']['rowsSignature'];

                if ($signature !== $lastSignature) {
                    $lastSignature = $signature;
                    echo "event: dashboard\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                } else {
                    echo "event: ping\n";
                    echo "data: {}\n\n";
                }

                @ob_flush();
                @flush();
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardContext(
        Request $request,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ActivityLogRepository $activityLogRepository,
    ): array {
        $customerCount = $customerRepository->count([]);
        $productCount = $productRepository->count([]);
        $orderCount = $orderRepository->count([]);
        $totalSales = $orderRepository->getTotalSales();

        $totalUsers = $userRepository->count([]);
        $totalStaff = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();

        $totalRecords = $customerCount + $productCount + $orderCount;
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

        return [
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
            'totalUsers' => $totalUsers,
            'totalStaff' => $totalStaff,
            'totalRecords' => $totalRecords,
            'recentActivities' => $recentActivities,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{
     *   orderCount: int,
     *   totalSales: float,
     *   totalSalesFormatted: string,
     *   totalRecords: int,
     *   statsSignature: string
     * }
     */
    private function buildStatsPayload(array $context): array
    {
        $orderCount = (int) $context['orderCount'];
        $totalSales = (float) $context['totalSales'];
        $totalRecords = (int) $context['totalRecords'];

        return [
            'orderCount' => $orderCount,
            'totalSales' => $totalSales,
            'totalSalesFormatted' => number_format($totalSales, 2, '.', ','),
            'totalRecords' => $totalRecords,
            'statsSignature' => sprintf('%d:%s:%d', $orderCount, number_format($totalSales, 2, '.', ''), $totalRecords),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{
     *   rowsHtml: string,
     *   rowsSignature: string,
     *   footerText: string
     * }
     */
    private function buildRowsPayload(array $context): array
    {
        /** @var list<Order> $recentOrders */
        $recentOrders = $context['recentOrders'];
        $page = (int) $context['page'];
        $perPage = (int) $context['perPage'];
        $totalOrders = (int) $context['totalOrders'];

        $rowsSignature = implode('|', array_map(
            static fn (Order $o): string => sprintf(
                '%d:%s:%0.2f',
                (int) $o->getId(),
                strtolower((string) $o->getStatus()),
                (float) $o->getTotal()
            ),
            $recentOrders
        ));

        $start = $totalOrders > 0 ? (($page - 1) * $perPage + 1) : 0;
        $end = min($page * $perPage, $totalOrders);

        return [
            'rowsHtml' => $this->renderView('dashboard/_recent_orders_rows.html.twig', [
                'recentOrders' => $recentOrders,
            ]),
            'rowsSignature' => $rowsSignature,
            'footerText' => sprintf('Showing %d–%d of %d', $start, $end, $totalOrders),
        ];
    }
}
