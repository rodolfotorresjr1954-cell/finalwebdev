<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BrevoContactService $brevoContactService,
    ) {
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send verification link to the registrant and alert the site owner.
     */
    public function sendVerificationEmail(User $user, string $verificationUrl): void
    {
        $username = $user->getUsername() ?? 'there';
        $email = $user->getEmail();

        if ($email === null || $email === '') {
            throw new \RuntimeException('Cannot send verification email: user has no email address.');
        }

        $html = sprintf(
            '<p>Hi %s,</p><p>Thanks for registering at Grilled &amp; Bites Burger. Please verify your email:</p>'
            .'<p><a href="%s" style="display:inline-block;padding:12px 24px;background:#b17418;color:#fff;text-decoration:none;border-radius:6px;">Verify email</a></p>'
            .'<p>Or copy this link:<br>%s</p>',
            htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8'),
        );

        $this->brevoContactService->sendEmail(
            $email,
            'Verify your Grilled & Bites Burger account',
            sprintf("Hi %s,\n\nPlease verify your account by opening this link:\n%s\n", $username, $verificationUrl),
            $html,
        );

        $this->brevoContactService->sendNewRegistrationAlert($username, $email);
    }

    public function verifyToken(string $token): ?User
    {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        $this->entityManager->flush();

        return $user;
    }

    public function needsVerification(User $user): bool
    {
        return !$user->isVerified();
    }
}
