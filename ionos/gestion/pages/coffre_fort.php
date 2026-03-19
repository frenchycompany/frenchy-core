<?php
/**
 * coffre_fort.php — Coffre-fort admin — Vue par proprietaire
 * Acces direct pour super_admin/admin (pas de 2FA)
 */
require_once __DIR__ . '/../config.php';
include __DIR__ . '/menu.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/coffre_fort_helper.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id_intervenant'] ?? 0;
$coffre = new CoffreFort($conn);
$feedback = '';

// Proprio selectionne ?
$selectedProprio = isset($_GET['proprio']) ? (int)$_GET['proprio'] : null;

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && $selectedProprio && isset($_FILES['fichier']) && $_FILES['fichier']['error'] !== UPLOAD_ERR_NO_FILE) {
        $categorie = $_POST['categorie'] ?? 'autre';
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $result = $coffre->upload($_FILES['fichier'], $categorie, $userId, $description, $tags, $selectedProprio);
        if ($result['success']) {
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Document ajoute au coffre-fort.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } else {
            $feedback = '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>';
        }
    }

    if ($action === 'supprimer') {
        $fichierId = (int) ($_POST['fichier_id'] ?? 0);
        $coffre->supprimer($fichierId, $userId);
        $feedback = '<div class="alert alert-success alert-dismissible fade show">Document supprime.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// ============================================================
// DONNEES
// ============================================================

$categories = [
    'identite'    => ['label' => 'Identite', 'icon' => 'fa-id-card', 'color' => '#1976d2'],
    'rib'         => ['label' => 'RIB / IBAN', 'icon' => 'fa-university', 'color' => '#28a745'],
    'bail'        => ['label' => 'Bail / Contrat', 'icon' => 'fa-file-signature', 'color' => '#6f42c1'],
    'diagnostic'  => ['label' => 'Diagnostics', 'icon' => 'fa-clipboard-check', 'color' => '#fd7e14'],
    'assurance'   => ['label' => 'Assurance', 'icon' => 'fa-shield-alt', 'color' => '#e53935'],
    'fiscal'      => ['label' => 'Fiscal', 'icon' => 'fa-file-invoice', 'color' => '#00acc1'],
    'photo'       => ['label' => 'Photos', 'icon' => 'fa-camera', 'color' => '#8e24aa'],
    'autre'       => ['label' => 'Autre', 'icon' => 'fa-folder', 'color' => '#757575'],
];

// Liste des proprietaires avec nombre de docs
$proprios = [];
try {
    // S'assurer que proprietaire_id existe
    try {
        $cols = array_column($conn->query("SHOW COLUMNS FROM coffre_fort_fichiers")->fetchAll(), 'Field');
        if (!in_array('proprietaire_id', $cols)) {
            $conn->exec("ALTER TABLE coffre_fort_fichiers ADD COLUMN proprietaire_id INT DEFAULT NULL AFTER uploade_par, ADD INDEX idx_proprio (proprietaire_id)");
        }
    } catch (PDOException $e) {}

    $proprios = $conn->query("
        SELECT p.id, p.nom, p.prenom, p.email, p.telephone,
               COUNT(f.id) AS nb_docs,
               COALESCE(SUM(f.taille), 0) AS taille_totale,
               MAX(f.created_at) AS dernier_upload
        FROM FC_proprietaires p
        LEFT JOIN coffre_fort_fichiers f ON f.proprietaire_id = p.id AND f.supprime = 0
        WHERE p.actif = 1
        GROUP BY p.id
        ORDER BY p.nom, p.prenom
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('coffre_fort admin: ' . $e->getMessage());
}

// Si proprio selectionne, charger ses infos et docs
$proprioInfo = null;
$fichiers = [];
$filtreCat = $_GET['cat'] ?? '';
if ($selectedProprio) {
    try {
        $stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE id = ?");
        $stmt->execute([$selectedProprio]);
        $proprioInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if ($proprioInfo) {
        $fichiers = $coffre->listerParProprietaire($selectedProprio, $filtreCat);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffre-Fort — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .vault-header { background: linear-gradient(135deg, #1B3A6B, #0d2240); color: #fff; padding: 24px 28px; border-radius: 12px; margin-bottom: 24px; }
        .proprio-card { background: #fff; border-radius: 10px; padding: 16px; border: 1px solid #e9ecef; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 14px; }
        .proprio-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-1px); }
        .proprio-card.active { border-color: #1B3A6B; background: #f0f4ff; }
        .proprio-avatar { width: 44px; height: 44px; border-radius: 50%; background: #1B3A6B; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; flex-shrink: 0; }
        .proprio-name { font-weight: 600; font-size: 0.95rem; }
        .proprio-meta { font-size: 0.78rem; color: #999; }
        .doc-count { background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 0.8rem; margin-left: auto; }
        .doc-count.empty { background: #f5f5f5; color: #999; }
        .file-card { background: #fff; border-radius: 10px; padding: 16px; border: 1px solid #e9ecef; display: flex; align-items: center; gap: 14px; margin-bottom: 8px; }
        .file-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; flex-shrink: 0; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .file-meta { font-size: 0.78rem; color: #999; }
        .cat-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid #ddd; margin: 0 4px 4px 0; }
        .cat-pill.active { background: #1B3A6B; color: #fff; border-color: #1B3A6B; }
        .upload-zone { border: 2px dashed #ccc; border-radius: 12px; padding: 25px; text-align: center; background: #fafafa; cursor: pointer; transition: border-color 0.2s; }
        .upload-zone:hover, .upload-zone.dragover { border-color: #1B3A6B; background: #f0f4ff; }
        .search-bar { margin-bottom: 16px; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="vault-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 style="font-weight:800;margin:0;"><i class="fas fa-vault me-2"></i>Coffre-Fort Numerique</h2>
                <p style="margin:4px 0 0;opacity:0.7;font-size:0.9rem;">Espaces proprietaires — Documents chiffres AES-256</p>
            </div>
            <span class="badge bg-success" style="font-size:0.9em;"><i class="fas fa-lock-open"></i> Acces admin</span>
        </div>
    </div>

    <?= $feedback ?>

    <?php if ($proprioInfo): ?>
    <!-- ============================================================ -->
    <!-- VUE ESPACE PROPRIETAIRE -->
    <!-- ============================================================ -->
    <?php $nomComplet = htmlspecialchars(trim(($proprioInfo['prenom'] ?? '') . ' ' . ($proprioInfo['nom'] ?? ''))); ?>

    <div class="mb-3">
        <a href="coffre_fort.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Tous les proprietaires</a>
        <a href="proprietaire_detail.php?id=<?= $selectedProprio ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-user-tie"></i> Fiche</a>
    </div>

    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="proprio-avatar" style="width:56px;height:56px;font-size:1.4rem;"><?= strtoupper(substr($proprioInfo['prenom'] ?? 'P', 0, 1)) ?></div>
        <div>
            <h4 style="margin:0;font-weight:700;"><?= $nomComplet ?></h4>
            <span class="text-muted"><?= htmlspecialchars($proprioInfo['email'] ?? '') ?> · <?= count($fichiers) ?> document(s) · <?= CoffreFort::formatTaille(array_sum(array_column($fichiers, 'taille'))) ?></span>
        </div>
    </div>

    <!-- Categories -->
    <div class="mb-3">
        <a href="?proprio=<?= $selectedProprio ?>" class="cat-pill <?= !$filtreCat ? 'active' : '' ?>">Tous (<?= count($coffre->listerParProprietaire($selectedProprio)) ?>)</a>
        <?php foreach ($categories as $catKey => $cat):
            $catCount = count(array_filter($coffre->listerParProprietaire($selectedProprio, $catKey)));
        ?>
        <a href="?proprio=<?= $selectedProprio ?>&cat=<?= $catKey ?>" class="cat-pill <?= $filtreCat === $catKey ? 'active' : '' ?>">
            <i class="fas <?= $cat['icon'] ?>" style="color:<?= $filtreCat === $catKey ? '#fff' : $cat['color'] ?>;"></i>
            <?= $cat['label'] ?> (<?= $catCount ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Upload -->
    <div class="card mb-4" style="border-radius:12px;">
        <div class="card-body">
            <h6 style="font-weight:700;"><i class="fas fa-cloud-upload-alt text-primary"></i> Ajouter un document pour <?= $nomComplet ?></h6>
            <form method="POST" enctype="multipart/form-data">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" value="upload">
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <select name="categorie" class="form-select form-select-sm">
                            <?php foreach ($categories as $catKey => $cat): ?>
                            <option value="<?= $catKey ?>"><?= htmlspecialchars($cat['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="description" class="form-control form-control-sm" placeholder="Description (optionnel)">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="tags" class="form-control form-control-sm" placeholder="Tags (optionnel)">
                    </div>
                </div>
                <div class="upload-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#1B3A6B;"></i>
                    <p style="margin:8px 0 0;font-weight:600;">Glissez ou cliquez</p>
                    <small class="text-muted">PDF, images, Word, Excel — max 500 Mo</small>
                    <input type="file" id="fileInput" name="fichier" style="display:none;" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" required>
                    <div id="fileLabel" style="margin-top:8px;font-weight:600;color:#1B3A6B;display:none;"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fas fa-lock"></i> Chiffrer et deposer</button>
            </form>
        </div>
    </div>

    <!-- Fichiers -->
    <?php if (empty($fichiers)): ?>
    <div style="text-align:center;padding:40px;color:#999;">
        <i class="fas fa-folder-open" style="font-size:3rem;margin-bottom:12px;"></i>
        <p>Aucun document<?= $filtreCat ? ' dans cette categorie' : '' ?>.</p>
    </div>
    <?php else: ?>
    <?php foreach ($fichiers as $f):
        $cat = $categories[$f['categorie']] ?? $categories['autre'];
    ?>
    <div class="file-card">
        <div class="file-icon" style="background:<?= $cat['color'] ?>;"><i class="fas <?= $cat['icon'] ?>"></i></div>
        <div class="file-info">
            <div class="file-name" title="<?= htmlspecialchars($f['nom_original']) ?>"><?= htmlspecialchars($f['nom_original']) ?></div>
            <div class="file-meta">
                <?= htmlspecialchars($cat['label']) ?>
                <?php if (!empty($f['description'])): ?> — <?= htmlspecialchars($f['description']) ?><?php endif; ?>
                · <?= CoffreFort::formatTaille((int)$f['taille']) ?>
                · <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
            </div>
        </div>
        <?php if (str_starts_with($f['type_mime'] ?? '', 'image/') || ($f['type_mime'] ?? '') === 'application/pdf'): ?>
        <a href="coffre_fort_viewer.php?id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Consulter"><i class="fas fa-eye"></i></a>
        <?php endif; ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce document ?');">
            <?php echoCsrfField(); ?>
            <input type="hidden" name="action" value="supprimer">
            <input type="hidden" name="fichier_id" value="<?= (int)$f['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- VUE LISTE PROPRIETAIRES -->
    <!-- ============================================================ -->

    <!-- Recherche -->
    <div class="search-bar">
        <div class="input-group" style="max-width:400px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control" id="searchProprio" placeholder="Rechercher un proprietaire..." oninput="filterProprios()">
        </div>
    </div>

    <div class="row g-3" id="proprioList">
        <?php foreach ($proprios as $p):
            $pNom = htmlspecialchars(trim(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')));
            $initiale = strtoupper(substr($p['prenom'] ?? $p['nom'] ?? 'P', 0, 1));
            $nbDocs = (int)$p['nb_docs'];
        ?>
        <div class="col-md-6 col-lg-4 proprio-item" data-search="<?= strtolower($pNom . ' ' . ($p['email'] ?? '')) ?>">
            <a href="?proprio=<?= (int)$p['id'] ?>" class="text-decoration-none">
                <div class="proprio-card">
                    <div class="proprio-avatar"><?= $initiale ?></div>
                    <div>
                        <div class="proprio-name"><?= $pNom ?></div>
                        <div class="proprio-meta">
                            <?= htmlspecialchars($p['email'] ?? '') ?>
                            <?php if ($p['dernier_upload']): ?><br>Dernier upload : <?= date('d/m/Y', strtotime($p['dernier_upload'])) ?><?php endif; ?>
                        </div>
                    </div>
                    <span class="doc-count <?= $nbDocs === 0 ? 'empty' : '' ?>">
                        <?= $nbDocs ?> doc<?= $nbDocs > 1 ? 's' : '' ?>
                        <?php if ($nbDocs > 0): ?><br><small><?= CoffreFort::formatTaille((int)$p['taille_totale']) ?></small><?php endif; ?>
                    </span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>

        <?php if (empty($proprios)): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="fas fa-user-slash fa-3x mb-3"></i>
            <p>Aucun proprietaire actif.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Recherche proprietaires
function filterProprios() {
    const q = document.getElementById('searchProprio')?.value?.toLowerCase() || '';
    document.querySelectorAll('.proprio-item').forEach(el => {
        el.style.display = el.dataset.search.includes(q) ? '' : 'none';
    });
}

// Upload drag & drop
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileLabel = document.getElementById('fileLabel');
if (dropZone) {
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault(); dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showFile(); }
    });
    fileInput?.addEventListener('change', showFile);
}
function showFile() {
    if (fileInput.files.length) { fileLabel.textContent = fileInput.files[0].name; fileLabel.style.display = 'block'; }
}
</script>
</body>
</html>
