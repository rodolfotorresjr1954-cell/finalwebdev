<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\MobileCartService;
use App\Service\MobileCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mobile')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MobileCartController extends AbstractController
{
    public function __construct(
        private readonly MobileCartService $cartService,
        private readonly MobileCheckoutService $checkoutService,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('/cart', name: 'api_mobile_cart_get', methods: ['GET'])]
    public function getCart(#[CurrentUser] User $user): JsonResponse
    {
        return $this->mobileSuccess('Cart loaded', $this->cartService->getCartSnapshot($user));
    }

    #[Route('/cart/items', name: 'api_mobile_cart_add', methods: ['POST'])]
    public function addItem(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $productId = (int) ($payload['productId'] ?? 0);
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        if ($productId <= 0) {
            return $this->mobileError('productId is required', Response::HTTP_BAD_REQUEST);
        }

        try {
            $cart = $this->cartService->addItem($user, $productId, $quantity);
        } catch (\InvalidArgumentException $e) {
            return $this->mobileError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->mobileSuccess('Item added to cart', $cart);
    }

    #[Route('/cart/items/{productId}', name: 'api_mobile_cart_update', methods: ['PATCH', 'PUT'])]
    public function updateItem(Request $request, int $productId, #[CurrentUser] User $user): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $quantity = (int) ($payload['quantity'] ?? -1);

        try {
            $cart = $this->cartService->updateQuantity($user, $productId, $quantity);
        } catch (\InvalidArgumentException $e) {
            return $this->mobileError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->mobileSuccess('Cart updated', $cart);
    }

    #[Route('/cart/items/{productId}', name: 'api_mobile_cart_remove', methods: ['DELETE'])]
    public function removeItem(int $productId, #[CurrentUser] User $user): JsonResponse
    {
        $cart = $this->cartService->removeItem($user, $productId);

        return $this->mobileSuccess('Item removed', $cart);
    }

    #[Route('/cart', name: 'api_mobile_cart_clear', methods: ['DELETE'])]
    public function clearCart(#[CurrentUser] User $user): JsonResponse
    {
        $cart = $this->cartService->clear($user);

        return $this->mobileSuccess('Cart cleared', $cart);
    }

    #[Route('/checkout/preview', name: 'api_mobile_checkout_preview', methods: ['GET'])]
    public function checkoutPreview(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $preview = $this->checkoutService->preview($user);
        } catch (\InvalidArgumentException $e) {
            return $this->mobileError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->mobileSuccess('Checkout preview', $preview);
    }

    #[Route('/checkout/place', name: 'api_mobile_checkout_place', methods: ['POST'])]
    public function placeOrder(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = $this->decodeJson($request);

        try {
            $result = $this->checkoutService->placeOrder($user, $payload);
        } catch (\InvalidArgumentException $e) {
            return $this->mobileError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->mobileSuccess('Order placed successfully', $result, Response::HTTP_CREATED);
    }

    #[Route('/orders/{id}', name: 'api_mobile_order_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function orderDetail(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $order = $this->orderRepository->find($id);
        if (!$order instanceof Order) {
            return $this->mobileError('Order not found', Response::HTTP_NOT_FOUND);
        }

        $owner = $order->getCreatedBy();
        if (!$owner instanceof User || $owner->getId() !== $user->getId()) {
            return $this->mobileError('Order not found', Response::HTTP_NOT_FOUND);
        }

        $lines = [];
        foreach ($order->getProducts() as $p) {
            $lines[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
                'quantity' => 1,
            ];
        }

        return $this->mobileSuccess('Order loaded', [
            'order' => [
                'id' => $order->getId(),
                'label' => $order->getName(),
                'status' => $order->getStatus(),
                'total' => $order->getTotal(),
                'paymentMethod' => $order->getPaymentMethod(),
                'createdAt' => $order->getCreateAt()?->format(\DateTimeInterface::ATOM),
                'items' => $lines,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
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
