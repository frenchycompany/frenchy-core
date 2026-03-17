<?php
/**
 * Fonctions utilitaires pour le tunnel d'onboarding
 * FrenchyConciergerie — Self Boarding
 */

// ─────────────────────────────────────────────────────────────────
// Auto-migration des tables onboarding
// ─────────────────────────────────────────────────────────────────
function onboarding_ensure_tables($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $conn->query("SELECT 1 FROM onboarding_requests LIMIT 1");
    } catch (PDOException $e) {
        // Tables manquantes — les creer inline (plus fiable que parser le .sql)
        onboarding_create_tables($conn);
    }
}

function onboarding_create_tables($conn) {
    $tables = [];

    $tables[] = "CREATE TABLE IF NOT EXISTS onboarding_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        etape_courante TINYINT DEFAULT 1,
        statut ENUM('brouillon','en_cours','termine','abandonne') DEFAULT 'brouillon',
        progression TINYINT DEFAULT 0,
        adresse TEXT,
        complement_adresse VARCHAR(255),
        code_postal VARCHAR(10),
        ville VARCHAR(100),
        pays VARCHAR(50) DEFAULT 'France',
        latitude DECIMAL(10,7),
        longitude DECIMAL(10,7),
        typologie ENUM('studio','T1','T2','T3','T4','T5+','maison','villa') DEFAULT NULL,
        superficie INT,
        nb_pieces INT DEFAULT 1,
        nb_couchages INT DEFAULT 2,
        etage VARCHAR(20),
        ascenseur TINYINT(1) DEFAULT 0,
        parking TINYINT(1) DEFAULT 0,
        photos JSON,
        annonce_existante TINYINT(1) DEFAULT 0,
        annonce_plateformes JSON,
        annonce_url_airbnb VARCHAR(500),
        annonce_url_booking VARCHAR(500),
        annonce_url_autre VARCHAR(500),
        experience_location VARCHAR(50),
        prenom VARCHAR(100),
        nom VARCHAR(100),
        email VARCHAR(255),
        telephone VARCHAR(20),
        societe VARCHAR(255),
        siret VARCHAR(20),
        equipements JSON,
        description_bien TEXT,
        pack ENUM('autonome','serenite','cle_en_main') DEFAULT 'autonome',
        commission_base DECIMAL(5,2) DEFAULT 10.00,
        options_supplementaires JSON,
        prix_souhaite DECIMAL(10,2),
        prix_min DECIMAL(10,2),
        prix_max DECIMAL(10,2),
        accepte_prix_dynamique TINYINT(1) DEFAULT 1,
        conditions_acceptees TINYINT(1) DEFAULT 0,
        rgpd_accepte TINYINT(1) DEFAULT 0,
        signature_date DATETIME,
        proprietaire_id INT,
        logement_id INT,
        code_parrain VARCHAR(30),
        source VARCHAR(50),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at DATETIME,
        INDEX idx_email (email),
        INDEX idx_statut (statut),
        INDEX idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables[] = "CREATE TABLE IF NOT EXISTS proprietaire_commission_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proprietaire_id INT NOT NULL,
        pack ENUM('autonome','serenite','cle_en_main') DEFAULT 'autonome',
        commission_base DECIMAL(5,2) DEFAULT 10.00,
        reduction_parrainage DECIMAL(5,2) DEFAULT 0.00,
        commission_effective DECIMAL(5,2) DEFAULT 10.00,
        options JSON DEFAULT NULL,
        services_inclus JSON,
        equipement_fourni TINYINT(1) DEFAULT 0,
        budget_equipement DECIMAL(10,2) DEFAULT 0,
        notes_admin TEXT,
        modifie_par INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_proprietaire (proprietaire_id),
        INDEX idx_pack (pack)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables[] = "CREATE TABLE IF NOT EXISTS codes_parrainage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proprietaire_id INT NOT NULL,
        code VARCHAR(30) NOT NULL UNIQUE,
        nb_utilisations INT DEFAULT 0,
        max_utilisations INT DEFAULT NULL,
        actif TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_proprio (proprietaire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables[] = "CREATE TABLE IF NOT EXISTS parrainages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parrain_id INT NOT NULL,
        filleul_id INT NOT NULL,
        code_utilise VARCHAR(30),
        reduction_parrain DECIMAL(5,2) DEFAULT 1.00,
        avantage_filleul VARCHAR(100) DEFAULT 'photos_pro_offertes',
        statut ENUM('en_attente','actif','expire','annule') DEFAULT 'en_attente',
        active_depuis DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_parrain (parrain_id),
        INDEX idx_filleul (filleul_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables[] = "CREATE TABLE IF NOT EXISTS onboarding_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        statut ENUM('pending','processing','done','error','skipped') DEFAULT 'pending',
        result JSON,
        error_message TEXT,
        retry_count TINYINT DEFAULT 0,
        executed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request (request_id),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables[] = "CREATE TABLE IF NOT EXISTS frenchy_score (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        score_global TINYINT DEFAULT 50,
        score_annonce TINYINT DEFAULT 50,
        score_reactivite TINYINT DEFAULT 50,
        score_satisfaction TINYINT DEFAULT 50,
        score_prix TINYINT DEFAULT 50,
        score_entretien TINYINT DEFAULT 50,
        badge VARCHAR(50),
        conseil_ia TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach ($tables as $sql) {
        try {
            $conn->exec($sql);
        } catch (PDOException $ex) {
            error_log('onboarding table creation: ' . $ex->getMessage());
        }
    }
}

// ─────────────────────────────────────────────────────────────────
// Token management
// ─────────────────────────────────────────────────────────────────
function onboarding_generate_token() {
    return bin2hex(random_bytes(32));
}

function onboarding_get_or_create($conn, $token = null) {
    onboarding_ensure_tables($conn);

    if ($token) {
        $stmt = $conn->prepare("SELECT * FROM onboarding_requests WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    // Creer une nouvelle demande
    $newToken = onboarding_generate_token();
    $stmt = $conn->prepare("
        INSERT INTO onboarding_requests (token, statut, etape_courante, progression, ip_address, user_agent, source)
        VALUES (?, 'brouillon', 1, 0, ?, ?, ?)
    ");
    $stmt->execute([
        $newToken,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_GET['source'] ?? 'direct'
    ]);

    return [
        'id' => $conn->lastInsertId(),
        'token' => $newToken,
        'etape_courante' => 1,
        'statut' => 'brouillon',
        'progression' => 0,
    ];
}

function onboarding_load($conn, $token) {
    onboarding_ensure_tables($conn);
    $stmt = $conn->prepare("SELECT * FROM onboarding_requests WHERE token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function onboarding_save_step($conn, $token, $etape, $data) {
    onboarding_ensure_tables($conn);

    $allowed_fields = [
        // Etape 1
        'adresse', 'complement_adresse', 'code_postal', 'ville', 'pays',
        'latitude', 'longitude', 'typologie', 'superficie', 'nb_pieces',
        'nb_couchages', 'etage', 'ascenseur', 'parking', 'photos',
        'annonce_existante', 'annonce_plateformes', 'annonce_url_airbnb',
        'annonce_url_booking', 'annonce_url_autre', 'experience_location',
        // Etape 2
        'prenom', 'nom', 'email', 'telephone', 'societe', 'siret',
        // Etape 3
        'equipements', 'description_bien',
        // Etape 4
        'pack', 'commission_base', 'options_supplementaires',
        // Etape 5
        'prix_souhaite', 'prix_min', 'prix_max', 'accepte_prix_dynamique',
        // Etape 6
        'conditions_acceptees', 'rgpd_accepte', 'code_parrain',
    ];

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        if (!in_array($key, $allowed_fields)) continue;
        // JSON fields
        if (in_array($key, ['photos', 'equipements', 'options_supplementaires', 'annonce_plateformes']) && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $sets[] = "`$key` = ?";
        $params[] = $value;
    }

    if (empty($sets)) return false;

    // Calculer progression
    $progression = onboarding_calc_progression($etape, $data);

    $sets[] = "etape_courante = ?";
    $params[] = $etape;
    $sets[] = "progression = ?";
    $params[] = $progression;
    $sets[] = "statut = 'en_cours'";
    $sets[] = "updated_at = NOW()";
    $params[] = $token;

    $sql = "UPDATE onboarding_requests SET " . implode(', ', $sets) . " WHERE token = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}

function onboarding_calc_progression($etape, $data) {
    $steps = [1 => 15, 2 => 30, 3 => 50, 4 => 65, 5 => 80, 6 => 100];
    return $steps[$etape] ?? 0;
}

// ─────────────────────────────────────────────────────────────────
// Packs & Commission
// ─────────────────────────────────────────────────────────────────
function onboarding_get_packs() {
    return [
        'autonome' => [
            'label' => 'Autonome',
            'slogan' => 'Vous gardez le controle, on automatise',
            'commission' => 10,
            'commission_min' => 5,
            'icon' => 'fa-user-check',
            'color' => '#28a745',
            'services' => [
                'Synchronisation calendriers (Airbnb, Booking)',
                'Messages automatiques IA',
                'Site vitrine personnalise',
                'Tableau de bord & statistiques',
                'Relances SMS pour avis',
                'Optimisation prix dynamique',
            ],
            'vous_gerez' => [
                'Menage entre locataires',
                'Check-in / check-out',
                'Petites reparations',
                'Achat equipement',
            ],
        ],
        'serenite' => [
            'label' => 'Serenite',
            'slogan' => 'On gere tout, vous encaissez',
            'commission' => 20,
            'commission_min' => 15,
            'icon' => 'fa-hands-helping',
            'color' => '#007bff',
            'popular' => true,
            'services' => [
                'Tout le pack Autonome',
                'Menage professionnel inclus',
                'Check-in / check-out gere',
                'Maintenance & depannage',
                'Gestion des conflits voyageurs',
                'Etude concurrence mensuelle',
                'Seance photo pro offerte',
            ],
            'vous_gerez' => [
                'Achat gros equipement',
                'Gros travaux',
            ],
        ],
        'cle_en_main' => [
            'label' => 'Cle en main',
            'slogan' => 'Arrivez les mains dans les poches',
            'commission' => 30,
            'commission_min' => 25,
            'icon' => 'fa-gem',
            'color' => '#6f42c1',
            'services' => [
                'Tout le pack Serenite',
                'EQUIPEMENT FOURNI PAR FRENCHY',
                'Mobilier, vaisselle, draps, deco',
                'Renouvellement automatique du materiel use',
                'Upgrade saisonnier (deco Noel, ete...)',
                'Assurance tout risque incluse',
                'Garantie occupation min 70%',
                'Conciergerie 24/7',
            ],
            'vous_gerez' => [
                'Rien. Juste encaisser.',
            ],
        ],
    ];
}

function onboarding_get_equipements_checklist() {
    return [
        'confort' => [
            'label' => 'Confort',
            'icon' => 'fa-couch',
            'items' => [
                'wifi' => 'Wi-Fi haut debit',
                'climatisation' => 'Climatisation',
                'chauffage' => 'Chauffage',
                'television' => 'Television',
                'lave_linge' => 'Lave-linge',
                'seche_linge' => 'Seche-linge',
                'lave_vaisselle' => 'Lave-vaisselle',
                'fer_a_repasser' => 'Fer a repasser',
            ],
        ],
        'cuisine' => [
            'label' => 'Cuisine',
            'icon' => 'fa-utensils',
            'items' => [
                'cuisine_equipee' => 'Cuisine equipee',
                'four' => 'Four',
                'micro_ondes' => 'Micro-ondes',
                'cafetiere' => 'Cafetiere / Nespresso',
                'bouilloire' => 'Bouilloire',
                'grille_pain' => 'Grille-pain',
            ],
        ],
        'exterieur' => [
            'label' => 'Exterieur',
            'icon' => 'fa-tree',
            'items' => [
                'balcon' => 'Balcon / Terrasse',
                'jardin' => 'Jardin',
                'piscine' => 'Piscine',
                'barbecue' => 'Barbecue',
                'parking' => 'Parking prive',
                'garage' => 'Garage',
            ],
        ],
        'securite' => [
            'label' => 'Securite & Acces',
            'icon' => 'fa-shield-alt',
            'items' => [
                'digicode' => 'Digicode / Serrure connectee',
                'interphone' => 'Interphone',
                'coffre_fort' => 'Coffre-fort',
                'detecteur_fumee' => 'Detecteur de fumee',
                'extincteur' => 'Extincteur',
            ],
        ],
        'linge' => [
            'label' => 'Linge & Literie',
            'icon' => 'fa-bed',
            'items' => [
                'draps_fournis' => 'Draps fournis',
                'serviettes' => 'Serviettes de bain',
                'oreillers_sup' => 'Oreillers supplementaires',
                'couette_ete' => 'Couette ete',
            ],
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────
// Estimation revenus
// ─────────────────────────────────────────────────────────────────
function onboarding_estimate_revenue($prix_nuit, $commission_pct, $occupation_pct = 70) {
    $jours_mois = 30;
    $jours_occupes = round($jours_mois * $occupation_pct / 100);
    $revenu_brut = $prix_nuit * $jours_occupes;
    $commission = $revenu_brut * $commission_pct / 100;
    $revenu_net = $revenu_brut - $commission;

    return [
        'jours_occupes' => $jours_occupes,
        'revenu_brut' => round($revenu_brut, 2),
        'commission' => round($commission, 2),
        'revenu_net' => round($revenu_net, 2),
        'revenu_annuel' => round($revenu_net * 12, 2),
    ];
}

// ─────────────────────────────────────────────────────────────────
// Finalisation onboarding → creation proprio + logement
// ─────────────────────────────────────────────────────────────────
function onboarding_finalize($conn, $token) {
    $request = onboarding_load($conn, $token);
    if (!$request || $request['statut'] === 'termine') return false;

    // S'assurer que les colonnes existent dans FC_proprietaires
    try {
        $cols = array_column($conn->query("SHOW COLUMNS FROM FC_proprietaires")->fetchAll(), 'Field');
    } catch (PDOException $e) {
        error_log('onboarding_finalize: FC_proprietaires missing: ' . $e->getMessage());
        throw new Exception('Table FC_proprietaires introuvable');
    }

    // 1. Creer le proprietaire — adapter les colonnes disponibles
    // Generer un mot de passe temporaire (le proprio le changera a la premiere connexion)
    $tempPassword = bin2hex(random_bytes(8));
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $insertCols = ['nom', 'prenom', 'email', 'telephone', 'actif'];
    $insertVals = [
        $request['nom'],
        $request['prenom'],
        $request['email'],
        $request['telephone'],
        1,
    ];
    // password_hash obligatoire
    if (in_array('password_hash', $cols)) {
        $insertCols[] = 'password_hash';
        $insertVals[] = $passwordHash;
    }
    // Colonnes optionnelles
    foreach (['societe', 'siret', 'commission'] as $optCol) {
        if (in_array($optCol, $cols)) {
            $insertCols[] = $optCol;
            $insertVals[] = $optCol === 'commission' ? $request['commission_base'] : $request[$optCol];
        }
    }
    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colsList = implode(',', $insertCols);
    $stmt = $conn->prepare("INSERT INTO FC_proprietaires ($colsList) VALUES ($placeholders)");
    $stmt->execute($insertVals);
    $proprietaire_id = $conn->lastInsertId();
    error_log("onboarding: proprietaire cree ID=$proprietaire_id");

    // 2. Creer le logement — verifier que proprietaire_id existe
    $nom_logement = ($request['typologie'] ?? 'Bien') . ' ' . ($request['ville'] ?? '');
    $adresse_complete = implode(', ', array_filter([
        $request['adresse'],
        $request['complement_adresse'],
        $request['code_postal'],
        $request['ville'],
    ]));

    $logCols = array_column($conn->query("SHOW COLUMNS FROM liste_logements")->fetchAll(), 'Field');
    if (in_array('proprietaire_id', $logCols)) {
        $stmt = $conn->prepare("INSERT INTO liste_logements (nom_du_logement, adresse, proprietaire_id) VALUES (?, ?, ?)");
        $stmt->execute([$nom_logement, $adresse_complete, $proprietaire_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO liste_logements (nom_du_logement, adresse) VALUES (?, ?)");
        $stmt->execute([$nom_logement, $adresse_complete]);
    }
    $logement_id = $conn->lastInsertId();
    error_log("onboarding: logement cree ID=$logement_id");

    // 3. Creer la config commission
    try {
        $stmt = $conn->prepare("
            INSERT INTO proprietaire_commission_config
            (proprietaire_id, pack, commission_base, commission_effective, options, equipement_fourni)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $commBase = $request['commission_base'] ?? 10;
        $stmt->execute([
            $proprietaire_id,
            $request['pack'] ?? 'autonome',
            $commBase,
            $commBase, // effective = base au debut
            $request['options_supplementaires'] ?? null,
            ($request['pack'] === 'cle_en_main') ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        error_log('onboarding commission config: ' . $e->getMessage());
        // Non bloquant
    }

    // 4. Gerer le parrainage (non bloquant)
    if (!empty($request['code_parrain'])) {
        try {
            $stmtP = $conn->prepare("SELECT proprietaire_id FROM codes_parrainage WHERE code = ? AND actif = 1");
            $stmtP->execute([$request['code_parrain']]);
            $parrain = $stmtP->fetch(PDO::FETCH_ASSOC);
            if ($parrain) {
                $conn->prepare("
                    INSERT INTO parrainages (parrain_id, filleul_id, code_utilise, statut, active_depuis)
                    VALUES (?, ?, ?, 'actif', NOW())
                ")->execute([$parrain['proprietaire_id'], $proprietaire_id, $request['code_parrain']]);

                $conn->prepare("UPDATE codes_parrainage SET nb_utilisations = nb_utilisations + 1 WHERE code = ?")
                    ->execute([$request['code_parrain']]);

                $stmtNb = $conn->prepare("SELECT COUNT(*) FROM parrainages WHERE parrain_id = ? AND statut = 'actif'");
                $stmtNb->execute([$parrain['proprietaire_id']]);
                $reduction = min((int)$stmtNb->fetchColumn() * 1.0, 5.0);
                $conn->prepare("UPDATE proprietaire_commission_config SET reduction_parrainage = ?, commission_effective = GREATEST(5, commission_base - ?) WHERE proprietaire_id = ?")
                    ->execute([$reduction, $reduction, $parrain['proprietaire_id']]);
            }
        } catch (PDOException $e) {
            error_log('onboarding parrainage: ' . $e->getMessage());
        }
    }

    // 5. Generer le code parrainage du nouveau proprio (non bloquant)
    $code = 'FRENCHY' . strtoupper(substr($request['prenom'] ?? 'X', 0, 4)) . $proprietaire_id;
    try {
        $conn->prepare("INSERT INTO codes_parrainage (proprietaire_id, code) VALUES (?, ?)")
            ->execute([$proprietaire_id, $code]);
    } catch (PDOException $e) {
        error_log('onboarding code parrainage: ' . $e->getMessage());
    }

    // 6. Creer les taches automatiques (non bloquant)
    try {
        $stmtTask = $conn->prepare("INSERT INTO onboarding_tasks (request_id, type, statut) VALUES (?, ?, ?)");
        $stmtTask->execute([$request['id'], 'create_proprietaire', 'done']);
        $stmtTask->execute([$request['id'], 'create_logement', 'done']);
        $stmtTask->execute([$request['id'], 'generate_site', 'pending']);
        $stmtTask->execute([$request['id'], 'sms_bienvenue', 'pending']);
        $stmtTask->execute([$request['id'], 'setup_superhote', 'pending']);
    } catch (PDOException $e) {
        error_log('onboarding tasks: ' . $e->getMessage());
    }

    // 7. Marquer comme termine
    $conn->prepare("
        UPDATE onboarding_requests
        SET statut = 'termine', progression = 100, proprietaire_id = ?, logement_id = ?, completed_at = NOW()
        WHERE token = ?
    ")->execute([$proprietaire_id, $logement_id, $token]);

    error_log("onboarding: finalise OK — proprio=$proprietaire_id logement=$logement_id");

    // 8. Notifier l'admin (email + base) — non bloquant
    try {
        require_once __DIR__ . '/../../includes/notifications.php';
        $nomComplet = trim(($request['prenom'] ?? '') . ' ' . ($request['nom'] ?? ''));
        $logementNom = $nom_logement;
        $packLabel = onboarding_get_packs()[$request['pack'] ?? 'autonome']['label'] ?? 'Autonome';
        $commBase = $request['commission_base'] ?? 10;
        $lien = "https://gestion.frenchyconciergerie.fr/pages/proprietaire_detail.php?id=$proprietaire_id";

        $adminMessage = "L'onboarding de <strong>" . htmlspecialchars($nomComplet) . "</strong> est termine.<br><br>"
            . "<table style='border-collapse:collapse;width:100%;'>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Pack</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;font-weight:700;'>" . htmlspecialchars($packLabel) . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Commission</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;font-weight:700;'>" . htmlspecialchars($commBase) . "%</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Logement</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($logementNom) . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Adresse</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($adresse_complete) . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Typologie</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['typologie'] ?? '-') . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Email</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['email'] ?? '') . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Telephone</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['telephone'] ?? '') . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Societe</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['societe'] ?? '-') . "</td></tr>"
            . "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Options</td>"
            .     "<td style='padding:6px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['options_supplementaires'] ?? 'Aucune') . "</td></tr>";

        // Annonce existante
        $annonceExistante = (int)($request['annonce_existante'] ?? 0);
        $anncPlat = json_decode($request['annonce_plateformes'] ?? '[]', true) ?: [];
        $expLabels = ['jamais' => 'Jamais', 'moins_1an' => '< 1 an', '1_3ans' => '1-3 ans', '3_5ans' => '3-5 ans', 'plus_5ans' => '5+ ans'];

        $adminMessage .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Annonce existante</td>"
            . "<td style='padding:6px 12px;border-bottom:1px solid #eee;font-weight:700;color:" . ($annonceExistante ? '#28a745' : '#dc3545') . ";'>"
            . ($annonceExistante ? 'Oui — ' . htmlspecialchars(implode(', ', array_map('ucfirst', $anncPlat))) : 'Non (premier lancement)') . "</td></tr>";

        if (!empty($request['annonce_url_airbnb'])) {
            $adminMessage .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Airbnb</td>"
                . "<td style='padding:6px 12px;border-bottom:1px solid #eee;'><a href='" . htmlspecialchars($request['annonce_url_airbnb']) . "'>" . htmlspecialchars($request['annonce_url_airbnb']) . "</a></td></tr>";
        }
        if (!empty($request['annonce_url_booking'])) {
            $adminMessage .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Booking</td>"
                . "<td style='padding:6px 12px;border-bottom:1px solid #eee;'><a href='" . htmlspecialchars($request['annonce_url_booking']) . "'>" . htmlspecialchars($request['annonce_url_booking']) . "</a></td></tr>";
        }
        if (!empty($request['annonce_url_autre'])) {
            $adminMessage .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;color:#888;'>Autre lien</td>"
                . "<td style='padding:6px 12px;border-bottom:1px solid #eee;'><a href='" . htmlspecialchars($request['annonce_url_autre']) . "'>" . htmlspecialchars($request['annonce_url_autre']) . "</a></td></tr>";
        }
        $adminMessage .= "<tr><td style='padding:6px 12px;color:#888;'>Experience</td>"
            . "<td style='padding:6px 12px;'>" . htmlspecialchars($expLabels[$request['experience_location'] ?? ''] ?? 'Non renseignee') . "</td></tr>";

        $adminMessage .= "</table>";

        if (!empty($request['code_parrain'])) {
            $adminMessage .= "<br><span style='color:#28a745;font-weight:700;'>Parraine par : " . htmlspecialchars($request['code_parrain']) . "</span>";
        }

        sendNotification(
            $conn,
            'onboarding_termine',
            "Nouveau proprietaire : $nomComplet ($packLabel — {$commBase}%)",
            $adminMessage,
            $lien
        );
    } catch (\Throwable $e) {
        error_log('onboarding notification email: ' . $e->getMessage());
    }

    // 9. Email de bienvenue au client — non bloquant
    try {
        $clientEmail = $request['email'] ?? null;
        if ($clientEmail) {
            $prenom = htmlspecialchars($request['prenom'] ?? '');
            $nomComplet = htmlspecialchars(trim(($request['prenom'] ?? '') . ' ' . ($request['nom'] ?? '')));
            $packs = onboarding_get_packs();
            $packLabel = htmlspecialchars($packs[$request['pack'] ?? 'autonome']['label'] ?? 'Autonome');
            $loginUrl = 'https://gestion.frenchyconciergerie.fr/proprietaire/login.php';

            $subject = "Bienvenue chez Frenchy, $prenom !";
            $headers = "From: Frenchy Conciergerie <noreply@frenchyconciergerie.fr>\r\n";
            $headers .= "Reply-To: contact@frenchyconciergerie.fr\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $htmlBody = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:30px;border-radius:12px 12px 0 0;text-align:center;'>
                    <h1 style='margin:0 0 5px;font-size:24px;'>Bienvenue chez Frenchy !</h1>
                    <p style='margin:0;opacity:0.8;'>Votre inscription est confirmee</p>
                </div>
                <div style='background:#fff;padding:25px;border:1px solid #eee;'>
                    <p>Bonjour <strong>$prenom</strong>,</p>
                    <p>Merci pour votre confiance ! Votre bien est en cours d'activation avec le pack <strong>$packLabel</strong>.</p>

                    <div style='background:#f8f9fa;border-radius:8px;padding:15px;margin:20px 0;'>
                        <h3 style='margin:0 0 10px;font-size:16px;color:#1a1a2e;'>Vos identifiants temporaires</h3>
                        <p style='margin:5px 0;'>Email : <strong>" . htmlspecialchars($clientEmail) . "</strong></p>
                        <p style='margin:5px 0;'>Mot de passe : <strong>$tempPassword</strong></p>
                        <p style='margin:10px 0 0;font-size:12px;color:#666;'>Pensez a changer votre mot de passe a la premiere connexion.</p>
                    </div>

                    <div style='text-align:center;margin:25px 0;'>
                        <a href='$loginUrl' style='display:inline-block;padding:14px 30px;background:#28a745;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;font-size:16px;'>Acceder a mon dashboard</a>
                    </div>

                    <h3 style='font-size:16px;color:#1a1a2e;'>Prochaines etapes :</h3>
                    <ol style='color:#333;line-height:1.8;'>
                        <li>Creation de votre email @frenchyconciergerie.fr (sous 24h)</li>
                        <li>Generation de votre site vitrine personnalise</li>
                        <li>Prise de RDV pour la seance photo / optimisation</li>
                        <li>Activation de la tarification dynamique</li>
                    </ol>

                    <p style='color:#666;font-size:13px;margin-top:20px;'>
                        Une question ? Repondez directement a cet email ou appelez-nous.<br>
                        Code parrainage : <strong>$code</strong> — partagez-le pour reduire votre commission !
                    </p>
                </div>
                <div style='background:#f1f1f1;padding:15px;text-align:center;border-radius:0 0 12px 12px;font-size:12px;color:#999;'>
                    Frenchy Conciergerie — Gestion locative courte duree
                </div>
            </div>";

            @mail($clientEmail, $subject, $htmlBody, $headers);
            error_log("onboarding: email bienvenue envoye a $clientEmail");
        }
    } catch (\Throwable $e) {
        error_log('onboarding email client: ' . $e->getMessage());
    }

    // 10. SMS fallback vers le numero admin — non bloquant
    try {
        $adminPhone = function_exists('env') ? env('ADMIN_PHONE', null) : null;
        if ($adminPhone) {
            $nomComplet = trim(($request['prenom'] ?? '') . ' ' . ($request['nom'] ?? ''));
            $smsMsg = "Nouveau client onboarde : $nomComplet — " . ($request['telephone'] ?? '') . ". Voir le dashboard pour details.";
            $conn->prepare(
                "INSERT INTO sms_outbox (receiver, message, modem, status) VALUES (?, ?, 'modem1', 'pending')"
            )->execute([$adminPhone, $smsMsg]);
        }
    } catch (\Throwable $e) {
        error_log('onboarding notification sms: ' . $e->getMessage());
    }

    return [
        'proprietaire_id' => $proprietaire_id,
        'logement_id' => $logement_id,
        'code_parrainage' => $code,
    ];
}

// ─────────────────────────────────────────────────────────────────
// CSS/HTML helpers pour le wizard
// ─────────────────────────────────────────────────────────────────
function onboarding_header($etape, $titre, $request = []) {
    $steps = [
        1 => ['label' => 'Le bien', 'icon' => 'fa-home'],
        2 => ['label' => 'Profil', 'icon' => 'fa-user'],
        3 => ['label' => 'Equipements', 'icon' => 'fa-check-square'],
        4 => ['label' => 'Pack', 'icon' => 'fa-tags'],
        5 => ['label' => 'Tarifs', 'icon' => 'fa-euro-sign'],
        6 => ['label' => 'Validation', 'icon' => 'fa-signature'],
    ];
    $progression = $request['progression'] ?? onboarding_calc_progression($etape, []);
    $token = $request['token'] ?? '';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($titre) ?> — Frenchy Self Boarding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --frenchy-green: #28a745;
                --frenchy-blue: #007bff;
                --frenchy-purple: #6f42c1;
                --frenchy-dark: #1a1a2e;
            }
            body { background: #f8f9fa; font-family: 'Segoe UI', system-ui, sans-serif; }
            .onboarding-container { max-width: 800px; margin: 0 auto; padding: 20px; }

            /* Stepper */
            .stepper { display: flex; justify-content: space-between; margin-bottom: 30px; padding: 0; position: relative; }
            .stepper::before {
                content: '';
                position: absolute;
                top: 20px;
                left: 40px;
                right: 40px;
                height: 3px;
                background: #e9ecef;
                z-index: 0;
            }
            .step {
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
                z-index: 1;
                flex: 1;
            }
            .step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e9ecef;
                color: #6c757d;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.9rem;
                transition: all 0.3s;
                border: 3px solid transparent;
            }
            .step.active .step-circle {
                background: var(--frenchy-blue);
                color: white;
                border-color: rgba(0,123,255,0.3);
                box-shadow: 0 0 0 4px rgba(0,123,255,0.15);
            }
            .step.done .step-circle {
                background: var(--frenchy-green);
                color: white;
            }
            .step-label {
                font-size: 0.7rem;
                margin-top: 6px;
                color: #6c757d;
                font-weight: 500;
                text-align: center;
            }
            .step.active .step-label, .step.done .step-label { color: #343a40; font-weight: 600; }

            /* Progress bar */
            .progress-bar-wrapper { margin-bottom: 25px; }
            .progress { height: 6px; border-radius: 3px; }

            /* Card wizard */
            .wizard-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                padding: 30px;
                margin-bottom: 20px;
            }
            .wizard-card h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 5px; }
            .wizard-card .subtitle { color: #6c757d; font-size: 0.9rem; margin-bottom: 25px; }

            /* Navigation */
            .wizard-nav { display: flex; justify-content: space-between; margin-top: 25px; }
            .wizard-nav .btn { min-width: 140px; }

            /* Responsive */
            @media (max-width: 576px) {
                .step-label { font-size: 0.6rem; }
                .step-circle { width: 32px; height: 32px; font-size: 0.75rem; }
                .wizard-card { padding: 20px 15px; }
            }
        </style>
    </head>
    <body>
    <div class="onboarding-container">
        <!-- Logo -->
        <div class="text-center mb-4">
            <h1 style="font-weight: 800; color: var(--frenchy-dark);">
                <i class="fas fa-concierge-bell" style="color: var(--frenchy-green);"></i>
                Frenchy<span style="color: var(--frenchy-green);">Conciergerie</span>
            </h1>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <?php foreach ($steps as $num => $s): ?>
            <div class="step <?= $num < $etape ? 'done' : ($num === $etape ? 'active' : '') ?>">
                <div class="step-circle">
                    <?php if ($num < $etape): ?>
                        <i class="fas fa-check"></i>
                    <?php else: ?>
                        <i class="fas <?= $s['icon'] ?>"></i>
                    <?php endif; ?>
                </div>
                <span class="step-label"><?= $s['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Progress -->
        <div class="progress-bar-wrapper">
            <div class="progress">
                <div class="progress-bar bg-success" style="width: <?= $progression ?>%"></div>
            </div>
            <small class="text-muted"><?= $progression ?>% complete</small>
        </div>

        <!-- Hidden token -->
        <input type="hidden" id="onboarding-token" value="<?= htmlspecialchars($token) ?>">
    <?php
}

function onboarding_footer($etape, $token) {
    $prev = $etape > 1 ? $etape - 1 : null;
    $next = $etape < 6 ? $etape + 1 : null;
    ?>
        <!-- Navigation -->
        <div class="wizard-nav">
            <?php if ($prev): ?>
                <a href="etape-<?= $prev ?>-<?= onboarding_step_slug($prev) ?>.php?token=<?= urlencode($token) ?>"
                   class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Precedent
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-lg" id="nextBtn">
                <?php if ($next): ?>
                    Continuer <i class="fas fa-arrow-right"></i>
                <?php else: ?>
                    <i class="fas fa-check"></i> Valider mon inscription
                <?php endif; ?>
            </button>
        </div>

    </div><!-- /onboarding-container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const ONBOARDING_TOKEN = document.getElementById('onboarding-token')?.value || '';
    const API_BASE = 'api/onboarding-save.php';

    async function saveStep(etape, data) {
        const body = { token: ONBOARDING_TOKEN, etape: etape, ...data };
        const resp = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return await resp.json();
    }
    </script>
    </body>
    </html>
    <?php
}

function onboarding_step_slug($etape) {
    $slugs = [
        1 => 'bien', 2 => 'profil', 3 => 'equipements',
        4 => 'pack', 5 => 'tarifs', 6 => 'recap',
    ];
    return $slugs[$etape] ?? 'bien';
}
