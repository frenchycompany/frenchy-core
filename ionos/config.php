<?php
/**
 * Configuration principale Frenchy Conciergerie
 */

// Connexion base de données
require_once __DIR__ . '/db/connection.php';

// Inclure les classes de sécurité
require_once __DIR__ . '/includes/security.php';

// Initialiser la sécurité si connexion OK
$security = null;
if ($conn) {
    // Créer les tables de sécurité si nécessaire
    try {
        // Table CSRF tokens
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_csrf_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Rate Limiting
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt DATETIME,
            last_attempt DATETIME,
            blocked_until DATETIME,
            INDEX idx_ip_action (ip_address, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Visites (Analytics)
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_visites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            referer TEXT,
            session_id VARCHAR(64),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_page (page),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Conversions (Analytics)
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_conversions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            source VARCHAR(100),
            ip_address VARCHAR(45),
            donnees JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Utilisateurs Admin
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'admin', 'editor', 'viewer') DEFAULT 'viewer',
            nom VARCHAR(100),
            prenom VARCHAR(100),
            actif TINYINT(1) DEFAULT 1,
            last_login DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Articles Blog
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_articles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            contenu LONGTEXT,
            extrait TEXT,
            image VARCHAR(255),
            categorie_id INT,
            auteur_id INT,
            meta_title VARCHAR(255),
            meta_description TEXT,
            actif TINYINT(1) DEFAULT 0,
            date_publication DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_actif (actif),
            INDEX idx_date (date_publication)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Ajouter les colonnes manquantes à FC_articles si elles n'existent pas
        $colonnesArticles = [
            'categorie_id' => 'INT',
            'auteur_id' => 'INT',
            'extrait' => 'TEXT',
            'meta_title' => 'VARCHAR(255)',
            'meta_description' => 'TEXT',
            'image' => 'VARCHAR(255)',
            'nb_vues' => 'INT DEFAULT 0',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        foreach ($colonnesArticles as $col => $type) {
            try {
                $conn->exec("ALTER TABLE FC_articles ADD COLUMN $col $type");
            } catch (PDOException $e) {
                // Colonne existe déjà
            }
        }

        // Table Catégories Blog
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            actif TINYINT(1) DEFAULT 1,
            ordre INT DEFAULT 0,
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Sessions Admin (pour tracking)
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_admin_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(64) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (session_token),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table Logs Admin
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    } catch (PDOException $e) {
        // Silently continue if tables already exist
    }

    $security = new Security($conn);
}
?>
