-- Base de données pour le système OCR de documents
-- Version 2.0

CREATE DATABASE IF NOT EXISTS ocr_documents CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ocr_documents;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des documents
CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,

    -- Informations fichier
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,

    -- Type de document
    document_type ENUM('passport', 'id_card', 'invoice', 'receipt', 'document', 'other') DEFAULT 'document',

    -- Résultats OCR
    ocr_text MEDIUMTEXT,
    ocr_data JSON,
    ocr_confidence DECIMAL(3,2) DEFAULT 0.00,
    ocr_engine VARCHAR(50),
    processing_time DECIMAL(5,2),
    language VARCHAR(20) DEFAULT 'fra+eng',

    -- Métadonnées
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Clés étrangères et index
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_document_type (document_type),
    INDEX idx_created_at (created_at),
    FULLTEXT INDEX idx_ocr_text (ocr_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des sessions (optionnel)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'audit (RGPD)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    resource VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vue pour les statistiques
CREATE OR REPLACE VIEW document_stats AS
SELECT
    user_id,
    COUNT(*) as total_documents,
    SUM(file_size) as total_size,
    AVG(ocr_confidence) as avg_confidence,
    AVG(processing_time) as avg_processing_time,
    document_type,
    ocr_engine
FROM documents
GROUP BY user_id, document_type, ocr_engine;

-- Créer un utilisateur par défaut pour les tests (à supprimer en production)
INSERT INTO users (name, email, password)
VALUES ('Test User', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE name=name;
-- Mot de passe: password

-- Event pour supprimer automatiquement les documents expirés (RGPD)
DELIMITER //
CREATE EVENT IF NOT EXISTS delete_expired_documents
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    DELETE FROM documents
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- Activer l'event scheduler
SET GLOBAL event_scheduler = ON;
