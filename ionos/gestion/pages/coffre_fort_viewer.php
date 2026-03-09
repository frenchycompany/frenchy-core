<?php
/**
 * coffre_fort_viewer.php — Consultation sécurisée de fichiers
 * Images en Canvas JS (pas de téléchargement), vidéo en streaming, PDF inline
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/coffre_fort_helper.php';

$auth = new Auth($conn);
$auth->requireAdmin('../login.php');

$userId = $_SESSION['user_id'];
$coffre = new CoffreFort($conn);

// Vérifier session coffre-fort
$session = $coffre->verifierSession();
if (!$session) {
    header('Location: coffre_fort.php');
    exit;
}

$fichierId = (int) ($_GET['id'] ?? 0);
$fichier = $coffre->getFichier($fichierId);
if (!$fichier) {
    http_response_code(404);
    die('Fichier introuvable.');
}

$isImage = str_starts_with($fichier['type_mime'], 'image/');
$isVideo = str_starts_with($fichier['type_mime'], 'video/');
$isPdf = $fichier['type_mime'] === 'application/pdf';

// Endpoint AJAX pour stream le contenu
if (isset($_GET['stream'])) {
    // Vérifier la session à chaque requête de stream
    $s = $coffre->verifierSession();
    if (!$s) {
        http_response_code(403);
        die('Session expirée');
    }

    if ($isImage) {
        // Retourner l'image en base64 pour le canvas
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $b64 = $coffre->streamImageBase64($fichierId, $userId);
        echo json_encode(['data' => $b64]);
        exit;
    }

    if ($isVideo) {
        $coffre->streamVideo($fichierId, $userId);
        exit;
    }

    if ($isPdf) {
        $coffre->streamDocument($fichierId, $userId);
        exit;
    }

    http_response_code(400);
    exit;
}

$coffre->prolongerSession($session['token']);
$tempsRestant = $coffre->tempsRestant();

// Watermark text
$watermark = strtoupper($_SESSION['user_nom'] ?? 'ADMIN') . ' — ' . date('d/m/Y H:i') . ' — CONFIDENTIEL';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation — <?= htmlspecialchars($fichier['nom_original']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0a0a0a;
            color: #fff;
            font-family: system-ui, -apple-system, sans-serif;
            overflow: hidden;
            height: 100vh;
            /* Désactiver sélection */
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        .viewer-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            border-bottom: 1px solid #333;
        }
        .viewer-header .file-info {
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .viewer-header .file-info .fname {
            font-weight: 700;
        }
        .viewer-header .file-info .fmeta {
            color: #888;
            font-size: 12px;
        }
        .viewer-header .controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .viewer-header .timer {
            background: #1B3A6B;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .timer.warning { background: #E53935; }
        .btn-viewer {
            background: rgba(255,255,255,0.1);
            border: 1px solid #555;
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-viewer:hover { background: rgba(255,255,255,0.2); color: #fff; }

        .viewer-container {
            position: fixed;
            top: 52px;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #imageCanvas {
            max-width: 100%;
            max-height: 100%;
            /* Anti capture d'écran basique */
            image-rendering: auto;
        }

        #videoPlayer {
            max-width: 100%;
            max-height: 100%;
        }

        #pdfFrame {
            width: 100%;
            height: 100%;
            border: none;
        }

        .watermark-overlay {
            position: fixed;
            top: 52px;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 50;
            opacity: 0.06;
            overflow: hidden;
        }
        .watermark-text {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
            transform: rotate(-30deg);
            position: absolute;
            letter-spacing: 2px;
        }

        .loading {
            text-align: center;
            color: #888;
        }
        .loading i {
            font-size: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Zoom controls */
        .zoom-controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            border-radius: 8px;
            padding: 6px 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            z-index: 60;
        }
        .zoom-controls button {
            background: none;
            border: none;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
        }
        .zoom-controls button:hover { color: #1E88E5; }
        .zoom-level { font-size: 13px; min-width: 50px; text-align: center; }
    </style>
</head>
<body oncontextmenu="return false;" ondragstart="return false;">

<!-- Header -->
<div class="viewer-header">
    <div class="file-info">
        <i class="fas <?= $isImage ? 'fa-image' : ($isVideo ? 'fa-video' : 'fa-file-pdf') ?>" style="color: #1E88E5;"></i>
        <div>
            <div class="fname"><?= htmlspecialchars($fichier['nom_original']) ?></div>
            <div class="fmeta"><?= CoffreFort::formatTaille($fichier['taille']) ?> — Chiffré AES-256</div>
        </div>
    </div>
    <div class="controls">
        <span class="timer" id="timer" data-seconds="<?= $tempsRestant ?>">
            <i class="fas fa-clock me-1"></i><span id="timerText"><?= gmdate('i:s', $tempsRestant) ?></span>
        </span>
        <a href="coffre_fort.php" class="btn-viewer"><i class="fas fa-arrow-left me-1"></i>Retour</a>
    </div>
</div>

<!-- Watermark dynamique -->
<div class="watermark-overlay" id="watermarkOverlay"></div>

<!-- Conteneur principal -->
<div class="viewer-container" id="viewerContainer">
    <div class="loading" id="loading">
        <i class="fas fa-circle-notch"></i>
        <p class="mt-3">Déchiffrement en cours...</p>
    </div>

    <?php if ($isImage): ?>
        <canvas id="imageCanvas" style="display:none;"></canvas>
    <?php elseif ($isVideo): ?>
        <video id="videoPlayer" style="display:none;" controls controlsList="nodownload noremoteplayback"
               disablePictureInPicture oncontextmenu="return false;">
        </video>
    <?php elseif ($isPdf): ?>
        <iframe id="pdfFrame" style="display:none;" sandbox="allow-same-origin"></iframe>
    <?php endif; ?>
</div>

<?php if ($isImage): ?>
<!-- Zoom pour images -->
<div class="zoom-controls" id="zoomControls" style="display:none;">
    <button onclick="zoomChange(-0.25)" title="Zoom -"><i class="fas fa-minus"></i></button>
    <span class="zoom-level" id="zoomLevel">100%</span>
    <button onclick="zoomChange(0.25)" title="Zoom +"><i class="fas fa-plus"></i></button>
    <button onclick="zoomReset()" title="Reset"><i class="fas fa-expand"></i></button>
</div>
<?php endif; ?>

<script>
// ========================================================
// ANTI-CAPTURE
// ========================================================

// Bloquer les raccourcis clavier de capture/sauvegarde
document.addEventListener('keydown', function(e) {
    // Ctrl+S, Ctrl+P, Ctrl+Shift+I, Ctrl+U, PrintScreen, F12
    if (
        (e.ctrlKey && (e.key === 's' || e.key === 'p' || e.key === 'u')) ||
        (e.ctrlKey && e.shiftKey && e.key === 'I') ||
        e.key === 'F12' ||
        e.key === 'PrintScreen'
    ) {
        e.preventDefault();
        return false;
    }
});

// Détection perte de focus (capture d'écran potentielle)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Masquer le contenu quand l'onglet perd le focus
        const canvas = document.getElementById('imageCanvas');
        const video = document.getElementById('videoPlayer');
        if (canvas) canvas.style.filter = 'blur(20px)';
        if (video) video.pause();
    } else {
        const canvas = document.getElementById('imageCanvas');
        if (canvas) canvas.style.filter = 'none';
    }
});

// ========================================================
// WATERMARK DYNAMIQUE
// ========================================================

(function generateWatermark() {
    const overlay = document.getElementById('watermarkOverlay');
    const text = <?= json_encode($watermark) ?>;
    let html = '';
    for (let y = -100; y < window.innerHeight + 200; y += 120) {
        for (let x = -200; x < window.innerWidth + 400; x += 400) {
            html += '<span class="watermark-text" style="left:' + x + 'px;top:' + y + 'px;">' + text + '</span>';
        }
    }
    overlay.innerHTML = html;
})();

// ========================================================
// TIMER
// ========================================================

const timerEl = document.getElementById('timer');
const timerText = document.getElementById('timerText');
let seconds = parseInt(timerEl.dataset.seconds);

setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        window.location.href = 'coffre_fort.php';
        return;
    }
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    timerText.textContent = m + ':' + s;
    if (seconds < 120) timerEl.classList.add('warning');
}, 1000);

// ========================================================
// CHARGEMENT DU CONTENU
// ========================================================

const loading = document.getElementById('loading');
const streamUrl = 'coffre_fort_viewer.php?id=<?= $fichierId ?>&stream=1';

<?php if ($isImage): ?>
// Image → Canvas (pas d'élément <img> = pas de clic-droit "enregistrer sous")
fetch(streamUrl)
    .then(r => r.json())
    .then(data => {
        const img = new Image();
        img.onload = function() {
            const canvas = document.getElementById('imageCanvas');
            const container = document.getElementById('viewerContainer');
            const zoomControls = document.getElementById('zoomControls');

            // Calculer la taille optimale
            const maxW = container.clientWidth - 40;
            const maxH = container.clientHeight - 40;
            const ratio = Math.min(maxW / img.width, maxH / img.height, 1);

            canvas.width = img.width;
            canvas.height = img.height;
            canvas.style.width = (img.width * ratio) + 'px';
            canvas.style.height = (img.height * ratio) + 'px';

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);

            // Watermark sur le canvas aussi
            ctx.save();
            ctx.globalAlpha = 0.04;
            ctx.fillStyle = '#ffffff';
            ctx.font = '24px system-ui';
            ctx.rotate(-0.5);
            const wText = <?= json_encode($watermark) ?>;
            for (let y = 0; y < img.height + 200; y += 100) {
                for (let x = -200; x < img.width + 200; x += 500) {
                    ctx.fillText(wText, x, y);
                }
            }
            ctx.restore();

            loading.style.display = 'none';
            canvas.style.display = 'block';
            zoomControls.style.display = 'flex';

            // Variables zoom
            window._zoom = ratio;
            window._baseRatio = ratio;
            window._imgW = img.width;
            window._imgH = img.height;
        };
        img.src = data.data;
    })
    .catch(() => {
        loading.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#E53935;animation:none;"></i><p class="mt-3">Erreur de déchiffrement.</p>';
    });

function zoomChange(delta) {
    window._zoom = Math.max(0.1, Math.min(5, window._zoom + delta));
    applyZoom();
}
function zoomReset() {
    window._zoom = window._baseRatio;
    applyZoom();
}
function applyZoom() {
    const canvas = document.getElementById('imageCanvas');
    canvas.style.width = (window._imgW * window._zoom) + 'px';
    canvas.style.height = (window._imgH * window._zoom) + 'px';
    document.getElementById('zoomLevel').textContent = Math.round(window._zoom / window._baseRatio * 100) + '%';
}

<?php elseif ($isVideo): ?>
// Vidéo → streaming avec contrôles limités
const video = document.getElementById('videoPlayer');
video.src = streamUrl;
video.addEventListener('loadeddata', () => {
    loading.style.display = 'none';
    video.style.display = 'block';
});
video.addEventListener('error', () => {
    loading.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#E53935;animation:none;"></i><p class="mt-3">Erreur de lecture.</p>';
});

// Empêcher PiP et certaines actions
video.addEventListener('enterpictureinpicture', (e) => {
    e.preventDefault();
    document.exitPictureInPicture();
});

<?php elseif ($isPdf): ?>
// PDF → iframe inline
const frame = document.getElementById('pdfFrame');
frame.src = streamUrl;
frame.addEventListener('load', () => {
    loading.style.display = 'none';
    frame.style.display = 'block';
});

<?php endif; ?>
</script>

</body>
</html>
