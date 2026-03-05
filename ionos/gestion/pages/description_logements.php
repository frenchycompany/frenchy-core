<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

// Récupérer la liste des logements
try {
    $stmt = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement ASC");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des logements : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Description des Logements</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container mt-4">
    <h2>Gestion des Fiches Logements</h2>

    <!-- Liste déroulante pour sélectionner un logement -->
    <form id="logementForm">
        <div class="form-group">
            <label for="logement_id">Logement :</label>
            <select id="logement_id" name="logement_id" class="form-control">
                <option value="">-- Sélectionnez un logement --</option>
                <?php foreach ($logements as $logement): ?>
                    <option value="<?= htmlspecialchars($logement['id']) ?>">
                        <?= htmlspecialchars($logement['nom_du_logement']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Zone pour afficher le formulaire dynamique -->
        <div id="formFields" class="mt-3"></div>
    </form>
</div>

<script>
    document.getElementById('logement_id').addEventListener('change', function () {
        const logementId = this.value;
        const formFields = document.getElementById('formFields');

        if (!logementId) {
            formFields.innerHTML = ''; // Réinitialise le formulaire si aucun logement sélectionné
            return;
        }

        // Récupérer les données du logement sélectionné
        fetch('get_description_logement.php?logement_id=' + logementId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    formFields.innerHTML = '<p class="text-danger">' + data.error + '</p>';
                    return;
                }

                // Générer le formulaire dynamique avec les données récupérées
                let formContent = `<h4>Fiche du logement</h4>`;

                // Ajouter des champs cachés pour l'ID du logement
                formContent += `
                    <input type="hidden" id="logement_id_hidden" name="logement_id" value="${data.logement_id}">
                `;

                // Générer les autres champs (exclure les ID)
                Object.entries(data).forEach(([key, value]) => {
                    if (key !== 'logement_id' && key !== 'id') { // Exclure les ID
                        formContent += `
                            <div class="form-group">
                                <label for="${key}">${key.replace('_', ' ').toUpperCase()} :</label>
                                <input type="number" id="${key}" name="${key}" value="${value}" step="0.1" class="form-control">
                            </div>
                        `;
                    }
                });

                // Ajouter un bouton pour calculer le poids de ménage
                formContent += `
                    <button type="button" id="calculateWeightButton" class="btn btn-secondary mt-3">Calculer le poids de ménage</button>
                    <p id="weightResult" class="mt-2 text-info"></p>
                `;

                // Ajouter un bouton pour sauvegarder les données
                formContent += `
                    <button type="button" id="saveButton" class="btn btn-primary mt-3">Sauvegarder</button>
                `;

                formFields.innerHTML = formContent;

                // Gestionnaire d'événement pour sauvegarder les données
                document.getElementById('saveButton').addEventListener('click', function () {
                    const formData = new FormData(document.getElementById('logementForm'));
                    fetch('save_description_logement.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.text())
                        .then(message => {
                            alert(message); // Afficher un message de succès ou d'erreur
                        })
                        .catch(error => {
                            console.error('Erreur lors de la sauvegarde :', error);
                            alert('Une erreur est survenue lors de la sauvegarde.');
                        });
                });

                // Gestionnaire d'événement pour calculer le poids de ménage
                document.getElementById('calculateWeightButton').addEventListener('click', function () {
                    const logementId = document.getElementById('logement_id_hidden').value;

                    fetch('calculate_poid_menage.php?logement_id=' + logementId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                document.getElementById('weightResult').textContent = `Erreur : ${data.error}`;
                            } else {
                                document.getElementById('weightResult').innerHTML = `
                                    <p><strong>Poids de ménage :</strong> ${data.poids_menage} points</p>
                                    <p><strong>Temps estimé :</strong> ${data.temps_estime} minutes</p>
                                    <p><strong>Temps moyen passé :</strong> ${data.temps_passe_moyen} minutes</p>
                                    <p><strong>Évaluation :</strong> ${data.evaluation}</p>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Erreur lors du calcul du poids de ménage :', error);
                            document.getElementById('weightResult').textContent = 'Erreur lors du calcul.';
                        });
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des données :', error);
                formFields.innerHTML = '<p class="text-danger">Impossible de charger les données.</p>';
            });
    });
</script>
</body>
</html>
