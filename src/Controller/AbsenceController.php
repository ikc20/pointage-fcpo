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

#[Route('/api/absences')]
class AbsenceController extends AbstractController
{
    #[Route('/', name: 'absence_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Filtres
        $employeId = $request->query->get('employe_id');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        $statut = $request->query->get('statut');
        $type = $request->query->get('type');
        
        $qb = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->join('a.employe', 'e')
            ->orderBy('a.date_debut', 'DESC');
        
        if ($employeId) {
            $qb->andWhere('e.id = :employeId')
               ->setParameter('employeId', $employeId);
        }
        
        if ($dateDebut) {
            $qb->andWhere('a.date_debut >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($dateDebut));
        }
        
        if ($dateFin) {
            $qb->andWhere('a.date_fin <= :dateFin')
               ->setParameter('dateFin', new \DateTime($dateFin));
        }
        
        if ($statut) {
            $qb->andWhere('a.statut = :statut')
               ->setParameter('statut', $statut);
        }
        
        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }
        
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

    #[Route('/{id}', name: 'absence_show', methods: ['GET'])]
    public function show(Absence $absence): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->serializeAbsence($absence)
        ]);
    }

    #[Route('/', name: 'absence_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $absence = new Absence();
        $absence->setType($data['type'] ?? 'CONGE');
        $absence->setDateDebut(new \DateTime($data['date_debut']));
        $absence->setDateFin(new \DateTime($data['date_fin']));
        $absence->setMotif($data['motif'] ?? null);
        $absence->setStatut($data['statut'] ?? 'EN_ATTENTE');
        $absence->setJustificatif($data['justificatif'] ?? null);
        $absence->setCreatedAt(new \DateTime());
        
        // Associer l'employé
        if (isset($data['employe_id'])) {
            $employe = $em->getRepository(Employe::class)->find($data['employe_id']);
            if (!$employe) {
                return $this->json([
                    'success' => false,
                    'error' => 'Employé non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }
            $absence->setEmploye($employe);
        }
        
        // Validation
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

    #[Route('/{id}', name: 'absence_update', methods: ['PUT'])]
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
        if (isset($data['statut'])) $absence->setStatut($data['statut']);
        if (isset($data['justificatif'])) $absence->setJustificatif($data['justificatif']);
        
        $absence->setUpdatedAt(new \DateTime());
        
        // Validation
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

    #[Route('/{id}', name: 'absence_delete', methods: ['DELETE'])]
    public function delete(Absence $absence, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($absence);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Absence supprimée avec succès'
        ]);
    }

    #[Route('/employe/{employeId}', name: 'absence_by_employe', methods: ['GET'])]
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

    #[Route('/statistiques/mensuelles', name: 'absence_statistiques_mensuelles', methods: ['GET'])]
    public function statistiquesMensuelles(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $mois = $request->query->get('mois', date('m'));
        $annee = $request->query->get('annee', date('Y'));
        
        $dateDebut = new \DateTime("$annee-$mois-01");
        $dateFin = clone $dateDebut;
        $dateFin->modify('last day of this month');
        
        // Récupérer toutes les absences du mois
        $absences = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.date_debut <= :fin')
            ->andWhere('a.date_fin >= :debut')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->getQuery()
            ->getResult();
        
        // Statistiques par type
        $statsParType = [];
        $statsParStatut = [];
        $totalJours = 0;
        
        foreach ($absences as $absence) {
            $type = $absence->getType();
            $statut = $absence->getStatut();
            
            // Calculer le nombre de jours dans le mois
            $debut = max($absence->getDateDebut(), $dateDebut);
            $fin = min($absence->getDateFin(), $dateFin);
            $jours = $debut->diff($fin)->days + 1;
            
            if (!isset($statsParType[$type])) {
                $statsParType[$type] = 0;
            }
            $statsParType[$type] += $jours;
            
            if (!isset($statsParStatut[$statut])) {
                $statsParStatut[$statut] = 0;
            }
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
            'date_debut' => $absence->getDateDebut() ? $absence->getDateDebut()->format('Y-m-d') : null,
            'date_fin' => $absence->getDateFin() ? $absence->getDateFin()->format('Y-m-d') : null,
            'motif' => $absence->getMotif(),
            'statut' => $absence->getStatut(),
            'justificatif' => $absence->getJustificatif(),
            'created_at' => $absence->getCreatedAt() ? $absence->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updated_at' => $absence->getUpdatedAt() ? $absence->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            'employe_id' => $absence->getEmploye() ? $absence->getEmploye()->getId() : null,
            'employe_nom_complet' => $absence->getEmploye() ? $absence->getEmploye()->getNomComplet() : null,
            'employe_matricule' => $absence->getEmploye() ? $absence->getEmploye()->getMatricule() : null,
            'duree_jours' => $absence->getDateDebut() && $absence->getDateFin() ? 
                $absence->getDateDebut()->diff($absence->getDateFin())->days + 1 : 0
        ];
    }
}