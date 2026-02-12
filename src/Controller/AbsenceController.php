<?php

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/absences')]
class AbsenceController extends AbstractController
{
    
    // LISTE DES ABSENCES (Admin seulement)
  
    #[Route('/', name: 'absence_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')] 
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // ... (code inchangé)
        $qb = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->join('a.employe', 'e')
            ->orderBy('a.date_debut', 'DESC');
        
        // ... filtres
        $absences = $qb->getQuery()->getResult();
        
        $data = [];
        foreach ($absences as $absence) {
            $data[] = $this->serializeAbsence($absence);
        }
        
        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

   
    // DÉTAIL D'UNE ABSENCE (Admin ou employé concerné)
 
    #[Route('/{id}', name: 'absence_show', methods: ['GET'])]
    #[IsGranted('view', 'absence')] // 
    public function show(Absence $absence): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->serializeAbsence($absence)
        ]);
    }

    
    // CRÉATION (Admin ou employé pour lui-même)
    
    #[Route('/', name: 'absence_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')] 
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Authentification requise'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        $absence = new Absence();
        $absence->setType($data['type'] ?? 'CONGE');
        $absence->setDateDebut(new \DateTime($data['date_debut']));
        $absence->setDateFin(new \DateTime($data['date_fin']));
        $absence->setMotif($data['motif'] ?? null);
        $absence->setStatut('EN_ATTENTE'); 
        $absence->setJustificatif($data['justificatif'] ?? null);
        $absence->setCreatedAt(new \DateTime());
        
        // Associer l'employé
        if (isset($data['employe_id'])) {
            //  VÉRIFICATION : Un admin peut créer pour n'importe qui
            // Un employé ne peut créer que pour lui-même
            if (!$this->isGranted('ROLE_ADMIN') && $user->getEmploye()->getId() != $data['employe_id']) {
                return $this->json([
                    'success' => false,
                    'error' => 'Vous ne pouvez créer une absence que pour vous-même'
                ], Response::HTTP_FORBIDDEN);
            }
            
            $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
            if (!$employe) {
                return $this->json([
                    'success' => false,
                    'error' => 'Employé non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }
            $absence->setEmploye($employe);
        } else {
            //  Si non spécifié, on prend l'employé connecté
            if (!$user->getEmploye()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucun employé associé'
                ], Response::HTTP_BAD_REQUEST);
            }
            $absence->setEmploye($user->getEmploye());
        }
        
        $errors = $validator->validate($absence);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $em->persist($absence);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Absence créée avec succès',
            'data' => $this->serializeAbsence($absence)
        ], Response::HTTP_CREATED);
    }

   
    // MODIFICATION (Admin seulement)

    #[Route('/{id}', name: 'absence_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')] 
    public function update(
        Request $request,
        Absence $absence,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['type'])) $absence->setType($data['type']);
        if (isset($data['date_debut'])) $absence->setDateDebut(new \DateTime($data['date_debut']));
        if (isset($data['date_fin'])) $absence->setDateFin(new \DateTime($data['date_fin']));
        if (isset($data['motif'])) $absence->setMotif($data['motif']);
        if (isset($data['statut'])) $absence->setStatut($data['statut']); //  Admin peut changer statut
        if (isset($data['justificatif'])) $absence->setJustificatif($data['justificatif']);
        
        $absence->setUpdatedAt(new \DateTime());
        
        $errors = $validator->validate($absence);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Absence mise à jour avec succès',
            'data' => $this->serializeAbsence($absence)
        ]);
    }

   
    // SUPPRESSION (Admin seulement)
    
    #[Route('/{id}', name: 'absence_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')] 
    public function delete(Absence $absence, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($absence);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Absence supprimée avec succès'
        ]);
    }

    
    // ABSENCES PAR EMPLOYÉ (Admin ou employé lui-même)
    
    #[Route('/employe/{employeId}', name: 'absence_by_employe', methods: ['GET'])]
    #[IsGranted('view', 'employe')] // (réutilise le voter Employe)
    public function getByEmploye(int $employeId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $employe = $em->getRepository(Employe::class)->find($employeId);
        
        if (!$employe) {
            return $this->json([
                'success' => false,
                'error' => 'Employé non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }
        
        $date = $request->query->get('date');
        $statut = $request->query->get('statut');
        
        $qb = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('a.date_debut', 'DESC');
        
        if ($date) {
            $qb->andWhere('a.date_debut <= :date')
               ->andWhere('a.date_fin >= :date')
               ->setParameter('date', new \DateTime($date));
        }
        
        if ($statut) {
            $qb->andWhere('a.statut = :statut')
               ->setParameter('statut', $statut);
        }
        
        $absences = $qb->getQuery()->getResult();
        
        $data = [];
        foreach ($absences as $absence) {
            $data[] = $this->serializeAbsence($absence);
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'employe' => $employe->toArray(),
                'absences' => $data,
                'count' => count($data)
            ]
        ]);
    }

    // STATISTIQUES (Admin seulement)
    #[Route('/statistiques/mensuelles', name: 'absence_statistiques_mensuelles', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')] 
    public function statistiquesMensuelles(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // ... code inchangé
        $mois = $request->query->get('mois', date('m'));
        $annee = $request->query->get('annee', date('Y'));
        
        // ... calculs statistiques
        $dateDebut = new \DateTime("$annee-$mois-01");
        $dateFin = clone $dateDebut;
        $dateFin->modify('last day of this month');
        
        $absences = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.date_debut <= :fin')
            ->andWhere('a.date_fin >= :debut')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->getQuery()
            ->getResult();
        
        $statsParType = [];
        $statsParStatut = [];
        $totalJours = 0;
        
        foreach ($absences as $absence) {
            $type = $absence->getType();
            $statut = $absence->getStatut();
            
            $debut = max($absence->getDateDebut(), $dateDebut);
            $fin = min($absence->getDateFin(), $dateFin);
            $jours = $debut->diff($fin)->days + 1;
            
            if (!isset($statsParType[$type])) $statsParType[$type] = 0;
            if (!isset($statsParStatut[$statut])) $statsParStatut[$statut] = 0;
            
            $statsParType[$type] += $jours;
            $statsParStatut[$statut] += $jours;
            $totalJours += $jours;
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'mois' => $mois,
                'annee' => $annee,
                'total_jours_absence' => $totalJours,
                'par_type' => $statsParType,
                'par_statut' => $statsParStatut,
                'absences_count' => count($absences)
            ]
        ]);
    }

    private function serializeAbsence(Absence $absence): array
    {
        return [
            'id' => $absence->getId(),
            'type' => $absence->getType(),
            'date_debut' => $absence->getDateDebut()?->format('Y-m-d'),
            'date_fin' => $absence->getDateFin()?->format('Y-m-d'),
            'motif' => $absence->getMotif(),
            'statut' => $absence->getStatut(),
            'justificatif' => $absence->getJustificatif(),
            'created_at' => $absence->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $absence->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'employe_id' => $absence->getEmploye()?->getId(),
            'employe_nom_complet' => $absence->getEmploye()?->getNomComplet(),
            'employe_matricule' => $absence->getEmploye()?->getMatricule(),
            'duree_jours' => $absence->getDateDebut() && $absence->getDateFin() ? 
                $absence->getDateDebut()->diff($absence->getDateFin())->days + 1 : 0
        ];
    }
}