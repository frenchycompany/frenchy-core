<?php
/**
 * Villes & Recommandations — Page unifiée
 * Gestion des villes et recommandations locales pour les voyageurs
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

$feedback = '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    if (isset($_POST['add_ville'])) {
        $nom = trim($_POST['nom'] ?? '');
        if (!empty($nom)) {
            try {
                $pdo->prepare("INSERT INTO villes (nom) VALUES (?)")->execute([$nom]);
                $feedback = '<div class="alert alert-success">Ville ajoutée.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    if (isset($_POST['add_recommandation'])) {
        $ville_id = (int)$_POST['ville_id'];
        $categorie = trim($_POST['categorie'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');

        if (!empty($nom) && $ville_id > 0) {
            try {
                $pdo->prepare("
                    INSERT INTO ville_recommandations (ville_id, categorie, nom, description, adresse)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$ville_id, $categorie, $nom, $description, $adresse]);
                $feedback = '<div class="alert alert-success">Recommandation ajoutée.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}

// Récupérer les villes
$villes = [];
try {
    $villes = $pdo->query("
        SELECT v.*, COUNT(vr.id) as nb_recommandations
        FROM villes v
        LEFT JOIN ville_recommandations vr ON v.id = vr.ville_id
        GROUP BY v.id
        ORDER BY v.nom
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Récupérer les recommandations
$recommandations = [];
try {
    $recommandations = $pdo->query("
        SELECT vr.*, v.nom as ville_nom
        FROM ville_recommandations vr
        LEFT JOIN villes v ON vr.ville_id = v.id
        ORDER BY v.nom, vr.categorie, vr.nom
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villes & Recommandations — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-city text-primary"></i> Villes & Recommandations</h2>
    <p class="text-muted"><?= count($villes) ?> ville(s), <?= count($recommandations) ?> recommandation(s)</p>

    <?= $feedback ?>

    <div class="row">
        <!-- Villes -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Villes</h5></div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <?php echoCsrfField(); ?>
                        <div class="input-group">
                            <input type="text" name="nom" class="form-control" placeholder="Nouvelle ville" required>
                            <button type="submit" name="add_ville" class="btn btn-primary"><i class="fas fa-plus"></i></button>
                        </div>
                    </form>
                    <ul class="list-group">
                    <?php foreach ($villes as $v): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= htmlspecialchars($v['nom']) ?>
                            <span class="badge bg-info"><?= $v['nb_recommandations'] ?></span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Ajout recommandation -->
            <div class="card">
                <div class="card-header bg-success text-white"><h5 class="mb-0">Ajouter une recommandation</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <div class="mb-2">
                            <select name="ville_id" class="form-select" required>
                                <option value="">Ville *</option>
                                <?php foreach ($villes as $v): ?>
                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <select name="categorie" class="form-select">
                                <option value="restaurant">Restaurant</option>
                                <option value="activite">Activité</option>
                                <option value="transport">Transport</option>
                                <option value="shopping">Shopping</option>
                                <option value="sante">Santé</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        <div class="mb-2"><input type="text" name="nom" class="form-control" placeholder="Nom *" required></div>
                        <div class="mb-2"><textarea name="description" class="form-control" rows="2" placeholder="Description"></textarea></div>
                        <div class="mb-2"><input type="text" name="adresse" class="form-control" placeholder="Adresse"></div>
                        <button type="submit" name="add_recommandation" class="btn btn-success w-100"><i class="fas fa-plus"></i> Ajouter</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recommandations -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Recommandations</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Ville</th><th>Catégorie</th><th>Nom</th><th>Description</th><th>Adresse</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recommandations as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['ville_nom'] ?? '') ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($r['categorie'] ?? '') ?></span></td>
                                    <td><strong><?= htmlspecialchars($r['nom']) ?></strong></td>
                                    <td><small><?= htmlspecialchars(mb_substr($r['description'] ?? '', 0, 60)) ?></small></td>
                                    <td><small><?= htmlspecialchars($r['adresse'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recommandations)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Aucune recommandation.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
