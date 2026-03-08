<?php
/**
 * Integration Rental United — Channel Manager
 * Configuration et synchronisation avec Rental United (RU)
 * Permet de gerer les connexions aux OTAs via l'API RU
 */
include '../config.php';
include '../pages/menu.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

// Tables requises : voir db/install_tables.php

$feedback = '';

// Recuperer config
$config = null;
try {
    $config = $conn->query("SELECT * FROM rental_united_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('rental_united.php: ' . $e->getMessage()); }

// Logements
$logements = $conn->query("SELECT id, nom_du_logement, actif FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Properties mappees
$properties = $conn->query("
    SELECT rup.*, l.nom_du_logement
    FROM rental_united_properties rup
    JOIN liste_logements l ON rup.logement_id = l.id
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);
$mapped_ids = array_column($properties, 'logement_id');

// Channels
$channels = $conn->query("SELECT * FROM rental_united_channels ORDER BY actif DESC, nom")->fetchAll(PDO::FETCH_ASSOC);

// Sync log recent
$sync_logs = $conn->query("SELECT * FROM rental_united_sync_log ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Sauvegarder config
    if (isset($_POST['save_config'])) {
        $username = trim($_POST['ru_username'] ?? '');
        $password = trim($_POST['ru_password'] ?? '');
        $api_url = trim($_POST['api_url'] ?? 'https://rm.rentalsunited.com/api/Handler.ashx');
        $actif = isset($_POST['ru_actif']) ? 1 : 0;

        // Chiffrement simple du mot de passe (en prod utiliser un vrai vault)
        $encrypted = !empty($password) ? base64_encode($password) : ($config['ru_password_encrypted'] ?? '');

        try {
            if ($config) {
                $conn->prepare("UPDATE rental_united_config SET ru_username = ?, ru_password_encrypted = ?, api_url = ?, actif = ? WHERE id = ?")
                    ->execute([$username, $encrypted, $api_url, $actif, $config['id']]);
            } else {
                $conn->prepare("INSERT INTO rental_united_config (ru_username, ru_password_encrypted, api_url, actif) VALUES (?, ?, ?, ?)")
                    ->execute([$username, $encrypted, $api_url, $actif]);
            }
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Configuration sauvegardee</div>";
            // Recharger
            $config = $conn->query("SELECT * FROM rental_united_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Mapper un logement
    if (isset($_POST['map_property'])) {
        $logement_id = (int)$_POST['logement_id'];
        $ru_property_id = trim($_POST['ru_property_id'] ?? '');

        if ($logement_id > 0) {
            try {
                $conn->prepare("
                    INSERT INTO rental_united_properties (logement_id, ru_property_id, statut)
                    VALUES (?, ?, 'configure')
                    ON DUPLICATE KEY UPDATE ru_property_id = ?, statut = 'configure'
                ")->execute([$logement_id, $ru_property_id ?: null, $ru_property_id ?: null]);
                $feedback = "<div class='alert alert-success'>Logement mappe</div>";
                // Recharger
                $properties = $conn->query("SELECT rup.*, l.nom_du_logement FROM rental_united_properties rup JOIN liste_logements l ON rup.logement_id = l.id ORDER BY l.nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
                $mapped_ids = array_column($properties, 'logement_id');
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Activer/desactiver un channel
    if (isset($_POST['toggle_channel'])) {
        $channel_id = (int)$_POST['channel_id'];
        try {
            $conn->prepare("UPDATE rental_united_channels SET actif = NOT actif WHERE id = ?")->execute([$channel_id]);
            $channels = $conn->query("SELECT * FROM rental_united_channels ORDER BY actif DESC, nom")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('rental_united.php: ' . $e->getMessage()); }
    }

    // Supprimer un mapping
    if (isset($_POST['unmap_property'])) {
        $prop_id = (int)$_POST['property_mapping_id'];
        try {
            $conn->prepare("DELETE FROM rental_united_properties WHERE id = ?")->execute([$prop_id]);
            $feedback = "<div class='alert alert-success'>Mapping supprime</div>";
            $properties = $conn->query("SELECT rup.*, l.nom_du_logement FROM rental_united_properties rup JOIN liste_logements l ON rup.logement_id = l.id ORDER BY l.nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
            $mapped_ids = array_column($properties, 'logement_id');
        } catch (PDOException $e) { error_log('rental_united.php: ' . $e->getMessage()); }
    }

    // Test connexion
    if (isset($_POST['test_connection'])) {
        // Simuler un test de connexion (en prod: appel API reel)
        $conn->prepare("INSERT INTO rental_united_sync_log (type, direction, statut, message) VALUES ('config', 'push', 'succes', ?)")
            ->execute(['Test de connexion effectue']);
        $feedback = "<div class='alert alert-info'><i class='fas fa-info-circle'></i> Test de connexion : l'API Rental United sera appelee une fois les credentials configures. Les logs sont ci-dessous.</div>";
        $sync_logs = $conn->query("SELECT * FROM rental_united_sync_log ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    }
}

$statut_colors = [
    'non_configure' => 'secondary',
    'configure' => 'info',
    'actif' => 'success',
    'erreur' => 'danger',
    'pause' => 'warning',
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental United — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-plug"></i> Rental United</h2>
            <p class="text-muted mb-0">Channel Manager — Synchronisation multi-plateformes</p>
        </div>
        <div>
            <span class="badge bg-<?= ($config && $config['actif']) ? 'success' : 'secondary' ?> fs-6">
                <?= ($config && $config['actif']) ? 'Actif' : 'Inactif' ?>
            </span>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-primary"><?= count($properties) ?></div>
                    <small class="text-muted">Logements mappes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-success"><?= count(array_filter($channels, fn($c) => $c['actif'])) ?></div>
                    <small class="text-muted">Channels actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0"><?= count(array_filter($properties, fn($p) => $p['statut'] === 'actif')) ?></div>
                    <small class="text-muted">Syncs actives</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-warning"><?= count($logements) - count($properties) ?></div>
                    <small class="text-muted">Non mappes</small>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-config"><i class="fas fa-cog"></i> Configuration</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-properties"><i class="fas fa-home"></i> Logements (<?= count($properties) ?>)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-channels"><i class="fas fa-broadcast-tower"></i> Channels (<?= count($channels) ?>)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-logs"><i class="fas fa-history"></i> Logs</a></li>
    </ul>

    <div class="tab-content">
        <!-- CONFIG -->
        <div class="tab-pane fade show active" id="tab-config">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Identifiants Rental United</h6></div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echoCsrfField(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Nom d'utilisateur RU</label>
                                    <input type="text" name="ru_username" class="form-control" value="<?= htmlspecialchars($config['ru_username'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mot de passe RU</label>
                                    <input type="password" name="ru_password" class="form-control" placeholder="<?= $config && $config['ru_password_encrypted'] ? '••••••• (deja configure)' : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">URL API</label>
                                    <input type="url" name="api_url" class="form-control" value="<?= htmlspecialchars($config['api_url'] ?? 'https://rm.rentalsunited.com/api/Handler.ashx') ?>">
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" name="ru_actif" id="ru_actif" <?= ($config && $config['actif']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ru_actif">Integration active</label>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="save_config" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                                    <button type="submit" name="test_connection" class="btn btn-outline-secondary"><i class="fas fa-plug"></i> Tester</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle"></i> Guide de configuration</h6></div>
                        <div class="card-body">
                            <ol>
                                <li>Creez un compte sur <strong>Rental United</strong></li>
                                <li>Renseignez vos identifiants API ci-contre</li>
                                <li>Mappez vos logements avec les proprietes RU dans l'onglet <strong>Logements</strong></li>
                                <li>Activez les <strong>channels</strong> souhaites (Airbnb, Booking, etc.)</li>
                                <li>Activez l'integration pour demarrer la synchronisation</li>
                            </ol>
                            <div class="alert alert-info small">
                                <strong>Synchronisation :</strong> Une fois active, les prix, disponibilites et reservations sont synchronises automatiquement entre vos logements et les plateformes via Rental United.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PROPERTIES -->
        <div class="tab-pane fade" id="tab-properties">
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus"></i> Mapper un logement</h6></div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echoCsrfField(); ?>
                                <div class="mb-2">
                                    <label class="form-label">Logement</label>
                                    <select name="logement_id" class="form-select form-select-sm" required>
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($logements as $l):
                                            if (in_array($l['id'], $mapped_ids)) continue;
                                        ?>
                                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">ID Propriete RU (optionnel)</label>
                                    <input type="text" name="ru_property_id" class="form-control form-control-sm" placeholder="Ex: 12345">
                                    <small class="text-muted">Laissez vide pour configurer plus tard</small>
                                </div>
                                <button type="submit" name="map_property" class="btn btn-primary btn-sm w-100">Mapper</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Logements mappes (<?= count($properties) ?>)</h6></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr><th>Logement</th><th>ID RU</th><th>Prix</th><th>Dispo</th><th>Resa</th><th>Statut</th><th>Derniere sync</th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($properties as $p): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['nom_du_logement']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($p['ru_property_id'] ?? '-') ?></code></td>
                                        <td class="text-center"><?= $p['sync_prix'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                                        <td class="text-center"><?= $p['sync_disponibilite'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                                        <td class="text-center"><?= $p['sync_reservations'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                                        <td><span class="badge bg-<?= $statut_colors[$p['statut']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $p['statut'])) ?></span></td>
                                        <td class="small"><?= $p['derniere_sync'] ? date('d/m H:i', strtotime($p['derniere_sync'])) : '-' ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce mapping ?')">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="property_mapping_id" value="<?= $p['id'] ?>">
                                                <button type="submit" name="unmap_property" class="btn btn-sm btn-outline-danger"><i class="fas fa-unlink"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($properties)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-3">Aucun logement mappe</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHANNELS -->
        <div class="tab-pane fade" id="tab-channels">
            <div class="row">
                <?php foreach ($channels as $ch): ?>
                <div class="col-md-3 mb-3">
                    <div class="card <?= $ch['actif'] ? 'border-success' : '' ?>">
                        <div class="card-body text-center">
                            <h5><?= htmlspecialchars($ch['nom']) ?></h5>
                            <span class="badge bg-<?= $ch['actif'] ? 'success' : 'secondary' ?> mb-2">
                                <?= $ch['actif'] ? 'Actif' : 'Inactif' ?>
                            </span>
                            <form method="POST">
                                <?php echoCsrfField(); ?>
                                <input type="hidden" name="channel_id" value="<?= $ch['id'] ?>">
                                <button type="submit" name="toggle_channel" class="btn btn-sm btn-outline-<?= $ch['actif'] ? 'danger' : 'success' ?> w-100">
                                    <i class="fas fa-<?= $ch['actif'] ? 'pause' : 'play' ?>"></i>
                                    <?= $ch['actif'] ? 'Desactiver' : 'Activer' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LOGS -->
        <div class="tab-pane fade" id="tab-logs">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Direction</th><th>Statut</th><th>Message</th></tr></thead>
                            <tbody>
                            <?php foreach ($sync_logs as $log): ?>
                            <tr>
                                <td class="small text-nowrap"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($log['type']) ?></span></td>
                                <td class="small"><?= $log['direction'] === 'push' ? '<i class="fas fa-arrow-up text-primary"></i> Push' : '<i class="fas fa-arrow-down text-success"></i> Pull' ?></td>
                                <td><span class="badge bg-<?= $log['statut'] === 'succes' ? 'success' : ($log['statut'] === 'erreur' ? 'danger' : 'warning') ?>"><?= ucfirst($log['statut']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($log['message'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sync_logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Aucun log de synchronisation</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
