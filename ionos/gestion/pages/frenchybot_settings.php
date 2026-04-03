<?php
/**
 * FrenchyBot — Configuration
 * Toutes les cles API et parametres se configurent ici
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Charger settings.php de maniere safe
$settingsFile = realpath(__DIR__ . '/../../../frenchybot/includes/settings.php');
if ($settingsFile && file_exists($settingsFile)) {
    require_once $settingsFile;
} else {
    // Fonctions inline si le fichier n'est pas trouve
    function botSettings(PDO $pdo): array {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM bot_settings");
            $r = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $r[$row['setting_key']] = $row['setting_value'];
            return $r;
        } catch (\PDOException $e) { return []; }
    }
    function botSetting(PDO $pdo, string $key, string $default = ''): string {
        $s = botSettings($pdo);
        return (isset($s[$key]) && $s[$key] !== '') ? $s[$key] : $default;
    }
    function saveBotSetting(PDO $pdo, string $key, string $value): void {
        $pdo->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value]);
    }
}

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $fields = $_POST['settings'] ?? [];
        foreach ($fields as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if ($key) saveBotSetting($pdo, $key, trim($value));
        }
        $_SESSION['flash'] = 'Configuration sauvegardee.';
    }

    if ($action === 'test_openai') {
        $apiKey = botSetting($pdo, 'openai_api_key');
        if (!$apiKey) {
            $_SESSION['flash_error'] = 'Aucune cle API OpenAI configuree. Sauvegardez d\'abord la cle ci-dessus.';
        } else {
            $ch = curl_init('https://api.openai.com/v1/models');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $_SESSION['flash'] = 'Connexion OpenAI OK ! La cle API est valide.';
            } else {
                $decoded = json_decode($response, true);
                $_SESSION['flash_error'] = "Erreur OpenAI : " . ($decoded['error']['message'] ?? "HTTP $httpCode");
            }
        }
    }

    if ($action === 'test_whatsapp') {
        $waToken = botSetting($pdo, 'whatsapp_token');
        $phoneId = botSetting($pdo, 'whatsapp_phone_id');
        if (!$waToken || !$phoneId) {
            $_SESSION['flash_error'] = 'Token ou Phone ID WhatsApp manquant.';
        } else {
            $ch = curl_init("https://graph.facebook.com/v21.0/$phoneId");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $waToken],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $_SESSION['flash'] = 'Connexion WhatsApp OK !';
            } else {
                $decoded = json_decode($response, true);
                $_SESSION['flash_error'] = "Erreur WhatsApp : " . ($decoded['error']['message'] ?? "HTTP $httpCode");
            }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Charger les parametres ---
try {
    $settings = botSettings($pdo);
    $allSettings = $pdo->query("SELECT * FROM bot_settings ORDER BY setting_group, sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $settings = [];
    $allSettings = [];
}

$settingsByGroup = [];
foreach ($allSettings as $s) {
    $settingsByGroup[$s['setting_group']][] = $s;
}

$groups = [
    'general'  => ['label' => 'General',                            'icon' => 'fa-cog',         'color' => 'primary'],
    'ia'       => ['label' => 'Intelligence Artificielle (OpenAI)', 'icon' => 'fa-brain',       'color' => 'success'],
    'whatsapp' => ['label' => 'WhatsApp (Meta Cloud API)',          'icon' => 'fa-comment-dots', 'color' => 'success'],
    'stripe'   => ['label' => 'Paiements (Stripe)',                 'icon' => 'fa-credit-card', 'color' => 'info'],
];

$openaiModels = [
    'gpt-4o-mini'  => 'GPT-4o Mini (rapide, pas cher)',
    'gpt-4o'       => 'GPT-4o (plus intelligent, plus cher)',
    'gpt-4.1-mini' => 'GPT-4.1 Mini',
    'gpt-4.1-nano' => 'GPT-4.1 Nano (le moins cher)',
];
?>

<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($allSettings)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-database"></i> La table <code>bot_settings</code> est vide ou n'existe pas.
            Executez <code>frenchybot/sql/install.sql</code> sur votre base de donnees.
        </div>
    <?php endif; ?>

    <h2 class="mb-4"><i class="fas fa-cog text-primary"></i> Configuration FrenchyBot</h2>

    <form method="POST" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <input type="hidden" name="action" value="save_settings" id="formAction">

        <?php foreach ($groups as $groupKey => $group): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas <?= $group['icon'] ?> text-<?= $group['color'] ?>"></i>
                    <?= $group['label'] ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($settingsByGroup[$groupKey])): ?>
                    <p class="text-muted">Aucun parametre dans ce groupe.</p>
                <?php else: ?>
                    <div class="row g-3">
                    <?php foreach ($settingsByGroup[$groupKey] as $s):
                        $val = $s['setting_value'] ?? '';
                    ?>
                        <div class="col-md-<?= $s['setting_type'] === 'textarea' ? '12' : '6' ?>">
                            <label class="form-label fw-semibold"><?= htmlspecialchars($s['setting_label'] ?? $s['setting_key']) ?></label>

                            <?php if ($s['setting_type'] === 'password'): ?>
                                <div class="input-group">
                                    <input type="password" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                                           class="form-control" value="<?= htmlspecialchars($val) ?>"
                                           id="field_<?= $s['setting_key'] ?>">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('field_<?= $s['setting_key'] ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>

                            <?php elseif ($s['setting_type'] === 'textarea'): ?>
                                <textarea name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                                          class="form-control" rows="3"><?= htmlspecialchars($val) ?></textarea>

                            <?php elseif ($s['setting_type'] === 'toggle'): ?>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" value="0">
                                    <input type="checkbox" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                                           class="form-check-input" value="1" <?= $val ? 'checked' : '' ?>>
                                </div>

                            <?php elseif ($s['setting_type'] === 'select' && $s['setting_key'] === 'openai_model'): ?>
                                <select name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" class="form-select">
                                    <?php foreach ($openaiModels as $mKey => $mLabel): ?>
                                        <option value="<?= $mKey ?>" <?= $val === $mKey ? 'selected' : '' ?>><?= htmlspecialchars($mLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php else: ?>
                                <input type="text" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                                       class="form-control" value="<?= htmlspecialchars($val) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($groupKey === 'ia'): ?>
                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="submitAction('test_openai')">
                        <i class="fas fa-plug"></i> Tester la connexion OpenAI
                    </button>
                    <?php if (!empty($settings['openai_api_key'])): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Cle configuree</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Non configure</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($groupKey === 'whatsapp'): ?>
                <div class="mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="submitAction('test_whatsapp')">
                        <i class="fas fa-plug"></i> Tester la connexion WhatsApp
                    </button>
                    <?php if (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Configure</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Non configure</span>
                    <?php endif; ?>
                    <div class="mt-2 small text-muted">
                        <a href="https://developers.facebook.com/apps/" target="_blank">Creer une app Meta Business</a> →
                        Ajouter le produit WhatsApp → Copier le Token et Phone Number ID
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($groupKey === 'stripe'): ?>
                <div class="mt-3 pt-3 border-top">
                    <div class="small text-muted">
                        <i class="fas fa-info-circle"></i> Pour les paiements, allez dans <strong>Upsells</strong> et collez vos
                        <a href="https://dashboard.stripe.com/payment-links" target="_blank">liens de paiement Stripe</a>
                        directement sur chaque upsell. Pas besoin de cle API.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Sauvegarder la configuration
        </button>
    </form>

    <!-- Statut global -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-heartbeat"></i> Statut des services</h6></div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-md-3">
                    <div class="fs-3 <?= !empty($settings['openai_api_key']) ? 'text-success' : 'text-muted' ?>"><i class="fas fa-brain"></i></div>
                    <div class="small fw-semibold">Chatbot IA</div>
                    <div class="small text-muted"><?= !empty($settings['openai_api_key']) ? 'Actif' : 'Inactif' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="fs-3 text-success"><i class="fas fa-sms"></i></div>
                    <div class="small fw-semibold">SMS (RPi)</div>
                    <div class="small text-muted">Toujours actif</div>
                </div>
                <div class="col-md-3">
                    <div class="fs-3 <?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'text-success' : 'text-muted' ?>"><i class="fas fa-comment-dots"></i></div>
                    <div class="small fw-semibold">WhatsApp</div>
                    <div class="small text-muted"><?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'Actif' : 'Inactif' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="fs-3 text-muted"><i class="fas fa-credit-card"></i></div>
                    <div class="small fw-semibold">Stripe</div>
                    <div class="small text-muted">Via liens de paiement</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePwd(id) {
    const f = document.getElementById(id);
    f.type = f.type === 'password' ? 'text' : 'password';
}
function submitAction(action) {
    document.getElementById('formAction').value = action;
    document.getElementById('settingsForm').submit();
}
</script>
