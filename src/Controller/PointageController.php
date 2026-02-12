<?php

namespace App\Controller;

use App\Entity\Pointage;
use App\Entity\FaceEncoding;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/pointages')]
class PointageController extends AbstractController
{
    private float $FACE_THRESHOLD;
    private float $MAX_DISTANCE_METERS;
    private float $COMPANY_LAT;
    private float $COMPANY_LNG;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private RateLimiterFactory $faceLimiter
    ) {
        $this->FACE_THRESHOLD = (float)($_ENV['FACE_THRESHOLD'] ?? 0.60);
        $this->MAX_DISTANCE_METERS = (float)($_ENV['MAX_DISTANCE_METERS'] ?? 100);

        if (!isset($_ENV['COMPANY_LAT']) || !isset($_ENV['COMPANY_LNG'])) {
            throw new \RuntimeException('COMPANY_LAT et COMPANY_LNG doivent être définis dans .env');
        }

        $this->COMPANY_LAT = (float) $_ENV['COMPANY_LAT'];
        $this->COMPANY_LNG = (float) $_ENV['COMPANY_LNG'];
    }

    #[Route('/face', methods: ['POST'])]
    public function pointageParVisage(Request $request): JsonResponse
    {
        // =============================================
        // 1. AUTHENTIFICATION
        // =============================================
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        if (!$user || !$user->getEmploye()) {
            return $this->json([
                'success' => false,
                'message' => 'Compte non associé à un employé.'
            ], 403);
        }

        // =============================================
        // 2. RATE LIMITER - Protection brute-force
        // =============================================
        $limiter = $this->faceLimiter->create('face_' . $user->getId());
        if (!$limiter->consume(1)->isAccepted()) {
            $this->logger->warning('Rate limit dépassé', [
                'user_id' => $user->getId(),
                'email' => $user->getUserIdentifier()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'Trop de tentatives. Réessayez dans 1 minute.'
            ], 429);
        }

        // =============================================
        // 3. VALIDATION STRICTE DU PAYLOAD
        // =============================================
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Payload JSON invalide'
            ], 400);
        }

        // ✅Image : obligatoire + doit être une string base64
        $image = $data['image'] ?? null;
        if (!$image || !is_string($image)) {
            return $this->json([
                'success' => false,
                'message' => 'Image requise (format base64 valide)'
            ], 400);
        }

        // GPS : présence obligatoire
        if (!array_key_exists('latitude', $data) || !array_key_exists('longitude', $data)) {
            return $this->json([
                'success' => false,
                'message' => 'Géolocalisation requise'
            ], 400);
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];

        //  GPS : validation des plages réalistes
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $this->json([
                'success' => false,
                'message' => 'Coordonnées GPS invalides'
            ], 400);
        }

        // =============================================
        // 4. GÉOFENCING - Vérification zone entreprise
        // =============================================
        $distance = $this->calculateDistance(
            $latitude,
            $longitude,
            $this->COMPANY_LAT,
            $this->COMPANY_LNG
        );

        if ($distance > $this->MAX_DISTANCE_METERS) {
            $this->logger->warning('Tentative pointage hors zone', [
                'user_id' => $user->getId(),
                'distance' => round($distance, 2) . 'm',
                'max_allowed' => $this->MAX_DISTANCE_METERS . 'm'
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Vous êtes hors de la zone autorisée'
            ], 403);
        }

        // =============================================
        // 5. APPEL IA OPTIMISÉ - 1 seul candidat
        // =============================================
        try {
            $candidates = $this->getCandidatesForUser($user);
            
            if (empty($candidates)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Aucun visage enregistré'
                ], 404);
            }

            $response = $this->httpClient->request('POST', $_ENV['FACE_SERVICE_URL'] . '/match', [
                'json' => [
                    'image' => $image,
                    'candidates' => $candidates,
                    'threshold' => $this->FACE_THRESHOLD
                ],
                'timeout' => 15,
            ]);

            $result = $response->toArray(false);
            
            if (!is_array($result) || !array_key_exists('employee_id', $result)) {
                throw new \Exception('Format de réponse IA invalide');
            }

        } catch (\Exception $e) {
            $this->logger->error('Service IA indisponible', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Service de reconnaissance faciale indisponible'
            ], 503);
        }

        $employeeId = $result['employee_id'] ?? null;
$confidence = (float) ($result['confidence'] ?? 0);
$distance = (float) ($result['distance'] ?? 0);

// ✅ Utilise le même seuil que Flask (0.5 = 50% de confiance)
$MIN_CONFIDENCE = 0.45; // plus tolérant

if (!$employeeId || $confidence < $MIN_CONFIDENCE) {
    $this->logger->warning('Reconnaissance faciale échouée', [
        'user_id' => $user->getId(),
        'confidence' => round($confidence * 100, 2) . '%',
        'distance' => round($distance, 4),
        'threshold' => $this->FACE_THRESHOLD
    ]);

    return $this->json([
        'success' => false,
        'message' => 'Visage non reconnu',
        'confidence' => $confidence,
        'distance' => $distance
    ]);
}

        // =============================================
        // 7. VÉRIFICATION FINALE - Redondance sécurité
        // =============================================
        $employe = $this->employeRepo->find($employeeId);

        if (!$employe || $user->getEmploye()->getId() !== $employe->getId()) {
            $this->logger->warning('Tentative pointage non autorisé', [
                'user_id' => $user->getId(),
                'target_employee' => $employeeId,
                'actual_employee' => $user->getEmploye()->getId()
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Pointage non autorisé'
            ], 403);
        }

        // =============================================
        // 8. LOGIQUE ENTRÉE/SORTIE
        // =============================================
        $dernier = $employe->getDernierPointage();
        $type = 'ENTREE';
        $today = (new \DateTime())->format('Y-m-d');

        if (
            $dernier &&
            $dernier->getType() === 'ENTREE' &&
            $dernier->getDateHeure()->format('Y-m-d') === $today
        ) {
            $type = 'SORTIE';
        }

        // =============================================
        // 9. CRÉATION DU POINTAGE
        // =============================================
        $pointage = new Pointage();
        $pointage->setEmploye($employe);
        $pointage->setType($type);
        $pointage->setConfidence($confidence);
        $pointage->setLatitude($latitude);
        $pointage->setLongitude($longitude);
        $pointage->setMethode('FACE');
        $pointage->setDateHeure(new \DateTime());

        $this->em->persist($pointage);
        $this->em->flush();

        // =============================================
        // 10. LOG DE SUCCÈS
        // =============================================
        $this->logger->info('Pointage réussi', [
            'user_id' => $user->getId(),
            'employe_id' => $employe->getId(),
            'type' => $type,
            'confidence' => round($confidence * 100, 2) . '%',
            'distance' => round($distance, 2) . 'm'
        ]);

        // =============================================
        // 11. RÉPONSE SUCCÈS
        // =============================================
        return $this->json([
            'success' => true,
            'message' => $type === 'ENTREE' ? '✅ Entrée enregistrée' : '👋 Sortie enregistrée',
            'data' => [
                'type' => $type,
                'date' => (new \DateTime())->format('Y-m-d H:i:s'),
                'confidence' => $confidence,
                'distance' => round($distance, 2)
            ]
        ]);
    }

    /**
     * ✅ OPTIMISATION MAJEURE - Un seul candidat par utilisateur
     */
    private function getCandidatesForUser($user): array
    {
        $employe = $user->getEmploye();
        if (!$employe) {
            return [];
        }

        $encoding = $this->em
            ->getRepository(FaceEncoding::class)
            ->findOneBy(['employe' => $employe]);

        if (!$encoding) {
            return [];
        }

        $enc = json_decode($encoding->getEncoding(), true);
        if (!is_array($enc)) {
            return [];
        }

        return [[
            'employee_id' => (string) $employe->getId(),
            'encoding' => $enc,
        ]];
    }

    /**
     * Calcule la distance entre deux points GPS (formule de Haversine)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Rayon de la Terre en mètres
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}