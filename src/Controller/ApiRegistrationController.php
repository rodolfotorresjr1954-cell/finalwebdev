<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiRegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly ValidatorInterface $validator,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $username = $payload['username'] ?? null;
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        $missing = [];
        if (!\is_string($username) || $username === '') {
            $missing[] = 'username';
        }
        if (!\is_string($email) || $email === '') {
            $missing[] = 'email';
        }
        if (!\is_string($password) || $password === '') {
            $missing[] = 'password';
        }
        if ($missing !== []) {
            return $this->json([
                'error' => 'Missing required fields',
                'fields' => $missing,
            ], 400);
        }

        if (\strlen($username) < 3) {
            return $this->json(['error' => 'Username must be at least 3 characters'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }

        if (\strlen($password) < 6) {
            return $this->json(['error' => 'Password must be at least 6 characters'], 400);
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        if (null !== $userRepo->findOneBy(['username' => $username])) {
            return $this->json(['error' => 'Username already taken'], 409);
        }
        if (null !== $userRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->json(['error' => 'Validation failed', 'violations' => $errors], 400);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->activityLogService->logRegister($user, 'mobile');

        $token = $this->jwtManager->create($user);

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. You are signed in.',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isVerified' => true === $user->isVerified(),
            ],
        ], 201);
    }
}
