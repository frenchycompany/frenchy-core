<?php
/**
 * Historique des checkups — Liste tous les checkups passes avec filtres
 */
include '../config.php';
include '../pages/menu.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Suppression d'un checkup (admin uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_checkup']) && $isAdmin) {
    $deleteId = (int)$_POST['delete_checkup'];

    // Recuperer les fichiers a supprimer (photos items + signature)
    $stmt = $conn->prepare("SELECT photo_path FROM checkup_items WHERE session_id = ? AND photo_path IS NOT NULL AND photo_path != ''");
    $stmt->execute([$deleteId]);
    $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    try {
        $stmt = $conn->prepare("SELECT signature_path FROM checkup_sessions WHERE id = ?");
        $stmt->execute([$deleteId]);
        $sigPath = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $sigPath = null;
    }

    // Supprimer les fichiers physiques
    foreach ($photos as $photo) {
        $fullPath = __DIR__ . '/../' . $photo;
        if (file_exists($fullPath)) @unlink($fullPath);
    }
    if ($sigPath) {
        $fullPath = __DIR__ . '/../' . $sigPath;
        if (file_exists($fullPath)) @unlink($fullPath);
    }

    // Supprimer en BDD (CASCADE supprime les checkup_items)
    $stmt = $conn->prepare("DELETE FROM checkup_sessions WHERE id = ?");
    $stmt->execute([$deleteId]);

    // Rediriger pour eviter le re-submit
    header("Location: checkup_historique.php?" . http_build_query($_GET));
    exit;
}

// Filtres
$logement_filter = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : null;
$intervenant_filter = isset($_GET['intervenant_id']) ? (int)$_GET['intervenant_id'] : null;
$date_debut = $_GET['date_debut'] ?? null;
$date_fin = $_GET['date_fin'] ?? null;

// Logements et intervenants pour les filtres
$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
$intervenants = $conn->query("SELECT id, nom FROM intervenant ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Construction requete
$query = "
    SELECT cs.*, l.nom_du_logement,
           COALESCE(i.nom, 'Inconnu') AS nom_intervenant,
           (cs.nb_ok + cs.nb_problemes + cs.nb_absents) AS total_items
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    LEFT JOIN intervenant i ON cs.intervenant_id = i.id
    WHERE 1=1
";
$params = [];

if ($logement_filter) {
    $query .= " AND cs.logement_id = :logement_id";
    $params[':logement_id'] = $logement_filter;
}
if ($statut_filter) {
    $query .= " AND cs.statut = :statut";
    $params[':statut'] = $statut_filter;
}
if ($intervenant_filter) {
    $query .= " AND cs.intervenant_id = :intervenant_id";
    $params[':intervenant_id'] = $intervenant_filter;
}
if ($date_debut) {
    $query .= " AND DATE(cs.created_at) >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if ($date_fin) {
    $query .= " AND DATE(cs.created_at) <= :date_fin";
    $params[':date_fin'] = $date_fin;
}

$query .= " ORDER BY cs.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$checkups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
$totalCheckups = count($checkups);
$termines = count(array_filter($checkups, fn($c) => $c['statut'] === 'termine'));
$enCours = $totalCheckups - $termines;
$avgScore = 0;
if ($termines > 0) {
    $scores = array_map(function($c) {
        $total = $c['total_items'] ?: 1;
        return round(($c['nb_ok'] / $total) * 100);
    }, array_filter($checkups, fn($c) => $c['statut'] === 'termine'));
    $avgScore = round(array_sum($scores) / count($scores));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Checkups</title>
    <style>
        .hist-container { max-width: 900px; margin: 0 auto; padding: 0 12px 40px; }
        .hist-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .hist-header h2 { margin: 0; font-size: 1.3em; }
        .hist-stats {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .hist-stat {
            flex: 1; min-width: 100px; background: #fff; border-radius: 12px;
            padding: 15px 12px; text-align: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07);
        }
        .hist-stat .number { font-size: 1.8em; font-weight: 800; line-height: 1; }
        .hist-stat .label { font-size: 0.82em; color: #666; margin-top: 4px; }
        .hist-filters {
            background: #fff; border-radius: 12px; padding: 18px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
        }
        .hist-filters h4 { margin: 0 0 12px; color: #555; font-size: 0.95em; }
        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px; align-items: end;
        }
        .filter-group label { display: block; font-size: 0.82em; color: #888; margin-bottom: 4px; }
        .filter-group select, .filter-group input {
            width: 100%; padding: 8px 10px; font-size: 0.95em;
            border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box;
        }
        .filter-group .btn-filter {
            padding: 8px 16px; background: #1976d2; color: #fff; border: none;
            border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%;
        }
        .filter-group .btn-reset {
            padding: 8px 16px; background: #e0e0e0; color: #555; border: none;
            border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%;
            margin-top: 4px;
        }
        .hist-card {
            background: #fff; border-radius: 12px; padding: 16px 18px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06); margin-bottom: 10px;
            display: flex; justify-content: space-between; align-items: center;
            text-decoration: none; color: inherit; transition: box-shadow 0.15s;
            border-left: 4px solid transparent;
        }
        .hist-card:hover { box-shadow: 0 3px 15px rgba(0,0,0,0.12); }
        .hist-card.status-termine { border-left-color: #43a047; }
        .hist-card.status-en_cours { border-left-color: #ff9800; }
        .hist-info h4 { margin: 0 0 4px; font-size: 1em; color: #333; }
        .hist-info small { color: #888; font-size: 0.85em; }
        .hist-badges { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.78em; font-weight: 600; }
        .badge-ok { background: #e8f5e9; color: #2e7d32; }
        .badge-pb { background: #fbe9e7; color: #c62828; }
        .badge-abs { background: #fff3e0; color: #e65100; }
        .badge-encours { background: #e3f2fd; color: #1565c0; }
        .badge-score { background: #f5f5f5; color: #333; }
        .badge-taches { background: #f3e5f5; color: #7b1fa2; }
        .hist-empty { text-align: center; padding: 40px 20px; color: #999; font-size: 1.05em; }
        .btn-delete-ck {
            padding: 6px 10px; background: #fbe9e7; color: #c62828;
            border: none; border-radius: 8px; font-size: 0.82em;
            font-weight: 600; cursor: pointer; white-space: nowrap;
            margin-left: 6px;
        }
        .btn-delete-ck:hover { background: #e53935; color: #fff; }
        /* Modal confirmation */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: 15px; padding: 30px 25px;
            max-width: 400px; width: 90%; text-align: center;
            box-shadow: 0 5px 30px rgba(0,0,0,0.2);
        }
        .modal-box h3 { margin: 0 0 10px; color: #c62828; font-size: 1.1em; }
        .modal-box p { margin: 0 0 20px; color: #666; font-size: 0.95em; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-actions button {
            padding: 10px 24px; border: none; border-radius: 8px;
            font-size: 0.95em; font-weight: 600; cursor: pointer;
        }
        .modal-cancel { background: #e0e0e0; color: #555; }
        .modal-confirm { background: #e53935; color: #fff; }
        @media (max-width: 600px) {
            .hist-stats { flex-wrap: wrap; }
            .hist-stat { min-width: calc(50% - 8px); }
            .filter-grid { grid-template-columns: 1fr 1fr; }
            .hist-card { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>
</head>
<body>
<div class="hist-container">
    <div class="hist-header">
        <h2><i class="fas fa-history"></i> Historique des Checkups</h2>
    </div>

    <!-- Stats globales -->
    <div class="hist-stats">
        <div class="hist-stat">
            <div class="number" style="color:#1976d2"><?= $totalCheckups ?></div>
            <div class="label">Total</div>
        </div>
        <div class="hist-stat">
            <div class="number" style="color:#43a047"><?= $termines ?></div>
            <div class="label">Termines</div>
        </div>
        <div class="hist-stat">
            <div class="number" style="color:#ff9800"><?= $enCours ?></div>
            <div class="label">En cours</div>
        </div>
        <div class="hist-stat">
            <div class="number" style="color:#1565c0"><?= $avgScore ?>%</div>
            <div class="label">Score moyen</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="hist-filters">
        <h4><i class="fas fa-filter"></i> Filtres</h4>
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Logement</label>
                    <select name="logement_id">
                        <option value="">Tous</option>
                        <?php foreach ($logements as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $logement_filter == $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['nom_du_logement']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select name="statut">
                        <option value="">Tous</option>
                        <option value="en_cours" <?= $statut_filter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $statut_filter === 'termine' ? 'selected' : '' ?>>Termine</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Intervenant</label>
                    <select name="intervenant_id">
                        <option value="">Tous</option>
                        <?php foreach ($intervenants as $i): ?>
                        <option value="<?= $i['id'] ?>" <?= $intervenant_filter == $i['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($i['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Du</label>
                    <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label>Au</label>
                    <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin ?? '') ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filtrer</button>
                    <a href="checkup_historique.php" class="btn-reset" style="display:block;text-align:center;text-decoration:none;margin-top:4px">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Liste des checkups -->
    <?php if (empty($checkups)): ?>
        <div class="hist-empty">
            <i class="fas fa-clipboard" style="font-size:2em;color:#ddd;display:block;margin-bottom:10px"></i>
            Aucun checkup trouve avec ces filtres.
        </div>
    <?php else: ?>
        <?php foreach ($checkups as $c):
            $total = $c['total_items'] ?: 1;
            $score = round(($c['nb_ok'] / $total) * 100);
        ?>
        <div class="hist-card status-<?= $c['statut'] ?>">
            <a href="<?= $c['statut'] === 'en_cours' ? 'checkup_faire.php?session_id=' . $c['id'] : 'checkup_rapport.php?session_id=' . $c['id'] ?>"
               style="flex:1;text-decoration:none;color:inherit;display:flex;justify-content:space-between;align-items:center;">
                <div class="hist-info">
                    <h4><?= htmlspecialchars($c['nom_du_logement']) ?></h4>
                    <small>
                        <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                        — <i class="fas fa-user"></i> <?= htmlspecialchars($c['nom_intervenant']) ?>
                    </small>
                </div>
                <div class="hist-badges">
                    <?php if ($c['statut'] === 'en_cours'): ?>
                        <span class="badge badge-encours">En cours</span>
                    <?php else: ?>
                        <span class="badge badge-score"><?= $score ?>%</span>
                        <?php if ($c['nb_ok'] > 0): ?><span class="badge badge-ok"><?= $c['nb_ok'] ?> OK</span><?php endif; ?>
                        <?php if ($c['nb_problemes'] > 0): ?><span class="badge badge-pb"><?= $c['nb_problemes'] ?> pb</span><?php endif; ?>
                        <?php if ($c['nb_absents'] > 0): ?><span class="badge badge-abs"><?= $c['nb_absents'] ?> abs</span><?php endif; ?>
                        <?php if ($c['nb_taches_faites'] > 0): ?><span class="badge badge-taches"><?= $c['nb_taches_faites'] ?> taches</span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ($isAdmin): ?>
            <button class="btn-delete-ck" onclick="event.stopPropagation(); confirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nom_du_logement'])) ?>', '<?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>')">
                <i class="fas fa-trash-alt"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<!-- Modal de confirmation de suppression -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3><i class="fas fa-exclamation-triangle"></i> Supprimer ce checkup ?</h3>
        <p id="deleteModalText">Cette action est irreversible.</p>
        <div class="modal-actions">
            <button class="modal-cancel" onclick="closeDeleteModal()">Annuler</button>
            <form method="POST" id="deleteForm" style="margin:0">
                <input type="hidden" name="delete_checkup" id="deleteCheckupId">
                <button type="submit" class="modal-confirm"><i class="fas fa-trash-alt"></i> Supprimer</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, logement, date) {
    document.getElementById('deleteCheckupId').value = id;
    document.getElementById('deleteModalText').innerHTML =
        '<strong>' + logement + '</strong><br><small style="color:#888">' + date + '</small><br><br>' +
        'Toutes les donnees, photos et signature seront supprimees.';
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Fermer en cliquant a l'exterieur
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
<?php endif; ?>
</body>
</html>
