<?php
// planning_user.php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Vérifie si l'utilisateur a accès à cette page
if ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin') {
    header("Location: ../error.php?message=Accès refusé");
    exit;
}

// Définir les transitions de statut autorisées
$transitions_statut = [
    'À Faire' => ['Fait'],
    'À Vérifier' => ['Vérifier'],
];

// Liste complète des statuts pour l'affichage
$statuts_possibles = ['À Faire', 'Fait', 'À Vérifier', 'Vérifier'];

// Gestion de la modification des statuts uniquement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_statut') {
    $intervention_id = (int)$_POST['intervention_id'];
    $nouveau_statut = $_POST['statut'];

    // Récupérer le statut actuel
    $stmt = $conn->prepare("SELECT statut FROM planning WHERE id = ?");
    $stmt->execute([$intervention_id]);
    $statut_actuel = $stmt->fetchColumn();

    // Valider la transition
    if (isset($transitions_statut[$statut_actuel]) && in_array($nouveau_statut, $transitions_statut[$statut_actuel], true)) {
        $stmt = $conn->prepare("UPDATE planning SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $intervention_id]);
    } else {
        error_log("Transition de statut non autorisée : $statut_actuel vers $nouveau_statut");
    }
}

// Récupération de toutes les interventions pour affichage avec les noms des intervenants
$query = $conn->query("
    SELECT 
        p.id,
        p.date,
        p.nombre_de_personnes,
        p.statut,
        l.nom_du_logement,
        i1.nom AS conducteur_nom,
        i2.nom AS femme_de_menage_1_nom,
        i3.nom AS femme_de_menage_2_nom,
        i4.nom AS laverie_nom
    FROM planning p
    LEFT JOIN liste_logements l ON p.logement_id = l.id
    LEFT JOIN intervenant i1 ON p.conducteur = i1.id
    LEFT JOIN intervenant i2 ON p.femme_de_menage_1 = i2.id
    LEFT JOIN intervenant i3 ON p.femme_de_menage_2 = i3.id
    LEFT JOIN intervenant i4 ON p.laverie = i4.id
    ORDER BY p.date ASC
");
$interventions = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning - Utilisateur</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Planning - Vue Utilisateur</h2>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Nom du Logement</th>
            <th>Date</th>
            <th>Nombre de Voyageurs</th>
            <th>Conducteur</th>
            <th>Femme de Ménage 1</th>
            <th>Femme de Ménage 2</th>
            <th>Laverie</th>
            <th>Statut</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($interventions as $intervention): ?>
            <tr>
                <form method="POST" action="">
                    <td><?= htmlspecialchars($intervention['nom_du_logement']) ?></td>
                    <td><?= htmlspecialchars($intervention['date']) ?></td>
                    <td><?= htmlspecialchars($intervention['nombre_de_personnes']) ?></td>
                    <td><?= htmlspecialchars($intervention['conducteur_nom'] ?? 'Non assigné') ?></td>
                    <td><?= htmlspecialchars($intervention['femme_de_menage_1_nom'] ?? 'Non assigné') ?></td>
                    <td><?= htmlspecialchars($intervention['femme_de_menage_2_nom'] ?? 'Non assigné') ?></td>
                    <td><?= htmlspecialchars($intervention['laverie_nom'] ?? 'Non assigné') ?></td>
                    <td>
                        <select name="statut" class="form-control">
                            <?php foreach ($statuts_possibles as $statut_possible): ?>
                                <option value="<?= $statut_possible ?>" <?= $intervention['statut'] === $statut_possible ? 'selected' : '' ?>>
                                    <?= $statut_possible ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button type="submit" name="action" value="modifier_statut" class="btn btn-primary btn-sm">Modifier</button>
                        <input type="hidden" name="intervention_id" value="<?= $intervention['id'] ?>">
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
