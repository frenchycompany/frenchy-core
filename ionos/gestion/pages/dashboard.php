<?php
/**
 * Dashboard - Vue d'ensemble du système SMS
 */
require_once __DIR__ . '/../includes/error_handler.php';
// DB loaded via config.php
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
// header loaded via menu.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Statistiques globales
$stats = [];

// Réservations
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation");
    $stats['total_reservations'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_arrivee >= CURDATE()");
    $stats['reservations_futures'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_arrivee = CURDATE()");
    $stats['arrivees_aujourd_hui'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM reservation WHERE date_depart = CURDATE()");
    $stats['departs_aujourd_hui'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['total_reservations'] = 0;
    $stats['reservations_futures'] = 0;
    $stats['arrivees_aujourd_hui'] = 0;
    $stats['departs_aujourd_hui'] = 0;
}

// SMS
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE DATE(timestamp) = CURDATE()");
    $stats['sms_today'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE status = 'sent' AND DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stats['sms_week'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE status = 'pending'");
    $stats['sms_pending'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['sms_today'] = 0;
    $stats['sms_week'] = 0;
    $stats['sms_pending'] = 0;
}

// Automatisations
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_automations WHERE actif = 1");
    $stats['automations_actives'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['automations_actives'] = 0;
}

// Logements actifs
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM liste_logements WHERE actif = 1");
    $stats['logements'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['logements'] = 0;
}

// Prochaines arrivées (7 jours)
$prochaines_arrivees = [];
try {
    $stmt = $pdo->query("
        SELECT r.prenom, r.nom, r.date_arrivee, r.date_depart, l.nom_du_logement
        FROM reservation r
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        WHERE r.date_arrivee BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY r.date_arrivee ASC
        LIMIT 10
    ");
    $prochaines_arrivees = $stmt->fetchAll();
} catch (PDOException $e) {}

// Derniers SMS envoyés
$derniers_sms = [];
try {
    $stmt = $pdo->query("
        SELECT s.destination, s.message, s.timestamp, s.status
        FROM sms_outbox s
        ORDER BY s.timestamp DESC
        LIMIT 5
    ");
    $derniers_sms = $stmt->fetchAll();
} catch (PDOException $e) {}
?>

<style>
    .stat-card {
        border-left: 4px solid;
        transition: all 0.3s;
        cursor: pointer;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
    }
</style>

<!-- Header -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-chart-line text-primary"></i> Tableau de bord
        </h1>
        <p class="lead text-muted">Vue d'ensemble de votre système de gestion SMS</p>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <a href="reservation_list.php" class="text-decoration-none">
            <div class="card stat-card h-100" style="border-left-color: #667eea;">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                    <h3 class="stat-value text-primary"><?= $stats['reservations_futures'] ?></h3>
                    <p class="text-muted mb-0">Réservations à venir</p>
                    <small class="text-muted"><?= $stats['total_reservations'] ?> au total</small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="recus.php" class="text-decoration-none">
            <div class="card stat-card h-100" style="border-left-color: #28a745;">
                <div class="card-body text-center">
                    <i class="fas fa-sms fa-3x text-success mb-3"></i>
                    <h3 class="stat-value text-success"><?= $stats['sms_today'] ?></h3>
                    <p class="text-muted mb-0">SMS aujourd'hui</p>
                    <small class="text-muted"><?= $stats['sms_week'] ?> cette semaine</small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="templates.php" class="text-decoration-none">
            <div class="card stat-card h-100" style="border-left-color: #f39c12;">
                <div class="card-body text-center">
                    <i class="fas fa-robot fa-3x text-warning mb-3"></i>
                    <h3 class="stat-value text-warning"><?= $stats['automations_actives'] ?></h3>
                    <p class="text-muted mb-0">Templates actifs</p>
                    <small class="text-muted">Modeles configures</small>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3 mb-3">
        <a href="logements.php" class="text-decoration-none">
            <div class="card stat-card h-100" style="border-left-color: #e74c3c;">
                <div class="card-body text-center">
                    <i class="fas fa-home fa-3x text-danger mb-3"></i>
                    <h3 class="stat-value text-danger"><?= $stats['logements'] ?></h3>
                    <p class="text-muted mb-0">Logements</p>
                    <small class="text-muted">Propriétés gérées</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Alertes du jour -->
<?php if ($stats['arrivees_aujourd_hui'] > 0 || $stats['departs_aujourd_hui'] > 0 || $stats['sms_pending'] > 0): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <h5><i class="fas fa-bell"></i> Alertes du jour</h5>
            <ul class="mb-0">
                <?php if ($stats['arrivees_aujourd_hui'] > 0): ?>
                    <li><strong><?= $stats['arrivees_aujourd_hui'] ?></strong> arrivée(s) aujourd'hui</li>
                <?php endif; ?>
                <?php if ($stats['departs_aujourd_hui'] > 0): ?>
                    <li><strong><?= $stats['departs_aujourd_hui'] ?></strong> départ(s) aujourd'hui</li>
                <?php endif; ?>
                <?php if ($stats['sms_pending'] > 0): ?>
                    <li><strong><?= $stats['sms_pending'] ?></strong> SMS en attente d'envoi</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<div class="row">
    <!-- Prochaines arrivées -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-plus"></i> Prochaines arrivées (7 jours)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($prochaines_arrivees) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($prochaines_arrivees as $arr): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($arr['prenom']) ?> <?= htmlspecialchars($arr['nom']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-home"></i> <?= htmlspecialchars($arr['nom_du_logement'] ?? 'N/A') ?>
                                        </small>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge text-bg-info">
                                            <?= date('d/m', strtotime($arr['date_arrivee'])) ?>
                                        </span>
                                        <span class="text-muted">→</span>
                                        <span class="badge text-bg-secondary">
                                            <?= date('d/m', strtotime($arr['date_depart'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-info-circle"></i> Aucune arrivée prévue dans les 7 prochains jours
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="reservation_list.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-list"></i> Voir toutes les reservations
                </a>
            </div>
        </div>
    </div>

    <!-- Derniers SMS -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-comments"></i> Derniers SMS envoyés</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($derniers_sms) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($derniers_sms as $sms): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block mb-1">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($sms['destination']) ?>
                                        </small>
                                        <small><?= htmlspecialchars(substr($sms['message'], 0, 60)) ?><?= strlen($sms['message']) > 60 ? '...' : '' ?></small>
                                    </div>
                                    <div class="ml-2">
                                        <?php if ($sms['status'] === 'sent'): ?>
                                            <span class="badge text-bg-success">Envoyé</span>
                                        <?php elseif ($sms['status'] === 'pending'): ?>
                                            <span class="badge text-bg-warning">En attente</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">Erreur</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= date('d/m H:i', strtotime($sms['timestamp'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-info-circle"></i> Aucun SMS envoyé récemment
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="recus.php" class="btn btn-sm btn-success">
                    <i class="fas fa-history"></i> Voir l'historique complet
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Actions rapides</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="recus.php" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Envoyer un SMS
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="templates.php" class="btn btn-warning btn-block">
                            <i class="fas fa-file-alt"></i> Gerer les templates
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="update_reservations.php" class="btn btn-info btn-block">
                            <i class="fas fa-sync-alt"></i> Synchroniser iCal
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="superhote_config.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-euro-sign"></i> Gestion tarifs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


