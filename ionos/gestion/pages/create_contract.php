<?php
include '../config.php'; // Inclut la configuration de la base de données
include '../pages/menu.php'; // Inclut le menu de navigation

try {
    // Récupérer tous les modèles de contrat
    $templatesStmt = $conn->query("SELECT id, title FROM contract_templates");
    $templates = $templatesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer la liste des logements
    $logementsStmt = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $logementsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Créer un Contrat</title>';
    echo '<link rel="stylesheet" href="../css/style.css">';
    echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>'; // Inclure jQuery pour le chargement dynamique
    echo '</head>';
    echo '<body>';
    echo '<div class="container mt-4">';

    echo '<h2>Créer un contrat de conciergerie</h2>';
    echo '<form id="contractForm" action="generate_contract.php" method="POST">';

    // Liste déroulante pour choisir un modèle
    echo '<div class="mb-3">';
    echo '<label for="template_id" class="form-label">Choisissez un modèle de contrat :</label>';
    echo '<select name="template_id" id="template_id" class="form-select" required>';
    echo '<option value="">-- Sélectionnez un modèle --</option>';
    foreach ($templates as $template) {
        echo "<option value='{$template['id']}'>{$template['title']}</option>";
    }
    echo '</select>';
    echo '</div>';

    // Liste déroulante pour sélectionner un logement
    echo '<div class="mb-3">';
    echo '<label for="logement_id" class="form-label">Sélectionnez le logement :</label>';
    echo '<select name="logement_id" id="logement_id" class="form-select" required>';
    echo '<option value="">-- Choisissez un logement --</option>';
    foreach ($logements as $logement) {
        echo "<option value='{$logement['id']}'>{$logement['nom_du_logement']}</option>";
    }
    echo '</select>';
    echo '</div>';

    // Section pour afficher les champs dynamiques
    echo '<div id="dynamicFields"></div>';

    // Bouton de soumission
    echo '<button type="submit" class="btn btn-primary">Générer le contrat</button>';
    echo '</form>';

    echo '</div>';
    echo '<script>
    $(document).ready(function() {
        // Charger les champs dynamiques en fonction du modèle sélectionné
        $("#template_id").on("change", function() {
            var templateId = $(this).val();
            if (templateId) {
                $.ajax({
                    url: "get_template_fields.php",
                    type: "GET",
                    data: { id: templateId },
                    success: function(response) {
                        $("#dynamicFields").html(response);
                    },
                    error: function() {
                        alert("Erreur lors du chargement des champs.");
                    }
                });
            } else {
                $("#dynamicFields").html("");
            }
        });

        // Charger les données liées au logement sélectionné
        $("#logement_id").on("change", function() {
            var logementId = $(this).val();
            if (logementId) {
                $.ajax({
                    url: "get_logement_infos.php",
                    type: "GET",
                    data: { logement_id: logementId },
                    success: function(data) {
                        try {
                            var logementData = JSON.parse(data);
                            if (logementData.error) {
                                alert("Erreur : " + logementData.error);
                                return;
                            }

                            // Injecter les données dans les champs correspondants
                            for (var key in logementData) {
                                var field = $("#" + key);
                                if (field.length) {
                                    field.val(logementData[key]);
                                }
                            }
                        } catch (e) {
                            console.error("Erreur lors de l\'analyse des données : ", e);
                            console.log("Données reçues : ", data);
                            alert("Erreur lors de l\'analyse des données du logement.");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erreur AJAX : ", error);
                        alert("Erreur lors du chargement des données du logement.");
                    }
                });
            } else {
                $("#dynamicFields").html("");
            }
        });
    });
    </script>';

    echo '</body>';
    echo '</html>';
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
