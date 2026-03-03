<?php
/**
 * Checkup Logement — Rapport de synthese
 * Affiche le bilan complet du checkup avec les problemes signales
 */
include '../config.php';
include '../pages/menu.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Suppression admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_checkup']) && $isAdmin) {
    $deleteId = (int)$_POST['delete_checkup'];

    // Supprimer les fichiers photos
    $stmt = $conn->prepare("SELECT photo_path FROM checkup_items WHERE session_id = ? AND photo_path IS NOT NULL AND photo_path != ''");
    $stmt->execute([$deleteId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $photo) {
        $f = __DIR__ . '/../' . $photo;
        if (file_exists($f)) @unlink($f);
    }

    // Supprimer la signature
    try {
        $stmt = $conn->prepare("SELECT signature_path FROM checkup_sessions WHERE id = ?");
        $stmt->execute([$deleteId]);
        $sig = $stmt->fetchColumn();
        if ($sig) { $f = __DIR__ . '/../' . $sig; if (file_exists($f)) @unlink($f); }
    } catch (PDOException $e) {}

    // Supprimer en BDD
    $conn->prepare("DELETE FROM checkup_sessions WHERE id = ?")->execute([$deleteId]);

    header("Location: checkup_historique.php");
    exit;
}

// Charger la session
$stmt = $conn->prepare("
    SELECT cs.*, l.nom_du_logement,
           COALESCE(i.nom, 'Inconnu') AS nom_intervenant
    FROM checkup_sessions cs
    JOIN liste_logements l ON cs.logement_id = l.id
    LEFT JOIN intervenant i ON cs.intervenant_id = i.id
    WHERE cs.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo '<div class="alert alert-danger m-3">Rapport introuvable.</div>';
    echo '<a href="checkup_logement.php" class="btn btn-primary m-3">Retour</a>';
    exit;
}

// Charger tous les items
$stmt = $conn->prepare("SELECT * FROM checkup_items WHERE session_id = ? ORDER BY categorie, id");
$stmt->execute([$session_id]);
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par categorie
$categories = [];
foreach ($allItems as $item) {
    $categories[$item['categorie']][] = $item;
}

// Items problematiques
$problemes = array_filter($allItems, fn($i) => $i['statut'] === 'probleme');
$absents = array_filter($allItems, fn($i) => $i['statut'] === 'absent');
$oks = array_filter($allItems, fn($i) => $i['statut'] === 'ok');
$nonVerifies = array_filter($allItems, fn($i) => $i['statut'] === 'non_verifie');

// Taches traitees (liees a todo_list)
$tachesFaites = array_filter($allItems, fn($i) => $i['categorie'] === 'Taches a faire' && $i['statut'] === 'ok');
$tachesNonFaites = array_filter($allItems, fn($i) => $i['categorie'] === 'Taches a faire' && $i['statut'] !== 'ok');

$total = count($allItems);
$score = $total > 0 ? round((count($oks) / $total) * 100) : 0;

// Signature
$signaturePath = null;
try {
    $stmt = $conn->prepare("SELECT signature_path FROM checkup_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $sigRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sigRow && !empty($sigRow['signature_path'])) {
        $signaturePath = $sigRow['signature_path'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Checkup — <?= htmlspecialchars($session['nom_du_logement']) ?></title>
    <style>
        .rp-container {
            max-width: 700px;
            margin: 0 auto;
            padding: 0 12px 40px;
        }
        /* En-tete rapport */
        .rp-header {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
            border-radius: 15px;
            padding: 25px 20px;
            margin: 15px 0 20px;
            text-align: center;
        }
        .rp-header h2 { margin: 0 0 5px; font-size: 1.3em; }
        .rp-header .subtitle { opacity: 0.85; font-size: 0.92em; }
        /* Score */
        .rp-score-row {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .rp-score-card {
            flex: 1;
            background: #fff;
            border-radius: 12px;
            padding: 18px 12px;
            text-align: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07);
        }
        .rp-score-card .number {
            font-size: 2em;
            font-weight: 800;
            line-height: 1;
        }
        .rp-score-card .label {
            font-size: 0.82em;
            color: #666;
            margin-top: 4px;
        }
        .score-ok .number { color: #43a047; }
        .score-problem .number { color: #e53935; }
        .score-absent .number { color: #ff9800; }
        .score-total .number { color: #1976d2; }
        /* Alertes */
        .rp-alert {
            border-radius: 12px;
            padding: 15px 18px;
            margin-bottom: 15px;
        }
        .rp-alert-danger {
            background: #fbe9e7;
            border-left: 4px solid #e53935;
        }
        .rp-alert-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .rp-alert-success {
            background: #e8f5e9;
            border-left: 4px solid #43a047;
        }
        .rp-alert h4 {
            margin: 0 0 10px;
            font-size: 1em;
        }
        .rp-alert-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .rp-alert-item:last-child {
            border-bottom: none;
        }
        .rp-alert-item .item-cat {
            font-size: 0.75em;
            color: #888;
            background: rgba(0,0,0,0.05);
            padding: 2px 8px;
            border-radius: 10px;
            white-space: nowrap;
        }
        .rp-alert-item .item-name {
            font-weight: 600;
            font-size: 0.95em;
        }
        .rp-alert-item .item-comment {
            font-size: 0.85em;
            color: #666;
            font-style: italic;
        }
        .rp-alert-item .item-photo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }
        /* Section detail par categorie */
        .rp-section {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .rp-section-title {
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.98em;
            background: #f5f7fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .rp-section-body {
            display: none;
        }
        .rp-section-body.open {
            display: block;
        }
        .rp-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 18px;
            border-top: 1px solid #f0f0f0;
            font-size: 0.92em;
        }
        .rp-row .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .dot-ok { background: #43a047; }
        .dot-probleme { background: #e53935; }
        .dot-absent { background: #ff9800; }
        .dot-non_verifie { background: #bdbdbd; }
        /* Commentaire general */
        .rp-comment {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 15px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
        }
        .rp-comment h4 {
            margin: 0 0 8px;
            font-size: 1em;
            color: #333;
        }
        .rp-comment p {
            margin: 0;
            color: #555;
            font-size: 0.95em;
        }
        /* Actions */
        .rp-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .rp-actions a, .rp-actions button {
            flex: 1;
            padding: 14px;
            font-size: 1em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-back {
            background: #e3f2fd;
            color: #1976d2;
        }
        .btn-reopen {
            background: #fff3e0;
            color: #e65100;
        }
        .btn-new {
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
        }
        @media (max-width: 600px) {
            .rp-score-row { flex-wrap: wrap; }
            .rp-score-card { min-width: calc(50% - 8px); }
            .rp-actions { flex-direction: column; }
        }
        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }
        .btn-delete:hover {
            background: #ffcdd2;
        }
        /* Modal suppression */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 30px 24px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .modal-box h4 {
            margin: 0 0 12px;
            font-size: 1.1em;
            color: #c62828;
        }
        .modal-box p {
            margin: 0 0 20px;
            color: #555;
            font-size: 0.95em;
        }
        .modal-box .modal-btns {
            display: flex;
            gap: 10px;
        }
        .modal-box .modal-btns button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95em;
            cursor: pointer;
        }
        .modal-box .btn-cancel {
            background: #e0e0e0;
            color: #333;
        }
        .modal-box .btn-confirm-del {
            background: #c62828;
            color: #fff;
        }
        @media print {
            nav, .rp-actions { display: none !important; }
            .rp-section-body { display: block !important; }
        }
    </style>
</head>
<body>
<div class="rp-container">
    <div class="rp-header">
        <h2><i class="fas fa-clipboard-check"></i> Rapport de Checkup</h2>
        <div class="subtitle">
            <?= htmlspecialchars($session['nom_du_logement']) ?> — <?= date('d/m/Y a H:i', strtotime($session['created_at'])) ?>
            <br>Par : <?= htmlspecialchars($session['nom_intervenant']) ?>
        </div>
    </div>

    <!-- Score global -->
    <div class="rp-score-row">
        <div class="rp-score-card score-ok">
            <div class="number"><?= count($oks) ?></div>
            <div class="label">OK</div>
        </div>
        <div class="rp-score-card score-problem">
            <div class="number"><?= count($problemes) ?></div>
            <div class="label">Problemes</div>
        </div>
        <div class="rp-score-card score-absent">
            <div class="number"><?= count($absents) ?></div>
            <div class="label">Absents</div>
        </div>
        <div class="rp-score-card score-total">
            <div class="number"><?= $score ?>%</div>
            <div class="label">Score</div>
        </div>
        <?php if (count($tachesFaites) > 0 || count($tachesNonFaites) > 0): ?>
        <div class="rp-score-card" style="border-top:3px solid #7b1fa2">
            <div class="number" style="color:#7b1fa2"><?= count($tachesFaites) ?>/<?= count($tachesFaites) + count($tachesNonFaites) ?></div>
            <div class="label">Taches</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Problemes signales -->
    <?php if (!empty($problemes)): ?>
    <div class="rp-alert rp-alert-danger">
        <h4><i class="fas fa-exclamation-triangle"></i> Problemes signales (<?= count($problemes) ?>)</h4>
        <?php foreach ($problemes as $p): ?>
        <div class="rp-alert-item">
            <?php if ($p['photo_path']): ?>
            <img src="../<?= htmlspecialchars($p['photo_path']) ?>" class="item-photo" onclick="window.open(this.src)">
            <?php endif; ?>
            <div>
                <span class="item-cat"><?= htmlspecialchars($p['categorie']) ?></span>
                <div class="item-name"><?= htmlspecialchars($p['nom_item']) ?></div>
                <?php if ($p['commentaire']): ?>
                <div class="item-comment"><?= htmlspecialchars($p['commentaire']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Elements absents -->
    <?php if (!empty($absents)): ?>
    <div class="rp-alert rp-alert-warning">
        <h4><i class="fas fa-times-circle"></i> Elements absents (<?= count($absents) ?>)</h4>
        <?php foreach ($absents as $a): ?>
        <div class="rp-alert-item">
            <div>
                <span class="item-cat"><?= htmlspecialchars($a['categorie']) ?></span>
                <div class="item-name"><?= htmlspecialchars($a['nom_item']) ?></div>
                <?php if ($a['commentaire']): ?>
                <div class="item-comment"><?= htmlspecialchars($a['commentaire']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Taches traitees -->
    <?php if (!empty($tachesFaites)): ?>
    <div class="rp-alert" style="background:#f3e5f5; border-left:4px solid #7b1fa2;">
        <h4 style="color:#7b1fa2"><i class="fas fa-tasks"></i> Taches realisees (<?= count($tachesFaites) ?>)</h4>
        <?php foreach ($tachesFaites as $t): ?>
        <div class="rp-alert-item">
            <div>
                <div class="item-name"><i class="fas fa-check" style="color:#43a047"></i> <?= htmlspecialchars($t['nom_item']) ?></div>
                <?php if ($t['commentaire']): ?>
                <div class="item-comment"><?= htmlspecialchars($t['commentaire']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($tachesNonFaites)): ?>
    <div class="rp-alert rp-alert-warning">
        <h4><i class="fas fa-clock"></i> Taches non realisees (<?= count($tachesNonFaites) ?>)</h4>
        <?php foreach ($tachesNonFaites as $t): ?>
        <div class="rp-alert-item">
            <div>
                <div class="item-name"><?= htmlspecialchars($t['nom_item']) ?></div>
                <?php if ($t['commentaire']): ?>
                <div class="item-comment"><?= htmlspecialchars($t['commentaire']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tout OK -->
    <?php if (empty($problemes) && empty($absents)): ?>
    <div class="rp-alert rp-alert-success">
        <h4><i class="fas fa-check-circle"></i> Tout est en ordre !</h4>
        <p>Aucun probleme ni element absent n'a ete signale. Le logement est pret.</p>
    </div>
    <?php endif; ?>

    <!-- Commentaire general -->
    <?php if (!empty($session['commentaire_general'])): ?>
    <div class="rp-comment">
        <h4><i class="fas fa-comment-dots"></i> Commentaire general</h4>
        <p><?= nl2br(htmlspecialchars($session['commentaire_general'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Detail par categorie -->
    <h5 style="color:#555; margin: 20px 0 10px;"><i class="fas fa-list"></i> Detail complet</h5>
    <?php foreach ($categories as $catName => $items): ?>
    <?php
        $catOk = count(array_filter($items, fn($i) => $i['statut'] === 'ok'));
        $catPb = count(array_filter($items, fn($i) => $i['statut'] === 'probleme'));
        $catAb = count(array_filter($items, fn($i) => $i['statut'] === 'absent'));
    ?>
    <div class="rp-section">
        <div class="rp-section-title" onclick="this.nextElementSibling.classList.toggle('open')">
            <span><?= htmlspecialchars($catName) ?>
                <?php if ($catPb > 0): ?><span style="color:#e53935; font-weight:400; font-size:0.85em">(<?= $catPb ?> pb)</span><?php endif; ?>
                <?php if ($catAb > 0): ?><span style="color:#ff9800; font-weight:400; font-size:0.85em">(<?= $catAb ?> abs)</span><?php endif; ?>
            </span>
            <span style="color:#888; font-size:0.85em"><?= $catOk ?>/<?= count($items) ?> OK</span>
        </div>
        <div class="rp-section-body">
            <?php foreach ($items as $item): ?>
            <div class="rp-row">
                <span>
                    <span class="status-dot dot-<?= $item['statut'] ?>"></span>
                    <?= htmlspecialchars($item['nom_item']) ?>
                    <?php if ($item['commentaire']): ?>
                    <small style="color:#888; font-style:italic"> — <?= htmlspecialchars($item['commentaire']) ?></small>
                    <?php endif; ?>
                </span>
                <span style="font-size:0.85em; font-weight:600; color:<?= match($item['statut']) { 'ok'=>'#43a047', 'probleme'=>'#e53935', 'absent'=>'#ff9800', default=>'#bbb' } ?>">
                    <?= match($item['statut']) { 'ok'=>'OK', 'probleme'=>'Probleme', 'absent'=>'Absent', default=>'—' } ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Signature -->
    <?php if ($signaturePath): ?>
    <div style="background:#fff;border-radius:12px;padding:18px;margin-bottom:15px;box-shadow:0 1px 5px rgba(0,0,0,0.06);text-align:center;">
        <h4 style="margin:0 0 8px;font-size:1em;color:#333;"><i class="fas fa-signature"></i> Signature de l'intervenant</h4>
        <img src="../<?= htmlspecialchars($signaturePath) ?>" alt="Signature" style="max-width:250px;max-height:100px;">
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="rp-actions">
        <a href="checkup_logement.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <a href="checkup_pdf.php?session_id=<?= $session_id ?>" class="btn-reopen" target="_blank">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
        <?php if ($session['statut'] === 'termine'): ?>
        <a href="checkup_faire.php?session_id=<?= $session_id ?>" class="btn-reopen">
            <i class="fas fa-edit"></i> Modifier
        </a>
        <?php endif; ?>
        <a href="checkup_logement.php" class="btn-new">
            <i class="fas fa-plus"></i> Nouveau
        </a>
        <?php if ($isAdmin): ?>
        <button type="button" class="btn-delete" onclick="confirmDelete(<?= $session_id ?>)">
            <i class="fas fa-trash"></i> Supprimer
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal de confirmation de suppression -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h4><i class="fas fa-exclamation-triangle"></i> Supprimer ce checkup ?</h4>
        <p>Cette action est irreversible. Le checkup, ses items, photos et signature seront definitivement supprimes.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_checkup" id="deleteCheckupId" value="">
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
                <button type="submit" class="btn-confirm-del"><i class="fas fa-trash"></i> Supprimer</button>
            </div>
        </form>
    </div>
</div>
<script>
function confirmDelete(id) {
    document.getElementById('deleteCheckupId').value = id;
    document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
<?php endif; ?>
</body>
</html>
