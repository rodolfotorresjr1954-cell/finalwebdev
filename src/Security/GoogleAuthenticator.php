<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\MobileGoogleOAuthBridge;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ActivityLogService $activityLogService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly MobileGoogleOAuthBridge $mobileOAuthBridge,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
                $googleUser = $client->fetchUserFromToken($accessToken);
                if (!$googleUser instanceof GoogleUser) {
                    throw new CustomUserMessageAuthenticationException('Invalid response from Google.');
                }

                $googleData = $googleUser->toArray();
                $email = $googleData['email'] ?? null;
                if (!\is_string($email) || $email === '') {
                    throw new CustomUserMessageAuthenticationException('Google did not return an email address for this account.');
                }

                $user = $this->userRepository->findOneBy(['email' => $email]);

                if ($user instanceof User) {
                    if (!$this->isStaffPortalUser($user)) {
                        throw new CustomUserMessageAuthenticationException('Google sign-in is only available for staff accounts. Please use email and password to sign in as a customer.');
                    }

                    $this->ensureStaffRoleStored($user);
                    $user->setIsVerified(true);
                    $user->setVerificationToken(null);
                    $this->entityManager->flush();

                    return $user;
                }

                $newUser = new User();
                $newUser->setEmail($email);
                $newUser->setUsername($this->uniqueUsernameFromEmail($email));
                $newUser->setPassword($this->passwordHasher->hashPassword($newUser, bin2hex(random_bytes(32))));
                $newUser->setRoles(['ROLE_STAFF']);
                $newUser->setIsVerified(true);
                $newUser->setVerificationToken(null);

                $this->entityManager->persist($newUser);
                $this->entityManager->flush();

                return $newUser;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $this->activityLogService->logLogin($user);
        }

        if ($request->hasSession() && true === $request->getSession()->get('oauth_mobile')) {
            $state = (string) $request->getSession()->get('oauth_mobile_state', '');
            $request->getSession()->remove('oauth_mobile');
            $request->getSession()->remove('oauth_mobile_state');

            if ($user instanceof User && '' !== $state) {
                $jwt = $this->jwtManager->create($user);
                $this->mobileOAuthBridge->storeResult($state, [
                    'token' => $jwt,
                    'user' => [
                        'username' => $user->getUserIdentifier(),
                        'email' => $user->getEmail(),
                        'roles' => $user->getRoles(),
                        'verified' => true === $user->isVerified(),
                    ],
                ]);

                return new RedirectResponse($this->mobileOAuthBridge->buildCallbackUrl($state));
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception->getMessage();
        if ($exception->getPrevious() instanceof \Throwable) {
            $prevMsg = $exception->getPrevious()->getMessage();
            if (str_contains($prevMsg, 'redirect_uri_mismatch')) {
                $message = 'OAuth redirect URI mismatch. Ensure /connect/google/check is configured in Google Cloud Console.';
            }
        }

        if ($request->hasSession() && true === $request->getSession()->get('oauth_mobile')) {
            $state = (string) $request->getSession()->get('oauth_mobile_state', '');
            $request->getSession()->remove('oauth_mobile');
            $request->getSession()->remove('oauth_mobile_state');

            if ('' !== $state) {
                $this->mobileOAuthBridge->storeResult($state, ['error' => $message]);

                return new RedirectResponse($this->mobileOAuthBridge->buildCallbackUrl($state, true));
            }
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('oauth_error', $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function isStaffPortalUser(User $user): bool
    {
        $stored = $user->getStoredRoles();

        return \in_array('ROLE_STAFF', $stored, true) || \in_array('ROLE_ADMIN', $stored, true);
    }

    /**
     * Persist ROLE_STAFF for admins who only had ROLE_ADMIN stored (getRoles still grants access).
     */
    private function ensureStaffRoleStored(User $user): void
    {
        $withoutUser = array_values(array_filter(
            $user->getRoles(),
            static fn (string $r): bool => 'ROLE_USER' !== $r
        ));
        if (\in_array('ROLE_ADMIN', $withoutUser, true) && !\in_array('ROLE_STAFF', $withoutUser, true)) {
            $withoutUser[] = 'ROLE_STAFF';
            $user->setRoles(array_values(array_unique($withoutUser)));
        }
    }

    private function uniqueUsernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true) ?: 'staff';
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $local) ?? 'staff';
        if ('' === $base || '_' === $base) {
            $base = 'staff';
        }
        $base = substr($base, 0, 180);
        $candidate = $base;
        for ($i = 0; $i < 64; ++$i) {
            $existing = $this->userRepository->findOneBy(['username' => $candidate]);
            if (null === $existing) {
                return $candidate;
            }
            $suffix = '_' . bin2hex(random_bytes(3));
            $candidate = substr($base, 0, max(1, 180 - \strlen($suffix))) . $suffix;
        }

        return 'staff_' . bin2hex(random_bytes(12));
    }
}
