<?php
/**
 * Page Communication - Version 2.0
 * Interface simple pour envoyer des SMS aux voyageurs
 */
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header_new.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

$feedback = '';
$preview_list = [];

// Récupérer les logements
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {}

// Récupérer les templates
$templates = [];
try {
    $stmt = $pdo->query("SELECT id, name, template FROM sms_templates ORDER BY name");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {}

// Prévisualiser les destinataires
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    $filter_type = $_POST['filter_type'] ?? 'date';
    $date = $_POST['date'] ?? date('Y-m-d');
    $date_field = $_POST['date_field'] ?? 'date_arrivee';
    $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;

    $sql = "
        SELECT r.id, r.prenom, r.nom, r.telephone, l.nom_du_logement,
               r.date_arrivee, r.date_depart
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE 1=1
    ";

    $params = [];

    if ($filter_type === 'date') {
        $sql .= " AND r.{$date_field} = :date";
        $params[':date'] = $date;
    }

    if ($logement_id) {
        $sql .= " AND r.logement_id = :logement_id";
        $params[':logement_id'] = $logement_id;
    }

    $sql .= " AND r.telephone IS NOT NULL AND r.telephone != ''";
    $sql .= " ORDER BY r.date_arrivee DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $preview_list = $stmt->fetchAll();
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Envoyer les SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    validateCsrfToken();

    $message = trim($_POST['message'] ?? '');
    $reservation_ids = $_POST['reservation_ids'] ?? [];

    if (empty($message)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le message est obligatoire</div>";
    } elseif (empty($reservation_ids)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Aucun destinataire sélectionné</div>";
    } else {
        $sent_count = 0;
        $error_count = 0;

        foreach ($reservation_ids as $res_id) {
            $res_id = (int)$res_id;

            // Récupérer les infos de la réservation
            try {
                $stmt = $pdo->prepare("
                    SELECT r.prenom, r.nom, r.telephone, l.nom_du_logement
                    FROM reservation r
                    LEFT JOIN liste_logements l ON r.logement_id = l.id
                    WHERE r.id = :id
                ");
                $stmt->execute([':id' => $res_id]);
                $res = $stmt->fetch();

                if ($res && !empty($res['telephone'])) {
                    // Personnaliser le message
                    $personalized_message = str_replace(
                        ['{prenom}', '{nom}', '{logement}'],
                        [$res['prenom'], $res['nom'], $res['nom_du_logement'] ?? ''],
                        $message
                    );

                    // Normaliser le téléphone
                    require_once __DIR__ . '/../includes/phone.php';
                    $phone_normalized = normalizePhoneNumber($res['telephone']);

                    // Insérer dans la file d'envoi
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO sms_outbox (destination, message, reservation_id, status)
                        VALUES (:destination, :message, :reservation_id, 'pending')
                    ");
                    $stmt_insert->execute([
                        ':destination' => $phone_normalized,
                        ':message' => $personalized_message,
                        ':reservation_id' => $res_id
                    ]);
                    $sent_count++;
                }
            } catch (PDOException $e) {
                $error_count++;
            }
        }

        if ($sent_count > 0) {
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> <strong>{$sent_count} SMS ajouté(s) à la file d'envoi !</strong><br>Les SMS seront envoyés dans quelques instants.</div>";
        }
        if ($error_count > 0) {
            $feedback .= "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> {$error_count} erreur(s) rencontrée(s)</div>";
        }

        $preview_list = []; // Réinitialiser la liste
    }
}
?>

<style>
    .recipient-card {
        border-left: 4px solid #007bff;
        transition: all 0.2s;
    }
    .recipient-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .recipient-card.selected {
        background-color: #e7f3ff;
        border-left-color: #28a745;
    }
</style>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-paper-plane text-primary"></i> Envoyer un SMS
        </h1>
        <p class="lead text-muted">Envoyez des SMS manuellement à vos voyageurs</p>
    </div>
</div>

<?= $feedback ?>

<div class="row">
    <!-- Formulaire de sélection -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtrer les destinataires</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echoCsrfField(); ?>

                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Sélectionner par</label>
                        <select class="form-control" name="date_field">
                            <option value="date_arrivee">Date d'arrivée</option>
                            <option value="date_depart">Date de départ</option>
                            <option value="date_reservation">Date de réservation</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-day"></i> Date</label>
                        <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-home"></i> Logement (optionnel)</label>
                        <select class="form-control" name="logement_id">
                            <option value="">Tous les logements</option>
                            <?php foreach ($logements as $logement): ?>
                                <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="filter_type" value="date">
                    <button type="submit" name="preview" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Afficher les destinataires
                    </button>
                </form>

                <hr>

                <div class="alert alert-info small">
                    <i class="fas fa-info-circle"></i> <strong>Variables disponibles :</strong><br>
                    <code>{prenom}</code> <code>{nom}</code> <code>{logement}</code>
                </div>
            </div>
        </div>

        <!-- Templates rapides -->
        <?php if (count($templates) > 0): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-file-alt"></i> Templates disponibles</h6>
                </div>
                <div class="card-body p-2">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($templates, 0, 5) as $tpl): ?>
                            <button type="button" class="list-group-item list-group-item-action p-2 small" onclick="useTemplate('<?= htmlspecialchars(addslashes($tpl['template'])) ?>')">
                                <?= htmlspecialchars($tpl['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Zone de composition et destinataires -->
    <div class="col-md-8">
        <?php if (!empty($preview_list)): ?>
            <form method="POST" id="sendForm">
                <?php echoCsrfField(); ?>

                <!-- Message -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Composer votre message</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <textarea class="form-control" name="message" id="message" rows="5" required placeholder="Écrivez votre message ici... Utilisez {prenom}, {nom}, {logement} pour personnaliser"></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><span id="charCount">0</span> / 160 caractères</small>
                            <button type="submit" name="send_sms" class="btn btn-success" onclick="return confirm('Envoyer <?= count($preview_list) ?> SMS ?')">
                                <i class="fas fa-paper-plane"></i> Envoyer aux destinataires cochés
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Liste des destinataires -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users"></i> Destinataires (<?= count($preview_list) ?>)</h5>
                        <button type="button" class="btn btn-sm btn-light" onclick="toggleAll()">
                            <i class="fas fa-check-square"></i> Tout cocher/décocher
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <?php foreach ($preview_list as $rec): ?>
                            <div class="card recipient-card mb-2">
                                <div class="card-body p-3">
                                    <div class="form-check">
                                        <input class="form-check-input recipient-checkbox" type="checkbox" name="reservation_ids[]" value="<?= $rec['id'] ?>" id="rec_<?= $rec['id'] ?>" checked>
                                        <label class="form-check-label w-100" for="rec_<?= $rec['id'] ?>">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?= htmlspecialchars($rec['prenom']) ?> <?= htmlspecialchars($rec['nom']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($rec['telephone']) ?>
                                                        <?php if ($rec['nom_du_logement']): ?>
                                                            | <i class="fas fa-home"></i> <?= htmlspecialchars($rec['nom_du_logement']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="text-right">
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($rec['date_arrivee'])) ?>
                                                        →
                                                        <?= date('d/m/Y', strtotime($rec['date_depart'])) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-arrow-left"></i> <strong>Utilisez les filtres à gauche pour afficher les destinataires</strong><br>
                Sélectionnez une date et un type (arrivée/départ) pour voir la liste des voyageurs.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Compter les caractères
document.getElementById('message')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Utiliser un template
function useTemplate(template) {
    document.getElementById('message').value = template;
    document.getElementById('charCount').textContent = template.length;
}

// Tout cocher/décocher
function toggleAll() {
    const checkboxes = document.querySelectorAll('.recipient-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        updateCardStyle(cb);
    });
}

// Mettre à jour le style de la carte
document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        updateCardStyle(this);
    });
});

function updateCardStyle(checkbox) {
    const card = checkbox.closest('.recipient-card');
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
}

// Initialiser les styles
document.querySelectorAll('.recipient-checkbox').forEach(checkbox => {
    updateCardStyle(checkbox);
});
</script>

<?php include '../includes/footer.php'; ?>
