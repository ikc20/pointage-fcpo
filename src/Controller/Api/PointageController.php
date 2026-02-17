<?php

namespace App\Controller\Api;

use App\Entity\Pointage;
use App\Repository\EmployeRepository;
use App\Repository\PointageRepository;
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

    public function __construct(
        private HttpClientInterface $httpClient,
        private EmployeRepository $employeRepository,
        private PointageRepository $pointageRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private RateLimiterFactory $faceLimiter
    ) {
        $this->FACE_THRESHOLD = (float)($_ENV['FACE_THRESHOLD'] ?? 0.60);
        $this->MIN_CONFIDENCE = (float)($_ENV['FACE_MIN_CONFIDENCE'] ?? 0.45);
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

        // ✅ Validation du format (mobile envoie data:image/...)
        if (!preg_match('/^data:image\/(jpeg|png);base64,/', $data['image'])) {
            return $this->json([
                'success' => false,
                'code' => 'INVALID_IMAGE_FORMAT',
                'message' => 'Format d\'image invalide. Utilisez JPEG ou PNG.'
            ], 400);
        }

        // 🔐 Extraction du base64 pur pour Flask
        $base64Only = preg_replace(
            '#^data:image/\w+;base64,#i',
            '',
            $data['image']
        );
        $base64Only = preg_replace('/\s+/', '', $base64Only);


        // 🔐 Validation base64 plus sûre
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

            // 🔥 ENVOI À FLASK AVEC BASE64 PUR
            $response = $this->httpClient->request('POST', $_ENV['FACE_SERVICE_URL'] . '/match', [
                'json' => [
                    'image' => $base64Only,  // ✅ BASE64 PUR pour Flask
                    'candidates' => $candidates,
                    'threshold' => $this->FACE_THRESHOLD
                ],
                'timeout' => 30
            ]);

            $result = $response->toArray(false);
            
            // 🔐 Validation de la structure de réponse
            if (!isset($result['success'])) {
                throw new \RuntimeException('Réponse IA invalide : structure manquante');
            }
            
        } catch (\Throwable $e) {
            $this->logger->error('❌ Erreur service IA', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'url' => $_ENV['FACE_SERVICE_URL'] . '/match',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Voir la réponse exacte si disponible
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
            return $this->json([
                'success' => false,
                'code' => 'FACE_NOT_RECOGNIZED',
                'message' => 'Visage non reconnu'
            ], 401);
        }

        $confidence = (float)$result['confidence'];
        if ($confidence < $this->MIN_CONFIDENCE) {
            return $this->json([
                'success' => false,
                'code' => 'LOW_CONFIDENCE',
                'message' => 'Confiance insuffisante'
            ], 401);
        }

        $employe = $this->employeRepository->find($result['employee_id']);
        if (!$employe) {
            return $this->json([
                'success' => false,
                'code' => 'EMPLOYEE_NOT_FOUND',
                'message' => 'Employé introuvable'
            ], 404);
        }

        $last = $this->pointageRepository->findDernierPointage($employe);
        $type = (!$last || $last->getType() === Pointage::TYPE_SORTIE)
            ? Pointage::TYPE_ENTREE
            : Pointage::TYPE_SORTIE;

        $pointage = (new Pointage())
            ->setEmploye($employe)
            ->setType($type)
            ->setConfidence($confidence)
            ->setDistance($result['distance'] ?? null)
            ->setIpAddress($request->getClientIp());

        $this->em->persist($pointage);
        $this->em->flush();

        $this->logger->info('Pointage enregistré', [
            'employe_id' => $employe->getId(),
            'type' => $type,
            'confidence' => $confidence
        ]);

        return $this->json([
            'success' => true,
            'message' => $type === 'ENTREE' ? 'Entrée enregistrée' : 'Sortie enregistrée',
            'server_time' => (new \DateTime())->format(DATE_ATOM),
            'data' => $pointage->toArray()
        ]);
    }
}