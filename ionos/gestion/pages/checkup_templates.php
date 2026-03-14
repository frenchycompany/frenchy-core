<?php
/**
 * Templates de checkup — Items personnalisables par logement
 * Permet d'ajouter des items specifiques (piscine, jardin, etc.) en plus des items standards
 */
include '../config.php';
include '../pages/menu.php';

// Tables requises : voir db/install_tables.php

$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;

// Traitement AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax_action'] === 'ajouter') {
        $lid = isset($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $categorie = trim(strip_tags($_POST['categorie'] ?? ''));
        $nom_item = trim(strip_tags($_POST['nom_item'] ?? ''));

        if (empty($categorie) || empty($nom_item)) {
            echo json_encode(['error' => 'Categorie et nom requis']);
            exit;
        }

        // logement_id NULL = template global (tous logements)
        $stmt = $conn->prepare("INSERT INTO checkup_templates (logement_id, categorie, nom_item) VALUES (?, ?, ?)");
        $stmt->execute([$lid ?: null, $categorie, $nom_item]);
        echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
        exit;
    }

    if ($_POST['ajax_action'] === 'supprimer') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM checkup_templates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['ajax_action'] === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE checkup_templates SET actif = NOT actif WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Charger les logements
$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Charger les templates
$query = "SELECT * FROM checkup_templates WHERE logement_id IS NULL";
$params = [];
if ($logement_id) {
    $query = "SELECT * FROM checkup_templates WHERE logement_id IS NULL OR logement_id = ?";
    $params = [$logement_id];
}
$query .= " ORDER BY categorie, ordre, nom_item";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par categorie
$parCategorie = [];
foreach ($templates as $t) {
    $parCategorie[$t['categorie']][] = $t;
}

// Categories predefinies
$categories = [
    'Piscine', 'Jardin / Exterieur', 'Garage', 'Cave',
    'Buanderie', 'Terrasse / Rooftop', 'Parking', 'Cuisine pro',
    'Sauna / Spa', 'Salle de sport', 'Etat general', 'Autre'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates de Checkup</title>
    <style>
        .tpl-container { max-width: 700px; margin: 0 auto; padding: 0 12px 40px; }
        .tpl-header {
            background: linear-gradient(135deg, #00897b, #00796b);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .tpl-header h2 { margin: 0; font-size: 1.3em; }
        .tpl-header p { margin: 8px 0 0; opacity: 0.85; font-size: 0.9em; }
        .tpl-form {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
        }
        .tpl-form label { display: block; font-weight: 600; color: #555; margin-bottom: 4px; font-size: 0.9em; }
        .tpl-form select, .tpl-form input {
            width: 100%; padding: 10px; font-size: 1em;
            border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 10px; box-sizing: border-box;
        }
        .tpl-form .row { display: flex; gap: 10px; }
        .tpl-form .row > * { flex: 1; }
        .btn-add {
            width: 100%; padding: 14px; font-size: 1em; font-weight: 700;
            border: none; border-radius: 10px;
            background: linear-gradient(135deg, #00897b, #00796b);
            color: #fff; cursor: pointer;
        }
        .tpl-cat-title {
            font-weight: 700; color: #00796b; padding: 10px 0; margin-top: 15px;
            border-bottom: 2px solid #e0f2f1; font-size: 1.05em;
        }
        .tpl-item {
            display: flex; align-items: center; justify-content: space-between;
            background: #fff; border-radius: 10px; padding: 12px 14px; margin: 6px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .tpl-item.inactive { opacity: 0.5; }
        .tpl-item-name { font-weight: 600; font-size: 0.95em; }
        .tpl-item-scope { font-size: 0.78em; color: #888; }
        .tpl-item-actions { display: flex; gap: 6px; }
        .tpl-item-actions button {
            padding: 6px 10px; border: none; border-radius: 6px;
            font-size: 0.85em; cursor: pointer;
        }
        .btn-toggle { background: #e3f2fd; color: #1565c0; }
        .btn-delete { background: #fbe9e7; color: #c62828; }
        .info-box {
            background: #e0f2f1; border-radius: 10px; padding: 12px 16px;
            font-size: 0.9em; color: #00695c; margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="tpl-container">
    <div class="tpl-header">
        <h2><i class="fas fa-puzzle-piece"></i> Templates de Checkup</h2>
        <p>Items personnalises ajoutes aux checkups (piscine, jardin...)</p>
    </div>

    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        Les items <strong>globaux</strong> (aucun logement) s'appliquent a tous les checkups.
        Les items lies a un logement specifique ne s'ajoutent qu'a ce logement.
    </div>

    <div class="tpl-form">
        <form id="addForm">
            <label>Logement (vide = global pour tous)</label>
            <select name="logement_id" id="filterLogement" onchange="window.location.href='?logement_id='+this.value">
                <option value="">Global (tous les logements)</option>
                <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $logement_id == $l['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nom_du_logement']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <div class="row">
                <div>
                    <label>Categorie</label>
                    <select name="categorie" id="categorie">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Nom de l'item</label>
                    <input type="text" name="nom_item" id="nom_item" placeholder="Ex: Niveau d'eau piscine" required>
                </div>
            </div>
            <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter l'item</button>
        </form>
    </div>

    <!-- Liste des templates -->
    <div id="templatesList">
    <?php if (empty($parCategorie)): ?>
        <div style="text-align:center;padding:30px;color:#999;">Aucun template personnalise pour le moment.</div>
    <?php else: ?>
        <?php foreach ($parCategorie as $cat => $items): ?>
        <div class="tpl-cat-title"><i class="fas fa-tag"></i> <?= htmlspecialchars($cat) ?> (<?= count($items) ?>)</div>
        <?php foreach ($items as $item): ?>
        <div class="tpl-item <?= $item['actif'] ? '' : 'inactive' ?>" id="tpl-<?= $item['id'] ?>">
            <div>
                <div class="tpl-item-name"><?= htmlspecialchars($item['nom_item']) ?></div>
                <div class="tpl-item-scope"><?= $item['logement_id'] ? 'Specifique logement' : 'Global' ?></div>
            </div>
            <div class="tpl-item-actions">
                <button class="btn-toggle" onclick="toggleTpl(<?= $item['id'] ?>)">
                    <i class="fas fa-<?= $item['actif'] ? 'eye-slash' : 'eye' ?>"></i>
                </button>
                <button class="btn-delete" onclick="deleteTpl(<?= $item['id'] ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('addForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData();
    formData.append('ajax_action', 'ajouter');
    formData.append('logement_id', document.getElementById('filterLogement').value);
    formData.append('categorie', document.getElementById('categorie').value);
    formData.append('nom_item', document.getElementById('nom_item').value);

    fetch('checkup_templates.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Erreur');
            }
        });
});

function toggleTpl(id) {
    const formData = new FormData();
    formData.append('ajax_action', 'toggle');
    formData.append('id', id);
    fetch('checkup_templates.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tpl-' + id).classList.toggle('inactive');
            }
        });
}

function deleteTpl(id) {
    if (!confirm('Supprimer ce template ?')) return;
    const formData = new FormData();
    formData.append('ajax_action', 'supprimer');
    formData.append('id', id);
    fetch('checkup_templates.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tpl-' + id).remove();
            }
        });
}
</script>
</body>
</html>
