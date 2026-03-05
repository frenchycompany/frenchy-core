<?php
/**
 * Systeme de notifications — Email et base de donnees
 * Envoie des alertes quand un checkup signale des problemes
 */

/**
 * Cree la table de notifications si elle n'existe pas
 */
function initNotificationsTable(PDO $conn): void {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            titre VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            lien VARCHAR(500) DEFAULT NULL,
            logement_id INT DEFAULT NULL,
            intervenant_id INT DEFAULT NULL,
            lu TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lu (lu),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Enregistre une notification en base + envoie un email
 */
function sendNotification(PDO $conn, string $type, string $titre, string $message, ?string $lien = null, ?int $logementId = null, ?int $intervenantId = null): bool {
    initNotificationsTable($conn);

    // Enregistrer en base
    $stmt = $conn->prepare("INSERT INTO notifications (type, titre, message, lien, logement_id, intervenant_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$type, $titre, $message, $lien, $logementId, $intervenantId]);

    // Envoyer par email si configure
    $adminEmail = env('ADMIN_EMAIL', null);
    if ($adminEmail) {
        $subject = "[Frenchy] $titre";
        $headers = "From: noreply@frenchyconciergerie.fr\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:#1976d2;color:#fff;padding:20px;border-radius:10px 10px 0 0;'>
                <h2 style='margin:0;'>$titre</h2>
            </div>
            <div style='background:#fff;padding:20px;border:1px solid #eee;border-radius:0 0 10px 10px;'>
                <p style='color:#333;font-size:15px;line-height:1.5;'>$message</p>
                " . ($lien ? "<a href='$lien' style='display:inline-block;padding:12px 24px;background:#1976d2;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:10px;'>Voir le rapport</a>" : "") . "
            </div>
        </div>";

        @mail($adminEmail, $subject, $htmlBody, $headers);
    }

    return true;
}

/**
 * Notification specifique pour un checkup termine avec problemes
 */
function notifyCheckupProblemes(PDO $conn, int $sessionId, string $logementNom, int $nbProblemes, int $nbAbsents, string $intervenant): void {
    $message = "Le checkup du logement <strong>$logementNom</strong> effectue par <strong>$intervenant</strong> signale :<br><br>";

    if ($nbProblemes > 0) {
        $message .= "<span style='color:#e53935;font-weight:bold;'>$nbProblemes probleme(s)</span><br>";
    }
    if ($nbAbsents > 0) {
        $message .= "<span style='color:#ff9800;font-weight:bold;'>$nbAbsents element(s) absent(s)</span><br>";
    }

    // Recuperer les details des problemes
    $stmt = $conn->prepare("SELECT nom_item, categorie, commentaire FROM checkup_items WHERE session_id = ? AND statut IN ('probleme','absent') ORDER BY categorie");
    $stmt->execute([$sessionId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {
        $message .= "<br><strong>Details :</strong><ul>";
        foreach ($items as $item) {
            $status = ($item['commentaire'] ? " — <em>" . htmlspecialchars($item['commentaire']) . "</em>" : "");
            $message .= "<li>[" . htmlspecialchars($item['categorie']) . "] " . htmlspecialchars($item['nom_item']) . $status . "</li>";
        }
        $message .= "</ul>";
    }

    $lien = "https://gestion.frenchyconciergerie.fr/pages/checkup_rapport.php?session_id=$sessionId";

    sendNotification(
        $conn,
        'checkup_probleme',
        "Checkup $logementNom : $nbProblemes probleme(s), $nbAbsents absent(s)",
        $message,
        $lien,
        null,
        null
    );
}

/**
 * Recuperer les notifications non lues
 */
function getUnreadNotifications(PDO $conn, int $limit = 10): array {
    initNotificationsTable($conn);
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE lu = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marquer une notification comme lue
 */
function markNotificationRead(PDO $conn, int $id): void {
    $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
    $stmt->execute([$id]);
}
