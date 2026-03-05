<?php
include '../config.php';
include '../pages/menu.php';
require_once '../lib/phpqrcode/qrlib.php';

// Vérifie que la session est bien spécifiée
if (!isset($_GET['session_id'])) {
    die("Session d'inventaire non spécifiée.");
}
$session_id = $_GET['session_id'];
$message = "";

// Traitement du formulaire de validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['proprietaire'] as $objet_id => $proprio) {
        // Met à jour le propriétaire
        $stmt = $conn->prepare("UPDATE inventaire_objets SET proprietaire = ? WHERE id = ? AND session_id = ?");
        $stmt->execute([$proprio, $objet_id, $session_id]);

        // Si le proprio est "frenchy" ET pas de QR code, alors on le génère
        if ($proprio === "frenchy") {
            $stmt2 = $conn->prepare("SELECT qr_code_path FROM inventaire_objets WHERE id = ? AND session_id = ?");
            $stmt2->execute([$objet_id, $session_id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);

            if (empty($row['qr_code_path']) || !file_exists($row['qr_code_path'])) {
                $qr_dir = __DIR__ . '/../uploads/qrcodes/';
                if (!is_dir($qr_dir)) mkdir($qr_dir, 0775, true);
                if (!is_writable($qr_dir)) @chmod($qr_dir, 0775);

                // Génère un QR code pointant sur la fiche OBJET (inventaire_objets)
                $qr_url = "https://gestion.frenchyconciergerie.fr/pages/objet.php?id=" . $objet_id;
                $qr_file_name = "qr_frenchy_" . $objet_id . ".png";
                $qr_file = $qr_dir . $qr_file_name;
                QRcode::png($qr_url, $qr_file);

                // Stocke le chemin relatif pour affichage web
                $qr_file_rel = '../uploads/qrcodes/' . $qr_file_name;
                $stmt3 = $conn->prepare("UPDATE inventaire_objets SET qr_code_path = ? WHERE id = ? AND session_id = ?");
                $stmt3->execute([$qr_file_rel, $objet_id, $session_id]);
            }
        }
    }

    // Clôture de la session
    $conn->prepare("UPDATE sessions_inventaire SET statut = 'terminee' WHERE id = ?")->execute([$session_id]);
    $message = "<div style='color:green;padding:10px;'>Inventaire validé avec succès (et QR codes générés pour les objets Frenchy).</div>";
}

// Récupère les objets de la session
$stmt = $conn->prepare("SELECT * FROM inventaire_objets WHERE session_id = ?");
$stmt->execute([$session_id]);
$objets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation Inventaire</title>
    <style>
    body { font-family: Arial,sans-serif; margin:0; background:#f8fafd; font-size:17px; }
    h2 { margin: 30px 0 20px 0; color:#1976d2; text-align:center;}
    table { width:100%; background:#fff; border-collapse:collapse; margin-bottom:20px; border-radius:7px; box-shadow:0 1px 4px #e0e0e0;}
    th,td { padding:10px 6px; font-size:1.07em; border-bottom:1px solid #eee;}
    th { background:#e3f2fd; }
    tr:last-child td { border-bottom:none; }
    img { max-width:60px; height:auto; border-radius:4px;}
    select { font-size:1.05em; padding:5px 10px; border-radius:5px; }
    @media (max-width:600px) {
        body { font-size:15px; }
        th,td { padding:8px 3px; font-size:0.97em; }
        img { max-width:44px; }
    }
    </style>
</head>
<body>

<h2>Validation de l'inventaire – Session <?= htmlspecialchars($session_id) ?></h2>
<?= $message ?>

<?php if (count($objets) === 0): ?>
    <p style="color:red;text-align:center;">Aucun objet trouvé pour cette session.</p>
<?php else: ?>
    <form method="POST">
        <table>
            <tr>
                <th>Photo</th>
                <th>Nom</th>
                <th>Quantité</th>
                <th>Propriétaire</th>
                <th>QR Code</th>
            </tr>
            <?php foreach ($objets as $obj): ?>
                <tr>
                    <td>
                        <?php if ($obj['photo_path'] && file_exists($obj['photo_path'])): ?>
                            <img src="<?= htmlspecialchars($obj['photo_path']) ?>" width="60">
                        <?php else: ?>
                            (pas de photo)
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($obj['nom_objet']) ?></td>
                    <td><?= (int)$obj['quantite'] ?></td>
                    <td>
                        <select name="proprietaire[<?= $obj['id'] ?>]" required>
                            <option value="">-- Choisir --</option>
                            <option value="frenchy" <?= $obj['proprietaire'] === 'frenchy' ? 'selected' : '' ?>>Frenchy Conciergerie</option>
                            <option value="proprietaire" <?= $obj['proprietaire'] === 'proprietaire' ? 'selected' : '' ?>>Propriétaire</option>
                            <option value="autre" <?= $obj['proprietaire'] === 'autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </td>
                    <td>
                        <?php if (!empty($obj['qr_code_path']) && file_exists(__DIR__ . '/' . $obj['qr_code_path'])): ?>
                            <img src="<?= htmlspecialchars($obj['qr_code_path']) ?>" width="55">
                        <?php elseif ($obj['proprietaire'] === 'frenchy'): ?>
                            <span style="color:#aaa;">À générer lors de la validation</span>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <br>
        <button type="submit">✅ Valider l'inventaire</button>
    </form>
<?php endif; ?>

</body>
</html>
