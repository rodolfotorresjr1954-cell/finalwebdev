<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lightweight health-check endpoint used by the Docker HEALTHCHECK and any
 * external uptime monitors. It intentionally avoids touching the database so
 * it stays fast and never fails due to a DB issue masking a PHP-FPM problem.
 */
final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok'], JsonResponse::HTTP_OK);
    }
}
