<?php
include '../config.php';
include '../pages/menu.php';

// Récupérer la DERNIÈRE session validée par logement
$sessions = $conn->query("
    SELECT l.id AS logement_id, l.nom_du_logement, MAX(s.id) AS session_id
    FROM liste_logements l
    JOIN sessions_inventaire s ON s.logement_id = l.id
    WHERE s.statut = 'terminee'
    GROUP BY l.id
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventaire – Synthèse par logement</title>
<style>
body { font-family: Arial,sans-serif; margin:0; background:#f7f7f9; font-size:17px; }
h2 { margin:24px 0 16px 0; text-align:center; color:#1976d2;}
h3 { margin:30px 0 10px 0; color:#1976d2; }
table { width:100%; background:#fff; border-collapse:collapse; margin-bottom:20px; border-radius:7px; box-shadow:0 1px 4px #e0e0e0;}
th,td { padding:10px 6px; font-size:1.07em; border-bottom:1px solid #eee;}
th { background:#e3f2fd; }
tr:last-child td { border-bottom:none; }
img { max-width:60px; height:auto; border-radius:4px;}
@media (max-width:600px) {
    body { font-size:15px; }
    th,td { padding:8px 3px; font-size:0.97em; }
    img { max-width:44px; }
}
</style>
</head>
<body>

<h2>Inventaire validé de tous les objets par logement</h2>

<?php
if (empty($sessions)) {
    echo "<p style='text-align:center;color:#888;'>Aucun inventaire validé.</p>";
} else {
    foreach ($sessions as $session) {
        // Charger tous les objets de la dernière session validée pour ce logement
        $stmt = $conn->prepare("
            SELECT * FROM inventaire_objets WHERE session_id = ? ORDER BY nom_objet ASC
        ");
        $stmt->execute([$session['session_id']]);
        $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<h3>'.htmlspecialchars($session['nom_du_logement']).'</h3>';
        if (empty($objets)) {
            echo "<p style='color:#999;'>Aucun objet inventorié dans la dernière session validée.</p>";
            continue;
        }
        echo '<table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Marque</th>
                <th>État</th>
                <th>Quantité</th>
                <th>Valeur (€)</th>
                <th>Propriétaire</th>
                <th>Photo</th>
                <th>Voir</th>
            </tr>
        </thead>
        <tbody>';
        foreach ($objets as $objet) {
            echo '<tr>
                <td>' . htmlspecialchars($objet['nom_objet']) . '</td>
                <td>' . htmlspecialchars($objet['marque']) . '</td>
                <td>' . htmlspecialchars($objet['etat']) . '</td>
                <td>' . (int)$objet['quantite'] . '</td>
                <td>' . number_format($objet['valeur'], 2, ',', ' ') . '</td>
                <td>' . htmlspecialchars($objet['proprietaire']) . '</td>
                <td>';
            if ($objet['photo_path']) {
                echo '<img src="' . htmlspecialchars($objet['photo_path']) . '" width="50">';
            }
            echo '</td>
                <td><a href="objet.php?id=' . $objet['id'] . '" target="_blank">🔍</a></td>
            </tr>';
        }
        echo '</tbody></table>';
    }
}
?>

</body>
</html>
