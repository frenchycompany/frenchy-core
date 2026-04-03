<?php
/**
 * Fonctions metier du HUB sejour FrenchyBot
 */

/**
 * Genere un token unique crypto-safe pour une reservation
 */
function generateHubToken(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Cree ou recupere le token HUB pour une reservation
 * @return string Le token
 */
function getOrCreateHubToken(PDO $pdo, int $reservationId, int $logementId): string
{
    // Token existant ?
    $stmt = $pdo->prepare("SELECT token FROM hub_tokens WHERE reservation_id = ? AND active = 1");
    $stmt->execute([$reservationId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return $existing;

    // Creer
    $token = generateHubToken();
    $stmt = $pdo->prepare("
        INSERT INTO hub_tokens (reservation_id, logement_id, token)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE token = VALUES(token), active = 1
    ");
    $stmt->execute([$reservationId, $logementId, $token]);
    return $token;
}

/**
 * Charge les donnees completes du HUB a partir d'un token
 * @return array|null
 */
function loadHubData(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            ht.id AS hub_token_id,
            ht.reservation_id,
            ht.logement_id,
            ht.access_count,
            r.prenom, r.nom, r.telephone, r.email,
            r.date_arrivee, r.heure_arrivee, r.date_depart, r.heure_depart,
            r.nb_adultes, r.nb_enfants, r.nb_bebes, r.plateforme,
            l.nom_du_logement, l.adresse, l.nombre_de_personnes
        FROM hub_tokens ht
        JOIN reservation r ON ht.reservation_id = r.id
        JOIN liste_logements l ON ht.logement_id = l.id
        WHERE ht.token = ? AND ht.active = 1
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) return null;

    // Charger les equipements/infos pratiques du logement
    try {
        $eq = $pdo->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
        $eq->execute([$data['logement_id']]);
        $data['equipements'] = $eq->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException $e) {
        $data['equipements'] = [];
    }

    // Charger les upsells disponibles
    $ups = $pdo->prepare("
        SELECT * FROM upsells
        WHERE active = 1 AND (logement_id IS NULL OR logement_id = ?)
        ORDER BY sort_order ASC
    ");
    $ups->execute([$data['logement_id']]);
    $data['upsells'] = $ups->fetchAll(PDO::FETCH_ASSOC);

    // Mettre a jour le compteur d'acces
    $pdo->prepare("UPDATE hub_tokens SET access_count = access_count + 1, last_accessed_at = NOW() WHERE token = ?")
        ->execute([$token]);

    return $data;
}

/**
 * Enregistre une interaction sur le HUB
 */
function trackInteraction(PDO $pdo, int $hubTokenId, int $reservationId, string $actionType, ?array $actionData = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hub_interactions (hub_token_id, reservation_id, action_type, action_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $hubTokenId,
            $reservationId,
            $actionType,
            $actionData ? json_encode($actionData) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (\PDOException $e) {
        error_log('trackInteraction error: ' . $e->getMessage());
    }
}

/**
 * Trouve la reservation active pour un logement (pour QR code)
 * Active = checkin aujourd'hui ou en cours de sejour
 */
function findActiveReservation(PDO $pdo, int $logementId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, logement_id, prenom, nom, date_arrivee, date_depart
        FROM reservation
        WHERE logement_id = ?
          AND statut = 'confirmée'
          AND date_arrivee <= CURDATE()
          AND date_depart >= CURDATE()
        ORDER BY date_arrivee DESC
        LIMIT 1
    ");
    $stmt->execute([$logementId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Genere l'URL publique du HUB
 */
function getHubUrl(string $token, ?PDO $pdo = null): string
{
    $baseUrl = $pdo ? botSetting($pdo, 'app_url', 'https://gestion.frenchyconciergerie.fr') : env('APP_URL', 'https://gestion.frenchyconciergerie.fr');
    return rtrim($baseUrl, '/') . '/frenchybot/hub/?id=' . $token;
}

/**
 * Calcule les dates cles du sejour
 */
function getSejourInfo(array $data): array
{
    $now = new \DateTime();
    $arrivee = new \DateTime($data['date_arrivee']);
    $depart = new \DateTime($data['date_depart']);
    $nbNuits = $arrivee->diff($depart)->days;

    $status = 'unknown';
    if ($now < $arrivee) {
        $status = 'before';
    } elseif ($now >= $arrivee && $now <= $depart) {
        $status = 'during';
    } else {
        $status = 'after';
    }

    return [
        'nb_nuits' => $nbNuits,
        'status' => $status,
        'jours_avant_arrivee' => $now < $arrivee ? $now->diff($arrivee)->days : 0,
        'jour_sejour' => $status === 'during' ? $arrivee->diff($now)->days + 1 : 0,
    ];
}

/**
 * Actions rapides disponibles pour le chatbot transactionnel
 */
function getQuickActions(): array
{
    return [
        [
            'id' => 'access_problem',
            'label' => 'Probleme d\'acces',
            'icon' => 'fa-key',
            'color' => 'danger',
            'response' => 'Nous avons bien recu votre signalement. Un membre de l\'equipe va vous recontacter dans les plus brefs delais. En attendant, verifiez les instructions d\'acces ci-dessus.',
            'notify' => true,
        ],
        [
            'id' => 'wifi_help',
            'label' => 'Probleme wifi',
            'icon' => 'fa-wifi',
            'color' => 'warning',
            'response' => 'Essayez de redemarrer la box internet (debranchez 30 secondes puis rebranchez). Si le probleme persiste, nous reviendrons vers vous.',
            'notify' => true,
        ],
        [
            'id' => 'cleaning_request',
            'label' => 'Question menage',
            'icon' => 'fa-broom',
            'color' => 'info',
            'response' => 'Votre demande a ete transmise a notre equipe. Nous vous recontactons rapidement.',
            'notify' => true,
        ],
        [
            'id' => 'checkout_info',
            'label' => 'Infos depart',
            'icon' => 'fa-door-open',
            'color' => 'secondary',
            'response' => null, // affiche les instructions de depart
            'notify' => false,
        ],
        [
            'id' => 'other',
            'label' => 'Autre question',
            'icon' => 'fa-comment-dots',
            'color' => 'primary',
            'response' => 'Votre message a ete transmis a notre equipe. Nous vous repondrons dans les plus brefs delais.',
            'notify' => true,
        ],
    ];
}
