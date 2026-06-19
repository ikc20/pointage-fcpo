<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestRouteController extends AbstractController
{
    #[Route('/api/test-route', name: 'api_test_route', methods: ['GET'])]
    public function test(): JsonResponse
    {
        return $this->json(['success' => true, 'message' => 'Test OK']);
    }
}
