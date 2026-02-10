<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $data = [];

        foreach ($users as $user) {
            $data[] = $this->serializeUser($user);
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/{id}', name: 'user_show', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user)
        ]);
    }

    #[Route('/', name: 'user_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setNom($data['nom'] ?? null);
        $user->setPrenom($data['prenom'] ?? null);
        $user->setTelephone($data['telephone'] ?? null);
        $user->setIsActive($data['is_active'] ?? true);
        $user->setCreatedAt(new \DateTime());

        if (isset($data['password'])) {
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        $user->setRoles($data['roles'] ?? ['ROLE_USER']);

        // Associer un employé
        if (isset($data['employe_id'])) {
            $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
            if ($employe) {
                $user->setEmploye($employe);
            }
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'user_update', methods: ['PUT'])]
    public function update(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) $user->setEmail($data['email']);
        if (isset($data['nom'])) $user->setNom($data['nom']);
        if (isset($data['prenom'])) $user->setPrenom($data['prenom']);
        if (isset($data['telephone'])) $user->setTelephone($data['telephone']);
        if (isset($data['is_active'])) $user->setIsActive($data['is_active']);

        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        // Associer/dissocier employé
        if (isset($data['employe_id'])) {
            if ($data['employe_id'] === null) {
                $user->setEmploye(null);
            } else {
                $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
                if ($employe) {
                    $user->setEmploye($employe);
                }
            }
        }

        $user->setUpdatedAt(new \DateTime());

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user)
        ]);
    }

    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur supprimé'
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'nom_complet' => trim($user->getPrenom() . ' ' . $user->getNom()),
            'telephone' => $user->getTelephone(),
            'is_active' => $user->isActive(),
            'employe_linked' => $user->getEmploye() ? [
                'id' => $user->getEmploye()->getId(),
                'nom_complet' => $user->getEmploye()->getNomComplet(),
                'matricule' => $user->getEmploye()->getMatricule()
            ] : null
        ];
    }
}
