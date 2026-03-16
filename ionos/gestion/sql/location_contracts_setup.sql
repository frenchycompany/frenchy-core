-- ============================================
-- Contrats de location - Tables
-- Systeme de contrats pour reservations directes
-- ============================================

-- Templates de contrats de location
CREATE TABLE IF NOT EXISTS location_contract_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    placeholders TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Champs dynamiques pour les contrats de location
CREATE TABLE IF NOT EXISTS location_contract_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    input_type ENUM('text', 'number', 'textarea', 'date', 'select') DEFAULT 'text',
    options TEXT DEFAULT NULL,
    field_group ENUM('voyageur', 'reservation', 'logement', 'proprietaire', 'autre') DEFAULT 'autre',
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Details personnalises par logement (description, equipements, regles, etc.)
CREATE TABLE IF NOT EXISTS location_contract_logement_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT NOT NULL,
    description_logement TEXT DEFAULT NULL,
    equipements TEXT DEFAULT NULL,
    regles_maison TEXT DEFAULT NULL,
    heure_arrivee VARCHAR(10) DEFAULT '16:00',
    heure_depart VARCHAR(10) DEFAULT '10:00',
    depot_garantie DECIMAL(10,2) DEFAULT NULL,
    taxe_sejour_par_nuit DECIMAL(10,2) DEFAULT NULL,
    conditions_annulation TEXT DEFAULT NULL,
    informations_supplementaires TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_logement (logement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contrats de location generes
CREATE TABLE IF NOT EXISTS generated_location_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    logement_id INT NOT NULL,
    template_title VARCHAR(255) DEFAULT NULL,
    logement_nom VARCHAR(255) DEFAULT NULL,
    voyageur_nom VARCHAR(255) DEFAULT NULL,
    date_arrivee DATE DEFAULT NULL,
    date_depart DATE DEFAULT NULL,
    prix_total DECIMAL(10,2) DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Champs par defaut pour contrats de location
-- ============================================
INSERT IGNORE INTO location_contract_fields (field_name, description, input_type, field_group, sort_order) VALUES
-- Voyageur
('nom_voyageur', 'Nom du voyageur', 'text', 'voyageur', 10),
('prenom_voyageur', 'Prenom du voyageur', 'text', 'voyageur', 20),
('email_voyageur', 'Email du voyageur', 'text', 'voyageur', 30),
('telephone_voyageur', 'Telephone du voyageur', 'text', 'voyageur', 40),
('adresse_voyageur', 'Adresse du voyageur', 'textarea', 'voyageur', 50),
-- Reservation
('date_arrivee', 'Date d\'arrivee', 'date', 'reservation', 100),
('date_depart', 'Date de depart', 'date', 'reservation', 110),
('nombre_nuits', 'Nombre de nuits', 'number', 'reservation', 120),
('nombre_voyageurs', 'Nombre de voyageurs', 'number', 'reservation', 130),
('prix_nuit', 'Prix par nuit (EUR)', 'number', 'reservation', 140),
('prix_total', 'Prix total sejour (EUR)', 'number', 'reservation', 150),
('prix_menage', 'Frais de menage (EUR)', 'number', 'reservation', 160),
('prix_taxe_sejour', 'Taxe de sejour totale (EUR)', 'number', 'reservation', 170),
('depot_garantie', 'Depot de garantie (EUR)', 'number', 'reservation', 180),
-- Logement (auto-fill)
('nom_du_logement', 'Nom du logement', 'text', 'logement', 200),
('adresse_logement', 'Adresse du logement', 'text', 'logement', 210),
('ville', 'Ville', 'text', 'logement', 220),
('code_postal', 'Code postal', 'text', 'logement', 230),
('type_logement', 'Type de logement', 'text', 'logement', 240),
('capacite', 'Capacite (personnes)', 'number', 'logement', 250),
('surface_m2', 'Surface (m2)', 'number', 'logement', 260),
-- Details logement (auto-fill depuis location_contract_logement_details)
('description_logement', 'Description du logement', 'textarea', 'logement', 270),
('equipements', 'Equipements', 'textarea', 'logement', 280),
('regles_maison', 'Regles de la maison', 'textarea', 'logement', 290),
('heure_arrivee', 'Heure d\'arrivee', 'text', 'logement', 300),
('heure_depart', 'Heure de depart', 'text', 'logement', 310),
('conditions_annulation', 'Conditions d\'annulation', 'textarea', 'logement', 320),
-- Proprietaire (auto-fill depuis FC_proprietaires)
('proprietaire_nom_complet', 'Nom complet du proprietaire', 'text', 'proprietaire', 500),
('proprietaire_email', 'Email du proprietaire', 'text', 'proprietaire', 510),
('proprietaire_telephone', 'Telephone du proprietaire', 'text', 'proprietaire', 520),
('proprietaire_adresse', 'Adresse du proprietaire (ligne 1)', 'text', 'proprietaire', 530),
('proprietaire_adresse_ligne2', 'Adresse du proprietaire (ligne 2)', 'text', 'proprietaire', 540),
('proprietaire_code_postal', 'Code postal du proprietaire', 'text', 'proprietaire', 550),
('proprietaire_ville', 'Ville du proprietaire', 'text', 'proprietaire', 560),
('proprietaire_adresse_complete', 'Adresse complete du proprietaire', 'text', 'proprietaire', 570),
('proprietaire_societe', 'Societe du proprietaire', 'text', 'proprietaire', 580),
('proprietaire_siret', 'SIRET du proprietaire', 'text', 'proprietaire', 590),
-- Autre
('date_contrat', 'Date du contrat', 'date', 'autre', 600),
('lieu_signature', 'Lieu de signature', 'text', 'autre', 610);
