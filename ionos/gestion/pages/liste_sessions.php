<?php
include '../config.php';
include '../pages/menu.php';
try {
    // On récupère toutes les sessions en cours avec leur logement associé
    $stmt = $conn->prepare("
        SELECT s.id, s.date_creation, l.nom_du_logement
        FROM sessions_inventaire s
        JOIN liste_logements l ON s.logement_id = l.id
        WHERE s.statut = 'en_cours'
        ORDER BY s.date_creation DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur lors du chargement des sessions : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session d'inventaire (mobile)</title>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 10px;
    background: #f6f6f6;
    font-size: 17px;
}
form {
    margin-bottom: 25px;
    background: #fff;
    padding: 18px 14px 14px 14px;
    border-radius: 12px;
    box-shadow: 0 2px 8px #ececec;
}
input[type="text"], input[type="number"], input[type="date"], select, textarea {
    width: 100%;
    font-size: 1.12em;
    padding: 11px;
    margin: 7px 0 15px 0;
    border: 1px solid #dadada;
    border-radius: 6px;
    box-sizing: border-box;
}
input[type="file"] {
    font-size: 1.13em;
    margin: 10px 0;
}
button, input[type="submit"] {
    padding: 16px 22px;
    font-size: 1.15em;
    margin: 14px 7px 10px 0;
    border: none;
    border-radius: 8px;
    background: #1976d2;
    color: #fff;
    font-weight: bold;
    box-shadow: 0 1px 4px #ddd;
}
button:active, input[type="submit"]:active {
    background: #0d47a1;
}
h2, h3 {
    font-size: 1.25em;
}
img {
    max-width: 92px;
    height: auto;
    border-radius: 7px;
    margin-right: 5px;
}
table {
    width: 100%;
    background: #fff;
    border-collapse: collapse;
    margin-bottom: 16px;
    border-radius: 7px;
    box-shadow: 0 1px 4px #e0e0e0;
    overflow: hidden;
}
th, td {
    padding: 11px 6px;
    font-size: 1.06em;
    border-bottom: 1px solid #eee;
    text-align: left;
}
th {
    background: #e3f2fd;
}
tr:last-child td {
    border-bottom: none;
}
@media (max-width: 560px) {
    body { font-size: 16px; }
    th, td { padding: 7px 2px; font-size: 0.97em; }
    img { max-width: 62px; }
}
</style>
</head>

<body>
    <h2>Sessions d'inventaire en cours</h2>

    <?php if (empty($sessions)): ?>
        <p>Aucune session d’inventaire en cours.</p>
    <?php else: ?>
        <table class="table" border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Logement</th>
                    <th>Date de création</th>
                    <th>Lien d’inventaire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nom_du_logement']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></td>
                        <td>
                            <a href="inventaire_saisie.php?session_id=<?= urlencode($s['id']) ?>" target="_blank">
                                📋 Ouvrir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
