<?php
/**
 * FrenchyBot - Fonctions utilitaires
 */

/**
 * Réponse JSON et exit
 */
function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Envoyer un email HTML
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $fromName = 'FrenchyBot'): bool {
    $fromEmail = 'noreply@frenchycompany.fr';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: {$fromEmail}",
        'X-Mailer: FrenchyBot/' . FB_VERSION,
    ];

    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Flash message (session)
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Échapper pour HTML
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Formater une date FR
 */
function dateFR(string $date): string {
    if (empty($date)) return '-';
    $dt = new DateTime($date);
    return $dt->format('d/m/Y H:i');
}

/**
 * Tronquer un texte
 */
function truncate(string $text, int $length = 100): string {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * Formater un nombre FR
 */
function numberFR($number, int $decimals = 0): string {
    return number_format((float)$number, $decimals, ',', ' ');
}

/**
 * Générer un badge HTML pour un statut
 */
function statusBadge(string $status): string {
    $colors = [
        'new' => '#3b82f6',
        'contacted' => '#f59e0b',
        'qualified' => '#8b5cf6',
        'converted' => '#10b981',
        'lost' => '#ef4444',
        'active' => '#10b981',
        'paused' => '#f59e0b',
        'completed' => '#6b7280',
    ];
    $color = $colors[$status] ?? '#6b7280';
    $label = ucfirst($status);
    return "<span style=\"display:inline-block;padding:2px 10px;border-radius:12px;background:{$color}20;color:{$color};font-size:12px;font-weight:600;\">{$label}</span>";
}
