<?php
/**
 * Creer un contrat de conciergerie
 * Bootstrap 5, auto-fill depuis logement + proprietaire
 */
include '../config.php';
include '../pages/menu.php';

// Recuperer les modeles de contrat
$templates = [];
try {
    $stmt = $conn->query("SELECT id, title FROM contract_templates ORDER BY title");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Recuperer les logements actifs
$logements = [];
try {
    $stmt = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-file-contract text-primary"></i> Creer un contrat</h2>
            <p class="text-muted">Selectionnez un modele et un logement pour generer un contrat pre-rempli</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_templates.php" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt"></i> Modeles
            </a>
        </div>
    </div>

    <form id="contractForm" action="generate_contract.php" method="POST">
        <?php echoCsrfField(); ?>

        <div class="row">
            <!-- Colonne gauche : selection -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="template_id" class="form-label fw-bold">Modele de contrat</label>
                            <select name="template_id" id="template_id" class="form-select" required>
                                <option value="">-- Selectionnez un modele --</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="logement_id" class="form-label fw-bold">Logement</label>
                            <select name="logement_id" id="logement_id" class="form-select" required>
                                <option value="">-- Selectionnez un logement --</option>
                                <?php foreach ($logements as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="date_contrat" class="form-label fw-bold">Date du contrat</label>
                            <input type="date" name="date_contrat" id="date_contrat" class="form-control"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Info logement auto-rempli -->
                <div class="card shadow-sm mb-4" id="logementInfoCard" style="display:none;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-home"></i> Logement</h5>
                    </div>
                    <div class="card-body" id="logementInfoBody">
                    </div>
                </div>

                <!-- Info proprietaire auto-rempli -->
                <div class="card shadow-sm mb-4" id="proprietaireInfoCard" style="display:none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie"></i> Proprietaire</h5>
                    </div>
                    <div class="card-body" id="proprietaireInfoBody">
                    </div>
                </div>
            </div>

            <!-- Colonne droite : champs dynamiques -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Champs du contrat</h5>
                    </div>
                    <div class="card-body" id="dynamicFields">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-hand-pointer fa-3x mb-3"></i>
                            <p>Selectionnez un modele de contrat pour afficher les champs a remplir</p>
                        </div>
                    </div>
                    <div class="card-footer text-end" id="submitSection" style="display:none;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-file-export"></i> Generer le contrat
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateSelect = document.getElementById('template_id');
    const logementSelect = document.getElementById('logement_id');
    const dynamicFields = document.getElementById('dynamicFields');
    const submitSection = document.getElementById('submitSection');
    const logementInfoCard = document.getElementById('logementInfoCard');
    const logementInfoBody = document.getElementById('logementInfoBody');
    const proprietaireInfoCard = document.getElementById('proprietaireInfoCard');
    const proprietaireInfoBody = document.getElementById('proprietaireInfoBody');

    // Donnees du logement stockees pour auto-fill
    let logementData = null;

    // Charger les champs dynamiques du modele
    templateSelect.addEventListener('change', function() {
        const templateId = this.value;
        if (!templateId) {
            dynamicFields.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-hand-pointer fa-3x mb-3"></i><p>Selectionnez un modele de contrat pour afficher les champs a remplir</p></div>';
            submitSection.style.display = 'none';
            return;
        }

        dynamicFields.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

        fetch('get_template_fields.php?id=' + templateId)
            .then(r => r.text())
            .then(html => {
                if (html.trim()) {
                    dynamicFields.innerHTML = html;
                    submitSection.style.display = 'block';
                    // Re-appliquer l'auto-fill si un logement est deja selectionne
                    if (logementData) {
                        applyAutoFill(logementData);
                    }
                } else {
                    dynamicFields.innerHTML = '<div class="alert alert-warning">Aucun champ dynamique pour ce modele</div>';
                    submitSection.style.display = 'block';
                }
            })
            .catch(err => {
                dynamicFields.innerHTML = '<div class="alert alert-danger">Erreur de chargement: ' + err.message + '</div>';
            });
    });

    // Charger les donnees du logement
    logementSelect.addEventListener('change', function() {
        const logementId = this.value;
        if (!logementId) {
            logementInfoCard.style.display = 'none';
            proprietaireInfoCard.style.display = 'none';
            logementData = null;
            return;
        }

        fetch('get_logement_infos.php?logement_id=' + logementId)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alert('Erreur: ' + data.error);
                    return;
                }

                logementData = data;

                // Afficher les infos logement
                logementInfoCard.style.display = 'block';
                logementInfoBody.innerHTML = buildInfoList([
                    {label: 'Nom', value: data.nom_du_logement},
                    {label: 'Adresse', value: data.adresse},
                    {label: 'Ville', value: data.ville},
                    {label: 'Type', value: data.type_logement},
                    {label: 'Capacite', value: data.capacite ? data.capacite + ' pers.' : ''},
                ]);

                // Afficher les infos proprietaire
                if (data.proprietaire_nom || data.proprietaire_prenom) {
                    proprietaireInfoCard.style.display = 'block';
                    proprietaireInfoBody.innerHTML = buildInfoList([
                        {label: 'Nom', value: (data.proprietaire_prenom || '') + ' ' + (data.proprietaire_nom || '')},
                        {label: 'Email', value: data.proprietaire_email},
                        {label: 'Tel', value: data.proprietaire_telephone},
                        {label: 'Adresse', value: data.proprietaire_adresse},
                    ]);
                } else {
                    proprietaireInfoCard.style.display = 'none';
                }

                // Auto-remplir les champs du formulaire
                applyAutoFill(data);
            })
            .catch(err => {
                console.error('Erreur chargement logement:', err);
            });
    });

    function applyAutoFill(data) {
        // Mapping des champs API vers les champs du formulaire
        const mapping = {
            'nom_du_logement': ['nom_du_logement', 'logement_nom', 'nom_logement'],
            'adresse': ['adresse', 'adresse_logement'],
            'ville': ['ville', 'ville_logement'],
            'type_logement': ['type_logement', 'type'],
            'capacite': ['capacite'],
            'proprietaire_nom': ['proprietaire_nom', 'nom_proprietaire'],
            'proprietaire_prenom': ['proprietaire_prenom', 'prenom_proprietaire'],
            'proprietaire_email': ['proprietaire_email', 'email_proprietaire'],
            'proprietaire_telephone': ['proprietaire_telephone', 'telephone_proprietaire', 'tel_proprietaire'],
            'proprietaire_adresse': ['proprietaire_adresse', 'adresse_proprietaire'],
        };

        // Remplir par ID direct
        for (const key in data) {
            const field = document.getElementById(key);
            if (field && data[key]) {
                field.value = data[key];
            }
        }

        // Remplir par mapping
        for (const [dataKey, fieldNames] of Object.entries(mapping)) {
            if (data[dataKey]) {
                fieldNames.forEach(name => {
                    const field = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
                    if (field && !field.value) {
                        field.value = data[dataKey];
                    }
                });
            }
        }

        // Date du contrat
        const dateField = document.getElementById('date_contrat');
        if (dateField) {
            const dateDisplay = document.querySelector('[name="date_contrat_display"]') || document.getElementById('date_contrat_display');
            if (dateDisplay && !dateDisplay.value) {
                const d = new Date(dateField.value);
                dateDisplay.value = d.toLocaleDateString('fr-FR');
            }
        }
    }

    function buildInfoList(items) {
        let html = '<ul class="list-unstyled mb-0">';
        items.forEach(item => {
            if (item.value && item.value.trim()) {
                html += '<li class="mb-1"><small class="text-muted">' + item.label + ':</small> <strong>' + escapeHtml(item.value.trim()) + '</strong></li>';
            }
        });
        html += '</ul>';
        return html;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
