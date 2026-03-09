<?php
/**
 * coffre_fort.php — Coffre-fort numérique Frenchy Conciergerie
 * Page principale : 2FA, listing, upload
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/coffre_fort_helper.php';

$auth = new Auth($conn);
$auth->requireAdmin('../login.php');

$csrf_token = $auth->csrfToken();
$userId = $_SESSION['user_id'];
$userNom = $_SESSION['user_nom'] ?? 'Admin';
$coffre = new CoffreFort($conn);

// Récupérer le numéro de téléphone de l'utilisateur
$stmtUser = $conn->prepare("SELECT telephone FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userPhone = $stmtUser->fetchColumn();

$message = '';
$messageType = '';

// ========================================================
// ACTIONS POST
// ========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf()) {
    $action = $_POST['action'] ?? '';

    // Envoi du code 2FA
    if ($action === 'envoyer_2fa') {
        $phone = $userPhone;
        if (!$phone) {
            $message = 'Aucun numéro de téléphone configuré pour votre compte.';
            $messageType = 'error';
        } else {
            $result = $coffre->envoyer2FA($userId, $phone);
            if ($result['success']) {
                $message = 'Code envoyé par SMS. Valable 5 minutes.';
                $messageType = 'success';
                $_SESSION['coffre_2fa_pending'] = true;
            } else {
                $message = $result['error'] ?? 'Erreur lors de l\'envoi du SMS.';
                $messageType = 'error';
            }
        }
    }

    // Vérification du code 2FA
    if ($action === 'verifier_2fa') {
        $code = trim($_POST['code'] ?? '');
        $result = $coffre->verifier2FA($userId, $code);
        if ($result['success']) {
            unset($_SESSION['coffre_2fa_pending']);
            $message = 'Accès autorisé. Session active pour 15 minutes.';
            $messageType = 'success';
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    }

    // Upload de fichier
    if ($action === 'upload') {
        $session = $coffre->verifierSession();
        if (!$session) {
            $message = 'Session expirée. Veuillez vous ré-authentifier.';
            $messageType = 'error';
        } elseif (isset($_FILES['fichier']) && $_FILES['fichier']['error'] !== UPLOAD_ERR_NO_FILE) {
            $categorie = $_POST['categorie'] ?? 'autre';
            $description = trim($_POST['description'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $result = $coffre->upload($_FILES['fichier'], $categorie, $userId, $description, $tags);
            if ($result['success']) {
                $message = 'Fichier chiffré et ajouté au coffre-fort.';
                $messageType = 'success';
            } else {
                $message = $result['error'];
                $messageType = 'error';
            }
        }
    }

    // Suppression
    if ($action === 'supprimer') {
        $session = $coffre->verifierSession();
        if ($session) {
            $fichierId = (int) ($_POST['fichier_id'] ?? 0);
            $coffre->supprimer($fichierId, $userId);
            $message = 'Fichier supprimé du coffre-fort.';
            $messageType = 'success';
        }
    }

    // Déconnexion coffre
    if ($action === 'deconnexion') {
        $token = $_SESSION['coffre_fort_token'] ?? '';
        if ($token) {
            $coffre->invaliderSession($token);
        }
        $message = 'Session coffre-fort terminée.';
        $messageType = 'success';
    }
}

// ========================================================
// ÉTAT
// ========================================================

$sessionCoffre = $coffre->verifierSession();
$isUnlocked = $sessionCoffre !== null;
$tempsRestant = $coffre->tempsRestant();
$pendingCode = isset($_SESSION['coffre_2fa_pending']);

$filtreCategorie = $_GET['categorie'] ?? '';
$filtreRecherche = $_GET['q'] ?? '';

$fichiers = $isUnlocked ? $coffre->lister($filtreCategorie, $filtreRecherche) : [];
$stats = $isUnlocked ? $coffre->getStats() : [];
$logs = ($isUnlocked && isset($_GET['logs'])) ? $coffre->getLogs(30) : [];

$categories = [
    'photo' => ['label' => 'Photos', 'icon' => 'fa-image', 'color' => '#43A047'],
    'video' => ['label' => 'Vidéos', 'icon' => 'fa-video', 'color' => '#1E88E5'],
    'document' => ['label' => 'Documents', 'icon' => 'fa-file-pdf', 'color' => '#FB8C00'],
    'contrat' => ['label' => 'Contrats', 'icon' => 'fa-file-contract', 'color' => '#8E24AA'],
    'identite' => ['label' => 'Identité', 'icon' => 'fa-id-card', 'color' => '#E53935'],
    'autre' => ['label' => 'Autres', 'icon' => 'fa-folder', 'color' => '#757575'],
];

include __DIR__ . '/menu.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffre-Fort Numérique — Frenchy Conciergerie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #1B3A6B;
            --red: #E53935;
            --bg: #f5f7fa;
        }
        body { background: var(--bg); }

        .vault-header {
            background: linear-gradient(135deg, var(--navy) 0%, #0d2240 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .vault-header h1 { font-size: 24px; font-weight: 800; }
        .vault-header .subtitle { opacity: 0.7; font-size: 14px; }

        .lock-screen {
            max-width: 500px;
            margin: 60px auto;
            text-align: center;
        }
        .lock-icon {
            font-size: 80px;
            color: var(--navy);
            margin-bottom: 20px;
        }
        .lock-screen h2 { color: var(--navy); font-weight: 800; }

        .code-input {
            font-size: 32px;
            letter-spacing: 12px;
            text-align: center;
            font-weight: 800;
            max-width: 250px;
            margin: 0 auto;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
        }
        .code-input:focus {
            border-color: var(--navy);
            outline: none;
            box-shadow: 0 0 0 3px rgba(27,58,107,0.15);
        }

        .btn-navy {
            background: var(--navy);
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
        }
        .btn-navy:hover { background: #142d54; color: #fff; }

        .btn-red {
            background: var(--red);
            color: #fff;
            border: none;
        }
        .btn-red:hover { background: #C62828; color: #fff; }

        .session-bar {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .session-timer {
            font-weight: 700;
            font-size: 18px;
            color: var(--navy);
        }
        .session-timer.warning { color: var(--red); }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border-top: 3px solid var(--navy);
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--navy);
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
        }

        .file-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid #e8e8e8;
            transition: box-shadow 0.2s;
            position: relative;
        }
        .file-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .file-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
        }
        .file-name {
            font-weight: 700;
            color: #333;
            font-size: 14px;
            word-break: break-all;
        }
        .file-meta { font-size: 12px; color: #999; }
        .file-badge {
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .upload-zone {
            background: #fff;
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--navy);
            background: #f0f4ff;
        }
        .upload-zone i { font-size: 40px; color: var(--navy); }

        .filter-pills .btn {
            border-radius: 20px;
            font-size: 12px;
            padding: 6px 16px;
            margin-right: 6px;
            margin-bottom: 6px;
        }
        .filter-pills .btn.active {
            background: var(--navy);
            color: #fff;
            border-color: var(--navy);
        }

        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .log-entry:last-child { border: none; }
        .log-action {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4" style="max-width: 1200px;">

    <!-- Header -->
    <div class="vault-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-vault me-2"></i>Coffre-Fort Numérique</h1>
                <div class="subtitle">Stockage chiffré AES-256 — Accès sécurisé 2FA</div>
            </div>
            <?php if ($isUnlocked): ?>
                <div class="text-end">
                    <span class="badge bg-success fs-6"><i class="fas fa-lock-open me-1"></i>Déverrouillé</span>
                </div>
            <?php else: ?>
                <div class="text-end">
                    <span class="badge bg-danger fs-6"><i class="fas fa-lock me-1"></i>Verrouillé</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$isUnlocked): ?>
    <!-- ===== ÉCRAN DE VERROUILLAGE ===== -->
    <div class="lock-screen">
        <div class="lock-icon"><i class="fas fa-shield-halved"></i></div>
        <h2>Accès Sécurisé</h2>
        <p class="text-muted mb-4">
            Une vérification par SMS est requise pour accéder au coffre-fort.
            <?php if ($userPhone): ?>
                <br>Le code sera envoyé au <strong><?= substr(htmlspecialchars($userPhone), 0, -4) ?>****</strong>
            <?php endif; ?>
        </p>

        <?php if (!$pendingCode): ?>
            <!-- Étape 1 : Demander le code -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="envoyer_2fa">
                <button type="submit" class="btn btn-navy btn-lg">
                    <i class="fas fa-sms me-2"></i>Recevoir le code SMS
                </button>
            </form>
        <?php else: ?>
            <!-- Étape 2 : Saisir le code -->
            <form method="POST" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="verifier_2fa">
                <div class="mb-3">
                    <input type="text" name="code" class="form-control code-input" maxlength="6"
                           placeholder="000000" autofocus autocomplete="off" inputmode="numeric" pattern="[0-9]{6}">
                </div>
                <button type="submit" class="btn btn-navy btn-lg">
                    <i class="fas fa-key me-2"></i>Vérifier
                </button>
                <div class="mt-3">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="action" value="envoyer_2fa">
                        <button type="submit" class="btn btn-link text-muted btn-sm">Renvoyer le code</button>
                    </form>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ===== COFFRE DÉVERROUILLÉ ===== -->

    <!-- Barre session -->
    <div class="session-bar">
        <div>
            <i class="fas fa-clock me-2 text-muted"></i>
            Session active — Expire dans
            <span class="session-timer" id="timer" data-seconds="<?= $tempsRestant ?>"><?= gmdate('i:s', $tempsRestant) ?></span>
        </div>
        <div>
            <a href="?logs=1" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-history me-1"></i>Logs</a>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="deconnexion">
                <button type="submit" class="btn btn-sm btn-red"><i class="fas fa-lock me-1"></i>Verrouiller</button>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_fichiers'] ?? 0 ?></div>
                <div class="stat-label">Fichiers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= CoffreFort::formatTaille($stats['taille_totale'] ?? 0) ?></div>
                <div class="stat-label">Espace utilisé</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['par_categorie']['photo'] ?? 0 ?></div>
                <div class="stat-label">Photos</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['par_categorie']['video'] ?? 0 ?></div>
                <div class="stat-label">Vidéos</div>
            </div>
        </div>
    </div>

    <!-- Upload -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="fas fa-upload me-2 text-primary"></i>Ajouter un fichier</h5>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="upload">
                <div class="upload-zone mb-3" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-arrow-up d-block mb-2"></i>
                    <span class="text-muted">Glissez un fichier ou cliquez pour sélectionner</span>
                    <div class="mt-1 text-muted" style="font-size: 12px;">Images, vidéos, PDF, documents — Max 200 Mo</div>
                    <input type="file" name="fichier" id="fileInput" class="d-none"
                           accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx">
                    <div id="fileName" class="mt-2 fw-bold text-primary" style="display:none;"></div>
                </div>
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="categorie" class="form-select">
                            <?php foreach ($categories as $key => $cat): ?>
                                <option value="<?= $key ?>"><?= $cat['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="description" class="form-control" placeholder="Description (optionnel)">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="tags" class="form-control" placeholder="Tags (ex: logement, paris)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-navy w-100" id="uploadBtn" disabled>
                            <i class="fas fa-lock me-1"></i>Chiffrer & Stocker
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="filter-pills">
            <a href="coffre_fort.php" class="btn btn-outline-secondary <?= !$filtreCategorie ? 'active' : '' ?>">Tout</a>
            <?php foreach ($categories as $key => $cat): ?>
                <a href="?categorie=<?= $key ?>" class="btn btn-outline-secondary <?= $filtreCategorie === $key ? 'active' : '' ?>">
                    <i class="fas <?= $cat['icon'] ?> me-1"></i><?= $cat['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
        <form class="d-flex gap-2" method="GET">
            <?php if ($filtreCategorie): ?><input type="hidden" name="categorie" value="<?= htmlspecialchars($filtreCategorie) ?>"><?php endif; ?>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Rechercher..."
                   value="<?= htmlspecialchars($filtreRecherche) ?>" style="width: 200px;">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <!-- Liste des fichiers -->
    <?php if (empty($fichiers)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-vault fa-3x mb-3 d-block"></i>
            <p>Aucun fichier dans le coffre-fort<?= $filtreCategorie ? ' pour cette catégorie' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($fichiers as $f):
                $cat = $categories[$f['categorie']] ?? $categories['autre'];
                $isImage = str_starts_with($f['type_mime'], 'image/');
                $isVideo = str_starts_with($f['type_mime'], 'video/');
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="file-card">
                        <div class="d-flex align-items-start gap-3">
                            <div class="file-icon" style="background: <?= $cat['color'] ?>;">
                                <i class="fas <?= $cat['icon'] ?>"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="file-name"><?= htmlspecialchars($f['nom_original']) ?></div>
                                <div class="file-meta">
                                    <?= CoffreFort::formatTaille($f['taille']) ?> —
                                    <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
                                </div>
                                <?php if ($f['description']): ?>
                                    <div class="file-meta mt-1"><?= htmlspecialchars($f['description']) ?></div>
                                <?php endif; ?>
                                <?php if ($f['tags']): ?>
                                    <div class="mt-1">
                                        <?php foreach (explode(',', $f['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark" style="font-size:10px;"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <span class="file-badge" style="background: <?= $cat['color'] ?>22; color: <?= $cat['color'] ?>;">
                                <?= $cat['label'] ?>
                            </span>
                            <div>
                                <?php if ($isImage || $isVideo || $f['type_mime'] === 'application/pdf'): ?>
                                    <a href="coffre_fort_viewer.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Consulter">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce fichier ?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="fichier_id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Logs -->
    <?php if (isset($_GET['logs']) && $logs): ?>
        <div class="card mt-4">
            <div class="card-header"><i class="fas fa-history me-2"></i>Journal d'accès (30 derniers)</div>
            <div class="card-body">
                <?php foreach ($logs as $log):
                    $actionColors = [
                        'login_2fa' => '#1E88E5',
                        'verification_ok' => '#43A047',
                        'verification_fail' => '#E53935',
                        'consultation' => '#FB8C00',
                        'upload' => '#8E24AA',
                        'suppression' => '#E53935',
                        'session_expire' => '#757575',
                    ];
                    $color = $actionColors[$log['action']] ?? '#757575';
                ?>
                    <div class="log-entry">
                        <span class="log-action" style="background: <?= $color ?>22; color: <?= $color ?>;">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                        <strong><?= htmlspecialchars($log['user_nom'] ?? 'Inconnu') ?></strong>
                        <?php if ($log['fichier_nom']): ?>
                            — <?= htmlspecialchars($log['fichier_nom']) ?>
                        <?php endif; ?>
                        <?php if ($log['details']): ?>
                            <span class="text-muted">— <?= htmlspecialchars($log['details']) ?></span>
                        <?php endif; ?>
                        <span class="text-muted float-end"><?= date('d/m H:i:s', strtotime($log['created_at'])) ?> — <?= htmlspecialchars($log['ip_address'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; /* fin isUnlocked */ ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Timer de session
const timerEl = document.getElementById('timer');
if (timerEl) {
    let seconds = parseInt(timerEl.dataset.seconds);
    const interval = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(interval);
            location.reload();
            return;
        }
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        timerEl.textContent = m + ':' + s;
        if (seconds < 120) timerEl.classList.add('warning');
    }, 1000);
}

// Upload zone
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileNameEl = document.getElementById('fileName');
const uploadBtn = document.getElementById('uploadBtn');

if (dropZone) {
    ['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, ev => {
        ev.preventDefault();
        dropZone.classList.add('dragover');
    }));
    ['dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, ev => {
        ev.preventDefault();
        dropZone.classList.remove('dragover');
    }));
    dropZone.addEventListener('drop', ev => {
        fileInput.files = ev.dataTransfer.files;
        showFile();
    });
    fileInput.addEventListener('change', showFile);
}

function showFile() {
    if (fileInput.files.length > 0) {
        fileNameEl.textContent = fileInput.files[0].name;
        fileNameEl.style.display = 'block';
        uploadBtn.disabled = false;
    }
}
</script>

</body>
</html>
