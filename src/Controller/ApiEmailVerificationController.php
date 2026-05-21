<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $token = $payload['token'] ?? null;
        if (!\is_string($token) || $token === '') {
            return $this->json(['error' => 'Token is required'], 400);
        }

        $user = $this->emailVerificationService->verifyToken($token);
        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid or expired verification token'], 400);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => true === $user->isVerified(),
            ],
        ]);
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(
        #[CurrentUser] ?User $user,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        if (true === $user->isVerified()) {
            return $this->json(['error' => 'Email is already verified'], 400);
        }

        $token = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($token);
        $this->entityManager->flush();

        $verificationUrl = $urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);

        return $this->json([
            'success' => true,
            'message' => 'Verification email has been sent',
        ]);
    }

    #[Route('/resend-verification-request', name: 'api_resend_verification_request', methods: ['POST'])]
    public function resendVerificationRequest(Request $request, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $email = $payload['email'] ?? null;
        if (!\is_string($email) || $email === '') {
            return $this->json(['error' => 'Email is required'], 400);
        }

        // Prevent account enumeration: always return success message.
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User && true !== $user->isVerified()) {
            $token = $this->emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($token);
            $this->entityManager->flush();

            $verificationUrl = $urlGenerator->generate(
                'app_verify_email',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            try {
                $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
            } catch (\Throwable) {
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'If an unverified account exists for that email, a new verification email has been sent.',
        ]);
    }

    #[Route('/verification-status', name: 'api_verification_status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        return $this->json([
            'isVerified' => true === $user->isVerified(),
            'email' => $user->getEmail(),
        ]);
    }
}
