<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orderRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = $request->query->get('status');
        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $qb = $orderRepository->createQueryBuilder('o')
            ->distinct()
            ->leftJoin('o.Customer', 'c')->addSelect('c')
            ->leftJoin('o.createdBy', 'u')->addSelect('u')
            ->leftJoin('o.products', 'op')->addSelect('op')
            ->leftJoin('op.Category', 'opc')->addSelect('opc');

        if ($search !== '') {
            $qb->andWhere('LOWER(o.Name) LIKE :search OR LOWER(c.Name) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($statusFilter && $statusFilter !== '') {
            $qb->andWhere('o.Status = :status')
               ->setParameter('status', $statusFilter);
        }

        switch ($sort) {
            case 'name':
                $qb->orderBy('o.Name', $dir);
                break;
            case 'total':
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

        $orders = $qb->getQuery()->getResult();

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'sort' => $sort,
            'dir' => strtolower($dir),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ProductRepository $productRepository): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order, ['quick_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order->setCreateAt(new \DateTimeImmutable());

            $label = null;
            foreach ($order->getProducts() as $p) {
                $label = $p->getName();
                break;
            }
            if ($label !== null && $label !== '') {
                $order->setName($label);
            } else {
                $order->setName('Order (no product selected)');
            }

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $order->setCreatedBy($user);
            }

            $entityManager->persist($order);
            $entityManager->flush();

            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logCreate(
                    $user,
                    'Order',
                    $order->getId(),
                    ['name' => $order->getName(), 'total' => $order->getTotal()],
                    sprintf('Created order: %s', $order->getName())
                );
            }
            
            $this->addFlash('success', 'Order created successfully.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $productPriceMap = [];
        foreach ($productRepository->findBy([], ['Name' => 'ASC']) as $pr) {
            $id = $pr->getId();
            if ($id !== null) {
                $productPriceMap[$id] = $pr->getPrice();
            }
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
            'productPriceMap' => $productPriceMap,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(int $id, OrderRepository $orderRepository, EntityManagerInterface $entityManager): Response
    {
        $order = $orderRepository->findOneWithDetails($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        if ($this->maybeUpgradeLegacyOrderName($order)) {
            $entityManager->flush();
        }

        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager, OrderRepository $orderRepository): Response
    {
        $this->denyUnlessOwnerOrAdmin($order);

        if ($order->getStatus() === 'Completed') {
            throw new AccessDeniedException('Completed orders cannot be modified.');
        }

        $orderId = $order->getId();
        if ($orderId !== null) {
            $withDetails = $orderRepository->findOneWithDetails($orderId);
            if ($withDetails !== null) {
                $order = $withDetails;
            }
        }

        if ($this->maybeUpgradeLegacyOrderName($order)) {
            $entityManager->flush();
        }

        $form = $this->createForm(OrderType::class, $order, [
            'customer_disabled' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->maybeUpgradeLegacyOrderName($order);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logUpdate(
                    $user,
                    'Order',
                    $order->getId(),
                    ['name' => $order->getName(), 'total' => $order->getTotal()],
                    sprintf('Updated order: %s', $order->getName())
                );
            }
            
            $this->addFlash('success', 'Order updated successfully.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->getString('_token'))) {
            $this->denyUnlessOwnerOrAdmin($order);

            $orderName = $order->getName();
            $orderTotal = $order->getTotal();
            $orderId = $order->getId();
            
            $entityManager->remove($order);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logDelete(
                    $user,
                    'Order',
                    $orderId,
                    ['name' => $orderName, 'total' => $orderTotal],
                    sprintf('Deleted order: %s', $orderName)
                );
            }
            
            $this->addFlash('success', 'Order deleted successfully.');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Replace old auto-generated titles like "Customer Order 20260321174535" with "Product — Customer".
     *
     * @return bool True if the stored name was updated
     */
    private function maybeUpgradeLegacyOrderName(Order $order): bool
    {
        $name = trim((string) ($order->getName() ?? ''));
        if ($name === '') {
            return false;
        }

        // Legacy staff/customer auto-titles: "Customer Order" or "Order" + timestamp digits
        if (!preg_match('/^(Customer Order|Order)\s+\d{12,17}$/iu', $name)) {
            return false;
        }

        $customerPart = trim((string) ($order->getCustomer()?->getName() ?? ''));
        if ($customerPart === '') {
            $customerPart = 'Guest';
        }

        $productNames = [];
        foreach ($order->getProducts() as $p) {
            $n = trim((string) ($p->getName() ?? ''));
            if ($n !== '') {
                $productNames[] = $n;
            }
        }
        $productNames = array_values(array_unique($productNames, SORT_STRING));
        $productsPart = implode(', ', $productNames);

        if ($productsPart !== '') {
            $orderLabel = sprintf('%s — %s', $productsPart, $customerPart);
        } else {
            $orderLabel = sprintf('Order — %s', $customerPart);
        }

        if (mb_strlen($orderLabel) > 255) {
            $orderLabel = mb_substr($orderLabel, 0, 252).'…';
        }

        if ($orderLabel === $name) {
            return false;
        }

        $order->setName($orderLabel);

        return true;
    }

    private function denyUnlessOwnerOrAdmin(Order $order): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true))) {
            return;
        }

        if ($user instanceof \App\Entity\User && $order->getCreatedBy()?->getId() === $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You cannot modify this record.');
    }
}
