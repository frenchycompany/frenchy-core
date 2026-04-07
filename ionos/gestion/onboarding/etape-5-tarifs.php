<?php
/**
 * Etape 5 : Tarifs & revenus estimes
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request) { header('Location: index.php'); exit; }

$commission = (float)($request['commission_base'] ?? 10);
$pack = $request['pack'] ?? 'autonome';

onboarding_header(5, 'Vos tarifs', $request);
?>

<form id="step5Form" class="wizard-card">
    <h2><i class="fas fa-euro-sign text-success"></i> Definissez vos tarifs</h2>
    <p class="subtitle">Nous optimiserons automatiquement les prix en fonction de la demande</p>

    <div class="row mb-4">
        <div class="col-md-4">
            <label for="prix_souhaite" class="form-label fw-bold">Prix par nuit souhaite *</label>
            <div class="input-group input-group-lg">
                <input type="number" class="form-control" id="prix_souhaite" name="prix_souhaite"
                       min="20" max="1000" step="5" placeholder="80"
                       value="<?= htmlspecialchars($request['prix_souhaite'] ?? '') ?>" required>
                <span class="input-group-text">EUR/nuit</span>
            </div>
        </div>
        <div class="col-md-4">
            <label for="prix_min" class="form-label">Prix minimum</label>
            <div class="input-group">
                <input type="number" class="form-control" id="prix_min" name="prix_min"
                       min="15" max="500" step="5" placeholder="50"
                       value="<?= htmlspecialchars($request['prix_min'] ?? '') ?>">
                <span class="input-group-text">EUR</span>
            </div>
            <small class="text-muted">Plancher en derniere minute</small>
        </div>
        <div class="col-md-4">
            <label for="prix_max" class="form-label">Prix maximum</label>
            <div class="input-group">
                <input type="number" class="form-control" id="prix_max" name="prix_max"
                       min="30" max="2000" step="5" placeholder="150"
                       value="<?= htmlspecialchars($request['prix_max'] ?? '') ?>">
                <span class="input-group-text">EUR</span>
            </div>
            <small class="text-muted">Plafond haute saison</small>
        </div>
    </div>

    <!-- Prix dynamique -->
    <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" id="accepte_prix_dynamique"
               <?= ($request['accepte_prix_dynamique'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label fw-bold" for="accepte_prix_dynamique">
            <i class="fas fa-chart-line text-primary"></i> Activer le prix dynamique (recommande)
        </label>
        <p class="text-muted" style="font-size: 0.8rem; margin-top: 4px;">
            Ajustement automatique selon l'occupation, la saison, les evenements locaux.
            Generalement +15 a +30% de revenus vs prix fixe.
        </p>
    </div>

    <!-- Regles automatiques -->
    <div class="card mb-4" style="border-radius: 10px;">
        <div class="card-body">
            <h6 class="fw-bold"><i class="fas fa-magic text-purple"></i> Regles automatiques activees</h6>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">+20%</span>
                        <span style="font-size: 0.85rem;">Week-ends (ven-sam)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">+30%</span>
                        <span style="font-size: 0.85rem;">Haute saison (juil-aout)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">+25%</span>
                        <span style="font-size: 0.85rem;">Jours feries & ponts</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning text-dark me-2">-10%</span>
                        <span style="font-size: 0.85rem;">Last minute (< 3 jours)</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning text-dark me-2">-15%</span>
                        <span style="font-size: 0.85rem;">Sejour > 7 nuits</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-info me-2">auto</span>
                        <span style="font-size: 0.85rem;">Ajustement occupation cible 80%</span>
                    </div>
                </div>
            </div>
            <small class="text-muted">Ces regles sont configurables dans votre dashboard apres inscription</small>
        </div>
    </div>

    <!-- Simulateur en temps reel -->
    <div class="card" style="border-radius: 10px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white;">
        <div class="card-body">
            <h6 class="fw-bold"><i class="fas fa-calculator"></i> Simulateur de revenus</h6>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label" style="font-size: 0.8rem;">Taux d'occupation</label>
                    <input type="range" class="form-range" id="occSlider" min="30" max="95" value="70">
                    <div class="d-flex justify-content-between" style="font-size: 0.75rem; opacity: 0.7;">
                        <span>30%</span>
                        <span id="occValue" style="font-weight: 700;">70%</span>
                        <span>95%</span>
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <div style="font-size: 0.8rem; opacity: 0.7;">Revenu net mensuel estime</div>
                    <div id="simRevenu" style="font-size: 2.5rem; font-weight: 800; color: #28a745;">— EUR</div>
                    <div style="font-size: 0.75rem; opacity: 0.7;">
                        soit <span id="simAnnuel" style="font-weight: 700;">—</span> EUR/an
                        (commission <?= $commission ?>%)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php onboarding_footer(5, $token); ?>
</form>

<script>
const commission = <?= $commission ?>;
const prixInput = document.getElementById('prix_souhaite');
const occSlider = document.getElementById('occSlider');
const occValue = document.getElementById('occValue');

function updateSimulation() {
    const prix = parseInt(prixInput.value) || 80;
    const occ = parseInt(occSlider.value) || 70;
    occValue.textContent = occ + '%';

    const jours = Math.round(30 * occ / 100);
    const brut = prix * jours;
    const comm = Math.round(brut * commission / 100);
    const net = brut - comm;

    document.getElementById('simRevenu').textContent = net.toLocaleString('fr-FR') + ' EUR';
    document.getElementById('simAnnuel').textContent = (net * 12).toLocaleString('fr-FR');

    // Auto-fill min/max if empty
    if (!document.getElementById('prix_min').value) {
        document.getElementById('prix_min').placeholder = Math.round(prix * 0.6);
    }
    if (!document.getElementById('prix_max').value) {
        document.getElementById('prix_max').placeholder = Math.round(prix * 1.5);
    }
}

prixInput.addEventListener('input', updateSimulation);
occSlider.addEventListener('input', updateSimulation);
updateSimulation();

// Form submit
document.getElementById('step5Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const data = {
        prix_souhaite: prixInput.value,
        prix_min: document.getElementById('prix_min').value || null,
        prix_max: document.getElementById('prix_max').value || null,
        accepte_prix_dynamique: document.getElementById('accepte_prix_dynamique').checked ? 1 : 0,
    };

    const result = await saveStep(5, data);
    if (result.success) {
        window.location.href = 'etape-6-recap.php?token=' + ONBOARDING_TOKEN;
    } else {
        btn.disabled = false;
        btn.innerHTML = 'Continuer <i class="fas fa-arrow-right"></i>';
        alert(result.error || 'Erreur');
    }
});
</script>
