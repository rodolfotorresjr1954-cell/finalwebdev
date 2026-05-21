<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class MobileGoogleOAuthBridge
{
    private const STATE_PREFIX = 'mobile_oauth_state_';
    private const RESULT_PREFIX = 'mobile_oauth_result_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OAuthPublicHostResolver $oauthPublicHostResolver,
        private readonly string $mobileDeepLinkBase = 'act1://oauth/callback',
    ) {
    }

    /**
     * @return array{authorizationUrl: string, state: string}
     */
    public function createAuthorizationRequest(?RequestContext $requestContext = null): array
    {
        if ($requestContext instanceof RequestContext) {
            $this->oauthPublicHostResolver->applyToRequestContext($requestContext);
            $this->urlGenerator->setContext($requestContext);
        }

        $state = bin2hex(random_bytes(16));
        $this->cache->delete(self::STATE_PREFIX.$state);
        $this->cache->get(self::STATE_PREFIX.$state, function (ItemInterface $item): bool {
            $item->expiresAfter(600);

            return true;
        });

        $authorizationUrl = $this->urlGenerator->generate('connect_google_start', [
            'mobile' => '1',
            'state' => $state,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            'authorizationUrl' => $this->oauthPublicHostResolver->rewriteOriginUrl($authorizationUrl, true),
            'state' => $state,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function storeResult(string $state, array $payload): void
    {
        $this->cache->delete(self::RESULT_PREFIX.$state);
        $this->cache->get(self::RESULT_PREFIX.$state, function (ItemInterface $item) use ($payload): array {
            $item->expiresAfter(120);

            return $payload;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function consumeResult(string $state): ?array
    {
        if ('' === $state) {
            return null;
        }

        $key = self::RESULT_PREFIX.$state;
        $miss = new \stdClass();
        $payload = $this->cache->get($key, function (ItemInterface $item) use ($miss) {
            $item->expiresAfter(0);

            return $miss;
        });

        if ($payload === $miss || !\is_array($payload)) {
            return null;
        }

        $this->cache->delete($key);
        $this->cache->delete(self::STATE_PREFIX.$state);

        return $payload;
    }

    public function buildCallbackUrl(string $state, bool $failed = false): string
    {
        $query = 'state='.rawurlencode($state);
        if ($failed) {
            $query .= '&error=1';
        }

        return $this->mobileDeepLinkBase.'?'.$query;
    }
}
