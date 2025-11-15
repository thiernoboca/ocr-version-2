#!/usr/bin/env php
<?php

/**
 * Script CRON pour supprimer automatiquement les documents expirés
 * Conformité RGPD : rétention limitée des données
 *
 * Ajouter à crontab:
 * 0 2 * * * /usr/bin/php /path/to/cleanup_expired_documents.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Document;
use App\Utils\Logger;
use Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Vérifier si l'auto-suppression est activée
if ($_ENV['AUTO_DELETE_ENABLED'] !== 'true') {
    echo "Auto-suppression désactivée. Quittant...\n";
    exit(0);
}

echo "Démarrage du nettoyage des documents expirés...\n";
Logger::info('Démarrage du nettoyage automatique des documents expirés');

try {
    $documentModel = new Document();
    $deletedCount = $documentModel->deleteExpired();

    echo "Documents supprimés: {$deletedCount}\n";
    Logger::info('Nettoyage terminé', ['deleted_count' => $deletedCount]);

    // Log d'audit
    Logger::audit('auto_cleanup', 'system', null, [
        'deleted_count' => $deletedCount,
        'retention_days' => (int)$_ENV['DOCUMENT_RETENTION_DAYS']
    ]);

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    Logger::error('Erreur lors du nettoyage automatique', ['error' => $e->getMessage()]);
    exit(1);
}

echo "Nettoyage terminé avec succès.\n";
exit(0);
