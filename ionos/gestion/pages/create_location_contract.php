<?php
/**
 * Creer un contrat de location pour une reservation directe
 * Selectionner un modele + logement, remplir les infos voyageur et reservation
 */
include '../config.php';
include '../pages/menu.php';

// Tables requises : voir db/install_tables.php

// Recuperer les modeles
$templates = [];
try {
    $stmt = $conn->query("SELECT id, title FROM location_contract_templates ORDER BY title");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('create_location_contract: ' . $e->getMessage()); }

// Recuperer les logements actifs
$logements = [];
try {
    $stmt = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('create_location_contract: ' . $e->getMessage()); }
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-file-signature text-warning"></i> Creer un contrat de location</h2>
            <p class="text-muted">Selectionnez un modele et un logement, remplissez les infos du voyageur et de la reservation</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_location_templates.php" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt"></i> Modeles
            </a>
            <a href="location_logement_details.php" class="btn btn-outline-info">
                <i class="fas fa-home"></i> Details logements
            </a>
            <a href="location_contrats_generes.php" class="btn btn-outline-dark">
                <i class="fas fa-history"></i> Historique
            </a>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Aucun modele de contrat de location disponible.
            <a href="create_location_template.php" class="btn btn-sm btn-success ms-2">
                <i class="fas fa-plus"></i> Creer un modele
            </a>
        </div>
    <?php endif; ?>

    <form id="locationContractForm" action="generate_location_contract.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php echoCsrfField(); ?>

        <div class="row">
            <!-- Colonne gauche : configuration -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
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
                    <div class="card-body" id="logementInfoBody"></div>
                </div>

                <!-- Details personnalises -->
                <div class="card shadow-sm mb-4" id="detailsInfoCard" style="display:none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Details location</h5>
                    </div>
                    <div class="card-body" id="detailsInfoBody"></div>
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
                        <button type="submit" class="btn btn-warning btn-lg text-dark">
                            <i class="fas fa-file-export"></i> Generer le contrat de location
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
    const detailsInfoCard = document.getElementById('detailsInfoCard');
    const detailsInfoBody = document.getElementById('detailsInfoBody');

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

        fetch('get_location_template_fields.php?id=' + templateId)
            .then(r => r.text())
            .then(html => {
                if (html.trim()) {
                    dynamicFields.innerHTML = html;
                    submitSection.style.display = 'block';
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
            detailsInfoCard.style.display = 'none';
            logementData = null;
            return;
        }

        fetch('get_location_logement_infos.php?logement_id=' + logementId)
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
                    {label: 'Surface', value: data.surface_m2 ? data.surface_m2 + ' m2' : (data.m2 ? data.m2 + ' m2' : '')},
                    {label: 'Chambres', value: data.nombre_chambres},
                    {label: 'Salles de bain', value: data.nombre_salles_bain},
                ]);

                // Afficher les details personnalises
                const hasDetails = data.detail_description_logement || data.detail_equipements || data.detail_regles_maison || data.detail_heure_arrivee;
                if (hasDetails) {
                    detailsInfoCard.style.display = 'block';
                    detailsInfoBody.innerHTML = buildInfoList([
                        {label: 'Arrivee', value: data.detail_heure_arrivee},
                        {label: 'Depart', value: data.detail_heure_depart},
                        {label: 'Garantie', value: data.detail_depot_garantie ? data.detail_depot_garantie + ' EUR' : ''},
                        {label: 'Taxe sejour/nuit', value: data.detail_taxe_sejour_par_nuit ? data.detail_taxe_sejour_par_nuit + ' EUR' : ''},
                    ]);
                    if (data.detail_equipements) {
                        const equipPreview = data.detail_equipements.substring(0, 150).replace(/\n/g, ', ');
                        detailsInfoBody.innerHTML += '<div class="mt-2"><small class="text-muted">Equipements:</small><br><small>' + escapeHtml(equipPreview) + '...</small></div>';
                    }
                    if (data.detail_regles_maison) {
                        detailsInfoBody.innerHTML += '<div class="mt-1"><small class="text-muted">Regles:</small><br><small>' + escapeHtml(data.detail_regles_maison) + '</small></div>';
                    }
                } else {
                    detailsInfoCard.style.display = 'none';
                }

                // Auto-remplir les champs
                applyAutoFill(data);
            })
            .catch(err => {
                console.error('Erreur chargement logement:', err);
            });
    });

    function applyAutoFill(data) {
        const computed = Object.assign({}, data);
        computed['date_contrat'] = document.getElementById('date_contrat') ? document.getElementById('date_contrat').value : '';

        // Parcourir les champs avec data-autofill
        const fields = dynamicFields.querySelectorAll('[data-autofill]');
        fields.forEach(field => {
            const source = field.getAttribute('data-autofill');
            if (!source) return;
            const value = computed[source];
            if (value !== undefined && value !== null && value !== '') {
                field.value = value;
            }
        });

        // Fallback par nom de champ
        dynamicFields.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.value) return;
            const name = (field.name || field.id || '').toLowerCase();
            if (name && data[name]) {
                field.value = data[name];
            }
        });

        // Attacher les calculs auto apres le remplissage
        attachAutoCalculations();
    }

    // Calcul automatique : nombre de nuits et prix total
    function attachAutoCalculations() {
        const dateArrivee = dynamicFields.querySelector('[name="date_arrivee"]');
        const dateDepart = dynamicFields.querySelector('[name="date_depart"]');
        const nombreNuits = dynamicFields.querySelector('[name="nombre_nuits"]');
        const prixNuit = dynamicFields.querySelector('[name="prix_nuit"]');
        const prixMenage = dynamicFields.querySelector('[name="prix_menage"]');
        const prixTaxeSejour = dynamicFields.querySelector('[name="prix_taxe_sejour"]');
        const prixTotal = dynamicFields.querySelector('[name="prix_total"]');
        const nombreVoyageurs = dynamicFields.querySelector('[name="nombre_voyageurs"]');
        const taxeParNuit = logementData ? parseFloat(logementData.detail_taxe_sejour_par_nuit) || 0 : 0;

        function calcNuits() {
            if (!dateArrivee || !dateDepart || !nombreNuits) return;
            const d1 = new Date(dateArrivee.value);
            const d2 = new Date(dateDepart.value);
            if (d1 && d2 && d2 > d1) {
                const nuits = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
                nombreNuits.value = nuits;

                // Auto-calcul taxe de sejour si on a le tarif par nuit
                if (prixTaxeSejour && taxeParNuit > 0) {
                    const nbVoyageurs = nombreVoyageurs ? (parseInt(nombreVoyageurs.value) || 1) : 1;
                    prixTaxeSejour.value = (taxeParNuit * nuits * nbVoyageurs).toFixed(2);
                }

                calcTotal();
            }
        }

        function calcTotal() {
            if (!prixTotal) return;
            const nuits = nombreNuits ? (parseFloat(nombreNuits.value) || 0) : 0;
            const tarif = prixNuit ? (parseFloat(prixNuit.value) || 0) : 0;
            const menage = prixMenage ? (parseFloat(prixMenage.value) || 0) : 0;
            const taxe = prixTaxeSejour ? (parseFloat(prixTaxeSejour.value) || 0) : 0;

            if (nuits > 0 && tarif > 0) {
                prixTotal.value = (nuits * tarif + menage + taxe).toFixed(2);
            }
        }

        // Ecouter les changements
        if (dateArrivee) dateArrivee.addEventListener('change', calcNuits);
        if (dateDepart) dateDepart.addEventListener('change', calcNuits);
        if (prixNuit) prixNuit.addEventListener('input', calcTotal);
        if (prixMenage) prixMenage.addEventListener('input', calcTotal);
        if (prixTaxeSejour) prixTaxeSejour.addEventListener('input', calcTotal);
        if (nombreNuits) nombreNuits.addEventListener('input', calcTotal);
        if (nombreVoyageurs) nombreVoyageurs.addEventListener('input', calcNuits);

        // Calculer si les dates sont deja remplies
        if (dateArrivee && dateArrivee.value && dateDepart && dateDepart.value) {
            calcNuits();
        }
    }

    function buildInfoList(items) {
        let html = '<ul class="list-unstyled mb-0">';
        items.forEach(item => {
            if (item.value && String(item.value).trim()) {
                html += '<li class="mb-1"><small class="text-muted">' + item.label + ':</small> <strong>' + escapeHtml(String(item.value).trim()) + '</strong></li>';
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
