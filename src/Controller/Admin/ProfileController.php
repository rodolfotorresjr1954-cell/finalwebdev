<?php

namespace App\Controller\Admin;

use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/profile')]
#[IsGranted('ROLE_ADMIN')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ActivityLogService $activityLogService
    ) {
    }

    #[Route('', name: 'admin_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('admin/profile/show.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/edit', name: 'admin_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedException();
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_password_reset' => false,
        ]);
        
        // Remove roles and isActive from profile edit
        $form->remove('roles');
        $form->remove('isActive');
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->activityLogService->logUpdate(
                $user,
                'User',
                $user->getId(),
                ['username' => $user->getUsername(), 'email' => $user->getEmail()],
                "Updated own profile"
            );

            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('admin_profile_show');
        }

        return $this->render('admin/profile/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'admin_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new AccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Verify current password
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->render('admin/profile/change_password.html.twig', [
                    'form' => $form,
                ]);
            }

            /** @var string $newPassword */
            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

            $this->entityManager->flush();

            $this->activityLogService->logPasswordChange($user);

            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute('admin_profile_show');
        }

        return $this->render('admin/profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}

