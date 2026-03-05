<?php
/**
 * Fonction de creation de session de checkup avec tous les items.
 * Utilisee par checkup_logement.php et planning.php
 */

if (!function_exists('createCheckupSession')) {

function createCheckupSession(PDO $conn, int $logement_id, ?int $intervenant_id = null): int {
    $stmt = $conn->prepare("INSERT INTO checkup_sessions (logement_id, intervenant_id) VALUES (?, ?)");
    $stmt->execute([$logement_id, $intervenant_id]);
    $session_id = (int) $conn->lastInsertId();

    $insertStmt = $conn->prepare(
        "INSERT INTO checkup_items (session_id, categorie, nom_item, todo_task_id) VALUES (?, ?, ?, ?)"
    );

    // Charger les equipements du logement
    $equip = null;
    try {
        $eqStmt = $conn->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
        $eqStmt->execute([$logement_id]);
        $equip = $eqStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // === 1. ENTREE / ACCES ===
    $entree = ['Porte d\'entree — etat et fermeture'];
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
    if ($equip && !empty($equip['ascenseur'])) $entree[] = 'Ascenseur — fonctionne';
    if ($equip && !empty($equip['heure_checkin'])) $entree[] = 'Heure check-in (' . $equip['heure_checkin'] . ') — affichee';
    if ($equip && !empty($equip['heure_checkout'])) $entree[] = 'Heure check-out (' . $equip['heure_checkout'] . ') — affichee';
    foreach ($entree as $item) { $insertStmt->execute([$session_id, 'Entree / Acces', $item, null]); }

    // === 2. SALON / SEJOUR ===
    $salon = ['Sol du salon — propre (aspire/lave)'];
    if ($equip && !empty($equip['canape'])) {
        $salon[] = 'Canape — etat des coussins et assise';
        $salon[] = 'Sous le canape — propre, rien oublie';
        $salon[] = 'Coussins decoratifs — propres et en place';
        $salon[] = 'Plaids / couvertures — propres et plies';
    }
    if ($equip && !empty($equip['canape_convertible'])) $salon[] = 'Canape convertible — mecanisme fonctionne, matelas propre';
    $salon[] = 'Table basse — propre, sans traces';
    if ($equip && !empty($equip['tv'])) $salon[] = 'Meuble TV — propre, cables ranges';
    if ($equip && !empty($equip['table_manger'])) {
        $nbPlaces = (!empty($equip['table_manger_places'])) ? ' (' . $equip['table_manger_places'] . ' places)' : '';
        $salon[] = 'Table a manger' . $nbPlaces . ' — propre';
        $salon[] = 'Chaises — propres, en bon etat';
    }
    if ($equip && !empty($equip['bureau'])) $salon[] = 'Bureau — propre, chaise ok';
    if ($equip && !empty($equip['livres'])) $salon[] = 'Livres / bibliotheque — en ordre';
    if ($equip && !empty($equip['jeux_societe'])) $salon[] = 'Jeux de societe — complets, en bon etat';
    $salon[] = 'Rideaux / voilages — propres, fonctionnent';
    $salon[] = 'Fenetres salon — propres (interieur)';
    $salon[] = 'Rebords de fenetres — sans poussiere';
    if ($equip && !empty($equip['chauffage'])) {
        $typeChauf = !empty($equip['chauffage_type']) ? ' (' . $equip['chauffage_type'] . ')' : '';
        $salon[] = 'Chauffage' . $typeChauf . ' — fonctionne';
    }
    if ($equip && !empty($equip['climatisation'])) $salon[] = 'Climatisation — fonctionne';
    if ($equip && !empty($equip['ventilateur'])) $salon[] = 'Ventilateur — fonctionne';
    $salon[] = 'Prises electriques salon — fonctionnent';
    $salon[] = 'Lumieres / lampes salon — fonctionnent';
    $salon[] = 'Interrupteurs salon — fonctionnent';
    $salon[] = 'Murs salon — pas de taches / trous';
    $salon[] = 'Plafond salon — pas de taches / fissures';
    $salon[] = 'Plinthes salon — propres';
    $salon[] = 'Decoration murale — en place, pas abimee';
    foreach ($salon as $item) { $insertStmt->execute([$session_id, 'Salon / Sejour', $item, null]); }

    // === 3. CUISINE ===
    $cuisineBase = [
        'Sol cuisine — propre (lave)', 'Plan de travail — propre, sans traces',
        'Evier — propre, pas bouche', 'Robinet cuisine — fonctionne, pas de fuite',
        'Sous l\'evier — propre, pas de fuite', 'Poubelle cuisine — videe et propre',
        'Poubelle tri selectif — videe', 'Interieur placards — propres et ranges',
        'Vaisselle — propre et rangee', 'Verres — propres, pas ebreches',
        'Couverts — complets et propres', 'Casseroles / poeles — propres',
        'Planche a decouper — propre', 'Torchons — propres',
        'Eponge — neuve ou propre', 'Produit vaisselle — disponible',
        'Interrupteurs / prises cuisine — fonctionnent', 'Lumieres cuisine — fonctionnent',
        'Fenetres cuisine — propres', 'Murs / credence — propres, sans eclaboussures',
    ];
    foreach ($cuisineBase as $item) { $insertStmt->execute([$session_id, 'Cuisine', $item, null]); }

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
            if (!empty($equip[$field])) { $insertStmt->execute([$session_id, 'Cuisine', $label, null]); }
        }
        if (!empty($equip['plaque_cuisson'])) {
            $insertStmt->execute([$session_id, 'Cuisine', 'Hotte aspirante — propre, filtre ok', null]);
        }
    }

    // === 4. CHAMBRES ===
    $nbChambres = ($equip && isset($equip['nombre_chambres']) && $equip['nombre_chambres'] > 0) ? (int)$equip['nombre_chambres'] : 1;
    for ($ch = 1; $ch <= $nbChambres; $ch++) {
        $catName = $nbChambres > 1 ? "Chambre $ch" : 'Chambre';
        $chambreItems = [
            'Sol — propre (aspire/lave)', 'Lit — draps propres et bien faits',
            'SOUS LE LIT — propre, rien oublie', 'Matelas — etat, pas de tache',
            'Oreillers — propres, en bon etat', 'Couette / couverture — propre',
            'Table de chevet — propre, sans poussiere', 'Lampe de chevet — fonctionne',
            'Armoire / penderie — propre, cintres en place', 'Interieur tiroirs — propres et vides',
            'Commode — propre, sans poussiere', 'Miroir — propre, sans traces',
            'Rideaux / voilages — propres', 'Fenetres — propres, ferment bien',
            'Volets / stores — fonctionnent', 'Rebords de fenetres — sans poussiere',
            'Prises electriques — fonctionnent', 'Lumieres / plafonnier — fonctionnent',
            'Interrupteurs — fonctionnent', 'Murs — pas de taches / trous',
            'Plafond — pas de taches / fissures', 'Plinthes — propres', 'Derriere la porte — propre',
        ];
        foreach ($chambreItems as $item) { $insertStmt->execute([$session_id, $catName, $item, null]); }
        if ($equip && !empty($equip['chauffage'])) {
            $insertStmt->execute([$session_id, $catName, 'Radiateur / chauffage — propre et fonctionne', null]);
        }
    }

    // === 5. SALLE DE BAIN ===
    $nbSdb = ($equip && isset($equip['nombre_salles_bain']) && $equip['nombre_salles_bain'] > 0) ? (int)$equip['nombre_salles_bain'] : 1;
    for ($sb = 1; $sb <= $nbSdb; $sb++) {
        $catName = $nbSdb > 1 ? "Salle de bain $sb" : 'Salle de bain';
        $sdbBase = [
            'Sol — propre et sec', 'Lavabo — propre, sans calcaire',
            'Robinet lavabo — fonctionne, pas de fuite', 'Miroir — propre, sans traces',
            'TOILETTES — cuvette propre et desinfectee', 'TOILETTES — lunette et abattant propres',
            'TOILETTES — derriere la cuvette propre', 'TOILETTES — chasse d\'eau fonctionne',
            'TOILETTES — brosse WC propre', 'TOILETTES — porte-rouleau avec papier neuf',
            'Carrelage mural — propre, joints ok', 'Serviettes — propres, bien pliees/suspendues',
            'Tapis de bain — propre', 'Produits de toilette — savon, shampoing, gel douche',
            'Poubelle salle de bain — videe', 'Ventilation / VMC — fonctionne',
            'Rangements / etageres — propres', 'Porte-serviettes — en bon etat',
            'Lumieres — fonctionnent', 'Interrupteur — fonctionne', 'Prise electrique — fonctionne',
        ];
        foreach ($sdbBase as $item) { $insertStmt->execute([$session_id, $catName, $item, null]); }
        if ($equip) {
            if (!empty($equip['douche'])) {
                $insertStmt->execute([$session_id, $catName, 'Douche — paroi/rideau propre', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — pommeau et flexible en bon etat', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — bac propre, evacuation ok', null]);
                $insertStmt->execute([$session_id, $catName, 'Douche — joints propres (pas de moisissure)', null]);
            }
            if (!empty($equip['baignoire'])) $insertStmt->execute([$session_id, $catName, 'Baignoire — propre, evacuation ok', null]);
            if (!empty($equip['seche_cheveux'])) $insertStmt->execute([$session_id, $catName, 'Seche-cheveux — fonctionne', null]);
        }
    }

    // === 6. WC SEPARE ===
    $wcSepare = [
        'Sol — propre', 'Cuvette WC — propre et desinfectee', 'Lunette et abattant — propres',
        'Derriere la cuvette — propre', 'Chasse d\'eau — fonctionne', 'Brosse WC — propre',
        'Papier toilette — rouleau neuf', 'Lave-mains — propre (si present)',
        'Miroir — propre (si present)', 'Poubelle — videe', 'Desodorisant — present',
        'Lumiere — fonctionne', 'Ventilation — fonctionne',
    ];
    foreach ($wcSepare as $item) { $insertStmt->execute([$session_id, 'WC separe', $item, null]); }

    // === 7. BUANDERIE / ENTRETIEN ===
    if ($equip) {
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
            if (!empty($equip[$field])) { $insertStmt->execute([$session_id, 'Buanderie / Entretien', $label, null]); $hasBuanderie = true; }
        }
        if ($hasBuanderie) {
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Sol — propre', null]);
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Balai / serpillere — propres', null]);
            $insertStmt->execute([$session_id, 'Buanderie / Entretien', 'Lessive — disponible', null]);
        }
    }

    // === 8. MULTIMEDIA ===
    if ($equip) {
        if (!empty($equip['tv'])) { $insertStmt->execute([$session_id, 'Multimedia', 'Television — fonctionne', null]); $insertStmt->execute([$session_id, 'Multimedia', 'Telecommande TV — fonctionne, piles ok', null]); }
        if (!empty($equip['netflix'])) $insertStmt->execute([$session_id, 'Multimedia', 'Netflix — connexion ok', null]);
        if (!empty($equip['amazon_prime'])) $insertStmt->execute([$session_id, 'Multimedia', 'Amazon Prime — connexion ok', null]);
        if (!empty($equip['disney_plus'])) $insertStmt->execute([$session_id, 'Multimedia', 'Disney+ — connexion ok', null]);
        if (!empty($equip['molotov_tv'])) $insertStmt->execute([$session_id, 'Multimedia', 'Molotov TV — connexion ok', null]);
        if (!empty($equip['enceinte_bluetooth'])) $insertStmt->execute([$session_id, 'Multimedia', 'Enceinte Bluetooth — fonctionne, chargee', null]);
        if (!empty($equip['console_jeux'])) $insertStmt->execute([$session_id, 'Multimedia', 'Console de jeux — fonctionne, manettes ok', null]);
    }

    // === 9. EXTERIEUR ===
    if ($equip) {
        if (!empty($equip['balcon'])) { $insertStmt->execute([$session_id, 'Exterieur', 'Balcon — sol propre', null]); $insertStmt->execute([$session_id, 'Exterieur', 'Balcon — rambarde en bon etat', null]); $insertStmt->execute([$session_id, 'Exterieur', 'Balcon — mobilier propre', null]); }
        if (!empty($equip['terrasse'])) { $insertStmt->execute([$session_id, 'Exterieur', 'Terrasse — sol propre', null]); $insertStmt->execute([$session_id, 'Exterieur', 'Terrasse — mobilier propre et en place', null]); }
        if (!empty($equip['jardin'])) { $insertStmt->execute([$session_id, 'Exterieur', 'Jardin — pelouse / vegetation ok', null]); $insertStmt->execute([$session_id, 'Exterieur', 'Jardin — propre, pas de dechets', null]); }
        if (!empty($equip['parking'])) $insertStmt->execute([$session_id, 'Exterieur', 'Parking — accessible, place libre', null]);
        if (!empty($equip['barbecue'])) $insertStmt->execute([$session_id, 'Exterieur', 'Barbecue — propre, pret a l\'emploi', null]);
        if (!empty($equip['salon_jardin'])) $insertStmt->execute([$session_id, 'Exterieur', 'Salon de jardin — propre, en bon etat', null]);
    }

    // === 10. SECURITE ===
    if ($equip) {
        if (!empty($equip['detecteur_fumee'])) $insertStmt->execute([$session_id, 'Securite', 'Detecteur de fumee — present et fonctionne', null]);
        if (!empty($equip['detecteur_co'])) $insertStmt->execute([$session_id, 'Securite', 'Detecteur CO — present et fonctionne', null]);
        if (!empty($equip['extincteur'])) $insertStmt->execute([$session_id, 'Securite', 'Extincteur — present et accessible', null]);
        if (!empty($equip['trousse_secours'])) $insertStmt->execute([$session_id, 'Securite', 'Trousse de secours — presente et complete', null]);
        if (!empty($equip['coffre_fort'])) $insertStmt->execute([$session_id, 'Securite', 'Coffre-fort — fonctionne', null]);
        $insertStmt->execute([$session_id, 'Securite', 'Issues de secours — degagees', null]);
        if (!empty($equip['numeros_urgence'])) $insertStmt->execute([$session_id, 'Securite', 'Numeros urgences — affiches', null]);
    }

    // === 11. ENFANTS ===
    if ($equip) {
        if (!empty($equip['lit_bebe'])) $insertStmt->execute([$session_id, 'Enfants', 'Lit bebe — propre, en bon etat, drap', null]);
        if (!empty($equip['chaise_haute'])) $insertStmt->execute([$session_id, 'Enfants', 'Chaise haute — propre, sangles ok', null]);
        if (!empty($equip['barriere_securite'])) $insertStmt->execute([$session_id, 'Enfants', 'Barriere de securite — en place, fonctionne', null]);
        if (!empty($equip['jeux_enfants'])) $insertStmt->execute([$session_id, 'Enfants', 'Jeux enfants — propres, complets', null]);
    }

    // === 12. INVENTAIRE ===
    try {
        $invStmt = $conn->prepare("SELECT io.nom_objet, io.quantite, io.piece FROM inventaire_objets io INNER JOIN sessions_inventaire si ON io.session_id = si.id WHERE si.logement_id = ? AND si.statut = 'terminee' ORDER BY si.date_creation DESC");
        $invStmt->execute([$logement_id]);
        foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $obj) {
            $label = $obj['nom_objet'];
            if ($obj['quantite'] > 1) $label .= ' (x' . $obj['quantite'] . ')';
            if ($obj['piece']) $label .= ' [' . $obj['piece'] . ']';
            $insertStmt->execute([$session_id, 'Inventaire', $label, null]);
        }
    } catch (PDOException $e) {}

    // === 13. TACHES A FAIRE ===
    try {
        $todoStmt = $conn->prepare("SELECT id, description, date_limite FROM todo_list WHERE logement_id = ? AND statut IN ('en attente', 'en cours') ORDER BY date_limite ASC");
        $todoStmt->execute([$logement_id]);
        foreach ($todoStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $label = $t['description'];
            if ($t['date_limite']) $label .= ' (avant le ' . date('d/m', strtotime($t['date_limite'])) . ')';
            $insertStmt->execute([$session_id, 'Taches a faire', $label, $t['id']]);
        }
    } catch (PDOException $e) {}

    // === 14. ETAT GENERAL ===
    $etatGeneral = [
        'Proprete generale du logement', 'Odeurs — pas de mauvaises odeurs',
        'Sols — tous propres dans chaque piece', 'Vitres / fenetres — propres (interieur)',
        'Volets / stores — tous fonctionnent', 'Poubelles — toutes videes',
        'Etat des murs — pas de taches / trous', 'Etat des plafonds — pas de taches / fissures',
        'Etat des portes interieures — ferment bien', 'Poignees de porte — toutes en bon etat',
        'Fonctionnement de TOUTES les lumieres', 'Fonctionnement de TOUTES les prises electriques',
        'Cles / codes d\'acces — complets et fonctionnels', 'Livret d\'accueil — present et a jour',
        'Compteurs (eau, elec) — releves',
    ];
    if ($equip && !empty($equip['nom_wifi'])) {
        $etatGeneral[] = 'WiFi (' . $equip['nom_wifi'] . ') — connexion ok';
        $etatGeneral[] = 'Mot de passe WiFi — affiche / accessible';
    }
    if ($equip && !empty($equip['chauffage'])) $etatGeneral[] = 'Thermostat / chauffage — regle correctement';
    foreach ($etatGeneral as $item) { $insertStmt->execute([$session_id, 'Etat general', $item, null]); }

    // === 15. FOURNITURES ===
    $fournitures = [
        'Papier toilette — stock suffisant', 'Savon mains — dans chaque point d\'eau',
        'Liquide vaisselle — disponible', 'Eponges — neuves', 'Sacs poubelle — stock suffisant',
        'Essuie-tout — disponible', 'Sel, poivre, huile — basiques cuisine',
        'The / tisane — disponible', 'Sucre — disponible',
    ];
    if ($equip && !empty($equip['machine_cafe_type']) && $equip['machine_cafe_type'] !== 'aucune') {
        $fournitures[] = 'Capsules / dosettes cafe (' . $equip['machine_cafe_type'] . ') — stock ok';
    }
    if ($equip && !empty($equip['linge_lit_fourni'])) $fournitures[] = 'Draps propres — en stock';
    if ($equip && !empty($equip['serviettes_fournies'])) $fournitures[] = 'Serviettes propres — en stock';
    foreach ($fournitures as $item) { $insertStmt->execute([$session_id, 'Fournitures', $item, null]); }

    // === 16. TEMPLATES PERSONNALISES ===
    try {
        $tplStmt = $conn->prepare("SELECT categorie, nom_item FROM checkup_templates WHERE actif = 1 AND (logement_id IS NULL OR logement_id = ?) ORDER BY categorie, ordre, nom_item");
        $tplStmt->execute([$logement_id]);
        foreach ($tplStmt->fetchAll(PDO::FETCH_ASSOC) as $ci) {
            $insertStmt->execute([$session_id, $ci['categorie'], $ci['nom_item'], null]);
        }
    } catch (PDOException $e) {}

    return $session_id;
}

} // end if !function_exists
