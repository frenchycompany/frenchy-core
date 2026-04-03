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
function callOpenAI(string $systemPrompt, array $messages): array
{
    $apiKey = env('OPENAI_API_KEY', '');
    if (!$apiKey) {
        return ['success' => false, 'error' => 'OPENAI_API_KEY non configure'];
    }

    $model = env('OPENAI_MODEL', 'gpt-4o-mini');

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
            SELECT content FROM bot_knowledge
            WHERE active = 1 AND (logement_id IS NULL OR logement_id = ?)
            ORDER BY logement_id IS NULL ASC, sort_order ASC
        ");
        $stmt->execute([$hubData['logement_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($rows) {
            $knowledge = "\n\n--- BASE DE CONNAISSANCES ---\n" . implode("\n\n", $rows);
        }
    } catch (\PDOException $e) {
        // Table peut ne pas exister encore
    }

    $prompt = "Tu es l'assistant virtuel de Frenchy Conciergerie. Tu aides les voyageurs pendant leur sejour.

REGLES IMPORTANTES :
- Reponds toujours en francais, de maniere amicale et professionnelle
- Sois concis (2-3 phrases max sauf si on te demande des details)
- Si tu ne connais pas la reponse, dis-le honnêtement et propose de contacter l'equipe
- Ne donne JAMAIS d'informations que tu n'as pas dans le contexte
- Utilise des emojis avec parcimonie (1-2 max par message)
- Tu peux utiliser du texte simple (pas de HTML ni markdown)

CONTEXTE DU SEJOUR :
- Voyageur : {$hubData['prenom']} {$hubData['nom']}
- Logement : {$hubData['nom_du_logement']}
- Adresse : " . ($hubData['adresse'] ?? 'Non renseignee') . "
- Arrivee : {$hubData['date_arrivee']} a " . ($hubData['heure_arrivee'] ?: ($equip['heure_checkin'] ?? '16:00')) . "
- Depart : {$hubData['date_depart']} a " . ($hubData['heure_depart'] ?: ($equip['heure_checkout'] ?? '10:00')) . "
- Plateforme : " . ($hubData['plateforme'] ?? 'Non renseignee') . "

INFORMATIONS PRATIQUES :";

    if (!empty($equip['code_porte'])) {
        $prompt .= "\n- Code porte : {$equip['code_porte']}";
    }
    if (!empty($equip['code_boite_cles'])) {
        $prompt .= "\n- Code boite a cles : {$equip['code_boite_cles']}";
    }
    if (!empty($equip['nom_wifi'])) {
        $prompt .= "\n- Wifi : {$equip['nom_wifi']} / Mot de passe : " . ($equip['code_wifi'] ?? 'Non renseigne');
    }
    if (!empty($equip['instructions_arrivee'])) {
        $prompt .= "\n- Instructions arrivee : {$equip['instructions_arrivee']}";
    }
    if (!empty($equip['instructions_depart'])) {
        $prompt .= "\n- Instructions depart : {$equip['instructions_depart']}";
    }
    if (!empty($equip['numeros_urgence'])) {
        $prompt .= "\n- Numeros utiles : {$equip['numeros_urgence']}";
    }
    if (!empty($equip['infos_quartier'])) {
        $prompt .= "\n- Infos quartier : {$equip['infos_quartier']}";
    }

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
