<?php
require_once '../db/connection.php';
include 'inventaire_menu.php';
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lancer un inventaire (mobile)</title>
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

<h2>Lancer un inventaire</h2>

<form action="inventaire_creer_session.php" method="POST">
    <label for="logement_id">Choisissez un logement :</label>
    <select name="logement_id" id="logement_id" required>
        <?php
        $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logements as $logement) {
            echo "<option value=\"{$logement['id']}\">{$logement['nom_du_logement']}</option>";
        }
        ?>
    </select>
    <br><br>
    <button type="submit">Créer une session d'inventaire</button>
</form>
