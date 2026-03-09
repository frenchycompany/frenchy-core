<?php
/**
 * install_coffre_fort.php — Création des tables pour le coffre-fort numérique
 * À exécuter une seule fois via : php install_coffre_fort.php
 */

require_once __DIR__ . '/../config.php';

echo "=== Installation Coffre-Fort Numérique ===\n\n";

$queries = [
    // Fichiers stockés dans le coffre
    "CREATE TABLE IF NOT EXISTS `coffre_fort_fichiers` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nom_original` VARCHAR(255) NOT NULL,
        `nom_stockage` VARCHAR(255) NOT NULL COMMENT 'Nom chiffré sur disque',
        `chemin_relatif` VARCHAR(500) NOT NULL,
        `type_mime` VARCHAR(100) NOT NULL,
        `taille` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `categorie` ENUM('photo','video','document','contrat','identite','autre') NOT NULL DEFAULT 'autre',
        `description` TEXT DEFAULT NULL,
        `tags` VARCHAR(500) DEFAULT NULL,
        `cle_chiffrement` VARCHAR(255) DEFAULT NULL COMMENT 'Clé AES unique par fichier (chiffrée)',
        `iv` VARCHAR(64) DEFAULT NULL COMMENT 'Vecteur initialisation AES',
        `hash_sha256` VARCHAR(64) NOT NULL COMMENT 'Hash intégrité du fichier original',
        `uploade_par` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `supprime` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_categorie` (`categorie`),
        KEY `idx_uploade_par` (`uploade_par`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Sessions 2FA pour accès au coffre
    "CREATE TABLE IF NOT EXISTS `coffre_fort_2fa` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `code` VARCHAR(6) NOT NULL,
        `telephone` VARCHAR(20) NOT NULL,
        `expire_at` DATETIME NOT NULL,
        `verifie` TINYINT(1) NOT NULL DEFAULT 0,
        `tentatives` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_code` (`user_id`, `code`),
        KEY `idx_expire` (`expire_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Sessions de consultation validées (après 2FA)
    "CREATE TABLE IF NOT EXISTS `coffre_fort_sessions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `token` VARCHAR(128) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `user_agent_hash` VARCHAR(64) NOT NULL,
        `expire_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_token` (`token`),
        KEY `idx_user_expire` (`user_id`, `expire_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Journal d'accès complet
    "CREATE TABLE IF NOT EXISTS `coffre_fort_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED DEFAULT NULL,
        `action` ENUM('login_2fa','verification_ok','verification_fail','consultation','upload','suppression','session_expire') NOT NULL,
        `fichier_id` INT UNSIGNED DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `details` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_action` (`action`),
        KEY `idx_fichier` (`fichier_id`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($queries as $sql) {
    try {
        $conn->exec($sql);
        // Extraire le nom de la table
        preg_match('/`(\w+)`/', $sql, $m);
        echo "✓ Table {$m[1]} créée/vérifiée\n";
    } catch (PDOException $e) {
        echo "✗ Erreur : " . $e->getMessage() . "\n";
    }
}

echo "\n=== Installation terminée ===\n";
