<?php
/**
 * Script d'installation des tables — A executer une seule fois lors du deploiement
 * Centralise toutes les CREATE TABLE IF NOT EXISTS depuis les pages individuelles
 * Usage : php ionos/gestion/db/install_tables.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';

// ============================================================================
// TABLES SUR LA BASE IONOS (via $conn)
// ============================================================================

$tables_ionos = [

    // --- Contrats de location (existait deja) ---
    "CREATE TABLE IF NOT EXISTS location_contract_templates (
        id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT NOT NULL,
        placeholders TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS location_contract_fields (
        id INT AUTO_INCREMENT PRIMARY KEY, field_name VARCHAR(255) NOT NULL UNIQUE,
        description VARCHAR(255) NOT NULL, input_type ENUM('text','number','textarea','date','select') DEFAULT 'text',
        options TEXT DEFAULT NULL, field_group ENUM('voyageur','reservation','logement','autre') DEFAULT 'autre',
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS location_contract_logement_details (
        id INT AUTO_INCREMENT PRIMARY KEY, logement_id INT NOT NULL,
        description_logement TEXT, equipements TEXT, regles_maison TEXT,
        heure_arrivee VARCHAR(10) DEFAULT '16:00', heure_depart VARCHAR(10) DEFAULT '10:00',
        depot_garantie DECIMAL(10,2), taxe_sejour_par_nuit DECIMAL(10,2),
        conditions_annulation TEXT, informations_supplementaires TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS generated_location_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT NULL, logement_id INT NOT NULL,
        template_title VARCHAR(255), logement_nom VARCHAR(255), voyageur_nom VARCHAR(255),
        date_arrivee DATE, date_depart DATE, prix_total DECIMAL(10,2),
        file_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Rate limiting (existait deja) ---
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        nom_utilisateur VARCHAR(255) DEFAULT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_dashboard.php, checkup_faire.php, checkup_logement.php ---
    "CREATE TABLE IF NOT EXISTS checkup_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        intervenant_id INT DEFAULT NULL,
        statut ENUM('en_cours','termine') DEFAULT 'en_cours',
        nb_ok INT DEFAULT 0,
        nb_problemes INT DEFAULT 0,
        nb_absents INT DEFAULT 0,
        nb_taches_faites INT DEFAULT 0,
        commentaire_general TEXT DEFAULT NULL,
        signature_path VARCHAR(500) DEFAULT NULL,
        video_path VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_dashboard.php ---
    "CREATE TABLE IF NOT EXISTS todo_list (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        description TEXT NOT NULL,
        statut ENUM('en attente','en cours','termine') DEFAULT 'en attente',
        date_limite DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_dashboard.php ---
    "CREATE TABLE IF NOT EXISTS sessions_inventaire (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        statut ENUM('en_cours','terminee') DEFAULT 'en_cours',
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_dashboard.php ---
    "CREATE TABLE IF NOT EXISTS inventaire_objets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        nom_objet VARCHAR(255) NOT NULL,
        quantite INT DEFAULT 1,
        piece VARCHAR(100) DEFAULT NULL,
        INDEX idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_faire.php, checkup_logement.php ---
    "CREATE TABLE IF NOT EXISTS checkup_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        categorie VARCHAR(50) NOT NULL,
        nom_item VARCHAR(255) NOT NULL,
        statut ENUM('ok','probleme','absent','non_verifie') DEFAULT 'non_verifie',
        commentaire TEXT DEFAULT NULL,
        photo_path VARCHAR(500) DEFAULT NULL,
        todo_task_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id),
        FOREIGN KEY (session_id) REFERENCES checkup_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis checkup_templates.php ---
    "CREATE TABLE IF NOT EXISTS checkup_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT DEFAULT NULL,
        categorie VARCHAR(50) NOT NULL,
        nom_item VARCHAR(255) NOT NULL,
        actif TINYINT(1) DEFAULT 1,
        ordre INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id),
        INDEX idx_categorie (categorie)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis import_photos_airbnb.php ---
    "CREATE TABLE IF NOT EXISTS logement_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        url_source TEXT DEFAULT NULL,
        caption VARCHAR(255) DEFAULT NULL,
        ordre INT DEFAULT 0,
        source ENUM('airbnb', 'booking', 'manual', 'autre') DEFAULT 'airbnb',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis logement_equipements.php ---
    "CREATE TABLE IF NOT EXISTS logement_equipements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        nombre_couchages INT DEFAULT 0,
        nombre_chambres INT DEFAULT 0,
        nombre_salles_bain INT DEFAULT 0,
        superficie_m2 INT DEFAULT 0,
        etage VARCHAR(50) DEFAULT NULL,
        ascenseur TINYINT(1) DEFAULT 0,
        code_wifi VARCHAR(100) DEFAULT NULL,
        nom_wifi VARCHAR(100) DEFAULT NULL,
        code_porte VARCHAR(100) DEFAULT NULL,
        code_boite_cles VARCHAR(100) DEFAULT NULL,
        instructions_arrivee TEXT DEFAULT NULL,
        machine_cafe_type VARCHAR(50) DEFAULT 'aucune',
        machine_cafe_autre VARCHAR(100) DEFAULT NULL,
        bouilloire TINYINT(1) DEFAULT 0,
        grille_pain TINYINT(1) DEFAULT 0,
        micro_ondes TINYINT(1) DEFAULT 0,
        four TINYINT(1) DEFAULT 0,
        plaque_cuisson TINYINT(1) DEFAULT 0,
        plaque_cuisson_type VARCHAR(50) DEFAULT NULL,
        lave_vaisselle TINYINT(1) DEFAULT 0,
        refrigerateur TINYINT(1) DEFAULT 0,
        congelateur TINYINT(1) DEFAULT 0,
        ustensiles_cuisine TINYINT(1) DEFAULT 0,
        machine_laver TINYINT(1) DEFAULT 0,
        seche_linge TINYINT(1) DEFAULT 0,
        fer_repasser TINYINT(1) DEFAULT 0,
        table_repasser TINYINT(1) DEFAULT 0,
        aspirateur TINYINT(1) DEFAULT 0,
        produits_menage TINYINT(1) DEFAULT 0,
        tv TINYINT(1) DEFAULT 0,
        tv_type VARCHAR(100) DEFAULT NULL,
        tv_pouces INT DEFAULT NULL,
        netflix TINYINT(1) DEFAULT 0,
        amazon_prime TINYINT(1) DEFAULT 0,
        disney_plus TINYINT(1) DEFAULT 0,
        molotov_tv TINYINT(1) DEFAULT 0,
        chaines_tv TEXT DEFAULT NULL,
        enceinte_bluetooth TINYINT(1) DEFAULT 0,
        console_jeux TINYINT(1) DEFAULT 0,
        console_jeux_type VARCHAR(100) DEFAULT NULL,
        livres TINYINT(1) DEFAULT 0,
        jeux_societe TINYINT(1) DEFAULT 0,
        canape TINYINT(1) DEFAULT 0,
        canape_type VARCHAR(100) DEFAULT NULL,
        canape_convertible TINYINT(1) DEFAULT 0,
        table_manger TINYINT(1) DEFAULT 0,
        table_manger_places INT DEFAULT NULL,
        bureau TINYINT(1) DEFAULT 0,
        type_lits TEXT DEFAULT NULL,
        linge_lit_fourni TINYINT(1) DEFAULT 1,
        serviettes_fournies TINYINT(1) DEFAULT 1,
        oreillers_supplementaires TINYINT(1) DEFAULT 0,
        couvertures_supplementaires TINYINT(1) DEFAULT 0,
        climatisation TINYINT(1) DEFAULT 0,
        chauffage TINYINT(1) DEFAULT 1,
        chauffage_type VARCHAR(50) DEFAULT 'electrique',
        ventilateur TINYINT(1) DEFAULT 0,
        baignoire TINYINT(1) DEFAULT 0,
        douche TINYINT(1) DEFAULT 1,
        seche_cheveux TINYINT(1) DEFAULT 0,
        produits_toilette TINYINT(1) DEFAULT 0,
        balcon TINYINT(1) DEFAULT 0,
        terrasse TINYINT(1) DEFAULT 0,
        jardin TINYINT(1) DEFAULT 0,
        parking TINYINT(1) DEFAULT 0,
        parking_type VARCHAR(50) DEFAULT NULL,
        barbecue TINYINT(1) DEFAULT 0,
        salon_jardin TINYINT(1) DEFAULT 0,
        detecteur_fumee TINYINT(1) DEFAULT 1,
        detecteur_co TINYINT(1) DEFAULT 0,
        extincteur TINYINT(1) DEFAULT 0,
        trousse_secours TINYINT(1) DEFAULT 0,
        coffre_fort TINYINT(1) DEFAULT 0,
        lit_bebe TINYINT(1) DEFAULT 0,
        chaise_haute TINYINT(1) DEFAULT 0,
        barriere_securite TINYINT(1) DEFAULT 0,
        jeux_enfants TINYINT(1) DEFAULT 0,
        animaux_acceptes TINYINT(1) DEFAULT 0,
        animaux_conditions TEXT DEFAULT NULL,
        guide_tv TEXT DEFAULT NULL,
        guide_canape_convertible TEXT DEFAULT NULL,
        guide_plaque_cuisson TEXT DEFAULT NULL,
        guide_four TEXT DEFAULT NULL,
        guide_micro_ondes TEXT DEFAULT NULL,
        guide_chauffage TEXT DEFAULT NULL,
        guide_climatisation TEXT DEFAULT NULL,
        guide_machine_cafe TEXT DEFAULT NULL,
        guide_machine_laver TEXT DEFAULT NULL,
        guide_lave_vaisselle TEXT DEFAULT NULL,
        guide_seche_linge TEXT DEFAULT NULL,
        fumer_autorise TINYINT(1) DEFAULT 0,
        fetes_autorisees TINYINT(1) DEFAULT 0,
        heure_checkin VARCHAR(50) DEFAULT '15:00',
        heure_checkout VARCHAR(50) DEFAULT '11:00',
        instructions_depart TEXT DEFAULT NULL,
        infos_quartier TEXT DEFAULT NULL,
        numeros_urgence TEXT DEFAULT NULL,
        notes_supplementaires TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis proprietaires.php ---
    "CREATE TABLE IF NOT EXISTS FC_proprietaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        telephone VARCHAR(20) DEFAULT NULL,
        adresse TEXT DEFAULT NULL,
        societe VARCHAR(255) DEFAULT NULL,
        siret VARCHAR(20) DEFAULT NULL,
        rib_iban VARCHAR(40) DEFAULT NULL,
        rib_bic VARCHAR(15) DEFAULT NULL,
        rib_banque VARCHAR(100) DEFAULT NULL,
        commission DECIMAL(5,2) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        actif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis prospection_proprietaires.php ---
    "CREATE TABLE IF NOT EXISTS prospection_proprietaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        telephone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        ville VARCHAR(100) DEFAULT NULL,
        nb_annonces INT DEFAULT 0,
        note_moyenne DECIMAL(3,2) DEFAULT NULL,
        host_profile_id VARCHAR(50) DEFAULT NULL,
        source ENUM('concurrence', 'recommandation', 'demarchage', 'entrant') DEFAULT 'concurrence',
        statut ENUM('identifie', 'contacte', 'en_discussion', 'proposition', 'converti', 'perdu') DEFAULT 'identifie',
        priorite ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
        notes TEXT DEFAULT NULL,
        prochaine_action VARCHAR(255) DEFAULT NULL,
        date_prochaine_action DATE DEFAULT NULL,
        date_premier_contact DATE DEFAULT NULL,
        date_conversion DATE DEFAULT NULL,
        proprietaire_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_host (host_profile_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis prospection_proprietaires.php ---
    "CREATE TABLE IF NOT EXISTS prospection_historique (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospect_id INT NOT NULL,
        type ENUM('appel', 'email', 'sms', 'rdv', 'visite', 'note') DEFAULT 'note',
        contenu TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (prospect_id) REFERENCES prospection_proprietaires(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis rental_united.php ---
    "CREATE TABLE IF NOT EXISTS rental_united_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ru_username VARCHAR(255) DEFAULT NULL,
        ru_password_encrypted TEXT DEFAULT NULL,
        api_url VARCHAR(255) DEFAULT 'https://rm.rentalsunited.com/api/Handler.ashx',
        actif TINYINT(1) DEFAULT 0,
        last_sync TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis rental_united.php ---
    "CREATE TABLE IF NOT EXISTS rental_united_properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        ru_property_id VARCHAR(50) DEFAULT NULL,
        ru_property_name VARCHAR(255) DEFAULT NULL,
        sync_prix TINYINT(1) DEFAULT 1,
        sync_disponibilite TINYINT(1) DEFAULT 1,
        sync_reservations TINYINT(1) DEFAULT 1,
        statut ENUM('non_configure', 'configure', 'actif', 'erreur', 'pause') DEFAULT 'non_configure',
        derniere_sync TIMESTAMP NULL,
        derniere_erreur TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_logement (logement_id),
        INDEX idx_ru_property (ru_property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis rental_united.php ---
    "CREATE TABLE IF NOT EXISTS rental_united_channels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        actif TINYINT(1) DEFAULT 0,
        logo_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis rental_united.php ---
    "CREATE TABLE IF NOT EXISTS rental_united_sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT DEFAULT NULL,
        type ENUM('prix', 'disponibilite', 'reservation', 'property', 'config') NOT NULL,
        direction ENUM('push', 'pull') NOT NULL,
        statut ENUM('succes', 'erreur', 'partiel') NOT NULL,
        message TEXT,
        details JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis sites.php ---
    "CREATE TABLE IF NOT EXISTS frenchysite_instances (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        logement_id   INT NOT NULL,
        db_prefix     VARCHAR(10) NOT NULL UNIQUE,
        site_slug     VARCHAR(100) NOT NULL UNIQUE,
        site_name     VARCHAR(255) NOT NULL,
        site_url      VARCHAR(500) DEFAULT '',
        deploy_path   VARCHAR(500) DEFAULT '',
        admin_user    VARCHAR(100) DEFAULT 'admin',
        admin_pass_hash VARCHAR(255) DEFAULT '',
        actif         TINYINT(1) NOT NULL DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis villes.php, logement_equipements.php ---
    "CREATE TABLE IF NOT EXISTS villes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis villes.php, logement_equipements.php ---
    "CREATE TABLE IF NOT EXISTS ville_recommandations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ville_id INT NOT NULL,
        categorie ENUM('partenaire', 'restaurant', 'activite') NOT NULL,
        nom VARCHAR(200) NOT NULL,
        description TEXT,
        adresse VARCHAR(255),
        telephone VARCHAR(50),
        site_web VARCHAR(255),
        prix_indicatif VARCHAR(100),
        note_interne TEXT,
        ordre INT DEFAULT 0,
        actif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ville_categorie (ville_id, categorie),
        FOREIGN KEY (ville_id) REFERENCES villes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// ============================================================================
// TABLES SUR LA BASE RPI (via getRpiPdo())
// ============================================================================

$tables_rpi = [

    // --- Depuis agent_dashboard.php ---
    "CREATE TABLE IF NOT EXISTS agent_action_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action_type VARCHAR(100) NOT NULL UNIQUE,
        rate_eur DECIMAL(8,2) NOT NULL DEFAULT 0.00
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis agent_dashboard.php ---
    "CREATE TABLE IF NOT EXISTS agent_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_user_id INT NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        channel VARCHAR(50) DEFAULT 'autre',
        reservation_ref VARCHAR(100) DEFAULT NULL,
        logement VARCHAR(255) DEFAULT NULL,
        client_name VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis automations.php, custom_automations.php ---
    "CREATE TABLE IF NOT EXISTS `sms_automations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nom` varchar(100) NOT NULL,
      `description` text,
      `actif` tinyint(1) DEFAULT 1,
      `declencheur_type` enum('date_arrivee','date_depart','date_reservation') NOT NULL,
      `declencheur_jours` int(11) DEFAULT 0,
      `template_name` varchar(50) NOT NULL,
      `condition_statut` varchar(50) DEFAULT 'confirmee',
      `flag_field` varchar(50) DEFAULT NULL,
      `logement_id` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_logement` (`logement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis campaigns.php ---
    "CREATE TABLE IF NOT EXISTS sms_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        logement_id INT DEFAULT NULL,
        message_template TEXT NOT NULL,
        date_debut DATE DEFAULT NULL,
        date_fin DATE DEFAULT NULL,
        statut ENUM('brouillon', 'planifiee', 'envoyee', 'annulee') DEFAULT 'brouillon',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        total_recipients INT DEFAULT 0,
        total_sent INT DEFAULT 0,
        FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE SET NULL
    )",

    // --- Depuis campaigns.php ---
    "CREATE TABLE IF NOT EXISTS sms_campaign_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        reservation_id INT NOT NULL,
        telephone VARCHAR(20) NOT NULL,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        statut ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente',
        sent_at TIMESTAMP NULL,
        error_message TEXT,
        FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
    )",

    // --- Depuis clients.php ---
    "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        telephone VARCHAR(20) NOT NULL UNIQUE,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        email VARCHAR(255),
        adresse TEXT,
        notes TEXT,
        tags VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis diagnostic_templates.php ---
    "CREATE TABLE IF NOT EXISTS sms_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        template TEXT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // --- Depuis templates.php ---
    "CREATE TABLE IF NOT EXISTS sms_logement_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        type_message VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        actif BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE CASCADE,
        UNIQUE KEY unique_logement_type (logement_id, type_message)
    )",

    // --- Depuis relances_voyageurs.php ---
    "CREATE TABLE IF NOT EXISTS relance_segments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        description TEXT,
        criteres JSON,
        nb_contacts INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis relances_voyageurs.php ---
    "CREATE TABLE IF NOT EXISTS relance_campagnes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        segment_id INT DEFAULT NULL,
        type ENUM('sms', 'email') DEFAULT 'sms',
        message_template TEXT NOT NULL,
        statut ENUM('brouillon', 'planifiee', 'envoyee', 'annulee') DEFAULT 'brouillon',
        date_envoi_prevue DATETIME DEFAULT NULL,
        total_destinataires INT DEFAULT 0,
        total_envoyes INT DEFAULT 0,
        total_echecs INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        FOREIGN KEY (segment_id) REFERENCES relance_segments(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis relances_voyageurs.php ---
    "CREATE TABLE IF NOT EXISTS relance_envois (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campagne_id INT NOT NULL,
        reservation_id INT DEFAULT NULL,
        telephone VARCHAR(20) NOT NULL,
        prenom VARCHAR(100),
        nom VARCHAR(100),
        message_envoye TEXT,
        statut ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente',
        sent_at TIMESTAMP NULL,
        error_message TEXT,
        FOREIGN KEY (campagne_id) REFERENCES relance_campagnes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // --- Depuis superhote.php ---
    "CREATE TABLE IF NOT EXISTS `superhote_config` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `logement_id` INT(11) NOT NULL,
        `superhote_property_id` VARCHAR(100) DEFAULT NULL,
        `superhote_property_name` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum (jour 0)',
        `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal (J+14)',
        `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend en %',
        `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche en euros',
        `groupe` VARCHAR(100) DEFAULT NULL,
        `nuits_minimum` INT(11) DEFAULT 1 COMMENT 'Nombre minimum de nuits',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_logement` (`logement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis superhote.php ---
    "CREATE TABLE IF NOT EXISTS `superhote_groups` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `nom` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `logement_reference_id` INT(11) DEFAULT NULL COMMENT 'Logement fictif de reference',
        `prix_plancher` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix minimum par defaut (J0)',
        `prix_standard` DECIMAL(10,2) DEFAULT NULL COMMENT 'Prix normal par defaut (J14+)',
        `weekend_pourcent` DECIMAL(5,2) DEFAULT 10 COMMENT 'Majoration weekend par defaut en %',
        `dimanche_reduction` DECIMAL(10,2) DEFAULT 5 COMMENT 'Reduction dimanche par defaut en euros',
        `nuits_minimum` INT(11) DEFAULT 1 COMMENT 'Nombre minimum de nuits par defaut',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_nom` (`nom`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // --- Depuis superhote.php ---
    "CREATE TABLE IF NOT EXISTS `superhote_settings` (
        `key_name` VARCHAR(50) NOT NULL,
        `value` VARCHAR(255) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`key_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// ============================================================================
// EXECUTION
// ============================================================================

echo "=== Installation des tables IONOS ($conn) ===\n\n";

$ok = 0;
$fail = 0;
foreach ($tables_ionos as $sql) {
    try {
        $conn->exec($sql);
        $ok++;
        preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\S+?)`?\s*\(/i', $sql, $m);
        $name = $m[1] ?? '?';
        echo "OK: $name\n";
    } catch (PDOException $e) {
        $fail++;
        preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\S+?)`?\s*\(/i', $sql, $m);
        $name = $m[1] ?? '?';
        error_log("install_tables.php IONOS ($name): " . $e->getMessage());
        echo "ERREUR ($name): " . $e->getMessage() . "\n";
    }
}

echo "\nIONOS: {$ok} tables OK, {$fail} erreurs.\n";

// RPI tables
$pdo_rpi = null;
try {
    $pdo_rpi = getRpiPdo();
} catch (Exception $e) {
    echo "\nIMPOSSIBLE de se connecter a la base RPI: " . $e->getMessage() . "\n";
    echo "Les tables RPI ne seront pas creees.\n";
}

if ($pdo_rpi) {
    echo "\n=== Installation des tables RPI ===\n\n";

    $ok_rpi = 0;
    $fail_rpi = 0;
    foreach ($tables_rpi as $sql) {
        try {
            $pdo_rpi->exec($sql);
            $ok_rpi++;
            preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\S+?)`?\s*\(/i', $sql, $m);
            $name = $m[1] ?? '?';
            echo "OK: $name\n";
        } catch (PDOException $e) {
            $fail_rpi++;
            preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\S+?)`?\s*\(/i', $sql, $m);
            $name = $m[1] ?? '?';
            error_log("install_tables.php RPI ($name): " . $e->getMessage());
            echo "ERREUR ($name): " . $e->getMessage() . "\n";
        }
    }

    echo "\nRPI: {$ok_rpi} tables OK, {$fail_rpi} erreurs.\n";
}

echo "\nTermine.\n";
