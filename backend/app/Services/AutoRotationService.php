<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * AutoRotationService - Détection et correction automatique de l'orientation des images
 *
 * Utilise Tesseract PSM 0 (OSD - Orientation and Script Detection) pour détecter
 * l'orientation des documents et les corriger automatiquement
 *
 * @package App\Services
 */
class AutoRotationService
{
    /**
     * Chemin vers Tesseract
     */
    private string $tesseractPath;

    /**
     * Activer/désactiver la rotation automatique
     */
    private bool $autoRotationEnabled;

    /**
     * Seuil de confiance minimum pour appliquer la rotation
     */
    private float $confidenceThreshold;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->tesseractPath = $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract';
        $this->autoRotationEnabled = ($_ENV['AUTO_ROTATION_ENABLED'] ?? 'true') === 'true';
        $this->confidenceThreshold = (float)($_ENV['AUTO_ROTATION_CONFIDENCE_THRESHOLD'] ?? 1.5);
    }

    /**
     * Détecte l'orientation d'une image
     *
     * @param string $imagePath Chemin vers l'image
     * @return array Informations sur l'orientation détectée
     */
    public function detectOrientation(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'error' => 'Image file not found',
                'path' => $imagePath
            ];
        }

        try {
            // Utiliser Tesseract PSM 0 (Orientation and Script Detection only)
            $command = sprintf(
                '%s %s - --psm 0 2>&1',
                escapeshellcmd($this->tesseractPath),
                escapeshellarg($imagePath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                return [
                    'success' => false,
                    'error' => 'Tesseract OSD failed',
                    'output' => implode("\n", $output)
                ];
            }

            // Parser la sortie de Tesseract OSD
            $osdData = $this->parseOsdOutput($output);

            return [
                'success' => true,
                'orientation' => $osdData['orientation'] ?? 0,
                'orientation_confidence' => $osdData['orientation_confidence'] ?? 0,
                'rotate' => $osdData['rotate'] ?? 0,
                'script' => $osdData['script'] ?? 'Latin',
                'script_confidence' => $osdData['script_confidence'] ?? 0,
                'raw_output' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Détecte et corrige automatiquement l'orientation d'une image
     *
     * @param string $imagePath Chemin vers l'image source
     * @param string|null $outputPath Chemin de sortie (optionnel, écrase l'original par défaut)
     * @return array Résultat de la rotation
     */
    public function detectAndRotate(string $imagePath, ?string $outputPath = null): array
    {
        if (!$this->autoRotationEnabled) {
            return [
                'success' => false,
                'error' => 'Auto-rotation is disabled',
                'rotated' => false
            ];
        }

        // Détecter l'orientation
        $orientation = $this->detectOrientation($imagePath);

        if (!$orientation['success']) {
            return [
                'success' => false,
                'error' => 'Orientation detection failed',
                'details' => $orientation,
                'rotated' => false
            ];
        }

        $rotateAngle = $orientation['rotate'] ?? 0;
        $confidence = $orientation['orientation_confidence'] ?? 0;

        // Vérifier si une rotation est nécessaire
        if ($rotateAngle == 0) {
            return [
                'success' => true,
                'message' => 'Image is already correctly oriented',
                'rotated' => false,
                'orientation' => $orientation
            ];
        }

        // Vérifier le seuil de confiance
        if ($confidence < $this->confidenceThreshold) {
            return [
                'success' => false,
                'error' => 'Orientation confidence too low',
                'confidence' => $confidence,
                'threshold' => $this->confidenceThreshold,
                'rotated' => false,
                'orientation' => $orientation
            ];
        }

        // Effectuer la rotation
        $rotationResult = $this->rotateImage($imagePath, $rotateAngle, $outputPath);

        return array_merge($rotationResult, [
            'orientation' => $orientation,
            'rotate_angle' => $rotateAngle,
            'confidence' => $confidence
        ]);
    }

    /**
     * Effectue la rotation d'une image
     *
     * @param string $imagePath Chemin vers l'image source
     * @param int $angle Angle de rotation (degrés, sens anti-horaire)
     * @param string|null $outputPath Chemin de sortie (optionnel)
     * @return array Résultat de la rotation
     */
    public function rotateImage(string $imagePath, int $angle, ?string $outputPath = null): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'error' => 'Source image not found',
                'rotated' => false
            ];
        }

        // Déterminer le chemin de sortie
        if ($outputPath === null) {
            $outputPath = $imagePath; // Écraser l'original
        }

        try {
            // Charger l'image selon le type
            $imageInfo = getimagesize($imagePath);
            $mimeType = $imageInfo['mime'] ?? null;

            $sourceImage = null;

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($imagePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($imagePath);
                    break;
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported image type',
                        'mime_type' => $mimeType,
                        'rotated' => false
                    ];
            }

            if (!$sourceImage) {
                return [
                    'success' => false,
                    'error' => 'Failed to load image',
                    'rotated' => false
                ];
            }

            // Effectuer la rotation
            // Note: imagerotate utilise le sens horaire, donc on inverse l'angle
            $rotatedImage = imagerotate($sourceImage, -$angle, 0);

            if (!$rotatedImage) {
                imagedestroy($sourceImage);
                return [
                    'success' => false,
                    'error' => 'Rotation failed',
                    'rotated' => false
                ];
            }

            // Sauvegarder l'image rotated
            $saved = false;

            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    $saved = imagejpeg($rotatedImage, $outputPath, 95);
                    break;
                case 'image/png':
                    $saved = imagepng($rotatedImage, $outputPath, 9);
                    break;
                case 'image/gif':
                    $saved = imagegif($rotatedImage, $outputPath);
                    break;
            }

            // Libérer la mémoire
            imagedestroy($sourceImage);
            imagedestroy($rotatedImage);

            if (!$saved) {
                return [
                    'success' => false,
                    'error' => 'Failed to save rotated image',
                    'rotated' => false
                ];
            }

            return [
                'success' => true,
                'message' => 'Image rotated successfully',
                'rotated' => true,
                'angle' => $angle,
                'output_path' => $outputPath,
                'original_size' => filesize($imagePath),
                'new_size' => filesize($outputPath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rotated' => false
            ];
        }
    }

    /**
     * Parse la sortie de Tesseract OSD
     *
     * @param array $output Sortie brute de Tesseract
     * @return array Données structurées
     */
    private function parseOsdOutput(array $output): array
    {
        $data = [];

        foreach ($output as $line) {
            // Orientation in degrees: 0
            if (preg_match('/Orientation in degrees:\s*(\d+)/', $line, $matches)) {
                $data['orientation'] = (int)$matches[1];
            }

            // Rotate: 0
            if (preg_match('/Rotate:\s*(\d+)/', $line, $matches)) {
                $data['rotate'] = (int)$matches[1];
            }

            // Orientation confidence: 15.23
            if (preg_match('/Orientation confidence:\s*([\d.]+)/', $line, $matches)) {
                $data['orientation_confidence'] = (float)$matches[1];
            }

            // Script: Latin
            if (preg_match('/Script:\s*(\w+)/', $line, $matches)) {
                $data['script'] = $matches[1];
            }

            // Script confidence: 5.67
            if (preg_match('/Script confidence:\s*([\d.]+)/', $line, $matches)) {
                $data['script_confidence'] = (float)$matches[1];
            }
        }

        return $data;
    }

    /**
     * Vérifie si l'auto-rotation est activée
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->autoRotationEnabled;
    }

    /**
     * Active ou désactive l'auto-rotation
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->autoRotationEnabled = $enabled;
    }

    /**
     * Récupère le seuil de confiance
     *
     * @return float
     */
    public function getConfidenceThreshold(): float
    {
        return $this->confidenceThreshold;
    }

    /**
     * Modifie le seuil de confiance
     *
     * @param float $threshold
     */
    public function setConfidenceThreshold(float $threshold): void
    {
        $this->confidenceThreshold = $threshold;
    }

    /**
     * Récupère les statistiques de rotation pour une session
     *
     * @param array $detectionResults Tableau de résultats de détection
     * @return array Statistiques
     */
    public function getRotationStats(array $detectionResults): array
    {
        $stats = [
            'total_images' => count($detectionResults),
            'correctly_oriented' => 0,
            'rotated_90' => 0,
            'rotated_180' => 0,
            'rotated_270' => 0,
            'detection_failed' => 0,
            'avg_confidence' => 0,
            'success_rate' => 0
        ];

        $totalConfidence = 0;
        $successCount = 0;

        foreach ($detectionResults as $result) {
            if (!isset($result['success']) || !$result['success']) {
                $stats['detection_failed']++;
                continue;
            }

            $successCount++;
            $angle = $result['rotate'] ?? 0;
            $confidence = $result['orientation_confidence'] ?? 0;
            $totalConfidence += $confidence;

            switch ($angle) {
                case 0:
                    $stats['correctly_oriented']++;
                    break;
                case 90:
                case -270:
                    $stats['rotated_90']++;
                    break;
                case 180:
                case -180:
                    $stats['rotated_180']++;
                    break;
                case 270:
                case -90:
                    $stats['rotated_270']++;
                    break;
            }
        }

        if ($successCount > 0) {
            $stats['avg_confidence'] = round($totalConfidence / $successCount, 2);
            $stats['success_rate'] = round(($successCount / $stats['total_images']) * 100, 2);
        }

        return $stats;
    }
}
