<?php
/**
 * FrenchyBot - Moteur chatbot multi-tenant
 * Toutes les fonctions acceptent $chatbot_id pour le filtrage multi-tenant
 */

// ======================================================
// CONNEXION BDD EXTERNE (par chatbot) — GENERIQUE
// ======================================================

$_extPdoCache = [];
$_extProductsCache = [];

/**
 * Obtenir la connexion PDO vers la BDD externe d'un chatbot
 */
function getExternalPdo($chatbot_id) {
    global $pdo, $_extPdoCache;

    if (isset($_extPdoCache[$chatbot_id])) return $_extPdoCache[$chatbot_id];

    $stmt = $pdo->prepare("SELECT ext_db_enabled, ext_db_host, ext_db_name, ext_db_user, ext_db_pass FROM chatbots WHERE id = ?");
    $stmt->execute([$chatbot_id]);
    $cfg = $stmt->fetch();

    if (!$cfg || !$cfg['ext_db_enabled'] || empty($cfg['ext_db_name'])) {
        $_extPdoCache[$chatbot_id] = null;
        return null;
    }

    try {
        $extPdo = new PDO(
            'mysql:host=' . ($cfg['ext_db_host'] ?: 'localhost') . ';dbname=' . $cfg['ext_db_name'] . ';charset=utf8mb4',
            $cfg['ext_db_user'] ?: 'root',
            $cfg['ext_db_pass'] ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $_extPdoCache[$chatbot_id] = $extPdo;
        return $extPdo;
    } catch (PDOException $e) {
        error_log('FrenchyBot ext DB error (chatbot ' . $chatbot_id . '): ' . $e->getMessage());
        $_extPdoCache[$chatbot_id] = null;
        return null;
    }
}

/**
 * Obtenir la config des produits d'un chatbot
 * Retourne un tableau de types de produits avec leur mapping
 *
 * Format ext_db_products JSON :
 * [
 *   {
 *     "type": "maison",           // identifiant du type
 *     "label": "Maisons",         // nom affiche
 *     "table": "modeles",         // table SQL
 *     "col_name": "nom",          // colonne nom du produit
 *     "col_price": "prix_base",   // colonne prix
 *     "col_description": "slogan",// colonne description (optionnel)
 *     "col_location": "",         // colonne localisation (optionnel)
 *     "col_surface": "surface_habitable",  // colonne surface (optionnel)
 *     "col_image": "",            // colonne image URL (optionnel)
 *     "col_category": "nb_etages",// colonne categorie (optionnel)
 *     "col_active": "is_active",  // colonne actif (optionnel, defaut: aucun filtre)
 *     "col_extra": ["nb_chambres","style"],  // colonnes supplementaires a afficher
 *     "search_keywords": ["maison","modele","construire","villa"] // mots-cles pour detecter ce type
 *   }
 * ]
 */
function getProductConfigs($chatbot_id) {
    global $pdo, $_extProductsCache;

    if (isset($_extProductsCache[$chatbot_id])) return $_extProductsCache[$chatbot_id];

    $stmt = $pdo->prepare("SELECT ext_db_products FROM chatbots WHERE id = ?");
    $stmt->execute([$chatbot_id]);
    $json = $stmt->fetchColumn();
    $products = $json ? json_decode($json, true) : [];

    $_extProductsCache[$chatbot_id] = is_array($products) ? $products : [];
    return $_extProductsCache[$chatbot_id];
}

/**
 * Recherche generique de produits dans la BDD externe
 */
function chatbotSearchProducts($chatbot_id, $productType, $criteria = []) {
    $extPdo = getExternalPdo($chatbot_id);
    if (!$extPdo) return [];

    $configs = getProductConfigs($chatbot_id);
    $config = null;
    foreach ($configs as $c) {
        if ($c['type'] === $productType) { $config = $c; break; }
    }
    if (!$config || empty($config['table'])) return [];

    try {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $config['table']);
        $where = [];
        $params = [];

        // Filtre actif
        if (!empty($config['col_active'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_active']);
            $where[] = "$col = 1";
        }

        // Filtre par prix/budget (supporte fourchette "min-max" ou valeur simple)
        $budgetVal = $criteria['budget'] ?? $criteria['budget_terrain'] ?? '';
        if (!empty($budgetVal) && !empty($config['col_price'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_price']);
            if (is_string($budgetVal) && strpos($budgetVal, '-') !== false) {
                $parts = explode('-', $budgetVal);
                $budgetMin = intval($parts[0]);
                $budgetMax = intval($parts[1]);
                if ($budgetMin > 0) {
                    $where[] = "$col >= ?";
                    $params[] = $budgetMin;
                }
                if ($budgetMax > 0 && $budgetMax < 999999) {
                    $where[] = "$col <= ?";
                    $params[] = $budgetMax;
                }
            } else {
                $budgetMax = intval($budgetVal);
                if ($budgetMax > 0 && $budgetMax < 999999) {
                    $where[] = "($col IS NOT NULL AND $col <= ?)";
                    $params[] = intval($budgetMax * 1.1);
                }
            }
        }

        // Filtre par localisation/departement
        if (!empty($criteria['departement']) && !empty($config['col_location'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_location']);
            $where[] = "$col = ?";
            $params[] = $criteria['departement'];
        }

        // Filtre par ville
        if (!empty($criteria['ville']) && !empty($config['col_name'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_name']);
            $where[] = "$col LIKE ?";
            $params[] = '%' . $criteria['ville'] . '%';
        }

        // Exclure les prix invalides (0, 1, etc.)
        if (!empty($config['col_price'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_price']);
            $where[] = "$col > 100";
        }

        // Filtre par surface
        if (!empty($criteria['surface']) && !empty($config['col_surface'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_surface']);
            $where[] = "$col >= ?";
            $params[] = intval($criteria['surface'] * 0.8);
        }

        // Filtre par categorie
        if (!empty($criteria['category']) && !empty($config['col_category'])) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_category']);
            $where[] = "$col = ?";
            $params[] = $criteria['category'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Colonnes a selectionner
        $cols = ['*'];
        $orderBy = !empty($config['col_price']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_price']) . ' DESC' : '1';

        // Compter le total
        $countSql = "SELECT COUNT(*) FROM $table $whereClause";
        $countStmt = $extPdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM $table $whereClause ORDER BY $orderBy LIMIT 6";
        $stmt = $extPdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Fallback sans filtre budget si rien trouve
        if (empty($results) && !empty($criteria['budget'])) {
            $where2 = [];
            $params2 = [];
            if (!empty($config['col_active'])) {
                $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_active']);
                $where2[] = "$col = 1";
            }
            if (!empty($criteria['departement']) && !empty($config['col_location'])) {
                $col = preg_replace('/[^a-zA-Z0-9_]/', '', $config['col_location']);
                $where2[] = "$col = ?";
                $params2[] = $criteria['departement'];
            }
            $whereClause2 = !empty($where2) ? 'WHERE ' . implode(' AND ', $where2) : '';
            $sql2 = "SELECT * FROM $table $whereClause2 ORDER BY $orderBy LIMIT 6";
            $stmt2 = $extPdo->prepare($sql2);
            $stmt2->execute($params2);
            $results = $stmt2->fetchAll();
        }

        return ['results' => $results, 'total' => $totalCount];
    } catch (Exception $e) {
        error_log('FrenchyBot product search error: ' . $e->getMessage());
        return ['results' => [], 'total' => 0];
    }
}

/**
 * Formater les resultats generiques
 */
function chatbotFormatProducts($results, $config, $budget = 0, $totalCount = 0) {
    $label = $config['label'] ?? 'produits';

    if (empty($results)) {
        return "Aucun(e) $label disponible pour ces criteres actuellement.\n\nLaissez vos coordonnees et un conseiller vous contactera !";
    }

    $colName = $config['col_name'] ?? '';
    $colPrice = $config['col_price'] ?? '';
    $colLocation = $config['col_location'] ?? '';
    $colSurface = $config['col_surface'] ?? '';
    $colExtra = $config['col_extra'] ?? [];

    $total = $totalCount ?: count($results);
    $shown = min(count($results), 4);

    if ($total > $shown) {
        $text = "🔥 **" . $total . " " . $label . " disponible(s) !** Voici quelques exemples :\n\n";
    } else {
        $text = "🔍 **" . $total . " " . $label . " trouve(s) :**\n\n";
    }

    foreach (array_slice($results, 0, 4) as $r) {
        $name = $colName && isset($r[$colName]) ? $r[$colName] : 'Produit';
        $text .= "• **$name**";

        $details = [];
        if ($colSurface && !empty($r[$colSurface])) $details[] = $r[$colSurface] . 'm²';
        if ($colLocation && !empty($r[$colLocation])) $details[] = $r[$colLocation];
        foreach ($colExtra as $extraCol) {
            if (!empty($r[$extraCol])) $details[] = $r[$extraCol];
        }
        if (!empty($details)) $text .= " — " . implode(', ', $details);

        if ($colPrice && !empty($r[$colPrice])) {
            $prix = number_format((float)$r[$colPrice], 0, ',', ' ') . ' €';
            $text .= " → $prix";
        }
        $text .= "\n";
    }

    if ($total > $shown) {
        $text .= "\n... et **" . ($total - $shown) . " autres** !\n";
    }

    return $text;
}

/**
 * Detecter quel type de produit l'utilisateur recherche
 */
function chatbotDetectProductType($message, $chatbot_id) {
    $msg = mb_strtolower(trim($message));
    $configs = getProductConfigs($chatbot_id);

    foreach ($configs as $config) {
        $keywords = $config['search_keywords'] ?? [];
        foreach ($keywords as $kw) {
            if (mb_strpos($msg, mb_strtolower($kw)) !== false) {
                return $config;
            }
        }
    }

    // Si un seul type configure, le retourner par defaut
    if (count($configs) === 1) return $configs[0];

    return null;
}

// ======================================================
// CONVERSATION
// ======================================================

function chatbotGetOrCreateConversation($chatbot_id) {
    global $pdo;
    $sid = session_id();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $page = $_SERVER['HTTP_REFERER'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM chatbot_conversations
                          WHERE chatbot_id = ? AND session_id = ? AND is_active = 1
                          AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$chatbot_id, $sid]);
    $conv = $stmt->fetch();

    if ($conv) return array_merge($conv, ['is_new' => false]);

    $pdo->prepare("INSERT INTO chatbot_conversations
                  (chatbot_id, session_id, ip_address, page_source, current_step, data_collected, started_at, last_activity)
                  VALUES (?, ?, ?, ?, 1, '{}', NOW(), NOW())")->execute([$chatbot_id, $sid, $ip, $page]);

    return ['id' => $pdo->lastInsertId(), 'chatbot_id' => $chatbot_id, 'current_step' => 1, 'data_collected' => '{}', 'is_new' => true];
}

function chatbotGetConversation($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbot_conversations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function chatbotUpdateStep($cid, $step) {
    global $pdo;
    $pdo->prepare("UPDATE chatbot_conversations SET current_step = ?, last_activity = NOW() WHERE id = ?")->execute([$step, $cid]);
}

function chatbotUpdateData($cid, $field, $val) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT data_collected FROM chatbot_conversations WHERE id = ?");
    $stmt->execute([$cid]);
    $data = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
    $data[$field] = $val;
    $score = min(count($data) * 12, 100);
    $pdo->prepare("UPDATE chatbot_conversations SET data_collected = ?, completion_score = ?, last_activity = NOW() WHERE id = ?")
        ->execute([json_encode($data, JSON_UNESCAPED_UNICODE), $score, $cid]);
}

function chatbotSaveMessage($cid, $type, $msg, $extra = null) {
    global $pdo;
    $buttons = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
    $pdo->prepare("INSERT INTO chatbot_messages (conversation_id, type, message, buttons, created_at) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$cid, $type, $msg, $buttons]);
    return $pdo->lastInsertId();
}

/**
 * Marquer le dernier message utilisateur comme reconnu
 */
function chatbotMarkRecognized($cid, $intentionKey) {
    global $pdo;
    $pdo->prepare("UPDATE chatbot_messages SET intention_detected = ?
        WHERE conversation_id = ? AND type = 'user' ORDER BY id DESC LIMIT 1")
        ->execute([$intentionKey, $cid]);
}

function chatbotGetHistory($cid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT type, message, buttons FROM chatbot_messages WHERE conversation_id = ? ORDER BY id ASC");
    $stmt->execute([$cid]);
    return $stmt->fetchAll();
}

function chatbotCountUserMessages($cid) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatbot_messages WHERE conversation_id = ? AND type = 'user'");
    $stmt->execute([$cid]);
    return (int) $stmt->fetchColumn();
}

// ======================================================
// SCÉNARIOS COMPLETS PRÉ-ÉTABLIS
// Chaque parcours guide l'utilisateur de A à Z
// et termine TOUJOURS par le formulaire de coordonnées
// ======================================================

function chatbotGetScenario() {
    return [
        // ===================== ACCUEIL =====================
        1 => [
            'type' => 'chips',
            'message' => "Bonjour ! 👋 Je suis l'assistant ORCA.\n\nJe peux vous trouver la maison et le terrain idéal. Qu'est-ce qui vous ferait plaisir ?",
            'options' => [
                ['label' => '🏠 Une maison', 'value' => 'go_maison', 'next' => 10],
                ['label' => '🌿 Un terrain', 'value' => 'go_terrain', 'next' => 20],
                ['label' => '💰 Les prix', 'value' => 'go_prix', 'next' => 30],
                ['label' => '❓ Une question', 'value' => 'go_question', 'next' => 40]
            ]
        ],

        // ===================== PARCOURS MAISON =====================
        10 => [
            'type' => 'chips',
            'message' => "Bonne idée ! 😊 On va trouver le modèle parfait.\n\nVous préférez quel style ?",
            'field' => 'type_maison',
            'options' => [
                ['label' => 'Plain-pied', 'value' => 'plain-pied', 'next' => 11],
                ['label' => 'Avec étage', 'value' => '1-etage', 'next' => 11],
                ['label' => 'Pas sûr', 'value' => 'tous', 'next' => 11]
            ]
        ],
        11 => [
            'type' => 'chips',
            'message' => "Noté ! Et côté chambres, il vous en faut combien ?",
            'field' => 'nb_chambres',
            'options' => [
                ['label' => '2', 'value' => '2', 'next' => 12],
                ['label' => '3', 'value' => '3', 'next' => 12],
                ['label' => '4+', 'value' => '4', 'next' => 12]
            ]
        ],
        12 => [
            'type' => 'chips',
            'message' => "Parfait ! Dernière question : votre budget maison (hors terrain) ?",
            'field' => 'budget',
            'options' => [
                ['label' => '< 155k€', 'value' => '0-155000', 'next' => 'results_maison'],
                ['label' => '155 - 185k€', 'value' => '155000-185000', 'next' => 'results_maison'],
                ['label' => '185 - 220k€', 'value' => '185000-220000', 'next' => 'results_maison'],
                ['label' => '> 220k€', 'value' => '220000-999999', 'next' => 'results_maison']
            ]
        ],

        // ===================== PARCOURS TERRAIN =====================
        20 => [
            'type' => 'chips',
            'message' => "Je peux vous aider à trouver un terrain ! 🌿\n\nDans quel coin cherchez-vous ?",
            'field' => 'departement',
            'options' => [
                ['label' => 'Oise (60)', 'value' => '60', 'next' => 21],
                ['label' => 'Seine-et-Marne (77)', 'value' => '77', 'next' => 21],
                ['label' => 'Val-d\'Oise (95)', 'value' => '95', 'next' => 21],
                ['label' => 'Aisne (02)', 'value' => '02', 'next' => 21],
                ['label' => 'Somme (80)', 'value' => '80', 'next' => 21]
            ]
        ],
        21 => [
            'type' => 'chips',
            'message' => "Top ! Et côté budget terrain, vous êtes sur quelle fourchette ?",
            'field' => 'budget_terrain',
            'options' => [
                ['label' => '< 50k€', 'value' => '0-50000', 'next' => 'results_terrain'],
                ['label' => '50 - 80k€', 'value' => '50000-80000', 'next' => 'results_terrain'],
                ['label' => '80 - 120k€', 'value' => '80000-120000', 'next' => 'results_terrain'],
                ['label' => 'Pas de limite', 'value' => '0-999999', 'next' => 'results_terrain']
            ]
        ],

        // ===================== PARCOURS PRIX =====================
        30 => [
            'type' => 'chips',
            'message' => "💰 **Voici nos prix de départ :**\n\n🏠 **Plain-pied :**\n• Coquelicot 88m² → **145 000 €**\n• Tulipe 95m² → **152 000 €**\n• Hibiscus 102m² → **168 000 €**\n\n🏡 **Avec étage :**\n• Orchidée 110m² → **178 000 €**\n• Lila 120m² → **185 000 €**\n• Magnolia 130m² → **215 000 €**\n\n*Hors terrain. Souvent moins cher qu'un loyer !*",
            'options' => [
                ['label' => '🏠 Choisir un modèle', 'value' => 'go_maison', 'next' => 10],
                ['label' => '🌿 Trouver un terrain', 'value' => 'go_terrain', 'next' => 20],
                ['label' => '📋 Être rappelé', 'value' => 'coord', 'next' => 50],
                ['label' => '❓ Une question', 'value' => 'go_question', 'next' => 40]
            ]
        ],

        // ===================== QUESTIONS LIBRES =====================
        40 => [
            'type' => 'text',
            'message' => "Allez-y, posez-moi votre question ! 😊\n\nJe connais nos maisons, les terrains dispo, les prix, les délais, les aides au financement...",
        ],

        // ===================== COLLECTE COORDONNÉES (conversationnel) =====================
        50 => [
            'type' => 'text',
            'field' => 'prenom',
            'message' => "Super ! Pour vous envoyer tout ça, j'ai juste besoin de votre prénom ? 😊",
            'validation' => 'name',
            'error' => "Hmm, je n'ai pas bien compris votre prénom. Pouvez-vous le retaper ?",
            'next' => 51
        ],
        51 => [
            'type' => 'text',
            'field' => 'nom',
            'message' => "Enchanté {{prenom}} ! Et votre nom de famille ?",
            'validation' => 'name',
            'error' => "Je n'ai pas bien lu, votre nom ?",
            'next' => 52
        ],
        52 => [
            'type' => 'text',
            'field' => 'email',
            'message' => "Votre email ? (pour recevoir les documents)",
            'validation' => 'email',
            'error' => "Cet email ne semble pas valide. Réessayez ? (ex: nom@email.fr)",
            'next' => 53
        ],
        53 => [
            'type' => 'text',
            'field' => 'telephone',
            'message' => "Et votre téléphone ? (pour que le conseiller vous rappelle)",
            'validation' => 'phone',
            'error' => "Le numéro ne semble pas valide. Format : 06 12 34 56 78",
            'next' => 55
        ],

        // ===================== CONFIRMATION =====================
        55 => [
            'type' => 'final',
            'message' => "🎉 **C'est noté {{prenom}} !**\n\n📞 Un conseiller ORCA vous rappelle sous 24h au **{{telephone}}**.\n\nMerci pour votre confiance !",
            'options' => [
                ['label' => '🏠 Voir nos modèles', 'value' => 'voir_modeles', 'action' => 'link', 'url' => '/modeles.php'],
                ['label' => 'Fermer', 'value' => 'fermer', 'action' => 'close']
            ]
        ]
    ];
}

// ======================================================
// RECHERCHE MODÈLES EN BDD
// ======================================================

function chatbotSearchModeles($data, $chatbot_id = null) {
    global $pdo;
    $db = $chatbot_id ? (getExternalPdo($chatbot_id) ?: $pdo) : $pdo;
    $tables = $chatbot_id ? getExternalTables($chatbot_id) : ['modeles' => 'modeles'];
    $tbl = $tables['modeles'];

    try {
        $where = ['is_active = 1'];
        $params = [];

        $type = $data['type_maison'] ?? '';
        if ($type && $type !== 'tous') {
            $where[] = 'nb_etages = ?';
            $params[] = $type;
        }

        $chambres = intval($data['nb_chambres'] ?? 0);
        if ($chambres > 0) {
            $where[] = 'nb_chambres >= ?';
            $params[] = $chambres;
        }

        $budget = intval($data['budget'] ?? 0);
        if ($budget > 0) {
            // Chercher les modèles dans le budget (avec marge de 10%)
            $where[] = '(prix_base IS NOT NULL AND prix_base <= ?)';
            $params[] = intval($budget * 1.1);
        }

        $sql = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                FROM $tbl WHERE " . implode(' AND ', $where) . " ORDER BY prix_base ASC LIMIT 6";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Si aucun résultat avec le budget, chercher TOUS les modèles correspondant au type/chambres
        if (empty($results) && $budget > 0) {
            $where2 = ['is_active = 1'];
            $params2 = [];
            if ($type && $type !== 'tous') { $where2[] = 'nb_etages = ?'; $params2[] = $type; }
            if ($chambres > 0) { $where2[] = 'nb_chambres >= ?'; $params2[] = $chambres; }

            $sql2 = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                     FROM $tbl WHERE " . implode(' AND ', $where2) . " ORDER BY prix_base ASC LIMIT 6";
            $stmt2 = $db->prepare($sql2);
            $stmt2->execute($params2);
            $results = $stmt2->fetchAll();
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Formater les résultats modèles
 */
function chatbotFormatModeles($modeles, $budget = 0) {
    if (empty($modeles)) {
        return "Nous n'avons pas encore de modèle en base, mais nos conseillers ont plein de solutions pour vous !";
    }

    $inBudget = [];
    $aboveBudget = [];

    foreach ($modeles as $m) {
        $prix = $m['prix_base'] ? intval($m['prix_base']) : 0;
        if ($budget > 0 && $prix > $budget) {
            $aboveBudget[] = $m;
        } else {
            $inBudget[] = $m;
        }
    }

    $text = '';

    if (!empty($inBudget)) {
        $text .= "🏠 **" . count($inBudget) . " modèle(s) dans votre budget :**\n\n";
        foreach ($inBudget as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $etage = $m['nb_etages'] === 'plain-pied' ? 'Plain-pied' : 'Avec étage';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch., {$etage}\n";
            $text .= "→ {$prix}\n\n";
        }
    }

    if (!empty($aboveBudget) && empty($inBudget)) {
        $text .= "Aucun modèle exactement dans ce budget, mais voici les plus proches :\n\n";
        foreach ($aboveBudget as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $etage = $m['nb_etages'] === 'plain-pied' ? 'Plain-pied' : 'Avec étage';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch., {$etage}\n";
            $text .= "→ {$prix}\n\n";
        }
        $text .= "💡 *Nos conseillers peuvent adapter les modèles à votre budget !*\n";
    } elseif (!empty($aboveBudget)) {
        $text .= "Et avec un peu plus de budget :\n\n";
        foreach (array_slice($aboveBudget, 0, 2) as $m) {
            $prix = $m['prix_afficher'] ?: number_format($m['prix_base'], 0, ',', ' ') . ' €';
            $text .= "**{$m['nom']}** — {$m['surface_habitable']}m², {$m['nb_chambres']} ch. → {$prix}\n";
        }
        $text .= "\n";
    }

    return $text;
}

// ======================================================
// RECHERCHE TERRAINS EN BDD
// ======================================================

function chatbotSearchTerrains($data, $chatbot_id = null) {
    global $pdo;
    $db = $chatbot_id ? (getExternalPdo($chatbot_id) ?: $pdo) : $pdo;
    $tables = $chatbot_id ? getExternalTables($chatbot_id) : ['terrains' => 'terrains'];
    $tbl = $tables['terrains'];

    try {
        $where = ['is_available = 1'];
        $params = [];

        $dept = $data['departement'] ?? '';
        if ($dept) { $where[] = 'departement = ?'; $params[] = $dept; }

        $budget = intval($data['budget_terrain'] ?? 0);
        if ($budget > 0 && $budget < 999999) { $where[] = 'prix <= ?'; $params[] = $budget; }

        $sql = "SELECT DISTINCT reference, ville, code_postal, departement, surface, prix, est_viabilise, proximite
                FROM $tbl WHERE " . implode(' AND ', $where) . " ORDER BY prix ASC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function chatbotFormatTerrains($terrains) {
    if (empty($terrains)) {
        return "Aucun terrain disponible pour ces critères actuellement.\n\n💡 **Nos conseillers recherchent en permanence de nouveaux terrains.** Laissez vos coordonnées et on vous préviendra dès qu'on a quelque chose !";
    }
    $text = "🌿 **" . count($terrains) . " terrain(s) disponible(s) :**\n\n";
    foreach ($terrains as $t) {
        $prix = number_format($t['prix'], 0, ',', ' ') . ' €';
        $viab = $t['est_viabilise'] ? '✅ Viabilisé' : '⚠️ À viabiliser';
        $text .= "**{$t['ville']}** ({$t['departement']}) — {$t['surface']}m² — {$prix}\n";
        $text .= "{$viab}";
        if ($t['proximite']) $text .= " | 📍 {$t['proximite']}";
        $text .= "\n\n";
    }
    return $text;
}

// ======================================================
// DÉTECTION D'INTENTION — MOTEUR INTELLIGENT A SCORING
// Score basé sur : nombre de matches, longueur des mots-clés,
// phrases multi-mots, priorité, fuzzy matching
// ======================================================

function chatbotDetectIntention($message, $chatbot_id) {
    global $pdo;
    $msg = mb_strtolower(trim($message));
    if (mb_strlen($msg) < 2) return null;

    $candidates = [];

    // 1. Charger les intentions BDD
    try {
        $stmt = $pdo->prepare("SELECT * FROM chatbot_intentions WHERE is_active = 1 AND chatbot_id = ? ORDER BY priority DESC");
        $stmt->execute([$chatbot_id]);
        $dbIntentions = $stmt->fetchAll();
    } catch (Exception $e) {
        $dbIntentions = [];
    }

    foreach ($dbIntentions as $intent) {
        $score = chatbotScoreIntention($msg, $intent['keywords'], $intent['priority']);
        if ($score > 0) {
            $candidates[] = [
                'key' => $intent['intention_key'],
                'response' => $intent['response_text'],
                'action' => $intent['action'] ?? null,
                'score' => $score,
            ];
        }
    }

    // 2. Fallback hardcodé (score de base plus bas)
    $fallback = [
        'prix' => [
            'kw' => 'prix,coût,cout,combien,tarif,cher,€,euro',
            'text' => "💰 **Nos maisons démarrent à 145 000 € (88m², plain-pied).**\n\nGamme complète de 145 000 € à 215 000 € selon la surface et le nombre de chambres.\n\n*Prix hors terrain, hors options. Consultez la grille complète !*",
            'priority' => 5
        ],
        'delai' => [
            'kw' => 'délai,delai,durée,duree,temps construction,quand,livraison',
            'text' => "⏱️ **Délai moyen : 8 à 12 mois** (permis + construction).\n\n• Étude et permis : 2-3 mois\n• Gros oeuvre : 4-5 mois\n• Second oeuvre + finitions : 2-3 mois\n\n**Nos délais sont contractuels et garantis.**",
            'priority' => 5
        ],
        'financement' => [
            'kw' => 'financement,prêt,pret,ptz,crédit,credit,banque,aide financière,mensualité,courtier,partenaire financement,meilleur financement',
            'text' => "💡 **Aides disponibles :**\n\n• **PTZ** : Prêt à Taux Zéro (sous conditions)\n• **Prêt Action Logement** : jusqu'à 40 000 €\n• **TVA réduite** dans certaines zones\n\nNotre partenaire bancaire vous accompagne gratuitement !",
            'priority' => 8
        ],
        'rdv' => [
            'kw' => 'rendez-vous,rdv,rencontrer,agence,visite,appeler',
            'text' => "📅 **Prenons rendez-vous !**\n\n• À l'agence de Longueil-Annel (60)\n• Chez vous (déplacement gratuit)\n• En visioconférence\n\nOuvert du lundi au vendredi, 9h-18h.",
            'priority' => 5
        ],
        'garantie' => [
            'kw' => 'garantie,qualité,norme,assurance,décennale,re2020',
            'text' => "✅ **Garanties ORCA :**\n\n• Garantie décennale (10 ans)\n• Garantie biennale (2 ans)\n• Assurance dommages-ouvrage\n• Norme RE2020\n• Constructeur depuis 1993",
            'priority' => 5
        ],
    ];

    foreach ($fallback as $key => $data) {
        $score = chatbotScoreIntention($msg, $data['kw'], $data['priority']);
        if ($score > 0) {
            $candidates[] = [
                'key' => $key,
                'response' => $data['text'],
                'action' => null,
                'score' => $score * 0.8, // Fallback = score legerement inferieur aux intentions BDD
            ];
        }
    }

    // 3. Trier par score descendant et retourner le meilleur
    if (empty($candidates)) return null;

    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    return $candidates[0];
}

/**
 * Calculer le score d'une intention pour un message
 * Plus le score est eleve, plus l'intention est pertinente
 */
function chatbotScoreIntention($msg, $keywordsStr, $priority = 10) {
    $keywords = array_map('trim', explode(',', mb_strtolower($keywordsStr)));
    $score = 0;
    $matchCount = 0;
    $totalKeywordLength = 0;
    $bestMatchLength = 0;

    foreach ($keywords as $kw) {
        if ($kw === '') continue;

        $matched = false;
        $kwLen = mb_strlen($kw);

        // Match exact (contenu dans le message)
        if (mb_strpos($msg, $kw) !== false) {
            $matched = true;

            // Bonus pour phrase multi-mots (plus specifique)
            $wordCount = count(explode(' ', $kw));
            if ($wordCount >= 3) {
                $score += 30 * $wordCount; // Phrase longue = tres specifique
            } elseif ($wordCount === 2) {
                $score += 20;
            } else {
                $score += 10;
            }

            // Bonus pour longueur du mot-cle (plus long = plus specifique)
            $score += $kwLen;

            // Bonus si le mot-cle est un mot entier (pas un sous-mot)
            // "fin" ne doit pas matcher "financement" si "fin" est un keyword
            if ($kwLen >= 4 || preg_match('/(?:^|\s|[\'"\-])' . preg_quote($kw, '/') . '(?:$|\s|[\'"\-.,!?])/u', $msg)) {
                $score += 5;
            }
        }
        // Fuzzy matching (tolerance fautes de frappe) — seulement pour mots >= 5 chars
        elseif ($kwLen >= 5) {
            $words = preg_split('/[\s\-\']+/u', $msg);
            foreach ($words as $word) {
                $word = trim($word, '.,!?;:');
                if (mb_strlen($word) < 3) continue;
                $distance = levenshtein($word, $kw);
                $threshold = ($kwLen >= 8) ? 2 : 1;
                if ($distance <= $threshold) {
                    $matched = true;
                    $score += max(5, 10 - $distance * 3); // Moins de points pour fuzzy
                    break;
                }
            }
        }

        if ($matched) {
            $matchCount++;
            $totalKeywordLength += $kwLen;
            if ($kwLen > $bestMatchLength) $bestMatchLength = $kwLen;
        }
    }

    if ($matchCount === 0) return 0;

    // Bonus multi-match (plusieurs mots-cles du meme intent matchent)
    if ($matchCount >= 3) $score += 25;
    elseif ($matchCount >= 2) $score += 10;

    // Poids de la priorite (x1 a x2)
    $priorityMultiplier = 1 + ($priority / 20);
    $score = $score * $priorityMultiplier;

    return round($score, 2);
}

// ======================================================
// EXTRACTION INTELLIGENTE DE CRITÈRES DEPUIS UN MESSAGE
// "je veux un terrain de 500m² dans l'oise" → departement=60, surface=500
// ======================================================

function chatbotExtractCriteria($message) {
    $msg = mb_strtolower(trim($message));
    $criteria = [];

    // --- Département (nom ou numéro) ---
    $deptNames = [
        'oise' => '60', 'aisne' => '02', 'somme' => '80',
        'seine-et-marne' => '77', 'seine et marne' => '77',
        'val-d\'oise' => '95', 'val d\'oise' => '95', 'valdoise' => '95',
        'essonne' => '91', 'yvelines' => '78',
        'hauts-de-seine' => '92', 'hauts de seine' => '92',
        'seine-saint-denis' => '93', 'seine saint denis' => '93',
        'val-de-marne' => '94', 'val de marne' => '94',
        'picardie' => '60', 'compiègne' => '60', 'compiegne' => '60',
        'senlis' => '60', 'beauvais' => '60', 'creil' => '60', 'noyon' => '60',
        'meaux' => '77', 'coulommiers' => '77',
        'cergy' => '95', 'pontoise' => '95',
        'laon' => '02', 'soissons' => '02', 'saint-quentin' => '02',
        'amiens' => '80', 'abbeville' => '80',
    ];
    foreach ($deptNames as $name => $code) {
        if (mb_strpos($msg, $name) !== false) {
            $criteria['departement'] = $code;
            break;
        }
    }
    // Numéro de département brut
    if (!isset($criteria['departement']) && preg_match('/\b(02|60|77|78|80|91|92|93|94|95)\b/', $msg, $m)) {
        $criteria['departement'] = $m[1];
    }

    // --- Surface (m²) ---
    if (preg_match('/(\d+)\s*(?:m²|m2|mètres?\s*carrés?|metres?\s*carres?)/i', $msg, $m)) {
        $criteria['surface'] = intval($m[1]);
    }

    // --- Budget / prix ---
    // "200000€", "200 000€", "200k€", "200 000 euros", "budget 200000"
    if (preg_match('/(\d[\d\s.,]*)\s*(?:k€|k\s*euros?|k€)/i', $msg, $m)) {
        $criteria['budget'] = intval(preg_replace('/[\s.,]/', '', $m[1])) * 1000;
    } elseif (preg_match('/(\d[\d\s.,]*)\s*(?:€|euros?)/i', $msg, $m)) {
        $criteria['budget'] = intval(preg_replace('/[\s.,]/', '', $m[1]));
    } elseif (preg_match('/budget\s*(?:de\s*)?(\d[\d\s.,]*)/i', $msg, $m)) {
        $num = intval(preg_replace('/[\s.,]/', '', $m[1]));
        if ($num < 1000) $num *= 1000;
        $criteria['budget'] = $num;
    }

    // --- Nombre de chambres ---
    if (preg_match('/(\d+)\s*(?:chambres?|ch\b|pièces?\s*principales?)/i', $msg, $m)) {
        $criteria['nb_chambres'] = intval($m[1]);
    }

    // --- Type de maison ---
    if (preg_match('/plain[\s-]?pied/i', $msg)) {
        $criteria['type_maison'] = 'plain-pied';
    } elseif (preg_match('/(?:avec\s+)?étage|etage|r\+1/i', $msg)) {
        $criteria['type_maison'] = '1-etage';
    }

    // --- Viabilisé ---
    if (preg_match('/viabilis[ée]/i', $msg)) {
        $criteria['viabilise'] = true;
    }

    // --- Sujet principal (terrain ou maison ?) ---
    if (preg_match('/terrain|parcelle|foncier|constructible/i', $msg)) {
        $criteria['_subject'] = 'terrain';
    } elseif (preg_match('/maison|modèle|modele|construire|villa|pavillon/i', $msg)) {
        $criteria['_subject'] = 'maison';
    }

    // --- Ville (extraire du texte : "à Moyvillers", "terrain moyvillers", "sur Compiègne") ---
    // Pattern 1 : "à/a/sur/dans/vers VILLE" (avec ou sans majuscule)
    if (preg_match('/(?:à|a|sur|dans|vers|près\s*de|pres\s*de|autour\s*de)\s+([a-zà-üA-ZÀ-Ü][a-zà-ü]+(?:[\s-][a-zà-üA-ZÀ-Ü]?[a-zà-ü]+)*)/ui', $message, $m)) {
        $ville = trim($m[1]);
        $excluded = ['bâtir', 'batir', 'vendre', 'louer', 'construire', 'acheter', 'pied', 'étage', 'etage',
                     'moi', 'vous', 'nous', 'lui', 'elle', 'terrain', 'maison', 'budget', 'prix', 'aide'];
        if (!in_array(mb_strtolower($ville), $excluded) && mb_strlen($ville) >= 3) {
            $criteria['ville'] = $ville;
        }
    }
    // Pattern 2 : "terrain VILLE" ou "terrain de/du/a VILLE" (sans preposition obligatoire)
    if (!isset($criteria['ville']) && preg_match('/(?:terrain|parcelle|maison|construire)\s+(?:a\s+|à\s+|de\s+|du\s+|au\s+|aux\s+|sur\s+|dans\s+|vers\s+|près\s+de\s+|pres\s+de\s+)?([a-zà-üA-ZÀ-Ü][a-zà-ü]{2,}(?:[\s-][a-zà-üA-ZÀ-Ü]?[a-zà-ü]+)*)/ui', $message, $m)) {
        $ville = trim($m[1]);
        $excluded = ['bâtir', 'batir', 'vendre', 'louer', 'construire', 'pas', 'cher', 'disponible',
                     'plat', 'viabilise', 'viabilisé', 'grand', 'petit', 'dans', 'sur'];
        if (!in_array(mb_strtolower($ville), $excluded) && mb_strlen($ville) >= 3) {
            $criteria['ville'] = $ville;
        }
    }

    // Pattern 3 : "VILLE terrain" ou "compiegne je cherche un terrain"
    if (!isset($criteria['ville']) && preg_match('/^([a-zà-üA-ZÀ-Ü][a-zà-ü]{2,}(?:[\s-][a-zà-üA-ZÀ-Ü]?[a-zà-ü]+)*)\s+.*(?:terrain|parcelle|maison|construire)/ui', $message, $m)) {
        $ville = trim($m[1]);
        $excluded = ['je', 'un', 'une', 'le', 'la', 'les', 'des', 'mon', 'trouver', 'chercher', 'cherche', 'avoir', 'quel', 'quelle', 'quels'];
        if (!in_array(mb_strtolower($ville), $excluded) && mb_strlen($ville) >= 3) {
            $criteria['ville'] = $ville;
        }
    }

    return $criteria;
}

/**
 * Recherche terrains avec critères enrichis (surface, viabilisé...)
 */
function chatbotSearchTerrainsAdvanced($criteria, $chatbot_id = null) {
    global $pdo;
    $db = $chatbot_id ? (getExternalPdo($chatbot_id) ?: $pdo) : $pdo;
    $tables = $chatbot_id ? getExternalTables($chatbot_id) : ['terrains' => 'terrains'];
    $tbl = $tables['terrains'];

    try {
        $where = ['is_available = 1'];
        $params = [];

        if (!empty($criteria['departement'])) {
            $where[] = 'departement = ?';
            $params[] = $criteria['departement'];
        }
        if (!empty($criteria['budget'])) {
            $where[] = 'prix <= ?';
            $params[] = intval($criteria['budget']);
        }
        if (!empty($criteria['surface'])) {
            $where[] = 'surface >= ?';
            $params[] = intval($criteria['surface'] * 0.8);
        }
        if (!empty($criteria['viabilise'])) {
            $where[] = 'est_viabilise = 1';
        }

        $sql = "SELECT DISTINCT reference, ville, code_postal, departement, surface, prix, est_viabilise, proximite
                FROM $tbl WHERE " . implode(' AND ', $where) . " ORDER BY prix ASC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Recherche modèles avec critères enrichis
 */
function chatbotSearchModelesAdvanced($criteria, $chatbot_id = null) {
    global $pdo;
    $db = $chatbot_id ? (getExternalPdo($chatbot_id) ?: $pdo) : $pdo;
    $tables = $chatbot_id ? getExternalTables($chatbot_id) : ['modeles' => 'modeles'];
    $tbl = $tables['modeles'];

    try {
        $where = ['is_active = 1'];
        $params = [];

        if (!empty($criteria['type_maison']) && $criteria['type_maison'] !== 'tous') {
            $where[] = 'nb_etages = ?';
            $params[] = $criteria['type_maison'];
        }
        if (!empty($criteria['nb_chambres'])) {
            $where[] = 'nb_chambres >= ?';
            $params[] = intval($criteria['nb_chambres']);
        }
        if (!empty($criteria['budget'])) {
            $where[] = '(prix_base IS NOT NULL AND prix_base <= ?)';
            $params[] = intval($criteria['budget'] * 1.1);
        }
        if (!empty($criteria['surface'])) {
            $where[] = 'surface_habitable >= ?';
            $params[] = intval($criteria['surface'] * 0.85);
        }

        $sql = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                FROM $tbl WHERE " . implode(' AND ', $where) . " ORDER BY prix_base ASC LIMIT 6";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Fallback si rien trouvé : enlever le filtre budget
        if (empty($results) && !empty($criteria['budget'])) {
            $where2 = ['is_active = 1'];
            $params2 = [];
            if (!empty($criteria['type_maison']) && $criteria['type_maison'] !== 'tous') { $where2[] = 'nb_etages = ?'; $params2[] = $criteria['type_maison']; }
            if (!empty($criteria['nb_chambres'])) { $where2[] = 'nb_chambres >= ?'; $params2[] = intval($criteria['nb_chambres']); }
            if (!empty($criteria['surface'])) { $where2[] = 'surface_habitable >= ?'; $params2[] = intval($criteria['surface'] * 0.85); }
            $sql2 = "SELECT nom, slug, surface_habitable, nb_chambres, nb_etages, style, prix_base, prix_afficher, slogan
                     FROM $tbl WHERE " . implode(' AND ', $where2) . " ORDER BY prix_base ASC LIMIT 6";
            $stmt2 = $db->prepare($sql2);
            $stmt2->execute($params2);
            $results = $stmt2->fetchAll();
        }

        return $results;
    } catch (Exception $e) {
        return [];
    }
}

// ======================================================
// VALIDATION & NORMALISATION
// ======================================================

function chatbotValidateInput($value, $type) {
    $value = trim($value);
    switch ($type) {
        case 'name':  return mb_strlen($value) >= 2 && preg_match('/^[\p{L}\s\-\']+$/u', $value);
        case 'email': return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        case 'phone': return preg_match('/^(0[1-9])[\s.-]?(\d{2}[\s.-]?){4}$/', $value);
        default: return true;
    }
}

function chatbotNormalizePhone($phone) {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 10) {
        return implode(' ', str_split($digits, 2));
    }
    return $phone;
}

// ======================================================
// CRÉATION DE LEAD
// ======================================================

function chatbotCreateLead($conversation_id, $data, $chatbot_id) {
    global $pdo;

    try {
        $terrainPrevu = in_array(($data['terrain'] ?? ''), ['oui', '1', 'true'], true) ? 1 : 0;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $pageSource = $_SERVER['HTTP_REFERER'] ?? 'chatbot';

        $stmt = $pdo->prepare("INSERT INTO leads
            (chatbot_id, nom, prenom, email, telephone, departement, surface_souhaitee, budget_estime,
             terrain_prevu, type_demande, source, page_source, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'devis', 'chatbot', ?, ?, NOW())");

        $stmt->execute([
            $chatbot_id,
            $data['nom'] ?? '', $data['prenom'] ?? '', $data['email'] ?? '',
            $data['telephone'] ?? '', $data['departement'] ?? '',
            $data['surface'] ?? '', $data['budget'] ?? '',
            $terrainPrevu, $pageSource, $ip
        ]);

        $lead_id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE chatbot_conversations SET lead_id = ?, is_active = 0, ended_at = NOW() WHERE id = ?")
            ->execute([$lead_id, $conversation_id]);

        // Notification email admin
        chatbotNotifyAdmin($lead_id, $data, $chatbot_id);

        return ['lead_id' => $lead_id, 'success' => true];
    } catch (Exception $e) {
        error_log('Chatbot lead error: ' . $e->getMessage());
        return ['lead_id' => null, 'success' => false, 'error' => $e->getMessage()];
    }
}

// ======================================================
// NOTIFICATION EMAIL ADMIN (point 2)
// ======================================================

function chatbotNotifyAdmin($lead_id, $data, $chatbot_id) {
    global $pdo;

    try {
        // Charger le chatbot pour ses paramètres
        $stmt = $pdo->prepare("SELECT * FROM chatbots WHERE id = ?");
        $stmt->execute([$chatbot_id]);
        $chatbot = $stmt->fetch();
        if (!$chatbot) return;

        // Vérifier que les notifs sont activées
        if (!$chatbot['email_notifications']) return;

        $adminEmail = $chatbot['notification_email'] ?? '';
        if (empty($adminEmail)) return;

        $prenom = htmlspecialchars($data['prenom'] ?? '');
        $nom = htmlspecialchars($data['nom'] ?? '');
        $email = htmlspecialchars($data['email'] ?? '');
        $tel = htmlspecialchars($data['telephone'] ?? '');
        $dept = htmlspecialchars($data['departement'] ?? '-');
        $budget = htmlspecialchars($data['budget'] ?? '-');
        $surface = htmlspecialchars($data['surface'] ?? '-');

        $chatbotName = htmlspecialchars($chatbot['name'] ?? 'FrenchyBot');
        $subject = "🏠 Nouveau lead {$chatbotName} : {$prenom} {$nom}";

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:#1a5653;color:white;padding:20px;border-radius:8px 8px 0 0;'>
                <h2 style='margin:0;'>🏠 Nouveau lead via {$chatbotName}</h2>
            </div>
            <div style='background:white;padding:25px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr><td style='padding:8px 0;color:#888;width:120px;'>Prénom :</td><td style='padding:8px 0;font-weight:bold;'>{$prenom}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Nom :</td><td style='padding:8px 0;font-weight:bold;'>{$nom}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Email :</td><td style='padding:8px 0;'><a href='mailto:{$email}'>{$email}</a></td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Téléphone :</td><td style='padding:8px 0;font-weight:bold;font-size:16px;'><a href='tel:{$tel}'>{$tel}</a></td></tr>
                    <tr><td colspan='2' style='border-top:1px solid #eee;padding-top:12px;'></td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Département :</td><td style='padding:8px 0;'>{$dept}</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Surface :</td><td style='padding:8px 0;'>{$surface} m²</td></tr>
                    <tr><td style='padding:8px 0;color:#888;'>Budget :</td><td style='padding:8px 0;'>{$budget} €</td></tr>
                </table>
                <div style='margin-top:20px;text-align:center;'>
                    <a href='" . FB_BASE_URL . "/admin/lead-view.php?id={$lead_id}' style='display:inline-block;padding:12px 30px;background:#1a5653;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Voir le lead dans l'admin</a>
                </div>
            </div>
        </div>";

        sendEmail($adminEmail, $subject, $body);
    } catch (Exception $e) {
        error_log('Chatbot email notification error: ' . $e->getMessage());
    }
}

// ======================================================
// A/B TESTING (point 6) - récupérer la variante pour l'accueil
// ======================================================

function chatbotGetABVariant($conversation_id, $chatbot_id) {
    global $pdo;

    try {
        // Chercher un test actif sur le message de bienvenue pour ce chatbot
        $stmt = $pdo->prepare("SELECT * FROM chatbot_ab_tests WHERE chatbot_id = ? AND status = 'active' AND test_type = 'welcome_message' LIMIT 1");
        $stmt->execute([$chatbot_id]);
        $test = $stmt->fetch();
        if (!$test) return null;

        // Assigner aléatoirement A ou B
        $variant = (mt_rand(0, 1) === 0) ? 'A' : 'B';

        // Sauvegarder dans la conversation
        $pdo->prepare("UPDATE chatbot_conversations SET ab_test_id = ?, ab_variant = ? WHERE id = ?")
            ->execute([$test['id'], $variant, $conversation_id]);

        return [
            'test_id' => $test['id'],
            'variant' => $variant,
            'message' => ($variant === 'A') ? $test['variant_a_value'] : $test['variant_b_value']
        ];
    } catch (Exception $e) {
        return null;
    }
}

// ======================================================
// FOLLOWUP / RELANCE (point 8)
// ======================================================

/**
 * Planifier un followup pour une conversation abandonnée
 * Appelé quand un visiteur a commencé mais n'a pas laissé ses coordonnées
 */
function chatbotScheduleFollowup($conversation_id, $chatbot_id) {
    global $pdo;

    try {
        $conv = chatbotGetConversation($conversation_id);
        if (!$conv || $conv['lead_id']) return; // Déjà converti

        $data = json_decode($conv['data_collected'] ?? '{}', true) ?: [];
        if (empty($data)) return; // Rien collecté

        // Vérifier qu'il n'y a pas déjà un followup
        $stmt = $pdo->prepare("SELECT id FROM chatbot_followups WHERE conversation_id = ? AND status = 'pending'");
        $stmt->execute([$conversation_id]);
        if ($stmt->fetch()) return;

        // Déterminer la priorité
        $score = intval($conv['completion_score'] ?? 0);
        $priority = 'low';
        if ($score >= 50) $priority = 'high';
        elseif ($score >= 25) $priority = 'medium';

        // Planifier dans 24h
        $followupDate = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $prenom = $data['prenom'] ?? 'visiteur';
        $chatbot = getChatbotById($chatbot_id);
        $chatbotName = $chatbot['name'] ?? 'FrenchyBot';
        $subject = "Votre projet {$chatbotName} - On reprend où on en était ?";
        $content = chatbotBuildFollowupEmail($data, $chatbot);

        $pdo->prepare("INSERT INTO chatbot_followups
            (chatbot_id, conversation_id, lead_data, followup_date, priority, status, email_subject, email_content, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())")
            ->execute([
                $chatbot_id,
                $conversation_id,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $followupDate,
                $priority,
                $subject,
                $content
            ]);
    } catch (Exception $e) {
        error_log('Chatbot followup error: ' . $e->getMessage());
    }
}

/**
 * Construire l'email de relance
 */
function chatbotBuildFollowupEmail($data, $chatbot = null) {
    $prenom = htmlspecialchars($data['prenom'] ?? '');
    $greeting = $prenom ? "Bonjour {$prenom}," : "Bonjour,";

    $details = '';
    if (!empty($data['type_maison'])) $details .= "<li>Type : " . htmlspecialchars($data['type_maison']) . "</li>";
    if (!empty($data['nb_chambres'])) $details .= "<li>Chambres : " . htmlspecialchars($data['nb_chambres']) . "</li>";
    if (!empty($data['budget'])) $details .= "<li>Budget : " . number_format(intval($data['budget']), 0, ',', ' ') . " €</li>";
    if (!empty($data['departement'])) $details .= "<li>Département : " . htmlspecialchars($data['departement']) . "</li>";

    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:#1a5653;color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>
            <h2 style='margin:0;'>" . htmlspecialchars($chatbot['name'] ?? 'FrenchyBot') . "</h2>
        </div>
        <div style='background:white;padding:30px;border:1px solid #eee;'>
            <p>{$greeting}</p>
            <p>Vous avez commencé à explorer nos maisons sur notre site. Nous avons noté vos critères :</p>
            " . ($details ? "<ul style='line-height:1.8;'>{$details}</ul>" : "") . "
            <p><strong>Un conseiller peut vous rappeler gratuitement</strong> pour répondre à toutes vos questions et vous accompagner dans votre projet.</p>
            <div style='text-align:center;margin:25px 0;'>
                <a href='" . FB_BASE_URL . "/contact' style='display:inline-block;padding:14px 35px;background:#1a5653;color:white;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px;'>Être rappelé gratuitement</a>
            </div>
            <p style='color:#888;font-size:13px;'>Cet email a été envoyé suite à votre visite. Si vous ne souhaitez plus recevoir de messages, ignorez simplement cet email.</p>
        </div>
    </div>";
}

/**
 * Traiter les followups en attente (à appeler via CRON)
 * Usage: php -r "require '/var/www/orca/includes/config.php'; chatbotProcessFollowups();"
 */
function chatbotProcessFollowups() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT f.*, c.data_collected, b.name AS chatbot_name, b.notification_email
            FROM chatbot_followups f
            JOIN chatbot_conversations c ON f.conversation_id = c.id
            JOIN chatbots b ON f.chatbot_id = b.id
            WHERE f.status = 'pending' AND f.followup_date <= NOW() AND b.is_active = 1
            LIMIT 10");
        $followups = $stmt->fetchAll();

        foreach ($followups as $f) {
            $data = json_decode($f['lead_data'] ?? $f['data_collected'] ?? '{}', true) ?: [];
            $email = $data['email'] ?? '';

            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $sent = sendEmail($email, $f['email_subject'], $f['email_content']);
                $status = $sent ? 'sent' : 'pending';
            } else {
                $status = 'cancelled'; // Pas d'email → on annule
            }

            $pdo->prepare("UPDATE chatbot_followups SET status = ?, sent_at = NOW() WHERE id = ?")
                ->execute([$status, $f['id']]);
        }

        return count($followups);
    } catch (Exception $e) {
        error_log('Chatbot followup process error: ' . $e->getMessage());
        return 0;
    }
}
