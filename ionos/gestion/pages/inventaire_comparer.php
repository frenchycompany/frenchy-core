<?php
/**
 * Comparaison d'inventaire — Compare deux sessions pour un meme logement
 * Detecte les objets ajoutes, supprimes et modifies
 */
include '../config.php';
include '../pages/menu.php';

$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;
$session1_id = $_GET['session1'] ?? null;
$session2_id = $_GET['session2'] ?? null;

// Logements avec au moins 2 sessions terminees
$logements = $conn->query("
    SELECT l.id, l.nom_du_logement, COUNT(s.id) AS nb_sessions
    FROM liste_logements l
    JOIN sessions_inventaire s ON s.logement_id = l.id AND s.statut = 'terminee'
    GROUP BY l.id
    HAVING nb_sessions >= 2
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);

// Sessions pour le logement selectionne
$sessions = [];
if ($logement_id) {
    $stmt = $conn->prepare("
        SELECT s.id, s.date_creation, COUNT(o.id) AS nb_objets
        FROM sessions_inventaire s
        LEFT JOIN inventaire_objets o ON o.session_id = s.id
        WHERE s.logement_id = ? AND s.statut = 'terminee'
        GROUP BY s.id
        ORDER BY s.date_creation DESC
    ");
    $stmt->execute([$logement_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Comparaison
$diff = null;
if ($session1_id && $session2_id) {
    // Objets session 1 (ancienne)
    $stmt = $conn->prepare("SELECT nom_objet, quantite, piece, etat, marque FROM inventaire_objets WHERE session_id = ? ORDER BY piece, nom_objet");
    $stmt->execute([$session1_id]);
    $objets1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Objets session 2 (nouvelle)
    $stmt->execute([$session2_id]);
    $objets2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indexer par nom_objet + piece pour la comparaison
    $map1 = [];
    foreach ($objets1 as $o) {
        $key = mb_strtolower($o['nom_objet']) . '|' . mb_strtolower($o['piece'] ?? '');
        $map1[$key] = $o;
    }
    $map2 = [];
    foreach ($objets2 as $o) {
        $key = mb_strtolower($o['nom_objet']) . '|' . mb_strtolower($o['piece'] ?? '');
        $map2[$key] = $o;
    }

    $ajoutes = [];    // Dans session2 mais pas dans session1
    $supprimes = [];   // Dans session1 mais pas dans session2
    $modifies = [];    // Dans les deux mais avec des differences
    $identiques = [];  // Strictement identiques

    // Trouver ajoutes et modifies
    foreach ($map2 as $key => $obj2) {
        if (!isset($map1[$key])) {
            $ajoutes[] = $obj2;
        } else {
            $obj1 = $map1[$key];
            $changes = [];
            if ((int)$obj1['quantite'] !== (int)$obj2['quantite']) {
                $changes[] = 'Quantite: ' . $obj1['quantite'] . ' → ' . $obj2['quantite'];
            }
            if ($obj1['etat'] !== $obj2['etat']) {
                $changes[] = 'Etat: ' . ($obj1['etat'] ?? '-') . ' → ' . ($obj2['etat'] ?? '-');
            }
            if ($obj1['marque'] !== $obj2['marque']) {
                $changes[] = 'Marque: ' . ($obj1['marque'] ?: '-') . ' → ' . ($obj2['marque'] ?: '-');
            }
            if (!empty($changes)) {
                $modifies[] = ['objet' => $obj2, 'changes' => $changes];
            } else {
                $identiques[] = $obj2;
            }
        }
    }

    // Trouver supprimes
    foreach ($map1 as $key => $obj1) {
        if (!isset($map2[$key])) {
            $supprimes[] = $obj1;
        }
    }

    $diff = [
        'ajoutes' => $ajoutes,
        'supprimes' => $supprimes,
        'modifies' => $modifies,
        'identiques' => $identiques,
        'total1' => count($objets1),
        'total2' => count($objets2),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparer Inventaires</title>
    <style>
        .cmp-container { max-width: 800px; margin: 0 auto; padding: 0 12px 40px; }
        .cmp-header {
            background: linear-gradient(135deg, #7b1fa2, #6a1b9a);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .cmp-header h2 { margin: 0; font-size: 1.3em; }
        .cmp-form {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
        }
        .cmp-form label { display: block; font-weight: 600; color: #555; margin-bottom: 6px; font-size: 0.92em; }
        .cmp-form select {
            width: 100%; padding: 10px; font-size: 1em;
            border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 12px;
        }
        .cmp-form .btn-compare {
            width: 100%; padding: 14px; font-size: 1.05em; font-weight: 700;
            border: none; border-radius: 10px;
            background: linear-gradient(135deg, #7b1fa2, #6a1b9a);
            color: #fff; cursor: pointer;
        }
        .cmp-stats {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .cmp-stat {
            flex: 1; min-width: 80px; background: #fff; border-radius: 12px;
            padding: 14px 10px; text-align: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07);
        }
        .cmp-stat .number { font-size: 1.6em; font-weight: 800; line-height: 1; }
        .cmp-stat .label { font-size: 0.78em; color: #666; margin-top: 4px; }
        .cmp-section {
            background: #fff; border-radius: 12px; margin-bottom: 12px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06); overflow: hidden;
        }
        .cmp-section-title {
            padding: 14px 16px; font-weight: 700; font-size: 0.95em;
            cursor: pointer; display: flex; justify-content: space-between;
            align-items: center;
        }
        .cmp-section-body { display: none; }
        .cmp-section-body.open { display: block; }
        .cmp-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px; border-top: 1px solid #f0f0f0; font-size: 0.92em;
        }
        .cmp-row .obj-name { font-weight: 600; }
        .cmp-row .obj-detail { font-size: 0.85em; color: #888; }
        .cmp-row .changes { font-size: 0.82em; color: #7b1fa2; font-style: italic; }
        .sec-added { background: #e8f5e9; }
        .sec-removed { background: #fbe9e7; }
        .sec-modified { background: #fff3e0; }
        .sec-same { background: #f5f5f5; }
        @media (max-width: 600px) {
            .cmp-stats { flex-wrap: wrap; }
            .cmp-stat { min-width: calc(50% - 8px); }
        }
    </style>
</head>
<body>
<div class="cmp-container">
    <div class="cmp-header">
        <h2><i class="fas fa-code-compare"></i> Comparer deux inventaires</h2>
    </div>

    <div class="cmp-form">
        <form method="GET" id="compareForm">
            <label>Logement</label>
            <select name="logement_id" onchange="this.form.submit()">
                <option value="">-- Selectionnez un logement --</option>
                <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $logement_id == $l['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nom_du_logement']) ?> (<?= $l['nb_sessions'] ?> sessions)
                </option>
                <?php endforeach; ?>
            </select>

            <?php if (!empty($sessions)): ?>
            <label>Session ancienne (reference)</label>
            <select name="session1">
                <option value="">-- Session 1 --</option>
                <?php foreach ($sessions as $s): ?>
                <option value="<?= htmlspecialchars($s['id']) ?>" <?= $session1_id === $s['id'] ? 'selected' : '' ?>>
                    <?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?> (<?= $s['nb_objets'] ?> objets)
                </option>
                <?php endforeach; ?>
            </select>

            <label>Session recente (a comparer)</label>
            <select name="session2">
                <option value="">-- Session 2 --</option>
                <?php foreach ($sessions as $s): ?>
                <option value="<?= htmlspecialchars($s['id']) ?>" <?= $session2_id === $s['id'] ? 'selected' : '' ?>>
                    <?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?> (<?= $s['nb_objets'] ?> objets)
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-compare"><i class="fas fa-balance-scale"></i> Comparer</button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($diff): ?>
    <!-- Resume -->
    <div class="cmp-stats">
        <div class="cmp-stat">
            <div class="number" style="color:#2e7d32"><?= count($diff['ajoutes']) ?></div>
            <div class="label">Ajoutes</div>
        </div>
        <div class="cmp-stat">
            <div class="number" style="color:#c62828"><?= count($diff['supprimes']) ?></div>
            <div class="label">Supprimes</div>
        </div>
        <div class="cmp-stat">
            <div class="number" style="color:#e65100"><?= count($diff['modifies']) ?></div>
            <div class="label">Modifies</div>
        </div>
        <div class="cmp-stat">
            <div class="number" style="color:#888"><?= count($diff['identiques']) ?></div>
            <div class="label">Identiques</div>
        </div>
        <div class="cmp-stat">
            <div class="number" style="color:#1565c0"><?= $diff['total1'] ?> → <?= $diff['total2'] ?></div>
            <div class="label">Total objets</div>
        </div>
    </div>

    <!-- Objets ajoutes -->
    <?php if (!empty($diff['ajoutes'])): ?>
    <div class="cmp-section">
        <div class="cmp-section-title sec-added" onclick="this.nextElementSibling.classList.toggle('open')">
            <span><i class="fas fa-plus-circle" style="color:#2e7d32"></i> Ajoutes (<?= count($diff['ajoutes']) ?>)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="cmp-section-body open">
            <?php foreach ($diff['ajoutes'] as $o): ?>
            <div class="cmp-row">
                <div>
                    <span class="obj-name"><?= htmlspecialchars($o['nom_objet']) ?></span>
                    <?php if ($o['piece']): ?><span class="obj-detail"> [<?= htmlspecialchars($o['piece']) ?>]</span><?php endif; ?>
                    <?php if ((int)$o['quantite'] > 1): ?><span class="obj-detail"> x<?= (int)$o['quantite'] ?></span><?php endif; ?>
                </div>
                <span style="color:#2e7d32;font-weight:600">+ Nouveau</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Objets supprimes -->
    <?php if (!empty($diff['supprimes'])): ?>
    <div class="cmp-section">
        <div class="cmp-section-title sec-removed" onclick="this.nextElementSibling.classList.toggle('open')">
            <span><i class="fas fa-minus-circle" style="color:#c62828"></i> Supprimes (<?= count($diff['supprimes']) ?>)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="cmp-section-body open">
            <?php foreach ($diff['supprimes'] as $o): ?>
            <div class="cmp-row">
                <div>
                    <span class="obj-name"><?= htmlspecialchars($o['nom_objet']) ?></span>
                    <?php if ($o['piece']): ?><span class="obj-detail"> [<?= htmlspecialchars($o['piece']) ?>]</span><?php endif; ?>
                    <?php if ((int)$o['quantite'] > 1): ?><span class="obj-detail"> x<?= (int)$o['quantite'] ?></span><?php endif; ?>
                </div>
                <span style="color:#c62828;font-weight:600">- Manquant</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Objets modifies -->
    <?php if (!empty($diff['modifies'])): ?>
    <div class="cmp-section">
        <div class="cmp-section-title sec-modified" onclick="this.nextElementSibling.classList.toggle('open')">
            <span><i class="fas fa-pen" style="color:#e65100"></i> Modifies (<?= count($diff['modifies']) ?>)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="cmp-section-body open">
            <?php foreach ($diff['modifies'] as $m): ?>
            <div class="cmp-row" style="flex-direction:column;align-items:flex-start;">
                <div>
                    <span class="obj-name"><?= htmlspecialchars($m['objet']['nom_objet']) ?></span>
                    <?php if ($m['objet']['piece']): ?><span class="obj-detail"> [<?= htmlspecialchars($m['objet']['piece']) ?>]</span><?php endif; ?>
                </div>
                <div class="changes">
                    <?= implode(' &bull; ', array_map('htmlspecialchars', $m['changes'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Objets identiques -->
    <?php if (!empty($diff['identiques'])): ?>
    <div class="cmp-section">
        <div class="cmp-section-title sec-same" onclick="this.nextElementSibling.classList.toggle('open')">
            <span><i class="fas fa-equals" style="color:#888"></i> Identiques (<?= count($diff['identiques']) ?>)</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="cmp-section-body">
            <?php foreach ($diff['identiques'] as $o): ?>
            <div class="cmp-row">
                <span class="obj-name"><?= htmlspecialchars($o['nom_objet']) ?></span>
                <span class="obj-detail"><?= $o['piece'] ? htmlspecialchars($o['piece']) : '' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($logement_id && empty($sessions)): ?>
        <div style="text-align:center;padding:30px;color:#999;">
            Ce logement n'a pas assez de sessions terminees pour comparer.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
