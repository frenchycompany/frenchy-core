<?php
// pages/logements.php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Gestion de la création et de la modification des logements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_du_logement = $_POST['nom_du_logement'];
    $adresse = $_POST['adresse'];
    $m2 = (float) $_POST['m2'];
    $nombre_de_personnes = (int) $_POST['nombre_de_personnes'];
    $poid_menage = (float) $_POST['poid_menage'];
    $prix_vente_menage = (float) $_POST['prix_vente_menage'];
    $valeur_locative = (float) $_POST['valeur_locative'];
    $valeur_fonciere = (float) $_POST['valeur_fonciere'];
    $code = $_POST['code'];

    if (isset($_POST['logement_id']) && !empty($_POST['logement_id'])) {
        // Mise à jour d'un logement existant
        $logement_id = (int) $_POST['logement_id'];
        $stmt = $conn->prepare("UPDATE liste_logements SET nom_du_logement = ?, adresse = ?, m2 = ?, nombre_de_personnes = ?, poid_menage = ?, prix_vente_menage = ?, valeur_locative = ?, valeur_fonciere = ?, code = ? WHERE id = ?");
        $stmt->execute([$nom_du_logement, $adresse, $m2, $nombre_de_personnes, $poid_menage, $prix_vente_menage, $valeur_locative, $valeur_fonciere, $code, $logement_id]);
    } else {
        // Création d'un nouveau logement
        $stmt = $conn->prepare("INSERT INTO liste_logements (nom_du_logement, adresse, m2, nombre_de_personnes, poid_menage, prix_vente_menage, valeur_locative, valeur_fonciere, code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nom_du_logement, $adresse, $m2, $nombre_de_personnes, $poid_menage, $prix_vente_menage, $valeur_locative, $valeur_fonciere, $code]);
    }
}

// Récupération de tous les logements pour affichage
$query = $conn->query("SELECT * FROM liste_logements");
$logements = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
       <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des logements</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <h2>Gestion des Logements</h2>
    <table>
        <tr>
            <th>Nom du Logement</th>
            <th>Adresse</th>
            <th>M²</th>
            <th>Nombre de Personnes</th>
            <th>Poid Ménage</th>
            <th>Prix vente ménage</th>
            <th>Valeur locative</th>
            <th>Valeur foncière</th>
            <th>Code</th>
            <th>Action</th>
        </tr>
        <?php foreach ($logements as $logement): ?>
            <tr>
                <form method="POST" action="">
                    <td>
                        <input type="text" name="nom_du_logement" value="<?= htmlspecialchars($logement['nom_du_logement']) ?>">
                        <input type="hidden" name="logement_id" value="<?= $logement['id'] ?>">
                    </td>
                    <td><input type="text" name="adresse" value="<?= htmlspecialchars($logement['adresse']) ?>"></td>
                    <td><input type="number" step="0.01" name="m2" value="<?= htmlspecialchars($logement['m2']) ?>"></td>
                    <td><input type="number" name="nombre_de_personnes" value="<?= htmlspecialchars($logement['nombre_de_personnes']) ?>"></td>
                    <td><input type="number" step="0.01" name="poid_menage" value="<?= htmlspecialchars($logement['poid_menage']) ?>"></td>
                    <td><input type="number" step="0.01" name="prix_vente_menage" value="<?= htmlspecialchars($logement['prix_vente_menage']) ?>"></td>
                    <td><input type="number" step="0.01" name="valeur_locative" value="<?= htmlspecialchars($logement['valeur_locative']) ?>"></td>
                    <td><input type="number" step="0.01" name="valeur_fonciere" value="<?= htmlspecialchars($logement['valeur_fonciere']) ?>"></td>
                    <td><input type="text" name="code" value="<?= htmlspecialchars($logement['code']) ?>"></td>
                    <td><button type="submit">Modifier</button></td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Créer un nouveau logement</h3>
    <form method="POST" action="">
        <label for="nom_du_logement">Nom du Logement :</label>
        <input type="text" name="nom_du_logement" required>
        <label for="adresse">Adresse :</label>
        <input type="text" name="adresse" required>
        <label for="m2">M² :</label>
        <input type="number" step="0.01" name="m2" required>
        <label for="nombre_de_personnes">Nombre de Personnes :</label>
        <input type="number" name="nombre_de_personnes" required>
        <label for="poid_menage">Poid Ménage :</label>
        <input type="number" step="0.01" name="poid_menage" required>
        <label for="prix_vente_menage">Prix vente ménage :</label>
        <input type="number" step="0.01" name="prix_vente_menage" required>
        <label for="valeur_locative">Valeur locative :</label>
        <input type="number" step="0.01" name="valeur_locative" required>
        <label for="valeur_fonciere">Valeur foncière :</label>
        <input type="number" step="0.01" name="valeur_fonciere" required>
        <label for="code">Code :</label>
        <input type="text" name="code" required>
        <button type="submit">Créer</button>
    </form>

    <!-- Script pour le menu de navigation -->
    <script>
        function toggleMenu() {
            const menu = document.querySelector(".menu");
            menu.classList.toggle("show");
        }
    </script>
</body>
</html>
