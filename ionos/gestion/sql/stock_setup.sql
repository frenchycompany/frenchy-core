-- ============================================
-- Stock & Consommables — Schéma de base
-- ============================================

-- Fournisseurs
CREATE TABLE IF NOT EXISTS stock_fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    url VARCHAR(255) DEFAULT NULL,
    contact VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produits
CREATE TABLE IF NOT EXISTS stock_produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(150) NOT NULL,
    categorie ENUM('menage','toilettes','cuisine','literie','entretien','bureau','autre') DEFAULT 'autre',
    unite ENUM('piece','litre','rouleau','kg','lot','boite','sachet','bidon') DEFAULT 'piece',
    stock_actuel DECIMAL(10,2) DEFAULT 0,
    seuil_alerte DECIMAL(10,2) DEFAULT 5,
    logement_id INT DEFAULT NULL COMMENT 'NULL = stock global',
    photo_url VARCHAR(255) DEFAULT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_categorie (categorie),
    KEY idx_logement (logement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mouvements de stock (entrées, sorties, ajustements)
CREATE TABLE IF NOT EXISTS stock_mouvements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    type_mouvement ENUM('entree','sortie','inventaire') NOT NULL,
    quantite DECIMAL(10,2) NOT NULL,
    prix_unitaire DECIMAL(10,2) DEFAULT NULL,
    fournisseur_id INT DEFAULT NULL,
    logement_id INT DEFAULT NULL COMMENT 'Logement consommateur (sorties)',
    intervenant_id INT DEFAULT NULL COMMENT 'Qui a fait le mouvement',
    planning_id INT DEFAULT NULL COMMENT 'Lien intervention si applicable',
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_produit (produit_id),
    KEY idx_type (type_mouvement),
    KEY idx_logement (logement_id),
    KEY idx_date (created_at),
    FOREIGN KEY (produit_id) REFERENCES stock_produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relevé de prix par fournisseur
CREATE TABLE IF NOT EXISTS stock_prix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    url_produit VARCHAR(500) DEFAULT NULL,
    date_releve DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_produit (produit_id),
    KEY idx_fournisseur (fournisseur_id),
    FOREIGN KEY (produit_id) REFERENCES stock_produits(id) ON DELETE CASCADE,
    FOREIGN KEY (fournisseur_id) REFERENCES stock_fournisseurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fournisseurs par défaut
INSERT IGNORE INTO stock_fournisseurs (nom) VALUES
('Action'), ('Leclerc'), ('Amazon'), ('Metro'), ('Carrefour'), ('Leroy Merlin'), ('Autre');
