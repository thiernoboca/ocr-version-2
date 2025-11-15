<?php

namespace App\Controllers;

use App\Services\TesseractService;
use App\Services\PassportEyeService;
use App\Services\ImageProcessingService;
use App\Services\OcrCacheService;
use App\Models\Document;
use App\Utils\Response;
use App\Utils\Validator;
use App\Utils\Logger;
use App\Middleware\AuthMiddleware;

class DocumentController
{
    private TesseractService $tesseract;
    private PassportEyeService $passportEye;
    private ImageProcessingService $imageProcessor;
    private OcrCacheService $ocrCache;
    private Document $documentModel;

    public function __construct()
    {
        $this->tesseract = new TesseractService();
        $this->passportEye = new PassportEyeService();
        $this->imageProcessor = new ImageProcessingService();
        $this->ocrCache = new OcrCacheService();
        $this->documentModel = new Document();
    }

    /**
     * Upload et traiter un document
     */
    public function upload(): void
    {
        try {
            // Valider le fichier
            if (!isset($_FILES['file'])) {
                Response::error('Aucun fichier fourni');
                return;
            }

            $errors = Validator::validateUploadedFile($_FILES['file']);
            if (!empty($errors)) {
                Response::error('Validation échouée', $errors, 422);
                return;
            }

            // Récupérer les paramètres
            $documentType = $_POST['type'] ?? 'document';
            $language = $_POST['language'] ?? null;

            if (!Validator::validateDocumentType($documentType)) {
                Response::error('Type de document invalide');
                return;
            }

            // Créer le répertoire de stockage si nécessaire
            $uploadPath = __DIR__ . '/../../' . $_ENV['UPLOAD_PATH'];
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Générer un nom de fichier unique
            $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('doc_') . '.' . $extension;
            $filepath = $uploadPath . '/' . $filename;

            // Déplacer le fichier uploadé
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                Response::serverError('Erreur lors de l\'enregistrement du fichier');
                return;
            }

            // Convertir PDF en image si nécessaire
            if ($extension === 'pdf') {
                $imagePath = $uploadPath . '/' . uniqid('doc_') . '.jpg';
                if (!$this->imageProcessor->convertPdfToImage($filepath, $imagePath)) {
                    unlink($filepath);
                    Response::serverError('Erreur lors de la conversion PDF');
                    return;
                }
                unlink($filepath); // Supprimer le PDF original
                $filepath = $imagePath;
                $filename = basename($imagePath);
            }

            // Prétraiter l'image
            $processedPath = $uploadPath . '/processed_' . $filename;
            $this->imageProcessor->preprocessImage($filepath, $processedPath);

            // Générer le hash de l'image pour le cache
            $imageHash = $this->ocrCache->generateImageHash($processedPath);

            // Déterminer le moteur OCR à utiliser
            $ocrEngine = ($documentType === 'passport') ? 'passporteye' : 'tesseract';

            // Vérifier le cache OCR
            $ocrResult = null;
            if ($imageHash && $this->ocrCache->has($imageHash, $ocrEngine, $language)) {
                // Récupérer depuis le cache
                $ocrResult = $this->ocrCache->get($imageHash, $ocrEngine, $language);

                Logger::info('Résultat OCR récupéré depuis le cache', [
                    'image_hash' => substr($imageHash, 0, 8),
                    'engine' => $ocrEngine,
                    'cache_age_seconds' => $ocrResult['cache_age_seconds'] ?? 0
                ]);

                // Extraire les données du cache
                $ocrResult = $ocrResult['result'] ?? $ocrResult;
            } else {
                // Cache miss - effectuer l'OCR
                if ($documentType === 'passport') {
                    // Essayer PassportEye d'abord
                    $ocrResult = $this->passportEye->extractPassportData($processedPath);

                    // Si PassportEye échoue, utiliser Tesseract en fallback
                    if (!$ocrResult['success']) {
                        Logger::info('PassportEye échoué, utilisation de Tesseract en fallback');
                        $ocrResult = $this->tesseract->extractText($processedPath, $language);
                        $ocrEngine = 'tesseract'; // Mettre à jour le moteur utilisé
                    }
                } else {
                    // Utiliser Tesseract pour les autres types de documents
                    $ocrResult = $this->tesseract->extractText($processedPath, $language);
                }

                // Stocker le résultat dans le cache
                if ($imageHash && $ocrResult['success']) {
                    $cached = $this->ocrCache->set($imageHash, $ocrEngine, $ocrResult, $language);

                    if ($cached) {
                        Logger::info('Résultat OCR stocké dans le cache', [
                            'image_hash' => substr($imageHash, 0, 8),
                            'engine' => $ocrEngine
                        ]);
                    }
                }
            }

            // Sauvegarder dans la base de données
            $userId = AuthMiddleware::getCurrentUserId();
            $documentId = $this->documentModel->create([
                'user_id' => $userId,
                'filename' => $filename,
                'original_name' => $_FILES['file']['name'],
                'file_path' => $filepath,
                'file_size' => filesize($filepath),
                'mime_type' => mime_content_type($filepath),
                'document_type' => $documentType,
                'ocr_text' => $ocrResult['text'] ?? '',
                'ocr_data' => json_encode($ocrResult),
                'ocr_confidence' => $ocrResult['confidence'] ?? 0,
                'ocr_engine' => $ocrResult['engine'] ?? 'unknown',
                'processing_time' => $ocrResult['processing_time'] ?? 0,
                'language' => $language ?? 'fra+eng'
            ]);

            // Supprimer le fichier traité temporaire
            if (file_exists($processedPath)) {
                unlink($processedPath);
            }

            // Log d'audit
            Logger::audit('document_upload', "document:{$documentId}", $userId, [
                'filename' => $_FILES['file']['name'],
                'type' => $documentType,
                'size' => filesize($filepath)
            ]);

            Response::success([
                'document_id' => $documentId,
                'filename' => $filename,
                'ocr_result' => $ocrResult
            ], 'Document uploadé et traité avec succès', 201);

        } catch (\Exception $e) {
            Logger::error('Erreur upload document', ['error' => $e->getMessage()]);
            Response::serverError('Erreur lors du traitement du document');
        }
    }

    /**
     * Récupérer un document
     */
    public function show(string $id): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();
            $document = $this->documentModel->findById((int)$id, $userId);

            if (!$document) {
                Response::notFound('Document non trouvé');
                return;
            }

            // Log d'audit
            Logger::audit('document_view', "document:{$id}", $userId);

            Response::success($document);

        } catch (\Exception $e) {
            Logger::error('Erreur récupération document', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }

    /**
     * Lister les documents de l'utilisateur
     */
    public function index(): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);

            $documents = $this->documentModel->findByUserId($userId, $page, $limit);

            Response::success($documents);

        } catch (\Exception $e) {
            Logger::error('Erreur liste documents', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }

    /**
     * Supprimer un document (RGPD)
     */
    public function delete(string $id): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();
            $document = $this->documentModel->findById((int)$id, $userId);

            if (!$document) {
                Response::notFound('Document non trouvé');
                return;
            }

            // Supprimer le fichier physique
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }

            // Supprimer de la base de données
            $this->documentModel->delete((int)$id, $userId);

            // Log d'audit
            Logger::audit('document_delete', "document:{$id}", $userId, [
                'filename' => $document['filename']
            ]);

            Response::success(null, 'Document supprimé définitivement');

        } catch (\Exception $e) {
            Logger::error('Erreur suppression document', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }
}
