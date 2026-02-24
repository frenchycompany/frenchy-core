<?php
require_once '../db/connection.php';

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

$stmt = $conn->prepare("SELECT s.*, l.nom_du_logement FROM sessions_inventaire s JOIN liste_logements l ON s.logement_id = l.id WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) die("Session introuvable");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['proprietaire'] as $objet_id => $valeur) {
        $stmt = $conn->prepare("UPDATE objet_inventaire SET proprietaire = ? WHERE id = ?");
        $stmt->execute([$valeur, $objet_id]);
    }

    // Terminer la session ?
    if (isset($_POST['terminer'])) {
        $stmt = $conn->prepare("UPDATE sessions_inventaire SET statut = 'terminee' WHERE id = ?");
        $stmt->execute([$session_id]);
        echo "<div class='alert alert-success'>Session terminée.</div>";
    } else {
        echo "<div class='alert alert-info'>Mise à jour enregistrée.</div>";
    }
}

// Récupération des objets
$stmt = $conn->prepare("SELECT * FROM objet_inventaire WHERE session_id = ?");
$stmt->execute([$session_id]);
$objets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Validation Inventaire (mobile)</title>
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

<h2>Validation de l’inventaire – <?= htmlspecialchars($session['nom_du_logement']) ?></h2>

<form method="post">
    <table border="1" cellpadding="8">
        <tr>
            <th>Photo</th>
            <th>Objet</th>
            <th>Quantité</th>
            <th>Marque</th>
            <th>État</th>
            <th>Propriétaire</th>
        </tr>
        <?php foreach ($objets as $obj): ?>
            <tr>
                <td>
                    <?php if ($obj['photo']) echo "<img src='../" . htmlspecialchars($obj['photo']) . "' width='60'>"; ?>
                </td>
                <td><?= htmlspecialchars($obj['nom_objet']) ?></td>
                <td><?= intval($obj['quantite']) ?></td>
                <td><?= htmlspecialchars($obj['marque']) ?></td>
                <td><?= htmlspecialchars($obj['etat']) ?></td>
                <td>
                    <select name="proprietaire[<?= $obj['id'] ?>]">
                        <option value="proprietaire" <?= $obj['proprietaire'] === 'proprietaire' ? 'selected' : '' ?>>Propriétaire</option>
                        <option value="frenchy" <?= $obj['proprietaire'] === 'frenchy' ? 'selected' : '' ?>>Frenchy Conciergerie</option>
                        <option value="autre" <?= $obj['proprietaire'] === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button type="submit">Enregistrer</button>
    <button type="submit" name="terminer" value="1">Terminer l’inventaire</button>
</form>
