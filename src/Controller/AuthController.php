<?php

namespace App\Controller;

use App\Repository\EmployeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, EmployeRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], 400);
        }

        $employe = $repo->findOneBy(['email' => $email]);

        if (!$employe) {
            return $this->json([
                'success' => false,
                'message' => 'Employé non trouvé'
            ], 404);
        }

        // Pour la démo : comparaison simple
        if ($employe->getPassword() !== $password) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], 401);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $employe->getId(),
                'nom' => $employe->getNom(),
                'email' => $employe->getEmail(),
            ]
        ]);
    }
}
