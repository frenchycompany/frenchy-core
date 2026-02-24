
<?php
session_start();
require_once 'config.php';
require_once '../db/connection.php';

// Ajouter un type de commerce
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_type'])) {
    $nom = htmlspecialchars($_POST['nom']);
    $stmt = $conn->prepare("INSERT INTO types_commerce (nom) VALUES (?)");
    $stmt->execute([$nom]);
    header("Location: types_commerce.php");
    exit();
}

// Supprimer un type de commerce
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_type'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM types_commerce WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: types_commerce.php");
    exit();
}

// Récupérer tous les types de commerce
$types_commerce = $conn->query("SELECT * FROM types_commerce")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types de Commerce</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Gestion des Types de Commerce</h1>
        <a href="index.php">Retour au Tableau de Bord</a>
        <a href="logout.php">Déconnexion</a>
    </header>
    <main>
        <h2>Liste des Types de Commerce</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types_commerce as $type): ?>
                    <tr>
                        <td><?php echo $type['id']; ?></td>
                        <td><?php echo htmlspecialchars($type['nom']); ?></td>
                        <td>
                            <form action="types_commerce.php" method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                <button type="submit" name="delete_type" class="btn btn-danger" onclick="return confirm('Voulez-vous vraiment supprimer ce type de commerce ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Ajouter un Nouveau Type de Commerce</h2>
        <form action="types_commerce.php" method="post">
            <label for="nom">Nom du Type de Commerce :</label>
            <input type="text" name="nom" required>
            <button type="submit" name="add_type">Ajouter</button>
        </form>
    </main>
</body>
</html>
