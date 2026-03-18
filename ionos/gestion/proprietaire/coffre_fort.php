<?php
/**
 * Espace Proprietaire - Coffre-fort numerique
 * Upload securise de documents sensibles (RIB, identite, diagnostics, etc.)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/coffre_fort_helper.php';

$coffre = new CoffreFort($conn);
$userId = $_SESSION['user_id'] ?? $_SESSION['proprietaire_id'] ?? 0;
$message = '';
$messageType = '';

$categories = [
    'identite'    => ['label' => 'Piece d\'identite', 'icon' => 'fa-id-card', 'color' => '#1976d2'],
    'rib'         => ['label' => 'RIB / IBAN', 'icon' => 'fa-university', 'color' => '#28a745'],
    'bail'        => ['label' => 'Bail / Contrat', 'icon' => 'fa-file-signature', 'color' => '#6f42c1'],
    'diagnostic'  => ['label' => 'Diagnostics (DPE, etc.)', 'icon' => 'fa-clipboard-check', 'color' => '#fd7e14'],
    'assurance'   => ['label' => 'Assurance', 'icon' => 'fa-shield-alt', 'color' => '#e53935'],
    'fiscal'      => ['label' => 'Documents fiscaux', 'icon' => 'fa-file-invoice', 'color' => '#00acc1'],
    'photo'       => ['label' => 'Photos du bien', 'icon' => 'fa-camera', 'color' => '#8e24aa'],
    'autre'       => ['label' => 'Autre', 'icon' => 'fa-folder', 'color' => '#757575'],
];

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && proprio_validate_csrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && isset($_FILES['fichier']) && $_FILES['fichier']['error'] !== UPLOAD_ERR_NO_FILE) {
        $categorie = $_POST['categorie'] ?? 'autre';
        $description = trim($_POST['description'] ?? '');
        $result = $coffre->upload($_FILES['fichier'], $categorie, $userId, $description, '', $proprietaire_id);
        if ($result['success']) {
            $message = 'Document ajoute au coffre-fort avec succes.';
            $messageType = 'success';
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    }

    if ($action === 'supprimer') {
        $fichierId = (int) ($_POST['fichier_id'] ?? 0);
        // Verifier que le fichier appartient au proprio
        $f = $coffre->getFichier($fichierId);
        if ($f && (int)($f['proprietaire_id'] ?? 0) === $proprietaire_id) {
            $coffre->supprimer($fichierId, $userId);
            $message = 'Document supprime.';
            $messageType = 'success';
        } else {
            $message = 'Document introuvable.';
            $messageType = 'error';
        }
    }
}

// ============================================================
// DONNEES
// ============================================================
$fichiers = $coffre->listerParProprietaire($proprietaire_id);
$parCategorie = [];
foreach ($fichiers as $f) {
    $parCategorie[$f['categorie']][] = $f;
}

$filtreCat = $_GET['cat'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffre-fort - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .cat-card { background: #fff; border-radius: 12px; padding: 16px; text-align: center; cursor: pointer; border: 2px solid #f0f0f0; transition: all 0.2s; text-decoration: none; color: inherit; }
        .cat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .cat-card.active { border-color: #1976d2; background: #f0f7ff; }
        .cat-card .cat-icon { font-size: 1.8rem; margin-bottom: 8px; }
        .cat-card .cat-label { font-size: 0.82rem; font-weight: 600; }
        .cat-card .cat-count { font-size: 0.75rem; color: #999; margin-top: 4px; }

        .file-card { background: #fff; border-radius: 10px; padding: 16px; border: 1px solid #e9ecef; display: flex; align-items: center; gap: 14px; margin-bottom: 8px; }
        .file-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; flex-shrink: 0; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-meta { font-size: 0.78rem; color: #999; }

        .upload-zone { border: 2px dashed #dee2e6; border-radius: 12px; padding: 30px; text-align: center; background: #fafafa; cursor: pointer; transition: border-color 0.2s; }
        .upload-zone:hover, .upload-zone.dragover { border-color: #1976d2; background: #f0f7ff; }
        .upload-zone i { font-size: 2.5rem; color: #aaa; margin-bottom: 10px; }

        .alert-msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 0.9rem; }
        .alert-msg.success { background: #d4edda; color: #155724; }
        .alert-msg.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-vault"></i> Coffre-fort numerique</h1>
            <p style="color:#6c757d;font-size:0.9rem;">Deposez vos documents sensibles en toute securite (chiffres AES-256)</p>
        </div>

        <?php if ($message): ?>
        <div class="alert-msg <?= $messageType ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <!-- Categories -->
        <div class="cat-grid">
            <a href="coffre_fort.php" class="cat-card <?= !$filtreCat ? 'active' : '' ?>">
                <div class="cat-icon"><i class="fas fa-folder-open" style="color:#333;"></i></div>
                <div class="cat-label">Tous</div>
                <div class="cat-count"><?= count($fichiers) ?> doc(s)</div>
            </a>
            <?php foreach ($categories as $catKey => $cat): ?>
            <a href="?cat=<?= $catKey ?>" class="cat-card <?= $filtreCat === $catKey ? 'active' : '' ?>">
                <div class="cat-icon"><i class="fas <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>;"></i></div>
                <div class="cat-label"><?= e($cat['label']) ?></div>
                <div class="cat-count"><?= count($parCategorie[$catKey] ?? []) ?> doc(s)</div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Upload -->
        <div class="card" style="margin-bottom:24px;">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                <i class="fas fa-cloud-upload-alt"></i> Deposer un document
            </h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <?= proprio_csrf_field() ?>
                <input type="hidden" name="action" value="upload">

                <div class="row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                    <div style="flex:1;min-width:200px;">
                        <label style="font-size:0.82rem;font-weight:600;margin-bottom:4px;display:block;">Categorie *</label>
                        <select name="categorie" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:0.9rem;">
                            <?php foreach ($categories as $catKey => $cat): ?>
                            <option value="<?= $catKey ?>"><?= e($cat['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:2;min-width:200px;">
                        <label style="font-size:0.82rem;font-weight:600;margin-bottom:4px;display:block;">Description (optionnel)</label>
                        <input type="text" name="description" placeholder="Ex: RIB Societe Generale, DPE 2024..." style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:0.9rem;">
                    </div>
                </div>

                <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p style="margin:0;font-weight:600;">Glissez votre fichier ici ou cliquez</p>
                    <p style="margin:4px 0 0;font-size:0.8rem;color:#999;">PDF, images, Word, Excel — max 500 Mo</p>
                    <input type="file" id="fileInput" name="fichier" style="display:none;" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" required>
                    <div id="fileLabel" style="margin-top:10px;font-weight:600;color:#1976d2;display:none;"></div>
                </div>

                <button type="submit" style="margin-top:12px;background:#1976d2;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.9rem;">
                    <i class="fas fa-lock"></i> Chiffrer et deposer
                </button>
            </form>
        </div>

        <!-- Fichiers -->
        <?php
        $affichage = $fichiers;
        if ($filtreCat) {
            $affichage = $parCategorie[$filtreCat] ?? [];
        }
        ?>
        <?php if (empty($affichage)): ?>
        <div style="text-align:center;padding:40px;color:#999;">
            <i class="fas fa-folder-open" style="font-size:3rem;margin-bottom:12px;"></i>
            <p>Aucun document<?= $filtreCat ? ' dans cette categorie' : '' ?>.</p>
        </div>
        <?php else: ?>
        <?php foreach ($affichage as $f):
            $cat = $categories[$f['categorie']] ?? $categories['autre'];
        ?>
        <div class="file-card">
            <div class="file-icon" style="background:<?= $cat['color'] ?>;">
                <i class="fas <?= $cat['icon'] ?>"></i>
            </div>
            <div class="file-info">
                <div class="file-name" title="<?= e($f['nom_original']) ?>"><?= e($f['nom_original']) ?></div>
                <div class="file-meta">
                    <?= e($cat['label']) ?>
                    <?php if (!empty($f['description'])): ?> — <?= e($f['description']) ?><?php endif; ?>
                    · <?= CoffreFort::formatTaille((int)$f['taille']) ?>
                    · <?= date('d/m/Y', strtotime($f['created_at'])) ?>
                </div>
            </div>
            <form method="POST" style="flex-shrink:0;" onsubmit="return confirm('Supprimer ce document ?');">
                <?= proprio_csrf_field() ?>
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="fichier_id" value="<?= (int)$f['id'] ?>">
                <button type="submit" style="background:none;border:none;color:#dc3545;cursor:pointer;font-size:1.1rem;" title="Supprimer">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileLabel = document.getElementById('fileLabel');

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        fileLabel.textContent = e.dataTransfer.files[0].name;
        fileLabel.style.display = 'block';
    }
});
fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
        fileLabel.textContent = fileInput.files[0].name;
        fileLabel.style.display = 'block';
    }
});
</script>
</body>
</html>
