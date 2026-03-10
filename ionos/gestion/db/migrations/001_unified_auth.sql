-- ============================================================
-- Migration 001: Système d'authentification unifié
-- Remplace: intervenant (auth), FC_proprietaires (auth), ionos/admin (hardcoded)
-- ============================================================

-- Table principale des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) DEFAULT NULL,
    `telephone` VARCHAR(30) DEFAULT NULL,
    `adresse` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,

    -- Rôle unique par utilisateur
    `role` ENUM('super_admin', 'gestionnaire', 'femme_de_menage', 'proprietaire', 'voyageur') NOT NULL DEFAULT 'femme_de_menage',

    -- Champs spécifiques staff (ex-intervenant)
    `numero` VARCHAR(50) DEFAULT NULL COMMENT 'Numéro interne staff',
    `role1` VARCHAR(255) DEFAULT NULL COMMENT 'Rôle métier 1 (conducteur, ménage...)',
    `role2` VARCHAR(255) DEFAULT NULL COMMENT 'Rôle métier 2',
    `role3` VARCHAR(255) DEFAULT NULL COMMENT 'Rôle métier 3',

    -- Champs spécifiques propriétaire
    `societe` VARCHAR(255) DEFAULT NULL,
    `siret` VARCHAR(20) DEFAULT NULL,
    `rib_iban` VARCHAR(40) DEFAULT NULL,
    `rib_bic` VARCHAR(15) DEFAULT NULL,
    `rib_banque` VARCHAR(100) DEFAULT NULL,
    `commission` DECIMAL(5,2) DEFAULT NULL COMMENT 'Commission en % (ex: 20.00 conciergerie, 6.00 opti)',
    `notes_admin` TEXT DEFAULT NULL COMMENT 'Notes internes admin, non visible par le user',

    -- État du compte
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `derniere_connexion` DATETIME DEFAULT NULL,
    `token_reset` VARCHAR(255) DEFAULT NULL,
    `token_reset_expire` DATETIME DEFAULT NULL,

    -- Références vers les anciens IDs (pour migration)
    `legacy_intervenant_id` INT DEFAULT NULL COMMENT 'Ancien ID dans table intervenant',
    `legacy_proprietaire_id` INT DEFAULT NULL COMMENT 'Ancien ID dans table FC_proprietaires',

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_actif` (`actif`),
    KEY `idx_legacy_intervenant` (`legacy_intervenant_id`),
    KEY `idx_legacy_proprietaire` (`legacy_proprietaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions par page (remplace intervenants_pages + pages_accessibles CSV)
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `user_id` INT NOT NULL,
    `page_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `page_id`),
    KEY `idx_page` (`page_id`),
    CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_permissions_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting unifié (remplace login_attempts + FC_rate_limit)
CREATE TABLE IF NOT EXISTS `auth_rate_limit` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `action` VARCHAR(50) NOT NULL DEFAULT 'login',
    `identifier` VARCHAR(255) DEFAULT NULL COMMENT 'Email ou autre identifiant',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_action` (`ip_address`, `action`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
