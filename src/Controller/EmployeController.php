<?php

namespace App\Controller;

use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employes')]
class EmployeController extends AbstractController
{
  
    // LISTE DES EMPLOYÉS (Admin uniquement)
  
    #[Route('', methods: ['GET'])]
    #[Route('/', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $employes = $em->getRepository(Employe::class)->findAll();

        return $this->json([
            'success' => true,
            'count' => count($employes),
            'data' => array_map(fn($e) => $e->toArray(), $employes)
        ]);
    }

  
    // CRÉATION (Admin uniquement)
   
    #[Route('', methods: ['POST'])]
    #[Route('/', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Champs requis
        $required = ['nom', 'prenom', 'email', 'poste', 'telephone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Champ requis manquant: $field"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérification unicité email
        if ($em->getRepository(Employe::class)->findOneBy(['email' => $data['email']])) {
            return $this->json([
                'success' => false,
                'message' => 'Email déjà utilisé'
            ], Response::HTTP_CONFLICT);
        }

        $employe = new Employe();
        $employe->setNom($data['nom']);
        $employe->setPrenom($data['prenom']);
        $employe->setEmail($data['email']);
        $employe->setPoste($data['poste']);
        $employe->setTelephone($data['telephone']);

        // Date embauche (optionnel)
        $employe->setDateEmbauche(
            isset($data['date_embauche'])
                ? new \DateTime($data['date_embauche'])
                : new \DateTime()
        );

        // Matricule optionnel
        if (!empty($data['matricule'])) {
            if ($em->getRepository(Employe::class)->findOneBy(['matricule' => $data['matricule']])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Matricule déjà utilisé'
                ], Response::HTTP_CONFLICT);
            }
            $employe->setMatricule($data['matricule']);
        }

        // Validation
        $errors = $validator->validate($employe);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $messages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($employe);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Employé créé avec succès',
            'data' => $employe->toArray()
        ], Response::HTTP_CREATED);
    }


    // DÉTAIL (Admin ou employé concerné)
    
    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('view', 'employe')]
    public function show(Employe $employe): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $employe->toArray()
        ]);
    }

   
    // MODIFICATION (Admin uniquement) 
    
    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Request $request,
        Employe $employe,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['nom'])) $employe->setNom($data['nom']);
        if (isset($data['prenom'])) $employe->setPrenom($data['prenom']);
        if (isset($data['poste'])) $employe->setPoste($data['poste']);
        if (isset($data['telephone'])) $employe->setTelephone($data['telephone']);

        if (isset($data['email']) && $data['email'] !== $employe->getEmail()) {
            if ($em->getRepository(Employe::class)->findOneBy(['email' => $data['email']])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Email déjà utilisé'
                ], Response::HTTP_CONFLICT);
            }
            $employe->setEmail($data['email']);
        }

        $errors = $validator->validate($employe);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $messages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Employé mis à jour',
            'data' => $employe->toArray()
        ]);
    }

   
    // SUPPRESSION (Admin uniquement)
    
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Employe $employe, EntityManagerInterface $em): JsonResponse
    {
        // Vérifier si l'employé est lié à un utilisateur
        if ($employe->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible: employé lié à un utilisateur'
            ], Response::HTTP_CONFLICT);
        }

        // Vérifier si l'employé a des pointages
        if ($employe->getPointages()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible: employé possède des pointages'
            ], Response::HTTP_CONFLICT);
        }

        $em->remove($employe);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Employé supprimé'
        ]);
    }

   
    // HISTORIQUE POINTAGES (Employé connecté ou Admin)
 
    #[Route('/{id}/historique-pointages', methods: ['GET'])]
    #[IsGranted('view', 'employe')]
    public function historique(Employe $employe): JsonResponse
    {
        $user = $this->getUser();
        
        // Vérification que l'utilisateur peut voir cet historique
        if (!$this->isGranted('ROLE_ADMIN') && $user->getEmploye() !== $employe) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'historique' => $employe->getHistoriquePointagesParDate()
        ]);
    }
}