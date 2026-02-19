<?php

namespace App\Controller\Api;

use App\Entity\Pointage;
use App\Repository\EmployeRepository;
use App\Repository\PointageRepository;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/pointages')]
class PointageController extends AbstractController
{
    private float $FACE_THRESHOLD;
    private float $MIN_CONFIDENCE;
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; 
    private const MIN_POINTAGE_INTERVAL = 5; // secondes

    public function __construct(
        private HttpClientInterface $httpClient,
        private EmployeRepository $employeRepository,
        private PointageRepository $pointageRepository,
        private PlanningRepository $planningRepository, // ✅ AJOUTÉ
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private RateLimiterFactory $faceLimiter
    ) {
        $this->FACE_THRESHOLD = (float)($_ENV['FACE_THRESHOLD'] ?? 0.60);
        $this->MIN_CONFIDENCE = (float)($_ENV['FACE_MIN_CONFIDENCE'] ?? 0.40);
    }

    #[Route('/face', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function pointageParVisage(Request $request): JsonResponse
    {
        $limiter = $this->faceLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json([
                'success' => false,
                'code' => 'RATE_LIMIT',
                'message' => 'Trop de requêtes. Veuillez patienter.'
            ], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['image'])) {
            return $this->json([
                'success' => false,
                'code' => 'IMAGE_MISSING',
                'message' => 'Image requise'
            ], 400);
        }

        // Validation du format (mobile envoie data:image/...)
        if (!preg_match('/^data:image\/(jpeg|png);base64,/', $data['image'])) {
            return $this->json([
                'success' => false,
                'code' => 'INVALID_IMAGE_FORMAT',
                'message' => 'Format d\'image invalide. Utilisez JPEG ou PNG.'
            ], 400);
        }

        // Extraction du base64 pur pour Flask
        $base64Only = preg_replace(
            '#^data:image/\w+;base64,#i',
            '',
            $data['image']
        );
        $base64Only = preg_replace('/\s+/', '', $base64Only);

        // Validation base64 plus sûre
        $decoded = base64_decode($base64Only, true);
        if ($decoded === false) {
            return $this->json([
                'success' => false,
                'code' => 'INVALID_BASE64',
                'message' => 'Image corrompue ou encodage invalide'
            ], 400);
        }

        if (strlen($decoded) > self::MAX_IMAGE_SIZE) {
            return $this->json([
                'success' => false,
                'code' => 'IMAGE_TOO_LARGE',
                'message' => 'Image trop volumineuse (max 5MB)'
            ], 400);
        }

        try {
            $candidates = $this->employeRepository->getAllEncodings();

            $this->logger->info('📤 Envoi à Flask', [
                'url' => $_ENV['FACE_SERVICE_URL'] . '/match',
                'candidates_count' => count($candidates),
                'threshold' => $this->FACE_THRESHOLD
            ]);

            $response = $this->httpClient->request('POST', $_ENV['FACE_SERVICE_URL'] . '/match', [
                'json' => [
                    'image' => $base64Only,
                    'candidates' => $candidates,
                    'threshold' => $this->FACE_THRESHOLD
                ],
                'timeout' => 30
            ]);

            $result = $response->toArray(false);
            
            $this->logger->info(' Réponse Flask reçue', $result);
            
            if (!isset($result['success'])) {
                throw new \RuntimeException('Réponse IA invalide : structure manquante');
            }
            
        } catch (\Throwable $e) {
            $this->logger->error(' Erreur service IA', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $_ENV['FACE_SERVICE_URL'] . '/match',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $this->logger->error('Réponse Flask', [
                        'status' => $response->getStatusCode(),
                        'body' => $response->getContent(false)
                    ]);
                }
            }
            
            return $this->json([
                'success' => false,
                'code' => 'FACE_SERVICE_DOWN',
                'message' => 'Service de reconnaissance temporairement indisponible'
            ], 503);
        }

        if (!$result['success'] || empty($result['employee_id'])) {
            $this->logger->warning(' Échec reconnaissance faciale', [
                'ip' => $request->getClientIp(),
                'user_id' => $this->getUser()?->getId(),
                'result' => $result
            ]);
            
            return $this->json([
                'success' => false,
                'code' => 'FACE_NOT_RECOGNIZED',
                'message' => 'Visage non reconnu'
            ], 401);
        }

        $score = max(0.0, min(1.0, (float)($result['confidence'] ?? 0)));

        if ($score < $this->MIN_CONFIDENCE) {
            $this->logger->warning('❌ Score de confiance insuffisant', [
                'score' => $score,
                'min_required' => $this->MIN_CONFIDENCE,
                'employe_id' => $result['employee_id'] ?? null,
                'ip' => $request->getClientIp()
            ]);
            
            return $this->json([
                'success' => false,
                'code' => 'LOW_CONFIDENCE',
                'message' => 'Score de confiance insuffisant'
            ], 401);
        }

        $employe = $this->employeRepository->find($result['employee_id']);
        if (!$employe) {
            $this->logger->error(' Employé introuvable', [
                'employee_id' => $result['employee_id'],
                'ip' => $request->getClientIp()
            ]);
            
            return $this->json([
                'success' => false,
                'code' => 'EMPLOYEE_NOT_FOUND',
                'message' => 'Employé introuvable'
            ], 404);
        }

        $last = $this->pointageRepository->findDernierPointage($employe);

        // Protection contre les doubles clics ultra-rapides
        if ($last) {
            $now = time();
            $lastTime = $last->getDateHeure()->getTimestamp();
            if (abs($now - $lastTime) < self::MIN_POINTAGE_INTERVAL) {
                $this->logger->info('⏱ Pointage trop rapide', [
                    'employe_id' => $employe->getId(),
                    'interval' => $now - $lastTime
                ]);
                
                return $this->json([
                    'success' => false,
                    'code' => 'TOO_FAST',
                    'message' => 'Pointage déjà enregistré récemment'
                ], 429);
            }
        }

        // ✅ LOGIQUE AVEC PLANNING
        $type = $this->determinerTypePointage($last);
        $planning = $this->planningRepository->findForToday();
        
        // Vérifier si on est dans les horaires autorisés
        if ($planning && !$this->estHoraireAutorise($planning)) {
            return $this->json([
                'success' => false,
                'code' => 'HORS_HORAIRE',
                'message' => 'Pointage en dehors des horaires autorisés'
            ], 403);
        }

        // Déterminer si ce sont des heures supp
        $heureActuelle = (int)(new \DateTime())->format('H');
        $estHeureSupp = $planning && $heureActuelle >= 19; // Exemple : après 19h

        // Nettoyage des données GPS
        $lat = $data['latitude'] ?? null;
        $lon = $data['longitude'] ?? null;

        if ($lat !== null && !is_numeric($lat)) {
            $lat = null;
        }
        if ($lon !== null && !is_numeric($lon)) {
            $lon = null;
        }

        $pointage = (new Pointage())
            ->setEmploye($employe)
            ->setType($type)
            ->setConfidence($score)
            ->setDistance($result['distance'] ?? null)
            ->setIpAddress($request->getClientIp())
            ->setMethode('FACE')
            ->setLatitude($lat !== null ? (string)$lat : null)
            ->setLongitude($lon !== null ? (string)$lon : null)
            // ✅ NOUVEAUX CHAMPS
            ->setEstHeureSupp($estHeureSupp)
            ->setPlanning($planning);

        $this->em->persist($pointage);
        $this->em->flush();

        $this->logger->info(' Pointage enregistré', [
            'employe_id' => $employe->getId(),
            'type' => $type,
            'score' => $score,
            'distance' => $result['distance'] ?? null,
            'gps' => $lat && $lon ? "$lat, $lon" : null,
            'ip' => $request->getClientIp(),
            'est_heure_supp' => $estHeureSupp,
            'planning' => $planning?->getType()
        ]);

        return $this->json([
            'success' => true,
            'message' => $type === Pointage::TYPE_ENTREE ? 'Entrée enregistrée' : 'Sortie enregistrée',
            'server_time' => (new \DateTime())->format(DATE_ATOM),
            'data' => $pointage->toArray()
        ]);
    }

    /**
     * Détermine le type de pointage (ENTREE/SORTIE)
     */
    private function determinerTypePointage(?Pointage $dernier): string
    {
        return (!$dernier || $dernier->getType() === Pointage::TYPE_SORTIE)
            ? Pointage::TYPE_ENTREE
            : Pointage::TYPE_SORTIE;
    }

    /**
     * Vérifie si l'heure actuelle est dans les horaires autorisés
     */
    private function estHoraireAutorise(?Planning $planning): bool
    {
        if (!$planning) {
            return true; // Pas de planning = toujours autorisé
        }

        $now = new \DateTime();
        $heureActuelle = (int)$now->format('H');
        $minutesActuelles = (int)$now->format('i');
        
        $heureDebut = (int)$planning->getHeureDebut()->format('H');
        $minutesDebut = (int)$planning->getHeureDebut()->format('i');
        
        $heureFin = (int)$planning->getHeureFin()->format('H');
        $minutesFin = (int)$planning->getHeureFin()->format('i');

        $tempsActuel = $heureActuelle * 60 + $minutesActuelles;
        $tempsDebut = $heureDebut * 60 + $minutesDebut;
        $tempsFin = $heureFin * 60 + $minutesFin;

        // Gérer le cas où la fin est le lendemain (ex: 22h - 6h)
        if ($tempsFin < $tempsDebut) {
            $tempsFin += 24 * 60;
            if ($tempsActuel < $tempsDebut) {
                $tempsActuel += 24 * 60;
            }
        }

        return $tempsActuel >= $tempsDebut && $tempsActuel <= $tempsFin;
    }
}