<?php

namespace App\Security;

use App\Entity\User;
use App\Service\ActivityLogService;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ActivityLogService $activityLogService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $jwt = $this->jwtManager->create($user);

            return new JWTAuthenticationSuccessResponse($jwt);
        }

        $this->activityLogService->logLogin($user, 'mobile');

        $jwt = $this->jwtManager->create($user);

        return new JWTAuthenticationSuccessResponse($jwt, [
            'user' => [
                'username' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => true === $user->isVerified(),
            ],
        ]);
    }
}
