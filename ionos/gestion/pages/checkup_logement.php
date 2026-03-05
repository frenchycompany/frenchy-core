<?php
/**
 * Checkup Logement — Lancement d'un checkup
 * Hub central : equipements + inventaire + taches + etat general
 */

// Debug : attraper les erreurs fatales silencieuses en production
ini_set('display_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo '<pre style="background:#fdd;padding:20px;margin:20px;border:2px solid #c00;border-radius:8px;">';
        echo "<b>Erreur fatale :</b> {$error['message']}\n";
        echo "Fichier : {$error['file']}\n";
        echo "Ligne : {$error['line']}\n";
        echo '</pre>';
    }
});

include '../config.php';
include '../pages/menu.php';

// Creation des tables si elles n'existent pas
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS checkup_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            logement_id INT NOT NULL,
            intervenant_id INT DEFAULT NULL,
            statut ENUM('en_cours','termine') DEFAULT 'en_cours',
            nb_ok INT DEFAULT 0,
            nb_problemes INT DEFAULT 0,
            nb_absents INT DEFAULT 0,
            nb_taches_faites INT DEFAULT 0,
            commentaire_general TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_logement (logement_id),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS checkup_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Ajouter les colonnes manquantes sur tables existantes
    try { $conn->exec("ALTER TABLE checkup_items ADD COLUMN todo_task_id INT DEFAULT NULL AFTER photo_path"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN nb_taches_faites INT DEFAULT 0 AFTER nb_absents"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN signature_path VARCHAR(500) DEFAULT NULL AFTER commentaire_general"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN video_path VARCHAR(500) DEFAULT NULL AFTER signature_path"); } catch (PDOException $e) {}
} catch (PDOException $e) {
    // Tables existent deja
}

// AJAX : preview du logement quand on le selectionne
if (isset($_GET['ajax_preview']) && isset($_GET['logement_id'])) {
    header('Content-Type: application/json');
    $lid = intval($_GET['logement_id']);

    // Taches en attente
    $nbTaches = 0;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM todo_list WHERE logement_id = ? AND statut IN ('en attente','en cours')");
        $stmt->execute([$lid]);
        $nbTaches = $stmt->fetchColumn();
    } catch (PDOException $e) {}

    // Dernier inventaire
    $lastInv = null;
    try {
        $stmt = $conn->prepare("SELECT s.date_creation, COUNT(o.id) AS nb_objets FROM sessions_inventaire s LEFT JOIN inventaire_objets o ON o.session_id = s.id WHERE s.logement_id = ? AND s.statut = 'terminee' GROUP BY s.id ORDER BY s.date_creation DESC LIMIT 1");
        $stmt->execute([$lid]);
        $lastInv = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // Equipements renseignes
    $hasEquip = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM logement_equipements WHERE logement_id = ?");
        $stmt->execute([$lid]);
        $hasEquip = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {}

    // Session en cours
    $stmt = $conn->prepare("SELECT id FROM checkup_sessions WHERE logement_id = ? AND statut = 'en_cours' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$lid]);
    $enCours = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'nb_taches' => (int)$nbTaches,
        'dernier_inventaire' => $lastInv ? date('d/m/Y', strtotime($lastInv['date_creation'])) : null,
        'nb_objets_inventaire' => $lastInv ? (int)$lastInv['nb_objets'] : 0,
        'has_equipements' => $hasEquip,
        'session_en_cours' => $enCours ? $enCours['id'] : null,
    ]);
    exit;
}

// Traitement : creer une session de checkup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logement_id'])) {
    $logement_id = intval($_POST['logement_id']);
    $intervenant_id = $_SESSION['id_intervenant'] ?? null;

    // Creer la session
    $stmt = $conn->prepare("INSERT INTO checkup_sessions (logement_id, intervenant_id) VALUES (?, ?)");
    $stmt->execute([$logement_id, $intervenant_id]);
    $session_id = $conn->lastInsertId();

    $insertStmt = $conn->prepare(
        "INSERT INTO checkup_items (session_id, categorie, nom_item, todo_task_id) VALUES (?, ?, ?, ?)"
    );

    // Charger les equipements du logement EN PREMIER
    $equip = null;
    try {
        $stmt = $conn->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* table n'existe pas encore */ }

    // === 1. ENTREE / ACCES ===
    $entree = [
        'Porte d\'entree — etat et fermeture',
    ];
    if ($equip && !empty($equip['code_porte'])) {
        $entree[] = 'Serrure / digicode (' . $equip['code_porte'] . ') — fonctionne';
    } else {
        $entree[] = 'Serrure — fonctionne correctement';
    }
    if ($equip && !empty($equip['code_boite_cles'])) {
        $entree[] = 'Boite a cles / key box (code: ' . $equip['code_boite_cles'] . ') — verifier';
    }
    $entree[] = 'Paillasson — propre et en bon etat';
    $entree[] = 'Couloir d\'entree — proprete';
    $entree[] = 'Interrupteurs entree — fonctionnent';
    $entree[] = 'Patere / porte-manteaux — etat';
    if ($equip && !empty($equip['ascenseur'])) {
        $entree[] = 'Ascenseur — fonctionne';
    }
    if ($equip && !empty($equip['heure_checkin'])) {
        $entree[] = 'Heure check-in (' . $equip['heure_checkin'] . ') — affichee';
    }
    if ($equip && !empty($equip['heure_checkout'])) {
        $entree[] = 'Heure check-out (' . $equip['heure_checkout'] . ') — affichee';
    }
    foreach ($entree as $item) {
        $insertStmt->execute([$session_id, 'Entree / Acces', $item, null]);
    }

    // === 2. SALON / SEJOUR ===
    $salon = [
        'Sol du salon — propre (aspire/lave)',
    ];
    if ($equip && !empty($equip['canape'])) {
        $salon[] = 'Canape — etat des coussins et assise';
        $salon[] = 'Sous le canape — propre, rien oublie';
        $salon[] = 'Coussins decoratifs — propres et en place';
        $salon[] = 'Plaids / couvertures — propres et plies';
    }
    if ($equip && !empty($equip['canape_convertible'])) {
        $salon[] = 'Canape convertible — mecanisme fonctionne, matelas propre';
    }
    $salon[] = 'Table basse — propre, sans traces';
    if ($equip && !empty($equip['tv'])) {
        $salon[] = 'Meuble TV — propre, cables ranges';
    }
    if ($equip && !empty($equip['table_manger'])) {
        $nbPlaces = (!empty($equip['table_manger_places'])) ? ' (' . $equip['table_manger_places'] . ' places)' : '';
        $salon[] = 'Table a manger' . $nbPlaces . ' — propre';
        $salon[] = 'Chaises — propres, en bon etat';
    }
    if ($equip && !empty($equip['bureau'])) {
        $salon[] = 'Bureau — propre, chaise ok';
    }
    if ($equip && !empty($equip['livres'])) {
        $salon[] = 'Livres / bibliotheque — en ordre';
    }
    if ($equip && !empty($equip['jeux_societe'])) {
        $salon[] = 'Jeux de societe — complets, en bon etat';
    }
    $salon[] = 'Rideaux / voilages — propres, fonctionnent';
    $salon[] = 'Fenetres salon — propres (interieur)';
    $salon[] = 'Rebords de fenetres — sans poussiere';
    if ($equip && !empty($equip['chauffage'])) {
        $typeChauf = !empty($equip['chauffage_type']) ? ' (' . $equip['chauffage_type'] . ')' : '';
        $salon[] = 'Chauffage' . $typeChauf . ' — fonctionne';
    }
    if ($equip && !empty($equip['climatisation'])) {
        $salon[] = 'Climatisation — fonctionne';
    }
    if ($equip && !empty($equip['ventilateur'])) {
        $salon[] = 'Ventilateur — fonctionne';
    }
    $salon[] = 'Prises electriques salon — fonctionnent';
    $salon[] = 'Lumieres / lampes salon — fonctionnent';
    $salon[] = 'Interrupteurs salon — fonctionnent';
    $salon[] = 'Murs salon — pas de taches / trous';
    $salon[] = 'Plafond salon — pas de taches / fissures';
    $salon[] = 'Plinthes salon — propres';
    $salon[] = 'Decoration murale — en place, pas abimee';
    foreach ($salon as $item) {
        $insertStmt->execute([$session_id, 'Salon / Sejour', $item, null]);
    }

    // === 3. CUISINE ===
    // Items cuisine toujours presents
    $cuisineBase = [
        'Sol cuisine — propre (lave)',
        'Plan de travail — propre, sans traces',
        'Evier — propre, pas bouche',
        'Robinet cuisine — fonctionne, pas de fuite',
        'Sous l\'evier — propre, pas de fuite',
        'Poubelle cuisine — videe et propre',
        'Poubelle tri selectif — videe',
        'Interieur placards — propres et ranges',
        'Vaisselle — propre et rangee',
        'Verres — propres, pas ebreches',
        'Couverts — complets et propres',
        'Casseroles / poeles — propres',
        'Planche a decouper — propre',
        'Torchons — propres',
        'Eponge — neuve ou propre',
        'Produit vaisselle — disponible',
        'Interrupteurs / prises cuisine — fonctionnent',
        'Lumieres cuisine — fonctionnent',
        'Fenetres cuisine — propres',
        'Murs / credence — propres, sans eclaboussures',
    ];
    foreach ($cuisineBase as $item) {
        $insertStmt->execute([$session_id, 'Cuisine', $item, null]);
    }

    // Items cuisine conditionnels (selon equipements)
    if ($equip) {
        $cuisineEquip = [
            'four' => 'Four — fonctionne, interieur propre',
            'micro_ondes' => 'Micro-ondes — fonctionne, interieur propre',
            'plaque_cuisson' => 'Plaques de cuisson — propres, fonctionnent',
            'refrigerateur' => 'Refrigerateur — fonctionne, interieur propre, pas de nourriture oubliee',
            'congelateur' => 'Congelateur — fonctionne, interieur propre, pas de givre',
            'lave_vaisselle' => 'Lave-vaisselle — fonctionne, interieur propre',
            'bouilloire' => 'Bouilloire — fonctionne et propre',
            'grille_pain' => 'Grille-pain — fonctionne et propre',
            'ustensiles_cuisine' => 'Ustensiles de cuisine — complets',
        ];
        if (!empty($equip['machine_cafe_type']) && $equip['machine_cafe_type'] !== 'aucune') {
            $cuisineEquip['machine_cafe_type'] = 'Machine a cafe (' . $equip['machine_cafe_type'] . ') — fonctionne, propre, capsules/dosettes';
        }
        foreach ($cuisineEquip as $field => $label) {
            if (!empty($equip[$field])) {
                $insertStmt->execute([$session_id, 'Cuisine', $label, null]);
            }
        }
        // Hotte (pas de champ dedie, on l'ajoute toujours si la cuisine a des plaques)
        if (!empty($equip['plaque_cuisson'])) {
            $insertStmt->execute([$session_id, 'Cuisine', 'Hotte aspirante — propre, filtre ok', null]);
        }
    }

    // === 4. CHAMBRES ===
    // Determiner le nombre de chambres
    $nbChambres = 1;
    if ($equip && isset($equip['nombre_chambres']) && $equip['nombre_chambres'] > 0) {
        $nbChambres = (int) $equip['nombre_chambres'];
    }

    for ($ch = 1; $ch <= $nbChambres; $ch++) {
        $catName = $nbChambres > 1 ? "Chambre $ch" : 'Chambre';
        $chambreItems = [
            'Sol — propre (aspire/lave)',
            'Lit — draps propres et bien faits',
            'SOUS LE LIT — propre, rien oublie',
            'Matelas — etat, pas de tache',
            'Oreillers — propres, en bon etat',
            'Couette / couverture — propre',
            'Table de chevet — propre, sans poussiere',
            'Lampe de chevet — fonctionne',
            'Armoire / penderie — propre, cintres en place',
            'Interieur tiroirs — propres et vides',
            'Commode — propre, sans poussiere',
            'Miroir — propre, sans traces',
            'Rideaux / voilages — propres',
            'Fenetres — propres, ferment bien',
            'Volets / stores — fonctionnent',
            'Rebords de fenetres — sans poussiere',
            'Prises electriques — fonctionnent',
            'Lumieres / plafonnier — fonctionnent',
            'Interrupteurs — fonctionnent',
            'Murs — pas de taches / trous',
            'Plafond — pas de taches / fissures',
            'Plinthes — propres',
            'Derriere la porte — propre',
        ];
        foreach ($chambreItems as $item) {
            $insertStmt->execute([$session_id, $catName, $item, null]);
        }
        // Items conditionnels chambre
        if ($equip && !empty($equip['chauffage'])) {
            $insertStmt->execute([$session_id, $catName, 'Radiateur / chauffage — propre et fonctionne', null]);
        }
    }

    // === 5. SALLE DE BAIN ===
    $nbSdb = 1;
    if ($equip && isset($equip['nombre_salles_bain']) && $equip['nombre_salles_bain'] > 0) {
        $nbSdb = (int) $equip['nombre_salles_bain'];
    }

    for ($sb = 1; $sb <= $nbSdb; $sb++) {
        $catName = $nbSdb > 1 ? "Salle de bain $sb" : 'Salle de bain';

        // Items toujours presents
        $sdbBase = [
            'Sol — propre et sec',
            'Lavabo — propre, sans calcaire',
            'Robinet lavabo — fonctionne, pas de fuite',
            'Miroir — propre, sans traces',
            'TOILETTES — cuvette propre et desinfectee',
            'TOILETTES — lunette et abattant propres',
            'TOILETTES — derriere la cuvette propre',
            'TOILETTES — chasse d\'eau fonctionne',
            'TOILETTES — brosse WC propre',
            'TOILETTES — porte-rouleau avec papier neuf',
            'Carrelage mural — propre, joints ok',
            'Serviettes — propres, bien pliees/suspendues',
            'Tapis de bain — propre',
            'Produits de toilette — savon, shampoing, gel douche',
            'Poubelle salle de bain — videe',
            'Ventilation / VMC — fonctionne',
            'Rangements / etageres — propres',
            'Porte-serviettes — en bon etat',
            'Lumieres — fonctionnent',
            'Interrupteur — fonctionne',
            'Prise electrique — fonctionne',
        ];
        foreach ($sdbBase as $item) {
            $insertStmt->execute([$session_id, $catName, $item, null]);
        }

        // Items conditionnels selon equipements
        if ($equip) {
            if (!empty($equip['douche'])) {
                $insertStmt->execute([$session_id, $catName, 'Douche — paroi/rideau propre', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — pommeau et flexible en bon etat', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — bac propre, evacuation ok', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — joints propres (pas de moisissure)', null]);
            }
            if (!empty($equip['baignoire'])) {
                $insertStmt->execute([$session_id, $catName, 'Baignoire — propre, evacuation ok', null]);
            }
            if (!empty($equip['seche_cheveux'])) {
                $insertStmt->execute([$session_id, $catName, 'Seche-cheveux — fonctionne', null]);
            }
        }
    }

    // === 6. WC SEPARE (si different de salle de bain) ===
    $wcSepare = [
        'Sol — propre',
        'Cuvette WC — propre et desinfectee',
        'Lunette et abattant — propres',
        'Derriere la cuvette — propre',
        'Chasse d\'eau — fonctionne',
        'Brosse WC — propre',
        'Papier toilette — rouleau neuf',
        'Lave-mains — propre (si present)',
        'Miroir — propre (si present)',
        'Poubelle — videe',
        'Desodorisant — present',
        'Lumiere — fonctionne',
        'Ventilation — fonctionne',
    ];
    foreach ($wcSepare as $item) {
        $insertStmt->execute([$session_id, 'WC separe', $item, null]);
    }

    // === 7. BUANDERIE / ENTRETIEN (uniquement les equipements presents) ===
    if ($equip) {
        // Items de base toujours presents si au moins un equipement buanderie existe
        $buanderieEquip = [
            'machine_laver' => 'Machine a laver — propre, joint ok',
            'seche_linge' => 'Seche-linge — propre, filtre nettoye',
            'fer_repasser' => 'Fer a repasser — fonctionne',
            'table_repasser' => 'Table a repasser — en bon etat',
            'aspirateur' => 'Aspirateur — fonctionne, sac/filtre ok',
            'produits_menage' => 'Produits menagers — en stock',
        ];

        $hasBuanderie = false;
        foreach ($buanderieEquip as $field => $label) {
            if (!empty($equip[$field])) {
                $insertStmt->execute([$session_id, 'Buanderie / Entretien', $label, null]);
                $hasBuanderie = true;
            }
        }

        // Items generiques si au moins un equipement buanderie est present
        if ($hasBuanderie) {
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Sol — propre', null]);
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Balai / serpillere — propres', null]);
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Lessive — disponible', null]);
        }
    }

    // === 8. MULTIMEDIA ===
    if ($equip) {
        $multiItems = [];
        if (!empty($equip['tv'])) {
            $multiItems[] = 'Television — fonctionne';
            $multiItems[] = 'Telecommande TV — fonctionne, piles ok';
        }
        if (!empty($equip['netflix'])) $multiItems[] = 'Netflix — connexion ok';
        if (!empty($equip['amazon_prime'])) $multiItems[] = 'Amazon Prime — connexion ok';
        if (!empty($equip['disney_plus'])) $multiItems[] = 'Disney+ — connexion ok';
        if (!empty($equip['molotov_tv'])) $multiItems[] = 'Molotov TV — connexion ok';
        if (!empty($equip['enceinte_bluetooth'])) $multiItems[] = 'Enceinte Bluetooth — fonctionne, chargee';
        if (!empty($equip['console_jeux'])) $multiItems[] = 'Console de jeux — fonctionne, manettes ok';

        foreach ($multiItems as $item) {
            $insertStmt->execute([$session_id, 'Multimedia', $item, null]);
        }
    }

    // === 9. EXTERIEUR ===
    if ($equip) {
        $extItems = [];
        if (!empty($equip['balcon'])) {
            $extItems[] = 'Balcon — sol propre';
            $extItems[] = 'Balcon — rambarde en bon etat';
            $extItems[] = 'Balcon — mobilier propre';
        }
        if (!empty($equip['terrasse'])) {
            $extItems[] = 'Terrasse — sol propre';
            $extItems[] = 'Terrasse — mobilier propre et en place';
        }
        if (!empty($equip['jardin'])) {
            $extItems[] = 'Jardin — pelouse / vegetation ok';
            $extItems[] = 'Jardin — propre, pas de dechets';
        }
        if (!empty($equip['parking'])) {
            $extItems[] = 'Parking — accessible, place libre';
        }
        if (!empty($equip['barbecue'])) {
            $extItems[] = 'Barbecue — propre, pret a l\'emploi';
        }
        if (!empty($equip['salon_jardin'])) {
            $extItems[] = 'Salon de jardin — propre, en bon etat';
        }

        foreach ($extItems as $item) {
            $insertStmt->execute([$session_id, 'Exterieur', $item, null]);
        }
    }

    // === 10. SECURITE (conditionnel selon equipements) ===
    if ($equip) {
        $securiteItems = [];
        if (!empty($equip['detecteur_fumee'])) $securiteItems[] = 'Detecteur de fumee — present et fonctionne';
        if (!empty($equip['detecteur_co'])) $securiteItems[] = 'Detecteur CO — present et fonctionne';
        if (!empty($equip['extincteur'])) $securiteItems[] = 'Extincteur — present et accessible';
        if (!empty($equip['trousse_secours'])) $securiteItems[] = 'Trousse de secours — presente et complete';
        if (!empty($equip['coffre_fort'])) $securiteItems[] = 'Coffre-fort — fonctionne';
        // Toujours verifier
        $securiteItems[] = 'Issues de secours — degagees';
        if (!empty($equip['numeros_urgence'])) {
            $securiteItems[] = 'Numeros urgences — affiches';
        }
        foreach ($securiteItems as $item) {
            $insertStmt->execute([$session_id, 'Securite', $item, null]);
        }
    }

    // === 11. ENFANTS (si equipements enfants declares) ===
    if ($equip) {
        $enfantsItems = [];
        if (!empty($equip['lit_bebe'])) $enfantsItems[] = 'Lit bebe — propre, en bon etat, drap';
        if (!empty($equip['chaise_haute'])) $enfantsItems[] = 'Chaise haute — propre, sangles ok';
        if (!empty($equip['barriere_securite'])) $enfantsItems[] = 'Barriere de securite — en place, fonctionne';
        if (!empty($equip['jeux_enfants'])) $enfantsItems[] = 'Jeux enfants — propres, complets';

        foreach ($enfantsItems as $item) {
            $insertStmt->execute([$session_id, 'Enfants', $item, null]);
        }
    }

    // === 12. INVENTAIRE (dernier inventaire termine) ===
    try {
        $stmt = $conn->prepare("
            SELECT io.nom_objet, io.quantite, io.piece
            FROM inventaire_objets io
            INNER JOIN sessions_inventaire si ON io.session_id = si.id
            WHERE si.logement_id = ? AND si.statut = 'terminee'
            ORDER BY si.date_creation DESC
        ");
        $stmt->execute([$logement_id]);
        $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($objets as $obj) {
            $label = $obj['nom_objet'];
            if ($obj['quantite'] > 1) {
                $label .= ' (x' . $obj['quantite'] . ')';
            }
            if ($obj['piece']) {
                $label .= ' [' . $obj['piece'] . ']';
            }
            $insertStmt->execute([$session_id, 'Inventaire', $label, null]);
        }
    } catch (PDOException $e) { /* tables inventaire n'existent pas encore */ }

    // === 13. TACHES A FAIRE (todo_list en attente ou en cours) ===
    try {
        $stmt = $conn->prepare("
            SELECT id, description, date_limite, statut
            FROM todo_list
            WHERE logement_id = ? AND statut IN ('en attente', 'en cours')
            ORDER BY date_limite ASC
        ");
        $stmt->execute([$logement_id]);
        $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($taches as $t) {
            $label = $t['description'];
            if ($t['date_limite']) {
                $label .= ' (avant le ' . date('d/m', strtotime($t['date_limite'])) . ')';
            }
            $insertStmt->execute([$session_id, 'Taches a faire', $label, $t['id']]);
        }
    } catch (PDOException $e) { /* table todo_list n'existe pas encore */ }

    // === 14. ETAT GENERAL ===
    $etatGeneral = [
        'Proprete generale du logement',
        'Odeurs — pas de mauvaises odeurs',
        'Sols — tous propres dans chaque piece',
        'Vitres / fenetres — propres (interieur)',
        'Volets / stores — tous fonctionnent',
        'Poubelles — toutes videes',
        'Etat des murs — pas de taches / trous',
        'Etat des plafonds — pas de taches / fissures',
        'Etat des portes interieures — ferment bien',
        'Poignees de porte — toutes en bon etat',
        'Fonctionnement de TOUTES les lumieres',
        'Fonctionnement de TOUTES les prises electriques',
        'Cles / codes d\'acces — complets et fonctionnels',
        'Livret d\'accueil — present et a jour',
        'Compteurs (eau, elec) — releves',
    ];
    if ($equip && !empty($equip['nom_wifi'])) {
        $etatGeneral[] = 'WiFi (' . $equip['nom_wifi'] . ') — connexion ok';
        $etatGeneral[] = 'Mot de passe WiFi — affiche / accessible';
    }
    if ($equip && !empty($equip['chauffage'])) {
        $etatGeneral[] = 'Thermostat / chauffage — regle correctement';
    }
    foreach ($etatGeneral as $item) {
        $insertStmt->execute([$session_id, 'Etat general', $item, null]);
    }

    // === 15. FOURNITURES / CONSOMMABLES ===
    $fournitures = [
        'Papier toilette — stock suffisant',
        'Savon mains — dans chaque point d\'eau',
        'Liquide vaisselle — disponible',
        'Eponges — neuves',
        'Sacs poubelle — stock suffisant',
        'Essuie-tout — disponible',
        'Sel, poivre, huile — basiques cuisine',
        'The / tisane — disponible',
        'Sucre — disponible',
    ];
    if ($equip && !empty($equip['machine_cafe_type']) && $equip['machine_cafe_type'] !== 'aucune') {
        $fournitures[] = 'Capsules / dosettes cafe (' . $equip['machine_cafe_type'] . ') — stock ok';
    }
    if ($equip && !empty($equip['linge_lit_fourni'])) {
        $fournitures[] = 'Draps propres — en stock';
    }
    if ($equip && !empty($equip['serviettes_fournies'])) {
        $fournitures[] = 'Serviettes propres — en stock';
    }
    foreach ($fournitures as $item) {
        $insertStmt->execute([$session_id, 'Fournitures', $item, null]);
    }

    // === 16. TEMPLATES PERSONNALISES ===
    try {
        $stmt = $conn->prepare("
            SELECT categorie, nom_item FROM checkup_templates
            WHERE actif = 1 AND (logement_id IS NULL OR logement_id = ?)
            ORDER BY categorie, ordre, nom_item
        ");
        $stmt->execute([$logement_id]);
        $customItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($customItems as $ci) {
            $insertStmt->execute([$session_id, $ci['categorie'], $ci['nom_item'], null]);
        }
    } catch (PDOException $e) {
        // Table checkup_templates n'existe pas encore, pas grave
    }

    // Rediriger vers la page de checkup
    header("Location: checkup_faire.php?session_id=" . $session_id);
    exit;
}

// Recuperer les logements avec infos rapides
try {
    $logements = $conn->query("
        SELECT l.id, l.nom_du_logement,
               (SELECT COUNT(*) FROM todo_list t WHERE t.logement_id = l.id AND t.statut IN ('en attente','en cours')) AS nb_taches
        FROM liste_logements l
        WHERE l.actif = 1
        ORDER BY l.nom_du_logement
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback sans compteur todo_list si la table n'existe pas
    try {
        $logements = $conn->query("
            SELECT l.id, l.nom_du_logement, 0 AS nb_taches
            FROM liste_logements l
            WHERE l.actif = 1
            ORDER BY l.nom_du_logement
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $logements = [];
    }
}

// Recuperer les checkups recents
try {
    $recents = $conn->query("
        SELECT cs.*, l.nom_du_logement,
               COALESCE(i.nom, 'Inconnu') AS nom_intervenant
        FROM checkup_sessions cs
        JOIN liste_logements l ON cs.logement_id = l.id
        LEFT JOIN intervenant i ON cs.intervenant_id = i.id
        ORDER BY cs.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recents = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkup Logement</title>
    <style>
        .checkup-container { max-width: 600px; margin: 20px auto; padding: 0 15px; }
        .checkup-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin-bottom: 25px;
        }
        .checkup-header h2 { margin: 0; font-size: 1.4em; }
        .checkup-header p { margin: 8px 0 0; opacity: 0.85; font-size: 0.95em; }
        .launch-card {
            background: #fff; border-radius: 15px; padding: 25px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;
        }
        .launch-card label { font-weight: 600; color: #333; margin-bottom: 10px; display: block; font-size: 1.05em; }
        .launch-card select {
            width: 100%; padding: 14px 12px; font-size: 1.1em;
            border: 2px solid #e0e0e0; border-radius: 10px;
            background: #fafafa; margin-bottom: 12px; appearance: auto;
        }
        .launch-card select:focus { border-color: #1976d2; outline: none; }
        /* Preview logement */
        .logement-preview {
            display: none; background: #f8fafd; border-radius: 12px;
            padding: 14px; margin-bottom: 15px; border: 1px solid #e3f2fd;
        }
        .logement-preview.visible { display: block; }
        .preview-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 0; font-size: 0.92em;
        }
        .preview-row .label { color: #666; }
        .preview-row .value { font-weight: 600; }
        .preview-badge {
            display: inline-block; padding: 3px 10px; border-radius: 15px;
            font-size: 0.82em; font-weight: 600;
        }
        .badge-warning { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e8f5e9; color: #2e7d32; }
        .badge-info { background: #e3f2fd; color: #1565c0; }
        .badge-none { background: #f5f5f5; color: #999; }
        .preview-encours {
            background: #fff3e0; border: 1px solid #ffcc80; border-radius: 10px;
            padding: 10px 14px; margin-bottom: 10px; font-size: 0.9em; color: #e65100;
        }
        .preview-encours a { color: #1565c0; font-weight: 600; }
        .btn-launch {
            width: 100%; padding: 16px; font-size: 1.15em; font-weight: 700;
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff; cursor: pointer; transition: transform 0.1s;
        }
        .btn-launch:active { transform: scale(0.97); }
        .history-title { font-size: 1.1em; font-weight: 600; color: #555; margin-bottom: 12px; }
        .history-card {
            background: #fff; border-radius: 12px; padding: 15px 18px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06); margin-bottom: 12px;
            display: flex; justify-content: space-between; align-items: center;
            text-decoration: none; color: inherit; transition: box-shadow 0.15s;
        }
        .history-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.12); }
        .history-info h4 { margin: 0 0 4px; font-size: 1em; color: #333; }
        .history-info small { color: #888; }
        .history-stats { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .stat-badge {
            display: inline-block; padding: 4px 10px;
            border-radius: 20px; font-size: 0.82em; font-weight: 600;
        }
        .stat-ok { background: #e8f5e9; color: #2e7d32; }
        .stat-problem { background: #fbe9e7; color: #c62828; }
        .stat-absent { background: #fff3e0; color: #e65100; }
        .stat-encours { background: #e3f2fd; color: #1565c0; }
        .stat-taches { background: #f3e5f5; color: #7b1fa2; }
        @media (max-width: 600px) {
            .checkup-container { margin: 10px auto; }
            .checkup-header { padding: 18px 10px; }
            .checkup-header h2 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
<div class="checkup-container">
    <div class="checkup-header">
        <h2><i class="fas fa-clipboard-check"></i> Checkup Logement</h2>
        <p>Equipements + Inventaire + Taches + Etat general</p>
    </div>

    <div class="launch-card">
        <form method="POST">
            <label for="logement_id"><i class="fas fa-home"></i> Choisir un logement</label>
            <select name="logement_id" id="logement_id" required onchange="loadPreview(this.value)">
                <option value="">-- Selectionnez --</option>
                <?php foreach ($logements as $l): ?>
                    <option value="<?= $l['id'] ?>"
                        <?= $l['nb_taches'] > 0 ? 'data-taches="' . $l['nb_taches'] . '"' : '' ?>>
                        <?= htmlspecialchars($l['nom_du_logement']) ?>
                        <?= $l['nb_taches'] > 0 ? ' (' . $l['nb_taches'] . ' tache' . ($l['nb_taches'] > 1 ? 's' : '') . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="logement-preview" id="preview">
                <div id="previewEnCours" class="preview-encours" style="display:none">
                    <i class="fas fa-exclamation-triangle"></i>
                    Un checkup est deja en cours pour ce logement.
                    <a id="linkEnCours" href="#">Reprendre</a> ou lancer un nouveau.
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-tasks"></i> Taches en attente</span>
                    <span class="value" id="prevTaches">—</span>
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-boxes-stacked"></i> Dernier inventaire</span>
                    <span class="value" id="prevInventaire">—</span>
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-couch"></i> Equipements</span>
                    <span class="value" id="prevEquip">—</span>
                </div>
            </div>

            <button type="submit" class="btn-launch">
                <i class="fas fa-play-circle"></i> Lancer le checkup
            </button>
        </form>
    </div>

    <?php if (!empty($recents)): ?>
    <div class="history-title"><i class="fas fa-history"></i> Checkups recents</div>
    <?php foreach ($recents as $r): ?>
        <a class="history-card" href="<?= $r['statut'] === 'en_cours' ? 'checkup_faire.php?session_id=' . $r['id'] : 'checkup_rapport.php?session_id=' . $r['id'] ?>">
            <div class="history-info">
                <h4><?= htmlspecialchars($r['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> — <?= htmlspecialchars($r['nom_intervenant']) ?></small>
            </div>
            <div class="history-stats">
                <?php if ($r['statut'] === 'en_cours'): ?>
                    <span class="stat-badge stat-encours">En cours</span>
                <?php else: ?>
                    <?php if ($r['nb_ok'] > 0): ?><span class="stat-badge stat-ok"><?= $r['nb_ok'] ?> OK</span><?php endif; ?>
                    <?php if ($r['nb_problemes'] > 0): ?><span class="stat-badge stat-problem"><?= $r['nb_problemes'] ?> pb</span><?php endif; ?>
                    <?php if ($r['nb_absents'] > 0): ?><span class="stat-badge stat-absent"><?= $r['nb_absents'] ?> abs</span><?php endif; ?>
                    <?php if ($r['nb_taches_faites'] > 0): ?><span class="stat-badge stat-taches"><?= $r['nb_taches_faites'] ?> taches</span><?php endif; ?>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Auto-select du logement si on vient d'un QR code
document.addEventListener('DOMContentLoaded', function() {
    var params = new URLSearchParams(window.location.search);
    var autoId = params.get('auto_logement');
    if (autoId) {
        var sel = document.getElementById('logement_id');
        sel.value = autoId;
        loadPreview(autoId);
    }
});

function loadPreview(logementId) {
    var preview = document.getElementById('preview');
    if (!logementId) { preview.classList.remove('visible'); return; }

    fetch('checkup_logement.php?ajax_preview=1&logement_id=' + logementId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            preview.classList.add('visible');

            // Taches
            var tEl = document.getElementById('prevTaches');
            if (data.nb_taches > 0) {
                tEl.innerHTML = '<span class="preview-badge badge-warning">' + data.nb_taches + ' en attente</span>';
            } else {
                tEl.innerHTML = '<span class="preview-badge badge-ok">Aucune</span>';
            }

            // Inventaire
            var iEl = document.getElementById('prevInventaire');
            if (data.dernier_inventaire) {
                iEl.innerHTML = '<span class="preview-badge badge-info">' + data.dernier_inventaire + ' (' + data.nb_objets_inventaire + ' obj.)</span>';
            } else {
                iEl.innerHTML = '<span class="preview-badge badge-none">Jamais fait</span>';
            }

            // Equipements
            var eEl = document.getElementById('prevEquip');
            eEl.innerHTML = data.has_equipements
                ? '<span class="preview-badge badge-ok">Renseignes</span>'
                : '<span class="preview-badge badge-none">Non renseignes</span>';

            // Session en cours
            var ecDiv = document.getElementById('previewEnCours');
            if (data.session_en_cours) {
                ecDiv.style.display = 'block';
                document.getElementById('linkEnCours').href = 'checkup_faire.php?session_id=' + data.session_en_cours;
            } else {
                ecDiv.style.display = 'none';
            }
        });
}
</script>
</body>
</html>
