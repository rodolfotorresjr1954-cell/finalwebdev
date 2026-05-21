<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MobileGoogleOAuthBridge;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RequestContext;

#[Route('/api/mobile/auth/google')]
final class MobileGoogleOAuthController extends AbstractController
{
    public function __construct(
        private readonly MobileGoogleOAuthBridge $oauthBridge,
    ) {
    }

    #[Route('/start', name: 'api_mobile_google_oauth_start', methods: ['GET'])]
    public function start(Request $request): JsonResponse
    {
        $context = (new RequestContext())->fromRequest($request);
        $data = $this->oauthBridge->createAuthorizationRequest($context);

        return $this->json([
            'success' => true,
            'message' => 'Open authorizationUrl in the device browser.',
            'data' => $data,
        ]);
    }

    #[Route('/complete', name: 'api_mobile_google_oauth_complete', methods: ['GET'])]
    public function complete(Request $request): JsonResponse
    {
        $state = (string) $request->query->get('state', '');
        if ('' === $state) {
            return $this->json(['success' => false, 'message' => 'Missing state.'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->oauthBridge->consumeResult($state);
        if (!\is_array($result)) {
            return $this->json([
                'success' => false,
                'message' => 'Sign-in is still in progress or has expired.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($result['error'])) {
            return $this->json([
                'success' => false,
                'message' => (string) $result['error'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'message' => 'Google sign-in successful',
            'token' => $result['token'] ?? null,
            'user' => $result['user'] ?? null,
        ]);
    }
}
