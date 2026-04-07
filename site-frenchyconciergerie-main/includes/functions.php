<?php
/**
 * Fonctions utilitaires pour le site frenchyconciergerie.fr
 * Utilisé par l'admin (gestion panel) et le site public
 */

/**
 * Échappement HTML sécurisé
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Récupérer tous les paramètres du site
 */
function getAllSettings(PDO $conn): array {
    $settings = [];
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $conn->query("SELECT setting_key, setting_value FROM FC_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log('getAllSettings: ' . $e->getMessage());
    }
    return $settings;
}

/**
 * Récupérer les logements du site vitrine
 */
function getLogements(PDO $conn): array {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_logements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(255) NOT NULL,
            description TEXT,
            image VARCHAR(500),
            localisation VARCHAR(255),
            type_bien VARCHAR(100),
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $conn->query("SELECT * FROM FC_logements WHERE actif = 1 ORDER BY ordre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getLogements: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupérer les avis publiés
 */
function getAvis(PDO $conn): array {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_avis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            role VARCHAR(100) DEFAULT 'Propriétaire',
            date_avis DATE,
            note INT DEFAULT 5,
            commentaire TEXT,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $conn->query("SELECT * FROM FC_avis WHERE actif = 1 ORDER BY date_avis DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getAvis: ' . $e->getMessage());
        return [];
    }
}

/**
 * Afficher des étoiles HTML
 */
function renderStars(int $note): string {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $note ? '★' : '☆';
    }
    return '<span style="color: #F59E0B;">' . $stars . '</span>';
}

/**
 * Créer les tables essentielles si elles n'existent pas
 */
function ensureFcTables(PDO $conn): void {
    $tables = [
        "CREATE TABLE IF NOT EXISTS FC_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(255) NOT NULL,
            icone VARCHAR(50) DEFAULT '🏠',
            carte_info VARCHAR(500),
            description TEXT,
            liste_items JSON,
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_tarifs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(255) NOT NULL,
            pourcentage DECIMAL(5,2),
            montant DECIMAL(10,2) DEFAULT 0,
            type_tarif ENUM('pourcentage', 'euro') DEFAULT 'pourcentage',
            description TEXT,
            details TEXT,
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_distinctions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(255) NOT NULL,
            icone VARCHAR(50) DEFAULT '🏆',
            description TEXT,
            image VARCHAR(500),
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            telephone VARCHAR(50),
            sujet VARCHAR(500),
            message TEXT,
            lu TINYINT(1) DEFAULT 0,
            archive TINYINT(1) DEFAULT 0,
            statut VARCHAR(20) DEFAULT 'nouveau',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_simulations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255),
            surface INT,
            capacite INT,
            ville VARCHAR(255),
            contacted TINYINT(1) DEFAULT 0,
            statut VARCHAR(20) DEFAULT 'a_contacter',
            notes TEXT,
            centre_ville TINYINT(1) DEFAULT 0,
            fibre TINYINT(1) DEFAULT 0,
            equipements_speciaux TINYINT(1) DEFAULT 0,
            machine_cafe TINYINT(1) DEFAULT 0,
            machine_laver TINYINT(1) DEFAULT 0,
            autre_equipement VARCHAR(255),
            tarif_nuit_estime DECIMAL(10,2),
            revenu_mensuel_estime DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(50) UNIQUE,
            section_label VARCHAR(100),
            actif TINYINT(1) DEFAULT 1,
            ordre INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(500) NOT NULL,
            slug VARCHAR(500),
            contenu TEXT,
            extrait TEXT,
            categorie_id INT,
            meta_title VARCHAR(500),
            meta_description VARCHAR(500),
            actif TINYINT(1) DEFAULT 0,
            date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            slug VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_visites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page VARCHAR(500),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_conversions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(100),
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_simulateur_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) UNIQUE NOT NULL,
            config_label VARCHAR(255),
            config_value VARCHAR(255),
            config_type VARCHAR(50) DEFAULT 'number',
            ordre INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_simulateur_villes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ville VARCHAR(255) NOT NULL,
            majoration_percent DECIMAL(5,2) DEFAULT 0,
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS FC_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'viewer',
            nom VARCHAR(255),
            prenom VARCHAR(255),
            actif TINYINT(1) DEFAULT 1,
            last_login DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $sql) {
        try {
            $conn->exec($sql);
        } catch (PDOException $e) {
            error_log('ensureFcTables: ' . $e->getMessage());
        }
    }
}
