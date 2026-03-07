<?php
/**
 * Pipeline de prospection proprietaires
 * CRM simplifie pour convertir les multi-proprietaires de la concurrence en clients
 * Source : analyse_concurrence.php (market_competitors)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$rpi = getRpiPdo();

// Auto-creation de la table prospection
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS prospection_proprietaires (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            ville VARCHAR(100) DEFAULT NULL,
            nb_annonces INT DEFAULT 0,
            note_moyenne DECIMAL(3,2) DEFAULT NULL,
            host_profile_id VARCHAR(50) DEFAULT NULL,
            source ENUM('concurrence', 'recommandation', 'demarchage', 'entrant') DEFAULT 'concurrence',
            statut ENUM('identifie', 'contacte', 'en_discussion', 'proposition', 'converti', 'perdu') DEFAULT 'identifie',
            priorite ENUM('basse', 'moyenne', 'haute') DEFAULT 'moyenne',
            notes TEXT DEFAULT NULL,
            prochaine_action VARCHAR(255) DEFAULT NULL,
            date_prochaine_action DATE DEFAULT NULL,
            date_premier_contact DATE DEFAULT NULL,
            date_conversion DATE DEFAULT NULL,
            proprietaire_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_host (host_profile_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Table historique des interactions
    $conn->exec("
        CREATE TABLE IF NOT EXISTS prospection_historique (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prospect_id INT NOT NULL,
            type ENUM('appel', 'email', 'sms', 'rdv', 'visite', 'note') DEFAULT 'note',
            contenu TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prospect_id) REFERENCES prospection_proprietaires(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Tables existent deja
}

$feedback = '';

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Importer depuis concurrence
    if (isset($_POST['import_concurrence'])) {
        try {
            $multiOwners = $rpi->query("
                SELECT host_name, host_profile_id, COUNT(*) as nb_annonces,
                       ROUND(AVG(note_moyenne), 2) as note_moy,
                       GROUP_CONCAT(DISTINCT ville SEPARATOR ', ') as villes
                FROM market_competitors
                WHERE host_profile_id IS NOT NULL AND host_profile_id != ''
                GROUP BY host_profile_id
                HAVING COUNT(*) > 1
                ORDER BY nb_annonces DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $imported = 0;
            $stmt = $conn->prepare("
                INSERT IGNORE INTO prospection_proprietaires
                    (nom, host_profile_id, nb_annonces, note_moyenne, ville, source, statut)
                VALUES (?, ?, ?, ?, ?, 'concurrence', 'identifie')
            ");
            foreach ($multiOwners as $o) {
                $stmt->execute([
                    $o['host_name'] ?: 'Inconnu',
                    $o['host_profile_id'],
                    $o['nb_annonces'],
                    $o['note_moy'],
                    $o['villes']
                ]);
                if ($stmt->rowCount() > 0) $imported++;
            }
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $imported nouveau(x) prospect(s) importe(s) depuis l'analyse concurrentielle</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur import : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Creer un prospect manuellement
    if (isset($_POST['create_prospect'])) {
        $nom = trim($_POST['nom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $source = $_POST['source'] ?? 'demarchage';
        $priorite = $_POST['priorite'] ?? 'moyenne';
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($nom)) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO prospection_proprietaires (nom, telephone, email, ville, source, priorite, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nom, $telephone ?: null, $email ?: null, $ville ?: null, $source, $priorite, $notes ?: null]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Prospect cree avec succes</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Mettre a jour le statut
    if (isset($_POST['update_statut'])) {
        $id = (int)$_POST['prospect_id'];
        $statut = $_POST['statut'];
        $extraSql = '';
        $extraParams = [];

        if ($statut === 'contacte' || $statut === 'en_discussion') {
            $extraSql = ', date_premier_contact = COALESCE(date_premier_contact, CURDATE())';
        }
        if ($statut === 'converti') {
            $extraSql .= ', date_conversion = CURDATE()';
        }

        try {
            $conn->prepare("UPDATE prospection_proprietaires SET statut = ?, updated_at = NOW() $extraSql WHERE id = ?")
                ->execute([$statut, $id]);
            $feedback = "<div class='alert alert-success'>Statut mis a jour</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Mettre a jour un prospect complet
    if (isset($_POST['update_prospect'])) {
        $id = (int)$_POST['prospect_id'];
        try {
            $conn->prepare("
                UPDATE prospection_proprietaires SET
                    nom = ?, telephone = ?, email = ?, ville = ?,
                    source = ?, priorite = ?, notes = ?,
                    prochaine_action = ?, date_prochaine_action = ?
                WHERE id = ?
            ")->execute([
                trim($_POST['nom']),
                trim($_POST['telephone']) ?: null,
                trim($_POST['email']) ?: null,
                trim($_POST['ville']) ?: null,
                $_POST['source'],
                $_POST['priorite'],
                trim($_POST['notes']) ?: null,
                trim($_POST['prochaine_action']) ?: null,
                !empty($_POST['date_prochaine_action']) ? $_POST['date_prochaine_action'] : null,
                $id
            ]);
            $feedback = "<div class='alert alert-success'>Prospect mis a jour</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Ajouter une interaction
    if (isset($_POST['add_interaction'])) {
        $prospect_id = (int)$_POST['prospect_id'];
        $type = $_POST['type_interaction'] ?? 'note';
        $contenu = trim($_POST['contenu'] ?? '');

        if (!empty($contenu)) {
            try {
                $conn->prepare("INSERT INTO prospection_historique (prospect_id, type, contenu) VALUES (?, ?, ?)")
                    ->execute([$prospect_id, $type, $contenu]);
                $feedback = "<div class='alert alert-success'>Interaction ajoutee</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Supprimer un prospect
    if (isset($_POST['delete_prospect'])) {
        $id = (int)$_POST['prospect_id'];
        try {
            $conn->prepare("DELETE FROM prospection_proprietaires WHERE id = ?")->execute([$id]);
            $feedback = "<div class='alert alert-success'>Prospect supprime</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// === DONNEES ===
$statuts = ['identifie', 'contacte', 'en_discussion', 'proposition', 'converti', 'perdu'];
$statut_labels = [
    'identifie' => ['Identifie', 'secondary'],
    'contacte' => ['Contacte', 'info'],
    'en_discussion' => ['En discussion', 'primary'],
    'proposition' => ['Proposition', 'warning'],
    'converti' => ['Converti', 'success'],
    'perdu' => ['Perdu', 'danger'],
];

$prospects = $conn->query("
    SELECT * FROM prospection_proprietaires
    ORDER BY
        CASE statut
            WHEN 'proposition' THEN 1
            WHEN 'en_discussion' THEN 2
            WHEN 'contacte' THEN 3
            WHEN 'identifie' THEN 4
            WHEN 'converti' THEN 5
            WHEN 'perdu' THEN 6
        END,
        CASE priorite WHEN 'haute' THEN 1 WHEN 'moyenne' THEN 2 WHEN 'basse' THEN 3 END,
        updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Stats pipeline
$stats_pipeline = [];
foreach ($statuts as $s) {
    $stats_pipeline[$s] = count(array_filter($prospects, fn($p) => $p['statut'] === $s));
}
$total_prospects = count($prospects);

// Actions a venir
$actions_prochaines = array_filter($prospects, fn($p) =>
    !empty($p['date_prochaine_action']) && $p['date_prochaine_action'] <= date('Y-m-d', strtotime('+7 days'))
    && !in_array($p['statut'], ['converti', 'perdu'])
);

// Vue detail
$view = $_GET['view'] ?? 'pipeline';
$detail_id = (int)($_GET['id'] ?? 0);
$detail = null;
$historique = [];
if ($detail_id > 0) {
    $view = 'detail';
    $stmt = $conn->prepare("SELECT * FROM prospection_proprietaires WHERE id = ?");
    $stmt->execute([$detail_id]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($detail) {
        $historique = $conn->prepare("SELECT * FROM prospection_historique WHERE prospect_id = ? ORDER BY created_at DESC");
        $historique->execute([$detail_id]);
        $historique = $historique->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prospection Proprietaires — FrenchyConciergerie</title>
    <style>
        .pipeline-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 10px; }
        .pipeline-col {
            min-width: 220px; flex: 1; background: #f8f9fa; border-radius: 10px; padding: 10px;
        }
        .pipeline-col h6 {
            text-align: center; padding: 8px; border-radius: 8px; color: #fff; margin-bottom: 10px;
            font-size: 0.85em;
        }
        .prospect-card {
            background: #fff; border-radius: 8px; padding: 10px; margin-bottom: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); cursor: pointer; transition: transform 0.1s;
        }
        .prospect-card:hover { transform: translateY(-2px); box-shadow: 0 3px 8px rgba(0,0,0,0.12); }
        .prospect-card .name { font-weight: 700; font-size: 0.9em; }
        .prospect-card .meta { font-size: 0.75em; color: #888; }
        .priorite-haute { border-left: 3px solid #dc3545; }
        .priorite-moyenne { border-left: 3px solid #ffc107; }
        .priorite-basse { border-left: 3px solid #6c757d; }
        .action-urgente { background: #fff3cd !important; }
        .funnel-bar { height: 30px; border-radius: 6px; margin-bottom: 4px; display: flex; align-items: center; padding: 0 10px; color: #fff; font-weight: 600; font-size: 0.85em; }
        @media (max-width: 768px) {
            .pipeline-board { flex-direction: column; }
            .pipeline-col { min-width: 100%; }
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-funnel-dollar"></i> Prospection Proprietaires</h2>
            <p class="text-muted mb-0">Pipeline d'acquisition depuis l'analyse concurrentielle</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <?php echoCsrfField(); ?>
                <button type="submit" name="import_concurrence" class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Importer concurrence
                </button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreer">
                <i class="fas fa-plus"></i> Nouveau prospect
            </button>
        </div>
    </div>

    <!-- Stats rapides -->
    <div class="row mb-3">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0"><?= $total_prospects ?></div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
        <?php
        $funnel_colors = ['identifie'=>'#6c757d','contacte'=>'#0dcaf0','en_discussion'=>'#0d6efd','proposition'=>'#ffc107','converti'=>'#198754','perdu'=>'#dc3545'];
        foreach (['identifie','contacte','en_discussion','proposition','converti'] as $s):
            [$label, $badge] = $statut_labels[$s];
        ?>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h4 mb-0" style="color:<?= $funnel_colors[$s] ?>"><?= $stats_pipeline[$s] ?></div>
                    <small class="text-muted"><?= $label ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($view === 'detail' && $detail): ?>
    <!-- VUE DETAIL -->
    <div class="mb-3">
        <a href="prospection_proprietaires.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Retour pipeline</a>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($detail['nom']) ?></h5>
                    <span class="badge bg-<?= $statut_labels[$detail['statut']][1] ?>"><?= $statut_labels[$detail['statut']][0] ?></span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="prospect_id" value="<?= $detail['id'] ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($detail['nom']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ville</label>
                                <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($detail['ville'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Telephone</label>
                                <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($detail['telephone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($detail['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Source</label>
                                <select name="source" class="form-select">
                                    <?php foreach (['concurrence','recommandation','demarchage','entrant'] as $src): ?>
                                    <option value="<?= $src ?>" <?= $detail['source'] === $src ? 'selected' : '' ?>><?= ucfirst($src) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priorite</label>
                                <select name="priorite" class="form-select">
                                    <?php foreach (['haute','moyenne','basse'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $detail['priorite'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Profil Airbnb</label>
                                <?php if ($detail['host_profile_id']): ?>
                                <a href="https://www.airbnb.fr/users/show/<?= htmlspecialchars($detail['host_profile_id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary d-block">
                                    <i class="fas fa-external-link-alt"></i> Voir profil
                                </a>
                                <?php else: ?>
                                <input type="text" class="form-control" disabled value="Non renseigne">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Prochaine action</label>
                                <input type="text" name="prochaine_action" class="form-control" value="<?= htmlspecialchars($detail['prochaine_action'] ?? '') ?>" placeholder="Ex: Appeler pour proposer un RDV">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date prochaine action</label>
                                <input type="date" name="date_prochaine_action" class="form-control" value="<?= htmlspecialchars($detail['date_prochaine_action'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($detail['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_prospect" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                            <button type="submit" name="delete_prospect" class="btn btn-outline-danger" onclick="return confirm('Supprimer ce prospect ?')"><i class="fas fa-trash"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Changer statut rapidement -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Avancer dans le pipeline</h6></div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <?php foreach ($statuts as $s):
                        [$label, $badge] = $statut_labels[$s];
                        $active = $detail['statut'] === $s;
                    ?>
                    <form method="POST" class="d-inline">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="prospect_id" value="<?= $detail['id'] ?>">
                        <input type="hidden" name="statut" value="<?= $s ?>">
                        <button type="submit" name="update_statut" class="btn btn-<?= $active ? '' : 'outline-' ?><?= $badge ?> btn-sm" <?= $active ? 'disabled' : '' ?>>
                            <?= $label ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Infos concurrence -->
            <?php if ($detail['nb_annonces'] > 0): ?>
            <div class="card mb-3">
                <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-chart-bar"></i> Donnees concurrence</h6></div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><strong><?= $detail['nb_annonces'] ?></strong><br><small class="text-muted">Annonces</small></div>
                        <div class="col"><strong><?= $detail['note_moyenne'] ?: '-' ?></strong><br><small class="text-muted">Note moy.</small></div>
                        <div class="col"><strong><?= htmlspecialchars($detail['ville'] ?? '-') ?></strong><br><small class="text-muted">Ville(s)</small></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-5">
            <!-- Ajouter interaction -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus"></i> Ajouter une interaction</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="prospect_id" value="<?= $detail['id'] ?>">
                        <div class="mb-2">
                            <select name="type_interaction" class="form-select form-select-sm">
                                <option value="appel">Appel</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="rdv">RDV</option>
                                <option value="visite">Visite</option>
                                <option value="note">Note</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="contenu" class="form-control form-control-sm" rows="3" placeholder="Details de l'interaction..." required></textarea>
                        </div>
                        <button type="submit" name="add_interaction" class="btn btn-sm btn-primary w-100"><i class="fas fa-plus"></i> Ajouter</button>
                    </form>
                </div>
            </div>

            <!-- Historique -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-history"></i> Historique (<?= count($historique) ?>)</h6></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($historique)): ?>
                        <p class="text-muted text-center">Aucune interaction enregistree</p>
                    <?php else: ?>
                        <?php
                        $type_icons = ['appel'=>'fa-phone','email'=>'fa-envelope','sms'=>'fa-comment','rdv'=>'fa-handshake','visite'=>'fa-home','note'=>'fa-sticky-note'];
                        foreach ($historique as $h):
                        ?>
                        <div class="d-flex gap-2 mb-3 pb-2 border-bottom">
                            <div>
                                <i class="fas <?= $type_icons[$h['type']] ?? 'fa-circle' ?> text-primary mt-1"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <strong class="small"><?= ucfirst($h['type']) ?></strong>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></small>
                                </div>
                                <p class="mb-0 small"><?= nl2br(htmlspecialchars($h['contenu'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- VUE PIPELINE -->

    <!-- Actions a venir -->
    <?php if (!empty($actions_prochaines)): ?>
    <div class="alert alert-warning mb-3">
        <strong><i class="fas fa-bell"></i> Actions a venir (7 jours) :</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($actions_prochaines as $ap): ?>
            <li>
                <a href="?id=<?= $ap['id'] ?>"><?= htmlspecialchars($ap['nom']) ?></a> —
                <?= htmlspecialchars($ap['prochaine_action']) ?>
                <span class="badge bg-<?= $ap['date_prochaine_action'] <= date('Y-m-d') ? 'danger' : 'warning' ?>">
                    <?= date('d/m', strtotime($ap['date_prochaine_action'])) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Entonnoir visuel -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <?php
            $max = max(1, max($stats_pipeline));
            foreach (['identifie','contacte','en_discussion','proposition','converti'] as $s):
                $w = max(20, ($stats_pipeline[$s] / $max) * 100);
                [$label, $badge] = $statut_labels[$s];
            ?>
            <div class="funnel-bar" style="width:<?= $w ?>%;background:<?= $funnel_colors[$s] ?>;">
                <?= $label ?> (<?= $stats_pipeline[$s] ?>)
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Board pipeline -->
    <div class="pipeline-board">
        <?php foreach (['identifie','contacte','en_discussion','proposition'] as $col_statut):
            [$label, $badge] = $statut_labels[$col_statut];
            $col_prospects = array_filter($prospects, fn($p) => $p['statut'] === $col_statut);
        ?>
        <div class="pipeline-col">
            <h6 style="background:<?= $funnel_colors[$col_statut] ?>;"><?= $label ?> (<?= count($col_prospects) ?>)</h6>
            <?php foreach ($col_prospects as $p):
                $urgente = !empty($p['date_prochaine_action']) && $p['date_prochaine_action'] <= date('Y-m-d');
            ?>
            <a href="?id=<?= $p['id'] ?>" class="text-decoration-none">
                <div class="prospect-card priorite-<?= $p['priorite'] ?> <?= $urgente ? 'action-urgente' : '' ?>">
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="meta">
                        <?php if ($p['nb_annonces']): ?>
                            <i class="fas fa-home"></i> <?= $p['nb_annonces'] ?> annonces
                        <?php endif; ?>
                        <?php if ($p['ville']): ?>
                            &middot; <?= htmlspecialchars(mb_substr($p['ville'], 0, 20)) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['prochaine_action']): ?>
                    <div class="meta mt-1" style="color:<?= $urgente ? '#dc3545' : '#666' ?>">
                        <i class="fas fa-clock"></i> <?= htmlspecialchars(mb_substr($p['prochaine_action'], 0, 30)) ?>
                    </div>
                    <?php endif; ?>
                    <div class="mt-1">
                        <span class="badge bg-<?= $p['priorite'] === 'haute' ? 'danger' : ($p['priorite'] === 'moyenne' ? 'warning text-dark' : 'secondary') ?>" style="font-size:0.65em;">
                            <?= ucfirst($p['priorite']) ?>
                        </span>
                        <span class="badge bg-light text-dark" style="font-size:0.65em;"><?= ucfirst($p['source']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Colonne convertis -->
        <div class="pipeline-col">
            <h6 style="background:#198754;">Convertis (<?= $stats_pipeline['converti'] ?>)</h6>
            <?php foreach (array_filter($prospects, fn($p) => $p['statut'] === 'converti') as $p): ?>
            <a href="?id=<?= $p['id'] ?>" class="text-decoration-none">
                <div class="prospect-card" style="border-left:3px solid #198754;">
                    <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="meta"><i class="fas fa-check"></i> Converti le <?= $p['date_conversion'] ? date('d/m/Y', strtotime($p['date_conversion'])) : '?' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Liste des perdus -->
    <?php $perdus = array_filter($prospects, fn($p) => $p['statut'] === 'perdu'); ?>
    <?php if (!empty($perdus)): ?>
    <div class="mt-3">
        <h6 class="text-muted"><i class="fas fa-times-circle"></i> Perdus (<?= count($perdus) ?>)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Nom</th><th>Ville</th><th>Source</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($perdus as $p): ?>
                <tr class="text-muted">
                    <td><?= htmlspecialchars($p['nom']) ?></td>
                    <td><?= htmlspecialchars($p['ville'] ?? '-') ?></td>
                    <td><?= ucfirst($p['source']) ?></td>
                    <td><?= htmlspecialchars(mb_substr($p['notes'] ?? '', 0, 50)) ?></td>
                    <td><a href="?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Modal creer prospect -->
<div class="modal fade" id="modalCreer" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nouveau prospect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Telephone</label>
                            <input type="text" name="telephone" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-select">
                                <option value="demarchage">Demarchage</option>
                                <option value="recommandation">Recommandation</option>
                                <option value="entrant">Entrant</option>
                                <option value="concurrence">Concurrence</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priorite</label>
                        <select name="priorite" class="form-select">
                            <option value="haute">Haute</option>
                            <option value="moyenne" selected>Moyenne</option>
                            <option value="basse">Basse</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_prospect" class="btn btn-primary"><i class="fas fa-check"></i> Creer</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
