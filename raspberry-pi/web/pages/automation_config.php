<?php
/**
 * Configuration de l'automatisation des SMS
 */
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

$feedback = '';
$config_file = __DIR__ . '/../../scripts/auto_send_sms_config.php';

// Charger la configuration actuelle
$config = [
    'enable_checkout' => true,
    'enable_checkin' => true,
    'enable_preparation' => true,
    'preparation_days' => 4,
    'cron_enabled' => false,
    'cron_schedule' => '*/30 * * * *'  // Toutes les 30 minutes par défaut
];

if (file_exists($config_file)) {
    include $config_file;
}

// Sauvegarder la configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    validateCsrfToken();

    $config['enable_checkout'] = isset($_POST['enable_checkout']);
    $config['enable_checkin'] = isset($_POST['enable_checkin']);
    $config['enable_preparation'] = isset($_POST['enable_preparation']);
    $config['preparation_days'] = (int)($_POST['preparation_days'] ?? 4);
    $config['cron_schedule'] = $_POST['cron_schedule'] ?? '*/30 * * * *';

    // Sauvegarder dans un fichier PHP
    $php_config = "<?php\n";
    $php_config .= "// Configuration générée automatiquement - " . date('Y-m-d H:i:s') . "\n";
    $php_config .= "\$config = " . var_export($config, true) . ";\n";

    if (file_put_contents($config_file, $php_config)) {
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Configuration sauvegardée avec succès</div>";
    } else {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur lors de la sauvegarde</div>";
    }
}

// Tester l'envoi manuel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_run'])) {
    validateCsrfToken();

    $output = [];
    $return_var = 0;

    exec('php ' . __DIR__ . '/../../scripts/auto_send_sms.php 2>&1', $output, $return_var);

    if ($return_var === 0) {
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Test exécuté avec succès<br><pre>" . implode("\n", $output) . "</pre></div>";
    } else {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur lors du test<br><pre>" . implode("\n", $output) . "</pre></div>";
    }
}

// Statistiques
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE status='pending'");
    $stats['pending'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE status='sent' AND DATE(sent_at) = CURDATE()");
    $stats['sent_today'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_depart = CURDATE() AND dep_sent = 0");
    $stats['checkout_pending'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_arrivee = CURDATE() AND j1_sent = 0");
    $stats['checkin_pending'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_arrivee = DATE_ADD(CURDATE(), INTERVAL 4 DAY) AND start_sent = 0");
    $stats['preparation_pending'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Ignorer
}

// Lire les dernières lignes du log
$log_lines = [];
$log_file = __DIR__ . '/../../logs/auto_send_sms.log';
if (file_exists($log_file)) {
    $log_content = file($log_file);
    $log_lines = array_slice($log_content, -50);  // 50 dernières lignes
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="text-gradient-primary">
                <i class="fas fa-robot"></i> Automatisation des SMS
            </h1>
            <p class="text-muted">Configuration de l'envoi automatique des SMS pour les réservations</p>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-custom border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h3 class="mb-0"><?= $stats['pending'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">SMS en attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-custom border-success">
                <div class="card-body text-center">
                    <i class="fas fa-paper-plane fa-2x text-success mb-2"></i>
                    <h3 class="mb-0"><?= $stats['sent_today'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Envoyés aujourd'hui</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-custom border-info">
                <div class="card-body text-center">
                    <i class="fas fa-sign-out-alt fa-2x text-info mb-2"></i>
                    <h3 class="mb-0"><?= $stats['checkout_pending'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Check-out à traiter</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-custom border-info">
                <div class="card-body text-center">
                    <i class="fas fa-sign-in-alt fa-2x text-info mb-2"></i>
                    <h3 class="mb-0"><?= $stats['checkin_pending'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Check-in à traiter</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card shadow-custom border-info">
                <div class="card-body text-center">
                    <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                    <h3 class="mb-0"><?= $stats['preparation_pending'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Préparations à traiter</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Configuration -->
        <div class="col-md-6">
            <div class="card shadow-custom">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <h6 class="text-primary"><i class="fas fa-toggle-on"></i> Types d'envoi activés</h6>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="enable_checkout" name="enable_checkout" <?= $config['enable_checkout'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_checkout">
                                <strong>Check-out du jour</strong> - SMS de départ envoyé le jour du départ
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="enable_checkin" name="enable_checkin" <?= $config['enable_checkin'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_checkin">
                                <strong>Check-in du jour</strong> - SMS d'accueil envoyé le jour de l'arrivée
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="enable_preparation" name="enable_preparation" <?= $config['enable_preparation'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_preparation">
                                <strong>Préparation</strong> - SMS de préparation
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="preparation_days"><i class="fas fa-calendar-alt"></i> Nombre de jours avant l'arrivée (préparation)</label>
                            <input type="number" class="form-control" id="preparation_days" name="preparation_days" value="<?= $config['preparation_days'] ?>" min="1" max="30">
                            <small class="form-text text-muted">Par défaut : 4 jours avant l'arrivée</small>
                        </div>

                        <hr>

                        <h6 class="text-primary"><i class="fas fa-clock"></i> Planification Cron</h6>

                        <div class="form-group">
                            <label for="cron_schedule">Expression cron</label>
                            <input type="text" class="form-control font-monospace" id="cron_schedule" name="cron_schedule" value="<?= htmlspecialchars($config['cron_schedule']) ?>">
                            <small class="form-text text-muted">
                                Exemples:<br>
                                • <code>*/30 * * * *</code> - Toutes les 30 minutes<br>
                                • <code>0 8,12,18 * * *</code> - À 8h, 12h et 18h<br>
                                • <code>0 9 * * *</code> - Tous les jours à 9h
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <strong><i class="fas fa-info-circle"></i> Pour activer le cron :</strong><br>
                            Exécutez : <code>crontab -e</code><br>
                            Ajoutez : <code><?= htmlspecialchars($config['cron_schedule']) ?> php /home/raphael/sms_project/scripts/auto_send_sms.php >> /home/raphael/sms_project/logs/cron.log 2>&1</code>
                        </div>

                        <button type="submit" name="save_config" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Sauvegarder la configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Logs et test -->
        <div class="col-md-6">
            <!-- Test manuel -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-flask"></i> Test manuel</h5>
                </div>
                <div class="card-body">
                    <p>Exécuter le script d'automatisation manuellement pour tester.</p>
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <button type="submit" name="test_run" class="btn btn-warning btn-block">
                            <i class="fas fa-play"></i> Exécuter maintenant
                        </button>
                    </form>
                </div>
            </div>

            <!-- Logs -->
            <div class="card shadow-custom">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-terminal"></i> Logs (50 dernières lignes)</h5>
                </div>
                <div class="card-body p-0">
                    <pre class="bg-dark text-light p-3 m-0" style="max-height: 400px; overflow-y: auto; font-size: 12px;"><?php
                    if (count($log_lines) > 0) {
                        foreach ($log_lines as $line) {
                            echo htmlspecialchars($line);
                        }
                    } else {
                        echo "Aucun log disponible.\n";
                    }
                    ?></pre>
                </div>
                <div class="card-footer">
                    <small class="text-muted">Fichier : <?= $log_file ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.font-monospace {
    font-family: 'Courier New', monospace;
}
</style>

<?php include '../includes/footer.php'; ?>
