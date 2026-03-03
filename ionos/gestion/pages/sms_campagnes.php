<?php
/**
 * Campagnes SMS — Page unifiée
 * Gestion des campagnes de relance et marketing SMS
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

$feedback = '';
$action = $_GET['action'] ?? 'list';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    if (isset($_POST['create_campaign'])) {
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $message = trim($_POST['message'] ?? '');
        $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
        $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

        if (!empty($nom) && !empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_campaigns (nom, description, logement_id, message_template, date_debut, date_fin, statut)
                    VALUES (?, ?, ?, ?, ?, ?, 'brouillon')
                ");
                $stmt->execute([$nom, $description, $logement_id, $message, $date_debut, $date_fin]);
                $feedback = '<div class="alert alert-success">Campagne créée.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// Récupérer les campagnes
$campaigns = [];
try {
    $campaigns = $pdo->query("
        SELECT c.*, l.nom_du_logement
        FROM sms_campaigns c
        LEFT JOIN liste_logements l ON c.logement_id = l.id
        ORDER BY c.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

$logements = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campagnes SMS — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-bullhorn text-primary"></i> Campagnes SMS</h2>
            <p class="text-muted"><?= count($campaigns) ?> campagne(s)</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus"></i> Nouvelle campagne
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Nom</th><th>Logement</th><th>Période</th><th>Destinataires</th><th>Envoyés</th><th>Statut</th><th>Créée le</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['nom']) ?></strong><br><small class="text-muted"><?= htmlspecialchars(mb_substr($c['description'] ?? '', 0, 50)) ?></small></td>
                            <td><?= htmlspecialchars($c['nom_du_logement'] ?? 'Tous') ?></td>
                            <td><small><?= $c['date_debut'] ? date('d/m/Y', strtotime($c['date_debut'])) : '' ?> — <?= $c['date_fin'] ? date('d/m/Y', strtotime($c['date_fin'])) : '' ?></small></td>
                            <td><span class="badge bg-info"><?= $c['total_recipients'] ?? 0 ?></span></td>
                            <td><span class="badge bg-success"><?= $c['total_sent'] ?? 0 ?></span></td>
                            <td>
                                <?php
                                $badges = ['brouillon'=>'secondary', 'planifiee'=>'warning', 'envoyee'=>'success', 'annulee'=>'danger'];
                                $badge = $badges[$c['statut'] ?? ''] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($c['statut'] ?? '') ?></span>
                            </td>
                            <td><small><?= date('d/m/Y', strtotime($c['created_at'])) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune campagne.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal création -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Nouvelle campagne</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom de la campagne *</label>
                            <input type="text" class="form-control" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Logement (optionnel)</label>
                            <select name="logement_id" class="form-select">
                                <option value="">Tous les logements</option>
                                <?php foreach ($logements as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Réservations du</label>
                            <input type="date" class="form-control" name="date_debut">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Au</label>
                            <input type="date" class="form-control" name="date_fin">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="message" rows="4" required placeholder="Bonjour {prenom}, ..."></textarea>
                        <small class="form-text text-muted">Variables : {prenom} {nom} {logement} {date_arrivee} {date_depart}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_campaign" class="btn btn-success"><i class="fas fa-plus"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
