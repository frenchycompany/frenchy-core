<?php
/**
 * Integration OpenAI pour le chatbot FrenchyBot
 * Envoie les questions des voyageurs a GPT avec le contexte du logement/sejour
 */

/**
 * Appel a l'API OpenAI Chat Completions
 * @param string $systemPrompt Instructions systeme
 * @param array $messages Historique [{role, content}, ...]
 * @return array{success: bool, message?: string, error?: string}
 */
function callOpenAI(string $systemPrompt, array $messages, ?PDO $pdo = null): array
{
    $apiKey = $pdo ? botSetting($pdo, 'openai_api_key') : env('OPENAI_API_KEY', '');
    if (!$apiKey) {
        return ['success' => false, 'error' => 'Cle API OpenAI non configuree (allez dans FrenchyBot > Configuration)'];
    }

    $model = $pdo ? botSetting($pdo, 'openai_model', 'gpt-4o-mini') : env('OPENAI_MODEL', 'gpt-4o-mini');

    $payload = [
        'model' => $model,
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        ),
        'temperature' => 0.7,
        'max_tokens' => 500,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'cURL: ' . $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'message' => $decoded['choices'][0]['message']['content'],
        ];
    }

    $errorMsg = $decoded['error']['message'] ?? "HTTP $httpCode";
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Construit le system prompt du chatbot avec le contexte du sejour
 */
function buildSystemPrompt(PDO $pdo, array $hubData): string
{
    $equip = $hubData['equipements'] ?? [];

    // Charger la base de connaissances du logement
    $knowledge = '';
    try {
        $stmt = $pdo->prepare("
            SELECT title, content FROM bot_knowledge
            WHERE active = 1 AND (logement_id IS NULL OR logement_id = ?)
            ORDER BY logement_id IS NULL ASC, sort_order ASC
        ");
        $stmt->execute([$hubData['logement_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $knowledge = "\n\n--- BASE DE CONNAISSANCES ---";
            foreach ($rows as $r) {
                $knowledge .= "\n[{$r['title']}] {$r['content']}";
            }
        }
    } catch (\PDOException $e) {}

    // Charger le planning menage/maintenance pour ce logement
    $planningInfo = '';
    try {
        $stmt = $pdo->prepare("
            SELECT date, type_intervention, statut, heure_debut, heure_fin, note
            FROM planning
            WHERE logement_id = ? AND date >= CURDATE() AND date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY date ASC LIMIT 5
        ");
        $stmt->execute([$hubData['logement_id']]);
        $plannings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($plannings) {
            $planningInfo = "\n\nPLANNING PREVU (7 prochains jours) :";
            foreach ($plannings as $p) {
                $planningInfo .= "\n- " . date('d/m', strtotime($p['date'])) . " : {$p['type_intervention']} ({$p['statut']})";
                if ($p['heure_debut']) $planningInfo .= " de {$p['heure_debut']}";
                if ($p['heure_fin']) $planningInfo .= " a {$p['heure_fin']}";
                if ($p['note']) $planningInfo .= " — {$p['note']}";
            }
        }
    } catch (\PDOException $e) {}

    // Instructions personnalisees depuis l'admin
    $botName = botSetting($pdo, 'bot_name', 'Frenchy');
    $customInstructions = botSetting($pdo, 'bot_instructions', '');

    $prompt = "Tu es $botName, l'assistant virtuel de Frenchy Conciergerie. Tu aides les voyageurs pendant leur sejour.

REGLES IMPORTANTES :
- Reponds toujours en francais, de maniere amicale et professionnelle
- Sois concis (2-3 phrases max sauf si on te demande des details)
- Si tu ne connais pas la reponse, dis-le honnetement et propose de contacter l'equipe
- Ne donne JAMAIS d'informations que tu n'as pas dans le contexte ci-dessous
- Utilise des emojis avec parcimonie (1-2 max par message)
- Tu peux utiliser du texte simple (pas de HTML ni markdown)";

    if ($customInstructions) {
        $prompt .= "\n\nINSTRUCTIONS DU PROPRIETAIRE :\n$customInstructions";
    }

    $prompt .= "

CONTEXTE DU SEJOUR :
- Voyageur : {$hubData['prenom']} {$hubData['nom']}
- Logement : {$hubData['nom_du_logement']}
- Adresse : " . ($hubData['adresse'] ?? 'Non renseignee') . "
- Arrivee : {$hubData['date_arrivee']} a " . ($hubData['heure_arrivee'] ?: ($equip['heure_checkin'] ?? '16:00')) . "
- Depart : {$hubData['date_depart']} a " . ($hubData['heure_depart'] ?: ($equip['heure_checkout'] ?? '10:00')) . "
- Nombre de voyageurs : " . ($hubData['nb_adultes'] ?? '?') . " adulte(s)" . (($hubData['nb_enfants'] ?? 0) ? ", {$hubData['nb_enfants']} enfant(s)" : '') . (($hubData['nb_bebes'] ?? 0) ? ", {$hubData['nb_bebes']} bebe(s)" : '') . "
- Plateforme : " . ($hubData['plateforme'] ?? 'Non renseignee') . "

ACCES & SECURITE :";

    $access = [];
    if (!empty($equip['code_porte'])) $access[] = "Code porte : {$equip['code_porte']}";
    if (!empty($equip['code_boite_cles'])) $access[] = "Code boite a cles : {$equip['code_boite_cles']}";
    if (!empty($equip['nom_wifi'])) $access[] = "Wifi : reseau \"{$equip['nom_wifi']}\" / mot de passe : " . ($equip['code_wifi'] ?? 'Non renseigne');
    if (!empty($equip['etage'])) $access[] = "Etage : {$equip['etage']}" . (!empty($equip['ascenseur']) ? ' (ascenseur disponible)' : ' (pas d\'ascenseur)');
    $prompt .= $access ? "\n- " . implode("\n- ", $access) : "\n- Aucune info d'acces renseignee";

    $prompt .= "\n\nLE LOGEMENT :";
    $logInfo = [];
    if (!empty($equip['superficie_m2'])) $logInfo[] = "Surface : {$equip['superficie_m2']} m²";
    if (!empty($equip['nombre_chambres'])) $logInfo[] = "Chambres : {$equip['nombre_chambres']}";
    if (!empty($equip['nombre_salles_bain'])) $logInfo[] = "Salles de bain : {$equip['nombre_salles_bain']}";
    if (!empty($equip['nombre_couchages'])) $logInfo[] = "Couchages : {$equip['nombre_couchages']}";
    if (!empty($equip['type_lits'])) $logInfo[] = "Lits : {$equip['type_lits']}";
    if (!empty($equip['canape_convertible'])) $logInfo[] = "Canape convertible : oui" . (!empty($equip['guide_canape_convertible']) ? " (guide : {$equip['guide_canape_convertible']})" : '');
    $prompt .= $logInfo ? "\n- " . implode("\n- ", $logInfo) : '';

    // Cuisine
    $cuisine = [];
    if (!empty($equip['machine_cafe_type'])) $cuisine[] = "Machine a cafe : {$equip['machine_cafe_type']}" . (!empty($equip['guide_machine_cafe']) ? " — {$equip['guide_machine_cafe']}" : '');
    if (!empty($equip['bouilloire'])) $cuisine[] = "Bouilloire";
    if (!empty($equip['grille_pain'])) $cuisine[] = "Grille-pain";
    if (!empty($equip['micro_ondes'])) $cuisine[] = "Micro-ondes" . (!empty($equip['guide_micro_ondes']) ? " — {$equip['guide_micro_ondes']}" : '');
    if (!empty($equip['four'])) $cuisine[] = "Four" . (!empty($equip['guide_four']) ? " — {$equip['guide_four']}" : '');
    if (!empty($equip['plaque_cuisson'])) $cuisine[] = "Plaque de cuisson" . (!empty($equip['plaque_cuisson_type']) ? " ({$equip['plaque_cuisson_type']})" : '') . (!empty($equip['guide_plaque_cuisson']) ? " — {$equip['guide_plaque_cuisson']}" : '');
    if (!empty($equip['lave_vaisselle'])) $cuisine[] = "Lave-vaisselle" . (!empty($equip['guide_lave_vaisselle']) ? " — {$equip['guide_lave_vaisselle']}" : '');
    if (!empty($equip['refrigerateur'])) $cuisine[] = "Refrigerateur";
    if (!empty($equip['congelateur'])) $cuisine[] = "Congelateur";
    if (!empty($equip['ustensiles_cuisine'])) $cuisine[] = "Ustensiles de cuisine fournis";
    if ($cuisine) {
        $prompt .= "\n\nCUISINE :\n- " . implode("\n- ", $cuisine);
    }

    // Linge & menage
    $linge = [];
    if (!empty($equip['machine_laver'])) $linge[] = "Machine a laver" . (!empty($equip['guide_machine_laver']) ? " — {$equip['guide_machine_laver']}" : '');
    if (!empty($equip['seche_linge'])) $linge[] = "Seche-linge" . (!empty($equip['guide_seche_linge']) ? " — {$equip['guide_seche_linge']}" : '');
    if (!empty($equip['fer_repasser'])) $linge[] = "Fer a repasser";
    if (!empty($equip['linge_lit_fourni'])) $linge[] = "Linge de lit fourni";
    if (!empty($equip['serviettes_fournies'])) $linge[] = "Serviettes fournies";
    if (!empty($equip['aspirateur'])) $linge[] = "Aspirateur disponible";
    if (!empty($equip['produits_menage'])) $linge[] = "Produits menagers fournis";
    if ($linge) {
        $prompt .= "\n\nLINGE & MENAGE :\n- " . implode("\n- ", $linge);
    }

    // Divertissement
    $divert = [];
    if (!empty($equip['tv'])) {
        $tvInfo = "TV";
        if (!empty($equip['tv_pouces'])) $tvInfo .= " {$equip['tv_pouces']}\"";
        if (!empty($equip['tv_type'])) $tvInfo .= " ({$equip['tv_type']})";
        if (!empty($equip['guide_tv'])) $tvInfo .= " — {$equip['guide_tv']}";
        $divert[] = $tvInfo;
    }
    if (!empty($equip['chaines_tv'])) $divert[] = "Chaines : {$equip['chaines_tv']}";
    if (!empty($equip['netflix'])) $divert[] = "Netflix disponible";
    if (!empty($equip['amazon_prime'])) $divert[] = "Amazon Prime Video";
    if (!empty($equip['disney_plus'])) $divert[] = "Disney+";
    if (!empty($equip['molotov_tv'])) $divert[] = "Molotov TV";
    if (!empty($equip['enceinte_bluetooth'])) $divert[] = "Enceinte Bluetooth";
    if (!empty($equip['console_jeux'])) $divert[] = "Console : {$equip['console_jeux_type']}";
    if (!empty($equip['livres'])) $divert[] = "Livres disponibles";
    if (!empty($equip['jeux_societe'])) $divert[] = "Jeux de societe";
    if ($divert) {
        $prompt .= "\n\nDIVERTISSEMENT :\n- " . implode("\n- ", $divert);
    }

    // Confort & climat
    $confort = [];
    if (!empty($equip['climatisation'])) $confort[] = "Climatisation" . (!empty($equip['guide_climatisation']) ? " — {$equip['guide_climatisation']}" : '');
    if (!empty($equip['chauffage'])) $confort[] = "Chauffage" . (!empty($equip['chauffage_type']) ? " ({$equip['chauffage_type']})" : '') . (!empty($equip['guide_chauffage']) ? " — {$equip['guide_chauffage']}" : '');
    if (!empty($equip['ventilateur'])) $confort[] = "Ventilateur";
    if (!empty($equip['seche_cheveux'])) $confort[] = "Seche-cheveux";
    if (!empty($equip['produits_toilette'])) $confort[] = "Produits de toilette fournis";
    if (!empty($equip['baignoire'])) $confort[] = "Baignoire";
    if (!empty($equip['douche'])) $confort[] = "Douche";
    if ($confort) {
        $prompt .= "\n\nCONFORT :\n- " . implode("\n- ", $confort);
    }

    // Exterieur
    $ext = [];
    if (!empty($equip['parking'])) $ext[] = "Parking" . (!empty($equip['parking_type']) ? " ({$equip['parking_type']})" : '');
    if (!empty($equip['balcon'])) $ext[] = "Balcon";
    if (!empty($equip['terrasse'])) $ext[] = "Terrasse";
    if (!empty($equip['jardin'])) $ext[] = "Jardin";
    if (!empty($equip['barbecue'])) $ext[] = "Barbecue";
    if (!empty($equip['salon_jardin'])) $ext[] = "Salon de jardin";
    if ($ext) {
        $prompt .= "\n\nEXTERIEUR :\n- " . implode("\n- ", $ext);
    }

    // Bebe & enfants
    $bebe = [];
    if (!empty($equip['lit_bebe'])) $bebe[] = "Lit bebe disponible";
    if (!empty($equip['chaise_haute'])) $bebe[] = "Chaise haute";
    if (!empty($equip['barriere_securite'])) $bebe[] = "Barriere de securite";
    if (!empty($equip['jeux_enfants'])) $bebe[] = "Jeux pour enfants";
    if ($bebe) {
        $prompt .= "\n\nBEBE & ENFANTS :\n- " . implode("\n- ", $bebe);
    }

    // Regles
    $regles = [];
    if (isset($equip['animaux_acceptes'])) $regles[] = $equip['animaux_acceptes'] ? "Animaux acceptes" . (!empty($equip['animaux_conditions']) ? " ({$equip['animaux_conditions']})" : '') : "Animaux NON acceptes";
    if (isset($equip['fumer_autorise'])) $regles[] = $equip['fumer_autorise'] ? "Fumer autorise" : "NON fumeur";
    if (isset($equip['fetes_autorisees'])) $regles[] = $equip['fetes_autorisees'] ? "Fetes autorisees" : "Fetes NON autorisees";
    if ($regles) {
        $prompt .= "\n\nREGLES :\n- " . implode("\n- ", $regles);
    }

    // Securite
    $secu = [];
    if (!empty($equip['detecteur_fumee'])) $secu[] = "Detecteur de fumee";
    if (!empty($equip['detecteur_co'])) $secu[] = "Detecteur CO";
    if (!empty($equip['extincteur'])) $secu[] = "Extincteur";
    if (!empty($equip['trousse_secours'])) $secu[] = "Trousse de secours";
    if (!empty($equip['coffre_fort'])) $secu[] = "Coffre-fort";
    if ($secu) {
        $prompt .= "\n\nSECURITE :\n- " . implode("\n- ", $secu);
    }

    // Instructions arrivee/depart
    if (!empty($equip['instructions_arrivee'])) {
        $prompt .= "\n\nINSTRUCTIONS D'ARRIVEE :\n{$equip['instructions_arrivee']}";
    }
    if (!empty($equip['instructions_depart'])) {
        $prompt .= "\n\nINSTRUCTIONS DE DEPART :\n{$equip['instructions_depart']}";
    }
    if (!empty($equip['notes_supplementaires'])) {
        $prompt .= "\n\nNOTES SUPPLEMENTAIRES :\n{$equip['notes_supplementaires']}";
    }

    // Infos utiles
    if (!empty($equip['numeros_urgence'])) {
        $prompt .= "\n\nNUMEROS UTILES :\n{$equip['numeros_urgence']}";
    }
    if (!empty($equip['infos_quartier'])) {
        $prompt .= "\n\nINFOS QUARTIER :\n{$equip['infos_quartier']}";
    }

    // Planning
    $prompt .= $planningInfo;

    // Base de connaissances
    $prompt .= $knowledge;

    return $prompt;
}

/**
 * Charge l'historique de conversation pour un hub_token
 */
function loadChatHistory(PDO $pdo, int $hubTokenId, int $limit = 20): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT role, content FROM bot_conversations
            WHERE hub_token_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$hubTokenId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($rows); // Chronologique
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Sauvegarde un message dans l'historique
 */
function saveChatMessage(PDO $pdo, int $hubTokenId, int $reservationId, string $role, string $content): void
{
    try {
        $pdo->prepare("
            INSERT INTO bot_conversations (hub_token_id, reservation_id, role, content)
            VALUES (?, ?, ?, ?)
        ")->execute([$hubTokenId, $reservationId, $role, $content]);
    } catch (\PDOException $e) {
        error_log('saveChatMessage error: ' . $e->getMessage());
    }
}
