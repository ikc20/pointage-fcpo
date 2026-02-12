<?php

namespace App\Controller\Admin;

use App\Entity\Employe;
use App\Repository\PointageRepository;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/location')]
#[IsGranted('ROLE_ADMIN')]
class LocationFraudController extends AbstractController
{
    public function __construct(
        private PointageRepository $pointageRepo,
        private EmployeRepository $employeRepo
    ) {}

    // =============================================
    // 1. TABLEAU DE BORD PRINCIPAL
    // =============================================
    #[Route('/', name: 'admin_location_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $employes = $this->employeRepo->findAll();
        $rapports = [];

        foreach ($employes as $employe) {
            $anomalies = $this->detecterAnomalies($employe);
            if (!empty($anomalies)) {
                $rapports[] = [
                    'employe_id' => $employe->getId(),
                    'nom_complet' => $employe->getNomComplet(),
                    'email' => $employe->getEmail(),
                    'anomalies' => $anomalies,
                    'score_risque' => $this->calculerScoreRisque($anomalies)
                ];
            }
        }

        return $this->json([
            'success' => true,
            'total_employes' => count($employes),
            'employes_a_risque' => count($rapports),
            'rapports' => $rapports
        ]);
    }

    // =============================================
    // 2. DÉTECTION DES ANOMALIES
    // =============================================
    private function detecterAnomalies(Employe $employe): array
    {
        $anomalies = [];
        $pointages = $employe->getPointages();
        
        if ($pointages->count() < 2) {
            return []; // Pas assez de données
        }

        // 📍 2.1 DISTANCE IMPOSSIBLE
        $dernier = null;
        foreach ($pointages as $pointage) {
            if (!$dernier) {
                $dernier = $pointage;
                continue;
            }

            $distance = $this->calculerDistance(
                $dernier->getLatitude(),
                $dernier->getLongitude(),
                $pointage->getLatitude(),
                $pointage->getLongitude()
            );

            $temps = $pointage->getDateHeure()->getTimestamp() - $dernier->getDateHeure()->getTimestamp();
            $heures = $temps / 3600;
            
            if ($heures > 0 && $distance > 100 && $distance / $heures > 800) {
                // 🚨 Plus de 800 km/h = voyage impossible
                $anomalies[] = [
                    'type' => 'vitesse_impossible',
                    'date1' => $dernier->getDateHeure()->format('Y-m-d H:i'),
                    'date2' => $pointage->getDateHeure()->format('Y-m-d H:i'),
                    'distance' => round($distance, 2) . ' km',
                    'vitesse' => round($distance / $heures, 2) . ' km/h',
                    'localisation1' => [
                        'lat' => $dernier->getLatitude(),
                        'lng' => $dernier->getLongitude()
                    ],
                    'localisation2' => [
                        'lat' => $pointage->getLatitude(),
                        'lng' => $pointage->getLongitude()
                    ]
                ];
            }

            $dernier = $pointage;
        }

        // 📍 2.2 EMPREINTE GPS UNIQUE (toujours le même point)
        $positions = [];
        foreach ($pointages as $p) {
            $cle = round($p->getLatitude(), 4) . ',' . round($p->getLongitude(), 4);
            $positions[$cle] = ($positions[$cle] ?? 0) + 1;
        }

        foreach ($positions as $position => $count) {
            if ($count >= 10 && count($positions) === 1) {
                $anomalies[] = [
                    'type' => 'position_fixe',
                    'message' => 'Toujours au même endroit depuis ' . $count . ' pointages',
                    'position' => $position
                ];
            }
        }

        // 📍 2.3 HORS ZONE AUTORISÉE
        $COMPANY_LAT = (float) $_ENV['COMPANY_LAT'];
        $COMPANY_LNG = (float) $_ENV['COMPANY_LNG'];
        $MAX_DISTANCE = (float) $_ENV['MAX_DISTANCE_METERS'] / 1000; // Convertir en km
        
        foreach ($pointages as $p) {
            $distance = $this->calculerDistance(
                $COMPANY_LAT,
                $COMPANY_LNG,
                $p->getLatitude(),
                $p->getLongitude()
            );
            
            if ($distance > $MAX_DISTANCE) {
                $anomalies[] = [
                    'type' => 'hors_zone',
                    'date' => $p->getDateHeure()->format('Y-m-d H:i'),
                    'distance' => round($distance, 2) . ' km',
                    'limite' => $MAX_DISTANCE . ' km'
                ];
                break; // Une seule suffit
            }
        }

        return $anomalies;
    }

    // =============================================
    // 3. HISTORIQUE GÉOGRAPHIQUE D'UN EMPLOYÉ
    // =============================================
    #[Route('/employe/{id}', name: 'admin_location_employe', methods: ['GET'])]
    public function historiqueEmploye(Employe $employe): JsonResponse
    {
        $pointages = $employe->getPointages();
        $historique = [];

        foreach ($pointages as $pointage) {
            $historique[] = [
                'id' => $pointage->getId(),
                'date' => $pointage->getDateHeure()->format('Y-m-d H:i:s'),
                'type' => $pointage->getType(),
                'latitude' => $pointage->getLatitude(),
                'longitude' => $pointage->getLongitude(),
                'methode' => $pointage->getMethode(),
                'confidence' => $pointage->getConfidence()
            ];
        }

        // Trier du plus récent au plus ancien
        usort($historique, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return $this->json([
            'success' => true,
            'employe' => [
                'id' => $employe->getId(),
                'nom_complet' => $employe->getNomComplet(),
                'email' => $employe->getEmail(),
                'matricule' => $employe->getMatricule()
            ],
            'total_pointages' => count($historique),
            'historique' => $historique,
            'anomalies' => $this->detecterAnomalies($employe)
        ]);
    }

    // =============================================
    // 4. CARTE DE CHALEUR (tous les pointages)
    // =============================================
    #[Route('/heatmap', name: 'admin_location_heatmap', methods: ['GET'])]
    public function heatmap(): JsonResponse
    {
        $pointages = $this->pointageRepo->findAll();
        $points = [];

        foreach ($pointages as $p) {
            $points[] = [
                'lat' => $p->getLatitude(),
                'lng' => $p->getLongitude(),
                'weight' => 1,
                'employe' => $p->getEmploye()->getNomComplet(),
                'date' => $p->getDateHeure()->format('Y-m-d')
            ];
        }

        return $this->json([
            'success' => true,
            'total' => count($points),
            'points' => $points
        ]);
    }

    // =============================================
    // 5. UTILITAIRE - CALCUL DE DISTANCE (KM)
    // =============================================
    private function calculerDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    private function calculerScoreRisque(array $anomalies): int
    {
        $score = 0;
        foreach ($anomalies as $a) {
            switch ($a['type']) {
                case 'vitesse_impossible': $score += 70; break;
                case 'hors_zone': $score += 50; break;
                case 'position_fixe': $score += 30; break;
            }
        }
        return min($score, 100);
    }
}