<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceRecognition
{
    private HttpClientInterface $httpClient;
    private string $pythonServiceUrl;

    public function __construct(string $pythonServiceUrl)
{
    $this->httpClient = HttpClient::create();
    $this->pythonServiceUrl = $pythonServiceUrl; 
}

    /**
     * Enregistre un visage pour un employé
     */
    public function registerFace(int $employeId, string $imageBase64): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonServiceUrl . '/register', [
                'json' => [
                    'employee_id' => $employeId,
                    'image' => $imageBase64,
                    'timestamp' => time()
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            
            return [
                'success' => true,
                'data' => $data,
                'encoding' => $data['encoding'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement du visage: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reconnaît un visage à partir d'une image
     */
    public function recognizeFace(string $imageBase64): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonServiceUrl . '/recognize', [
                'json' => [
                    'image' => $imageBase64,
                    'timestamp' => time()
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            
            return [
                'success' => true,
                'employee_id' => $data['employee_id'] ?? null,
                'confidence' => $data['confidence'] ?? 0,
                'distance' => $data['distance'] ?? null,
                'data' => $data
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la reconnaissance faciale: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si le service Python est disponible
     */
    public function isServiceAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->pythonServiceUrl . '/health', [
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extraction de l'encodage facial (alternative si service Python indisponible)
     */
    public function extractFaceEncoding(string $imageBase64): ?string
    {
        // Logique simplifiée - à remplacer par vraie extraction si nécessaire
        // Dans un cas réel, vous appelleriez un service externe
        return json_encode([
            'method' => 'placeholder',
            'timestamp' => time(),
            'hash' => md5($imageBase64)
        ]);
    }

    /**
     * Compare deux encodages faciaux
     */
    public function compareFaceEncodings(string $encoding1, string $encoding2): float
    {
        // Simulation de comparaison
        // Dans un cas réel, vous utiliseriez une vraie comparaison
        $data1 = json_decode($encoding1, true);
        $data2 = json_decode($encoding2, true);
        
        if ($data1['hash'] === $data2['hash']) {
            return 1.0; // 100% de correspondance
        }
        
        return mt_rand(70, 95) / 100; // Simulation
    }
}