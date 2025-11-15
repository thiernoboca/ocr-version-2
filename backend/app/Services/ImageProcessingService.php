<?php

namespace App\Services;

use App\Utils\Logger;

class ImageProcessingService
{
    /**
     * Améliorer la qualité d'une image pour l'OCR
     */
    public function preprocessImage(string $inputPath, string $outputPath): bool
    {
        try {
            $imageInfo = getimagesize($inputPath);
            $mimeType = $imageInfo['mime'];

            // Charger l'image selon son type
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($inputPath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($inputPath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($inputPath);
                    break;
                default:
                    throw new \Exception("Type MIME non supporté: {$mimeType}");
            }

            if (!$image) {
                throw new \Exception("Impossible de charger l'image");
            }

            // Convertir en niveaux de gris
            imagefilter($image, IMG_FILTER_GRAYSCALE);

            // Augmenter le contraste
            imagefilter($image, IMG_FILTER_CONTRAST, -20);

            // Augmenter la luminosité si nécessaire
            imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);

            // Sauvegarder l'image traitée
            $saved = imagejpeg($image, $outputPath, 95);
            imagedestroy($image);

            if (!$saved) {
                throw new \Exception("Impossible de sauvegarder l'image traitée");
            }

            Logger::info('Image prétraitée avec succès', [
                'input' => basename($inputPath),
                'output' => basename($outputPath)
            ]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Erreur de prétraitement d\'image', [
                'error' => $e->getMessage(),
                'input' => $inputPath
            ]);
            return false;
        }
    }

    /**
     * Redimensionner une image si elle est trop grande
     */
    public function resizeIfNeeded(string $imagePath, int $maxWidth = 2000, int $maxHeight = 2000): bool
    {
        try {
            list($width, $height) = getimagesize($imagePath);

            // Pas besoin de redimensionner
            if ($width <= $maxWidth && $height <= $maxHeight) {
                return true;
            }

            // Calculer les nouvelles dimensions
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);

            // Créer la nouvelle image
            $image = imagecreatefromjpeg($imagePath);
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            imagecopyresampled(
                $resized, $image,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );

            // Sauvegarder
            $saved = imagejpeg($resized, $imagePath, 90);

            imagedestroy($image);
            imagedestroy($resized);

            Logger::info('Image redimensionnée', [
                'original' => "{$width}x{$height}",
                'new' => "{$newWidth}x{$newHeight}"
            ]);

            return $saved;

        } catch (\Exception $e) {
            Logger::error('Erreur de redimensionnement', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convertir un PDF en images (première page uniquement)
     */
    public function convertPdfToImage(string $pdfPath, string $outputPath): bool
    {
        try {
            // Utiliser Imagick si disponible
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                $imagick->readImage($pdfPath . '[0]'); // Première page
                $imagick->setImageFormat('jpg');
                $imagick->setImageCompressionQuality(90);
                $imagick->writeImage($outputPath);
                $imagick->clear();
                $imagick->destroy();

                return true;
            }

            // Sinon utiliser convert (ImageMagick CLI)
            $command = sprintf(
                'convert -density 300 %s[0] -quality 90 %s',
                escapeshellarg($pdfPath),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            return $returnCode === 0 && file_exists($outputPath);

        } catch (\Exception $e) {
            Logger::error('Erreur de conversion PDF', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Détecter l'orientation d'un document et le corriger
     */
    public function autoRotate(string $imagePath): bool
    {
        // Cette fonction nécessiterait une bibliothèque plus avancée
        // ou Tesseract avec --psm 0 pour la détection d'orientation
        // Implémentation basique pour l'exemple
        return true;
    }
}
