<?php
/**
 * Liste des sessions d'inventaire — en cours + terminees
 */
include '../config.php';

$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// AJAX : suppression d'une session (admin uniquement) — avant le menu pour éviter le HTML dans la réponse JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_session'])) {
    header('Content-Type: application/json');
    if (!$is_admin) {
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    $del_id = $_POST['session_id'] ?? '';
    if (empty($del_id)) {
        echo json_encode(['error' => 'Session non spécifiée']);
        exit;
    }
    // Supprimer les fichiers photos et QR codes associés
    $stmtFiles = $conn->prepare("SELECT photo_path, qr_code_path FROM inventaire_objets WHERE session_id = ?");
    $stmtFiles->execute([$del_id]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $f) {
        if (!empty($f['photo_path']) && file_exists(__DIR__ . '/../' . $f['photo_path'])) {
            @unlink(__DIR__ . '/../' . $f['photo_path']);
        }
        if (!empty($f['qr_code_path']) && file_exists(__DIR__ . '/' . $f['qr_code_path'])) {
            @unlink(__DIR__ . '/' . $f['qr_code_path']);
        }
    }
    // Supprimer les objets puis la session
    $conn->prepare("DELETE FROM inventaire_objets WHERE session_id = ?")->execute([$del_id]);
    $conn->prepare("DELETE FROM sessions_inventaire WHERE id = ?")->execute([$del_id]);
    echo json_encode(['success' => true]);
    exit;
}

include '../pages/menu.php';

// Sessions en cours
$enCours = $conn->query("
    SELECT s.id, s.date_creation, s.statut, l.nom_du_logement,
           (SELECT COUNT(*) FROM inventaire_objets WHERE session_id = s.id) AS nb_objets
    FROM sessions_inventaire s
    JOIN liste_logements l ON s.logement_id = l.id
    WHERE s.statut = 'en_cours'
    ORDER BY s.date_creation DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Sessions terminees (les 30 dernieres)
$terminees = $conn->query("
    SELECT s.id, s.date_creation, s.statut, l.nom_du_logement,
           (SELECT COUNT(*) FROM inventaire_objets WHERE session_id = s.id) AS nb_objets
    FROM sessions_inventaire s
    JOIN liste_logements l ON s.logement_id = l.id
    WHERE s.statut = 'terminee'
    ORDER BY s.date_creation DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions d'inventaire</title>
    <style>
        .sessions-container {
            max-width: 650px;
            margin: 0 auto;
            padding: 0 12px 30px;
        }
        .sessions-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            text-align: center;
            padding: 22px 15px;
            border-radius: 15px;
            margin: 15px 0 20px;
        }
        .sessions-header h2 { margin: 0; font-size: 1.3em; }
        .section-title {
            font-size: 1.05em;
            font-weight: 700;
            color: #555;
            margin: 20px 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title .badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        .session-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px 18px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.06);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.15s;
        }
        .session-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.12); }
        .session-info h4 { margin: 0 0 4px; font-size: 1em; color: #333; }
        .session-info small { color: #888; }
        .session-stats {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .stat-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.82em;
            font-weight: 600;
        }
        .stat-encours { background: #fff3e0; color: #e65100; }
        .stat-terminee { background: #e8f5e9; color: #2e7d32; }
        .stat-objets { background: #e3f2fd; color: #1565c0; }
        .empty-msg {
            text-align: center;
            color: #999;
            padding: 20px 0;
            font-size: 0.95em;
        }
        .btn-new {
            display: inline-block;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1em;
            margin-top: 15px;
        }
        .session-card-wrap {
            position: relative;
        }
        .btn-delete-session {
            position: absolute;
            top: 50%;
            right: -40px;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #e53935;
            font-size: 1.1em;
            cursor: pointer;
            padding: 8px;
            opacity: 0.6;
            transition: opacity 0.15s;
        }
        .btn-delete-session:hover { opacity: 1; }
        @media (max-width: 600px) {
            .sessions-container { padding: 0 6px 30px; }
            .session-card { flex-direction: column; align-items: flex-start; gap: 8px; }
            .btn-delete-session { right: 5px; top: 5px; transform: none; }
        }
    </style>
</head>
<body>
<div class="sessions-container">
    <div class="sessions-header">
        <h2><i class="fas fa-clipboard-list"></i> Sessions d'inventaire</h2>
    </div>

    <!-- En cours -->
    <div class="section-title">
        <i class="fas fa-spinner" style="color:#e65100"></i>
        En cours
        <span class="badge"><?= count($enCours) ?></span>
    </div>
    <?php if (empty($enCours)): ?>
        <p class="empty-msg">Aucune session en cours.</p>
    <?php else: ?>
        <?php foreach ($enCours as $s): ?>
        <div class="session-card-wrap" id="session-<?= htmlspecialchars($s['id']) ?>">
        <a class="session-card" href="inventaire_saisie.php?session_id=<?= urlencode($s['id']) ?>">
            <div class="session-info">
                <h4><?= htmlspecialchars($s['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></small>
            </div>
            <div class="session-stats">
                <span class="stat-badge stat-objets"><?= (int)$s['nb_objets'] ?> obj.</span>
                <span class="stat-badge stat-encours">En cours</span>
            </div>
        </a>
        <?php if ($is_admin): ?>
            <button class="btn-delete-session" onclick="deleteSession('<?= htmlspecialchars($s['id'], ENT_QUOTES) ?>')" title="Supprimer cette session">
                <i class="fas fa-trash-alt"></i>
            </button>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Terminees -->
    <div class="section-title">
        <i class="fas fa-check-circle" style="color:#2e7d32"></i>
        Terminees
        <span class="badge"><?= count($terminees) ?></span>
    </div>
    <?php if (empty($terminees)): ?>
        <p class="empty-msg">Aucune session terminee.</p>
    <?php else: ?>
        <?php foreach ($terminees as $s): ?>
        <div class="session-card-wrap" id="session-<?= htmlspecialchars($s['id']) ?>">
        <a class="session-card" href="inventaire_saisie.php?session_id=<?= urlencode($s['id']) ?>">
            <div class="session-info">
                <h4><?= htmlspecialchars($s['nom_du_logement']) ?></h4>
                <small><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></small>
            </div>
            <div class="session-stats">
                <span class="stat-badge stat-objets"><?= (int)$s['nb_objets'] ?> obj.</span>
                <span class="stat-badge stat-terminee">Terminee</span>
            </div>
        </a>
        <?php if ($is_admin): ?>
            <button class="btn-delete-session" onclick="deleteSession('<?= htmlspecialchars($s['id'], ENT_QUOTES) ?>')" title="Supprimer cette session">
                <i class="fas fa-trash-alt"></i>
            </button>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="text-align:center">
        <a href="inventaire_lancer.php" class="btn-new"><i class="fas fa-plus"></i> Nouveau inventaire</a>
    </div>
</div>
<?php if ($is_admin): ?>
<script>
function deleteSession(sessionId) {
    if (!confirm('Supprimer cette session et tous ses objets ? Cette action est irréversible.')) return;
    var fd = new FormData();
    fd.append('ajax_delete_session', '1');
    fd.append('session_id', sessionId);
    fetch('liste_sessions.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var el = document.getElementById('session-' + sessionId);
                if (el) el.remove();
            } else {
                alert(data.error || 'Erreur lors de la suppression');
            }
        })
        .catch(function() { alert('Erreur de connexion'); });
}
</script>
<?php endif; ?>
</body>
</html>
