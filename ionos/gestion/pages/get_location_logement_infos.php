<?php
/**
 * AJAX endpoint - Donnees logement + equipements + details personnalises pour contrats de location
 */
include '../config.php';
header('Content-Type: application/json; charset=utf-8');

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Donnees du logement
    $stmt = $conn->prepare("SELECT * FROM liste_logements WHERE id = ?");
    $stmt->execute([$logement_id]);
    $logement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$logement) {
        echo json_encode(['error' => 'Logement introuvable']);
        exit;
    }

    $result = $logement;

    // Mapper les champs de liste_logements vers les noms attendus
    $result['capacite'] = $logement['nombre_de_personnes'] ?? '';
    $result['surface_m2'] = $logement['m2'] ?? '';

    // Equipements depuis logement_equipements
    $equip = null;
    try {
        $stmt = $conn->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table n'existe pas encore
    }

    if ($equip) {
        // Surface et capacite depuis equipements (plus precis)
        if (!empty($equip['superficie_m2'])) {
            $result['surface_m2'] = $equip['superficie_m2'];
            $result['m2'] = $equip['superficie_m2'];
        }
        if (!empty($equip['nombre_couchages'])) {
            $result['capacite'] = $equip['nombre_couchages'];
        }
        $result['nombre_chambres'] = $equip['nombre_chambres'] ?? '';
        $result['nombre_salles_bain'] = $equip['nombre_salles_bain'] ?? '';
        $result['etage'] = $equip['etage'] ?? '';

        // Horaires check-in/out depuis equipements (fallback)
        $result['equip_heure_checkin'] = $equip['heure_checkin'] ?? '';
        $result['equip_heure_checkout'] = $equip['heure_checkout'] ?? '';

        // Construire la liste des equipements automatiquement
        $equipList = [];

        // Cuisine
        $cuisine = [];
        if (!empty($equip['machine_cafe_type']) && $equip['machine_cafe_type'] !== 'aucune') $cuisine[] = 'Machine a cafe (' . $equip['machine_cafe_type'] . ')';
        if (!empty($equip['bouilloire'])) $cuisine[] = 'Bouilloire';
        if (!empty($equip['grille_pain'])) $cuisine[] = 'Grille-pain';
        if (!empty($equip['micro_ondes'])) $cuisine[] = 'Micro-ondes';
        if (!empty($equip['four'])) $cuisine[] = 'Four';
        if (!empty($equip['plaque_cuisson'])) {
            $type = !empty($equip['plaque_cuisson_type']) ? ' (' . $equip['plaque_cuisson_type'] . ')' : '';
            $cuisine[] = 'Plaque de cuisson' . $type;
        }
        if (!empty($equip['lave_vaisselle'])) $cuisine[] = 'Lave-vaisselle';
        if (!empty($equip['refrigerateur'])) $cuisine[] = 'Refrigerateur';
        if (!empty($equip['congelateur'])) $cuisine[] = 'Congelateur';
        if (!empty($equip['ustensiles_cuisine'])) $cuisine[] = 'Ustensiles de cuisine';
        if (!empty($cuisine)) $equipList[] = 'Cuisine : ' . implode(', ', $cuisine);

        // Linge & entretien
        $linge = [];
        if (!empty($equip['machine_laver'])) $linge[] = 'Lave-linge';
        if (!empty($equip['seche_linge'])) $linge[] = 'Seche-linge';
        if (!empty($equip['fer_repasser'])) $linge[] = 'Fer a repasser';
        if (!empty($equip['aspirateur'])) $linge[] = 'Aspirateur';
        if (!empty($equip['linge_lit_fourni'])) $linge[] = 'Linge de lit fourni';
        if (!empty($equip['serviettes_fournies'])) $linge[] = 'Serviettes fournies';
        if (!empty($linge)) $equipList[] = 'Linge & entretien : ' . implode(', ', $linge);

        // Multimedia
        $media = [];
        if (!empty($equip['tv'])) {
            $tvDesc = 'TV';
            if (!empty($equip['tv_pouces'])) $tvDesc .= ' ' . $equip['tv_pouces'] . '"';
            if (!empty($equip['tv_type'])) $tvDesc .= ' ' . $equip['tv_type'];
            $media[] = $tvDesc;
        }
        if (!empty($equip['netflix'])) $media[] = 'Netflix';
        if (!empty($equip['amazon_prime'])) $media[] = 'Amazon Prime';
        if (!empty($equip['disney_plus'])) $media[] = 'Disney+';
        if (!empty($equip['enceinte_bluetooth'])) $media[] = 'Enceinte Bluetooth';
        if (!empty($equip['jeux_societe'])) $media[] = 'Jeux de societe';
        if (!empty($media)) $equipList[] = 'Multimedia : ' . implode(', ', $media);

        // Confort
        $confort = [];
        if (!empty($equip['climatisation'])) $confort[] = 'Climatisation';
        if (!empty($equip['chauffage'])) {
            $type = !empty($equip['chauffage_type']) ? ' (' . $equip['chauffage_type'] . ')' : '';
            $confort[] = 'Chauffage' . $type;
        }
        if (!empty($equip['seche_cheveux'])) $confort[] = 'Seche-cheveux';
        if (!empty($equip['coffre_fort'])) $confort[] = 'Coffre-fort';
        if (!empty($equip['ascenseur'])) $confort[] = 'Ascenseur';
        if (!empty($confort)) $equipList[] = 'Confort : ' . implode(', ', $confort);

        // Exterieur
        $ext = [];
        if (!empty($equip['balcon'])) $ext[] = 'Balcon';
        if (!empty($equip['terrasse'])) $ext[] = 'Terrasse';
        if (!empty($equip['jardin'])) $ext[] = 'Jardin';
        if (!empty($equip['parking'])) {
            $type = !empty($equip['parking_type']) ? ' (' . $equip['parking_type'] . ')' : '';
            $ext[] = 'Parking' . $type;
        }
        if (!empty($equip['barbecue'])) $ext[] = 'Barbecue';
        if (!empty($equip['salon_jardin'])) $ext[] = 'Salon de jardin';
        if (!empty($ext)) $equipList[] = 'Exterieur : ' . implode(', ', $ext);

        // Bebe/enfants
        $enfants = [];
        if (!empty($equip['lit_bebe'])) $enfants[] = 'Lit bebe';
        if (!empty($equip['chaise_haute'])) $enfants[] = 'Chaise haute';
        if (!empty($equip['jeux_enfants'])) $enfants[] = 'Jeux enfants';
        if (!empty($enfants)) $equipList[] = 'Enfants : ' . implode(', ', $enfants);

        // Securite
        $securite = [];
        if (!empty($equip['detecteur_fumee'])) $securite[] = 'Detecteur de fumee';
        if (!empty($equip['detecteur_co'])) $securite[] = 'Detecteur CO';
        if (!empty($equip['extincteur'])) $securite[] = 'Extincteur';
        if (!empty($equip['trousse_secours'])) $securite[] = 'Trousse de secours';
        if (!empty($securite)) $equipList[] = 'Securite : ' . implode(', ', $securite);

        // WiFi
        if (!empty($equip['nom_wifi'])) {
            $result['equip_wifi'] = $equip['nom_wifi'];
            if (!empty($equip['code_wifi'])) {
                $result['equip_wifi'] .= ' (code: ' . $equip['code_wifi'] . ')';
            }
        }

        // Lits
        $result['equip_type_lits'] = $equip['type_lits'] ?? '';

        // Construire le texte equipements auto
        $result['auto_equipements'] = implode("\n", $equipList);

        // Regles auto depuis equipements
        $regles = [];
        if (isset($equip['fumer_autorise']) && !$equip['fumer_autorise']) $regles[] = 'Non-fumeur';
        if (isset($equip['animaux_acceptes']) && !$equip['animaux_acceptes']) $regles[] = 'Animaux non acceptes';
        if (!empty($equip['animaux_acceptes']) && !empty($equip['animaux_conditions'])) $regles[] = 'Animaux acceptes : ' . $equip['animaux_conditions'];
        if (isset($equip['fetes_autorisees']) && !$equip['fetes_autorisees']) $regles[] = 'Fetes non autorisees';
        $result['auto_regles_maison'] = implode("\n", $regles);
    }

    // Details personnalises pour contrats de location (prioritaire sur auto)
    $details = null;
    try {
        $stmt = $conn->prepare("SELECT * FROM location_contract_logement_details WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table n'existe pas encore
    }

    // Les details manuels ont priorite, sinon on utilise les auto-generes
    $result['detail_description_logement'] = '';
    $result['detail_equipements'] = '';
    $result['detail_regles_maison'] = '';
    $result['detail_heure_arrivee'] = $equip['heure_checkin'] ?? '16:00';
    $result['detail_heure_depart'] = $equip['heure_checkout'] ?? '10:00';
    $result['detail_depot_garantie'] = '';
    $result['detail_taxe_sejour_par_nuit'] = '';
    $result['detail_conditions_annulation'] = '';
    $result['detail_informations_supplementaires'] = '';

    if ($details) {
        if (!empty($details['description_logement'])) $result['detail_description_logement'] = $details['description_logement'];
        if (!empty($details['equipements'])) {
            $result['detail_equipements'] = $details['equipements'];
        } elseif (!empty($result['auto_equipements'])) {
            $result['detail_equipements'] = $result['auto_equipements'];
        }
        if (!empty($details['regles_maison'])) {
            $result['detail_regles_maison'] = $details['regles_maison'];
        } elseif (!empty($result['auto_regles_maison'])) {
            $result['detail_regles_maison'] = $result['auto_regles_maison'];
        }
        if (!empty($details['heure_arrivee'])) $result['detail_heure_arrivee'] = $details['heure_arrivee'];
        if (!empty($details['heure_depart'])) $result['detail_heure_depart'] = $details['heure_depart'];
        if (!empty($details['depot_garantie'])) $result['detail_depot_garantie'] = $details['depot_garantie'];
        if (!empty($details['taxe_sejour_par_nuit'])) $result['detail_taxe_sejour_par_nuit'] = $details['taxe_sejour_par_nuit'];
        if (!empty($details['conditions_annulation'])) $result['detail_conditions_annulation'] = $details['conditions_annulation'];
        if (!empty($details['informations_supplementaires'])) $result['detail_informations_supplementaires'] = $details['informations_supplementaires'];
    } else {
        // Pas de details manuels, utiliser les auto-generes
        if (!empty($result['auto_equipements'])) $result['detail_equipements'] = $result['auto_equipements'];
        if (!empty($result['auto_regles_maison'])) $result['detail_regles_maison'] = $result['auto_regles_maison'];
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('get_location_logement_infos.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des donnees du logement.']);
}
