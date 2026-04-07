<?php
/**
 * FrenchyBot — Admin Messages Automatiques
 * Configure les messages envoyes automatiquement aux voyageurs (J-1, J, J+depart)
 */
include '../config.php';
include '../pages/menu.php';

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
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
    'before_checkin' => ['label' => 'Avant check-in', 'icon' => 'fa-plane-arrival', 'color' => 'primary'],
    'checkin_day'    => ['label' => 'Jour du check-in', 'icon' => 'fa-door-open', 'color' => 'success'],
    'during_stay'    => ['label' => 'Pendant le sejour', 'icon' => 'fa-bed', 'color' => 'info'],
    'checkout_day'   => ['label' => 'Jour du check-out', 'icon' => 'fa-door-closed', 'color' => 'warning'],
    'after_checkout' => ['label' => 'Apres le check-out', 'icon' => 'fa-star', 'color' => 'secondary'],
];

$channelLabels = [
    'auto'     => ['label' => 'Auto (SMS FR / WhatsApp etranger)', 'short' => 'Auto'],
    'sms'      => ['label' => 'SMS uniquement', 'short' => 'SMS'],
    'whatsapp' => ['label' => 'WhatsApp uniquement', 'short' => 'WhatsApp'],
];

// Grouper par trigger
$messagesByTrigger = [];
foreach ($messages as $msg) {
    $messagesByTrigger[$msg['trigger_type']][] = $msg;
}
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

    <!-- Timeline du sejour -->
    <div class="alert alert-light border mb-4">
        <h6 class="mb-2"><i class="fas fa-route"></i> Parcours voyageur</h6>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php foreach ($triggerLabels as $tk => $t):
                $count = count($messagesByTrigger[$tk] ?? []);
            ?>
            <div class="d-flex align-items-center">
                <span class="badge bg-<?= $t['color'] ?> me-1"><i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?></span>
                <span class="small text-muted">(<?= $count ?> msg)</span>
                <?php if ($tk !== 'after_checkout'): ?>
                    <i class="fas fa-arrow-right text-muted mx-2"></i>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Variables disponibles -->
    <div class="alert alert-info mb-4">
        <strong><i class="fas fa-code"></i> Variables disponibles dans les templates :</strong><br>
        <code>{prenom}</code> Prenom du voyageur &middot;
        <code>{nom}</code> Nom &middot;
        <code>{logement}</code> Nom du logement &middot;
        <code>{date_arrivee}</code> &middot;
        <code>{date_depart}</code> &middot;
        <code>{heure_checkin}</code> &middot;
        <code>{heure_checkout}</code> &middot;
        <code>{hub_url}</code> Lien vers le HUB du voyageur &middot;
        <code>{telephone}</code>
    </div>

    <!-- Messages groupes par declencheur -->
    <?php foreach ($triggerLabels as $tk => $t):
        $groupMessages = $messagesByTrigger[$tk] ?? [];
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-<?= $t['color'] ?> bg-opacity-10 d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas <?= $t['icon'] ?> text-<?= $t['color'] ?>"></i> <?= $t['label'] ?></h6>
            <span class="badge bg-<?= $t['color'] ?>"><?= count($groupMessages) ?> message(s)</span>
        </div>
        <?php if (!empty($groupMessages)): ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Decalage</th>
                            <th>Canal</th>
                            <th>Logement</th>
                            <th>Apercu</th>
                            <th>Envoyes</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($groupMessages as $msg): ?>
                        <tr class="<?= $msg['active'] ? '' : 'opacity-50' ?>">
                            <td><strong><?= htmlspecialchars($msg['name']) ?></strong></td>
                            <td>
                                <?php if ($msg['trigger_offset_hours'] < 0): ?>
                                    <span class="badge bg-light text-dark"><?= abs($msg['trigger_offset_hours']) ?>h avant</span>
                                <?php elseif ($msg['trigger_offset_hours'] > 0): ?>
                                    <span class="badge bg-light text-dark"><?= $msg['trigger_offset_hours'] ?>h apres</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">Le jour meme</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= $channelLabels[$msg['channel']]['short'] ?? $msg['channel'] ?></span></td>
                            <td class="small"><?= $msg['nom_du_logement'] ? htmlspecialchars($msg['nom_du_logement']) : '<span class="text-muted">Tous</span>' ?></td>
                            <td class="small" style="max-width:250px;">
                                <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($msg['template'], 0, 80)) ?><?= mb_strlen($msg['template']) > 80 ? '...' : '' ?></div>
                            </td>
                            <td>
                                <span class="badge bg-success"><?= $msg['nb_sent'] ?></span>
                                <?php if ($msg['nb_failed']): ?>
                                    <span class="badge bg-danger"><?= $msg['nb_failed'] ?> echec</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $msg['active'] ? 'btn-success' : 'btn-secondary' ?>">
                                        <?= $msg['active'] ? 'ON' : 'OFF' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editMessage(<?= json_encode($msg) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce message ?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card-body text-center text-muted py-3">
            <small>Aucun message pour cette etape.</small>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- CRON Info -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h6><i class="fas fa-terminal"></i> Configuration CRON</h6>
            <p class="small text-muted mb-1">Ajouter cette ligne au crontab du serveur pour l'envoi automatique :</p>
            <code>0 * * * * php /var/www/frenchy-core/frenchybot/cron/auto-messages.php</code>
        </div>
    </div>
</div>

<!-- Modal Edition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="save_message">
                <input type="hidden" name="id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Nouveau message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom du message</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Ex: Bienvenue J-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Canal d'envoi</label>
                            <select name="channel" id="edit_channel" class="form-select">
                                <?php foreach ($channelLabels as $ck => $cl): ?>
                                    <option value="<?= $ck ?>"><?= $cl['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quand envoyer ?</label>
                            <select name="trigger_type" id="edit_trigger_type" class="form-select" required>
                                <?php foreach ($triggerLabels as $val => $t): ?>
                                    <option value="<?= $val ?>"><?= $t['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Decalage (heures)</label>
                            <input type="number" name="trigger_offset_hours" id="edit_offset" class="form-control" value="0">
                            <div class="form-text">Ex: -24 = 24h avant, 0 = le jour meme, 10 = a 10h</div>
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
                            <label class="form-label">Contenu du message</label>
                            <textarea name="template" id="edit_template" class="form-control" rows="5" required
                                placeholder="Bonjour {prenom} ! Votre sejour a {logement} commence demain. Toutes les infos ici : {hub_url}"></textarea>
                            <div class="form-text">Utilisez les variables entre accolades : {prenom}, {logement}, {hub_url}, etc.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="edit_active" class="form-check-input" checked>
                                <label class="form-check-label" for="edit_active">Actif (envoi automatique)</label>
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
