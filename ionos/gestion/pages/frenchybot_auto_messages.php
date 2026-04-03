<?php
/**
 * FrenchyBot — Admin Messages Automatiques
 * Configure les messages envoyes automatiquement aux voyageurs (J-1, J, J+depart)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_message') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $triggerType = $_POST['trigger_type'] ?? '';
        $triggerOffset = (int)($_POST['trigger_offset_hours'] ?? 0);
        $channel = $_POST['channel'] ?? 'auto';
        $template = trim($_POST['template'] ?? '');
        $logementId = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name && $triggerType && $template) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE auto_messages SET name=?, trigger_type=?, trigger_offset_hours=?, channel=?, template=?, logement_id=?, active=? WHERE id=?");
                $stmt->execute([$name, $triggerType, $triggerOffset, $channel, $template, $logementId, $active, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO auto_messages (name, trigger_type, trigger_offset_hours, channel, template, logement_id, active) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$name, $triggerType, $triggerOffset, $channel, $template, $logementId, $active]);
            }
            $_SESSION['flash'] = 'Message sauvegarde.';
        }
    }

    if ($action === 'delete_message') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM auto_messages WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = 'Message supprime.';
        }
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE auto_messages SET active = NOT active WHERE id = ?")->execute([$id]);
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Donnees ---
$messages = $pdo->query("
    SELECT am.*, l.nom_du_logement,
           (SELECT COUNT(*) FROM auto_messages_log WHERE auto_message_id = am.id AND status = 'sent') AS nb_sent,
           (SELECT COUNT(*) FROM auto_messages_log WHERE auto_message_id = am.id AND status = 'failed') AS nb_failed
    FROM auto_messages am
    LEFT JOIN liste_logements l ON am.logement_id = l.id
    ORDER BY am.trigger_type, am.trigger_offset_hours
")->fetchAll(PDO::FETCH_ASSOC);

$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

$triggerLabels = [
    'before_checkin' => 'Avant check-in',
    'checkin_day' => 'Jour du check-in',
    'during_stay' => 'Pendant le sejour',
    'checkout_day' => 'Jour du check-out',
    'after_checkout' => 'Apres le check-out',
];
?>

<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-envelope-open-text text-primary"></i> Messages automatiques</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editMessage(null)">
            <i class="fas fa-plus"></i> Nouveau message
        </button>
    </div>

    <!-- Variables disponibles -->
    <div class="alert alert-info mb-4">
        <strong><i class="fas fa-info-circle"></i> Variables disponibles :</strong>
        <code>{prenom}</code> <code>{nom}</code> <code>{logement}</code> <code>{date_arrivee}</code>
        <code>{date_depart}</code> <code>{heure_checkin}</code> <code>{heure_checkout}</code>
        <code>{hub_url}</code> <code>{telephone}</code>
    </div>

    <!-- Liste des messages -->
    <div class="row g-3">
        <?php foreach ($messages as $msg): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 <?= $msg['active'] ? '' : 'opacity-50' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($msg['name']) ?></strong>
                    <div>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $msg['active'] ? 'btn-success' : 'btn-secondary' ?>">
                                <?= $msg['active'] ? 'ON' : 'OFF' ?>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge bg-primary"><?= $triggerLabels[$msg['trigger_type']] ?? $msg['trigger_type'] ?></span>
                        <?php if ($msg['trigger_offset_hours']): ?>
                            <span class="badge bg-secondary"><?= $msg['trigger_offset_hours'] > 0 ? '+' : '' ?><?= $msg['trigger_offset_hours'] ?>h</span>
                        <?php endif; ?>
                        <span class="badge bg-info"><?= $msg['channel'] ?></span>
                    </div>
                    <?php if ($msg['nom_du_logement']): ?>
                        <div class="small text-muted mb-2"><i class="fas fa-home"></i> <?= htmlspecialchars($msg['nom_du_logement']) ?></div>
                    <?php else: ?>
                        <div class="small text-muted mb-2"><i class="fas fa-globe"></i> Tous les logements</div>
                    <?php endif; ?>
                    <div class="bg-light rounded p-2 small" style="white-space:pre-line; max-height:120px; overflow-y:auto;">
                        <?= htmlspecialchars($msg['template']) ?>
                    </div>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-paper-plane"></i> <?= $msg['nb_sent'] ?> envoyes
                        <?php if ($msg['nb_failed']): ?>
                            | <span class="text-danger"><i class="fas fa-exclamation-circle"></i> <?= $msg['nb_failed'] ?> echecs</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer border-0 bg-transparent">
                    <button class="btn btn-sm btn-outline-primary" onclick='editMessage(<?= json_encode($msg) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
                        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                        <input type="hidden" name="action" value="delete_message">
                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($messages)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-envelope fa-3x mb-3 opacity-25"></i>
                <p>Aucun message automatique configure.<br>Cliquez sur "Nouveau message" pour commencer.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- CRON Info -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-terminal"></i> Configuration CRON</h6>
            <p class="small text-muted mb-1">Ajouter cette ligne au crontab du serveur pour l'envoi automatique :</p>
            <code>0 * * * * php <?= realpath(__DIR__ . '/../../frenchybot/cron/auto-messages.php') ?: '/var/www/frenchy-core/frenchybot/cron/auto-messages.php' ?></code>
        </div>
    </div>
</div>

<!-- Modal Edition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <input type="hidden" name="action" value="save_message">
                <input type="hidden" name="id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Nouveau message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Ex: Message de bienvenue">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Canal</label>
                            <select name="channel" id="edit_channel" class="form-select">
                                <option value="auto">Auto (SMS FR / WhatsApp etranger)</option>
                                <option value="sms">SMS uniquement</option>
                                <option value="whatsapp">WhatsApp uniquement</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Declencheur</label>
                            <select name="trigger_type" id="edit_trigger_type" class="form-select" required>
                                <?php foreach ($triggerLabels as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Decalage (heures)</label>
                            <input type="number" name="trigger_offset_hours" id="edit_offset" class="form-control" value="0">
                            <div class="form-text">-24 = 24h avant, 0 = le jour meme</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Logement</label>
                            <select name="logement_id" id="edit_logement" class="form-select">
                                <option value="">Tous les logements</option>
                                <?php foreach ($logements as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Template du message</label>
                            <textarea name="template" id="edit_template" class="form-control" rows="4" required
                                placeholder="Bonjour {prenom} ! Votre sejour a {logement} commence demain. Infos : {hub_url}"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="edit_active" class="form-check-input" checked>
                                <label class="form-check-label" for="edit_active">Actif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMessage(msg) {
    if (msg) {
        document.getElementById('editModalTitle').textContent = 'Modifier le message';
        document.getElementById('edit_id').value = msg.id;
        document.getElementById('edit_name').value = msg.name;
        document.getElementById('edit_trigger_type').value = msg.trigger_type;
        document.getElementById('edit_offset').value = msg.trigger_offset_hours;
        document.getElementById('edit_channel').value = msg.channel;
        document.getElementById('edit_template').value = msg.template;
        document.getElementById('edit_logement').value = msg.logement_id || '';
        document.getElementById('edit_active').checked = !!msg.active;
    } else {
        document.getElementById('editModalTitle').textContent = 'Nouveau message';
        document.getElementById('edit_id').value = 0;
        document.getElementById('edit_name').value = '';
        document.getElementById('edit_trigger_type').value = 'before_checkin';
        document.getElementById('edit_offset').value = -24;
        document.getElementById('edit_channel').value = 'auto';
        document.getElementById('edit_template').value = '';
        document.getElementById('edit_logement').value = '';
        document.getElementById('edit_active').checked = true;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
