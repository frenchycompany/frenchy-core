<?php
/**
 * API de suggestions de reponses SMS intelligentes
 * Analyse les messages et propose des reponses contextuelles
 *
 * Adapté depuis raspberry-pi/web/pages/sms_ai_suggest.php
 * Split queries: reservation/villes/equipements sur RPi, liste_logements sur VPS
 */

require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/rpi_db.php';
require_once __DIR__ . '/../db/connection.php'; // VPS $conn pour liste_logements

header('Content-Type: application/json; charset=utf-8');

$pdoRpi = getRpiPdo();

// Recuperer les parametres
$message = $_POST['message'] ?? $_GET['message'] ?? '';
$sender = $_POST['sender'] ?? $_GET['sender'] ?? '';
$reservation_id = $_POST['reservation_id'] ?? $_GET['reservation_id'] ?? null;

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

// Charger les infos de reservation si disponibles
$reservation = null;
$equipements = null;
$ville_logement = null;
$ville_id = null;
$ville_recommandations = [];
$debug_info = [
    'reservation_id_param' => $reservation_id,
    'sender_param' => $sender,
    'pdo_ok' => true
];

if ($reservation_id) {
    try {
        // 1) Reservation depuis RPi
        $stmt = $pdoRpi->prepare("SELECT * FROM reservation WHERE id = ?");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_info['query_by_id'] = true;
        $debug_info['reservation_found'] = !empty($reservation);

        if ($reservation && !empty($reservation['logement_id'])) {
            $debug_info['logement_id'] = $reservation['logement_id'];

            // 2) Logement depuis VPS (liste_logements)
            $stmt_vps = $conn->prepare("SELECT id, nom_du_logement, adresse, ville_id FROM liste_logements WHERE id = ?");
            $stmt_vps->execute([$reservation['logement_id']]);
            $logement_data = $stmt_vps->fetch(PDO::FETCH_ASSOC);

            if ($logement_data) {
                $reservation['nom_du_logement'] = $logement_data['nom_du_logement'];
                $reservation['adresse'] = $logement_data['adresse'];
                $reservation['ville_id'] = $logement_data['ville_id'];
            }

            // 3) Ville depuis RPi
            if (!empty($reservation['ville_id'])) {
                $stmt_v = $pdoRpi->prepare("SELECT id, nom FROM villes WHERE id = ?");
                $stmt_v->execute([$reservation['ville_id']]);
                $ville_data = $stmt_v->fetch(PDO::FETCH_ASSOC);
                if ($ville_data) {
                    $reservation['ville_nom'] = $ville_data['nom'];
                    $ville_id = $ville_data['id'];
                    $ville_logement = $ville_data['nom'];
                }
            }

            $debug_info['ville_id_from_query'] = $reservation['ville_id'] ?? null;
            $debug_info['ville_nom_from_query'] = $reservation['ville_nom'] ?? null;

            // 4) Equipements depuis RPi
            $stmt_eq = $pdoRpi->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
            $stmt_eq->execute([$reservation['logement_id']]);
            $equipements = $stmt_eq->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $debug_info['error_reservation_id'] = $e->getMessage();
    }
} elseif ($sender) {
    // Essayer de trouver la reservation par le numero
    try {
        $phone_clean = preg_replace('/[^0-9+]/', '', $sender);
        $phone_0 = preg_replace('/^\+33/', '0', $phone_clean);

        // 1) Reservation depuis RPi
        $stmt = $pdoRpi->prepare("
            SELECT *
            FROM reservation
            WHERE (telephone = ? OR telephone = ?)
            AND date_depart >= CURDATE()
            ORDER BY date_arrivee ASC
            LIMIT 1
        ");
        $stmt->execute([$phone_clean, $phone_0]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservation && !empty($reservation['logement_id'])) {
            // 2) Logement depuis VPS
            $stmt_vps = $conn->prepare("SELECT id, nom_du_logement, adresse, ville_id FROM liste_logements WHERE id = ?");
            $stmt_vps->execute([$reservation['logement_id']]);
            $logement_data = $stmt_vps->fetch(PDO::FETCH_ASSOC);

            if ($logement_data) {
                $reservation['nom_du_logement'] = $logement_data['nom_du_logement'];
                $reservation['adresse'] = $logement_data['adresse'];
                $reservation['ville_id'] = $logement_data['ville_id'];
            }

            // 3) Ville depuis RPi
            if (!empty($reservation['ville_id'])) {
                $stmt_v = $pdoRpi->prepare("SELECT id, nom FROM villes WHERE id = ?");
                $stmt_v->execute([$reservation['ville_id']]);
                $ville_data = $stmt_v->fetch(PDO::FETCH_ASSOC);
                if ($ville_data) {
                    $reservation['ville_nom'] = $ville_data['nom'];
                    $ville_id = $ville_data['id'];
                    $ville_logement = $ville_data['nom'];
                }
            }

            // 4) Equipements depuis RPi
            $stmt_eq = $pdoRpi->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
            $stmt_eq->execute([$reservation['logement_id']]);
            $equipements = $stmt_eq->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Continuer sans les infos
    }
}

// Charger les recommandations de la ville si disponible
$recs = [];

if ($ville_id) {
    try {
        $stmt = $pdoRpi->prepare("
            SELECT categorie, nom, description, adresse, telephone, site_web, prix_indicatif, note_interne
            FROM ville_recommandations
            WHERE ville_id = ? AND actif = 1
            ORDER BY categorie, ordre, nom
            LIMIT 30
        ");
        $stmt->execute([$ville_id]);
        $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table n'existe peut-etre pas encore
    }
} elseif ($ville_logement) {
    try {
        $stmt = $pdoRpi->prepare("SELECT id FROM villes WHERE LOWER(nom) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($ville_logement)]);
        $ville_found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ville_found) {
            $ville_id = $ville_found['id'];
            $stmt = $pdoRpi->prepare("
                SELECT categorie, nom, description, adresse, telephone, site_web, prix_indicatif, note_interne
                FROM ville_recommandations
                WHERE ville_id = ? AND actif = 1
                ORDER BY categorie, ordre, nom
                LIMIT 30
            ");
            $stmt->execute([$ville_id]);
            $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Ignorer les erreurs
    }
}

// Organiser les recommandations par categorie
if (!empty($recs)) {
    $category_map = [
        'restaurant' => 'restaurants',
        'activite' => 'activites',
        'partenaire' => 'partenaires'
    ];

    foreach ($recs as $rec) {
        $cat = $rec['categorie'];
        $mapped_cat = $category_map[$cat] ?? $cat;
        if (!isset($ville_recommandations[$mapped_cat])) {
            $ville_recommandations[$mapped_cat] = [];
        }
        $ville_recommandations[$mapped_cat][] = $rec;
    }
}

// Patterns de detection et reponses associees
$patterns = [
    ['keywords' => ['wifi', 'wi-fi', 'internet', 'code wifi', 'mot de passe wifi', 'password wifi', 'connexion'], 'category' => 'wifi', 'priority' => 10],
    ['keywords' => ['arrivee', 'arriver', 'check-in', 'checkin', 'heure arrivee', 'quelle heure', 'entrer', 'rentrer', 'acces', 'cle', 'clef', 'boite a cle', 'digicode', 'code porte', 'code entree'], 'category' => 'checkin', 'priority' => 9],
    ['keywords' => ['depart', 'partir', 'check-out', 'checkout', 'heure depart', 'quitter', 'rendre cle', 'fin sejour'], 'category' => 'checkout', 'priority' => 9],
    ['keywords' => ['parking', 'garer', 'voiture', 'stationnement', 'place', 'garage'], 'category' => 'parking', 'priority' => 8],
    ['keywords' => ['menage', 'draps', 'serviettes', 'linge', 'propre', 'nettoyer', 'machine a laver', 'lave-linge'], 'category' => 'menage', 'priority' => 7],
    ['keywords' => ['cafe', 'cafetiere', 'nespresso', 'bouilloire', 'micro-onde', 'four', 'cuisine', 'ustensile', 'casserole', 'poele'], 'category' => 'cuisine', 'priority' => 6],
    ['keywords' => ['tv', 'television', 'tele', 'netflix', 'chaine', 'telecommande', 'amazon', 'disney'], 'category' => 'tv', 'priority' => 6],
    ['keywords' => ['chauffage', 'chaud', 'froid', 'climatisation', 'clim', 'temperature', 'radiateur', 'thermostat'], 'category' => 'chauffage', 'priority' => 7],
    ['keywords' => ['probleme', 'panne', 'casse', 'fonctionne pas', 'marche pas', 'urgence', 'aide', 'bloque', 'coincé'], 'category' => 'probleme', 'priority' => 10],
    ['keywords' => ['merci', 'super', 'genial', 'parfait', 'excellent', 'bien passe', 'satisfait'], 'category' => 'remerciement', 'priority' => 3],
    ['keywords' => ['retard', 'tard', 'arriver plus tard', 'decaler', 'reporter'], 'category' => 'retard', 'priority' => 8],
    ['keywords' => ['adresse', 'ou se trouve', 'localisation', 'gps', 'itineraire', 'trouver'], 'category' => 'adresse', 'priority' => 8],
    ['keywords' => ['restaurant', 'resto', 'manger', 'diner', 'dejeuner', 'ou manger', 'bonne table', 'recommandation', 'recommander', 'conseil', 'conseiller', 'suggestion', 'activite', 'visiter', 'faire', 'sortir', 'bar', 'boire un verre'], 'category' => 'recommandation', 'priority' => 7],
    ['keywords' => ['taxi', 'uber', 'vtc', 'transport', 'bus', 'metro', 'tramway', 'gare', 'aeroport', 'navette'], 'category' => 'transport', 'priority' => 6],
    ['keywords' => ['supermarche', 'courses', 'magasin', 'commerce', 'boulangerie', 'pharmacie', 'epicerie', 'acheter'], 'category' => 'commerces', 'priority' => 5],
    ['keywords' => ['a quelle heure', 'quand', 'horaire', 'disponible', 'possible de', 'peut-on'], 'category' => 'horaires', 'priority' => 5]
];

// Analyser le message
$message_lower = mb_strtolower($message, 'UTF-8');
$message_lower = str_replace(['é', 'è', 'ê', 'ë'], 'e', $message_lower);
$message_lower = str_replace(['à', 'â', 'ä'], 'a', $message_lower);
$message_lower = str_replace(['ù', 'û', 'ü'], 'u', $message_lower);
$message_lower = str_replace(['ô', 'ö'], 'o', $message_lower);
$message_lower = str_replace(['î', 'ï'], 'i', $message_lower);
$message_lower = str_replace(['ç'], 'c', $message_lower);

$detected_categories = [];

foreach ($patterns as $pattern) {
    foreach ($pattern['keywords'] as $keyword) {
        if (strpos($message_lower, $keyword) !== false) {
            $detected_categories[$pattern['category']] = $pattern['priority'];
            break;
        }
    }
}

// Trier par priorite
arsort($detected_categories);

// Generer les suggestions
$suggestions = [];

// Informations contextuelles
$client_name = $reservation['prenom'] ?? '';
$logement_name = $reservation['nom_du_logement'] ?? '';
$date_arrivee = $reservation['date_arrivee'] ?? '';
$date_depart = $reservation['date_depart'] ?? '';

// WiFi info
$wifi_name = $equipements['wifi_nom'] ?? '';
$wifi_code = $equipements['wifi_code'] ?? '';

// Acces info
$code_porte = $equipements['code_porte'] ?? '';
$code_boite_cles = $equipements['code_boite_cles'] ?? '';
$instructions_arrivee = $equipements['instructions_arrivee'] ?? '';

// Horaires
$heure_checkin = $equipements['heure_checkin'] ?? '15:00';
$heure_checkout = $equipements['heure_checkout'] ?? '11:00';

// Parking
$parking_disponible = $equipements['parking'] ?? false;
$parking_instructions = $equipements['parking_instructions'] ?? '';

// Generer les reponses selon les categories detectees
foreach (array_keys($detected_categories) as $category) {
    $suggestion = generateSuggestion($category, [
        'client_name' => $client_name,
        'logement_name' => $logement_name,
        'wifi_name' => $wifi_name,
        'wifi_code' => $wifi_code,
        'code_porte' => $code_porte,
        'code_boite_cles' => $code_boite_cles,
        'instructions_arrivee' => $instructions_arrivee,
        'heure_checkin' => $heure_checkin,
        'heure_checkout' => $heure_checkout,
        'parking_disponible' => $parking_disponible,
        'parking_instructions' => $parking_instructions,
        'equipements' => $equipements,
        'reservation' => $reservation,
        'ville' => $ville_logement,
        'ville_recommandations' => $ville_recommandations
    ]);

    if ($suggestion) {
        $suggestions[] = $suggestion;
    }
}

// Si aucune categorie detectee, proposer une reponse generique
if (empty($suggestions)) {
    $suggestions[] = [
        'category' => 'general',
        'label' => 'Reponse generale',
        'text' => "Bonjour" . ($client_name ? " $client_name" : "") . ", merci pour votre message. Je reviens vers vous rapidement.",
        'confidence' => 0.3
    ];
}

echo json_encode([
    'success' => true,
    'message_analyzed' => $message,
    'detected_categories' => array_keys($detected_categories),
    'suggestions' => $suggestions,
    'context' => [
        'has_reservation' => !empty($reservation),
        'has_equipements' => !empty($equipements),
        'logement' => $logement_name,
        'ville_id' => $ville_id,
        'ville_nom' => $ville_logement,
        'nb_recommandations' => count($recs),
        'categories_reco' => array_keys($ville_recommandations)
    ],
    'debug' => $debug_info
], JSON_UNESCAPED_UNICODE);

/**
 * Genere une suggestion de reponse selon la categorie
 */
function generateSuggestion($category, $ctx) {
    $greeting = $ctx['client_name'] ? "Bonjour {$ctx['client_name']}" : "Bonjour";

    switch ($category) {
        case 'wifi':
            if ($ctx['wifi_name'] && $ctx['wifi_code']) {
                return [
                    'category' => 'wifi',
                    'label' => 'Code WiFi',
                    'text' => "$greeting, voici les informations WiFi :\nNom du reseau : {$ctx['wifi_name']}\nMot de passe : {$ctx['wifi_code']}",
                    'confidence' => 0.95
                ];
            } else {
                return [
                    'category' => 'wifi',
                    'label' => 'WiFi (a completer)',
                    'text' => "$greeting, voici les informations WiFi :\nNom du reseau : [NOM_WIFI]\nMot de passe : [CODE_WIFI]",
                    'confidence' => 0.5
                ];
            }

        case 'checkin':
            $response = "$greeting, ";
            $parts = [];

            if ($ctx['heure_checkin']) {
                $parts[] = "l'arrivee est possible a partir de {$ctx['heure_checkin']}";
            }
            if ($ctx['code_porte']) {
                $parts[] = "le code de la porte est : {$ctx['code_porte']}";
            }
            if ($ctx['code_boite_cles']) {
                $parts[] = "les cles sont dans la boite a cles, code : {$ctx['code_boite_cles']}";
            }
            if ($ctx['instructions_arrivee']) {
                $parts[] = $ctx['instructions_arrivee'];
            }

            if (!empty($parts)) {
                $response .= implode(". ", $parts) . ".";
                return ['category' => 'checkin', 'label' => 'Instructions arrivee', 'text' => $response, 'confidence' => 0.9];
            } else {
                return ['category' => 'checkin', 'label' => 'Arrivee (a completer)', 'text' => "$greeting, l'arrivee est prevue a partir de 15h. Je vous enverrai les instructions d'acces avant votre arrivee.", 'confidence' => 0.4];
            }

        case 'checkout':
            $response = "$greeting, ";
            if ($ctx['heure_checkout']) {
                $response .= "le depart doit se faire avant {$ctx['heure_checkout']}. ";
            }
            $response .= "Merci de laisser les cles dans le logement et de fermer la porte en partant.";
            if (isset($ctx['equipements']['instructions_depart']) && $ctx['equipements']['instructions_depart']) {
                $response .= " " . $ctx['equipements']['instructions_depart'];
            }
            return ['category' => 'checkout', 'label' => 'Instructions depart', 'text' => $response, 'confidence' => 0.85];

        case 'parking':
            if ($ctx['parking_disponible']) {
                $response = "$greeting, une place de parking est disponible. ";
                if ($ctx['parking_instructions']) {
                    $response .= $ctx['parking_instructions'];
                }
                return ['category' => 'parking', 'label' => 'Info parking', 'text' => $response, 'confidence' => 0.9];
            } else {
                return ['category' => 'parking', 'label' => 'Parking (pas dispo)', 'text' => "$greeting, malheureusement il n'y a pas de parking prive. Vous trouverez des places de stationnement dans les rues environnantes.", 'confidence' => 0.7];
            }

        case 'menage':
            $response = "$greeting, ";
            $eq = $ctx['equipements'] ?? [];
            $items = [];
            if (!empty($eq['linge_fourni'])) { $items[] = "les draps et serviettes sont fournis"; }
            if (!empty($eq['machine_a_laver'])) { $items[] = "une machine a laver est a disposition"; }
            if (!empty($items)) {
                $response .= implode(", ", $items) . ".";
            } else {
                $response .= "le linge de lit et les serviettes sont fournis.";
            }
            return ['category' => 'menage', 'label' => 'Info linge/menage', 'text' => $response, 'confidence' => 0.8];

        case 'cuisine':
            $response = "$greeting, la cuisine est equipee avec ";
            $eq = $ctx['equipements'] ?? [];
            $items = [];
            if (!empty($eq['type_cafetiere'])) { $items[] = "une cafetiere " . $eq['type_cafetiere']; }
            if (!empty($eq['bouilloire'])) { $items[] = "une bouilloire"; }
            if (!empty($eq['micro_ondes'])) { $items[] = "un micro-ondes"; }
            if (!empty($eq['four'])) { $items[] = "un four"; }
            if (!empty($eq['lave_vaisselle'])) { $items[] = "un lave-vaisselle"; }
            if (!empty($items)) {
                $response .= implode(", ", $items) . ".";
            } else {
                $response = "$greeting, la cuisine est entierement equipee pour preparer vos repas.";
            }
            return ['category' => 'cuisine', 'label' => 'Equipements cuisine', 'text' => $response, 'confidence' => 0.8];

        case 'tv':
            $response = "$greeting, ";
            $eq = $ctx['equipements'] ?? [];
            $items = [];
            if (!empty($eq['tv'])) {
                $tv_info = "une TV";
                if (!empty($eq['tv_pouces'])) { $tv_info .= " " . $eq['tv_pouces'] . " pouces"; }
                $items[] = $tv_info;
            }
            if (!empty($eq['netflix'])) { $items[] = "Netflix"; }
            if (!empty($eq['amazon_prime'])) { $items[] = "Amazon Prime"; }
            if (!empty($eq['disney_plus'])) { $items[] = "Disney+"; }
            if (!empty($items)) {
                $response .= "le logement dispose de " . implode(", ", $items) . ".";
            } else {
                $response .= "le logement dispose d'une TV avec les chaines classiques.";
            }
            return ['category' => 'tv', 'label' => 'Info TV/Streaming', 'text' => $response, 'confidence' => 0.8];

        case 'chauffage':
            return ['category' => 'chauffage', 'label' => 'Chauffage/Clim', 'text' => "$greeting, le chauffage/climatisation se controle via le thermostat situe dans le salon. N'hesitez pas si vous avez besoin d'aide pour le regler.", 'confidence' => 0.6];

        case 'probleme':
            return ['category' => 'probleme', 'label' => 'Reponse probleme', 'text' => "$greeting, je suis desole d'apprendre que vous rencontrez un probleme. Pouvez-vous me donner plus de details ? Je fais le necessaire au plus vite.", 'confidence' => 0.9];

        case 'remerciement':
            return ['category' => 'remerciement', 'label' => 'Reponse remerciement', 'text' => "$greeting, merci beaucoup pour votre message ! Je suis ravi que tout se passe bien. N'hesitez pas si vous avez besoin de quoi que ce soit.", 'confidence' => 0.85];

        case 'retard':
            return ['category' => 'retard', 'label' => 'Reponse retard', 'text' => "$greeting, pas de probleme pour votre retard. Tenez-moi informe de votre heure d'arrivee estimee. Bonne route !", 'confidence' => 0.85];

        case 'adresse':
            $response = "$greeting, ";
            if (!empty($ctx['reservation']['adresse'])) {
                $response .= "voici l'adresse exacte : {$ctx['reservation']['adresse']}.";
            } else if (!empty($ctx['logement_name'])) {
                $response .= "le logement \"{$ctx['logement_name']}\" se trouve a l'adresse que je vous ai communiquee lors de la reservation.";
            } else {
                $response .= "je vous envoie l'adresse exacte par message.";
            }
            return ['category' => 'adresse', 'label' => 'Info adresse', 'text' => $response, 'confidence' => 0.7];

        case 'recommandation':
            $eq = $ctx['equipements'] ?? [];
            $ville_recs = $ctx['ville_recommandations'] ?? [];
            $ville = $ctx['ville'] ?? '';
            $response = "$greeting, ";
            $confidence = 0.75;

            if (!empty($ville_recs['restaurants'])) {
                $restos = array_slice($ville_recs['restaurants'], 0, 3);
                $resto_list = [];
                foreach ($restos as $r) {
                    $item = "- " . $r['nom'];
                    if (!empty($r['description'])) $item .= " (" . $r['description'] . ")";
                    if (!empty($r['prix_indicatif'])) $item .= " " . $r['prix_indicatif'];
                    if (!empty($r['adresse'])) $item .= " - " . $r['adresse'];
                    $resto_list[] = $item;
                }
                $response .= "voici mes recommandations de restaurants" . ($ville ? " a $ville" : "") . " :\n" . implode("\n", $resto_list);
                $confidence = 0.92;
            } elseif (!empty($eq['restaurants_recommandes'])) {
                $response .= "voici mes recommandations de restaurants : {$eq['restaurants_recommandes']}";
                $confidence = 0.85;
            } elseif (!empty($ville_recs['activites'])) {
                $acts = array_slice($ville_recs['activites'], 0, 3);
                $act_list = [];
                foreach ($acts as $a) {
                    $item = "- " . $a['nom'];
                    if (!empty($a['description'])) $item .= " (" . $a['description'] . ")";
                    if (!empty($a['adresse'])) $item .= " - " . $a['adresse'];
                    $act_list[] = $item;
                }
                $response .= "voici quelques activites a faire" . ($ville ? " a $ville" : "") . " :\n" . implode("\n", $act_list);
                $confidence = 0.90;
            } elseif (!empty($eq['activites_recommandees'])) {
                $response .= "voici quelques activites a faire dans le coin : {$eq['activites_recommandees']}";
                $confidence = 0.80;
            } elseif (!empty($ville_recs['partenaires'])) {
                $parts = array_slice($ville_recs['partenaires'], 0, 3);
                $part_list = [];
                foreach ($parts as $p) {
                    $item = "- " . $p['nom'];
                    if (!empty($p['description'])) $item .= " : " . $p['description'];
                    $part_list[] = $item;
                }
                $response .= "voici quelques partenaires et services que je recommande" . ($ville ? " a $ville" : "") . " :\n" . implode("\n", $part_list);
                $confidence = 0.85;
            } else {
                $response .= "je vous recommande de consulter les avis sur Google Maps pour trouver de bons restaurants" . ($ville ? " a $ville" : " a proximite") . ". N'hesitez pas si vous souhaitez des suggestions plus personnalisees !";
                $confidence = 0.50;
            }

            return ['category' => 'recommandation', 'label' => 'Recommandations' . ($ville ? " $ville" : ""), 'text' => $response, 'confidence' => $confidence];

        case 'transport':
            $eq = $ctx['equipements'] ?? [];
            $ville_recs = $ctx['ville_recommandations'] ?? [];
            $ville = $ctx['ville'] ?? '';
            $response = "$greeting, ";
            $confidence = 0.70;

            if (!empty($ville_recs['transports'])) {
                $transports = array_slice($ville_recs['transports'], 0, 3);
                $trans_list = [];
                foreach ($transports as $t) {
                    $item = "- " . $t['nom'];
                    if ($t['description']) $item .= " : " . $t['description'];
                    $trans_list[] = $item;
                }
                $response .= "voici les transports disponibles" . ($ville ? " a $ville" : "") . " :\n" . implode("\n", $trans_list);
                $confidence = 0.90;
            } elseif (!empty($eq['transports_proximite'])) {
                $response .= "voici les transports a proximite : {$eq['transports_proximite']}";
                $confidence = 0.85;
            } else {
                $response .= "pour les transports, vous pouvez utiliser les applications Uber ou les taxis locaux. La gare/arret de bus le plus proche est accessible a pied.";
            }

            return ['category' => 'transport', 'label' => 'Info transports', 'text' => $response, 'confidence' => $confidence];

        case 'commerces':
            $eq = $ctx['equipements'] ?? [];
            $ville_recs = $ctx['ville_recommandations'] ?? [];
            $ville = $ctx['ville'] ?? '';
            $response = "$greeting, ";
            $confidence = 0.70;

            if (!empty($ville_recs['commerces'])) {
                $commerces = array_slice($ville_recs['commerces'], 0, 3);
                $comm_list = [];
                foreach ($commerces as $c) {
                    $item = "- " . $c['nom'];
                    if ($c['adresse']) $item .= " (" . $c['adresse'] . ")";
                    $comm_list[] = $item;
                }
                $response .= "voici les commerces a proximite" . ($ville ? " a $ville" : "") . " :\n" . implode("\n", $comm_list);
                $confidence = 0.90;
            } elseif (!empty($eq['commerces_proximite'])) {
                $response .= "voici les commerces a proximite : {$eq['commerces_proximite']}";
                $confidence = 0.85;
            } else {
                $response .= "vous trouverez un supermarche et des commerces de proximite a quelques minutes a pied du logement.";
            }

            return ['category' => 'commerces', 'label' => 'Commerces proches', 'text' => $response, 'confidence' => $confidence];

        case 'horaires':
            $response = "$greeting, ";
            if ($ctx['heure_checkin'] && $ctx['heure_checkout']) {
                $response .= "les horaires sont : arrivee a partir de {$ctx['heure_checkin']}, depart avant {$ctx['heure_checkout']}. Si vous avez besoin d'un horaire different, n'hesitez pas a me le faire savoir.";
            } else {
                $response .= "je reste disponible pour repondre a vos questions. Pouvez-vous me preciser votre demande ?";
            }
            return ['category' => 'horaires', 'label' => 'Info horaires', 'text' => $response, 'confidence' => 0.6];

        default:
            return null;
    }
}
