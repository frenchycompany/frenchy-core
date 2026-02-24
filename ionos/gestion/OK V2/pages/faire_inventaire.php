<?php
require_once '../db/connection.php';

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Vérifier que la session existe et est en cours
$stmt = $conn->prepare("SELECT s.*, l.nom_du_logement FROM sessions_inventaire s JOIN liste_logements l ON s.logement_id = l.id WHERE s.id = ? AND s.statut = 'en_cours'");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die("Session d'inventaire introuvable ou terminée.");
}

// Enregistrement objet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_objet = $_POST['nom_objet'] ?? '';
    $quantite = $_POST['quantite'] ?? 1;
    $marque = $_POST['marque'] ?? null;
    $etat = $_POST['etat'] ?? null;
    $date_acquisition = $_POST['date_acquisition'] ?? null;
    $valeur_achat = $_POST['valeur_achat'] ?? null;

    // Photo
    $photo_path = null;
    if (!empty($_FILES['photo']['tmp_name'])) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_name = uniqid('photo_') . '.' . $ext;
        $photo_dir = '../uploads/inventaire/';
        if (!is_dir($photo_dir)) mkdir($photo_dir, 0777, true);
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $photo_name);
        $photo_path = 'uploads/inventaire/' . $photo_name;
    }

    // Insertion
    $stmt = $conn->prepare("INSERT INTO objet_inventaire (session_id, nom_objet, quantite, photo, marque, etat, date_acquisition, valeur_achat)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$session_id, $nom_objet, $quantite, $photo_path, $marque, $etat, $date_acquisition, $valeur_achat]);

    echo "<div class='alert alert-success'>Objet ajouté !</div>";
}
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faire Inventaire (mobile)</title>
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

<h2>Inventaire : <?= htmlspecialchars($session['nom_du_logement']) ?></h2>

<form method="post" enctype="multipart/form-data">
    <label>Nom de l’objet :</label>
    <input type="text" name="nom_objet" required><br>

    <label>Quantité :</label>
    <input type="number" name="quantite" value="1" required><br>

    <label>Photo :</label>
    <input type="file" name="photo" accept="image/*"><br>

    <label>Marque :</label>
    <input type="text" name="marque"><br>

    <label>État :</label>
    <input type="text" name="etat"><br>

    <label>Date d’acquisition :</label>
    <input type="date" name="date_acquisition"><br>

    <label>Valeur d’achat (€) :</label>
    <input type="number" step="0.01" name="valeur_achat"><br>

    <button type="submit">Ajouter à l’inventaire</button>
</form>
