<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request): RedirectResponse
    {
        if ('1' === (string) $request->query->get('mobile')) {
            $state = $request->query->get('state');
            if (\is_string($state) && $state !== '' && $request->hasSession()) {
                $session = $request->getSession();
                $session->set('oauth_mobile', true);
                $session->set('oauth_mobile_state', $state);
            }
        }

        // Do not pass device_id/device_name here — KnpU treats unknown keys as OAuth scopes (invalid_scope).
        // Mobile uses http://127.0.0.1:8000 (registered in Google Console) via adb reverse.
        return $this->clientRegistry->getClient('google')->redirect();
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): Response
    {
        throw new \LogicException('Google authentication is handled by GoogleAuthenticator.');
    }
}
