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

#[Route('/api/pointages')]
class PointageController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EmployeRepository $employeRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('/face', name: 'api_pointage_face', methods: ['POST'])]
    public function pointageParVisage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $image = $data['image'] ?? null;
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if (!$image) {
            return $this->json([
                'success' => false,
                'error' => 'Image requise'
            ], 400);
        }

        $response = $this->httpClient->request('POST', $_ENV['FACE_SERVICE_URL'] . '/match', [
            'json' => [
                'image' => $image,
                'candidates' => $this->getCandidates(),
                'threshold' => 0.60
            ],
        ]);

        $result = $response->toArray(false);

       $employeeId = $result['employee_id'] ?? null;
$confidence = $result['confidence'] ?? 0;
$distance   = $result['distance'] ?? null;

// seuil de sécurité
$MIN_CONFIDENCE = 0.60;

if (!$employeeId || $confidence < $MIN_CONFIDENCE) {
    return $this->json([
        'success' => false,
        'message' => 'Visage non reconnu',
        'confidence' => $confidence,
        'distance' => $distance
    ]);
}

        $employe = $this->employeRepo->find($employeeId);

        $dernier = $employe->getDernierPointage();
        $type = 'ENTREE';

        if ($dernier && $dernier->getType() === 'ENTREE') {
            $type = 'SORTIE';
        }

        $pointage = new Pointage();
        $pointage->setEmploye($employe);
        $pointage->setType($type);
        $pointage->setConfidence($confidence);

        if ($latitude) $pointage->setLatitude($latitude);
        if ($longitude) $pointage->setLongitude($longitude);

        $this->em->persist($pointage);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => $type === 'ENTREE' ? 'Bienvenue' : 'Au revoir',
            'data' => $pointage->toArray()
        ]);
    }

    private function getCandidates(): array
    {
        $rows = $this->em->getRepository(FaceEncoding::class)->findAll();

        $candidates = [];
        foreach ($rows as $row) {
            $emp = $row->getEmploye();
            if (!$emp) continue;

            $enc = json_decode($row->getEncoding(), true);
            if (!is_array($enc)) continue;

            $candidates[] = [
                'employee_id' => (string) $emp->getId(),
                'encoding' => $enc,
            ];
        }

        return $candidates;
    }
}
