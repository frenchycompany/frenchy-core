<?php
require_once '../db/connection.php';
include 'inventaire_menu.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sélection du logement
$logement_id = isset($_GET['logement_id']) ? intval($_GET['logement_id']) : 0;

// Récupérer les logements ayant une session d'inventaire validée
$logements = $conn->query("
    SELECT DISTINCT l.id, l.nom_du_logement
    FROM liste_logements l
    JOIN sessions_inventaire s ON s.logement_id = l.id
    WHERE s.statut = 'terminee'
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);

$objets = [];
if ($logement_id) {
    // Charger les objets Frenchy pour ce logement sur session terminée
    $stmt = $conn->prepare("
        SELECT o.id, o.nom_objet, l.nom_du_logement
        FROM inventaire_objets o
        JOIN sessions_inventaire s ON o.session_id = s.id
        JOIN liste_logements l ON o.logement_id = l.id
        WHERE o.proprietaire = 'frenchy'
          AND o.logement_id = ?
          AND s.statut = 'terminee'
        ORDER BY o.nom_objet ASC
    ");
    $stmt->execute([$logement_id]);
    $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Impression d'étiquettes</title>
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

<h2>Impression d’étiquettes – Frenchy Conciergerie</h2>

<form method="get" action="">
    <label for="logement_id">Choisir un logement (inventaire validé) :</label>
    <select name="logement_id" id="logement_id" onchange="this.form.submit()">
        <option value="">-- Sélectionner --</option>
        <?php foreach ($logements as $l) : ?>
            <option value="<?= $l['id'] ?>" <?= ($logement_id == $l['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['nom_du_logement']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($logement_id): ?>
    <?php if (empty($objets)): ?>
        <p style="color:orange;">Aucun objet Frenchy pour ce logement.</p>
    <?php else: ?>
        <form method="post" action="generer_etiquettes.php" target="_blank">
            <p>Objets « Frenchy Conciergerie » pour ce logement :</p>
            <table border="1" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <th>✔</th>
                        <th>Objet</th>
                        <th>Logement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($objets as $objet) : ?>
                        <tr>
                            <td><input type="checkbox" name="objets[]" value="<?= $objet['id'] ?>" checked></td>
                            <td><?= htmlspecialchars($objet['nom_objet']) ?></td>
                            <td><?= htmlspecialchars($objet['nom_du_logement']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <button type="submit">🖨️ Générer les étiquettes PDF</button>
        </form>
    <?php endif; ?>
<?php elseif ($logement_id === 0): ?>
    <p>Sélectionnez un logement pour voir les objets Frenchy.</p>
<?php endif; ?>
