<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\BrevoContactService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CustomerLandingController extends AbstractController
{
    private const CART_SESSION_KEY = 'customer_cart_product_ids';

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private OrderRepository $orderRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/customer-landing', name: 'customer_landing', methods: ['GET'])]
    public function landing(Request $request): Response
    {
        $products = $this->productRepository->findAll();

        $cartProductIds = $this->getCartProductIds($request);
        $cartProducts = [];
        $cartTotal = 0.0;

        if (!empty($cartProductIds)) {
            $cartProducts = $this->loadCartProducts($cartProductIds);
            $cartTotal = $this->calculateTotal($cartProducts);
        }

        $destinationProducts = array_slice($products, 0, 4);

        $menuCtx = $this->menuCategoryContext($products);

        return $this->render('customer/landing.html.twig', [
            'products' => $products,
            'menu_categories' => $menuCtx['menu_categories'],
            'has_uncategorized_product' => $menuCtx['has_uncategorized_product'],
            'destination_products' => $destinationProducts,
            'cartProductIds' => $cartProductIds,
            'cartProducts' => $cartProducts,
            'cartTotal' => $cartTotal,
        ]);
    }

    #[Route('/menu', name: 'customer_menu', methods: ['GET'])]
    public function menuPage(Request $request): Response
    {
        $products = $this->productRepository->findAll();

        $cartProductIds = $this->getCartProductIds($request);
        $cartProducts = [];
        if (!empty($cartProductIds)) {
            $cartProducts = $this->loadCartProducts($cartProductIds);
        }

        $menuCtx = $this->menuCategoryContext($products);

        return $this->render('customer/menu.html.twig', [
            'products' => $products,
            'menu_categories' => $menuCtx['menu_categories'],
            'has_uncategorized_product' => $menuCtx['has_uncategorized_product'],
            'cartProductIds' => $cartProductIds,
            'cartProducts' => $cartProducts,
        ]);
    }

    #[Route('/about', name: 'customer_about', methods: ['GET'])]
    public function aboutPage(Request $request): Response
    {
        $cartProductIds = $this->getCartProductIds($request);
        $cartProducts = [];
        if (!empty($cartProductIds)) {
            $cartProducts = $this->loadCartProducts($cartProductIds);
        }

        return $this->render('customer/about.html.twig', [
            'cartProducts' => $cartProducts,
        ]);
    }

    #[Route('/contact', name: 'customer_contact', methods: ['GET'])]
    public function contactPage(Request $request): Response
    {
        if ('1' === (string) $request->query->get('sent')) {
            $this->addFlash('success', 'Thanks! Your message has been sent.');
        }

        $cartProductIds = $this->getCartProductIds($request);
        $cartProducts = [];
        if (!empty($cartProductIds)) {
            $cartProducts = $this->loadCartProducts($cartProductIds);
        }

        return $this->render('customer/contact.html.twig', [
            'cartProducts' => $cartProducts,
        ]);
    }

    #[Route('/contact', name: 'customer_contact_submit', methods: ['POST'])]
    public function contactSubmit(Request $request, BrevoContactService $brevo): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('contact_submit', $token)) {
            $this->addFlash('error', 'Invalid form submission. Please try again.');

            return $this->redirectToRoute('customer_contact');
        }

        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $message = trim((string) $request->request->get('message', ''));

        if ($name === '' || $email === '' || $message === '') {
            $this->addFlash('error', 'Please fill out all fields.');

            return $this->redirectToRoute('customer_contact');
        }

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please enter a valid email address.');

            return $this->redirectToRoute('customer_contact');
        }

        if (mb_strlen($message) > 4000) {
            $this->addFlash('error', 'Message is too long.');

            return $this->redirectToRoute('customer_contact');
        }

        try {
            $brevo->sendContactMessage($name, $email, 'Website contact form', $message);
        } catch (\Throwable) {
            $this->addFlash('error', 'Sorry — we could not send your message right now. Please try again later.');

            return $this->redirectToRoute('customer_contact');
        }

        return $this->redirectToRoute('customer_contact', ['sent' => 1]);
    }

    #[Route('/my-orders', name: 'customer_my_orders', methods: ['GET'])]
    public function myOrders(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedException();
        }

        $cartProductIds = $this->getCartProductIds($request);
        $cartProducts = [];
        if (!empty($cartProductIds)) {
            $cartProducts = $this->loadCartProducts($cartProductIds);
        }

        $orders = $this->orderRepository->findPlacedByUser($user);

        return $this->render('customer/my_orders.html.twig', [
            'orders' => $orders,
            'cartProducts' => $cartProducts,
        ]);
    }

    #[Route('/customer-landing/cart/add/{id}', name: 'customer_cart_add', methods: ['POST'])]
    public function addToCart(Request $request, Product $product): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_add_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid request token.');

            return $this->redirectToRoute($this->resolveCartReturnRoute($request));
        }

        $cartProductIds = $this->getCartProductIds($request);
        $cartProductIds[$product->getId()] = (int) $product->getId();

        $request->getSession()->set(self::CART_SESSION_KEY, array_values($cartProductIds));

        return $this->redirectToRoute($this->resolveCartReturnRoute($request));
    }

    #[Route('/customer-landing/cart/remove/{id}', name: 'customer_cart_remove', methods: ['POST'])]
    public function removeFromCart(Request $request, Product $product): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cart_remove_' . $product->getId(), $token)) {
            $this->addFlash('error', 'Invalid request token.');

            return $this->redirectToRoute($this->resolveCartReturnRoute($request));
        }

        $cartProductIds = $this->getCartProductIds($request);
        unset($cartProductIds[(int) $product->getId()]);

        $request->getSession()->set(self::CART_SESSION_KEY, array_values($cartProductIds));

        return $this->redirectToRoute($this->resolveCartReturnRoute($request));
    }

    #[Route('/customer-landing/checkout', name: 'customer_checkout', methods: ['GET'])]
    public function checkout(Request $request): Response
    {
        $cartProductIds = $this->getCartProductIds($request);
        if (empty($cartProductIds)) {
            $this->addFlash('error', 'Your cart is empty.');

            return $this->redirectToRoute('app_home');
        }

        $cartProducts = $this->loadCartProducts($cartProductIds);
        $total = $this->calculateTotal($cartProducts);

        return $this->render('customer/checkout.html.twig', [
            'cartProducts' => $cartProducts,
            'total' => $total,
        ]);
    }

    #[Route('/customer-landing/checkout/place', name: 'customer_checkout_place', methods: ['POST'])]
    public function placeOrder(Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('checkout_place', $token)) {
            $this->addFlash('error', 'Invalid request token.');

            return $this->redirectToRoute('customer_checkout');
        }

        $cartProductIds = $this->getCartProductIds($request);
        if (empty($cartProductIds)) {
            $this->addFlash('error', 'Your cart is empty.');

            return $this->redirectToRoute('app_home');
        }

        $paymentMethod = strtolower((string) $request->request->get('paymentMethod', ''));
        $allowed = ['cash', 'gcash', 'atm'];
        if (!in_array($paymentMethod, $allowed, true)) {
            $this->addFlash('error', 'Please choose a valid payment method.');

            return $this->redirectToRoute('customer_checkout');
        }

        $cartProducts = $this->loadCartProducts($cartProductIds);
        $total = $this->calculateTotal($cartProducts);

        // Fill required Customer fields from the logged-in account.
        $user = $this->getUser();
        $customerName = 'Guest';
        $customerEmail = 'guest@example.com';
        if ($user instanceof \App\Entity\User) {
            $customerName = $user->getUserIdentifier();
            $customerEmail = $user->getEmail() ?: ($user->getUserIdentifier() . '@example.com');
        }

        // Reuse an existing customer by email if possible
        $customer = $this->customerRepository->findOneBy(['Email' => $customerEmail]);
        if (!$customer) {
            $customer = new Customer();
        }
        $customer->setName($customerName);
        $customer->setEmail($customerEmail);
        $customer->setPhone(null);
        $customer->setCreateAt($customer->getCreateAt() ?? new \DateTimeImmutable());

        $order = new Order();
        $order->setCreateAt(new \DateTimeImmutable());
        $order->setStatus('Pending');
        $order->setTotal($total);
        $order->setPaymentMethod($paymentMethod);
        $order->setCustomer($customer);

        if ($user instanceof \App\Entity\User) {
            $order->setCreatedBy($user);
        }

        $lineNames = [];
        foreach (array_values($cartProductIds) as $productId) {
            if (!isset($cartProducts[$productId])) {
                continue;
            }
            $product = $cartProducts[$productId];
            $order->addProduct($product);
            $lineNames[] = $product->getName() ?? '';
        }

        $lineNames = array_values(array_filter($lineNames, static fn (string $n): bool => $n !== ''));
        $productsPart = implode(', ', $lineNames);
        $customerDisplay = trim((string) ($customer->getName() ?? ''));
        if ($customerDisplay === '') {
            $customerDisplay = 'Guest';
        }
        if ($productsPart !== '') {
            $orderLabel = sprintf('%s — %s', $productsPart, $customerDisplay);
        } else {
            $orderLabel = sprintf('Order — %s', $customerDisplay);
        }
        if (mb_strlen($orderLabel) > 255) {
            $orderLabel = mb_substr($orderLabel, 0, 252).'…';
        }
        $order->setName($orderLabel);

        $entityManager->persist($customer);
        $entityManager->persist($order);
        $entityManager->flush();

        // Clear cart
        $request->getSession()->remove(self::CART_SESSION_KEY);

        $this->addFlash('success', 'Order placed successfully. Your receipt is ready.');

        return $this->redirectToRoute('customer_receipt', ['id' => $order->getId()]);
    }

    #[Route('/customer-landing/receipt/{id}', name: 'customer_receipt', methods: ['GET'])]
    public function receipt(Request $request, Order $order): Response
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true)) {
                return $this->render('customer/receipt.html.twig', ['order' => $order]);
            }

            if ($order->getCreatedBy() && $order->getCreatedBy()->getId() === $user->getId()) {
                return $this->render('customer/receipt.html.twig', ['order' => $order]);
            }
        }

        throw new AccessDeniedException('You cannot view this receipt.');
    }

    /**
     * @param iterable<Product> $products
     *
     * @return array{menu_categories: list<\App\Entity\Category>, has_uncategorized_product: bool}
     */
    private function menuCategoryContext(iterable $products): array
    {
        $menuCategories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        $hasUncategorizedProduct = false;
        foreach ($products as $p) {
            if (null === $p->getCategory()) {
                $hasUncategorizedProduct = true;
                break;
            }
        }

        return [
            'menu_categories' => $menuCategories,
            'has_uncategorized_product' => $hasUncategorizedProduct,
        ];
    }

    private function resolveCartReturnRoute(Request $request): string
    {
        if ('menu' === (string) $request->request->get('_cart_return')) {
            return 'customer_menu';
        }

        return 'app_home';
    }

    /**
     * @return array<int,int> productId => productId
     */
    private function getCartProductIds(Request $request): array
    {
        $cart = $request->getSession()->get(self::CART_SESSION_KEY, []);
        if (!is_array($cart)) {
            return [];
        }

        $cartProductIds = [];
        foreach ($cart as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $cartProductIds[$productId] = $productId;
            }
        }

        return $cartProductIds;
    }

    /**
     * @param array<int,int> $cartProductIds
     * @return array<int,Product> productId => Product
     */
    private function loadCartProducts(array $cartProductIds): array
    {
        $ids = array_values($cartProductIds);
        $products = $this->productRepository->findBy(['id' => $ids]);

        $map = [];
        foreach ($products as $product) {
            $map[(int) $product->getId()] = $product;
        }

        return $map;
    }

    /**
     * @param array<int,Product> $products
     */
    private function calculateTotal(array $products): float
    {
        $total = 0.0;
        foreach ($products as $product) {
            $price = $product->getPrice();
            if ($price !== null) {
                $total += (float) $price;
            }
        }

        return round($total, 2);
    }
}

