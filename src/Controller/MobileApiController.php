<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mobile API — mirrors storefront data ({@see CustomerLandingController}) with standardized JSON.
 * Public routes are allow-listed in security.yaml.
 */
#[Route('/api/mobile')]
final class MobileApiController extends AbstractController
{
    private const MSG_OK = 'Request successful';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('/ping', name: 'api_mobile_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->mobileSuccess('ok', ['pong' => true]);
    }

    /**
     * Categories list for the mobile menu/filter UI.
     */
    #[Route('/categories', name: 'api_mobile_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        $items = array_map(static fn ($c): array => [
            'id' => $c->getId(),
            'name' => $c->getName(),
        ], $categories);

        return $this->mobileSuccess(self::MSG_OK, [
            'count' => \count($items),
            'items' => $items,
        ]);
    }

    /**
     * Menu-shaped payload aligned with {@see CustomerLandingController::menuCategoryContext()} and the /menu page:
     * categories (name ASC), products grouped per category, plus uncategorized bucket when applicable.
     */
    #[Route('/menu', name: 'api_mobile_menu', methods: ['GET'])]
    public function menu(): JsonResponse
    {
        $products = $this->productRepository->findAll();
        $menuCategories = $this->categoryRepository->findBy([], ['name' => 'ASC']);

        $hasUncategorized = false;
        foreach ($products as $p) {
            if (null === $p->getCategory()) {
                $hasUncategorized = true;
                break;
            }
        }

        $byCategory = [];
        foreach ($menuCategories as $cat) {
            $cid = (int) $cat->getId();
            $byCategory[$cid] = [
                'id' => $cid,
                'name' => $cat->getName(),
                'products' => [],
            ];
        }

        $uncategorizedItems = [];
        foreach ($products as $product) {
            $cat = $product->getCategory();
            if (null === $cat) {
                $uncategorizedItems[] = $this->serializeProductSummary($product);
                continue;
            }
            $cid = (int) $cat->getId();
            if (!isset($byCategory[$cid])) {
                $byCategory[$cid] = [
                    'id' => $cid,
                    'name' => $cat->getName(),
                    'products' => [],
                ];
            }
            $byCategory[$cid]['products'][] = $this->serializeProductSummary($product);
        }

        return $this->mobileSuccess(self::MSG_OK, [
            'categories' => array_values($byCategory),
            'uncategorized' => [
                'hasProducts' => $hasUncategorized,
                'products' => $uncategorizedItems,
            ],
        ]);
    }

    /** Same product set as the landing page ({@see CustomerLandingController::landing()}). */
    #[Route('/products', name: 'api_mobile_products', methods: ['GET'])]
    public function products(): JsonResponse
    {
        $products = $this->productRepository->findAll();
        $items = array_map(fn (Product $p) => $this->serializeProductSummary($p), $products);

        return $this->mobileSuccess(self::MSG_OK, [
            'count' => \count($items),
            'items' => $items,
        ]);
    }

    #[Route('/products/{id}', name: 'api_mobile_product_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function product(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->mobileError('Product not found', Response::HTTP_NOT_FOUND);
        }

        return $this->mobileSuccess(self::MSG_OK, [
            'product' => $this->serializeProductDetail($product),
        ]);
    }

    #[Route('/me', name: 'api_mobile_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->mobileSuccess(self::MSG_OK, [
            'user' => $this->serializeUserProfile($user),
        ]);
    }

    /**
     * Orders placed by this account — same basis as web receipt access ({@see OrderRepository::findPlacedByUser()}).
     */
    #[Route('/my-orders', name: 'api_mobile_my_orders', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myOrders(#[CurrentUser] User $user): JsonResponse
    {
        $orders = $this->orderRepository->findPlacedByUser($user);

        return $this->mobileSuccess(self::MSG_OK, [
            'count' => \count($orders),
            'orders' => array_map(fn (Order $o) => $this->serializeOrderSummary($o), $orders),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProductSummary(Product $product): array
    {
        $category = $product->getCategory();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'image' => $product->getimage(),
            'category' => $category ? ['id' => $category->getId(), 'name' => $category->getName()] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProductDetail(Product $product): array
    {
        $base = $this->serializeProductSummary($product);
        $base['description'] = $product->getDescription();
        $dt = $product->getDatetime();
        $base['updatedAt'] = $dt?->format(\DateTimeInterface::ATOM);

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUserProfile(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'verified' => true === $user->isVerified(),
            'active' => $user->isActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(Order $order): array
    {
        $lines = [];
        foreach ($order->getProducts() as $p) {
            $lines[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
            ];
        }

        return [
            'id' => $order->getId(),
            'label' => $order->getName(),
            'status' => $order->getStatus(),
            'total' => $order->getTotal(),
            'paymentMethod' => $order->getPaymentMethod(),
            'createdAt' => $order->getCreateAt()?->format(\DateTimeInterface::ATOM),
            'items' => $lines,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mobileSuccess(string $message, array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function mobileError(string $message, int $status): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
