<?php
/**
 * Envoi de SMS — Page unifiée
 * Interface pour envoyer un SMS via le modem GSM
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$feedback = '';

// Traitement de l'envoi
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();

    $receiver = trim($_POST["receiver"] ?? '');
    $message  = trim($_POST["message"] ?? '');
    $modem    = trim($_POST["modem"] ?? 'modem1');

    if ($receiver !== '' && $message !== '') {
        try {
            $pdoRpi = getRpiPdo();
            $stmt = $pdoRpi->prepare("
                INSERT INTO sms_outbox (receiver, message, modem, status)
                VALUES (:receiver, :message, :modem, 'pending')
            ");
            $stmt->execute([':receiver' => $receiver, ':message' => $message, ':modem' => $modem]);
            $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> SMS mis en file d\'attente pour envoi.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $feedback = '<div class="alert alert-warning">Numéro et message sont obligatoires.</div>';
    }
}

// Modems disponibles
$modems = [];
try {
    $pdoRpi = getRpiPdo();
    $modems = $pdoRpi->query("SELECT DISTINCT modem FROM sms_in")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* ignore */ }
if (empty($modems)) $modems = ['modem1'];

// Templates disponibles
$templates = [];
try {
    $pdoRpi = getRpiPdo();
    $templates = $pdoRpi->query("SELECT id, name, template, description FROM sms_templates ORDER BY name")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Derniers SMS envoyés
$recent_sent = [];
try {
    $pdoRpi = getRpiPdo();
    $recent_sent = $pdoRpi->query("SELECT * FROM sms_outbox ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer SMS — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-paper-plane text-primary"></i> Envoyer un SMS</h2>

    <?= $feedback ?>

    <div class="row">
        <!-- Formulaire d'envoi -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Nouveau message</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-phone"></i> Destinataire</label>
                            <input type="text" name="receiver" class="form-control" placeholder="+33612345678 ou 0612345678" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-comment"></i> Message</label>
                            <textarea name="message" class="form-control" rows="5" id="smsMessage" required maxlength="480"></textarea>
                            <small class="form-text text-muted">
                                <span id="charCount">0</span>/480 caractères
                            </small>
                        </div>
                        <?php if (!empty($templates)): ?>
                        <div class="mb-3">
                            <label class="form-label">Utiliser un template</label>
                            <select class="form-select" id="templateSelect" onchange="useTemplate()">
                                <option value="">— Choisir un template —</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= htmlspecialchars($t['template']) ?>"><?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['description'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Modem</label>
                            <select name="modem" class="form-select">
                                <?php foreach ($modems as $m): ?>
                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Derniers envois -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Derniers envois</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Date</th><th>Destinataire</th><th>Message</th><th>Statut</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_sent as $s): ?>
                                <tr>
                                    <td><small><?= date('d/m H:i', strtotime($s['sent_at'] ?? $s['created_at'] ?? '')) ?></small></td>
                                    <td><small><?= htmlspecialchars($s['receiver'] ?? '') ?></small></td>
                                    <td><small><?= htmlspecialchars(mb_substr($s['message'] ?? '', 0, 50)) ?></small></td>
                                    <td><span class="badge bg-<?= ($s['status'] ?? '') === 'sent' ? 'success' : 'warning' ?>"><?= htmlspecialchars($s['status'] ?? '') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_sent)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Aucun envoi récent.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('smsMessage').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});
function useTemplate() {
    var val = document.getElementById('templateSelect').value;
    if (val) {
        document.getElementById('smsMessage').value = val;
        document.getElementById('charCount').textContent = val.length;
    }
}
</script>
</body>
</html>
