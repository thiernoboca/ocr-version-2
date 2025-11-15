<?php

namespace App\Utils;

class Validator
{
    /**
     * Valider un fichier uploadé
     */
    public static function validateUploadedFile(array $file): array
    {
        $errors = [];

        // Vérifier qu'un fichier a été uploadé
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'Aucun fichier fourni';
            return $errors;
        }

        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erreur lors de l\'upload: ' . self::getUploadErrorMessage($file['error']);
            return $errors;
        }

        // Vérifier la taille
        $maxSize = (int)$_ENV['MAX_UPLOAD_SIZE'];
        if ($file['size'] > $maxSize) {
            $errors[] = 'Fichier trop volumineux (max: ' . self::formatBytes($maxSize) . ')';
        }

        // Vérifier le type MIME
        $allowedTypes = explode(',', $_ENV['ALLOWED_FILE_TYPES']);
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowedTypes);
        }

        // Vérifier le type MIME réel du fichier
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/tiff',
            'application/pdf'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = 'Type MIME non autorisé: ' . $mimeType;
        }

        return $errors;
    }

    /**
     * Valider un type de document
     */
    public static function validateDocumentType(?string $type): bool
    {
        $validTypes = ['passport', 'id_card', 'invoice', 'receipt', 'document', 'other'];
        return in_array($type, $validTypes);
    }

    /**
     * Valider une langue OCR
     */
    public static function validateLanguage(?string $language): bool
    {
        $validLanguages = ['fra', 'eng', 'deu', 'spa', 'ita', 'fra+eng', 'eng+fra'];
        return in_array($language, $validLanguages);
    }

    /**
     * Nettoyer une chaîne de caractères
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtenir le message d'erreur d'upload
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale du formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture sur le disque',
            UPLOAD_ERR_EXTENSION => 'Extension PHP a arrêté l\'upload'
        ];

        return $errors[$errorCode] ?? 'Erreur inconnue';
    }

    /**
     * Formater des octets en format lisible
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
