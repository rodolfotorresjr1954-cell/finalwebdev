<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $context = $this->buildContext($userRepository, $request);

        return $this->render('admin/users/index.html.twig', $context);
    }

    #[Route('/rows', name: 'admin_user_rows', methods: ['GET'])]
    public function rows(UserRepository $userRepository, Request $request): JsonResponse
    {
        $context = $this->buildContext($userRepository, $request);

        return $this->json($this->buildRowsPayload($context));
    }

    #[Route('/stream', name: 'admin_user_stream', methods: ['GET'])]
    public function liveStream(UserRepository $userRepository, Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($userRepository, $request): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $lastSignature = '';
            $maxSeconds = 25;
            $start = time();

            while ((time() - $start) < $maxSeconds) {
                $context = $this->buildContext($userRepository, $request);
                $payload = $this->buildRowsPayload($context);

                if ($payload['rowsSignature'] !== $lastSignature) {
                    $lastSignature = $payload['rowsSignature'];
                    echo "event: users\n";
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

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            // Set role as array
            $role = $form->get('roles')->getData();
            if ($role && is_string($role)) {
                $user->setRoles([$role]);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $roleString = is_string($role) ? $role : (is_array($role) ? ($role[0] ?? 'ROLE_USER') : 'ROLE_USER');
            $this->activityLogService->logCreate(
                $this->getUser(),
                'User',
                $user->getId(),
                ['username' => $user->getUsername(), 'email' => $user->getEmail(), 'role' => $roleString],
                "Created user account: {$user->getUsername()}"
            );

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/users/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);

        // Pre-populate role dropdown (read-only on edit): prefer Admin/Staff, else User
        $currentRole = 'ROLE_USER';
        foreach ($user->getRoles() as $r) {
            if ($r === 'ROLE_ADMIN' || $r === 'ROLE_STAFF') {
                $currentRole = $r;
                break;
            }
        }
        $form->get('roles')->setData($currentRole);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password reset if provided
            if ($form->has('plainPassword') && $form->get('plainPassword')->getData()) {
                /** @var string $plainPassword */
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            // Username, email, and role are disabled on edit — only isActive (and optional password) apply

            $this->entityManager->flush();

            $roleString = 'ROLE_USER';
            foreach ($user->getRoles() as $r) {
                if ($r === 'ROLE_ADMIN' || $r === 'ROLE_STAFF') {
                    $roleString = $r;
                    break;
                }
            }
            $this->activityLogService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                ['username' => $user->getUsername(), 'email' => $user->getEmail(), 'role' => $roleString, 'isActive' => $user->isActive()],
                "Updated user account: {$user->getUsername()}"
            );

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/reset-password', name: 'admin_user_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true, 'is_password_reset' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->entityManager->flush();

            $this->activityLogService->log(
                $this->getUser(),
                'PASSWORD_RESET',
                'User',
                $user->getId(),
                null,
                "Password reset for user: {$user->getUsername()}"
            );

            $this->addFlash('success', 'Password reset successfully.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/users/reset_password.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->getString('_token'))) {
            $username = $user->getUsername();
            $userId = $user->getId();

            $this->activityLogService->logDelete(
                $this->getUser(),
                'User',
                $userId,
                ['username' => $username, 'email' => $user->getEmail()],
                "Deleted user account: {$username}"
            );

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('toggle_status' . $user->getId(), $request->request->getString('_token'))) {
            $user->setIsActive(!$user->isActive());
            $status = $user->isActive() ? 'activated' : 'deactivated';

            $this->entityManager->flush();

            $currentRoles = $user->getRoles();
            $currentRole = 'ROLE_USER';
            foreach ($currentRoles as $r) {
                if ($r !== 'ROLE_USER') {
                    $currentRole = $r;
                    break;
                }
            }
            
            $this->activityLogService->logUpdate(
                $this->getUser(),
                'User',
                $user->getId(),
                ['username' => $user->getUsername(), 'isActive' => $user->isActive(), 'role' => $currentRole],
                "User account {$status}: {$user->getUsername()}"
            );

            $this->addFlash('success', "User account {$status} successfully.");
        }

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * @return array{users: list<User>, search: string, roleFilter: mixed, statusFilter: mixed}
     */
    private function buildContext(UserRepository $userRepository, Request $request): array
    {
        $search = trim((string) $request->query->get('search', ''));
        $roleFilter = $request->query->get('role');
        $statusFilter = $request->query->get('status');

        return [
            'users' => $this->findFilteredUsers($userRepository, $search, $roleFilter, $statusFilter),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
        ];
    }

    /**
     * @return list<User>
     */
    private function findFilteredUsers(
        UserRepository $userRepository,
        string $search,
        mixed $roleFilter,
        mixed $statusFilter,
    ): array {
        $qb = $userRepository->createQueryBuilder('u');

        if ($search !== '') {
            $qb->andWhere('u.username LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        if ($roleFilter === 'ROLE_USER') {
            $qb->andWhere('u.roles NOT LIKE :rAdmin AND u.roles NOT LIKE :rStaff')
                ->setParameter('rAdmin', '%ROLE_ADMIN%')
                ->setParameter('rStaff', '%ROLE_STAFF%');
        } elseif ($roleFilter) {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%'.$roleFilter.'%');
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $qb->andWhere('u.isActive = :status')
                ->setParameter('status', $statusFilter === 'active');
        }

        /** @var list<User> $users */
        $users = $qb->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @param array{users: list<User>, search: string, roleFilter: mixed, statusFilter: mixed} $context
     *
     * @return array{rowsHtml: string, rowsSignature: string, userCount: int}
     */
    private function buildRowsPayload(array $context): array
    {
        /** @var list<User> $users */
        $users = $context['users'];

        $rowsSignature = implode('|', array_map(
            static fn (User $user): string => sprintf(
                '%d:%s:%d',
                (int) $user->getId(),
                $user->isActive() ? '1' : '0',
                $user->getCreatedAt()?->getTimestamp() ?? 0
            ),
            $users
        ));

        return [
            'rowsHtml' => $this->renderView('admin/users/_rows.html.twig', [
                'users' => $users,
            ]),
            'rowsSignature' => $rowsSignature,
            'userCount' => \count($users),
        ];
    }
}

