<?php
// pages/roles.php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Gestion de la création et de la modification des rôles
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $valeur = (float) $_POST['valeur'];
    
    if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
        // Mise à jour d'un rôle existant
        $role_id = (int) $_POST['role_id'];
        $stmt = $conn->prepare("UPDATE role SET role = ?, valeur = ? WHERE id = ?");
        $stmt->execute([$role, $valeur, $role_id]);
    } else {
        // Création d'un nouveau rôle
        $stmt = $conn->prepare("INSERT INTO role (role, valeur) VALUES (?, ?)");
        $stmt->execute([$role, $valeur]);
    }
}

// Récupération de tous les rôles pour affichage
$query = $conn->query("SELECT * FROM role");
$roles = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
      <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rôles</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <h2>Gestion des Rôles</h2>
    <table>
        <tr>
            <th>Rôle</th>
            <th>Valeur</th>
            <th>Action</th>
        </tr>
        <?php foreach ($roles as $role): ?>
            <tr>
                <form method="POST" action="">
                    <td>
                        <input type="text" name="role" value="<?= htmlspecialchars($role['role']) ?>">
                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                    </td>
                    <td><input type="number" step="0.01" name="valeur" value="<?= htmlspecialchars($role['valeur']) ?>"></td>
                    <td><button type="submit">Modifier</button></td>
                </form>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Créer un nouveau rôle</h3>
    <form method="POST" action="">
        <label for="role">Nom du rôle :</label>
        <input type="text" name="role" required>
        <label for="valeur">Valeur :</label>
        <input type="number" step="0.01" name="valeur" required>
        <button type="submit">Créer</button>
    </form>
</body>
</html>
