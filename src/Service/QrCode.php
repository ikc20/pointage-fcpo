<?php

namespace App\Service;

use Endroid\QrCode\QrCode as EndroidQrCode; 
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Symfony\Component\Filesystem\Filesystem;

class QrCode  
{
    private string $uploadDir;
    private Filesystem $filesystem;

    public function __construct(string $projectDir)
    {
        $this->uploadDir = $projectDir . '/public/uploads/qrcodes/';
        $this->filesystem = new Filesystem();
        
        // Créer le dossier s'il n'existe pas
        if (!$this->filesystem->exists($this->uploadDir)) {
            $this->filesystem->mkdir($this->uploadDir);
        }
    }

    /**
     * Génère un QR Code pour un employé
     */
    public function generateForEmploye(int $employeId, array $employeData): array
    {
        // Créer les données à encoder
        $qrData = json_encode([
            'employe_id' => $employeId,
            'matricule' => $employeData['matricule'] ?? '',
            'nom_complet' => $employeData['nom_complet'] ?? '',
            'timestamp' => time(),
            'secret' => bin2hex(random_bytes(16))
        ]);

        // Générer le QR Code
        $qrCode = EndroidQrCode::create($qrData)  // Utiliser l'alias
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->setSize(300)
            ->setMargin(10)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        // Générer un nom de fichier unique
        $filename = 'qr_' . $employeId . '_' . time() . '.png';
        $filepath = $this->uploadDir . $filename;

        // Sauvegarder l'image
        $result->saveToFile($filepath);

        // Générer l'URL publique
        $publicUrl = '/uploads/qrcodes/' . $filename;

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => $publicUrl,
            'data' => $qrData,
            'size' => filesize($filepath)
        ];
    }

    /**
     * Génère un QR Code en base64 pour affichage immédiat
     */
    public function generateBase64(array $data): string
    {
        $qrCode = EndroidQrCode::create(json_encode($data))  // Utiliser l'alias
            ->setSize(200)
            ->setMargin(10);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

    /**
     * Supprime un QR Code
     */
    public function deleteQrCode(string $filename): bool
    {
        $filepath = $this->uploadDir . $filename;
        
        if ($this->filesystem->exists($filepath)) {
            $this->filesystem->remove($filepath);
            return true;
        }
        
        return false;
    }

    /**
     * Liste tous les QR Codes générés
     */
    public function listAll(): array
    {
        $files = [];
        
        if ($this->filesystem->exists($this->uploadDir)) {
            $iterator = new \DirectoryIterator($this->uploadDir);
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'png') {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => $file->getMTime(),
                        'path' => '/uploads/qrcodes/' . $file->getFilename()
                    ];
                }
            }
        }
        
        return $files;
    }
}