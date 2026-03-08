<?php
/**
 * Creer un contrat — Systeme unifie (conciergerie + location)
 * Bootstrap 5, auto-fill depuis logement + proprietaire
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/contract_config.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'user'])) {
    header("Location: ../error.php?message=" . urlencode('Acces reserve au personnel.'));
    exit;
}

$type = detectContractType();
$config = getContractConfig($type);

// Recuperer les modeles de contrat
$templates = [];
try {
    $stmt = $conn->query("SELECT id, title FROM {$config['table_templates']} ORDER BY title");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('create_contract: ' . $e->getMessage()); }

// Recuperer les logements actifs
$logements = [];
try {
    $stmt = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('create_contract: ' . $e->getMessage()); }
?>

<div class="container-fluid mt-4">
    <?= renderContractTypeTabs($type, 'create_contract.php') ?>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas <?= $config['icon'] ?> text-<?= $config['color'] ?>"></i> Creer un contrat de <?= strtolower($config['label']) ?></h2>
            <p class="text-muted">Selectionnez un modele et un logement pour generer un contrat pre-rempli</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= $config['page_templates'] ?>" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt"></i> Modeles
            </a>
            <?php if ($type === 'location'): ?>
            <a href="location_logement_details.php" class="btn btn-outline-info">
                <i class="fas fa-home"></i> Details logements
            </a>
            <?php endif; ?>
            <a href="<?= $config['page_list'] ?>" class="btn btn-outline-dark">
                <i class="fas fa-history"></i> Historique
            </a>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Aucun modele de contrat de <?= strtolower($config['label']) ?> disponible.
            <a href="<?= $config['page_create_template'] ?>" class="btn btn-sm btn-success ms-2">
                <i class="fas fa-plus"></i> Creer un modele
            </a>
        </div>
    <?php endif; ?>

    <form id="contractForm" action="generate_contract.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php echoCsrfField(); ?>
        <input type="hidden" name="contract_type" value="<?= $type ?>">

        <div class="row">
            <!-- Colonne gauche : selection -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-<?= $config['color'] ?> <?= $config['color'] === 'warning' ? 'text-dark' : 'text-white' ?>">
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

                <!-- Info proprietaire (conciergerie) ou details (location) -->
                <div class="card shadow-sm mb-4" id="extraInfoCard" style="display:none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas <?= $type === 'location' ? 'fa-clipboard-list' : 'fa-user-tie' ?>"></i> <?= $type === 'location' ? 'Details location' : 'Proprietaire' ?></h5>
                    </div>
                    <div class="card-body" id="extraInfoBody"></div>
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
                        <button type="submit" class="btn btn-<?= $config['color'] ?> btn-lg <?= $config['color'] === 'warning' ? 'text-dark' : '' ?>">
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
    const contractType = '<?= $type ?>';
    const templateSelect = document.getElementById('template_id');
    const logementSelect = document.getElementById('logement_id');
    const dynamicFields = document.getElementById('dynamicFields');
    const submitSection = document.getElementById('submitSection');
    const logementInfoCard = document.getElementById('logementInfoCard');
    const logementInfoBody = document.getElementById('logementInfoBody');
    const extraInfoCard = document.getElementById('extraInfoCard');
    const extraInfoBody = document.getElementById('extraInfoBody');

    let logementData = null;

    templateSelect.addEventListener('change', function() {
        const templateId = this.value;
        if (!templateId) {
            dynamicFields.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-hand-pointer fa-3x mb-3"></i><p>Selectionnez un modele</p></div>';
            submitSection.style.display = 'none';
            return;
        }

        dynamicFields.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

        const fieldsUrl = contractType === 'location' ? 'get_location_template_fields.php' : 'get_template_fields.php';
        fetch(fieldsUrl + '?id=' + templateId)
            .then(r => r.text())
            .then(html => {
                if (html.trim()) {
                    dynamicFields.innerHTML = html;
                    submitSection.style.display = 'block';
                    if (logementData) applyAutoFill(logementData);
                } else {
                    dynamicFields.innerHTML = '<div class="alert alert-warning">Aucun champ dynamique pour ce modele</div>';
                    submitSection.style.display = 'block';
                }
            })
            .catch(function() {
                dynamicFields.innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>';
            });
    });

    logementSelect.addEventListener('change', function() {
        const logementId = this.value;
        if (!logementId) {
            logementInfoCard.style.display = 'none';
            extraInfoCard.style.display = 'none';
            logementData = null;
            return;
        }

        const infosUrl = contractType === 'location' ? 'get_location_logement_infos.php' : 'get_logement_infos.php';
        fetch(infosUrl + '?logement_id=' + logementId)
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert('Erreur: ' + data.error); return; }
                logementData = data;

                logementInfoCard.style.display = 'block';
                const infoItems = [
                    {label: 'Nom', value: data.nom_du_logement},
                    {label: 'Adresse', value: data.adresse},
                    {label: 'Ville', value: data.ville},
                    {label: 'Type', value: data.type_logement},
                    {label: 'Capacite', value: data.capacite ? data.capacite + ' pers.' : ''},
                ];
                if (contractType === 'location') {
                    infoItems.push({label: 'Surface', value: data.surface_m2 ? data.surface_m2 + ' m2' : (data.m2 ? data.m2 + ' m2' : '')});
                }
                logementInfoBody.innerHTML = buildInfoList(infoItems);

                if (contractType === 'location') {
                    const hasDetails = data.detail_description_logement || data.detail_equipements || data.detail_heure_arrivee;
                    if (hasDetails) {
                        extraInfoCard.style.display = 'block';
                        extraInfoBody.innerHTML = buildInfoList([
                            {label: 'Arrivee', value: data.detail_heure_arrivee},
                            {label: 'Depart', value: data.detail_heure_depart},
                            {label: 'Garantie', value: data.detail_depot_garantie ? data.detail_depot_garantie + ' EUR' : ''},
                            {label: 'Taxe/nuit', value: data.detail_taxe_sejour_par_nuit ? data.detail_taxe_sejour_par_nuit + ' EUR' : ''},
                        ]);
                    } else { extraInfoCard.style.display = 'none'; }
                } else {
                    if (data.proprietaire_nom || data.proprietaire_prenom) {
                        extraInfoCard.style.display = 'block';
                        extraInfoBody.innerHTML = buildInfoList([
                            {label: 'Nom', value: (data.proprietaire_prenom || '') + ' ' + (data.proprietaire_nom || '')},
                            {label: 'Societe', value: data.proprietaire_societe},
                            {label: 'Email', value: data.proprietaire_email},
                            {label: 'Tel', value: data.proprietaire_telephone},
                            {label: 'Commission', value: data.proprietaire_commission ? data.proprietaire_commission + '%' : ''},
                        ]);
                    } else { extraInfoCard.style.display = 'none'; }
                }
                applyAutoFill(data);
            })
            .catch(function() {});
    });

    function applyAutoFill(data) {
        const computed = Object.assign({}, data);
        computed['proprietaire_fullname'] = ((data.proprietaire_prenom || '') + ' ' + (data.proprietaire_nom || '')).trim();
        computed['description_logement'] = [data.type_logement, data.adresse, data.ville].filter(Boolean).join(', ');
        computed['date_contrat'] = document.getElementById('date_contrat') ? document.getElementById('date_contrat').value : '';

        dynamicFields.querySelectorAll('[data-autofill]').forEach(field => {
            const source = field.getAttribute('data-autofill');
            if (source && computed[source] !== undefined && computed[source] !== null && computed[source] !== '') field.value = computed[source];
        });

        dynamicFields.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.value) return;
            const name = (field.name || field.id || '').toLowerCase();
            if (name && data[name]) field.value = data[name];
        });

        if (contractType === 'location') attachAutoCalculations();
    }

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
            const d1 = new Date(dateArrivee.value), d2 = new Date(dateDepart.value);
            if (d1 && d2 && d2 > d1) {
                const nuits = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
                nombreNuits.value = nuits;
                if (prixTaxeSejour && taxeParNuit > 0) {
                    const nbV = nombreVoyageurs ? (parseInt(nombreVoyageurs.value) || 1) : 1;
                    prixTaxeSejour.value = (taxeParNuit * nuits * nbV).toFixed(2);
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
            if (nuits > 0 && tarif > 0) prixTotal.value = (nuits * tarif + menage + taxe).toFixed(2);
        }

        if (dateArrivee) dateArrivee.addEventListener('change', calcNuits);
        if (dateDepart) dateDepart.addEventListener('change', calcNuits);
        if (prixNuit) prixNuit.addEventListener('input', calcTotal);
        if (prixMenage) prixMenage.addEventListener('input', calcTotal);
        if (prixTaxeSejour) prixTaxeSejour.addEventListener('input', calcTotal);
        if (nombreNuits) nombreNuits.addEventListener('input', calcTotal);
        if (nombreVoyageurs) nombreVoyageurs.addEventListener('input', calcNuits);
        if (dateArrivee && dateArrivee.value && dateDepart && dateDepart.value) calcNuits();
    }

    function buildInfoList(items) {
        let html = '<ul class="list-unstyled mb-0">';
        items.forEach(item => {
            if (item.value && String(item.value).trim())
                html += '<li class="mb-1"><small class="text-muted">' + item.label + ':</small> <strong>' + escapeHtml(String(item.value).trim()) + '</strong></li>';
        });
        return html + '</ul>';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
