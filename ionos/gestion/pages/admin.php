<?php
// pages/admin.php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu

// Initialisation des données pour le tableau de bord
try {
    // Récupérer la configuration générale
    $config_query = $conn->query("SELECT * FROM configuration LIMIT 1");
    $configuration = $config_query->fetch(PDO::FETCH_ASSOC) ?? [
        'nom_site' => 'Nom du Site',
        'email_contact' => '',
        'mode_maintenance' => 0,
        'footer_text' => '',
    ];

    // Compter les différentes entités
    $intervenants_count = $conn->query("SELECT COUNT(*) AS count FROM intervenant")->fetchColumn();
    $pages_count = $conn->query("SELECT COUNT(*) AS count FROM pages WHERE afficher_menu = 1")->fetchColumn();
    $tasks_count = $conn->query("SELECT COUNT(*) AS count FROM todo_list WHERE statut = 'en attente'")->fetchColumn();
    $logements_count = $conn->query("SELECT COUNT(*) AS count FROM liste_logements")->fetchColumn();
    $poids_criteres_count = $conn->query("SELECT COUNT(*) AS count FROM poids_criteres")->fetchColumn();
    $description_logements_count = $conn->query("SELECT COUNT(*) AS count FROM description_logements")->fetchColumn();
} catch (PDOException $e) {
    header("Location: error.php?message=" . urlencode("Erreur de base de données : " . $e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h1 class="text-center">Tableau de Bord Administrateur</h1>

    <!-- Résumé du tableau de bord -->
    <div class="row text-center mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Intervenants</h5>
                    <p class="card-text"><?= $intervenants_count ?> au total</p>
                    <a href="intervenants.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Pages</h5>
                    <p class="card-text"><?= $pages_count ?> affichées</p>
                    <a href="gestion_pages.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Tâches</h5>
                    <p class="card-text"><?= $tasks_count ?> en attente</p>
                    <a href="todo_list_complete.php" class="btn btn-light">Voir</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Logements</h5>
                    <p class="card-text"><?= $logements_count ?> au total</p>
                    <a href="logements.php" class="btn btn-light">Voir</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Section supplémentaire -->
    <div class="row text-center mt-4">
        <div class="col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Poids des Critères</h5>
                    <p class="card-text"><?= $poids_criteres_count ?> critères définis</p>
                    <a href="poids_criteres.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title">Descriptions des Logements</h5>
                    <p class="card-text"><?= $description_logements_count ?> fiches existantes</p>
                    <a href="description_logements.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Configuration Générale -->
    <div class="mt-5">
        <h2>Configuration Générale</h2>
        <form method="POST" action="config_general.php">
            <div class="form-group">
                <label for="nom_site">Nom du Site</label>
                <input type="text" id="nom_site" name="nom_site" class="form-control" value="<?= htmlspecialchars($configuration['nom_site']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email_contact">Email de Contact</label>
                <input type="email" id="email_contact" name="email_contact" class="form-control" value="<?= htmlspecialchars($configuration['email_contact']) ?>" required>
            </div>
            <div class="form-group">
                <label for="mode_maintenance">Mode Maintenance</label>
                <select id="mode_maintenance" name="mode_maintenance" class="form-control">
                    <option value="0" <?= $configuration['mode_maintenance'] == 0 ? 'selected' : '' ?>>Désactivé</option>
                    <option value="1" <?= $configuration['mode_maintenance'] == 1 ? 'selected' : '' ?>>Activé</option>
                </select>
            </div>
            <div class="form-group">
                <label for="footer_text">Texte du Pied de Page</label>
                <textarea id="footer_text" name="footer_text" class="form-control"><?= htmlspecialchars($configuration['footer_text']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
