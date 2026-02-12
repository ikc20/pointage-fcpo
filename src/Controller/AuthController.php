<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface; // ✅ AJOUTER
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthController extends AbstractController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepo,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em // ✅ AJOUTER
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], 400);
        }

        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur introuvable'
            ], 404);
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe incorrect'
            ], 401);
        }

        if (!$user->isActive()) {
            return $this->json([
                'success' => false,
                'message' => 'Compte désactivé'
            ], 403);
        }

        $user->setLastLogin(new \DateTime());
        $em->flush(); // utilisation de EntityManagerInterface

        $token = $jwtManager->create($user);

        return $this->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'nom_complet' => trim($user->getPrenom().' '.$user->getNom()),
                'is_active' => $user->isActive(),
                'employe_linked' => $user->getEmploye() ? [
                    'id' => $user->getEmploye()->getId(),
                    'nom_complet' => $user->getEmploye()->getNomComplet(),
                    'matricule' => $user->getEmploye()->getMatricule(),
                    'poste' => $user->getEmploye()->getPoste()
                ] : null
            ]
        ]);
    }
}