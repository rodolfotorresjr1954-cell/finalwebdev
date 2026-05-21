<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MobileCheckoutService
{
    private const TAX_RATE = 0.0;

    public function __construct(
        private readonly MobileCartService $cartService,
        private readonly CartItemRepository $cartItemRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly PaymentGatewayService $paymentGateway,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(User $user): array
    {
        $cart = $this->cartService->getCartSnapshot($user);

        if ($cart['itemCount'] === 0) {
            throw new \InvalidArgumentException('Your cart is empty.');
        }

        $subtotal = (float) $cart['subtotal'];
        $tax = round($subtotal * self::TAX_RATE, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'items' => $cart['items'],
            'itemCount' => $cart['itemCount'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => 0.0,
            'total' => $total,
            'paymentMethods' => $this->paymentGateway->getAllowedMethods(),
        ];
    }

    /**
     * @param array{
     *   name?: string,
     *   email?: string,
     *   phone?: string,
     *   shippingAddress?: string,
     *   paymentMethod: string,
     *   paymentToken?: string|null
     * } $payload
     *
     * @return array<string, mixed>
     */
    public function placeOrder(User $user, array $payload): array
    {
        $preview = $this->preview($user);
        $total = (float) $preview['total'];
        $paymentMethod = strtolower(trim((string) ($payload['paymentMethod'] ?? '')));
        $paymentToken = isset($payload['paymentToken']) ? (string) $payload['paymentToken'] : null;

        $payment = $this->paymentGateway->charge($paymentMethod, $paymentToken, $total);

        $customerName = trim((string) ($payload['name'] ?? ''));
        if ($customerName === '') {
            $customerName = $user->getUserIdentifier();
        }

        $customerEmail = trim((string) ($payload['email'] ?? ''));
        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $customerEmail = $user->getEmail() ?: ($user->getUserIdentifier().'@example.com');
        }

        $customerPhone = trim((string) ($payload['phone'] ?? ''));
        $shippingAddress = trim((string) ($payload['shippingAddress'] ?? ''));

        $customer = $this->customerRepository->findOneBy(['Email' => $customerEmail]);
        if (!$customer instanceof Customer) {
            $customer = new Customer();
        }
        $customer->setName($customerName);
        $customer->setEmail($customerEmail);
        $customer->setPhone($customerPhone !== '' ? $customerPhone : null);
        $customer->setCreateAt($customer->getCreateAt() ?? new \DateTimeImmutable());
        $customer->setCreatedBy($user);

        $order = new Order();
        $order->setCreateAt(new \DateTimeImmutable());
        $order->setStatus('Pending');
        $order->setTotal($total);
        $order->setPaymentMethod($paymentMethod);
        $order->setCustomer($customer);
        $order->setCreatedBy($user);

        $lineNames = [];
        foreach ($this->cartItemRepository->findByUser($user) as $cartRow) {
            $product = $cartRow->getProduct();
            if (null === $product) {
                continue;
            }
            $qty = $cartRow->getQuantity();
            $order->addProduct($product);
            $name = $product->getName() ?? 'Item';
            $lineNames[] = $qty > 1 ? sprintf('%dx %s', $qty, $name) : $name;
        }

        $labelParts = array_filter($lineNames, static fn (string $n): bool => $n !== '');
        $productsPart = implode(', ', $labelParts);
        $orderLabel = $productsPart !== ''
            ? sprintf('%s — %s', $productsPart, $customerName)
            : sprintf('Order — %s', $customerName);

        if ($shippingAddress !== '') {
            $orderLabel .= ' | '.$shippingAddress;
        }

        if (mb_strlen($orderLabel) > 255) {
            $orderLabel = mb_substr($orderLabel, 0, 252).'…';
        }
        $order->setName($orderLabel);

        $this->entityManager->persist($customer);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->cartItemRepository->clearForUser($user);

        return [
            'order' => [
                'id' => $order->getId(),
                'label' => $order->getName(),
                'status' => $order->getStatus(),
                'total' => $order->getTotal(),
                'paymentMethod' => $order->getPaymentMethod(),
                'createdAt' => $order->getCreateAt()?->format(\DateTimeInterface::ATOM),
                'items' => $preview['items'],
            ],
            'payment' => $payment,
        ];
    }
}
