<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Document;
use App\Middleware\AuthMiddleware;
use App\Utils\Response;
use App\Utils\Logger;

/**
 * Contrôleur pour les fonctionnalités RGPD
 */
class GdprController
{
    private User $userModel;
    private Document $documentModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->documentModel = new Document();
    }

    /**
     * Exporter toutes les données de l'utilisateur (portabilité RGPD)
     */
    public function exportData(): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();

            // Récupérer les données utilisateur
            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::notFound('Utilisateur non trouvé');
                return;
            }

            // Récupérer tous les documents
            $documents = $this->documentModel->findByUserId($userId, 1, 10000);

            // Préparer l'export
            $export = [
                'export_date' => date('c'),
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'created_at' => $user['created_at'],
                    'last_login_at' => $user['last_login_at']
                ],
                'documents' => array_map(function($doc) {
                    // Exclure les chemins de fichiers internes
                    unset($doc['file_path']);
                    return $doc;
                }, $documents['documents']),
                'statistics' => [
                    'total_documents' => $documents['pagination']['total'],
                    'total_size_mb' => array_sum(array_column($documents['documents'], 'file_size')) / 1024 / 1024
                ]
            ];

            // Log d'audit
            Logger::audit('gdpr_export', "user:{$userId}", $userId);

            // Retourner le JSON
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="my_data_' . date('Y-m-d') . '.json"');
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Logger::error('Erreur export RGPD', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }

    /**
     * Supprimer définitivement le compte et toutes les données (droit à l'oubli RGPD)
     */
    public function deleteAccount(): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();

            // Récupérer tous les documents pour supprimer les fichiers
            $documents = $this->documentModel->findByUserId($userId, 1, 10000);

            foreach ($documents['documents'] as $doc) {
                $docDetail = $this->documentModel->findById($doc['id'], $userId);
                if ($docDetail && file_exists($docDetail['file_path'])) {
                    unlink($docDetail['file_path']);
                }
            }

            // Supprimer l'utilisateur et toutes ses données
            $this->userModel->delete($userId);

            // Log d'audit (avant suppression)
            Logger::audit('gdpr_delete_account', "user:{$userId}", $userId);

            Response::success(null, 'Compte et données supprimés définitivement');

        } catch (\Exception $e) {
            Logger::error('Erreur suppression compte', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }

    /**
     * Obtenir les informations sur les données conservées
     */
    public function dataInfo(): void
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();

            $documents = $this->documentModel->findByUserId($userId, 1, 10000);
            $user = $this->userModel->findById($userId);

            $retentionDays = (int)$_ENV['DOCUMENT_RETENTION_DAYS'];

            $info = [
                'data_retention_policy' => [
                    'retention_period_days' => $retentionDays,
                    'auto_delete_enabled' => $_ENV['AUTO_DELETE_ENABLED'] === 'true',
                    'description' => "Les documents sont automatiquement supprimés après {$retentionDays} jours"
                ],
                'your_data' => [
                    'account_created' => $user['created_at'],
                    'total_documents' => $documents['pagination']['total'],
                    'total_storage_mb' => round(array_sum(array_column($documents['documents'], 'file_size')) / 1024 / 1024, 2)
                ],
                'your_rights' => [
                    'right_to_access' => 'Vous pouvez consulter toutes vos données à tout moment',
                    'right_to_portability' => 'Vous pouvez exporter toutes vos données au format JSON',
                    'right_to_erasure' => 'Vous pouvez supprimer votre compte et toutes vos données',
                    'right_to_rectification' => 'Vous pouvez modifier vos informations personnelles'
                ],
                'data_processing' => [
                    'purpose' => 'OCR et reconnaissance de documents',
                    'legal_basis' => 'Consentement',
                    'storage_location' => 'Serveur local chiffré',
                    'third_parties' => 'Aucun partage avec des tiers'
                ]
            ];

            Response::success($info);

        } catch (\Exception $e) {
            Logger::error('Erreur info RGPD', ['error' => $e->getMessage()]);
            Response::serverError();
        }
    }
}
