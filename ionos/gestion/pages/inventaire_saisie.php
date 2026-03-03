<?php
include '../config.php';
include '../pages/menu.php';


// Vérifie que la session est bien spécifiée
if (!isset($_GET['session_id'])) {
    die("Session d'inventaire non spécifiée.");
}
$session_id = $_GET['session_id'];

// Vérifie que la session existe dans la base
$stmt = $conn->prepare("SELECT * FROM sessions_inventaire WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die("Session introuvable.");
}

$logement_id = $session['logement_id'];

// Traitement du formulaire d'ajout d’objet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom_objet'])) {
    $nom = $_POST['nom_objet'];
    $quantite = intval($_POST['quantite']);
    $marque = $_POST['marque'] ?? '';
    $etat = $_POST['etat'] ?? '';
    $date_acquisition = $_POST['date_acquisition'] ?? null;
    $valeur = $_POST['valeur'] ?? null;
    $remarques = $_POST['remarques'] ?? '';

    // Gestion de l’image
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $upload_dir = '../uploads/photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'photo_' . uniqid() . '.' . $ext;
        $photo_path = $upload_dir . $filename;
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
    }

    // Insertion
    $stmt = $conn->prepare("INSERT INTO inventaire_objets 
        (session_id, logement_id, nom_objet, quantite, marque, etat, date_acquisition, valeur, remarques, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $session_id, $logement_id, $nom, $quantite, $marque, $etat, $date_acquisition, $valeur, $remarques, $photo_path
    ]);
    echo "<div style='color:green'>Objet ajouté.</div>";
}

// Suppression d’un objet
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("SELECT photo_path FROM inventaire_objets WHERE id = ? AND session_id = ?");
    $stmt->execute([$delete_id, $session_id]);
    $obj = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($obj && $obj['photo_path'] && file_exists($obj['photo_path'])) {
        unlink($obj['photo_path']);
    }
    $stmt = $conn->prepare("DELETE FROM inventaire_objets WHERE id = ? AND session_id = ?");
    $stmt->execute([$delete_id, $session_id]);
    echo "<div style='color:red'>Objet supprimé.</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Saisie Inventaire (mobile)</title>
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
    <h2>Ajouter un objet pour <?= htmlspecialchars($session_id) ?></h2>
    <form method="post" enctype="multipart/form-data">
        <label>Photo : <input type="file" name="photo" required></label><br>
        <label>Nom de l'objet : <input type="text" name="nom_objet" required></label><br>
        <label>Quantité : <input type="number" name="quantite" value="1" required></label><br>
        <label>Marque : <input type="text" name="marque"></label><br>
        <label>État :
            <select name="etat">
                <option value="neuf">Neuf</option>
                <option value="bon">Bon</option>
                <option value="usé">Usé</option>
            </select>
        </label><br>
        <label>Date acquisition : <input type="date" name="date_acquisition"></label><br>
        <label>Valeur (€) : <input type="number" name="valeur" step="0.01"></label><br>
        <label>Remarques : <input type="text" name="remarques"></label><br>
        <button type="submit">Ajouter</button>
    </form>

    <hr>
    <h3>Objets ajoutés</h3>
    <ul>
        <?php
        $stmt = $conn->prepare("SELECT * FROM inventaire_objets WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $objets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($objets as $obj) {
            echo "<li>";
            if ($obj['photo_path'] && file_exists($obj['photo_path'])) {
                echo "<img src='" . $obj['photo_path'] . "' width='60'> ";
            }
            echo htmlspecialchars($obj['nom_objet']) . " (x{$obj['quantite']})";
            echo " <a href='?session_id={$session_id}&delete_id={$obj['id']}' style='color:red' onclick='return confirm(\"Supprimer cet objet ?\")'>[Supprimer]</a>";
            echo "</li>";
        }
        ?>
    </ul>

    <a href="inventaire_valider.php?session_id=<?= urlencode($session_id) ?>">➡️ Valider et attribuer les objets</a>
</body>
</html>
