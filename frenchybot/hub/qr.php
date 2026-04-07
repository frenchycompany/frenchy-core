<?php
/**
 * Redirection QR Code → HUB de la reservation active
 * URL : /frenchybot/hub/qr.php?logement=ID
 *
 * Si reservation active → redirige vers le HUB
 * Sinon → page generique du logement
 */

require_once __DIR__ . '/../../ionos/gestion/includes/env_loader.php';
require_once __DIR__ . '/../../ionos/gestion/db/connection.php';
require_once __DIR__ . '/../includes/hub-functions.php';

$logementId = (int)($_GET['logement'] ?? 0);

if (!$logementId) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>QR Code invalide</h1></body></html>';
    exit;
}

// Tracker le scan QR
try {
    $pdo->prepare("INSERT INTO hub_qr_scans (logement_id, ip_address, user_agent) VALUES (?, ?, ?)")
        ->execute([$logementId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
} catch (\PDOException $e) { /* table peut ne pas exister */ }

// Chercher la reservation active
$resa = findActiveReservation($pdo, $logementId);

if ($resa) {
    // Creer/recuperer le token HUB
    try {
        $token = getOrCreateHubToken($pdo, $resa['id'], $logementId);

        // Tracker le scan avec la reservation
        try {
            $tokenRow = $pdo->prepare("SELECT id FROM hub_tokens WHERE token = ?");
            $tokenRow->execute([$token]);
            $htId = $tokenRow->fetchColumn();
            if ($htId) {
                $pdo->prepare("UPDATE hub_qr_scans SET reservation_id = ?, hub_token_id = ? WHERE id = LAST_INSERT_ID()")
                    ->execute([$resa['id'], $htId]);
            }
        } catch (\PDOException $e) { /* pas grave */ }

        $hubUrl = getHubUrl($token);
        header('Location: ' . $hubUrl);
        exit;
    } catch (\PDOException $e) {
        error_log('QR redirect error: ' . $e->getMessage());
    }
}

// Pas de reservation active → page generique du logement
$logement = $pdo->prepare("SELECT nom_du_logement, adresse FROM liste_logements WHERE id = ?");
$logement->execute([$logementId]);
$log = $logement->fetch(PDO::FETCH_ASSOC);

$equip = null;
try {
    $eq = $pdo->prepare("SELECT * FROM logement_equipements WHERE logement_id = ?");
    $eq->execute([$logementId]);
    $equip = $eq->fetch(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { /* table peut ne pas exister */ }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($log['nom_du_logement'] ?? 'Logement') ?> — Frenchy Conciergerie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .qr-page { max-width: 480px; margin: 0 auto; padding: 24px 16px; text-align: center; }
        .qr-header { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 32px 16px; border-radius: 16px; margin-bottom: 24px; }
        .qr-header h1 { font-size: 1.3rem; margin: 0; }
    </style>
</head>
<body>
<div class="qr-page">
    <div class="qr-header">
        <h1><i class="fas fa-home"></i> <?= htmlspecialchars($log['nom_du_logement'] ?? 'Logement') ?></h1>
        <p style="opacity:0.85; margin:8px 0 0;"><?= htmlspecialchars($log['adresse'] ?? '') ?></p>
    </div>
    <div class="card">
        <div class="card-body">
            <p>Aucune reservation active pour le moment.</p>
            <?php if (!empty($equip['code_wifi'])): ?>
            <hr>
            <p><strong>Wifi :</strong> <?= htmlspecialchars($equip['nom_wifi'] ?? '') ?></p>
            <p><strong>Mot de passe :</strong> <?= htmlspecialchars($equip['code_wifi']) ?></p>
            <?php endif; ?>
            <hr>
            <p class="text-muted small"><i class="fas fa-bolt"></i> Frenchy Conciergerie</p>
        </div>
    </div>
</div>
</body>
</html>
