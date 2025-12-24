<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    private function faceBaseUrl(): string
    {
        // Plus fiable d'essayer $_SERVER puis $_ENV
        $baseUrl = $_SERVER['FACE_SERVICE_URL']
            ?? $_ENV['FACE_SERVICE_URL']
            ?? 'http://127.0.0.1:5000';

        return rtrim($baseUrl, '/');
    }

    private function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return $this->json(array_merge([
            'success' => false,
            'error' => $message,
        ], $extra), $status);
    }

    private function extractImage(Request $request): ?UploadedFile
    {
        // Attendu: multipart/form-data avec un champ "image"
        $file = $request->files->get('image');
        return $file instanceof UploadedFile ? $file : null;
    }

    private function validateImage(UploadedFile $image): ?JsonResponse
    {
        if (!$image->isValid()) {
            return $this->jsonError('Fichier image invalide (upload error).', 400);
        }

        // Limite 6MB (ajuste si tu veux)
        if ($image->getSize() !== null && $image->getSize() > 6 * 1024 * 1024) {
            return $this->jsonError('Image trop grande (max 6MB).', 413);
        }

        $mime = $image->getMimeType() ?? '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed, true)) {
            return $this->jsonError('Format image non supporté. Utilise JPG/PNG/WebP.', 415, [
                'mime' => $mime,
            ]);
        }

        return null;
    }

    #[Route('/api/face/health', name: 'api_face_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $flaskUrl = $this->faceBaseUrl() . '/health';

        try {
            $response = $this->httpClient->request('GET', $flaskUrl, [
                'timeout' => 2.0,
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
            $data = json_decode($raw, true) ?? ['raw' => $raw];

            $flaskOk = ($status === 200) && (($data['success'] ?? false) === true);

            return $this->json([
                'success' => $flaskOk,
                'symfony' => ['success' => true],
                'flask' => [
                    'url' => $flaskUrl,
                    'status' => $status,
                    'body' => $data,
                ],
            ], $flaskOk ? 200 : 502);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'symfony' => ['success' => true],
                'flask' => [
                    'url' => $flaskUrl,
                    'error' => $e->getMessage(),
                ],
            ], 502);
        }
    }

    /**
     * REGISTER (enrôlement) :
     * - Envoie une image au service Flask pour enregistrer le visage d'un employé.
     *
     * Attendu côté client:
     *   POST /api/face/register/123
     *   Content-Type: multipart/form-data
     *   image=<fichier>
     */
    #[Route('/api/face/register/{employeId}', name: 'api_face_register', methods: ['POST'])]
    public function register(Request $request, int $employeId): JsonResponse
    {
        $image = $this->extractImage($request);
        if (!$image) {
            return $this->jsonError('Champ "image" manquant (multipart/form-data).', 400);
        }

        if ($err = $this->validateImage($image)) {
            return $err;
        }

        // Deux variantes fréquentes côté Flask:
        // 1) POST /register/<employeId>
        // 2) POST /register (avec employeId dans form-data)
        //
        // Ici je tente la variante 1 (la plus simple).
        $flaskUrl = $this->faceBaseUrl() . '/register/' . $employeId;

        try {
            $response = $this->httpClient->request('POST', $flaskUrl, [
                'timeout' => 20.0,
                'headers' => [
                    // optionnel : certains serveurs aiment le header
                    'Accept' => 'application/json',
                ],
                'body' => [
                    // Le champ doit s'appeler "image" (adapte si ton Flask utilise un autre nom)
                    'image' => fopen($image->getPathname(), 'rb'),
                    // Si ton Flask est en variante 2, dé-commente ceci et change l'URL:
                    // 'employeId' => (string) $employeId,
                ],
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
            $data = json_decode($raw, true) ?? ['raw' => $raw];

            $ok = ($status >= 200 && $status < 300) && (($data['success'] ?? true) !== false);

            return $this->json([
                'success' => $ok,
                'symfony' => ['success' => true],
                'flask' => [
                    'url' => $flaskUrl,
                    'status' => $status,
                    'body' => $data,
                ],
            ], $ok ? 200 : 502);

        } catch (TransportExceptionInterface $e) {
            return $this->jsonError('Impossible de contacter le service Flask.', 502, [
                'flask' => ['url' => $flaskUrl, 'error' => $e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Erreur côté Symfony lors de l’appel Flask.', 502, [
                'flask' => ['url' => $flaskUrl, 'error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * RECOGNIZE (reconnaissance) :
     * - Envoie une image au service Flask pour détecter/reconnaître un visage.
     *
     * Attendu côté client:
     *   POST /api/face/recognize
     *   Content-Type: multipart/form-data
     *   image=<fichier>
     */
    #[Route('/api/face/recognize', name: 'api_face_recognize', methods: ['POST'])]
    public function recognize(Request $request): JsonResponse
    {
        $image = $this->extractImage($request);
        if (!$image) {
            return $this->jsonError('Champ "image" manquant (multipart/form-data).', 400);
        }

        if ($err = $this->validateImage($image)) {
            return $err;
        }

        $flaskUrl = $this->faceBaseUrl() . '/recognize';

        try {
            $response = $this->httpClient->request('POST', $flaskUrl, [
                'timeout' => 20.0,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'image' => fopen($image->getPathname(), 'rb'),
                ],
            ]);

            $status = $response->getStatusCode();
            $raw = $response->getContent(false);
            $data = json_decode($raw, true) ?? ['raw' => $raw];

            // Ici "ok" dépend de ton Flask (souvent success=true)
            $ok = ($status >= 200 && $status < 300) && (($data['success'] ?? true) !== false);

            return $this->json([
                'success' => $ok,
                'symfony' => ['success' => true],
                'flask' => [
                    'url' => $flaskUrl,
                    'status' => $status,
                    'body' => $data,
                ],
            ], $ok ? 200 : 502);

        } catch (TransportExceptionInterface $e) {
            return $this->jsonError('Impossible de contacter le service Flask.', 502, [
                'flask' => ['url' => $flaskUrl, 'error' => $e->getMessage()],
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Erreur côté Symfony lors de l’appel Flask.', 502, [
                'flask' => ['url' => $flaskUrl, 'error' => $e->getMessage()],
            ]);
        }
    }
}
