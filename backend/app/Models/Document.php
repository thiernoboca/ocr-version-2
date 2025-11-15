<?php

namespace App\Models;

use App\Utils\Database;

class Document
{
    /**
     * Créer un nouveau document
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO documents (
                    user_id, filename, original_name, file_path, file_size, mime_type,
                    document_type, ocr_text, ocr_data, ocr_confidence, ocr_engine,
                    processing_time, language, created_at
                ) VALUES (
                    :user_id, :filename, :original_name, :file_path, :file_size, :mime_type,
                    :document_type, :ocr_text, :ocr_data, :ocr_confidence, :ocr_engine,
                    :processing_time, :language, NOW()
                )";

        Database::query($sql, $data);

        return (int)Database::lastInsertId();
    }

    /**
     * Trouver un document par ID (et vérifier qu'il appartient à l'utilisateur)
     */
    public function findById(int $id, int $userId): ?array
    {
        $sql = "SELECT * FROM documents WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = Database::query($sql, ['id' => $id, 'user_id' => $userId]);
        $document = $stmt->fetch();

        if ($document) {
            // Parser le JSON de ocr_data
            $document['ocr_data'] = json_decode($document['ocr_data'], true);
        }

        return $document ?: null;
    }

    /**
     * Trouver tous les documents d'un utilisateur
     */
    public function findByUserId(int $userId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT id, filename, original_name, file_size, mime_type, document_type,
                       ocr_confidence, ocr_engine, processing_time, language, created_at
                FROM documents
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = Database::query($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $documents = $stmt->fetchAll();

        // Compter le total
        $countSql = "SELECT COUNT(*) as total FROM documents WHERE user_id = :user_id";
        $countStmt = Database::query($countSql, ['user_id' => $userId]);
        $total = $countStmt->fetch()['total'];

        return [
            'documents' => $documents,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => (int)ceil($total / $limit)
            ]
        ];
    }

    /**
     * Supprimer un document
     */
    public function delete(int $id, int $userId): void
    {
        $sql = "DELETE FROM documents WHERE id = :id AND user_id = :user_id";
        Database::query($sql, ['id' => $id, 'user_id' => $userId]);
    }

    /**
     * Supprimer les documents expirés (RGPD - rétention limitée)
     */
    public function deleteExpired(): int
    {
        $retentionDays = (int)$_ENV['DOCUMENT_RETENTION_DAYS'];

        $sql = "DELETE FROM documents
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = Database::query($sql, ['days' => $retentionDays]);

        return $stmt->rowCount();
    }

    /**
     * Rechercher dans les documents OCR
     */
    public function search(int $userId, string $query): array
    {
        $sql = "SELECT id, filename, original_name, document_type, ocr_text, created_at
                FROM documents
                WHERE user_id = :user_id AND ocr_text LIKE :query
                ORDER BY created_at DESC
                LIMIT 50";

        $stmt = Database::query($sql, [
            'user_id' => $userId,
            'query' => '%' . $query . '%'
        ]);

        return $stmt->fetchAll();
    }
}
