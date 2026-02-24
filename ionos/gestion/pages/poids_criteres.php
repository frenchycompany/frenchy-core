<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclut le menu de navigation
// Récupérer les critères existants
try {
    $stmt = $conn->query("SELECT critere, valeur, temps_par_unite FROM poids_criteres ORDER BY critere ASC");
    $criteres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des critères : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuster les Criteres</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Ajuster les Poids des Critères</h2>

    <!-- Formulaire pour ajuster les poids existants -->
    <form id="criteresForm">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Critère</th>
                        <th>Valeur Actuelle (Poids)</th>
                        <th>Temps estimé (min/unité)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criteres as $critere): ?>
                        <tr>
                            <td><?= htmlspecialchars($critere['critere']) ?></td>
                            <td>
                                <input type="number" step="0.001" name="<?= htmlspecialchars($critere['critere']) ?>_valeur" 
                                       value="<?= htmlspecialchars($critere['valeur']) ?>" class="form-control">
                            </td>
                            <td>
                                <input type="number" step="0.1" name="<?= htmlspecialchars($critere['critere']) ?>_temps" 
                                       value="<?= htmlspecialchars($critere['temps_par_unite']) ?>" class="form-control">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" id="saveButton" class="btn btn-primary mt-3">Enregistrer les poids et temps</button>
    </form>

    <!-- Formulaire pour ajouter un nouveau critère -->
    <h3 class="mt-5">Ajouter un nouveau critère</h3>
    <form id="newCritereForm">
        <div class="form-group">
            <label for="newCritere">Nom du critère :</label>
            <input type="text" id="newCritere" name="critere" class="form-control" placeholder="Nom du critère" required>
        </div>
        <div class="form-group">
            <label for="newValue">Valeur du critère (Poids) :</label>
            <input type="number" id="newValue" name="valeur" step="0.001" class="form-control" placeholder="Valeur du critère" required>
        </div>
        <div class="form-group">
            <label for="newTemps">Temps estimé (min/unité) :</label>
            <input type="number" id="newTemps" name="temps_par_unite" step="0.1" class="form-control" placeholder="Temps estimé" required>
        </div>
        <button type="button" id="addCritereButton" class="btn btn-success">Ajouter le critère</button>
    </form>

    <p id="resultMessage" class="mt-3"></p>
</div>

<script>
    // Sauvegarde des poids et temps existants
    document.getElementById('saveButton').addEventListener('click', function () {
        const formData = new FormData(document.getElementById('criteresForm'));

        fetch('save_poids_criteres.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(message => {
                document.getElementById('resultMessage').textContent = message;
            })
            .catch(error => {
                console.error('Erreur lors de la sauvegarde :', error);
                document.getElementById('resultMessage').textContent = 'Une erreur est survenue lors de la sauvegarde.';
            });
    });

    // Ajout d'un nouveau critère
    document.getElementById('addCritereButton').addEventListener('click', function () {
        const formData = new FormData(document.getElementById('newCritereForm'));

        fetch('add_poids_critere.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(message => {
                document.getElementById('resultMessage').textContent = message;
                // Recharger la page pour voir le nouveau critère
                setTimeout(() => location.reload(), 1000);
            })
            .catch(error => {
                console.error('Erreur lors de l\'ajout du critère :', error);
                document.getElementById('resultMessage').textContent = 'Une erreur est survenue lors de l\'ajout.';
            });
    });
</script>
</body>
</html>
