<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    #[Route('/api/health/ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['success' => true, 'message' => 'pong']);
    }
}