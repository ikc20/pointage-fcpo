<?php
// src/Controller/TestController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test')]
class TestController extends AbstractController
{
    #[Route('/', name: 'api_test_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'API test working!',
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'success'
        ]);
    }
}