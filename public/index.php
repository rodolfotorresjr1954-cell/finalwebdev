<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

Request ::setTrustedProxies(
    proxies: ['0.0.0./0'],
    trustedHeaderSet: Request::HEADER_X_FORWARDED_FOR
    
    | Request::HEADER_X_FORWARDED_HOST
    | Request::HEADER_X_FORWARDED_PROTO
    | Request::HEADER_X_FORWARDED_PORT

    
);

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
