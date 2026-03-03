<?php
/**
 * Checkup Logement — Checklist interactive mobile
 * Interface tactile pour verifier chaque item : OK / Probleme / Absent
 * Possibilite d'ajouter un commentaire et une photo par item
 */
include '../config.php';
include '../pages/menu.php';

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Charger la session
$stmt = $conn->prepare("
    SELECT cs.*, l.nom_du_logement
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo '<div class="alert alert-danger m-3">Session de checkup introuvable.</div>';
    echo '<a href="checkup_logement.php" class="btn btn-primary m-3">Retour</a>';
    exit;
}

// Traitement AJAX : mise a jour d'un item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $item_id = intval($_POST['item_id'] ?? 0);
    $statut = $_POST['statut'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';

    if (!in_array($statut, ['ok', 'probleme', 'absent', 'non_verifie'])) {
        echo json_encode(['error' => 'Statut invalide']);
        exit;
    }

    // Gestion de la photo
    $photo_path = null;
    if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../uploads/checkup/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
        if (in_array($ext, $allowed)) {
            $filename = 'checkup_' . $session_id . '_' . $item_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                $photo_path = 'uploads/checkup/' . $filename;
            }
        }
    }

    // Mise a jour de l'item
    if ($photo_path) {
        $stmt = $conn->prepare("UPDATE checkup_items SET statut = ?, commentaire = ?, photo_path = ? WHERE id = ? AND session_id = ?");
        $stmt->execute([$statut, $commentaire, $photo_path, $item_id, $session_id]);
    } else {
        $stmt = $conn->prepare("UPDATE checkup_items SET statut = ?, commentaire = ? WHERE id = ? AND session_id = ?");
        $stmt->execute([$statut, $commentaire, $item_id, $session_id]);
    }

    // Synchroniser avec todo_list si c'est une tache liee
    $stmt = $conn->prepare("SELECT todo_task_id FROM checkup_items WHERE id = ? AND session_id = ?");
    $stmt->execute([$item_id, $session_id]);
    $taskRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($taskRow && $taskRow['todo_task_id']) {
        $todoStatut = ($statut === 'ok') ? 'terminée' : 'en cours';
        $stmt = $conn->prepare("UPDATE todo_list SET statut = ? WHERE id = ?");
        $stmt->execute([$todoStatut, $taskRow['todo_task_id']]);
    }

    echo json_encode(['success' => true, 'photo_path' => $photo_path]);
    exit;
}

// Traitement : terminer le checkup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminer'])) {
    $commentaire_general = $_POST['commentaire_general'] ?? '';

    // Compter les stats
    $stmt = $conn->prepare("SELECT statut, COUNT(*) as nb FROM checkup_items WHERE session_id = ? GROUP BY statut");
    $stmt->execute([$session_id]);
    $stats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['statut']] = $row['nb'];
    }

    // Compter les taches todo_list marquees OK
    $stmt = $conn->prepare("SELECT COUNT(*) FROM checkup_items WHERE session_id = ? AND todo_task_id IS NOT NULL AND statut = 'ok'");
    $stmt->execute([$session_id]);
    $nbTachesFaites = $stmt->fetchColumn();

    $stmt = $conn->prepare("
        UPDATE checkup_sessions
        SET statut = 'termine',
            nb_ok = ?,
            nb_problemes = ?,
            nb_absents = ?,
            nb_taches_faites = ?,
            commentaire_general = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $stats['ok'] ?? 0,
        $stats['probleme'] ?? 0,
        $stats['absent'] ?? 0,
        $nbTachesFaites,
        $commentaire_general,
        $session_id
    ]);

    header("Location: checkup_rapport.php?session_id=" . $session_id);
    exit;
}

// Charger tous les items groupes par categorie
$stmt = $conn->prepare("SELECT * FROM checkup_items WHERE session_id = ? ORDER BY categorie, id");
$stmt->execute([$session_id]);
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($allItems as $item) {
    $categories[$item['categorie']][] = $item;
}

// Stats en temps reel
$total = count($allItems);
$done = 0;
foreach ($allItems as $item) {
    if ($item['statut'] !== 'non_verifie') $done++;
}
$progress = $total > 0 ? round(($done / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkup — <?= htmlspecialchars($session['nom_du_logement']) ?></title>
    <style>
        .ck-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 10px 120px;
        }
        /* Header fixe */
        .ck-topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            padding: 12px 0 8px;
            border-bottom: 1px solid #eee;
        }
        .ck-topbar h3 {
            margin: 0;
            font-size: 1.1em;
            color: #333;
        }
        .ck-topbar small {
            color: #888;
        }
        /* Barre de progression */
        .progress-wrap {
            margin-top: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            height: 10px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .progress-text {
            font-size: 0.82em;
            color: #666;
            margin-top: 3px;
        }
        /* Categorie */
        .ck-categorie {
            margin-top: 22px;
        }
        .ck-categorie-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.05em;
            color: #1565c0;
            padding: 10px 0;
            border-bottom: 2px solid #e3f2fd;
            cursor: pointer;
            user-select: none;
        }
        .ck-categorie-title .cat-count {
            font-size: 0.8em;
            font-weight: 400;
            color: #888;
        }
        .ck-categorie-title .chevron {
            transition: transform 0.2s;
        }
        .ck-categorie-title.collapsed .chevron {
            transform: rotate(-90deg);
        }
        .ck-items {
            display: block;
        }
        .ck-items.hidden {
            display: none;
        }
        /* Item */
        .ck-item {
            background: #fff;
            border-radius: 12px;
            margin: 8px 0;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: border-left 0.2s;
            border-left: 4px solid #bdbdbd;
        }
        .ck-item.status-ok { border-left-color: #43a047; }
        .ck-item.status-probleme { border-left-color: #e53935; }
        .ck-item.status-absent { border-left-color: #ff9800; }
        .ck-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 12px;
            cursor: pointer;
        }
        .ck-item-name {
            font-weight: 600;
            font-size: 1em;
            flex: 1;
        }
        .ck-item-status-icon {
            font-size: 1.3em;
            margin-left: 8px;
        }
        /* Boutons d'action */
        .ck-actions {
            display: flex;
            gap: 6px;
            padding: 0 12px 12px;
        }
        .ck-btn {
            flex: 1;
            padding: 12px 6px;
            font-size: 0.9em;
            font-weight: 700;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
            background: #f5f5f5;
            color: #555;
        }
        .ck-btn:active { transform: scale(0.96); }
        .ck-btn-ok { border-color: #43a047; }
        .ck-btn-ok.active { background: #43a047; color: #fff; }
        .ck-btn-problem { border-color: #e53935; }
        .ck-btn-problem.active { background: #e53935; color: #fff; }
        .ck-btn-absent { border-color: #ff9800; }
        .ck-btn-absent.active { background: #ff9800; color: #fff; }
        /* Details (commentaire + photo) */
        .ck-details {
            display: none;
            padding: 0 12px 14px;
        }
        .ck-details.open {
            display: block;
        }
        .ck-details textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95em;
            resize: vertical;
            min-height: 50px;
            margin-bottom: 8px;
            box-sizing: border-box;
        }
        .ck-details .photo-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ck-details .photo-btn {
            padding: 10px 16px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9em;
            cursor: pointer;
            white-space: nowrap;
        }
        .ck-details .photo-preview {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            display: none;
        }
        .ck-details input[type="file"] {
            display: none;
        }
        /* Bouton terminer */
        .ck-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 1px solid #eee;
            padding: 12px 15px;
            z-index: 100;
        }
        .ck-footer-inner {
            max-width: 600px;
            margin: 0 auto;
        }
        .btn-finish {
            width: 100%;
            padding: 16px;
            font-size: 1.1em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            cursor: pointer;
        }
        .btn-finish:disabled {
            background: #bbb;
            cursor: not-allowed;
        }
        .btn-finish:active:not(:disabled) {
            transform: scale(0.98);
        }
        /* Commentaire general */
        .ck-general-comment {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        .ck-general-comment label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        .ck-general-comment textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
        }
        @media (max-width: 600px) {
            .ck-container { padding: 0 6px 120px; }
            .ck-btn { font-size: 0.82em; padding: 10px 4px; }
        }
    </style>
</head>
<body>
<div class="ck-container">
    <div class="ck-topbar">
        <h3><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($session['nom_du_logement']) ?></h3>
        <small>Checkup #<?= $session_id ?> — <?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></small>
        <div class="progress-wrap">
            <div class="progress-bar" id="progressBar" style="width: <?= $progress ?>%; background: <?= $progress === 100 ? '#43a047' : '#1976d2' ?>"></div>
        </div>
        <div class="progress-text" id="progressText"><?= $done ?> / <?= $total ?> verifies (<?= $progress ?>%)</div>
    </div>

    <!-- Raccourcis rapides -->
    <div style="display:flex; gap:8px; margin:12px 0;">
        <a href="inventaire_saisie.php?session_id=<?php
            // Trouver la derniere session inventaire en cours pour ce logement
            $invStmt = $conn->prepare("SELECT id FROM sessions_inventaire WHERE logement_id = ? AND statut = 'en_cours' ORDER BY date_creation DESC LIMIT 1");
            $invStmt->execute([$session['logement_id']]);
            $invSession = $invStmt->fetch(PDO::FETCH_ASSOC);
            echo $invSession ? urlencode($invSession['id']) : '';
        ?>" style="flex:1; padding:10px; background:#e3f2fd; color:#1565c0; border-radius:10px; text-align:center; text-decoration:none; font-weight:600; font-size:0.88em;"
        <?= $invSession ? '' : 'onclick="event.preventDefault(); if(confirm(\'Pas d\\\'inventaire en cours. Lancer un nouvel inventaire ?\')) window.location.href=\'inventaire_lancer.php\';"' ?>>
            <i class="fas fa-boxes-stacked"></i> Inventaire
        </a>
        <a href="todo.php?logement_id=<?= $session['logement_id'] ?>" style="flex:1; padding:10px; background:#f3e5f5; color:#7b1fa2; border-radius:10px; text-align:center; text-decoration:none; font-weight:600; font-size:0.88em;">
            <i class="fas fa-tasks"></i> Taches
        </a>
        <a href="logement_equipements.php?id=<?= $session['logement_id'] ?>" style="flex:1; padding:10px; background:#e8f5e9; color:#2e7d32; border-radius:10px; text-align:center; text-decoration:none; font-weight:600; font-size:0.88em;">
            <i class="fas fa-couch"></i> Equipements
        </a>
    </div>

    <?php foreach ($categories as $catName => $items): ?>
    <?php
        $catDone = 0;
        foreach ($items as $it) { if ($it['statut'] !== 'non_verifie') $catDone++; }
        $catIcon = match($catName) {
            'Cuisine' => 'fa-utensils',
            'Entretien' => 'fa-broom',
            'Multimedia' => 'fa-tv',
            'Mobilier' => 'fa-couch',
            'Literie / Linge' => 'fa-bed',
            'Salle de bain' => 'fa-bath',
            'Confort' => 'fa-temperature-high',
            'Exterieur' => 'fa-tree',
            'Securite' => 'fa-shield-alt',
            'Enfants' => 'fa-baby',
            'Inventaire' => 'fa-boxes-stacked',
            'Taches a faire' => 'fa-tasks',
            'Etat general' => 'fa-search',
            default => 'fa-check-circle'
        };
    ?>
    <div class="ck-categorie">
        <div class="ck-categorie-title" onclick="toggleCategory(this)">
            <span><i class="fas <?= $catIcon ?>"></i> <?= htmlspecialchars($catName) ?> <span class="cat-count">(<?= $catDone ?>/<?= count($items) ?>)</span></span>
            <i class="fas fa-chevron-down chevron"></i>
        </div>
        <div class="ck-items">
            <?php foreach ($items as $item): ?>
            <div class="ck-item <?= $item['statut'] !== 'non_verifie' ? 'status-' . $item['statut'] : '' ?>"
                 id="item-<?= $item['id'] ?>"
                 data-id="<?= $item['id'] ?>"
                 data-statut="<?= htmlspecialchars($item['statut']) ?>">
                <div class="ck-item-header" onclick="toggleDetails(<?= $item['id'] ?>)">
                    <span class="ck-item-name"><?= htmlspecialchars($item['nom_item']) ?></span>
                    <span class="ck-item-status-icon" id="icon-<?= $item['id'] ?>">
                        <?= match($item['statut']) {
                            'ok' => '<i class="fas fa-check-circle" style="color:#43a047"></i>',
                            'probleme' => '<i class="fas fa-exclamation-triangle" style="color:#e53935"></i>',
                            'absent' => '<i class="fas fa-times-circle" style="color:#ff9800"></i>',
                            default => '<i class="fas fa-circle" style="color:#ccc"></i>'
                        } ?>
                    </span>
                </div>
                <div class="ck-actions">
                    <button class="ck-btn ck-btn-ok <?= $item['statut'] === 'ok' ? 'active' : '' ?>"
                            onclick="setStatus(<?= $item['id'] ?>, 'ok')">
                        <i class="fas fa-check"></i> OK
                    </button>
                    <button class="ck-btn ck-btn-problem <?= $item['statut'] === 'probleme' ? 'active' : '' ?>"
                            onclick="setStatus(<?= $item['id'] ?>, 'probleme')">
                        <i class="fas fa-exclamation-triangle"></i> Probleme
                    </button>
                    <button class="ck-btn ck-btn-absent <?= $item['statut'] === 'absent' ? 'active' : '' ?>"
                            onclick="setStatus(<?= $item['id'] ?>, 'absent')">
                        <i class="fas fa-times"></i> Absent
                    </button>
                </div>
                <div class="ck-details <?= ($item['statut'] === 'probleme' || $item['statut'] === 'absent' || $item['commentaire']) ? 'open' : '' ?>" id="details-<?= $item['id'] ?>">
                    <textarea placeholder="Commentaire (optionnel)..." id="comment-<?= $item['id'] ?>"
                              onblur="saveComment(<?= $item['id'] ?>)"><?= htmlspecialchars($item['commentaire'] ?? '') ?></textarea>
                    <div class="photo-row">
                        <button class="photo-btn" onclick="document.getElementById('file-<?= $item['id'] ?>').click()">
                            <i class="fas fa-camera"></i> Photo
                        </button>
                        <input type="file" id="file-<?= $item['id'] ?>" accept="image/*" capture="environment"
                               onchange="uploadPhoto(<?= $item['id'] ?>, this)">
                        <?php if ($item['photo_path']): ?>
                        <img class="photo-preview" id="preview-<?= $item['id'] ?>"
                             src="../<?= htmlspecialchars($item['photo_path']) ?>" style="display:block">
                        <?php else: ?>
                        <img class="photo-preview" id="preview-<?= $item['id'] ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <form method="POST" class="ck-general-comment">
        <label><i class="fas fa-comment-dots"></i> Commentaire general</label>
        <textarea name="commentaire_general" placeholder="Remarques generales sur l'etat du logement..."><?= htmlspecialchars($session['commentaire_general'] ?? '') ?></textarea>
        <input type="hidden" name="terminer" value="1">
        <br>
        <button type="submit" class="btn-finish" id="btnFinish">
            <i class="fas fa-flag-checkered"></i> Terminer le checkup
        </button>
    </form>
</div>

<script>
const sessionId = <?= $session_id ?>;
let totalItems = <?= $total ?>;
let doneItems = <?= $done ?>;

function updateProgress() {
    const pct = totalItems > 0 ? Math.round((doneItems / totalItems) * 100) : 0;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressBar').style.background = pct === 100 ? '#43a047' : '#1976d2';
    document.getElementById('progressText').textContent = doneItems + ' / ' + totalItems + ' verifies (' + pct + '%)';
}

function setStatus(itemId, statut) {
    const item = document.getElementById('item-' + itemId);
    const oldStatut = item.dataset.statut;

    // Mettre a jour le compteur
    if (oldStatut === 'non_verifie' && statut !== 'non_verifie') doneItems++;
    if (oldStatut !== 'non_verifie' && statut === 'non_verifie') doneItems--;

    item.dataset.statut = statut;
    item.className = 'ck-item status-' + statut;

    // Mettre a jour les boutons
    item.querySelectorAll('.ck-btn').forEach(btn => btn.classList.remove('active'));
    item.querySelector('.ck-btn-' + (statut === 'probleme' ? 'problem' : statut)).classList.add('active');

    // Mettre a jour l'icone
    const iconMap = {
        'ok': '<i class="fas fa-check-circle" style="color:#43a047"></i>',
        'probleme': '<i class="fas fa-exclamation-triangle" style="color:#e53935"></i>',
        'absent': '<i class="fas fa-times-circle" style="color:#ff9800"></i>',
    };
    document.getElementById('icon-' + itemId).innerHTML = iconMap[statut] || '<i class="fas fa-circle" style="color:#ccc"></i>';

    // Ouvrir les details si probleme ou absent
    const details = document.getElementById('details-' + itemId);
    if (statut === 'probleme' || statut === 'absent') {
        details.classList.add('open');
    }

    updateProgress();

    // Mettre a jour le compteur de la categorie
    const catSection = item.closest('.ck-categorie');
    const catItems = catSection.querySelectorAll('.ck-item');
    let catDone = 0;
    catItems.forEach(ci => { if (ci.dataset.statut !== 'non_verifie') catDone++; });
    catSection.querySelector('.cat-count').textContent = '(' + catDone + '/' + catItems.length + ')';

    // Sauvegarder via AJAX
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('item_id', itemId);
    formData.append('statut', statut);
    formData.append('commentaire', document.getElementById('comment-' + itemId).value);

    fetch('checkup_faire.php?session_id=' + sessionId, { method: 'POST', body: formData });
}

function saveComment(itemId) {
    const item = document.getElementById('item-' + itemId);
    const statut = item.dataset.statut;
    if (statut === 'non_verifie') return;

    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('item_id', itemId);
    formData.append('statut', statut);
    formData.append('commentaire', document.getElementById('comment-' + itemId).value);

    fetch('checkup_faire.php?session_id=' + sessionId, { method: 'POST', body: formData });
}

function uploadPhoto(itemId, input) {
    if (!input.files || !input.files[0]) return;

    const item = document.getElementById('item-' + itemId);
    const statut = item.dataset.statut === 'non_verifie' ? 'probleme' : item.dataset.statut;

    // Si c'etait non_verifie, on passe en probleme automatiquement
    if (item.dataset.statut === 'non_verifie') {
        setStatus(itemId, 'probleme');
    }

    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('item_id', itemId);
    formData.append('statut', statut);
    formData.append('commentaire', document.getElementById('comment-' + itemId).value);
    formData.append('photo', input.files[0]);

    fetch('checkup_faire.php?session_id=' + sessionId, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.photo_path) {
                const preview = document.getElementById('preview-' + itemId);
                preview.src = '../' + data.photo_path;
                preview.style.display = 'block';
            }
        });
}

function toggleDetails(itemId) {
    const details = document.getElementById('details-' + itemId);
    details.classList.toggle('open');
}

function toggleCategory(el) {
    el.classList.toggle('collapsed');
    const items = el.nextElementSibling;
    items.classList.toggle('hidden');
}
</script>
</body>
</html>
