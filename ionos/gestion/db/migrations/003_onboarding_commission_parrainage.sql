-- ============================================================================
-- Migration 003 : Self-Boarding + Commission configurable + Parrainage
-- FrenchyConciergerie
-- ============================================================================

-- 1. Demandes d'onboarding (tunnel wizard)
CREATE TABLE IF NOT EXISTS onboarding_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token securise pour reprise du parcours',

    -- Progression
    etape_courante TINYINT DEFAULT 1 COMMENT 'Etape active (1-6)',
    statut ENUM('brouillon','en_cours','termine','abandonne') DEFAULT 'brouillon',
    progression TINYINT DEFAULT 0 COMMENT 'Pourcentage completion 0-100',

    -- Etape 1: Le bien
    adresse TEXT,
    complement_adresse VARCHAR(255),
    code_postal VARCHAR(10),
    ville VARCHAR(100),
    pays VARCHAR(50) DEFAULT 'France',
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    typologie ENUM('studio','T1','T2','T3','T4','T5+','maison','villa') DEFAULT NULL,
    superficie INT COMMENT 'en m2',
    nb_pieces INT DEFAULT 1,
    nb_couchages INT DEFAULT 2,
    etage VARCHAR(20),
    ascenseur TINYINT(1) DEFAULT 0,
    parking TINYINT(1) DEFAULT 0,
    photos JSON COMMENT '["url1","url2",...]',

    -- Etape 2: Le proprio
    prenom VARCHAR(100),
    nom VARCHAR(100),
    email VARCHAR(255),
    telephone VARCHAR(20),
    societe VARCHAR(255),
    siret VARCHAR(20),

    -- Etape 3: Equipements
    equipements JSON COMMENT '{"wifi":true,"climatisation":false,...}',
    description_bien TEXT COMMENT 'Description libre du bien',

    -- Etape 4: Pack & Commission
    pack ENUM('autonome','serenite','cle_en_main') DEFAULT 'autonome',
    commission_base DECIMAL(5,2) DEFAULT 10.00,
    options_supplementaires JSON COMMENT '{"menage_inclus":true,...}',

    -- Etape 5: Tarifs
    prix_souhaite DECIMAL(10,2) COMMENT 'Prix/nuit souhaite par le proprio',
    prix_min DECIMAL(10,2),
    prix_max DECIMAL(10,2),
    accepte_prix_dynamique TINYINT(1) DEFAULT 1,

    -- Etape 6: Validation
    conditions_acceptees TINYINT(1) DEFAULT 0,
    rgpd_accepte TINYINT(1) DEFAULT 0,
    signature_date DATETIME,

    -- Resultat
    proprietaire_id INT COMMENT 'FK vers FC_proprietaires une fois cree',
    logement_id INT COMMENT 'FK vers liste_logements une fois cree',

    -- Parrainage
    code_parrain VARCHAR(30) COMMENT 'Code parrainage utilise',

    -- Meta
    source VARCHAR(50) COMMENT 'landing, facebook, google, bouche_a_oreille',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME,

    INDEX idx_email (email),
    INDEX idx_statut (statut),
    INDEX idx_token (token),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Configuration commission par proprietaire (extensible)
CREATE TABLE IF NOT EXISTS proprietaire_commission_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proprietaire_id INT NOT NULL,

    -- Pack de base
    pack ENUM('autonome','serenite','cle_en_main') DEFAULT 'autonome',
    commission_base DECIMAL(5,2) DEFAULT 10.00 COMMENT '10% autonome, 20% serenite, 30% cle_en_main',

    -- Reduction parrainage (cumulable, min 5%)
    reduction_parrainage DECIMAL(5,2) DEFAULT 0.00 COMMENT '-1% par filleul actif',

    -- Commission effective = commission_base - reduction_parrainage (min 5%)
    commission_effective DECIMAL(5,2) GENERATED ALWAYS AS (
        GREATEST(5.00, commission_base - reduction_parrainage)
    ) STORED,

    -- Options a la carte (JSON flexible)
    options JSON DEFAULT NULL COMMENT '{"menage_inclus":{"actif":false,"surcharge":0},...}',

    -- Services inclus selon pack
    services_inclus JSON COMMENT 'Liste des services actifs',

    -- Equipement fourni (pack cle_en_main)
    equipement_fourni TINYINT(1) DEFAULT 0,
    budget_equipement DECIMAL(10,2) DEFAULT 0 COMMENT 'Budget equipement investi par Frenchy',

    -- Historique
    notes_admin TEXT,
    modifie_par INT COMMENT 'ID admin qui a modifie',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_proprietaire (proprietaire_id),
    INDEX idx_pack (pack),
    INDEX idx_commission (commission_effective)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Systeme de parrainage
CREATE TABLE IF NOT EXISTS codes_parrainage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proprietaire_id INT NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE COMMENT 'Ex: FRENCHYJEAN45',

    nb_utilisations INT DEFAULT 0,
    max_utilisations INT DEFAULT NULL COMMENT 'NULL = illimite',

    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_proprio (proprietaire_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parrainages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parrain_id INT NOT NULL COMMENT 'Proprietaire qui parraine',
    filleul_id INT NOT NULL COMMENT 'Proprietaire parraine',
    code_utilise VARCHAR(30),

    -- Avantages
    reduction_parrain DECIMAL(5,2) DEFAULT 1.00 COMMENT '-1% commission pour le parrain',
    avantage_filleul VARCHAR(100) DEFAULT 'photos_pro_offertes',

    statut ENUM('en_attente','actif','expire','annule') DEFAULT 'en_attente',
    active_depuis DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_parrain (parrain_id),
    INDEX idx_filleul (filleul_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Taches auto-generees apres onboarding
CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    type ENUM(
        'create_proprietaire',
        'create_logement',
        'create_email',
        'generate_site',
        'generate_guide',
        'sms_bienvenue',
        'setup_superhote',
        'create_frenchysite'
    ) NOT NULL,
    statut ENUM('pending','processing','done','error','skipped') DEFAULT 'pending',
    result JSON COMMENT 'Resultat de la tache',
    error_message TEXT,
    retry_count TINYINT DEFAULT 0,

    executed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_request (request_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Frenchy Score (gamification du bien)
CREATE TABLE IF NOT EXISTS frenchy_score (
    id INT AUTO_INCREMENT PRIMARY KEY,
    logement_id INT NOT NULL,

    score_global TINYINT DEFAULT 50 COMMENT '0-100',
    score_annonce TINYINT DEFAULT 50,
    score_reactivite TINYINT DEFAULT 50,
    score_satisfaction TINYINT DEFAULT 50,
    score_prix TINYINT DEFAULT 50,
    score_entretien TINYINT DEFAULT 50,

    badge VARCHAR(50) COMMENT 'standard, silver, gold, premium',
    conseil_ia TEXT COMMENT 'Dernier conseil IA genere',

    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_logement (logement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
