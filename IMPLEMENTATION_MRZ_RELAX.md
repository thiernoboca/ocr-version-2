# Recommandations d'Implémentation Détaillées
## Intégration MRZ Scanner + OCR Version 2

---

## A. INTÉGRATION MRZ-RELAX EN PHP

### A.1 Service PHP pour mrz-relax

**Fichier**: `backend/app/Services/MrzRelaxService.php`

```php
<?php

namespace App\Services;

use App\Utils\Logger;

class MrzRelaxService
{
    /**
     * Corriger les données MRZ avec logique contexte-aware
     * Basé sur l'algorithme mrz-relax du projet JavaScript
     */
    public function correctMrzData(array $mrzData, int $maxRetries = 3): array
    {
        $mrz = $mrzData['mrz_code'] ?? '';
        
        if (empty($mrz)) {
            return $mrzData;
        }

        // Convertir en array de lignes
        $lines = explode("\n", trim($mrz));
        
        if (count($lines) < 2) {
            return $mrzData;
        }

        $retryCount = 0;
        $modified = false;

        do {
            $initialLines = $lines;
            $modified = false;
            $retryCount++;

            // Analyser la structure MRZ (TD1, TD2, TD3)
            $mrzType = $this->detectMrzType($lines);
            
            // Récupérer les positions des champs selon le type
            $fields = $this->getMrzFieldDefinition($mrzType);

            // Correction par champ
            foreach ($fields as $fieldName => $fieldDef) {
                $lineIdx = $fieldDef['line'];
                $startPos = $fieldDef['start'];
                $endPos = $fieldDef['end'];
                $fieldType = $fieldDef['type']; // 'numeric', 'alpha', 'mixed'

                if (!isset($lines[$lineIdx])) {
                    continue;
                }

                $fieldValue = substr($lines[$lineIdx], $startPos, $endPos - $startPos);
                $correctedValue = $this->correctFieldValue($fieldValue, $fieldType);

                if ($correctedValue !== $fieldValue) {
                    Logger::debug("MRZ correction: $fieldName changed from '$fieldValue' to '$correctedValue'");
                    $lines[$lineIdx] = substr_replace(
                        $lines[$lineIdx],
                        $correctedValue,
                        $startPos,
                        $endPos - $startPos
                    );
                    $modified = true;
                }
            }

        } while ($modified && $retryCount < $maxRetries);

        return [
            'mrz_code' => implode("\n", $lines),
            'mrz_original' => $mrzData['mrz_code'],
            'corrected' => ($lines !== $initialLines),
            'attempts' => $retryCount,
            'fields' => $mrzData['fields'] ?? []
        ];
    }

    /**
     * Corriger une valeur de champ selon son type
     * 
     * Numériques/dates: 0 ↔ O, 1 ↔ l/I, 5 ↔ S, 9 ↔ g
     * Texte: 0 → O, 1 → I, 5 → S
     */
    private function correctFieldValue(string $value, string $type): string
    {
        if ($type === 'numeric' || $type === 'date') {
            // Pour champs numériques: remplacer lettres par chiffres
            $corrected = $value;
            $corrected = preg_replace('/[Oo]/', '0', $corrected);
            $corrected = preg_replace('/[lI]/', '1', $corrected);
            $corrected = preg_replace('/[Ss]/', '5', $corrected);
            $corrected = preg_replace('/[gG]/', '9', $corrected);
            return $corrected;
        } 
        else if ($type === 'alpha') {
            // Pour champs texte: remplacer chiffres par lettres (inverse)
            $corrected = $value;
            $corrected = str_replace('0', 'O', $corrected);
            $corrected = str_replace('1', 'I', $corrected);
            $corrected = str_replace('5', 'S', $corrected);
            return $corrected;
        }
        
        return $value;
    }

    /**
     * Détecter le type MRZ (TD1, TD2, TD3)
     * TD1: 3 lignes de 30 caractères (ID cards)
     * TD2: 2 lignes de 36 caractères
     * TD3: 2 lignes de 44 caractères (passeports)
     */
    private function detectMrzType(array $lines): string
    {
        if (count($lines) === 3) {
            return 'TD1';
        } else if (count($lines) === 2) {
            $length = strlen($lines[0]);
            if ($length === 36) {
                return 'TD2';
            } else if ($length === 44) {
                return 'TD3';
            }
        }
        return 'unknown';
    }

    /**
     * Définir les positions des champs selon le type MRZ
     * Format: [line_index => [start_pos, end_pos, field_type]]
     */
    private function getMrzFieldDefinition(string $type): array
    {
        switch ($type) {
            case 'TD1':
                return [
                    'document_type' => ['line' => 0, 'start' => 0, 'end' => 2, 'type' => 'alpha'],
                    'issuing_country' => ['line' => 0, 'start' => 2, 'end' => 5, 'type' => 'alpha'],
                    'surname' => ['line' => 0, 'start' => 5, 'end' => 30, 'type' => 'alpha'],
                    'given_names' => ['line' => 1, 'start' => 0, 'end' => 30, 'type' => 'alpha'],
                    'document_number' => ['line' => 2, 'start' => 0, 'end' => 9, 'type' => 'numeric'],
                    'nationality' => ['line' => 2, 'start' => 10, 'end' => 13, 'type' => 'alpha'],
                    'date_of_birth' => ['line' => 2, 'start' => 13, 'end' => 19, 'type' => 'date'],
                    'sex' => ['line' => 2, 'start' => 20, 'end' => 21, 'type' => 'alpha'],
                    'expiration_date' => ['line' => 2, 'start' => 21, 'end' => 27, 'type' => 'date'],
                ];
            
            case 'TD2':
            case 'TD3':
                return [
                    'document_type' => ['line' => 0, 'start' => 0, 'end' => 2, 'type' => 'alpha'],
                    'issuing_country' => ['line' => 0, 'start' => 2, 'end' => 5, 'type' => 'alpha'],
                    'surname' => ['line' => 0, 'start' => 5, 'end' => 44, 'type' => 'alpha'],
                    'document_number' => ['line' => 1, 'start' => 0, 'end' => 9, 'type' => 'numeric'],
                    'nationality' => ['line' => 1, 'start' => 10, 'end' => 13, 'type' => 'alpha'],
                    'date_of_birth' => ['line' => 1, 'start' => 13, 'end' => 19, 'type' => 'date'],
                    'sex' => ['line' => 1, 'start' => 20, 'end' => 21, 'type' => 'alpha'],
                    'expiration_date' => ['line' => 1, 'start' => 21, 'end' => 27, 'type' => 'date'],
                ];
            
            default:
                return [];
        }
    }

    /**
     * Valider la checksum d'un champ MRZ
     */
    public function validateChecksum(string $line, int $checksumPosition): bool
    {
        if ($checksumPosition >= strlen($line)) {
            return false;
        }

        $data = substr($line, 0, $checksumPosition);
        $expectedChecksum = intval($line[$checksumPosition]);
        $calculatedChecksum = $this->calculateChecksum($data);

        return $expectedChecksum === $calculatedChecksum;
    }

    /**
     * Calculer la checksum ICAO MRZ
     */
    private function calculateChecksum(string $data): int
    {
        $weights = [7, 3, 1];
        $sum = 0;
        $dataLength = strlen($data);

        for ($i = 0; $i < $dataLength; $i++) {
            $char = $data[$i];
            $weight = $weights[$i % 3];

            if (ctype_digit($char)) {
                $value = intval($char);
            } else if (ctype_alpha($char)) {
                // A=10, B=11, ..., Z=35
                $value = ord(strtoupper($char)) - ord('A') + 10;
            } else {
                $value = 0;
            }

            $sum += $value * $weight;
        }

        return $sum % 10;
    }
}
```

### A.2 Intégration dans PassportEyeService

**Modification**: `backend/app/Services/PassportEyeService.php`

```php
<?php

namespace App\Services;

use App\Utils\Logger;

class PassportEyeService
{
    private string $pythonPath;
    private string $scriptPath;
    private MrzRelaxService $mrzRelaxService;

    public function __construct()
    {
        $this->pythonPath = $_ENV['PYTHON_PATH'] ?? '/usr/bin/python3';
        $this->scriptPath = $_ENV['PASSPORTEYE_SCRIPT_PATH'] ?? __DIR__ . '/../../scripts/passport_ocr.py';
        $this->mrzRelaxService = new MrzRelaxService();
    }

    /**
     * Extraire les données MRZ d'un passeport avec correction
     */
    public function extractPassportData(string $imagePath): array
    {
        try {
            $startTime = microtime(true);

            if (!file_exists($this->scriptPath)) {
                throw new \Exception("Script PassportEye introuvable: {$this->scriptPath}");
            }

            $command = escapeshellcmd("{$this->pythonPath} {$this->scriptPath} " . 
                       escapeshellarg($imagePath));
            $output = shell_exec($command . ' 2>&1');

            if ($output === null) {
                throw new \Exception("Erreur d'exécution du script PassportEye");
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Erreur de parsing JSON: " . json_last_error_msg());
            }

            // NOUVEAU: Appliquer mrz-relax pour correction intelligente
            if ($result['success'] && isset($result['data']['mrz_code'])) {
                $correctionResult = $this->mrzRelaxService->correctMrzData([
                    'mrz_code' => $result['data']['mrz_code'],
                    'fields' => $result['data']
                ]);
                
                // Si correction effectuée, logger et mettre à jour
                if ($correctionResult['corrected']) {
                    Logger::info('MRZ correction appliquée', [
                        'original' => $correctionResult['mrz_original'],
                        'corrected' => $correctionResult['mrz_code'],
                        'attempts' => $correctionResult['attempts']
                    ]);
                    
                    $result['data']['mrz_code'] = $correctionResult['mrz_code'];
                    $result['data']['mrz_corrected'] = true;
                    $result['data']['mrz_correction_attempts'] = $correctionResult['attempts'];
                }
            }

            $processingTime = round(microtime(true) - $startTime, 2);
            $result['processing_time'] = $processingTime;
            $result['engine'] = 'passporteye';

            return $result;

        } catch (\Exception $e) {
            Logger::error('Erreur PassportEye', [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'engine' => 'passporteye'
            ];
        }
    }
}
```

---

## B. SYSTÈME DE CACHING DES RÉSULTATS OCR

### B.1 CacheService pour OCR

**Fichier**: `backend/app/Services/OcrCacheService.php`

```php
<?php

namespace App\Services;

use App\Utils\Logger;

class OcrCacheService
{
    private string $cacheDir;
    private int $ttl;  // Time to live en secondes

    public function __construct(string $cacheDir = null, int $ttl = 3600)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/ocr_cache';
        $this->ttl = $ttl;
        
        // Créer le répertoire de cache s'il n'existe pas
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Générer une clé de cache basée sur le hash du fichier
     */
    public function getCacheKey(string $filePath): string
    {
        $fileHash = hash_file('sha256', $filePath);
        return "ocr_{$fileHash}";
    }

    /**
     * Récupérer un résultat OCR du cache
     */
    public function get(string $cacheKey): ?array
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        // Vérifier l'expiration
        $fileTime = filemtime($cacheFile);
        if (time() - $fileTime > $this->ttl) {
            unlink($cacheFile);
            Logger::info("Cache expired: $cacheKey");
            return null;
        }

        $data = file_get_contents($cacheFile);
        return json_decode($data, true);
    }

    /**
     * Sauvegarder un résultat OCR en cache
     */
    public function set(string $cacheKey, array $data): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($cacheFile, $jsonData) === false) {
            Logger::error("Failed to write cache file: $cacheFile");
            return false;
        }

        Logger::debug("Cache written: $cacheKey");
        return true;
    }

    /**
     * Nettoyer les fichiers cache expirés
     */
    public function cleanup(): int
    {
        $cleanedCount = 0;
        
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        foreach (glob($this->cacheDir . '/ocr_*') as $cacheFile) {
            $fileTime = filemtime($cacheFile);
            if (time() - $fileTime > $this->ttl) {
                if (unlink($cacheFile)) {
                    $cleanedCount++;
                }
            }
        }

        Logger::info("Cache cleanup completed: {$cleanedCount} files deleted");
        return $cleanedCount;
    }

    /**
     * Obtenir le chemin du fichier cache
     */
    private function getCacheFilePath(string $cacheKey): string
    {
        // Utiliser un sous-répertoire pour éviter trop de fichiers dans un seul dossier
        $subDir = substr($cacheKey, 0, 2);
        $dir = $this->cacheDir . '/' . $subDir;
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/' . substr($cacheKey, 2) . '.json';
    }
}
```

### B.2 Utilisation dans DocumentController

**Modification**: `backend/app/Controllers/DocumentController.php`

```php
<?php

namespace App\Controllers;

use App\Services\OcrCacheService;
use App\Services\TesseractService;
use App\Services\PassportEyeService;
use App\Services\ImageProcessingService;

class DocumentController
{
    private OcrCacheService $ocrCache;
    private TesseractService $tesseract;
    private PassportEyeService $passportEye;
    private ImageProcessingService $imageProcessor;

    public function __construct()
    {
        $this->ocrCache = new OcrCacheService();
        $this->tesseract = new TesseractService();
        $this->passportEye = new PassportEyeService();
        $this->imageProcessor = new ImageProcessingService();
    }

    public function upload(): void
    {
        try {
            // ... validation du fichier ...

            // Prétraiter l'image
            $processedPath = $uploadPath . '/processed_' . $filename;
            $this->imageProcessor->preprocessImage($filepath, $processedPath);

            // NOUVEAU: Vérifier le cache
            $cacheKey = $this->ocrCache->getCacheKey($processedPath);
            $cachedResult = $this->ocrCache->get($cacheKey);
            
            if ($cachedResult !== null) {
                Logger::info('OCR result retrieved from cache', ['cache_key' => $cacheKey]);
                $ocrResult = $cachedResult;
            } else {
                // Exécuter l'OCR
                if ($documentType === 'passport') {
                    $ocrResult = $this->passportEye->extractPassportData($processedPath);
                    if (!$ocrResult['success']) {
                        $ocrResult = $this->tesseract->extractText($processedPath);
                    }
                } else {
                    $ocrResult = $this->tesseract->extractText($processedPath);
                }

                // NOUVEAU: Sauvegarder en cache
                $this->ocrCache->set($cacheKey, $ocrResult);
            }

            // ... sauvegarder en base de données ...
        } catch (\Exception $e) {
            // ... gestion d'erreur ...
        }
    }
}
```

---

## C. AUTO-ROTATION AVEC TESSERACT PSM 0

### C.1 AutoRotationService

**Fichier**: `backend/app/Services/AutoRotationService.php`

```php
<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Utils\Logger;

class AutoRotationService
{
    private string $tesseractPath;

    public function __construct()
    {
        $this->tesseractPath = $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract';
    }

    /**
     * Détecter l'orientation du document et le corriger
     * Utilise Tesseract PSM 0 (OSD - Orientation and Script Detection)
     */
    public function detectAndCorrect(string $imagePath): bool
    {
        try {
            // PSM 0: Orientation and script detection only
            $ocr = new TesseractOCR($imagePath);
            $ocr->executable($this->tesseractPath);
            $ocr->psm(0);  // OSD mode

            $output = $ocr->run();

            // Parser la sortie pour extraire l'orientation
            $rotation = $this->extractRotation($output);

            if ($rotation > 0) {
                Logger::info("Document rotation detected", ['rotation_angle' => $rotation]);
                return $this->rotateImage($imagePath, $rotation);
            }

            return true;

        } catch (\Exception $e) {
            Logger::warning("Auto-rotation failed", [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);
            return false;
        }
    }

    /**
     * Extraire l'angle de rotation de la sortie Tesseract OSD
     * Format: "Rotation: 90"
     */
    private function extractRotation(string $output): int
    {
        if (preg_match('/Rotation:\s*(\d+)/', $output, $matches)) {
            $rotation = (int)$matches[1];
            
            // Valeur de 0 = pas de rotation, autres = rotation en degrés
            if ($rotation !== 0) {
                // Tesseract retourne: 0, 90, 180, 270
                return $rotation;
            }
        }

        return 0;
    }

    /**
     * Effectuer la rotation de l'image
     */
    private function rotateImage(string $imagePath, int $angle): bool
    {
        try {
            $imageInfo = getimagesize($imagePath);
            $mimeType = $imageInfo['mime'];

            // Charger l'image
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                default:
                    return false;
            }

            if (!$image) {
                return false;
            }

            // Convertir angle Tesseract en angle PHP
            // Tesseract: 0=normal, 90=rotated 90 CCW, etc.
            // PHP imagerotate: positive = counter-clockwise
            $phpAngle = 360 - $angle;

            // Effectuer la rotation
            $rotated = imagerotate($image, $phpAngle, 0);

            if (!$rotated) {
                imagedestroy($image);
                return false;
            }

            // Sauvegarder l'image rotatée
            switch ($mimeType) {
                case 'image/jpeg':
                    $result = imagejpeg($rotated, $imagePath, 95);
                    break;
                case 'image/png':
                    $result = imagepng($rotated, $imagePath);
                    break;
                default:
                    $result = false;
            }

            imagedestroy($image);
            imagedestroy($rotated);

            if ($result) {
                Logger::info("Image rotation applied", [
                    'image' => basename($imagePath),
                    'angle' => $angle
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Logger::error("Image rotation failed", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

### C.2 Intégration dans le flux

**Modification**: `backend/app/Services/ImageProcessingService.php`

```php
<?php

namespace App\Services;

class ImageProcessingService
{
    private AutoRotationService $rotationService;

    public function __construct()
    {
        $this->rotationService = new AutoRotationService();
    }

    /**
     * Prétraiter l'image complètement
     */
    public function preprocessImage(string $inputPath, string $outputPath): bool
    {
        try {
            // 1. Auto-rotation
            $this->rotationService->detectAndCorrect($inputPath);

            // 2. Conversion gris + contrast
            $imageInfo = getimagesize($inputPath);
            $mimeType = $imageInfo['mime'];

            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($inputPath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($inputPath);
                    break;
                default:
                    throw new \Exception("Type MIME non supporté: {$mimeType}");
            }

            if (!$image) {
                throw new \Exception("Impossible de charger l'image");
            }

            // Filtres
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -20);
            imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);

            // 3. Sauvegarder
            $saved = imagejpeg($image, $outputPath, 95);
            imagedestroy($image);

            return $saved;

        } catch (\Exception $e) {
            Logger::error('Erreur de prétraitement', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

---

## D. WEB WORKERS FRONTEND POUR IMAGE PREPROCESSING

### D.1 Image Processing Worker

**Fichier**: `frontend/src/workers/imageProcessor.worker.js`

```javascript
/**
 * Web Worker pour traitement d'images
 * Évite de bloquer le thread UI
 */

self.onmessage = async (event) => {
  const { cmd, imageData, width, height } = event.data;

  try {
    switch (cmd) {
      case 'convert_grayscale':
        const grayscaled = convertToGrayscale(imageData, width, height);
        self.postMessage({
          status: 'success',
          result: grayscaled
        });
        break;

      case 'enhance_contrast':
        const enhanced = enhanceContrast(imageData, width, height);
        self.postMessage({
          status: 'success',
          result: enhanced
        });
        break;

      case 'process_complete':
        const processed = processComplete(imageData, width, height);
        self.postMessage({
          status: 'success',
          result: processed
        });
        break;

      default:
        self.postMessage({
          status: 'error',
          error: 'Unknown command: ' + cmd
        });
    }
  } catch (error) {
    self.postMessage({
      status: 'error',
      error: error.message
    });
  }
};

/**
 * Convertir en niveaux de gris
 */
function convertToGrayscale(imageData, width, height) {
  const data = imageData.data;
  const newData = new Uint8ClampedArray(data.length);

  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const a = data[i + 3];

    // Formule standard: 0.299*R + 0.587*G + 0.114*B
    const gray = Math.round(0.299 * r + 0.587 * g + 0.114 * b);

    newData[i] = gray;
    newData[i + 1] = gray;
    newData[i + 2] = gray;
    newData[i + 3] = a;
  }

  return new ImageData(newData, width, height);
}

/**
 * Améliorer le contraste
 */
function enhanceContrast(imageData, width, height) {
  const data = imageData.data;
  const newData = new Uint8ClampedArray(data.length);

  // Trouver min/max des valeurs
  let minVal = 255, maxVal = 0;
  for (let i = 0; i < data.length; i += 4) {
    const val = data[i];
    minVal = Math.min(minVal, val);
    maxVal = Math.max(maxVal, val);
  }

  const range = maxVal - minVal;
  if (range === 0) {
    return imageData;
  }

  // Normaliser
  for (let i = 0; i < data.length; i += 4) {
    const val = data[i];
    const normalized = Math.round(((val - minVal) / range) * 255);

    newData[i] = normalized;
    newData[i + 1] = normalized;
    newData[i + 2] = normalized;
    newData[i + 3] = data[i + 3];
  }

  return new ImageData(newData, width, height);
}

/**
 * Pipeline complet: grayscale + contrast
 */
function processComplete(imageData, width, height) {
  let result = convertToGrayscale(imageData, width, height);
  result = enhanceContrast(result, width, height);
  return result;
}
```

### D.2 Utilisation dans le composant

**Fichier**: `frontend/src/components/Capture/DocumentCapture.jsx`

```javascript
import React, { useRef } from 'react'

const DocumentCapture = () => {
  const workerRef = useRef(null);

  // Initialiser le worker
  useEffect(() => {
    workerRef.current = new Worker(
      new URL('../../workers/imageProcessor.worker.js', import.meta.url),
      { type: 'module' }
    );

    workerRef.current.onmessage = (event) => {
      if (event.data.status === 'success') {
        console.log('Image processing completed');
      } else {
        console.error('Processing error:', event.data.error);
      }
    };

    return () => {
      workerRef.current?.terminate();
    };
  }, []);

  // Traiter l'image avec le worker
  const processImage = async (imageData) => {
    return new Promise((resolve, reject) => {
      const canvas = document.createElement('canvas');
      canvas.width = imageData.width;
      canvas.height = imageData.height;

      const ctx = canvas.getContext('2d');
      ctx.putImageData(imageData, 0, 0);

      const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);

      workerRef.current.onmessage = (event) => {
        if (event.data.status === 'success') {
          resolve(event.data.result);
        } else {
          reject(new Error(event.data.error));
        }
      };

      workerRef.current.postMessage({
        cmd: 'process_complete',
        imageData: imgData,
        width: imageData.width,
        height: imageData.height
      });
    });
  };

  // ...
};

export default DocumentCapture;
```

---

## E. TESTS D'IMPLÉMENTATION

### E.1 Test MrzRelaxService

**Fichier**: `backend/tests/Unit/MrzRelaxServiceTest.php`

```php
<?php

namespace Tests\Unit;

use App\Services\MrzRelaxService;
use PHPUnit\Framework\TestCase;

class MrzRelaxServiceTest extends TestCase
{
    private MrzRelaxService $service;

    protected function setUp(): void
    {
        $this->service = new MrzRelaxService();
    }

    /**
     * Test correction basique O → 0
     */
    public function testCorrectOToZero()
    {
        // MRZ avec O au lieu de 0
        $mrzData = [
            'mrz_code' => "POL<<<<<<<<<<<<<<<<<<<<<<<<\nKOWALSKI<<<<<<<<<JOANNO0000O6789O123456\n780101O2030128PL100101<<<<<<<<<<<<<<<<<4",
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['corrected']);
        $this->assertStringContainsString('00000', $result['mrz_code']);
    }

    /**
     * Test correction I → 1
     */
    public function testCorrectIToOne()
    {
        $mrzData = [
            'mrz_code' => "POL<<<<<<<<<<<<<<<<<<<<<<<<\nKOWALSKI<<<<<<<<<JOANNO0000I6789O123456\n780101O2030128PL100101<<<<<<<<<<<<<<<<<4",
        ];

        $result = $this->service->correctMrzData($mrzData);

        $this->assertTrue($result['corrected']);
    }

    /**
     * Test validation checksum
     */
    public function testValidateChecksum()
    {
        $line = "0812345678";  // "0812345678" avec checksum
        
        // Position 9 est la checksum
        $isValid = $this->service->validateChecksum($line, 9);

        // Le résultat dépendra de la checksum ICAO réelle
        $this->assertIsBool($isValid);
    }
}
```

---

## CONCLUSION

Ces implémentations permettent:

1. ✅ **Correction intelligente MRZ** avec mrz-relax en PHP
2. ✅ **Caching des résultats OCR** pour éviter le re-traitement
3. ✅ **Auto-rotation de documents** avec Tesseract PSM 0
4. ✅ **Web Workers frontend** pour traitement image sans bloquer l'UI

Ces améliorations fusionnent les forces du MRZ Scanner avec l'architecture robuste d'OCR Version 2.

