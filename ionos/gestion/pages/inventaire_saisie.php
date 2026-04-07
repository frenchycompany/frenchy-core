<?php
/**
 * Saisie d'inventaire — Interface mobile moderne
 * Ajout d'objets avec photo, categorie par piece, etat, AJAX
 */
include '../config.php';
include '../pages/menu.php';

// Auto-migrations
try { $conn->exec("ALTER TABLE inventaire_objets ADD COLUMN piece VARCHAR(50) DEFAULT NULL AFTER logement_id"); } catch (PDOException $e) { error_log('inventaire_saisie.php: ' . $e->getMessage()); }
try { $conn->exec("ALTER TABLE sessions_inventaire ADD COLUMN intervenant_id INT DEFAULT NULL AFTER logement_id"); } catch (PDOException $e) { error_log('inventaire_saisie.php: ' . $e->getMessage()); }

// Verifier la session
if (!isset($_GET['session_id'])) {
    die("Session d'inventaire non specifiee.");
}
$session_id = $_GET['session_id'];

$stmt = $conn->prepare("
    SELECT s.*, l.nom_du_logement
    FROM sessions_inventaire s
    JOIN liste_logements l ON s.logement_id = l.id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo '<div style="padding:20px;text-align:center;color:#e53935">Session introuvable.</div>';
    echo '<a href="inventaire.php" style="display:block;text-align:center;padding:15px">Retour</a>';
    exit;
}

$logement_id = $session['logement_id'];
$isTerminee = ($session['statut'] === 'terminee');
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

// AJAX : supprimer la session (admin uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_session'])) {
    header('Content-Type: application/json');
    if (!$is_admin) {
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    // Supprimer les fichiers photos et QR codes (avec protection path traversal)
    $baseDir = realpath(__DIR__ . '/../');
    $stmtFiles = $conn->prepare("SELECT photo_path, qr_code_path FROM inventaire_objets WHERE session_id = ?");
    $stmtFiles->execute([$session_id]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
    foreach ($files as $f) {
        if (!empty($f['photo_path'])) {
            $fp = realpath(__DIR__ . '/../' . $f['photo_path']);
            if ($fp && strpos($fp, $baseDir . DIRECTORY_SEPARATOR) === 0) @unlink($fp);
        }
        if (!empty($f['qr_code_path'])) {
            $fp = realpath(__DIR__ . '/' . $f['qr_code_path']);
            if ($fp && strpos($fp, $baseDir . DIRECTORY_SEPARATOR) === 0) @unlink($fp);
        }
    }
    $conn->prepare("DELETE FROM inventaire_objets WHERE session_id = ?")->execute([$session_id]);
    $conn->prepare("DELETE FROM sessions_inventaire WHERE id = ?")->execute([$session_id]);
    echo json_encode(['success' => true]);
    exit;
}

// AJAX : changer l'intervenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_change_intervenant'])) {
    header('Content-Type: application/json');
    $newId = intval($_POST['intervenant_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE sessions_inventaire SET intervenant_id = ? WHERE id = ?");
    $stmt->execute([$newId ?: null, $session_id]);
    $nom = 'Non attribue';
    if ($newId) {
        $nStmt = $conn->prepare("SELECT nom FROM intervenant WHERE id = ?");
        $nStmt->execute([$newId]);
        $nom = $nStmt->fetchColumn() ?: 'Inconnu';
    }
    echo json_encode(['success' => true, 'nom' => $nom]);
    exit;
}

// Traitement AJAX : ajout d'objet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax_action'] === 'ajouter') {
        $nom = trim($_POST['nom_objet'] ?? '');
        $quantite = max(1, intval($_POST['quantite'] ?? 1));
        $piece = $_POST['piece'] ?? null;
        $marque = $_POST['marque'] ?? '';
        $etat = $_POST['etat'] ?? 'bon';
        $date_acquisition = $_POST['date_acquisition'] ?? null;
        $valeur = $_POST['valeur'] ?? null;
        $remarques = $_POST['remarques'] ?? '';

        if (empty($nom)) {
            echo json_encode(['error' => 'Nom requis']);
            exit;
        }

        // Photo
        $photo_path = null;
        if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === 0) {
            $upload_dir = '../uploads/inventaire/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            if (!is_writable($upload_dir)) @chmod($upload_dir, 0775);
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
            if (in_array($ext, $allowed)) {
                $filename = 'inv_' . $session_id . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    $photo_path = 'uploads/inventaire/' . $filename;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO inventaire_objets
            (session_id, logement_id, piece, nom_objet, quantite, marque, etat, date_acquisition, valeur, remarques, photo_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$session_id, $logement_id, $piece, $nom, $quantite, $marque, $etat,
                        $date_acquisition ?: null, $valeur ?: null, $remarques, $photo_path]);

        $newId = $conn->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'photo_path' => $photo_path]);
        exit;
    }

    if ($_POST['ajax_action'] === 'supprimer') {
        $obj_id = intval($_POST['obj_id'] ?? 0);
        // Supprimer le fichier photo
        $stmt = $conn->prepare("SELECT photo_path FROM inventaire_objets WHERE id = ? AND session_id = ?");
        $stmt->execute([$obj_id, $session_id]);
        $obj = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($obj && $obj['photo_path']) {
            $baseDir = realpath(__DIR__ . '/../');
            $fp = realpath(__DIR__ . '/../' . $obj['photo_path']);
            if ($fp && strpos($fp, $baseDir . DIRECTORY_SEPARATOR) === 0) @unlink($fp);
        }
        $stmt = $conn->prepare("DELETE FROM inventaire_objets WHERE id = ? AND session_id = ?");
        $stmt->execute([$obj_id, $session_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['ajax_action'] === 'modifier') {
        $obj_id = intval($_POST['obj_id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        $allowed_fields = ['nom_objet', 'quantite', 'piece', 'marque', 'etat', 'remarques'];
        if (in_array($field, $allowed_fields)) {
            $stmt = $conn->prepare("UPDATE inventaire_objets SET $field = ? WHERE id = ? AND session_id = ?");
            $stmt->execute([$value, $obj_id, $session_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Champ non autorise']);
        }
        exit;
    }

    echo json_encode(['error' => 'Action inconnue']);
    exit;
}

// Charger les objets existants
$stmt = $conn->prepare("SELECT * FROM inventaire_objets WHERE session_id = ? ORDER BY piece, nom_objet");
$stmt->execute([$session_id]);
$objets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par piece
$parPiece = [];
foreach ($objets as $obj) {
    $p = $obj['piece'] ?: 'Non classe';
    $parPiece[$p][] = $obj;
}

$nbObjets = count($objets);

// Charger les intervenants pour le selecteur
$intervenants = $conn->query("SELECT id, nom FROM intervenant WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$currentIntervenantNom = 'Non attribue';
if (!empty($session['intervenant_id'])) {
    foreach ($intervenants as $int) {
        if ($int['id'] == $session['intervenant_id']) {
            $currentIntervenantNom = $int['nom'];
            break;
        }
    }
}

// Liste des pieces possibles
$pieces = [
    'Salon', 'Cuisine', 'Chambre 1', 'Chambre 2', 'Chambre 3',
    'Salle de bain', 'WC', 'Entree', 'Couloir', 'Terrasse / Balcon',
    'Buanderie', 'Bureau', 'Autre'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaire — <?= htmlspecialchars($session['nom_du_logement']) ?></title>
    <style>
        .inv-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 10px 30px;
        }
        /* Header */
        .inv-topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            padding: 12px 0 8px;
            border-bottom: 1px solid #eee;
        }
        .inv-topbar h3 { margin: 0; font-size: 1.1em; color: #333; }
        .inv-topbar small { color: #888; }
        .inv-count {
            display: inline-block;
            background: #e3f2fd;
            color: #1565c0;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 8px;
        }
        .inv-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-en_cours { background: #fff3e0; color: #e65100; }
        .status-terminee { background: #e8f5e9; color: #2e7d32; }
        /* Formulaire d'ajout */
        .inv-form {
            background: #fff;
            border-radius: 15px;
            padding: 18px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin: 15px 0;
        }
        .inv-form h4 {
            margin: 0 0 15px;
            font-size: 1.05em;
            color: #1565c0;
        }
        .form-row {
            margin-bottom: 12px;
        }
        .form-row label {
            display: block;
            font-weight: 600;
            font-size: 0.9em;
            color: #555;
            margin-bottom: 4px;
        }
        .form-row input, .form-row select, .form-row textarea {
            width: 100%;
            padding: 12px;
            font-size: 1em;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            box-sizing: border-box;
            background: #fafafa;
        }
        .form-row input:focus, .form-row select:focus {
            border-color: #1976d2;
            outline: none;
            background: #fff;
        }
        .form-row-half {
            display: flex;
            gap: 10px;
        }
        .form-row-half .form-row { flex: 1; }
        .photo-capture-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            background: #e3f2fd;
            color: #1565c0;
            border: 2px dashed #90caf9;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
        }
        .photo-capture-btn.has-photo {
            background: #e8f5e9;
            border-color: #81c784;
            color: #2e7d32;
        }
        .form-row input[type="file"] { display: none; }
        .photo-preview-small {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-top: 8px;
            display: none;
        }
        .btn-add {
            width: 100%;
            padding: 15px;
            font-size: 1.1em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            cursor: pointer;
            margin-top: 8px;
            transition: transform 0.1s;
        }
        .btn-add:active { transform: scale(0.97); }
        .btn-add:disabled { background: #bbb; cursor: not-allowed; }
        /* Toggle pour les champs optionnels */
        .toggle-optional {
            color: #1976d2;
            font-size: 0.9em;
            cursor: pointer;
            padding: 6px 0;
            display: block;
            text-align: center;
        }
        .optional-fields {
            display: none;
        }
        .optional-fields.open { display: block; }
        /* Liste des objets */
        .inv-piece-section { margin-top: 20px; }
        .inv-piece-title {
            font-weight: 700;
            font-size: 1em;
            color: #1565c0;
            padding: 8px 0;
            border-bottom: 2px solid #e3f2fd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .inv-piece-title .piece-count {
            font-size: 0.8em;
            font-weight: 400;
            color: #888;
        }
        .inv-obj-card {
            background: #fff;
            border-radius: 12px;
            margin: 8px 0;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06);
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
        }
        .inv-obj-photo {
            width: 55px;
            height: 55px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #f0f0f0;
        }
        .inv-obj-nophoto {
            width: 55px;
            height: 55px;
            border-radius: 8px;
            flex-shrink: 0;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 1.4em;
        }
        .inv-obj-info {
            flex: 1;
            min-width: 0;
        }
        .inv-obj-name {
            font-weight: 600;
            font-size: 0.95em;
            color: #333;
        }
        .inv-obj-meta {
            font-size: 0.8em;
            color: #888;
            margin-top: 2px;
        }
        .inv-obj-qty {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.82em;
            font-weight: 600;
        }
        .inv-obj-etat {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .etat-neuf { background: #e8f5e9; color: #2e7d32; }
        .etat-bon { background: #e3f2fd; color: #1565c0; }
        .etat-use { background: #fff3e0; color: #e65100; }
        .etat-abime { background: #fbe9e7; color: #c62828; }
        .inv-obj-delete {
            color: #e53935;
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            padding: 8px;
            flex-shrink: 0;
        }
        /* Boutons bas */
        .inv-footer-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        .inv-footer-actions a, .inv-footer-actions button {
            flex: 1;
            padding: 15px;
            font-size: 1em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-validate {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff;
        }
        .btn-back {
            background: #e3f2fd;
            color: #1976d2;
        }
        .btn-delete-session-main {
            background: #fff;
            color: #e53935;
            border: 2px solid #e53935;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95em;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .btn-delete-session-main:hover {
            background: #e53935;
            color: #fff;
        }
        @media (max-width: 600px) {
            .inv-container { padding: 0 6px 30px; }
            .form-row-half { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>
<div class="inv-container">
    <div class="inv-topbar">
        <h3>
            <i class="fas fa-boxes-stacked"></i>
            <?= htmlspecialchars($session['nom_du_logement']) ?>
            <span class="inv-count" id="totalCount"><?= $nbObjets ?> objet<?= $nbObjets > 1 ? 's' : '' ?></span>
            <span class="inv-status-badge status-<?= $session['statut'] ?>"><?= $session['statut'] === 'en_cours' ? 'En cours' : 'Terminee' ?></span>
        </h3>
        <small>Session #<?= htmlspecialchars($session_id) ?> — <?= date('d/m/Y H:i', strtotime($session['date_creation'])) ?></small>
        <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
            <i class="fas fa-user" style="color:#666;font-size:0.85em;"></i>
            <span id="intervenantLabel" style="font-size:0.88em;color:#555;font-weight:600;"><?= htmlspecialchars($currentIntervenantNom) ?></span>
            <button type="button" onclick="document.getElementById('invIntervenantSelect').style.display=document.getElementById('invIntervenantSelect').style.display==='none'?'block':'none'" style="background:none;border:1px solid #ccc;border-radius:6px;padding:2px 8px;font-size:0.8em;color:#43a047;cursor:pointer;">
                <i class="fas fa-pen"></i> Changer
            </button>
        </div>
        <div id="invIntervenantSelect" style="display:none;margin-top:6px;">
            <select onchange="changeInvIntervenant(this.value)" style="width:100%;padding:8px;border:1px solid #43a047;border-radius:8px;font-size:0.95em;">
                <option value="0">-- Non attribue --</option>
                <?php foreach ($intervenants as $int): ?>
                    <option value="<?= $int['id'] ?>" <?= $int['id'] == ($session['intervenant_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($int['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (!$isTerminee): ?>
    <!-- Formulaire d'ajout -->
    <div class="inv-form">
        <h4><i class="fas fa-plus-circle"></i> Ajouter un objet</h4>
        <form id="addForm" enctype="multipart/form-data">
            <div class="form-row-half">
                <div class="form-row">
                    <label for="nom_objet">Nom de l'objet *</label>
                    <input type="text" id="nom_objet" name="nom_objet" placeholder="Ex: Television Samsung" required>
                </div>
                <div class="form-row" style="max-width:90px">
                    <label for="quantite">Qte</label>
                    <input type="number" id="quantite" name="quantite" value="1" min="1">
                </div>
            </div>

            <div class="form-row-half">
                <div class="form-row">
                    <label for="piece">Piece</label>
                    <select id="piece" name="piece">
                        <option value="">-- Piece --</option>
                        <?php foreach ($pieces as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="etat">Etat</label>
                    <select id="etat" name="etat">
                        <option value="neuf">Neuf</option>
                        <option value="bon" selected>Bon</option>
                        <option value="use">Use</option>
                        <option value="abime">Abime</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <label class="photo-capture-btn" id="photoBtnLabel" onclick="document.getElementById('photo').click()">
                    <i class="fas fa-camera"></i> <span id="photoText">Prendre une photo</span>
                </label>
                <input type="file" id="photo" name="photo" accept="image/*" capture="environment" onchange="previewPhoto(this)">
                <img class="photo-preview-small" id="photoPreview">
            </div>

            <a class="toggle-optional" onclick="document.getElementById('optionalFields').classList.toggle('open')">
                <i class="fas fa-chevron-down"></i> Plus de details
            </a>
            <div class="optional-fields" id="optionalFields">
                <div class="form-row">
                    <label for="marque">Marque</label>
                    <input type="text" id="marque" name="marque" placeholder="Ex: Samsung, Ikea...">
                </div>
                <div class="form-row-half">
                    <div class="form-row">
                        <label for="date_acquisition">Date acquisition</label>
                        <input type="date" id="date_acquisition" name="date_acquisition">
                    </div>
                    <div class="form-row">
                        <label for="valeur">Valeur (EUR)</label>
                        <input type="number" id="valeur" name="valeur" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <label for="remarques">Remarques</label>
                    <textarea id="remarques" name="remarques" rows="2" placeholder="Notes..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn-add" id="btnAdd">
                <i class="fas fa-plus"></i> Ajouter l'objet
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Liste des objets par piece -->
    <div id="objetsListe">
    <?php if (empty($objets)): ?>
        <p id="emptyMsg" style="text-align:center; color:#999; padding:30px 0;">Aucun objet ajoute pour le moment.</p>
    <?php else: ?>
        <?php foreach ($parPiece as $pieceName => $items): ?>
        <div class="inv-piece-section" data-piece="<?= htmlspecialchars($pieceName) ?>">
            <div class="inv-piece-title">
                <span><i class="fas fa-door-open"></i> <?= htmlspecialchars($pieceName) ?></span>
                <span class="piece-count"><?= count($items) ?> objet<?= count($items) > 1 ? 's' : '' ?></span>
            </div>
            <?php foreach ($items as $obj): ?>
            <div class="inv-obj-card" id="obj-<?= $obj['id'] ?>">
                <?php if ($obj['photo_path']): ?>
                    <img class="inv-obj-photo" src="../<?= htmlspecialchars($obj['photo_path']) ?>"
                         onclick="window.open(this.src)">
                <?php else: ?>
                    <div class="inv-obj-nophoto"><i class="fas fa-image"></i></div>
                <?php endif; ?>
                <div class="inv-obj-info">
                    <div class="inv-obj-name">
                        <?= htmlspecialchars($obj['nom_objet']) ?>
                        <?php if ($obj['quantite'] > 1): ?>
                            <span class="inv-obj-qty">x<?= (int)$obj['quantite'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="inv-obj-meta">
                        <?php if ($obj['etat']): ?>
                            <span class="inv-obj-etat etat-<?= htmlspecialchars($obj['etat']) ?>"><?= htmlspecialchars(ucfirst($obj['etat'])) ?></span>
                        <?php endif; ?>
                        <?php if ($obj['marque']): ?> <?= htmlspecialchars($obj['marque']) ?><?php endif; ?>
                        <?php if ($obj['remarques']): ?> — <?= htmlspecialchars($obj['remarques']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if (!$isTerminee): ?>
                <button class="inv-obj-delete" onclick="deleteObj(<?= $obj['id'] ?>)" title="Supprimer">
                    <i class="fas fa-trash-alt"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="inv-footer-actions">
        <a href="inventaire.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
        <?php if (!$isTerminee): ?>
        <a href="inventaire_valider.php?session_id=<?= urlencode($session_id) ?>" class="btn-validate">
            <i class="fas fa-check-double"></i> Valider l'inventaire
        </a>
        <?php endif; ?>
    </div>
    <?php if ($is_admin): ?>
    <div style="text-align:center;margin-top:20px;">
        <button onclick="deleteSession()" class="btn-delete-session-main">
            <i class="fas fa-trash-alt"></i> Supprimer cette session
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
const sessionId = '<?= htmlspecialchars($session_id, ENT_QUOTES) ?>';
let totalObjets = <?= $nbObjets ?>;

function changeInvIntervenant(id) {
    var fd = new FormData();
    fd.append('ajax_change_intervenant', '1');
    fd.append('intervenant_id', id);
    fetch('inventaire_saisie.php?session_id=' + sessionId, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('intervenantLabel').textContent = data.nom;
                document.getElementById('invIntervenantSelect').style.display = 'none';
            }
        });
}

function updateCount() {
    const el = document.getElementById('totalCount');
    el.textContent = totalObjets + ' objet' + (totalObjets > 1 ? 's' : '');
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
            document.getElementById('photoBtnLabel').classList.add('has-photo');
            document.getElementById('photoText').textContent = 'Photo prise !';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Ajout d'objet via AJAX
document.getElementById('addForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAdd');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';

    const formData = new FormData(this);
    formData.append('ajax_action', 'ajouter');

    fetch('inventaire_saisie.php?session_id=' + sessionId, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                totalObjets++;
                updateCount();

                // Ajouter l'objet dans la liste
                const nom = document.getElementById('nom_objet').value;
                const qte = document.getElementById('quantite').value;
                const piece = document.getElementById('piece').value || 'Non classe';
                const etat = document.getElementById('etat').value;
                const marque = document.getElementById('marque').value;

                // Trouver ou creer la section piece
                let section = document.querySelector('.inv-piece-section[data-piece="' + CSS.escape(piece) + '"]');
                if (!section) {
                    // Supprimer le message vide
                    document.getElementById('emptyMsg')?.remove();

                    section = document.createElement('div');
                    section.className = 'inv-piece-section';
                    section.dataset.piece = piece;
                    section.innerHTML =
                        '<div class="inv-piece-title">' +
                            '<span><i class="fas fa-door-open"></i> ' + escapeHtml(piece) + '</span>' +
                            '<span class="piece-count">0 objet</span>' +
                        '</div>';
                    document.getElementById('objetsListe').appendChild(section);
                }

                // Incrementer compteur piece
                const countEl = section.querySelector('.piece-count');
                const currentCount = parseInt(countEl.textContent) + 1;
                countEl.textContent = currentCount + ' objet' + (currentCount > 1 ? 's' : '');

                // Creer la carte
                const photoHtml = data.photo_path
                    ? '<img class="inv-obj-photo" src="../' + escapeHtml(data.photo_path) + '" onclick="window.open(this.src)">'
                    : '<div class="inv-obj-nophoto"><i class="fas fa-image"></i></div>';

                const card = document.createElement('div');
                card.className = 'inv-obj-card';
                card.id = 'obj-' + data.id;
                card.innerHTML =
                    photoHtml +
                    '<div class="inv-obj-info">' +
                        '<div class="inv-obj-name">' +
                            escapeHtml(nom) +
                            (qte > 1 ? ' <span class="inv-obj-qty">x' + qte + '</span>' : '') +
                        '</div>' +
                        '<div class="inv-obj-meta">' +
                            '<span class="inv-obj-etat etat-' + etat + '">' + etat.charAt(0).toUpperCase() + etat.slice(1) + '</span>' +
                            (marque ? ' ' + escapeHtml(marque) : '') +
                        '</div>' +
                    '</div>' +
                    '<button class="inv-obj-delete" onclick="deleteObj(' + data.id + ')" title="Supprimer">' +
                        '<i class="fas fa-trash-alt"></i>' +
                    '</button>';
                section.appendChild(card);

                // Reset le formulaire
                document.getElementById('addForm').reset();
                document.getElementById('photoPreview').style.display = 'none';
                document.getElementById('photoBtnLabel').classList.remove('has-photo');
                document.getElementById('photoText').textContent = 'Prendre une photo';
                document.getElementById('nom_objet').focus();
            } else {
                alert(data.error || 'Erreur');
            }
        })
        .catch(function() { alert('Erreur de connexion'); })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Ajouter l\'objet';
        });
});

function deleteObj(id) {
    if (!confirm('Supprimer cet objet ?')) return;

    const formData = new FormData();
    formData.append('ajax_action', 'supprimer');
    formData.append('obj_id', id);

    fetch('inventaire_saisie.php?session_id=' + sessionId, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                const card = document.getElementById('obj-' + id);
                const section = card.closest('.inv-piece-section');
                card.remove();

                totalObjets--;
                updateCount();

                // Mettre a jour le compteur de la piece
                const remaining = section.querySelectorAll('.inv-obj-card').length;
                if (remaining === 0) {
                    section.remove();
                } else {
                    section.querySelector('.piece-count').textContent = remaining + ' objet' + (remaining > 1 ? 's' : '');
                }
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function deleteSession() {
    if (!confirm('Supprimer cette session et tous ses objets ? Cette action est irréversible.')) return;
    var fd = new FormData();
    fd.append('ajax_delete_session', '1');
    fetch('inventaire_saisie.php?session_id=' + sessionId, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.href = 'liste_sessions.php';
            } else {
                alert(data.error || 'Erreur lors de la suppression');
            }
        })
        .catch(function() { alert('Erreur de connexion'); });
}
</script>
</body>
</html>
