<?php
/**
 * FrenchyBot — Configuration
 */
include '../config.php';
include '../pages/menu.php';

// --- Fonctions settings inline (pas de dependance frenchybot/) ---
function _botSettings(): array {
    global $pdo;
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM bot_settings")->fetchAll(PDO::FETCH_ASSOC);
        $r = [];
        foreach ($rows as $row) $r[$row['setting_key']] = $row['setting_value'];
        return $r;
    } catch (\PDOException $e) { return []; }
}
function _saveSetting(string $key, string $value): void {
    global $pdo;
    $pdo->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value]);
}

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        foreach ($_POST['settings'] ?? [] as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if ($key) _saveSetting($key, trim($value));
        }
        $_SESSION['flash'] = 'Configuration sauvegardee.';
    }

    if ($action === 'test_openai') {
        $s = _botSettings();
        $apiKey = $s['openai_api_key'] ?? '';
        if (!$apiKey) {
            $_SESSION['flash_error'] = 'Sauvegardez d\'abord votre cle OpenAI ci-dessus.';
        } else {
            $ch = curl_init('https://api.openai.com/v1/models');
            curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200) $_SESSION['flash'] = 'Connexion OpenAI OK !';
            else { $d = json_decode($resp, true); $_SESSION['flash_error'] = 'Erreur OpenAI : ' . ($d['error']['message'] ?? "HTTP $code"); }
        }
    }

    if ($action === 'test_whatsapp') {
        $s = _botSettings();
        $t = $s['whatsapp_token'] ?? ''; $p = $s['whatsapp_phone_id'] ?? '';
        if (!$t || !$p) { $_SESSION['flash_error'] = 'Token ou Phone ID manquant.'; }
        else {
            $ch = curl_init("https://graph.facebook.com/v21.0/$p");
            curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $t], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
            $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($code === 200) $_SESSION['flash'] = 'Connexion WhatsApp OK !';
            else { $d = json_decode($resp, true); $_SESSION['flash_error'] = 'Erreur WhatsApp : ' . ($d['error']['message'] ?? "HTTP $code"); }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$settings = _botSettings();
try {
    $allSettings = $pdo->query("SELECT * FROM bot_settings ORDER BY setting_group, sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $allSettings = []; }

$settingsByGroup = [];
foreach ($allSettings as $s) $settingsByGroup[$s['setting_group']][] = $s;

$groups = [
    'general'  => ['label' => 'General',                            'icon' => 'fa-cog',          'color' => 'primary'],
    'ia'       => ['label' => 'Intelligence Artificielle (OpenAI)', 'icon' => 'fa-brain',        'color' => 'success'],
    'whatsapp' => ['label' => 'WhatsApp (Meta Cloud API)',          'icon' => 'fa-comment-dots',  'color' => 'success'],
];
$models = ['gpt-4o-mini' => 'GPT-4o Mini (pas cher)', 'gpt-4o' => 'GPT-4o (plus intelligent)', 'gpt-4.1-mini' => 'GPT-4.1 Mini', 'gpt-4.1-nano' => 'GPT-4.1 Nano (le moins cher)'];
?>
<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['flash']); endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['flash_error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['flash_error']); endif; ?>

    <?php if (empty($allSettings)): ?>
        <div class="alert alert-warning"><i class="fas fa-database"></i> Table <code>bot_settings</code> vide ou inexistante. Executez <code>frenchybot/sql/install.sql</code>.</div>
    <?php endif; ?>

    <h2 class="mb-4"><i class="fas fa-cog text-primary"></i> Configuration FrenchyBot</h2>

    <form method="POST" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="save_settings" id="formAction">

        <?php foreach ($groups as $gk => $g): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas <?= $g['icon'] ?> text-<?= $g['color'] ?>"></i> <?= $g['label'] ?></h5></div>
            <div class="card-body">
                <?php if (empty($settingsByGroup[$gk])): ?>
                    <p class="text-muted">Aucun parametre.</p>
                <?php else: ?>
                <div class="row g-3">
                <?php foreach ($settingsByGroup[$gk] as $s): $v = $s['setting_value'] ?? ''; ?>
                    <div class="col-md-<?= $s['setting_type'] === 'textarea' ? '12' : '6' ?>">
                        <label class="form-label fw-semibold"><?= htmlspecialchars($s['setting_label'] ?? $s['setting_key']) ?></label>
                        <?php if ($s['setting_type'] === 'password'): ?>
                            <div class="input-group">
                                <input type="password" name="settings[<?= $s['setting_key'] ?>]" class="form-control" value="<?= htmlspecialchars($v) ?>" id="f_<?= $s['setting_key'] ?>">
                                <button type="button" class="btn btn-outline-secondary" onclick="var f=document.getElementById('f_<?= $s['setting_key'] ?>');f.type=f.type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
                            </div>
                        <?php elseif ($s['setting_type'] === 'textarea'): ?>
                            <textarea name="settings[<?= $s['setting_key'] ?>]" class="form-control" rows="3"><?= htmlspecialchars($v) ?></textarea>
                        <?php elseif ($s['setting_type'] === 'toggle'): ?>
                            <div class="form-check form-switch">
                                <input type="hidden" name="settings[<?= $s['setting_key'] ?>]" value="0">
                                <input type="checkbox" name="settings[<?= $s['setting_key'] ?>]" class="form-check-input" value="1" <?= $v ? 'checked' : '' ?>>
                            </div>
                        <?php elseif ($s['setting_type'] === 'select' && $s['setting_key'] === 'openai_model'): ?>
                            <select name="settings[<?= $s['setting_key'] ?>]" class="form-select">
                                <?php foreach ($models as $mk => $ml): ?>
                                    <option value="<?= $mk ?>" <?= $v === $mk ? 'selected' : '' ?>><?= $ml ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="settings[<?= $s['setting_key'] ?>]" class="form-control" value="<?= htmlspecialchars($v) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($gk === 'ia'): ?>
                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('formAction').value='test_openai';document.getElementById('settingsForm').submit()">
                        <i class="fas fa-plug"></i> Tester OpenAI
                    </button>
                    <?= !empty($settings['openai_api_key']) ? '<span class="badge bg-success ms-2">Cle configuree</span>' : '<span class="badge bg-warning ms-2">Non configure</span>' ?>
                </div>
                <?php endif; ?>
                <?php if ($gk === 'whatsapp'): ?>
                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="document.getElementById('formAction').value='test_whatsapp';document.getElementById('settingsForm').submit()">
                        <i class="fas fa-plug"></i> Tester WhatsApp
                    </button>
                    <?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? '<span class="badge bg-success ms-2">Configure</span>' : '<span class="badge bg-warning ms-2">Non configure</span>' ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Sauvegarder</button>
    </form>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-info-circle"></i> Liens de paiement Stripe</h6>
            <p class="mb-0">Pour configurer les paiements, allez dans <strong>Upsells</strong> et collez vos <a href="https://dashboard.stripe.com/payment-links" target="_blank">liens Stripe</a> sur chaque upsell. Pas besoin de cle API.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-heartbeat"></i> Statut</h6></div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-3"><div class="fs-3 <?= !empty($settings['openai_api_key']) ? 'text-success' : 'text-muted' ?>"><i class="fas fa-brain"></i></div><div class="small">IA <?= !empty($settings['openai_api_key']) ? 'OK' : 'Off' ?></div></div>
                <div class="col-3"><div class="fs-3 text-success"><i class="fas fa-sms"></i></div><div class="small">SMS OK</div></div>
                <div class="col-3"><div class="fs-3 <?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'text-success' : 'text-muted' ?>"><i class="fas fa-comment-dots"></i></div><div class="small">WhatsApp <?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'OK' : 'Off' ?></div></div>
                <div class="col-3"><div class="fs-3 text-muted"><i class="fas fa-credit-card"></i></div><div class="small">Stripe via liens</div></div>
            </div>
        </div>
    </div>
</div>
