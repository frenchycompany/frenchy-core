<?php
/**
 * Relances voyageurs — Segmentation + Campagnes
 * Permet de segmenter les anciens voyageurs et creer des campagnes ciblees
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

// Auto-creation tables
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS relance_segments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            description TEXT,
            criteres JSON,
            nb_contacts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS relance_campagnes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            segment_id INT DEFAULT NULL,
            type ENUM('sms', 'email') DEFAULT 'sms',
            message_template TEXT NOT NULL,
            statut ENUM('brouillon', 'planifiee', 'envoyee', 'annulee') DEFAULT 'brouillon',
            date_envoi_prevue DATETIME DEFAULT NULL,
            total_destinataires INT DEFAULT 0,
            total_envoyes INT DEFAULT 0,
            total_echecs INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            FOREIGN KEY (segment_id) REFERENCES relance_segments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS relance_envois (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campagne_id INT NOT NULL,
            reservation_id INT DEFAULT NULL,
            telephone VARCHAR(20) NOT NULL,
            prenom VARCHAR(100),
            nom VARCHAR(100),
            message_envoye TEXT,
            statut ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente',
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            FOREIGN KEY (campagne_id) REFERENCES relance_campagnes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Tables existent deja
}

$feedback = '';
$action = $_GET['action'] ?? 'dashboard';

// Logements pour les filtres
$logements = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Creer un segment
    if (isset($_POST['create_segment'])) {
        $nom = trim($_POST['seg_nom'] ?? '');
        $description = trim($_POST['seg_description'] ?? '');
        $criteres = [
            'logement_id' => !empty($_POST['seg_logement']) ? (int)$_POST['seg_logement'] : null,
            'date_sejour_min' => $_POST['seg_date_min'] ?? null,
            'date_sejour_max' => $_POST['seg_date_max'] ?? null,
            'nb_sejours_min' => !empty($_POST['seg_sejours_min']) ? (int)$_POST['seg_sejours_min'] : null,
            'nb_personnes_min' => !empty($_POST['seg_personnes_min']) ? (int)$_POST['seg_personnes_min'] : null,
            'nb_personnes_max' => !empty($_POST['seg_personnes_max']) ? (int)$_POST['seg_personnes_max'] : null,
            'duree_min' => !empty($_POST['seg_duree_min']) ? (int)$_POST['seg_duree_min'] : null,
            'pas_sejour_depuis_mois' => !empty($_POST['seg_inactif_mois']) ? (int)$_POST['seg_inactif_mois'] : null,
        ];

        if (!empty($nom)) {
            // Compter les contacts correspondants
            $nb = countSegmentContacts($pdo, $criteres);
            try {
                $stmt = $pdo->prepare("INSERT INTO relance_segments (nom, description, criteres, nb_contacts) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $description ?: null, json_encode($criteres), $nb]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Segment cree avec $nb contact(s)</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Creer une campagne
    if (isset($_POST['create_campagne'])) {
        $nom = trim($_POST['camp_nom'] ?? '');
        $segment_id = !empty($_POST['camp_segment']) ? (int)$_POST['camp_segment'] : null;
        $type = $_POST['camp_type'] ?? 'sms';
        $message = trim($_POST['camp_message'] ?? '');
        $date_envoi = !empty($_POST['camp_date_envoi']) ? $_POST['camp_date_envoi'] : null;

        if (!empty($nom) && !empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO relance_campagnes (nom, segment_id, type, message_template, date_envoi_prevue)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $segment_id, $type, $message, $date_envoi]);
                $camp_id = $pdo->lastInsertId();

                // Si un segment est selectionne, pre-charger les destinataires
                if ($segment_id) {
                    $seg = $pdo->prepare("SELECT criteres FROM relance_segments WHERE id = ?");
                    $seg->execute([$segment_id]);
                    $seg = $seg->fetch(PDO::FETCH_ASSOC);
                    if ($seg) {
                        $criteres = json_decode($seg['criteres'], true);
                        $contacts = getSegmentContacts($pdo, $criteres);
                        $stmtIns = $pdo->prepare("INSERT INTO relance_envois (campagne_id, reservation_id, telephone, prenom, nom) VALUES (?, ?, ?, ?, ?)");
                        foreach ($contacts as $c) {
                            $stmtIns->execute([$camp_id, $c['id'], $c['telephone'], $c['prenom'], $c['nom']]);
                        }
                        $pdo->prepare("UPDATE relance_campagnes SET total_destinataires = ? WHERE id = ?")->execute([count($contacts), $camp_id]);
                    }
                }

                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne creee</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Envoyer une campagne
    if (isset($_POST['send_campagne'])) {
        $camp_id = (int)$_POST['campagne_id'];
        try {
            $envois = $pdo->prepare("SELECT * FROM relance_envois WHERE campagne_id = ? AND statut = 'en_attente'");
            $envois->execute([$camp_id]);
            $envois = $envois->fetchAll(PDO::FETCH_ASSOC);

            $camp = $pdo->prepare("SELECT message_template FROM relance_campagnes WHERE id = ?");
            $camp->execute([$camp_id]);
            $camp = $camp->fetch(PDO::FETCH_ASSOC);

            $sent = 0;
            $errors = 0;

            foreach ($envois as $envoi) {
                $message = str_replace(
                    ['{prenom}', '{nom}'],
                    [$envoi['prenom'] ?? '', $envoi['nom'] ?? ''],
                    $camp['message_template']
                );

                $tel = preg_replace('/[^0-9+]/', '', $envoi['telephone']);
                if (!str_starts_with($tel, '+')) {
                    if (str_starts_with($tel, '0')) {
                        $tel = '+33' . substr($tel, 1);
                    } else {
                        $tel = '+' . $tel;
                    }
                }

                try {
                    $pdo->prepare("
                        INSERT INTO outbox (DestinationNumber, TextDecoded, CreatorID, Coding, Class, InsertIntoDB, SendingTimeOut, DeliveryReport)
                        VALUES (?, ?, 'Relance', 'Default_No_Compression', -1, NOW(), NOW(), 'default')
                    ")->execute([$tel, $message]);

                    $pdo->prepare("UPDATE relance_envois SET statut = 'envoye', message_envoye = ?, sent_at = NOW() WHERE id = ?")
                        ->execute([$message, $envoi['id']]);
                    $sent++;
                } catch (PDOException $e) {
                    $pdo->prepare("UPDATE relance_envois SET statut = 'echec', error_message = ? WHERE id = ?")
                        ->execute([$e->getMessage(), $envoi['id']]);
                    $errors++;
                }
            }

            $pdo->prepare("UPDATE relance_campagnes SET statut = 'envoyee', sent_at = NOW(), total_envoyes = ?, total_echecs = ? WHERE id = ?")
                ->execute([$sent, $errors, $camp_id]);

            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne envoyee : $sent SMS, $errors echec(s)</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Supprimer un segment
    if (isset($_POST['delete_segment'])) {
        $id = (int)$_POST['segment_id'];
        try {
            $pdo->prepare("DELETE FROM relance_segments WHERE id = ?")->execute([$id]);
            $feedback = "<div class='alert alert-success'>Segment supprime</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Supprimer une campagne
    if (isset($_POST['delete_campagne'])) {
        $id = (int)$_POST['campagne_id'];
        try {
            $pdo->prepare("DELETE FROM relance_campagnes WHERE id = ?")->execute([$id]);
            $feedback = "<div class='alert alert-success'>Campagne supprimee</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Previsualiser segment
    if (isset($_POST['preview_segment'])) {
        $action = 'preview';
    }
}

/**
 * Construit la requete WHERE pour un segment
 */
function buildSegmentWhere(array $criteres): array {
    $where = ["r.telephone IS NOT NULL", "r.telephone != ''"];
    $params = [];

    if (!empty($criteres['logement_id'])) {
        $where[] = "r.logement_id = ?";
        $params[] = $criteres['logement_id'];
    }
    if (!empty($criteres['date_sejour_min'])) {
        $where[] = "r.date_arrivee >= ?";
        $params[] = $criteres['date_sejour_min'];
    }
    if (!empty($criteres['date_sejour_max'])) {
        $where[] = "r.date_depart <= ?";
        $params[] = $criteres['date_sejour_max'];
    }
    if (!empty($criteres['nb_personnes_min'])) {
        $where[] = "r.nombre_voyageurs >= ?";
        $params[] = $criteres['nb_personnes_min'];
    }
    if (!empty($criteres['nb_personnes_max'])) {
        $where[] = "r.nombre_voyageurs <= ?";
        $params[] = $criteres['nb_personnes_max'];
    }
    if (!empty($criteres['duree_min'])) {
        $where[] = "DATEDIFF(r.date_depart, r.date_arrivee) >= ?";
        $params[] = $criteres['duree_min'];
    }
    if (!empty($criteres['pas_sejour_depuis_mois'])) {
        $where[] = "r.date_depart < DATE_SUB(NOW(), INTERVAL ? MONTH)";
        $params[] = $criteres['pas_sejour_depuis_mois'];
    }

    return [implode(' AND ', $where), $params];
}

function countSegmentContacts(PDO $pdo, array $criteres): int {
    [$where, $params] = buildSegmentWhere($criteres);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT telephone) FROM reservation r WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function getSegmentContacts(PDO $pdo, array $criteres): array {
    [$where, $params] = buildSegmentWhere($criteres);
    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.telephone, r.prenom, r.nom,
                   MAX(r.date_depart) as dernier_sejour,
                   COUNT(*) as nb_sejours,
                   l.nom_du_logement
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE $where
            GROUP BY r.telephone
            ORDER BY dernier_sejour DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// === DONNEES ===
$segments = [];
try {
    $segments = $pdo->query("SELECT * FROM relance_segments ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$campagnes = [];
try {
    $campagnes = $pdo->query("
        SELECT c.*, s.nom as segment_nom
        FROM relance_campagnes c
        LEFT JOIN relance_segments s ON c.segment_id = s.id
        ORDER BY c.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Stats globales voyageurs
$stats_voyageurs = ['total' => 0, 'avec_tel' => 0, 'recents_6m' => 0, 'recurrents' => 0];
try {
    $stats_voyageurs = $pdo->query("
        SELECT
            COUNT(DISTINCT telephone) as total,
            COUNT(DISTINCT CASE WHEN telephone IS NOT NULL AND telephone != '' THEN telephone END) as avec_tel,
            COUNT(DISTINCT CASE WHEN date_depart >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND telephone != '' THEN telephone END) as recents_6m,
            (SELECT COUNT(*) FROM (SELECT telephone FROM reservation WHERE telephone IS NOT NULL AND telephone != '' GROUP BY telephone HAVING COUNT(*) > 1) t) as recurrents
        FROM reservation
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Preview segment
$preview_contacts = [];
if ($action === 'preview' && isset($_POST['preview_segment'])) {
    $criteres_preview = [
        'logement_id' => !empty($_POST['seg_logement']) ? (int)$_POST['seg_logement'] : null,
        'date_sejour_min' => $_POST['seg_date_min'] ?? null,
        'date_sejour_max' => $_POST['seg_date_max'] ?? null,
        'nb_sejours_min' => !empty($_POST['seg_sejours_min']) ? (int)$_POST['seg_sejours_min'] : null,
        'nb_personnes_min' => !empty($_POST['seg_personnes_min']) ? (int)$_POST['seg_personnes_min'] : null,
        'nb_personnes_max' => !empty($_POST['seg_personnes_max']) ? (int)$_POST['seg_personnes_max'] : null,
        'duree_min' => !empty($_POST['seg_duree_min']) ? (int)$_POST['seg_duree_min'] : null,
        'pas_sejour_depuis_mois' => !empty($_POST['seg_inactif_mois']) ? (int)$_POST['seg_inactif_mois'] : null,
    ];
    $preview_contacts = getSegmentContacts($pdo, $criteres_preview);
}

// Detail campagne
$detail_campagne = null;
$detail_envois = [];
if (!empty($_GET['id'])) {
    $camp_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT c.*, s.nom as segment_nom FROM relance_campagnes c LEFT JOIN relance_segments s ON c.segment_id = s.id WHERE c.id = ?");
    $stmt->execute([$camp_id]);
    $detail_campagne = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($detail_campagne) {
        $action = 'detail_campagne';
        $detail_envois = $pdo->prepare("SELECT * FROM relance_envois WHERE campagne_id = ? ORDER BY statut, nom");
        $detail_envois->execute([$camp_id]);
        $detail_envois = $detail_envois->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relances Voyageurs — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-bullhorn"></i> Relances Voyageurs</h2>
            <p class="text-muted mb-0">Segmentation et campagnes de relance</p>
        </div>
        <div>
            <a href="relances_voyageurs.php" class="btn btn-outline-secondary btn-sm me-1"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <!-- Stats voyageurs -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-primary"><?= $stats_voyageurs['avec_tel'] ?></div>
                    <small class="text-muted">Voyageurs avec tel.</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-success"><?= $stats_voyageurs['recents_6m'] ?></div>
                    <small class="text-muted">Actifs (6 mois)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-warning"><?= $stats_voyageurs['recurrents'] ?></div>
                    <small class="text-muted">Recurrents (2+ sejours)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body py-2">
                    <div class="h4 mb-0 text-info"><?= count($segments) ?></div>
                    <small class="text-muted">Segments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="relanceTabs">
        <li class="nav-item">
            <a class="nav-link <?= in_array($action, ['dashboard','preview']) ? 'active' : '' ?>" href="#segmentation" data-bs-toggle="tab">
                <i class="fas fa-filter"></i> Segmentation
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= in_array($action, ['campagnes','detail_campagne']) ? 'active' : '' ?>" href="#campagnes" data-bs-toggle="tab">
                <i class="fas fa-paper-plane"></i> Campagnes (<?= count($campagnes) ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB SEGMENTATION -->
        <div class="tab-pane fade <?= in_array($action, ['dashboard','preview']) ? 'show active' : '' ?>" id="segmentation">

            <div class="row">
                <div class="col-md-5">
                    <!-- Creer un segment -->
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus"></i> Creer un segment</h6></div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echoCsrfField(); ?>
                                <div class="mb-2">
                                    <label class="form-label">Nom du segment *</label>
                                    <input type="text" name="seg_nom" class="form-control form-control-sm" placeholder="Ex: Clients fideles ete 2025" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="seg_description" class="form-control form-control-sm" placeholder="Description courte">
                                </div>
                                <hr>
                                <h6 class="small text-muted">Criteres de segmentation</h6>
                                <div class="row mb-2">
                                    <div class="col">
                                        <label class="form-label small">Logement</label>
                                        <select name="seg_logement" class="form-select form-select-sm">
                                            <option value="">Tous</option>
                                            <?php foreach ($logements as $l): ?>
                                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <label class="form-label small">Sejour apres le</label>
                                        <input type="date" name="seg_date_min" class="form-control form-control-sm">
                                    </div>
                                    <div class="col">
                                        <label class="form-label small">Sejour avant le</label>
                                        <input type="date" name="seg_date_max" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <label class="form-label small">Nb voyageurs min</label>
                                        <input type="number" name="seg_personnes_min" class="form-control form-control-sm" min="1">
                                    </div>
                                    <div class="col">
                                        <label class="form-label small">Nb voyageurs max</label>
                                        <input type="number" name="seg_personnes_max" class="form-control form-control-sm" min="1">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <label class="form-label small">Duree min (nuits)</label>
                                        <input type="number" name="seg_duree_min" class="form-control form-control-sm" min="1">
                                    </div>
                                    <div class="col">
                                        <label class="form-label small">Inactif depuis (mois)</label>
                                        <input type="number" name="seg_inactif_mois" class="form-control form-control-sm" min="1" placeholder="Ex: 6">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" name="preview_segment" class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fas fa-eye"></i> Previsualiser
                                    </button>
                                    <button type="submit" name="create_segment" class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-save"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <?php if ($action === 'preview' && !empty($preview_contacts)): ?>
                    <!-- Preview segment -->
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-eye"></i> Apercu : <?= count($preview_contacts) ?> contact(s)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Nom</th><th>Telephone</th><th>Dernier sejour</th><th>Nb sejours</th><th>Logement</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($preview_contacts as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')) ?></td>
                                        <td><code><?= htmlspecialchars($c['telephone']) ?></code></td>
                                        <td><?= $c['dernier_sejour'] ? date('d/m/Y', strtotime($c['dernier_sejour'])) : '-' ?></td>
                                        <td class="text-center"><?= $c['nb_sejours'] ?></td>
                                        <td><?= htmlspecialchars($c['nom_du_logement'] ?? '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Segments existants -->
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-layer-group"></i> Segments enregistres (<?= count($segments) ?>)</h6></div>
                        <div class="card-body">
                            <?php if (empty($segments)): ?>
                                <p class="text-center text-muted">Aucun segment cree</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Nom</th><th>Criteres</th><th class="text-center">Contacts</th><th>Date</th><th></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($segments as $seg):
                                            $crit = json_decode($seg['criteres'], true) ?: [];
                                            $crit_labels = [];
                                            if (!empty($crit['logement_id'])) $crit_labels[] = 'Logement #' . $crit['logement_id'];
                                            if (!empty($crit['date_sejour_min'])) $crit_labels[] = 'Apres ' . date('d/m/Y', strtotime($crit['date_sejour_min']));
                                            if (!empty($crit['date_sejour_max'])) $crit_labels[] = 'Avant ' . date('d/m/Y', strtotime($crit['date_sejour_max']));
                                            if (!empty($crit['pas_sejour_depuis_mois'])) $crit_labels[] = 'Inactif ' . $crit['pas_sejour_depuis_mois'] . 'm';
                                            if (!empty($crit['duree_min'])) $crit_labels[] = $crit['duree_min'] . '+ nuits';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($seg['nom']) ?></strong>
                                                <?php if ($seg['description']): ?><br><small class="text-muted"><?= htmlspecialchars($seg['description']) ?></small><?php endif; ?>
                                            </td>
                                            <td class="small"><?= implode(', ', $crit_labels) ?: '<em class="text-muted">Tous</em>' ?></td>
                                            <td class="text-center"><span class="badge bg-primary"><?= $seg['nb_contacts'] ?></span></td>
                                            <td class="small"><?= date('d/m/Y', strtotime($seg['created_at'])) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce segment ?')">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="segment_id" value="<?= $seg['id'] ?>">
                                                    <button type="submit" name="delete_segment" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB CAMPAGNES -->
        <div class="tab-pane fade <?= in_array($action, ['campagnes','detail_campagne']) ? 'show active' : '' ?>" id="campagnes">

            <?php if ($action === 'detail_campagne' && $detail_campagne): ?>
            <!-- Detail campagne -->
            <div class="mb-3">
                <a href="relances_voyageurs.php?action=campagnes" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0"><?= htmlspecialchars($detail_campagne['nom']) ?></h5>
                    <span class="badge bg-<?= $detail_campagne['statut'] === 'envoyee' ? 'success' : ($detail_campagne['statut'] === 'brouillon' ? 'warning' : 'secondary') ?>">
                        <?= ucfirst($detail_campagne['statut']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 text-center">
                            <div class="h3"><?= $detail_campagne['total_destinataires'] ?></div>
                            <small class="text-muted">Destinataires</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h3 text-success"><?= $detail_campagne['total_envoyes'] ?></div>
                            <small class="text-muted">Envoyes</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h3 text-danger"><?= $detail_campagne['total_echecs'] ?></div>
                            <small class="text-muted">Echecs</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="h3"><?= htmlspecialchars($detail_campagne['segment_nom'] ?? '-') ?></div>
                            <small class="text-muted">Segment</small>
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded">
                        <strong>Message :</strong><br>
                        <?= nl2br(htmlspecialchars($detail_campagne['message_template'])) ?>
                    </div>

                    <?php if ($detail_campagne['statut'] === 'brouillon' && $detail_campagne['total_destinataires'] > 0): ?>
                    <form method="POST" onsubmit="return confirm('Envoyer cette campagne a <?= $detail_campagne['total_destinataires'] ?> destinataire(s) ?')">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="campagne_id" value="<?= $detail_campagne['id'] ?>">
                        <button type="submit" name="send_campagne" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Envoyer la campagne
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Liste des envois -->
            <?php if (!empty($detail_envois)): ?>
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Destinataires (<?= count($detail_envois) ?>)</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Nom</th><th>Telephone</th><th>Statut</th><th>Envoye le</th></tr></thead>
                            <tbody>
                            <?php foreach ($detail_envois as $env): ?>
                            <tr>
                                <td><?= htmlspecialchars(($env['prenom'] ?? '') . ' ' . ($env['nom'] ?? '')) ?></td>
                                <td><code><?= htmlspecialchars($env['telephone']) ?></code></td>
                                <td>
                                    <?php if ($env['statut'] === 'envoye'): ?>
                                    <span class="badge bg-success">Envoye</span>
                                    <?php elseif ($env['statut'] === 'echec'): ?>
                                    <span class="badge bg-danger" title="<?= htmlspecialchars($env['error_message'] ?? '') ?>">Echec</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= $env['sent_at'] ? date('d/m/Y H:i', strtotime($env['sent_at'])) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- Creer + liste campagnes -->
            <div class="row">
                <div class="col-md-5">
                    <div class="card mb-3">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus"></i> Nouvelle campagne</h6></div>
                        <div class="card-body">
                            <form method="POST">
                                <?php echoCsrfField(); ?>
                                <div class="mb-2">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" name="camp_nom" class="form-control form-control-sm" required placeholder="Ex: Relance ete 2026">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Segment cible</label>
                                    <select name="camp_segment" class="form-select form-select-sm">
                                        <option value="">-- Aucun (manuel) --</option>
                                        <?php foreach ($segments as $seg): ?>
                                        <option value="<?= $seg['id'] ?>"><?= htmlspecialchars($seg['nom']) ?> (<?= $seg['nb_contacts'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Type</label>
                                    <select name="camp_type" class="form-select form-select-sm">
                                        <option value="sms">SMS</option>
                                        <option value="email">Email</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Message *</label>
                                    <textarea name="camp_message" class="form-control form-control-sm" rows="4" required placeholder="Bonjour {prenom}, nous esperons que vous avez passe un bon sejour..."></textarea>
                                    <small class="text-muted">Variables : {prenom}, {nom}</small>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Date d'envoi prevue</label>
                                    <input type="datetime-local" name="camp_date_envoi" class="form-control form-control-sm">
                                </div>
                                <button type="submit" name="create_campagne" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-plus"></i> Creer la campagne
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0"><i class="fas fa-list"></i> Campagnes (<?= count($campagnes) ?>)</h6></div>
                        <div class="card-body">
                            <?php if (empty($campagnes)): ?>
                                <p class="text-center text-muted">Aucune campagne creee</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead><tr><th>Nom</th><th>Segment</th><th>Type</th><th class="text-center">Dest.</th><th>Statut</th><th></th></tr></thead>
                                        <tbody>
                                        <?php foreach ($campagnes as $c): ?>
                                        <tr>
                                            <td>
                                                <a href="?id=<?= $c['id'] ?>"><strong><?= htmlspecialchars($c['nom']) ?></strong></a>
                                                <br><small class="text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($c['segment_nom'] ?? '-') ?></td>
                                            <td><span class="badge bg-<?= $c['type'] === 'sms' ? 'info' : 'secondary' ?>"><?= strtoupper($c['type']) ?></span></td>
                                            <td class="text-center">
                                                <?= $c['total_destinataires'] ?>
                                                <?php if ($c['total_envoyes'] > 0): ?>
                                                <br><small class="text-success"><?= $c['total_envoyes'] ?> ok</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $c['statut'] === 'envoyee' ? 'success' : ($c['statut'] === 'brouillon' ? 'warning text-dark' : 'secondary') ?>">
                                                    <?= ucfirst($c['statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($c['statut'] === 'brouillon'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="campagne_id" value="<?= $c['id'] ?>">
                                                    <button type="submit" name="delete_campagne" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
