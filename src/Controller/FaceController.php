<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    private function getFlaskBaseUrl(): string
    {
        // env FACE_SERVICE_URL ou 127.0.0.1:5000 par défaut
        $baseUrl = $_ENV['FACE_SERVICE_URL'] ?? 'http://127.0.0.1:5000';
        return rtrim($baseUrl, '/');
    }

    /**
     * Essaie de récupérer l'image :
     *  - d'abord depuis un fichier uploadé "image" (multipart/form-data)
     *  - sinon depuis le JSON {"image": "..."}
     */
    private function extractImageBase64(Request $request): ?string
    {
        // 1) multipart/form-data : fichier "image"
        $file = $request->files->get('image');
        if ($file) {
            $contents = @file_get_contents($file->getPathname());
            if ($contents !== false) {
                return base64_encode($contents);
            }
        }

        // 2) JSON : {"image": "..."}
        $raw = $request->getContent();
        if (!empty($raw)) {
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $data = null;
            }

            if (is_array($data) && isset($data['image']) && is_string($data['image'])) {
                return $data['image'];
            }
        }

        return null;
    }

    private function callFlask(string $path, array $payload): array
    {
        $baseUrl = $this->getFlaskBaseUrl();
        $url     = $baseUrl . $path;

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json'    => $payload,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            $body   = null;
            try {
                $body = $response->toArray(false);
            } catch (\Throwable) {
                $body = $response->getContent(false);
            }

            return [
                'ok'     => $status >= 200 && $status < 300,
                'status' => $status,
                'body'   => $body,
                'url'    => $url,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'status' => 0,
                'body'   => $e->getMessage(),
                'url'    => $url,
            ];
        }
    }

    // ---------------- HEALTH ----------------

    #[Route('/api/face/health', name: 'api_face_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $baseUrl = $this->getFlaskBaseUrl();
        $flaskUrl = $baseUrl . '/health';

        try {
            $response = $this->httpClient->request('GET', $flaskUrl, [
                'timeout' => 2.0,
            ]);

            $statusCode = $response->getStatusCode();
            $data       = $response->toArray(false);

            $flaskOk = ($statusCode === 200) && (($data['success'] ?? false) === true);

            return $this->json([
                'success' => $flaskOk,
                'symfony' => ['success' => true],
                'flask'   => [
                    'url'    => $flaskUrl,
                    'status' => $statusCode,
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

    // ---------------- REGISTER ----------------

    #[Route('/api/face/register/{id}', name: 'api_face_register', methods: ['POST'])]
    public function register(int $id, Request $request): JsonResponse
    {
        $imageB64 = $this->extractImageBase64($request);

        if ($imageB64 === null) {
            return $this->json([
                'success' => false,
                'error'   => 'Missing "image" (file upload or JSON field)',
            ], 400);
        }

        $payload = [
            'employee_id' => (string)$id,
            'image'       => $imageB64,
        ];

        $flaskRes = $this->callFlask('/register', $payload);

        return $this->json([
            'success' => $flaskRes['ok'],
            'symfony' => ['success' => true],
            'flask'   => [
                'url'    => $flaskRes['url'],
                'status' => $flaskRes['status'],
                'body'   => $flaskRes['body'],
            ],
        ], $flaskRes['ok'] ? 200 : 502);
    }

    // ---------------- RECOGNIZE ----------------

    #[Route('/api/face/recognize', name: 'api_face_recognize', methods: ['POST'])]
    public function recognize(Request $request): JsonResponse
    {
        $imageB64 = $this->extractImageBase64($request);

        if ($imageB64 === null) {
            return $this->json([
                'success' => false,
                'error'   => 'Missing "image" (file upload or JSON field)',
            ], 400);
        }

        $payload = [
            'image' => $imageB64,
        ];

        $flaskRes = $this->callFlask('/recognize', $payload);

        return $this->json([
            'success' => $flaskRes['ok'],
            'symfony' => ['success' => true],
            'flask'   => [
                'url'    => $flaskRes['url'],
                'status' => $flaskRes['status'],
                'body'   => $flaskRes['body'],
            ],
        ], $flaskRes['ok'] ? 200 : 502);
    }
}
