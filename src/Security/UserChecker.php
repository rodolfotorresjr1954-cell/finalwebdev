<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account is deactivated.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if user was deactivated while their session is still active
        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account is deactivated.');
        }

        if ($user->assignCustomerRoleIfNeeded()) {
            $this->entityManager->flush();
        }
    }
}


