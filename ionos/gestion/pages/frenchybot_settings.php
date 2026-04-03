<?php
/**
 * FrenchyBot — Configuration
 * Toutes les cles API et parametres se configurent ici (pas de .env a modifier)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../../../frenchybot/includes/settings.php';

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $fields = $_POST['settings'] ?? [];
        foreach ($fields as $key => $value) {
            // Securite : ne sauvegarder que les cles connues
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if ($key) {
                saveBotSetting($pdo, $key, trim($value));
            }
        }
        $_SESSION['flash'] = 'Configuration sauvegardee.';
    }

    if ($action === 'test_openai') {
        require_once __DIR__ . '/../../../frenchybot/includes/openai.php';
        $apiKey = trim($_POST['test_key'] ?? '');
        if (!$apiKey) $apiKey = botSetting($pdo, 'openai_api_key');

        if (!$apiKey) {
            $_SESSION['flash_error'] = 'Aucune cle API OpenAI configuree.';
        } else {
            // Test rapide
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
                $err = $decoded['error']['message'] ?? "HTTP $httpCode";
                $_SESSION['flash_error'] = "Erreur OpenAI : $err";
            }
        }
    }

    if ($action === 'test_whatsapp') {
        $token = botSetting($pdo, 'whatsapp_token');
        $phoneId = botSetting($pdo, 'whatsapp_phone_id');
        if (!$token || !$phoneId) {
            $_SESSION['flash_error'] = 'Token ou Phone ID WhatsApp manquant.';
        } else {
            $ch = curl_init("https://graph.facebook.com/v21.0/$phoneId");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
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
                $err = $decoded['error']['message'] ?? "HTTP $httpCode";
                $_SESSION['flash_error'] = "Erreur WhatsApp : $err";
            }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Charger les parametres ---
$settings = botSettings($pdo);

// Groupes de parametres
$groups = [
    'general' => ['label' => 'General', 'icon' => 'fa-cog', 'color' => 'primary'],
    'ia'      => ['label' => 'Intelligence Artificielle (OpenAI)', 'icon' => 'fa-brain', 'color' => 'success'],
    'whatsapp'=> ['label' => 'WhatsApp (Meta Cloud API)', 'icon' => 'fa-brands fa-whatsapp', 'color' => 'success'],
    'stripe'  => ['label' => 'Paiements (Stripe)', 'icon' => 'fa-credit-card', 'color' => 'info'],
];

// Charger la config complete avec metadonnees
$allSettings = $pdo->query("SELECT * FROM bot_settings ORDER BY setting_group, sort_order")->fetchAll(PDO::FETCH_ASSOC);
$settingsByGroup = [];
foreach ($allSettings as $s) {
    $settingsByGroup[$s['setting_group']][] = $s;
}

$openaiModels = ['gpt-4o-mini' => 'GPT-4o Mini (rapide, pas cher)', 'gpt-4o' => 'GPT-4o (plus intelligent, plus cher)', 'gpt-4.1-mini' => 'GPT-4.1 Mini', 'gpt-4.1-nano' => 'GPT-4.1 Nano (le moins cher)'];
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

    <h2 class="mb-4"><i class="fas fa-cog text-primary"></i> Configuration FrenchyBot</h2>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        <input type="hidden" name="action" value="save_settings">

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
                                           id="field_<?= $s['setting_key'] ?>"
                                           placeholder="<?= $val ? '••••••••' : 'Non configure' ?>">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('field_<?= $s['setting_key'] ?>')">
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

                <!-- Boutons de test -->
                <?php if ($groupKey === 'ia'): ?>
                <div class="mt-3 pt-3 border-top">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="test_openai">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-plug"></i> Tester la connexion OpenAI
                        </button>
                    </form>
                    <?php if (!empty($settings['openai_api_key'])): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Cle configuree</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Non configure</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($groupKey === 'whatsapp'): ?>
                <div class="mt-3 pt-3 border-top">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="test_whatsapp">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-plug"></i> Tester la connexion WhatsApp
                        </button>
                    </form>
                    <?php if (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Configure</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Non configure</span>
                    <?php endif; ?>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-info-circle"></i>
                        <a href="https://developers.facebook.com/apps/" target="_blank">Creer une app Meta Business</a> →
                        Ajouter le produit WhatsApp → Copier le Token et Phone Number ID
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($groupKey === 'stripe'): ?>
                <div class="mt-3 pt-3 border-top">
                    <?php if (!empty($settings['stripe_secret_key'])): ?>
                        <span class="badge bg-success"><i class="fas fa-check"></i> Stripe configure</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-info-circle"></i> Optionnel — sans Stripe, les commandes upsells sont en mode manuel</span>
                    <?php endif; ?>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-info-circle"></i>
                        <a href="https://dashboard.stripe.com/apikeys" target="_blank">Dashboard Stripe</a> → Cles API
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Sauvegarder la configuration
            </button>
        </div>
    </form>

    <!-- Statut global -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header"><h6 class="mb-0"><i class="fas fa-heartbeat"></i> Statut des services</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3 text-center">
                    <div class="fs-3 <?= !empty($settings['openai_api_key']) ? 'text-success' : 'text-muted' ?>">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="small fw-semibold">Chatbot IA</div>
                    <div class="small text-muted"><?= !empty($settings['openai_api_key']) ? 'Actif' : 'Inactif' ?></div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="fs-3 text-success"><i class="fas fa-sms"></i></div>
                    <div class="small fw-semibold">SMS (RPi)</div>
                    <div class="small text-muted">Toujours actif</div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="fs-3 <?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'text-success' : 'text-muted' ?>">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="small fw-semibold">WhatsApp</div>
                    <div class="small text-muted"><?= (!empty($settings['whatsapp_token']) && !empty($settings['whatsapp_phone_id'])) ? 'Actif' : 'Inactif' ?></div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="fs-3 <?= !empty($settings['stripe_secret_key']) ? 'text-success' : 'text-muted' ?>">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="small fw-semibold">Stripe</div>
                    <div class="small text-muted"><?= !empty($settings['stripe_secret_key']) ? 'Actif' : 'Manuel' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}
</script>

