<?php

namespace App\Controller\Api; 

use App\Entity\Employe;
use App\Entity\Pointage;
use App\Repository\PlanningRepository;
use App\Repository\PointageRepository;
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

        $required = ['nom', 'prenom', 'email', 'poste', 'telephone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Champ requis manquant: $field"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

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

        $employe->setDateEmbauche(
            isset($data['date_embauche'])
                ? new \DateTime($data['date_embauche'])
                : new \DateTime()
        );

        if (!empty($data['matricule'])) {
            if ($em->getRepository(Employe::class)->findOneBy(['matricule' => $data['matricule']])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Matricule déjà utilisé'
                ], Response::HTTP_CONFLICT);
            }
            $employe->setMatricule($data['matricule']);
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

        $em->persist($employe);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Employé créé avec succès',
            'data' => $employe->toArray()
        ], Response::HTTP_CREATED);
    }

    /**
     * STATS POUR L'EMPLOYÉ (utilisé par HomeScreen)
     * AVEC PLANNING ACTIF, STATUT RÉEL ET GESTION DES RETARDS
     */
    #[Route('/{id}/stats', methods: ['GET'])]
    #[IsGranted('view', 'employe')]
    public function stats(
        Employe $employe, 
        PointageRepository $pointageRepo,
        PlanningRepository $planningRepo
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$this->isGranted('ROLE_ADMIN') && $user->getEmploye() !== $employe) {
            return $this->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        $hasFaceEncoding = $employe->getFaceEncoding() !== null;
        
        // Récupérer le planning actif
        $planning = $planningRepo->findForToday();
        
        // Récupérer les pointages du jour
        $today = new \DateTime();
        $todayStart = (clone $today)->setTime(0, 0, 0);
        $todayEnd = (clone $today)->setTime(23, 59, 59);
        
        $pointages = $pointageRepo->findForEmployeBetweenDates(
            $employe,
            $todayStart,
            $todayEnd
        );
        
        $todayStatus = 'ABSENT';
        $lastPointage = null;
        $todayPointagesCount = count($pointages);
        $estEnRetard = false;
        
        if ($todayPointagesCount > 0) {
            usort($pointages, fn($a, $b) => $b->getDateHeure() <=> $a->getDateHeure());
            $lastPointage = $pointages[0];
            
            if ($lastPointage->getType() === Pointage::TYPE_ENTREE) {
                $todayStatus = 'PRESENT';
                
                // Vérifier si c'est un retard
                if ($planning) {
                    $heureEntree = $lastPointage->getDateHeure();
                    $heureDebutPlanning = $planning->getHeureDebut();
                    
                    // Créer une DateTime avec la date d'aujourd'hui et l'heure du planning
                    $heureDebut = clone $heureEntree;
                    $heureDebut->setTime(
                        (int)$heureDebutPlanning->format('H'),
                        (int)$heureDebutPlanning->format('i'),
                        0
                    );
                    
                    // Comparer les heures
                    if ($heureEntree > $heureDebut) {
                        $estEnRetard = true;
                    }
                }
            } else {
                $todayStatus = 'ABSENT';
            }
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'hasFaceEncoding' => $hasFaceEncoding,
                'todayStatus' => $todayStatus,
                'estEnRetard' => $estEnRetard,
                'lastPointage' => $lastPointage ? [
                    'type' => $lastPointage->getType(),
                    'date_heure' => $lastPointage->getDateHeure()->format('c'),
                ] : null,
                'todayPointagesCount' => $todayPointagesCount,
                'pendingAbsences' => 0,
                'totalAbsenceDays' => 0,
                'nextAbsence' => null,
                'joursRestants' => 25,
                'planning' => $planning ? [
                    'type' => $planning->getType(),
                    'type_label' => $planning->getTypeLabel(),
                    'heure_debut' => $planning->getHeureDebut()?->format('H:i'),
                    'heure_fin' => $planning->getHeureFin()?->format('H:i'),
                    'pause_debut' => $planning->getPauseDebut()?->format('H:i'),
                    'pause_fin' => $planning->getPauseFin()?->format('H:i'),
                    'pause_obligatoire' => $planning->isPauseObligatoire(),
                ] : null,
            ]
        ]);
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
        if ($employe->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible: employé lié à un utilisateur'
            ], Response::HTTP_CONFLICT);
        }

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

    // HISTORIQUE POINTAGES avec PAGINATION
    #[Route('/{id}/historique-pointages', methods: ['GET'])]
    #[IsGranted('view', 'employe')]
    public function historique(
        Employe $employe, 
        Request $request
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$this->isGranted('ROLE_ADMIN') && $user->getEmploye() !== $employe) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $pointages = $employe->getPointages();
        $total = count($pointages);
        
        $pointagesArray = $pointages->toArray();
        usort($pointagesArray, fn($a, $b) => $b->getDateHeure() <=> $a->getDateHeure());
        
        $paginatedPointages = array_slice($pointagesArray, $offset, $limit);
        
        $historique = [];
        foreach ($paginatedPointages as $pointage) {
            $date = $pointage->getDateHeure()->format('Y-m-d');
            if (!isset($historique[$date])) {
                $historique[$date] = [
                    'date' => $date,
                    'pointages' => []
                ];
            }
            $historique[$date]['pointages'][] = $pointage->toArray();
        }
        
        $historique = array_values($historique);

        return $this->json([
            'success' => true,
            'data' => [
                'items' => $historique,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_items' => $total,
                    'total_pages' => ceil($total / $limit),
                    'has_next_page' => $page < ceil($total / $limit),
                    'has_previous_page' => $page > 1
                ]
            ]
        ]);
    }
}