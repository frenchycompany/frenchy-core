<?php
// pages/liste_logements.php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

try {
    // Récupération des logements
    $query = $conn->query("SELECT id, nom_du_logement, adresse, code FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
 <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Éditer le planning</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
  <link
    rel="stylesheet"
    integrity="sha384-…"
    crossorigin="anonymous"
  />
</head>
<body>
<div class="container mt-4">
    <h2 class="text-center">Liste des Logements</h2>

    <table class="table table-striped">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Adresse</th>
            <th>Code</th>
            <th>Tâches</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($logements): ?>
            <?php foreach ($logements as $logement): ?>
                <tr>
                    <td><?= htmlspecialchars($logement['nom_du_logement']) ?></td>
                    <td><?= htmlspecialchars($logement['adresse']) ?></td>
                    <td><?= htmlspecialchars($logement['code']) ?></td>
                    <td>
                        <a href="todo.php?logement_id=<?= $logement['id'] ?>" class="btn btn-primary btn-sm">
                            Voir les Tâches
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center">Aucun logement trouvé.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap JS -->
  <script
    integrity="sha384-…"
    crossorigin="anonymous"
  ></script>
</body>
</html>
