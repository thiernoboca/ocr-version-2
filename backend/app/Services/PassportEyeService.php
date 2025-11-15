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
     * Extraire les données MRZ d'un passeport
     */
    public function extractPassportData(string $imagePath): array
    {
        try {
            $startTime = microtime(true);

            // Vérifier que le script Python existe
            if (!file_exists($this->scriptPath)) {
                throw new \Exception("Script PassportEye introuvable: {$this->scriptPath}");
            }

            // Exécuter le script Python
            $command = escapeshellcmd("{$this->pythonPath} {$this->scriptPath} " . escapeshellarg($imagePath));
            $output = shell_exec($command . ' 2>&1');

            if ($output === null) {
                throw new \Exception("Erreur d'exécution du script PassportEye");
            }

            // Parser la sortie JSON
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Erreur de parsing JSON: " . json_last_error_msg());
            }

            $processingTime = round(microtime(true) - $startTime, 2);

            if ($result['success']) {
                // Appliquer la correction MRZ intelligente
                if (isset($result['data'])) {
                    $correctionResult = $this->mrzRelaxService->correctMrzData($result['data']);

                    if ($correctionResult['success']) {
                        // Fusionner les données corrigées
                        $result['data'] = $correctionResult['data'];
                        $result['mrz_corrections'] = [
                            'applied' => $correctionResult['corrections_applied'],
                            'details' => $correctionResult['corrections_details'],
                            'checksum_validation' => $correctionResult['checksum_validation']
                        ];

                        Logger::info('MRZ correction appliquée', [
                            'image' => basename($imagePath),
                            'corrections_count' => $correctionResult['corrections_applied'],
                            'checksums_valid' => $correctionResult['checksum_validation']['valid'] ?? false
                        ]);
                    }
                }

                Logger::info('PassportEye OCR réussi', [
                    'image' => basename($imagePath),
                    'processing_time' => $processingTime,
                    'mrz_type' => $result['data']['mrz_type'] ?? 'unknown'
                ]);
            } else {
                Logger::warning('PassportEye OCR échoué', [
                    'image' => basename($imagePath),
                    'error' => $result['error'] ?? 'unknown'
                ]);
            }

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

    /**
     * Détecter si une image contient un passeport
     */
    public function detectPassport(string $imagePath): bool
    {
        $result = $this->extractPassportData($imagePath);
        return $result['success'] && isset($result['data']['mrz_code']);
    }
}
