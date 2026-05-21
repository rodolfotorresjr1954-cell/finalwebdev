<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Skip if not the main request
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        
        // Check if user is logged in and account is disabled
        if ($user instanceof User && !$user->isActive()) {
            // Skip logout and login routes to avoid redirect loop
            $request = $event->getRequest();
            $route = $request->attributes->get('_route');
            
            if ($route !== 'app_logout' && $route !== 'app_login') {
                $session = $request->getSession();
                
                // Add flash message (must be done before clearing token)
                $session->getFlashBag()->add('error', 'Your account is deactivated.');
                
                // Clear the authentication token (log out user)
                $this->tokenStorage->setToken(null);
                
                // Redirect to login page
                // Note: We don't invalidate the session here to preserve the flash message
                // The session will be naturally cleared on next login or can be handled by logout route
                $loginUrl = $this->urlGenerator->generate('app_login', ['deactivated' => '1']);
                $response = new RedirectResponse($loginUrl);
                $event->setResponse($response);
            }
        }
    }
}

