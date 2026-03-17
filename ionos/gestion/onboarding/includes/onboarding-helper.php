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
        if (in_array($key, ['photos', 'equipements', 'options_supplementaires']) && is_array($value)) {
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

    // 1. Creer le proprietaire dans FC_proprietaires
    $stmt = $conn->prepare("
        INSERT INTO FC_proprietaires (nom, prenom, email, telephone, societe, siret, commission, actif)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $request['nom'],
        $request['prenom'],
        $request['email'],
        $request['telephone'],
        $request['societe'],
        $request['siret'],
        $request['commission_base'],
    ]);
    $proprietaire_id = $conn->lastInsertId();

    // 2. Creer le logement dans liste_logements
    $nom_logement = ($request['typologie'] ?? 'Bien') . ' ' . ($request['ville'] ?? '');
    $stmt = $conn->prepare("
        INSERT INTO liste_logements (nom_du_logement, adresse, proprietaire_id)
        VALUES (?, ?, ?)
    ");
    $adresse_complete = implode(', ', array_filter([
        $request['adresse'],
        $request['complement_adresse'],
        $request['code_postal'],
        $request['ville'],
    ]));
    $stmt->execute([$nom_logement, $adresse_complete, $proprietaire_id]);
    $logement_id = $conn->lastInsertId();

    // 3. Creer la config commission
    $stmt = $conn->prepare("
        INSERT INTO proprietaire_commission_config
        (proprietaire_id, pack, commission_base, options, equipement_fourni)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $proprietaire_id,
        $request['pack'] ?? 'autonome',
        $request['commission_base'] ?? 10,
        $request['options_supplementaires'],
        ($request['pack'] === 'cle_en_main') ? 1 : 0,
    ]);

    // 4. Gerer le parrainage
    if (!empty($request['code_parrain'])) {
        $stmtP = $conn->prepare("
            SELECT cp.proprietaire_id
            FROM codes_parrainage cp
            WHERE cp.code = ? AND cp.actif = 1
        ");
        $stmtP->execute([$request['code_parrain']]);
        $parrain = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($parrain) {
            // Creer le parrainage
            $conn->prepare("
                INSERT INTO parrainages (parrain_id, filleul_id, code_utilise, statut, active_depuis)
                VALUES (?, ?, ?, 'actif', NOW())
            ")->execute([$parrain['proprietaire_id'], $proprietaire_id, $request['code_parrain']]);

            // Incrementer le compteur
            $conn->prepare("UPDATE codes_parrainage SET nb_utilisations = nb_utilisations + 1 WHERE code = ?")
                ->execute([$request['code_parrain']]);

            // Calculer la reduction du parrain
            $stmtNb = $conn->prepare("SELECT COUNT(*) FROM parrainages WHERE parrain_id = ? AND statut = 'actif'");
            $stmtNb->execute([$parrain['proprietaire_id']]);
            $nb_filleuls = (int)$stmtNb->fetchColumn();
            $reduction = min($nb_filleuls * 1.0, 5.0); // max -5%

            $conn->prepare("
                UPDATE proprietaire_commission_config
                SET reduction_parrainage = ?
                WHERE proprietaire_id = ?
            ")->execute([$reduction, $parrain['proprietaire_id']]);
        }
    }

    // 5. Generer le code parrainage du nouveau proprio
    $code = 'FRENCHY' . strtoupper(substr($request['prenom'] ?? 'X', 0, 4)) . $proprietaire_id;
    $conn->prepare("
        INSERT INTO codes_parrainage (proprietaire_id, code) VALUES (?, ?)
    ")->execute([$proprietaire_id, $code]);

    // 6. Creer les taches automatiques
    $tasks = ['create_proprietaire', 'create_logement', 'generate_site', 'sms_bienvenue', 'setup_superhote'];
    $stmtTask = $conn->prepare("
        INSERT INTO onboarding_tasks (request_id, type, statut) VALUES (?, ?, 'done')
    ");
    // Les 2 premieres sont deja faites
    $stmtTask->execute([$request['id'], 'create_proprietaire']);
    $stmtTask->execute([$request['id'], 'create_logement']);
    // Les autres sont en pending
    $stmtTaskP = $conn->prepare("
        INSERT INTO onboarding_tasks (request_id, type, statut) VALUES (?, ?, 'pending')
    ");
    $stmtTaskP->execute([$request['id'], 'generate_site']);
    $stmtTaskP->execute([$request['id'], 'sms_bienvenue']);
    $stmtTaskP->execute([$request['id'], 'setup_superhote']);

    // 7. Marquer comme termine
    $conn->prepare("
        UPDATE onboarding_requests
        SET statut = 'termine', progression = 100, proprietaire_id = ?, logement_id = ?, completed_at = NOW()
        WHERE token = ?
    ")->execute([$proprietaire_id, $logement_id, $token]);

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
