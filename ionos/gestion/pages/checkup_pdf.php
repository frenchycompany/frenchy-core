<?php
/**
 * Export PDF du rapport de checkup
 * Page optimisee pour l'impression / export PDF navigateur
 * Accessible via un bouton "Telecharger PDF" depuis le rapport
 */
include '../config.php';

// Pas de menu pour la version PDF
if (session_status() === PHP_SESSION_NONE) session_start();

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

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
    die('Rapport introuvable.');
}

// Items
$stmt = $conn->prepare("SELECT * FROM checkup_items WHERE session_id = ? ORDER BY categorie, id");
$stmt->execute([$session_id]);
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
foreach ($allItems as $item) {
    $categories[$item['categorie']][] = $item;
}

$problemes = array_filter($allItems, fn($i) => $i['statut'] === 'probleme');
$absents = array_filter($allItems, fn($i) => $i['statut'] === 'absent');
$oks = array_filter($allItems, fn($i) => $i['statut'] === 'ok');
$total = count($allItems);
$score = $total > 0 ? round((count($oks) / $total) * 100) : 0;

$tachesFaites = array_filter($allItems, fn($i) => $i['categorie'] === 'Taches a faire' && $i['statut'] === 'ok');
$tachesNonFaites = array_filter($allItems, fn($i) => $i['categorie'] === 'Taches a faire' && $i['statut'] !== 'ok');

// Signature
$signaturePath = null;
try {
    $stmt = $conn->prepare("SELECT signature_path FROM checkup_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['signature_path'])) {
        $signaturePath = $row['signature_path'];
    }
} catch (PDOException $e) {
    // Colonne n'existe pas encore
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Checkup — <?= htmlspecialchars($session['nom_du_logement']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #333;
            line-height: 1.4;
        }
        .pdf-header {
            background: #1976d2;
            color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pdf-header h1 { font-size: 16pt; margin: 0; }
        .pdf-header .meta { font-size: 9pt; opacity: 0.9; text-align: right; }
        .score-row {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        .score-box {
            flex: 1;
            text-align: center;
            padding: 12px 8px;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        .score-box .num { font-size: 20pt; font-weight: 800; }
        .score-box .lbl { font-size: 8pt; color: #666; }
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11pt;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .sec-danger { background: #fbe9e7; color: #c62828; }
        .sec-warning { background: #fff3e0; color: #e65100; }
        .sec-success { background: #e8f5e9; color: #2e7d32; }
        .sec-info { background: #f3e5f5; color: #7b1fa2; }
        .sec-detail { background: #f5f5f5; color: #555; }
        .item-row {
            padding: 5px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 10pt;
            display: flex;
            justify-content: space-between;
        }
        .item-row:last-child { border-bottom: none; }
        .item-comment { color: #888; font-style: italic; font-size: 9pt; }
        .status-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .dot-ok { background: #43a047; }
        .dot-probleme { background: #e53935; }
        .dot-absent { background: #ff9800; }
        .dot-non_verifie { background: #bdbdbd; }
        .comment-box {
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 10pt;
        }
        .signature-box {
            margin-top: 15px;
            text-align: center;
            page-break-inside: avoid;
        }
        .signature-box img {
            max-width: 200px;
            max-height: 80px;
        }
        .signature-box .sig-label {
            font-size: 9pt;
            color: #888;
            margin-top: 4px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8pt;
            color: #aaa;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
        /* Bouton imprimer (masque a l'impression) */
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .no-print button {
            padding: 14px 30px;
            font-size: 14pt;
            font-weight: 700;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin: 5px;
        }
        .no-print a {
            padding: 14px 30px;
            font-size: 14pt;
            font-weight: 700;
            background: #e0e0e0;
            color: #555;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()"><i class="fas fa-download"></i> Telecharger en PDF</button>
    <a href="checkup_rapport.php?session_id=<?= $session_id ?>">Retour au rapport</a>
</div>

<div class="pdf-header">
    <div>
        <h1>Rapport de Checkup</h1>
        <div><?= htmlspecialchars($session['nom_du_logement']) ?></div>
    </div>
    <div class="meta">
        Date : <?= date('d/m/Y H:i', strtotime($session['created_at'])) ?><br>
        Intervenant : <?= htmlspecialchars($session['nom_intervenant']) ?><br>
        Session #<?= $session_id ?>
    </div>
</div>

<div class="score-row">
    <div class="score-box">
        <div class="num" style="color:#43a047"><?= count($oks) ?></div>
        <div class="lbl">OK</div>
    </div>
    <div class="score-box">
        <div class="num" style="color:#e53935"><?= count($problemes) ?></div>
        <div class="lbl">Problemes</div>
    </div>
    <div class="score-box">
        <div class="num" style="color:#ff9800"><?= count($absents) ?></div>
        <div class="lbl">Absents</div>
    </div>
    <div class="score-box">
        <div class="num" style="color:#1976d2"><?= $score ?>%</div>
        <div class="lbl">Score</div>
    </div>
    <?php if (count($tachesFaites) + count($tachesNonFaites) > 0): ?>
    <div class="score-box">
        <div class="num" style="color:#7b1fa2"><?= count($tachesFaites) ?>/<?= count($tachesFaites) + count($tachesNonFaites) ?></div>
        <div class="lbl">Taches</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($problemes)): ?>
<div class="section">
    <div class="section-title sec-danger">Problemes signales (<?= count($problemes) ?>)</div>
    <?php foreach ($problemes as $p): ?>
    <div class="item-row">
        <span><span class="status-dot dot-probleme"></span> [<?= htmlspecialchars($p['categorie']) ?>] <?= htmlspecialchars($p['nom_item']) ?></span>
    </div>
    <?php if ($p['commentaire']): ?>
    <div class="item-row"><span class="item-comment">&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($p['commentaire']) ?></span></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($absents)): ?>
<div class="section">
    <div class="section-title sec-warning">Elements absents (<?= count($absents) ?>)</div>
    <?php foreach ($absents as $a): ?>
    <div class="item-row">
        <span><span class="status-dot dot-absent"></span> [<?= htmlspecialchars($a['categorie']) ?>] <?= htmlspecialchars($a['nom_item']) ?></span>
    </div>
    <?php if ($a['commentaire']): ?>
    <div class="item-row"><span class="item-comment">&nbsp;&nbsp;&nbsp;&nbsp;<?= htmlspecialchars($a['commentaire']) ?></span></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($tachesFaites)): ?>
<div class="section">
    <div class="section-title sec-info">Taches realisees (<?= count($tachesFaites) ?>)</div>
    <?php foreach ($tachesFaites as $t): ?>
    <div class="item-row"><span><span class="status-dot dot-ok"></span> <?= htmlspecialchars($t['nom_item']) ?></span></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($tachesNonFaites)): ?>
<div class="section">
    <div class="section-title sec-warning">Taches non realisees (<?= count($tachesNonFaites) ?>)</div>
    <?php foreach ($tachesNonFaites as $t): ?>
    <div class="item-row"><span><span class="status-dot dot-absent"></span> <?= htmlspecialchars($t['nom_item']) ?></span></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($problemes) && empty($absents)): ?>
<div class="section">
    <div class="section-title sec-success">Tout est en ordre ! Aucun probleme signale.</div>
</div>
<?php endif; ?>

<?php if (!empty($session['commentaire_general'])): ?>
<div class="comment-box">
    <strong>Commentaire general :</strong><br>
    <?= nl2br(htmlspecialchars($session['commentaire_general'])) ?>
</div>
<?php endif; ?>

<!-- Detail complet par categorie -->
<?php foreach ($categories as $catName => $items): ?>
<div class="section">
    <div class="section-title sec-detail"><?= htmlspecialchars($catName) ?></div>
    <?php foreach ($items as $item): ?>
    <div class="item-row">
        <span>
            <span class="status-dot dot-<?= $item['statut'] ?>"></span>
            <?= htmlspecialchars($item['nom_item']) ?>
            <?php if ($item['commentaire']): ?>
                <span class="item-comment"> — <?= htmlspecialchars($item['commentaire']) ?></span>
            <?php endif; ?>
        </span>
        <?php $sColors = ['ok'=>'#43a047','probleme'=>'#e53935','absent'=>'#ff9800']; $sLabels = ['ok'=>'OK','probleme'=>'Probleme','absent'=>'Absent']; ?>
        <span style="font-size:9pt;font-weight:600;color:<?= $sColors[$item['statut']] ?? '#bbb' ?>">
            <?= $sLabels[$item['statut']] ?? '-' ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if ($signaturePath): ?>
<div class="signature-box">
    <img src="../<?= htmlspecialchars($signaturePath) ?>" alt="Signature">
    <div class="sig-label">Signature de l'intervenant</div>
</div>
<?php endif; ?>

<div class="footer">
    Rapport genere le <?= date('d/m/Y a H:i') ?> — Frenchy Conciergerie
</div>

</body>
</html>
