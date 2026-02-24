<?php
// gestion_pages.php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Menu de navigation

// Vérifie si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../error.php?message=Accès non autorisé.");
    exit;
}

// Gestion des pages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['page_id']) && isset($_POST['action'])) {
        $page_id = (int)$_POST['page_id'];
        $action = $_POST['action'];

        if ($action === 'update') {
            // Mise à jour d'une page existante
            $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
            $chemin = filter_input(INPUT_POST, 'chemin', FILTER_SANITIZE_STRING);
            $afficher_menu = isset($_POST['afficher_menu']) ? 1 : 0;

            try {
                $stmt = $conn->prepare("UPDATE pages SET nom = ?, chemin = ?, afficher_menu = ? WHERE id = ?");
                $stmt->execute([$nom, $chemin, $afficher_menu, $page_id]);
            } catch (PDOException $e) {
                die("Erreur lors de la mise à jour : " . $e->getMessage());
            }
        } elseif ($action === 'delete') {
            // Suppression d'une page
            try {
                $stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
                $stmt->execute([$page_id]);
            } catch (PDOException $e) {
                die("Erreur lors de la suppression : " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['add_page'])) {
        // Ajout d'une nouvelle page
        $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
        $chemin = filter_input(INPUT_POST, 'chemin', FILTER_SANITIZE_STRING);
        $afficher_menu = isset($_POST['afficher_menu']) ? 1 : 0;

        try {
            $stmt = $conn->prepare("INSERT INTO pages (nom, chemin, afficher_menu) VALUES (?, ?, ?)");
            $stmt->execute([$nom, $chemin, $afficher_menu]);
        } catch (PDOException $e) {
            die("Erreur lors de l'ajout : " . $e->getMessage());
        }
    }
}

// Récupération des pages
try {
    $pages_query = $conn->query("SELECT * FROM pages");
    $pages = $pages_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des pages : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Pages</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Gestion des Pages</h2>

    <table class="table table-striped">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Chemin</th>
            <th>Visible dans le menu</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $page): ?>
            <tr>
                <form method="POST" action="">
                    <td>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($page['nom']) ?>" required>
                        <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                    </td>
                    <td>
                        <input type="text" name="chemin" class="form-control" value="<?= htmlspecialchars($page['chemin']) ?>" required>
                    </td>
                    <td>
                        <input type="checkbox" name="afficher_menu" <?= $page['afficher_menu'] ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="submit" name="action" value="update" class="btn btn-primary btn-sm">Mettre à jour</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression ?')">Supprimer</button>
                        </div>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Ajouter une nouvelle page</h3>
    <form method="POST" action="">
        <div class="form-group">
            <label for="nom">Nom :</label>
            <input type="text" name="nom" id="nom" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="chemin">Chemin :</label>
            <input type="text" name="chemin" id="chemin" class="form-control" required>
        </div>
        <div class="form-group form-check">
            <input type="checkbox" name="afficher_menu" id="afficher_menu" class="form-check-input">
            <label for="afficher_menu" class="form-check-label">Afficher dans le menu</label>
        </div>
        <button type="submit" name="add_page" class="btn btn-success">Ajouter la page</button>
    </form>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
