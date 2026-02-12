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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    // =============================================
    // LISTE DES UTILISATEURS
    // =============================================
    #[Route('', name: 'user_index', methods: ['GET'])]
    #[Route('/', name: 'user_index_slash', methods: ['GET'])]
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

    // =============================================
    // DÉTAIL D'UN UTILISATEUR
    // =============================================
    #[Route('/{id}', name: 'user_show', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user)
        ]);
    }

    // =============================================
    // CRÉATION D'UN UTILISATEUR
    // =============================================
    #[Route('', name: 'user_create', methods: ['POST'])]
    #[Route('/', name: 'user_create_slash', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Vérification des champs requis
        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'email existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setNom($data['nom'] ?? null);
        $user->setPrenom($data['prenom'] ?? null);
        $user->setTelephone($data['telephone'] ?? null);
        $user->setIsActive($data['is_active'] ?? true);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());

        // Hash du mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Définition des rôles
        $user->setRoles($data['roles'] ?? ['ROLE_USER']);

        // Associer un employé si spécifié
        if (isset($data['employe_id']) && $data['employe_id'] !== null) {
            $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
            if ($employe) {
                $user->setEmploye($employe);
            }
        }

        // Validation
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $this->serializeUser($user)
        ], Response::HTTP_CREATED);
    }

    // =============================================
    // MODIFICATION D'UN UTILISATEUR
    // =============================================
    #[Route('/{id}', name: 'user_update', methods: ['PUT'])]
    #[Route('/{id}', name: 'user_patch', methods: ['PATCH'])]
    public function update(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs
        if (isset($data['email'])) {
            // Vérifier si le nouvel email n'est pas déjà utilisé
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Cet email est déjà utilisé'
                ], Response::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['nom'])) $user->setNom($data['nom']);
        if (isset($data['prenom'])) $user->setPrenom($data['prenom']);
        if (isset($data['telephone'])) $user->setTelephone($data['telephone']);
        if (isset($data['is_active'])) $user->setIsActive($data['is_active']);

        // Mise à jour du mot de passe
        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        // Mise à jour des rôles
        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        // Mise à jour de l'association employé
        if (array_key_exists('employe_id', $data)) {
            if ($data['employe_id'] === null) {
                $user->setEmploye(null);
            } else {
                $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
                if ($employe) {
                    $user->setEmploye($employe);
                } else {
                    return $this->json([
                        'success' => false,
                        'message' => 'Employé non trouvé'
                    ], Response::HTTP_NOT_FOUND);
                }
            }
        }

        $user->setUpdatedAt(new \DateTime());

        // Validation
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $this->serializeUser($user)
        ]);
    }

    // =============================================
    // SUPPRESSION D'UN UTILISATEUR
    // =============================================
    #[Route('/{id}', name: 'user_delete', methods: ['DELETE'])]
    public function delete(User $user, EntityManagerInterface $em): JsonResponse
    {
        // Vérifier si l'utilisateur est associé à un employé
        if ($user->getEmploye()) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer cet utilisateur car il est lié à un employé'
            ], Response::HTTP_CONFLICT);
        }

        $em->remove($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès'
        ]);
    }

    // =============================================
    // CHANGER LE STATUT ACTIF/INACTIF
    // =============================================
    #[Route('/{id}/toggle-status', name: 'user_toggle_status', methods: ['PATCH'])]
    public function toggleStatus(User $user, EntityManagerInterface $em): JsonResponse
    {
        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $user->isActive() ? 'Utilisateur activé' : 'Utilisateur désactivé',
            'is_active' => $user->isActive()
        ]);
    }

    // =============================================
    // RECHERCHE D'UTILISATEURS
    // =============================================
    #[Route('/search/by-email', name: 'user_search_by_email', methods: ['GET'])]
    public function searchByEmail(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $email = $request->query->get('email');
        
        if (!$email) {
            return $this->json([
                'success' => false,
                'message' => 'Paramètre email requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeUser($user)
        ]);
    }

    // =============================================
    // UTILISATEURS PAR RÔLE
    // =============================================
    #[Route('/by-role/{role}', name: 'user_by_role', methods: ['GET'])]
    public function getUsersByRole(string $role, EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $filteredUsers = [];

        foreach ($users as $user) {
            if (in_array($role, $user->getRoles())) {
                $filteredUsers[] = $this->serializeUser($user);
            }
        }

        return $this->json([
            'success' => true,
            'role' => $role,
            'count' => count($filteredUsers),
            'data' => $filteredUsers
        ]);
    }

    // =============================================
    // UTILISATEURS NON ASSOCIÉS À UN EMPLOYÉ
    // =============================================
    #[Route('/without-employe', name: 'user_without_employe', methods: ['GET'])]
    public function getUsersWithoutEmploye(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $filteredUsers = [];

        foreach ($users as $user) {
            if (!$user->getEmploye()) {
                $filteredUsers[] = $this->serializeUser($user);
            }
        }

        return $this->json([
            'success' => true,
            'count' => count($filteredUsers),
            'data' => $filteredUsers
        ]);
    }

    // =============================================
    // UTILITAIRE DE SÉRIALISATION
    // =============================================
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
            'created_at' => $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updated_at' => $user->getUpdatedAt() ? $user->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            'employe_linked' => $user->getEmploye() ? [
                'id' => $user->getEmploye()->getId(),
                'nom_complet' => $user->getEmploye()->getNomComplet(),
                'matricule' => $user->getEmploye()->getMatricule(),
                'poste' => $user->getEmploye()->getPoste()
            ] : null
        ];
    }
}