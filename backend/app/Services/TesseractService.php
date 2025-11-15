<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Utils\Logger;

class TesseractService
{
    private string $tesseractPath;
    private string $defaultLanguage;
    private int $psm;

    public function __construct()
    {
        $this->tesseractPath = $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract';
        $this->defaultLanguage = $_ENV['TESSERACT_LANGUAGES'] ?? 'fra+eng';
        $this->psm = (int)($_ENV['TESSERACT_PSM'] ?? 3);
    }

    /**
     * Extraire le texte d'une image avec Tesseract
     */
    public function extractText(string $imagePath, ?string $language = null): array
    {
        try {
            $startTime = microtime(true);

            $ocr = new TesseractOCR($imagePath);
            $ocr->executable($this->tesseractPath);
            $ocr->lang($language ?? $this->defaultLanguage);
            $ocr->psm($this->psm);

            // Options pour améliorer la précision
            $ocr->config('preserve_interword_spaces', '1');

            $text = $ocr->run();
            $processingTime = round(microtime(true) - $startTime, 2);

            // Calculer un score de confiance basique (approximation)
            $confidence = $this->estimateConfidence($text);

            Logger::info('Tesseract OCR terminé', [
                'image' => basename($imagePath),
                'language' => $language ?? $this->defaultLanguage,
                'processing_time' => $processingTime,
                'confidence' => $confidence,
                'text_length' => strlen($text)
            ]);

            return [
                'success' => true,
                'text' => trim($text),
                'confidence' => $confidence,
                'processing_time' => $processingTime,
                'language' => $language ?? $this->defaultLanguage,
                'engine' => 'tesseract'
            ];

        } catch (\Exception $e) {
            Logger::error('Erreur Tesseract OCR', [
                'error' => $e->getMessage(),
                'image' => $imagePath
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'text' => '',
                'confidence' => 0,
                'engine' => 'tesseract'
            ];
        }
    }

    /**
     * Extraire le texte avec les coordonnées (pour mise en évidence)
     */
    public function extractTextWithCoordinates(string $imagePath, ?string $language = null): array
    {
        try {
            $ocr = new TesseractOCR($imagePath);
            $ocr->executable($this->tesseractPath);
            $ocr->lang($language ?? $this->defaultLanguage);
            $ocr->psm($this->psm);

            // Récupérer le TSV (Tab-Separated Values) avec coordonnées
            $ocr->tsv();
            $tsvOutput = $ocr->run();

            return $this->parseTsvOutput($tsvOutput);

        } catch (\Exception $e) {
            Logger::error('Erreur extraction avec coordonnées', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Estimer la confiance basée sur des heuristiques
     */
    private function estimateConfidence(string $text): float
    {
        if (empty($text)) {
            return 0.0;
        }

        $score = 0.5; // Score de base

        // Bonus si le texte contient des mots reconnaissables
        $wordCount = str_word_count($text);
        if ($wordCount > 5) {
            $score += 0.2;
        }

        // Bonus si le texte contient peu de caractères spéciaux bizarres
        $specialCharRatio = preg_match_all('/[^a-zA-Z0-9\s\.,;:!?\'"-]/', $text) / strlen($text);
        if ($specialCharRatio < 0.1) {
            $score += 0.2;
        }

        // Bonus si le texte a une structure cohérente
        if (preg_match('/[A-Z][a-z]+/', $text)) {
            $score += 0.1;
        }

        return min(round($score, 2), 1.0);
    }

    /**
     * Parser la sortie TSV de Tesseract
     */
    private function parseTsvOutput(string $tsv): array
    {
        $lines = explode("\n", $tsv);
        $words = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 12 && !empty($parts[11])) {
                $words[] = [
                    'text' => $parts[11],
                    'confidence' => (float)$parts[10],
                    'x' => (int)$parts[6],
                    'y' => (int)$parts[7],
                    'width' => (int)$parts[8],
                    'height' => (int)$parts[9]
                ];
            }
        }

        return [
            'success' => true,
            'words' => $words,
            'full_text' => implode(' ', array_column($words, 'text'))
        ];
    }
}
