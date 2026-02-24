<?php
/**
 * Automatisations SMS — Page unifiée
 * Configuration de l'envoi automatique de SMS selon les événements de réservation
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

$feedback = '';

// Récupérer les automatisations
$automations = [];
try {
    $automations = $pdo->query("SELECT * FROM sms_automations ORDER BY trigger_event, delay_hours")->fetchAll();
} catch (PDOException $e) { /* table peut ne pas exister */ }

// Récupérer les templates
$templates = [];
try {
    $templates = $pdo->query("SELECT id, name, description FROM sms_templates ORDER BY name")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Statistiques d'envoi automatique
$stats = ['total_sent' => 0, 'pending' => 0, 'failed' => 0];
try {
    $r = $pdo->query("SELECT COUNT(*) as c FROM sms_outbox WHERE status = 'pending'")->fetch();
    $stats['pending'] = $r['c'] ?? 0;
    $r = $pdo->query("SELECT COUNT(*) as c FROM sms_out WHERE DATE(sent_at) = CURDATE()")->fetch();
    $stats['total_sent'] = $r['c'] ?? 0;
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatisations SMS — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-robot text-primary"></i> Automatisations SMS</h2>

    <!-- Stats rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-start border-primary border-4">
                <div class="card-body py-2">
                    <small class="text-muted">Règles actives</small>
                    <h4 class="mb-0"><?= count($automations) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-warning border-4">
                <div class="card-body py-2">
                    <small class="text-muted">En file d'attente</small>
                    <h4 class="mb-0"><?= $stats['pending'] ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-success border-4">
                <div class="card-body py-2">
                    <small class="text-muted">Envoyés aujourd'hui</small>
                    <h4 class="mb-0"><?= $stats['total_sent'] ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Règles d'automatisation -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Règles d'envoi automatique</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Les SMS sont envoyés automatiquement par le script <code>auto_send_sms.php</code> (cron toutes les 30 min).
                Les types d'envoi configurés :
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr><th>Événement</th><th>Délai</th><th>Description</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-info">check-in</span></td>
                            <td>J-0 (jour d'arrivée)</td>
                            <td>Message d'accueil avec infos pratiques</td>
                            <td><span class="badge bg-success">Actif</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-warning">préparation</span></td>
                            <td>J-4 (4 jours avant)</td>
                            <td>Message de préparation du séjour</td>
                            <td><span class="badge bg-success">Actif</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-secondary">mi-séjour</span></td>
                            <td>Milieu du séjour (3+ nuits)</td>
                            <td>Message de suivi bien-être</td>
                            <td><span class="badge bg-success">Actif</span></td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-danger">check-out</span></td>
                            <td>J-0 (jour de départ)</td>
                            <td>Message de remerciement</td>
                            <td><span class="badge bg-success">Actif</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($automations)): ?>
            <h6 class="mt-4">Règles personnalisées</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr><th>Trigger</th><th>Délai</th><th>Template</th><th>Actif</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($automations as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['trigger_event'] ?? '') ?></td>
                            <td><?= ($a['delay_hours'] ?? 0) ?>h</td>
                            <td><?= htmlspecialchars($a['template_name'] ?? $a['template_id'] ?? '') ?></td>
                            <td><?= !empty($a['actif']) ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
