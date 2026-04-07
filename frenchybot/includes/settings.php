<?php
/**
 * FrenchyBot — Gestion des parametres en base de donnees
 * Remplace la lecture .env pour les cles API configurables depuis l'admin
 */

/**
 * Cache statique des parametres pour eviter les requetes repetees
 */
function botSettings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM bot_settings");
        $cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\PDOException $e) {
        // Table n'existe pas encore
        $cache = [];
    }

    return $cache;
}

/**
 * Recupere un parametre FrenchyBot
 * Priorite : DB (bot_settings) → .env → default
 */
function botSetting(PDO $pdo, string $key, string $default = ''): string
{
    $settings = botSettings($pdo);

    // Valeur en DB (non vide)
    if (isset($settings[$key]) && $settings[$key] !== '') {
        return $settings[$key];
    }

    // Mapping vers les anciennes variables .env pour compatibilite
    $envMap = [
        'openai_api_key'      => 'OPENAI_API_KEY',
        'openai_model'        => 'OPENAI_MODEL',
        'whatsapp_token'      => 'WHATSAPP_TOKEN',
        'whatsapp_phone_id'   => 'WHATSAPP_PHONE_ID',
        'stripe_secret_key'   => 'STRIPE_SECRET_KEY',
        'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
        'admin_phone'         => 'ADMIN_PHONE',
        'app_url'             => 'APP_URL',
    ];

    if (isset($envMap[$key])) {
        $envVal = env($envMap[$key], '');
        if ($envVal !== '') return $envVal;
    }

    return $default;
}

/**
 * Sauvegarde un parametre
 */
function saveBotSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO bot_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}
