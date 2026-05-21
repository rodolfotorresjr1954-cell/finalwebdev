<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/customer')]
final class CustomerController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route(name: 'app_customer_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));

        $qb = $customerRepository->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'u')->addSelect('u');

        if ($search !== '') {
            $qb->andWhere('LOWER(c.Name) LIKE :search OR LOWER(c.Email) LIKE :search OR LOWER(c.Phone) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        // Catalog UI: fixed sort by name A→Z (no sort dropdown)
        $qb->orderBy('c.Name', 'ASC');

        $customers = $qb->getQuery()->getResult();

        return $this->render('customer/index.html.twig', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->remove('createAt');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setCreateAt(new \DateTimeImmutable());
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $customer->setCreatedBy($user);
            }

            $entityManager->persist($customer);
            $entityManager->flush();

            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logCreate(
                    $user,
                    'Customer',
                    $customer->getId(),
                    ['name' => $customer->getName(), 'email' => $customer->getEmail()],
                    sprintf('Created customer: %s', $customer->getName())
                );
            }
            
            $this->addFlash('success', 'Customer created successfully.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOwnerOrAdmin($customer);

        $form = $this->createForm(CustomerType::class, $customer);
        $form->remove('createAt');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logUpdate(
                    $user,
                    'Customer',
                    $customer->getId(),
                    ['name' => $customer->getName(), 'email' => $customer->getEmail()],
                    sprintf('Updated customer: %s', $customer->getName())
                );
            }
            
            $this->addFlash('success', 'Customer updated successfully.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->getString('_token'))) {
            $this->denyUnlessOwnerOrAdmin($customer);

            $customerName = $customer->getName();
            $customerEmail = $customer->getEmail();
            $customerId = $customer->getId();
            
            $entityManager->remove($customer);
            $entityManager->flush();

            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $this->activityLogService->logDelete(
                    $user,
                    'Customer',
                    $customerId,
                    ['name' => $customerName, 'email' => $customerEmail],
                    sprintf('Deleted customer: %s', $customerName)
                );
            }
            
            $this->addFlash('success', 'Customer deleted successfully.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }

    private function denyUnlessOwnerOrAdmin(Customer $customer): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        if ($user instanceof \App\Entity\User && $customer->getCreatedBy()?->getId() === $user->getId()) {
            return;
        }

        throw new AccessDeniedException('You cannot modify this record.');
    }
}
