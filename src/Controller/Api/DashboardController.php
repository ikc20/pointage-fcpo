<?php

namespace App\Controller\Api; 

use App\Entity\Employe;
use App\Entity\Pointage;
use App\Entity\Absence;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/statistiques', name: 'dashboard_statistiques', methods: ['GET'])]
    public function statistiques(EntityManagerInterface $em): JsonResponse
    {
        $today = new \DateTime();
        $todayStart = (clone $today)->setTime(0, 0, 0);
        $todayEnd = (clone $today)->setTime(23, 59, 59);
        
        // Totaux
        $totalEmployes = $em->getRepository(Employe::class)->count([]);
        $totalUsers = $em->getRepository(User::class)->count([]);
        $totalAbsences = $em->getRepository(Absence::class)->count([]);
        
        // Aujourd'hui
        $pointagesAujourdhui = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.date_heure BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $employesPresents = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.date_heure BETWEEN :start AND :end')
            ->andWhere('p.type = :type')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->setParameter('type', 'ENTREE')
            ->select('COUNT(DISTINCT p.employe)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $absencesAujourdhui = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.date_debut <= :today')
            ->andWhere('a.date_fin >= :today')
            ->andWhere('a.statut = :statut')
            ->setParameter('today', $today)
            ->setParameter('statut', Absence::STATUT_VALIDE)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Pointages récents
        $recentPointages = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->join('p.employe', 'e')
            ->orderBy('p.date_heure', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        $recentData = [];
        foreach ($recentPointages as $pointage) {
            $recentData[] = $pointage->toArray();
        }
        
        // Absences en attente
        $absencesEnAttente = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.statut = :statut')
            ->setParameter('statut', 'EN_ATTENTE')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Statistiques par poste
        $parPoste = $em->getRepository(Employe::class)->createQueryBuilder('e')
            ->select('e.poste, COUNT(e.id) as count')
            ->groupBy('e.poste')
            ->getQuery()
            ->getResult();
        
        // Pointages des 7 derniers jours
        $sevenDaysAgo = (new \DateTime())->modify('-7 days');
        $pointages7Jours = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.date_heure >= :date')
            ->setParameter('date', $sevenDaysAgo)
            ->select('DATE(p.date_heure) as date, COUNT(p.id) as count')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
        
        return $this->json([
            'success' => true,
            'data' => [
                'totaux' => [
                    'employes' => (int)$totalEmployes,
                    'utilisateurs' => (int)$totalUsers,
                    'absences_total' => (int)$totalAbsences,
                    'pointages_aujourdhui' => (int)$pointagesAujourdhui,
                    'employes_presents' => (int)$employesPresents,
                    'absences_aujourdhui' => (int)$absencesAujourdhui,
                    'absences_en_attente' => (int)$absencesEnAttente,
                    'employes_absents_sans_justif' => $totalEmployes - $employesPresents - $absencesAujourdhui
                ],
                'employes_par_poste' => $parPoste,
                'recent_pointages' => $recentData,
                'pointages_7_jours' => $pointages7Jours,
                'date_du_jour' => $today->format('Y-m-d')
            ]
        ]);
    }

    #[Route('/pointages-jour', name: 'dashboard_pointages_jour', methods: ['GET'])]
    public function pointagesDuJour(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $date = $request->query->get('date', date('Y-m-d'));
        $dateObj = new \DateTime($date);
        $dateStart = (clone $dateObj)->setTime(0, 0, 0);
        $dateEnd = (clone $dateObj)->setTime(23, 59, 59);
        
        // Récupérer tous les pointages du jour
        $pointages = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->join('p.employe', 'e')
            ->where('p.date_heure BETWEEN :start AND :end')
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Récupérer tous les employés
        $employes = $em->getRepository(Employe::class)->findAll();
        
        $result = [];
        $presentIds = [];
        
        // Organiser les pointages par employé
        foreach ($employes as $employe) {
            $pointagesEmploye = array_filter($pointages, function($p) use ($employe) {
                return $p->getEmploye()->getId() === $employe->getId();
            });
            
            // Trier par heure
            usort($pointagesEmploye, function($a, $b) {
                return $a->getDateHeure() <=> $b->getDateHeure();
            });
            
            $entrees = array_filter($pointagesEmploye, function($p) {
                return $p->getType() === 'ENTREE';
            });
            
            $sorties = array_filter($pointagesEmploye, function($p) {
                return $p->getType() === 'SORTIE';
            });
            
            // Si l'employé a pointé une entrée aujourd'hui, il est présent
            $present = !empty($entrees);
            if ($present) {
                $presentIds[] = $employe->getId();
            }
            
            $result[] = [
                'employe' => $employe->toArray(),
                'pointages' => array_map(function($p) {
                    return $p->toArray();
                }, $pointagesEmploye),
                'entrees' => array_map(function($p) {
                    return $p->toArray();
                }, $entrees),
                'sorties' => array_map(function($p) {
                    return $p->toArray();
                }, $sorties),
                'present' => $present,
                'derniere_entree' => !empty($entrees) ? end($entrees)->getDateHeure()->format('H:i') : null,
                'derniere_sortie' => !empty($sorties) ? end($sorties)->getDateHeure()->format('H:i') : null
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'pointages' => $result,
                'statistiques' => [
                    'total_employes' => count($employes),
                    'employes_presents' => count($presentIds),
                    'employes_absents' => count($employes) - count($presentIds),
                    'total_pointages' => count($pointages)
                ]
            ]
        ]);
    }

    #[Route('/historique-employe/{id}', name: 'dashboard_historique_employe', methods: ['GET'])]
    public function historiqueEmploye(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $employe = $em->getRepository(Employe::class)->find($id);
        
        if (!$employe) {
            return $this->json([
                'success' => false,
                'error' => 'Employé non trouvé'
            ], 404);
        }
        
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        
        $qb = $em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('p.date_heure', 'DESC');
        
        if ($dateDebut) {
            $qb->andWhere('p.date_heure >= :debut')
               ->setParameter('debut', new \DateTime($dateDebut));
        }
        
        if ($dateFin) {
            $qb->andWhere('p.date_heure <= :fin')
               ->setParameter('fin', new \DateTime($dateFin . ' 23:59:59'));
        }
        
        $pointages = $qb->getQuery()->getResult();
        
        // Organiser par date
        $parDate = [];
        foreach ($pointages as $pointage) {
            $date = $pointage->getDateHeure()->format('Y-m-d');
            
            if (!isset($parDate[$date])) {
                $parDate[$date] = [
                    'date' => $date,
                    'entrees' => [],
                    'sorties' => [],
                    'pointages' => []
                ];
            }
            
            $parDate[$date]['pointages'][] = $pointage->toArray();
            
            if ($pointage->getType() === 'ENTREE') {
                $parDate[$date]['entrees'][] = $pointage->getDateHeure()->format('H:i');
            } else {
                $parDate[$date]['sorties'][] = $pointage->getDateHeure()->format('H:i');
            }
        }
        
        // Calculer les totaux
        $totalEntrees = 0;
        $totalSorties = 0;
        $joursPresents = 0;
        
        foreach ($parDate as $dateData) {
            $totalEntrees += count($dateData['entrees']);
            $totalSorties += count($dateData['sorties']);
            if (count($dateData['entrees']) > 0) {
                $joursPresents++;
            }
        }
        
        // Récupérer les absences
        $absences = $em->getRepository(Absence::class)->createQueryBuilder('a')
            ->where('a.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('a.date_debut', 'DESC')
            ->getQuery()
            ->getResult();
        
        $absencesData = [];
        foreach ($absences as $absence) {
            $absencesData[] = [
                'type' => $absence->getType(),
                'date_debut' => $absence->getDateDebut()->format('Y-m-d'),
                'date_fin' => $absence->getDateFin()->format('Y-m-d'),
                'motif' => $absence->getMotif(),
                'statut' => $absence->getStatut(),
                'duree_jours' => $absence->getDateDebut()->diff($absence->getDateFin())->days + 1
            ];
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'employe' => $employe->toArray(),
                'historique' => array_values($parDate),
                'statistiques' => [
                    'total_pointages' => count($pointages),
                    'total_entrees' => $totalEntrees,
                    'total_sorties' => $totalSorties,
                    'jours_presents' => $joursPresents,
                    'jours_avec_pointage' => count($parDate)
                ],
                'absences' => $absencesData,
                'periode' => [
                    'debut' => $dateDebut,
                    'fin' => $dateFin
                ]
            ]
        ]);
    }

    #[Route('/rapport-mensuel', name: 'dashboard_rapport_mensuel', methods: ['GET'])]
    public function rapportMensuel(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $mois = $request->query->get('mois', date('m'));
        $annee = $request->query->get('annee', date('Y'));
        
        $dateDebut = new \DateTime("$annee-$mois-01");
        $dateFin = clone $dateDebut;
        $dateFin->modify('last day of this month');
        
        // Récupérer tous les employés
        $employes = $em->getRepository(Employe::class)->findAll();
        
        $rapport = [];
        $totalHeures = 0;
        $totalJoursTravail = 0;
        
        foreach ($employes as $employe) {
            // Pointages du mois
            $pointages = $em->getRepository(Pointage::class)->createQueryBuilder('p')
                ->where('p.employe = :employe')
                ->andWhere('p.date_heure >= :debut')
                ->andWhere('p.date_heure <= :fin')
                ->setParameter('employe', $employe)
                ->setParameter('debut', $dateDebut)
                ->setParameter('fin', $dateFin)
                ->orderBy('p.date_heure')
                ->getQuery()
                ->getResult();
            
            // Calculer les heures travaillées
            $heuresTravaillees = 0;
            $joursTravail = [];
            
            for ($i = 0; $i < count($pointages); $i++) {
                if ($pointages[$i]->getType() === 'ENTREE') {
                    $entree = $pointages[$i]->getDateHeure();
                    
                    // Chercher la sortie correspondante
                    $sortie = null;
                    for ($j = $i + 1; $j < count($pointages); $j++) {
                        if ($pointages[$j]->getType() === 'SORTIE') {
                            $sortie = $pointages[$j]->getDateHeure();
                            break;
                        }
                    }
                    
                    if ($sortie && $entree->format('Y-m-d') === $sortie->format('Y-m-d')) {
                        // Calculer la durée en heures
                        $diff = $entree->diff($sortie);
                        $heures = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
                        $heuresTravaillees += $heures;
                        
                        $date = $entree->format('Y-m-d');
                        if (!isset($joursTravail[$date])) {
                            $joursTravail[$date] = 0;
                        }
                        $joursTravail[$date] += $heures;
                    }
                }
            }
            
            // Jours d'absence dans le mois
            $absences = $em->getRepository(Absence::class)->createQueryBuilder('a')
                ->where('a.employe = :employe')
                ->andWhere('a.date_debut <= :fin')
                ->andWhere('a.date_fin >= :debut')
                ->andWhere('a.statut = :statut')
                ->setParameter('employe', $employe)
                ->setParameter('debut', $dateDebut)
                ->setParameter('fin', $dateFin)
                ->setParameter('statut', 'APPROUVEE')
                ->getQuery()
                ->getResult();
            
            $joursAbsence = 0;
            $absencesDetail = [];
            foreach ($absences as $absence) {
                $debut = max($absence->getDateDebut(), $dateDebut);
                $fin = min($absence->getDateFin(), $dateFin);
                
                $jours = $debut->diff($fin)->days + 1;
                $joursAbsence += $jours;
                
                $absencesDetail[] = [
                    'type' => $absence->getType(),
                    'dates' => $debut->format('Y-m-d') . ' au ' . $fin->format('Y-m-d'),
                    'jours' => $jours,
                    'motif' => $absence->getMotif()
                ];
            }
            
            $rapport[] = [
                'employe' => $employe->toArray(),
                'heures_travaillees' => round($heuresTravaillees, 2),
                'jours_travail' => count($joursTravail),
                'jours_absence' => $joursAbsence,
                'pointages_count' => count($pointages),
                'absences_detail' => $absencesDetail,
                'jours_travail_detail' => $joursTravail
            ];
            
            $totalHeures += $heuresTravaillees;
            $totalJoursTravail += count($joursTravail);
        }
        
        // Statistiques globales
        $joursOuvrables = $this->calculerJoursOuvrables($dateDebut, $dateFin);
        $tauxPresence = $totalJoursTravail / ($joursOuvrables * count($employes)) * 100;
        
        return $this->json([
            'success' => true,
            'data' => [
                'mois' => $mois,
                'annee' => $annee,
                'rapport' => $rapport,
                'statistiques_globales' => [
                    'total_heures' => round($totalHeures, 2),
                    'total_jours_travail' => $totalJoursTravail,
                    'jours_ouvrables' => $joursOuvrables,
                    'taux_presence' => round($tauxPresence, 2),
                    'moyenne_heures_par_employe' => count($employes) > 0 ? round($totalHeures / count($employes), 2) : 0
                ]
            ]
        ]);
    }

    private function calculerJoursOuvrables(\DateTime $debut, \DateTime $fin): int
    {
        $jours = 0;
        $current = clone $debut;
        
        while ($current <= $fin) {
            $jourSemaine = (int)$current->format('w');
            // Lundi à Vendredi (1-5)
            if ($jourSemaine >= 1 && $jourSemaine <= 5) {
                $jours++;
            }
            $current->modify('+1 day');
        }
        
        return $jours;
    }
}