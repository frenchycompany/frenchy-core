<?php
// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connexion à la BDD + lib QR
require_once '../db/connection.php';
require_once '../lib/phpqrcode/qrlib.php';

// Activer les exceptions PDO
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $logement_id = $_POST['logement_id'];
        $nom_objet = $_POST['nom_objet'];
        $marque = $_POST['marque'] ?? null;
        $etat = $_POST['etat'] ?? null;
        $date_acquisition = $_POST['date_acquisition'] ?? null;
        $valeur_achat = $_POST['valeur_achat'] ?? null;
        $quantite = $_POST['quantite'] ?? 1;
        $notes = $_POST['notes'] ?? null;

        // PHOTO OBJET
        $photo_objet_path = null;
        if (!empty($_FILES['photo_objet']['tmp_name'])) {
            if ($_FILES['photo_objet']['error'] === UPLOAD_ERR_OK) {
                $photo_ext = pathinfo($_FILES['photo_objet']['name'], PATHINFO_EXTENSION);
                $photo_name = uniqid('photo_') . '.' . $photo_ext;
                $photo_path_server = dirname(__DIR__) . '/uploads/photos/' . $photo_name;
                move_uploaded_file($_FILES['photo_objet']['tmp_name'], $photo_path_server);
                $photo_objet_path = '../uploads/photos/' . $photo_name;
            } else {
                throw new Exception("Erreur lors de l'upload de la photo.");
            }
        }

        // FACTURE
        $facture_path = null;
        if (!empty($_FILES['facture']['tmp_name'])) {
            if ($_FILES['facture']['error'] === UPLOAD_ERR_OK) {
                $facture_ext = pathinfo($_FILES['facture']['name'], PATHINFO_EXTENSION);
                $facture_name = uniqid('facture_') . '.' . $facture_ext;
                $facture_path_server = dirname(__DIR__) . '/uploads/factures/' . $facture_name;
                move_uploaded_file($_FILES['facture']['tmp_name'], $facture_path_server);
                $facture_path = '../uploads/factures/' . $facture_name;
            } else {
                throw new Exception("Erreur lors de l'upload de la facture.");
            }
        }

        // INSERTION SANS QR CODE
        $stmt = $conn->prepare("INSERT INTO objet_logements (
            logement_id, nom_objet, marque, etat, date_acquisition, valeur_achat, quantite,
            notes, qr_code_path, photo_objet, facture_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?)");

        $stmt->execute([
            $logement_id, $nom_objet, $marque, $etat, $date_acquisition, $valeur_achat, $quantite,
            $notes, $photo_objet_path, $facture_path
        ]);

        // ID INSÉRÉ
        $objet_id = $conn->lastInsertId();

        // QR CODE
        $qr_file_name = 'qr_' . $objet_id . '.png';
        $qr_file_server = dirname(__DIR__) . '/uploads/qrcodes/' . $qr_file_name;
        $qr_file = '../uploads/qrcodes/' . $qr_file_name;
        $url_objet = "https://gestion.frenchyconciergerie.fr/pages/objet.php?id=" . $objet_id;

        QRcode::png($url_objet, $qr_file_server);

        // MISE À JOUR DE LA TABLE
        $update = $conn->prepare("UPDATE objet_logements SET qr_code_path = ? WHERE id = ?");
        $update->execute([$qr_file, $objet_id]);

        echo "<div style='color:green;'>✅ Objet ajouté avec succès.</div>";

    } catch (Exception $e) {
        echo "<div style='color:red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventaire (mobile)</title>
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

<h2>Ajouter un objet à un logement</h2>
<form method="post" enctype="multipart/form-data">
    <label>Logement :</label>
    <select name="logement_id" required>
        <?php
        try {
            $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($logements as $logement) {
                echo "<option value='{$logement['id']}'>{$logement['nom_du_logement']}</option>";
            }
        } catch (PDOException $e) {
            echo "<option disabled>Erreur chargement logements</option>";
        }
        ?>
    </select><br><br>

    <label>Nom de l’objet :</label>
    <input type="text" name="nom_objet" required><br>

    <label>Marque :</label>
    <input type="text" name="marque"><br>

    <label>État :</label>
    <input type="text" name="etat"><br>

    <label>Date d’acquisition :</label>
    <input type="date" name="date_acquisition"><br>

    <label>Valeur d’achat (€) :</label>
    <input type="number" step="0.01" name="valeur_achat"><br>

    <label>Quantité :</label>
    <input type="number" name="quantite" value="1"><br>

    <label>Notes :</label>
    <textarea name="notes"></textarea><br>

    <label>Photo de l’objet :</label>
    <input type="file" name="photo_objet" accept="image/*"><br>

    <label>Facture (image ou PDF) :</label>
    <input type="file" name="facture" accept="image/*,application/pdf"><br><br>

    <button type="submit">➕ Ajouter l’objet</button>
</form>
