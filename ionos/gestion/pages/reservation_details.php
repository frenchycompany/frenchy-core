<?php
// Interface détaillée d'une réservation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB loaded via config.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Vérifiez la connexion à la base de données.');
}

// Ajouter la colonne mid_sent si elle n'existe pas
try {
    $pdo->exec("ALTER TABLE reservation ADD COLUMN mid_sent TINYINT(4) DEFAULT 0");
} catch (PDOException $e) {
    // Colonne existe deja
}

$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reservation_id <= 0) {
    header('Location: reservation_list.php');
    exit;
}

// Récupérer les détails de la réservation avec le logement
try {
    $stmt = $pdo->prepare("
        SELECT r.*, l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $reservation_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        // header loaded via menu.php
        echo "<div class='alert alert-danger'>Réservation introuvable</div>";
        exit;
    }
} catch (PDOException $e) {
    // header loaded via menu.php
    echo "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Récupérer les informations essentielles du logement
$logementInfo = null;
if (!empty($reservation['logement_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT le.code_wifi, le.nom_wifi, le.code_porte, le.code_boite_cles,
                   le.instructions_arrivee, l.adresse, l.nom_du_logement
            FROM liste_logements l
            LEFT JOIN logement_equipements le ON l.id = le.logement_id
            WHERE l.id = :id
        ");
        $stmt->execute([':id' => $reservation['logement_id']]);
        $logementInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignorer silencieusement
    }
}

// header loaded via menu.php

// Récupérer l'historique des SMS envoyés pour ce numéro
$sms_history = [];
if (!empty($reservation['telephone'])) {
    try {
        $stmt_sms = $pdo->prepare("
            SELECT id, message, status, created_at, sent_at
            FROM sms_outbox
            WHERE receiver = :phone
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt_sms->execute([':phone' => $reservation['telephone']]);
        $sms_history = $stmt_sms->fetchAll();
    } catch (PDOException $e) {
        // Ignorer silencieusement les erreurs
    }
}

// Calculer les informations supplémentaires
$jours_avant_arrivee = 0;
$jours_avant_depart = 0;
$duree_sejour = 0;

if (!empty($reservation['date_arrivee']) && !empty($reservation['date_depart'])) {
    $now = new DateTime();
    $date_in = new DateTime($reservation['date_arrivee']);
    $date_out = new DateTime($reservation['date_depart']);

    $jours_avant_arrivee = $now->diff($date_in)->days;
    if ($date_in < $now) {
        $jours_avant_arrivee = -$jours_avant_arrivee;
    }

    $jours_avant_depart = $now->diff($date_out)->days;
    if ($date_out < $now) {
        $jours_avant_depart = -$jours_avant_depart;
    }

    $duree_sejour = $date_in->diff($date_out)->days;
}

// Déterminer le statut visuel
$statut_badge = 'secondary';
$statut_icon = 'circle';
switch (strtolower($reservation['statut'] ?? 'confirmée')) {
    case 'confirmée':
    case 'confirmed':
        $statut_badge = 'success';
        $statut_icon = 'check-circle';
        break;
    case 'annulée':
    case 'cancelled':
        $statut_badge = 'danger';
        $statut_icon = 'times-circle';
        break;
    case 'en attente':
    case 'pending':
        $statut_badge = 'warning';
        $statut_icon = 'clock';
        break;
}
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="display-4">
            <i class="fas fa-info-circle text-primary"></i> Details de la reservation
        </h1>
        <p class="lead text-muted">Reference: <strong><?= htmlspecialchars($reservation['reference'] ?? 'N/A') ?></strong></p>
    </div>
    <div class="col-md-4 text-right d-flex align-items-center justify-content-end">
        <button class="btn btn-primary mr-2" data-bs-toggle="modal" data-bs-target="#smsModal">
            <i class="fas fa-paper-plane"></i> Envoyer un SMS
        </button>
        <a href="reservation_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

    <div class="row">
        <!-- Colonne gauche: Informations principales -->
        <div class="col-lg-8">
            <!-- Informations du client -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Informations du client</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-user-circle"></i> Prénom:</strong>
                                <?= htmlspecialchars($reservation['prenom'] ?? 'N/A') ?>
                            </p>
                            <p><strong><i class="fas fa-id-card"></i> Nom:</strong>
                                <?= htmlspecialchars($reservation['nom'] ?? 'N/A') ?>
                            </p>
                            <p><strong><i class="fas fa-phone"></i> Téléphone:</strong>
                                <?php if (!empty($reservation['telephone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($reservation['telephone']) ?>">
                                        <?= htmlspecialchars($reservation['telephone']) ?>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-map-marker-alt"></i> Ville:</strong>
                                <?= htmlspecialchars($reservation['ville'] ?? 'N/A') ?>
                            </p>
                            <p><strong><i class="fas fa-globe"></i> Plateforme:</strong>
                                <span class="badge text-bg-info">
                                    <?= htmlspecialchars($reservation['plateforme'] ?? 'N/A') ?>
                                </span>
                            </p>
                            <p><strong><i class="fas fa-<?= $statut_icon ?>"></i> Statut:</strong>
                                <span class="badge badge-<?= $statut_badge ?>">
                                    <?= htmlspecialchars($reservation['statut'] ?? 'confirmée') ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations de la réservation -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Dates et durée</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-calendar-plus fa-2x text-primary mb-2"></i>
                                <p class="mb-1"><strong>Date de réservation</strong></p>
                                <p class="text-muted">
                                    <?= !empty($reservation['date_reservation'])
                                        ? date('d/m/Y', strtotime($reservation['date_reservation']))
                                        : 'N/A' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-sign-in-alt fa-2x text-success mb-2"></i>
                                <p class="mb-1"><strong>Arrivée</strong></p>
                                <p class="text-muted">
                                    <?= !empty($reservation['date_arrivee'])
                                        ? date('d/m/Y', strtotime($reservation['date_arrivee']))
                                        : 'N/A' ?>
                                </p>
                                <?php if ($jours_avant_arrivee > 0): ?>
                                    <small class="text-info">Dans <?= $jours_avant_arrivee ?> jour(s)</small>
                                <?php elseif ($jours_avant_arrivee == 0): ?>
                                    <small class="text-success font-weight-bold">Aujourd'hui!</small>
                                <?php else: ?>
                                    <small class="text-muted">Il y a <?= abs($jours_avant_arrivee) ?> jour(s)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-sign-out-alt fa-2x text-danger mb-2"></i>
                                <p class="mb-1"><strong>Départ</strong></p>
                                <p class="text-muted">
                                    <?= !empty($reservation['date_depart'])
                                        ? date('d/m/Y', strtotime($reservation['date_depart']))
                                        : 'N/A' ?>
                                </p>
                                <?php if ($jours_avant_depart > 0): ?>
                                    <small class="text-info">Dans <?= $jours_avant_depart ?> jour(s)</small>
                                <?php elseif ($jours_avant_depart == 0): ?>
                                    <small class="text-warning font-weight-bold">Aujourd'hui!</small>
                                <?php else: ?>
                                    <small class="text-muted">Il y a <?= abs($jours_avant_depart) ?> jour(s)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-moon"></i> Durée du séjour: <strong><?= $duree_sejour ?> nuit(s)</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations du logement -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-home"></i> Logement</h5>
                    <?php if (!empty($reservation['logement_id'])): ?>
                        <a href="logement_equipements.php?id=<?= (int)$reservation['logement_id'] ?>" class="btn btn-sm btn-dark">
                            <i class="fas fa-external-link-alt"></i> Voir fiche complète
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($reservation['nom_du_logement']) && $logementInfo): ?>
                        <h4 class="text-primary mb-3">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($reservation['nom_du_logement']) ?>
                        </h4>

                        <div class="row">
                            <?php if (!empty($logementInfo['adresse'])): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <strong><i class="fas fa-map-marker-alt text-danger"></i> Adresse</strong>
                                    <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($logementInfo['adresse'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($logementInfo['code_wifi']) || !empty($logementInfo['nom_wifi'])): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <strong><i class="fas fa-wifi text-primary"></i> WiFi</strong>
                                    <?php if (!empty($logementInfo['nom_wifi'])): ?>
                                        <p class="mb-1 mt-2">Réseau: <code><?= htmlspecialchars($logementInfo['nom_wifi']) ?></code></p>
                                    <?php endif; ?>
                                    <?php if (!empty($logementInfo['code_wifi'])): ?>
                                        <p class="mb-0">Code: <code class="bg-white p-1"><?= htmlspecialchars($logementInfo['code_wifi']) ?></code></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($logementInfo['code_porte'])): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <strong><i class="fas fa-door-open text-success"></i> Code porte</strong>
                                    <p class="mb-0 mt-2"><code class="bg-white p-1 font-weight-bold"><?= htmlspecialchars($logementInfo['code_porte']) ?></code></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($logementInfo['code_boite_cles'])): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100 bg-light">
                                    <strong><i class="fas fa-key text-warning"></i> Code boîte à clés</strong>
                                    <p class="mb-0 mt-2"><code class="bg-white p-1 font-weight-bold"><?= htmlspecialchars($logementInfo['code_boite_cles']) ?></code></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($logementInfo['instructions_arrivee'])): ?>
                        <div class="mt-3 border rounded p-3 bg-info text-white">
                            <strong><i class="fas fa-info-circle"></i> Instructions d'arrivée</strong>
                            <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($logementInfo['instructions_arrivee'])) ?></p>
                        </div>
                        <?php endif; ?>

                    <?php elseif (!empty($reservation['nom_du_logement'])): ?>
                        <h4 class="text-primary mb-3">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($reservation['nom_du_logement']) ?>
                        </h4>
                        <p class="text-muted"><i class="fas fa-info-circle"></i> Aucune information renseignée pour ce logement.</p>
                        <a href="logement_equipements.php?id=<?= (int)$reservation['logement_id'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Compléter les informations
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="fas fa-exclamation-triangle"></i> Aucun logement associé à cette réservation.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique SMS -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Historique SMS
                        <span class="badge badge-light"><?= count($sms_history) ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($sms_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th><i class="fas fa-comment"></i> Message</th>
                                        <th><i class="fas fa-info-circle"></i> Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sms_history as $sms): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <?= date('d/m/Y H:i', strtotime($sms['created_at'])) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(substr($sms['message'], 0, 50)) ?>
                                                <?= strlen($sms['message']) > 50 ? '...' : '' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badge = 'secondary';
                                                $status_text = $sms['status'];
                                                switch ($sms['status']) {
                                                    case 'sent':
                                                        $status_badge = 'success';
                                                        $status_text = 'Envoyé';
                                                        break;
                                                    case 'pending':
                                                        $status_badge = 'warning';
                                                        $status_text = 'En attente';
                                                        break;
                                                    case 'failed':
                                                        $status_badge = 'danger';
                                                        $status_text = 'Échec';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge badge-<?= $status_badge ?>">
                                                    <?= $status_text ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">
                            <i class="fas fa-inbox"></i> Aucun SMS envoyé à ce numéro
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne droite: Statut des SMS automatiques -->
        <div class="col-lg-4">
            <div class="card shadow-custom sticky-top" style="top: 20px;">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-robot"></i> SMS Automatiques</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Suivi des SMS automatiques envoyés pour cette réservation:</p>

                    <!-- SMS de préparation (J-4) -->
                    <div class="mb-3 p-3 border rounded <?= !empty($reservation['start_sent']) ? 'border-success bg-light' : 'border-secondary' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-<?= !empty($reservation['start_sent']) ? 'check-circle text-success' : 'circle text-muted' ?> fa-lg"></i>
                                <strong class="ml-2">Préparation</strong>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle"></i> SMS envoyé 4 jours avant l'arrivée
                        </small>
                        <?php if (!empty($reservation['start_sent'])): ?>
                            <span class="badge text-bg-success mt-2">✓ Envoyé</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary mt-2">Non envoyé</span>
                        <?php endif; ?>
                    </div>

                    <!-- SMS d'accueil (J) -->
                    <div class="mb-3 p-3 border rounded <?= !empty($reservation['j1_sent']) ? 'border-success bg-light' : 'border-secondary' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-<?= !empty($reservation['j1_sent']) ? 'check-circle text-success' : 'circle text-muted' ?> fa-lg"></i>
                                <strong class="ml-2">Accueil</strong>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle"></i> SMS envoyé le jour de l'arrivée
                        </small>
                        <?php if (!empty($reservation['j1_sent'])): ?>
                            <span class="badge text-bg-success mt-2">✓ Envoyé</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary mt-2">Non envoyé</span>
                        <?php endif; ?>
                    </div>

                    <!-- SMS mi-parcours (séjours 3+ nuits) -->
                    <?php if ($duree_sejour >= 3): ?>
                    <?php
                        $jourMiParcours = floor($duree_sejour / 2);
                        $dateMiParcours = date('d/m/Y', strtotime($reservation['date_arrivee'] . " + $jourMiParcours days"));
                    ?>
                    <div class="mb-3 p-3 border rounded <?= !empty($reservation['mid_sent']) ? 'border-success bg-light' : 'border-info' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-<?= !empty($reservation['mid_sent']) ? 'check-circle text-success' : 'hand-wave text-info' ?> fa-lg"></i>
                                <strong class="ml-2">Mi-parcours</strong>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle"></i> SMS envoyé le jour <?= $jourMiParcours ?> (<?= $dateMiParcours ?>)
                        </small>
                        <?php if (!empty($reservation['mid_sent'])): ?>
                            <span class="badge text-bg-success mt-2">✓ Envoyé</span>
                        <?php else: ?>
                            <span class="badge text-bg-info mt-2">Prévu (<?= $duree_sejour ?> nuits)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- SMS de départ -->
                    <div class="mb-3 p-3 border rounded <?= !empty($reservation['dep_sent']) ? 'border-success bg-light' : 'border-secondary' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-<?= !empty($reservation['dep_sent']) ? 'check-circle text-success' : 'circle text-muted' ?> fa-lg"></i>
                                <strong class="ml-2">Check-out</strong>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle"></i> SMS envoyé le jour du départ
                        </small>
                        <?php if (!empty($reservation['dep_sent'])): ?>
                            <span class="badge text-bg-success mt-2">✓ Envoyé</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary mt-2">Non envoyé</span>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Bouton d'action -->
                    <button class="btn btn-primary btn-block" data-bs-toggle="modal" data-bs-target="#smsModal">
                        <i class="fas fa-paper-plane"></i> Envoyer un SMS manuel
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Modal pour envoyer un SMS -->
<div class="modal fade" id="smsModal" tabindex="-1" role="dialog" aria-labelledby="smsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="smsModalLabel">
                    <i class="fas fa-paper-plane"></i> Envoyer un SMS
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="smsForm" method="POST">
                <div class="modal-body">
                    <div id="sms-alert" class="alert" style="display: none;"></div>

                    <div class="form-group">
                        <label for="receiver">
                            <i class="fas fa-phone"></i> Destinataire
                        </label>
                        <input type="text"
                               class="form-control"
                               id="receiver"
                               name="receiver"
                               value="<?= htmlspecialchars($reservation['telephone'] ?? '') ?>"
                               required
                               readonly>
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($reservation['prenom'] ?? 'Client') ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-comment-dots"></i> Message
                        </label>
                        <textarea class="form-control"
                                  id="message"
                                  name="message"
                                  rows="5"
                                  maxlength="160"
                                  required
                                  placeholder="Saisissez votre message..."></textarea>
                        <small id="char-counter" class="form-text text-muted">0/160 caractères</small>
                    </div>

                    <div class="form-group">
                        <label for="modem">
                            <i class="fas fa-sim-card"></i> Modem
                        </label>
                        <select class="form-control" id="modem" name="modem" required>
                            <option value="/dev/ttyUSB0">/dev/ttyUSB0</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="send-btn">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Compteur de caractères
document.getElementById('message').addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('char-counter').textContent = count + '/160 caractères';

    if (count > 160) {
        document.getElementById('char-counter').classList.add('text-danger');
    } else {
        document.getElementById('char-counter').classList.remove('text-danger');
    }
});

// Soumission AJAX du formulaire
document.getElementById('smsForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const alertDiv = document.getElementById('sms-alert');
    const sendBtn = document.getElementById('send-btn');

    // Désactiver le bouton
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';

    fetch('send_sms_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
        alertDiv.innerHTML = '<i class="fas fa-' + (data.success ? 'check' : 'exclamation') + '-circle"></i> ' + data.message;

        if (data.success) {
            document.getElementById('message').value = '';
            setTimeout(() => {
                $('#smsModal').modal('hide');
                location.reload(); // Recharger pour voir le SMS dans l'historique
            }, 2000);
        }

        // Réactiver le bouton
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
    })
    .catch(error => {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erreur de connexion';

        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer';
    });
});
</script>

<?php  ?>
