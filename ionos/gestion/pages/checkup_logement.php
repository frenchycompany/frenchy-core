<?php
/**
 * Checkup Logement — Lancement d'un checkup
 * Hub central : equipements + inventaire + taches + etat general
 */

// Debug : attraper les erreurs fatales silencieuses en production
ini_set('display_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo '<pre style="background:#fdd;padding:20px;margin:20px;border:2px solid #c00;border-radius:8px;">';
        echo "<b>Erreur fatale :</b> {$error['message']}\n";
        echo "Fichier : {$error['file']}\n";
        echo "Ligne : {$error['line']}\n";
        echo '</pre>';
    }
});

include '../config.php';
include '../pages/menu.php';

// Tables requises : voir db/install_tables.php

// Ajouter les colonnes manquantes sur tables existantes
try { $conn->exec("ALTER TABLE checkup_items ADD COLUMN todo_task_id INT DEFAULT NULL AFTER photo_path"); } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }
try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN nb_taches_faites INT DEFAULT 0 AFTER nb_absents"); } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }
try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN signature_path VARCHAR(500) DEFAULT NULL AFTER commentaire_general"); } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }
try { $conn->exec("ALTER TABLE checkup_sessions ADD COLUMN video_path VARCHAR(500) DEFAULT NULL AFTER signature_path"); } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }

// AJAX : preview du logement quand on le selectionne
if (isset($_GET['ajax_preview']) && isset($_GET['logement_id'])) {
    header('Content-Type: application/json');
    $lid = intval($_GET['logement_id']);

    // Taches en attente
    $nbTaches = 0;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM todo_list WHERE logement_id = ? AND statut IN ('en attente','en cours')");
        $stmt->execute([$lid]);
        $nbTaches = $stmt->fetchColumn();
    } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }

    // Dernier inventaire
    $lastInv = null;
    try {
        $stmt = $conn->prepare("SELECT s.date_creation, COUNT(o.id) AS nb_objets FROM sessions_inventaire s LEFT JOIN inventaire_objets o ON o.session_id = s.id WHERE s.logement_id = ? AND s.statut = 'terminee' GROUP BY s.id ORDER BY s.date_creation DESC LIMIT 1");
        $stmt->execute([$lid]);
        $lastInv = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }

    // Equipements renseignes
    $hasEquip = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM logement_equipements WHERE logement_id = ?");
        $stmt->execute([$lid]);
        $hasEquip = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) { error_log('checkup_logement.php: ' . $e->getMessage()); }

    // Session en cours
    $stmt = $conn->prepare("SELECT id FROM checkup_sessions WHERE logement_id = ? AND statut = 'en_cours' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$lid]);
    $enCours = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'nb_taches' => (int)$nbTaches,
        'dernier_inventaire' => $lastInv ? date('d/m/Y', strtotime($lastInv['date_creation'])) : null,
        'nb_objets_inventaire' => $lastInv ? (int)$lastInv['nb_objets'] : 0,
        'has_equipements' => $hasEquip,
        'session_en_cours' => $enCours ? $enCours['id'] : null,
    ]);
    exit;
}

// AJAX : changer l'intervenant d'un checkup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_change_intervenant'])) {
    header('Content-Type: application/json');
    $ckId = intval($_POST['session_id'] ?? 0);
    $newId = intval($_POST['intervenant_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE checkup_sessions SET intervenant_id = ? WHERE id = ?");
    $stmt->execute([$newId ?: null, $ckId]);
    $nom = 'Non attribue';
    if ($newId) {
        $nStmt = $conn->prepare("SELECT nom FROM intervenant WHERE id = ?");
        $nStmt->execute([$newId]);
        $nom = $nStmt->fetchColumn() ?: 'Inconnu';
    }
    echo json_encode(['success' => true, 'nom' => $nom]);
    exit;
}

// Charger la fonction createCheckupSession() depuis le fichier partage
require_once __DIR__ . '/../includes/checkup_create.php';

// Traitement : creer une session de checkup via le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logement_id'])) {
    $logement_id = intval($_POST['logement_id']);
    $intervenant_id = $_SESSION['id_intervenant'] ?? null;
    $session_id = createCheckupSession($conn, $logement_id, $intervenant_id);

    // Rediriger vers la page de checkup
    header("Location: checkup_faire.php?session_id=" . $session_id);
    exit;
}

// Recuperer les logements avec infos rapides
try {
    $logements = $conn->query("
        SELECT l.id, l.nom_du_logement,
               (SELECT COUNT(*) FROM todo_list t WHERE t.logement_id = l.id AND t.statut IN ('en attente','en cours')) AS nb_taches
        FROM liste_logements l
        WHERE l.actif = 1
        ORDER BY l.nom_du_logement
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback sans compteur todo_list si la table n'existe pas
    try {
        $logements = $conn->query("
            SELECT l.id, l.nom_du_logement, 0 AS nb_taches
            FROM liste_logements l
            WHERE l.actif = 1
            ORDER BY l.nom_du_logement
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $logements = [];
    }
}

// Recuperer les checkups recents
try {
    $recents = $conn->query("
        SELECT cs.*, l.nom_du_logement,
               COALESCE(i.nom, 'Inconnu') AS nom_intervenant
        FROM checkup_sessions cs
        JOIN liste_logements l ON cs.logement_id = l.id
        LEFT JOIN intervenant i ON cs.intervenant_id = i.id
        ORDER BY cs.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recents = [];
}

// Charger les intervenants pour le selecteur de reassignation
$intervenants = $conn->query("SELECT id, nom FROM intervenant WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkup Logement</title>
    <style>
        .checkup-container { max-width: 600px; margin: 20px auto; padding: 0 15px; }
        .checkup-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin-bottom: 25px;
        }
        .checkup-header h2 { margin: 0; font-size: 1.4em; }
        .checkup-header p { margin: 8px 0 0; opacity: 0.85; font-size: 0.95em; }
        .launch-card {
            background: #fff; border-radius: 15px; padding: 25px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;
        }
        .launch-card label { font-weight: 600; color: #333; margin-bottom: 10px; display: block; font-size: 1.05em; }
        .launch-card select {
            width: 100%; padding: 14px 12px; font-size: 1.1em;
            border: 2px solid #e0e0e0; border-radius: 10px;
            background: #fafafa; margin-bottom: 12px; appearance: auto;
        }
        .launch-card select:focus { border-color: #1976d2; outline: none; }
        /* Preview logement */
        .logement-preview {
            display: none; background: #f8fafd; border-radius: 12px;
            padding: 14px; margin-bottom: 15px; border: 1px solid #e3f2fd;
        }
        .logement-preview.visible { display: block; }
        .preview-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 0; font-size: 0.92em;
        }
        .preview-row .label { color: #666; }
        .preview-row .value { font-weight: 600; }
        .preview-badge {
            display: inline-block; padding: 3px 10px; border-radius: 15px;
            font-size: 0.82em; font-weight: 600;
        }
        .badge-warning { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e8f5e9; color: #2e7d32; }
        .badge-info { background: #e3f2fd; color: #1565c0; }
        .badge-none { background: #f5f5f5; color: #999; }
        .preview-encours {
            background: #fff3e0; border: 1px solid #ffcc80; border-radius: 10px;
            padding: 10px 14px; margin-bottom: 10px; font-size: 0.9em; color: #e65100;
        }
        .preview-encours a { color: #1565c0; font-weight: 600; }
        .btn-launch {
            width: 100%; padding: 16px; font-size: 1.15em; font-weight: 700;
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff; cursor: pointer; transition: transform 0.1s;
        }
        .btn-launch:active { transform: scale(0.97); }
        .history-title { font-size: 1.1em; font-weight: 600; color: #555; margin-bottom: 12px; }
        .history-card {
            background: #fff; border-radius: 12px; padding: 15px 18px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06); margin-bottom: 12px;
            display: flex; justify-content: space-between; align-items: center;
            text-decoration: none; color: inherit; transition: box-shadow 0.15s;
        }
        .history-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.12); }
        .history-info h4 { margin: 0 0 4px; font-size: 1em; color: #333; }
        .history-info small { color: #888; }
        .history-stats { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .stat-badge {
            display: inline-block; padding: 4px 10px;
            border-radius: 20px; font-size: 0.82em; font-weight: 600;
        }
        .stat-ok { background: #e8f5e9; color: #2e7d32; }
        .stat-problem { background: #fbe9e7; color: #c62828; }
        .stat-absent { background: #fff3e0; color: #e65100; }
        .stat-encours { background: #e3f2fd; color: #1565c0; }
        .stat-taches { background: #f3e5f5; color: #7b1fa2; }
        @media (max-width: 600px) {
            .checkup-container { margin: 10px auto; }
            .checkup-header { padding: 18px 10px; }
            .checkup-header h2 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
<div class="checkup-container">
    <div class="checkup-header">
        <h2><i class="fas fa-clipboard-check"></i> Checkup Logement</h2>
        <p>Equipements + Inventaire + Taches + Etat general</p>
    </div>

    <div class="launch-card">
        <form method="POST">
            <label for="logement_id"><i class="fas fa-home"></i> Choisir un logement</label>
            <select name="logement_id" id="logement_id" required onchange="loadPreview(this.value)">
                <option value="">-- Selectionnez --</option>
                <?php foreach ($logements as $l): ?>
                    <option value="<?= $l['id'] ?>"
                        <?= $l['nb_taches'] > 0 ? 'data-taches="' . $l['nb_taches'] . '"' : '' ?>>
                        <?= htmlspecialchars($l['nom_du_logement']) ?>
                        <?= $l['nb_taches'] > 0 ? ' (' . $l['nb_taches'] . ' tache' . ($l['nb_taches'] > 1 ? 's' : '') . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="logement-preview" id="preview">
                <div id="previewEnCours" class="preview-encours" style="display:none">
                    <i class="fas fa-exclamation-triangle"></i>
                    Un checkup est deja en cours pour ce logement.
                    <a id="linkEnCours" href="#">Reprendre</a> ou lancer un nouveau.
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-tasks"></i> Taches en attente</span>
                    <span class="value" id="prevTaches">—</span>
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-boxes-stacked"></i> Dernier inventaire</span>
                    <span class="value" id="prevInventaire">—</span>
                </div>
                <div class="preview-row">
                    <span class="label"><i class="fas fa-couch"></i> Equipements</span>
                    <span class="value" id="prevEquip">—</span>
                </div>
            </div>

            <button type="submit" class="btn-launch">
                <i class="fas fa-play-circle"></i> Lancer le checkup
            </button>
        </form>
    </div>

    <?php if (!empty($recents)): ?>
    <div class="history-title"><i class="fas fa-history"></i> Checkups recents</div>
    <?php foreach ($recents as $r): ?>
        <div class="history-card" style="display:block; cursor:default;">
            <a href="<?= $r['statut'] === 'en_cours' ? 'checkup_faire.php?session_id=' . $r['id'] : 'checkup_rapport.php?session_id=' . $r['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:inherit;">
                <div class="history-info">
                    <h4><?= htmlspecialchars($r['nom_du_logement']) ?></h4>
                    <small><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> — <span id="intName-<?= $r['id'] ?>"><?= htmlspecialchars($r['nom_intervenant']) ?></span></small>
                </div>
                <div class="history-stats">
                    <?php if ($r['statut'] === 'en_cours'): ?>
                        <span class="stat-badge stat-encours">En cours</span>
                    <?php else: ?>
                        <?php if ($r['nb_ok'] > 0): ?><span class="stat-badge stat-ok"><?= $r['nb_ok'] ?> OK</span><?php endif; ?>
                        <?php if ($r['nb_problemes'] > 0): ?><span class="stat-badge stat-problem"><?= $r['nb_problemes'] ?> pb</span><?php endif; ?>
                        <?php if ($r['nb_absents'] > 0): ?><span class="stat-badge stat-absent"><?= $r['nb_absents'] ?> abs</span><?php endif; ?>
                        <?php if ($r['nb_taches_faites'] > 0): ?><span class="stat-badge stat-taches"><?= $r['nb_taches_faites'] ?> taches</span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </a>
            <?php if ($r['statut'] === 'en_cours'): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;padding-top:8px;border-top:1px solid #eee;">
                <i class="fas fa-user-edit" style="color:#666;font-size:0.85em;"></i>
                <select onchange="changeIntervenantList(<?= $r['id'] ?>, this.value)" style="flex:1;padding:6px 8px;border:1px solid #ddd;border-radius:8px;font-size:0.88em;">
                    <option value="0" <?= empty($r['intervenant_id']) ? 'selected' : '' ?>>-- Non attribue --</option>
                    <?php foreach ($intervenants as $int): ?>
                        <option value="<?= $int['id'] ?>" <?= $int['id'] == $r['intervenant_id'] ? 'selected' : '' ?>><?= htmlspecialchars($int['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function changeIntervenantList(sessionId, intervenantId) {
    var fd = new FormData();
    fd.append('ajax_change_intervenant', '1');
    fd.append('session_id', sessionId);
    fd.append('intervenant_id', intervenantId);
    fetch('checkup_logement.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.getElementById('intName-' + sessionId);
                if (el) el.textContent = data.nom;
            }
        });
}

// Auto-select du logement si on vient d'un QR code
document.addEventListener('DOMContentLoaded', function() {
    var params = new URLSearchParams(window.location.search);
    var autoId = params.get('auto_logement');
    if (autoId) {
        var sel = document.getElementById('logement_id');
        sel.value = autoId;
        loadPreview(autoId);
    }
});

function loadPreview(logementId) {
    var preview = document.getElementById('preview');
    if (!logementId) { preview.classList.remove('visible'); return; }

    fetch('checkup_logement.php?ajax_preview=1&logement_id=' + logementId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            preview.classList.add('visible');

            // Taches
            var tEl = document.getElementById('prevTaches');
            if (data.nb_taches > 0) {
                tEl.innerHTML = '<span class="preview-badge badge-warning">' + data.nb_taches + ' en attente</span>';
            } else {
                tEl.innerHTML = '<span class="preview-badge badge-ok">Aucune</span>';
            }

            // Inventaire
            var iEl = document.getElementById('prevInventaire');
            if (data.dernier_inventaire) {
                iEl.innerHTML = '<span class="preview-badge badge-info">' + data.dernier_inventaire + ' (' + data.nb_objets_inventaire + ' obj.)</span>';
            } else {
                iEl.innerHTML = '<span class="preview-badge badge-none">Jamais fait</span>';
            }

            // Equipements
            var eEl = document.getElementById('prevEquip');
            eEl.innerHTML = data.has_equipements
                ? '<span class="preview-badge badge-ok">Renseignes</span>'
                : '<span class="preview-badge badge-none">Non renseignes</span>';

            // Session en cours
            var ecDiv = document.getElementById('previewEnCours');
            if (data.session_en_cours) {
                ecDiv.style.display = 'block';
                document.getElementById('linkEnCours').href = 'checkup_faire.php?session_id=' + data.session_en_cours;
            } else {
                ecDiv.style.display = 'none';
            }
        });
}
</script>
</body>
</html>
