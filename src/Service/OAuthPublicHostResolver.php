<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Google OAuth rejects redirect_uri values that use bare private IPs.
 * Map LAN IPs to *.nip.io so the redirect host is a public DNS name that still resolves locally.
 */
final class OAuthPublicHostResolver
{
    public function __construct(
        private readonly ?string $oauthPublicHost = null,
    ) {
    }

    public function resolve(string $host, int $port = 8000): string
    {
        if (null !== $this->oauthPublicHost && '' !== trim($this->oauthPublicHost)) {
            return trim($this->oauthPublicHost);
        }

        if ('127.0.0.1' === $host || 'localhost' === $host) {
            return $host;
        }

        if ($this->isPrivateIpv4($host)) {
            return $host.'.nip.io';
        }

        return $host;
    }

    public function rewriteOriginUrl(string $absoluteUrl, bool $preferLoopback = false): string
    {
        $parts = parse_url($absoluteUrl);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return $absoluteUrl;
        }

        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 8000;

        if ($preferLoopback && $this->isPrivateIpv4($host)) {
            $publicHost = '127.0.0.1';
        } else {
            $publicHost = $this->resolve($host, $port);
        }

        if ($publicHost === $host) {
            return $absoluteUrl;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $portSuffix = isset($parts['port']) && !\in_array((int) $parts['port'], [80, 443], true)
            ? ':'.$parts['port']
            : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return sprintf('%s://%s%s%s%s', $scheme, $publicHost, $portSuffix, $path, $query);
    }

    public function applyToRequestContext(\Symfony\Component\Routing\RequestContext $context): void
    {
        $host = $context->getHost();
        if ('' === $host) {
            return;
        }

        $publicHost = $this->resolve($host, $context->getHttpPort());
        if ($publicHost !== $host) {
            $context->setHost($publicHost);
        }
    }

    private function isPrivateIpv4(string $host): bool
    {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        return !filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
