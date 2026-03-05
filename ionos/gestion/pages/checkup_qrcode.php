<?php
/**
 * QR Code Checkup — Genere un QR code par logement
 * Permet de scanner pour lancer directement un checkup
 */
include '../config.php';
include '../pages/menu.php';

// Chercher la librairie phpqrcode
$qrLibPaths = [
    __DIR__ . '/../lib/phpqrcode/qrlib.php',
    __DIR__ . '/../../OK V2/lib/phpqrcode/qrlib.php',
];
$qrLibFound = false;
foreach ($qrLibPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $qrLibFound = true;
        break;
    }
}

$logement_id = isset($_GET['logement_id']) ? (int)$_GET['logement_id'] : null;

// Generer le QR code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate']) && $qrLibFound) {
    $lid = (int)$_POST['logement_id'];
    $baseUrl = 'https://gestion.frenchyconciergerie.fr/pages/checkup_logement.php';
    $qrUrl = $baseUrl . '?auto_logement=' . $lid;

    $qrDir = __DIR__ . '/../uploads/qrcodes/';
    if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

    $qrFile = 'checkup_qr_' . $lid . '.png';
    QRcode::png($qrUrl, $qrDir . $qrFile, QR_ECLEVEL_M, 8, 2);
}

// Charger les logements
$logements = $conn->query("
    SELECT l.id, l.nom_du_logement
    FROM liste_logements l
    WHERE l.actif = 1
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);

// Charger les QR existants
$qrDir = __DIR__ . '/../uploads/qrcodes/';
$existingQRs = [];
foreach ($logements as $l) {
    $file = 'checkup_qr_' . $l['id'] . '.png';
    if (file_exists($qrDir . $file)) {
        $existingQRs[$l['id']] = 'uploads/qrcodes/' . $file;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes Checkup</title>
    <style>
        .qr-container { max-width: 800px; margin: 0 auto; padding: 0 12px 40px; }
        .qr-header {
            background: linear-gradient(135deg, #424242, #333);
            color: #fff; text-align: center; padding: 25px 15px;
            border-radius: 15px; margin: 15px 0 20px;
        }
        .qr-header h2 { margin: 0; font-size: 1.3em; }
        .qr-header p { margin: 8px 0 0; opacity: 0.85; font-size: 0.9em; }
        .qr-form {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.07); margin-bottom: 20px;
        }
        .qr-form select {
            width: 100%; padding: 12px; font-size: 1em;
            border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 12px;
        }
        .btn-gen {
            width: 100%; padding: 14px; font-size: 1.05em; font-weight: 700;
            border: none; border-radius: 10px;
            background: #424242; color: #fff; cursor: pointer;
        }
        .qr-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .qr-card {
            background: #fff; border-radius: 12px; padding: 15px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06); text-align: center;
        }
        .qr-card img {
            width: 160px; height: 160px; margin-bottom: 8px;
            border-radius: 8px;
        }
        .qr-card h4 { margin: 0 0 8px; font-size: 0.95em; color: #333; }
        .qr-card .qr-actions { display: flex; gap: 6px; justify-content: center; }
        .qr-card .qr-actions a, .qr-card .qr-actions button {
            padding: 6px 12px; border-radius: 6px; font-size: 0.82em;
            font-weight: 600; border: none; cursor: pointer; text-decoration: none;
        }
        .btn-dl { background: #e3f2fd; color: #1565c0; }
        .btn-print { background: #e8f5e9; color: #2e7d32; }
        .no-qr-lib {
            background: #fff3e0; border-radius: 10px; padding: 15px;
            color: #e65100; margin-bottom: 15px; text-align: center;
        }
        @media (max-width: 600px) {
            .qr-grid { grid-template-columns: 1fr 1fr; }
            .qr-card img { width: 120px; height: 120px; }
        }
        @media print {
            .qr-header, .qr-form, .qr-card .qr-actions, nav { display: none !important; }
            .qr-card { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="qr-container">
    <div class="qr-header">
        <h2><i class="fas fa-qrcode"></i> QR Codes Checkup</h2>
        <p>Scannez pour lancer un checkup directement</p>
    </div>

    <?php if (!$qrLibFound): ?>
    <div class="no-qr-lib">
        <i class="fas fa-exclamation-triangle"></i>
        La librairie phpqrcode n'est pas installee. Copiez-la dans <code>lib/phpqrcode/</code>.
        Les QR codes deja generes restent utilisables ci-dessous.
    </div>
    <?php endif; ?>

    <?php if ($qrLibFound): ?>
    <div class="qr-form">
        <form method="POST">
            <select name="logement_id">
                <option value="">-- Generer un QR pour un logement --</option>
                <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?>
                    <?= isset($existingQRs[$l['id']]) ? ' (deja genere)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="generate" value="1" class="btn-gen">
                <i class="fas fa-qrcode"></i> Generer le QR Code
            </button>
        </form>
    </div>

    <div style="text-align:center;margin-bottom:15px;">
        <form method="POST">
            <?php foreach ($logements as $l): ?>
            <input type="hidden" name="logements[]" value="<?= $l['id'] ?>">
            <?php endforeach; ?>
            <button type="submit" name="generate_all" value="1" class="btn-gen" style="width:auto;padding:10px 20px;font-size:0.9em;background:#1976d2;">
                <i class="fas fa-layer-group"></i> Generer tous les QR codes
            </button>
        </form>
        <?php
        // Generer tous
        if (isset($_POST['generate_all'])) {
            foreach ($logements as $l) {
                $qrUrl = 'https://gestion.frenchyconciergerie.fr/pages/checkup_logement.php?auto_logement=' . $l['id'];
                $qrFile = 'checkup_qr_' . $l['id'] . '.png';
                if (!file_exists($qrDir . $qrFile)) {
                    QRcode::png($qrUrl, $qrDir . $qrFile, QR_ECLEVEL_M, 8, 2);
                    $existingQRs[$l['id']] = 'uploads/qrcodes/' . $qrFile;
                }
            }
            echo '<div style="color:#2e7d32;margin-top:10px;"><i class="fas fa-check"></i> Tous les QR codes generes.</div>';
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- QR codes existants -->
    <div class="qr-grid">
        <?php foreach ($logements as $l): ?>
            <?php if (isset($existingQRs[$l['id']])): ?>
            <div class="qr-card">
                <h4><?= htmlspecialchars($l['nom_du_logement']) ?></h4>
                <img src="../<?= htmlspecialchars($existingQRs[$l['id']]) ?>" alt="QR <?= htmlspecialchars($l['nom_du_logement']) ?>">
                <div class="qr-actions">
                    <a href="../<?= htmlspecialchars($existingQRs[$l['id']]) ?>" download class="btn-dl">
                        <i class="fas fa-download"></i> Telecharger
                    </a>
                    <button class="btn-print" onclick="printQR('<?= htmlspecialchars($l['nom_du_logement']) ?>', '../<?= htmlspecialchars($existingQRs[$l['id']]) ?>')">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
function printQR(name, imgSrc) {
    var win = window.open('', '_blank');
    win.document.write(
        '<html><head><title>QR ' + name + '</title>' +
        '<style>body{text-align:center;font-family:Arial;padding:40px;}img{width:300px;height:300px;}h2{margin-bottom:20px;}</style>' +
        '</head><body>' +
        '<h2>' + name + '</h2>' +
        '<img src="' + imgSrc + '"><br>' +
        '<p style="margin-top:15px;color:#888;font-size:14px;">Scannez pour lancer un checkup</p>' +
        '<script>setTimeout(function(){window.print();},500);<\/script>' +
        '</body></html>'
    );
    win.document.close();
}
</script>
</body>
</html>
