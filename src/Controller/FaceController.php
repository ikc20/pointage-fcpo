<?php

namespace App\Controller;

use App\Entity\FaceEncoding;
use App\Repository\EmployeRepository;
use App\Repository\FaceEncodingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EmployeRepository $employeRepo,
        private readonly FaceEncodingRepository $faceEncodingRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    private function getFlaskBaseUrl(): string
    {
        $baseUrl = $_ENV['FACE_SERVICE_URL'] ?? 'http://127.0.0.1:5000';
        return rtrim($baseUrl, '/');
    }

    private function extractImageBase64(Request $request): ?string
    {
        // multipart/form-data : fichier "image"
        $file = $request->files->get('image');
        if ($file) {
            $contents = @file_get_contents($file->getPathname());
            if ($contents !== false) {
                return base64_encode($contents);
            }
        }

        // JSON : {"image":"...base64..."}
        $raw = $request->getContent();
        if (!empty($raw)) {
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $data = null;
            }

            if (is_array($data) && isset($data['image']) && is_string($data['image'])) {
                return $data['image'];
            }
        }

        return null;
    }

    private function callFlaskPost(string $path, array $payload, float $timeout = 10.0): array
    {
        $url = $this->getFlaskBaseUrl() . $path;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json'    => $payload,
                'timeout' => $timeout,
            ]);

            $status = $response->getStatusCode();

            try {
                $body = $response->toArray(false);
            } catch (\Throwable) {
                $body = $response->getContent(false);
            }

            return [
                'network_ok' => true,
                'status'     => $status,
                'ok'         => $status >= 200 && $status < 300,
                'body'       => $body,
                'url'        => $url,
            ];
        } catch (\Throwable $e) {
            return [
                'network_ok' => false,
                'status'     => 0,
                'ok'         => false,
                'body'       => $e->getMessage(),
                'url'        => $url,
            ];
        }
    }

    // ---------------- HEALTH ----------------

    #[Route('/api/face/health', name: 'api_face_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $flaskUrl = $this->getFlaskBaseUrl() . '/health';

        try {
            $response = $this->httpClient->request('GET', $flaskUrl, ['timeout' => 2.0]);
            $status   = $response->getStatusCode();

            try {
                $data = $response->toArray(false);
            } catch (\Throwable) {
                $data = $response->getContent(false);
            }

            $flaskOk = ($status === 200) && (is_array($data) ? (($data['success'] ?? false) === true) : false);

            return $this->json([
                'success' => $flaskOk,
                'symfony' => ['success' => true],
                'storage' => [
                    'type'  => 'symfony_db',
                    'count' => $this->faceEncodingRepo->count([]),
                ],
                'flask'   => [
                    'url'    => $flaskUrl,
                    'status' => $status,
                    'body'   => $data,
                ],
            ], $flaskOk ? 200 : 502);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'symfony' => ['success' => true],
                'flask'   => [
                    'url'   => $flaskUrl,
                    'error' => $e->getMessage(),
                ],
            ], 502);
        }
    }

    // ---------------- SAVE ENCODING (manuel) ----------------
    // Tu l'as utilisé avec curl /api/face/encoding/{id} ✅

    #[Route('/api/face/encoding/{id}', name: 'api_face_save_encoding', methods: ['POST'])]
    public function saveEncoding(int $id, Request $request): JsonResponse
    {
        $employe = $this->employeRepo->find($id);
        if (!$employe) {
            return $this->json(['success' => false, 'error' => 'Employe not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['encoding']) || !is_array($data['encoding'])) {
            return $this->json(['success' => false, 'error' => 'Missing/invalid encoding array'], 400);
        }

        $encodingJson = json_encode($data['encoding'], JSON_THROW_ON_ERROR);

        $face = $this->faceEncodingRepo->findOneBy(['employe' => $employe]);
        if (!$face) {
            $face = new FaceEncoding();
            $face->setEmploye($employe);
            $this->em->persist($face);
        }

        $face->setEncoding($encodingJson);
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'employe_id' => $id,
            'saved'      => true,
            'len'        => strlen($encodingJson),
        ]);
    }

    // ---------------- CANDIDATES ----------------
    // Renvoie la liste des encodings DB (pour debug / pour Flask)

    #[Route('/api/face/candidates', name: 'api_face_candidates', methods: ['GET'])]
    public function candidates(): JsonResponse
    {
        $rows = $this->faceEncodingRepo->findAll();

        $candidates = [];
        foreach ($rows as $row) {
            $emp = $row->getEmploye();
            if (!$emp) continue;

            $enc = json_decode($row->getEncoding(), true);
            if (!is_array($enc) || count($enc) === 0) continue;

            $candidates[] = [
                'employee_id' => (string) $emp->getId(),
                'encoding'    => $enc,
            ];
        }

        return $this->json([
            'success'    => true,
            'count'      => count($candidates),
            'candidates' => $candidates,
        ]);
    }

    // ---------------- REGISTER ----------------
    // Symfony stocke, Flask calcule l'encoding via /encode

    #[Route('/api/face/register/{id}', name: 'api_face_register', methods: ['POST'])]
    public function register(int $id, Request $request): JsonResponse
    {
        $imageB64 = $this->extractImageBase64($request);
        if ($imageB64 === null) {
            return $this->json(['success' => false, 'error' => 'Missing "image" (file upload or JSON field)'], 400);
        }

        $employe = $this->employeRepo->find($id);
        if (!$employe) {
            return $this->json(['success' => false, 'error' => "Employe $id not found"], 404);
        }

        $existing = $this->faceEncodingRepo->findOneBy(['employe' => $employe]);
        if ($existing) {
            return $this->json(['success' => false, 'error' => "Employee $id already registered"], 409);
        }

        $flaskRes = $this->callFlaskPost('/encode', ['image' => $imageB64], 20.0);

        if (!$flaskRes['network_ok']) {
            return $this->json(['success' => false, 'flask' => ['url' => $flaskRes['url'], 'error' => $flaskRes['body']]], 502);
        }

        if (!$flaskRes['ok']) {
            return $this->json(['success' => false, 'flask' => $flaskRes], $flaskRes['status']);
        }

        $body = is_array($flaskRes['body']) ? $flaskRes['body'] : null;
        $encoding = $body['encoding'] ?? null;

        if (!is_array($encoding) || count($encoding) === 0) {
            return $this->json(['success' => false, 'error' => 'Flask did not return a valid encoding', 'flask' => $flaskRes], 502);
        }

        $face = new FaceEncoding();
        $face->setEmploye($employe);
        $face->setEncoding(json_encode($encoding, JSON_THROW_ON_ERROR));

        $this->em->persist($face);
        $this->em->flush();

        return $this->json([
            'success'     => true,
            'employee_id' => (string) $id,
            'stored'      => 'symfony_db',
        ], 200);
    }

    // ---------------- RECOGNIZE ----------------
    // ✅ CORRIGÉ: envoie candidates + threshold à Flask /match

    #[Route('/api/face/recognize', name: 'api_face_recognize', methods: ['POST'])]
    public function recognize(Request $request): JsonResponse
    {
        $imageB64 = $this->extractImageBase64($request);
        if ($imageB64 === null) {
            return $this->json(['success' => false, 'error' => 'Missing "image" (file upload or JSON field)'], 400);
        }

        $rows = $this->faceEncodingRepo->findAll();
        if (!$rows) {
            return $this->json([
                'success'     => true,
                'employee_id' => null,
                'confidence'  => 0.0,
                'distance'    => null,
                'reason'      => 'no_known_faces_in_db',
            ], 200);
        }

        $candidates = [];
        foreach ($rows as $row) {
            $emp = $row->getEmploye();
            if (!$emp) continue;

            $decoded = json_decode($row->getEncoding(), true);
            if (!is_array($decoded) || count($decoded) === 0) continue;

            $candidates[] = [
                'employee_id' => (string) $emp->getId(),
                'encoding'    => $decoded,
            ];
        }

        // ✅ IMPORTANT: Flask /match attend "candidates" (pas "known")
        $flaskRes = $this->callFlaskPost('/match', [
            'image'      => $imageB64,
            'candidates' => $candidates,
            'threshold'  => 0.60,
        ], 25.0);

        if (!$flaskRes['network_ok']) {
            return $this->json([
                'success' => false,
                'symfony' => ['success' => true],
                'flask'   => ['url' => $flaskRes['url'], 'error' => $flaskRes['body']],
            ], 502);
        }

        // On renvoie exactement ce que Flask répond (employee_id, distance, confidence, etc.)
        return $this->json([
            'success' => $flaskRes['ok'],
            'symfony' => ['success' => true],
            'flask'   => [
                'url'    => $flaskRes['url'],
                'status' => $flaskRes['status'],
                'body'   => $flaskRes['body'],
            ],
        ], $flaskRes['status']);
    }

    // ---------------- RESET DB ----------------

    #[Route('/api/face/reset', name: 'api_face_reset', methods: ['POST'])]
    public function resetDbFaces(): JsonResponse
    {
        $all = $this->faceEncodingRepo->findAll();
        foreach ($all as $row) {
            $this->em->remove($row);
        }
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'All face encodings cleared from database'], 200);
    }
}
