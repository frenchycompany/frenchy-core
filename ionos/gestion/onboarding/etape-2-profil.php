<?php
/**
 * Etape 2 : Profil proprietaire
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request) { header('Location: index.php'); exit; }

onboarding_header(2, 'Votre profil', $request);
?>

<form id="step2Form" class="wizard-card">
    <h2><i class="fas fa-user text-primary"></i> Vos coordonnees</h2>
    <p class="subtitle">Pour creer votre espace proprietaire et vos accès</p>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="prenom" class="form-label fw-bold">Prenom *</label>
            <input type="text" class="form-control form-control-lg" id="prenom" name="prenom"
                   placeholder="Jean"
                   value="<?= htmlspecialchars($request['prenom'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label for="nom" class="form-label fw-bold">Nom *</label>
            <input type="text" class="form-control form-control-lg" id="nom" name="nom"
                   placeholder="Dupont"
                   value="<?= htmlspecialchars($request['nom'] ?? '') ?>" required>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="email" class="form-label fw-bold">Email *</label>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="jean.dupont@gmail.com"
                   value="<?= htmlspecialchars($request['email'] ?? '') ?>" required>
            <small class="text-muted">Votre email principal — pour les notifications et l'acces dashboard</small>
        </div>
        <div class="col-md-6">
            <label for="telephone" class="form-label fw-bold">Telephone *</label>
            <input type="tel" class="form-control" id="telephone" name="telephone"
                   placeholder="06 12 34 56 78"
                   value="<?= htmlspecialchars($request['telephone'] ?? '') ?>" required>
        </div>
    </div>

    <!-- Optionnel : societe -->
    <div class="mb-3">
        <a href="#" class="text-decoration-none" onclick="document.getElementById('societeBlock').classList.toggle('d-none'); return false;">
            <i class="fas fa-building"></i> J'ai une societe (optionnel)
        </a>
        <div id="societeBlock" class="<?= empty($request['societe']) ? 'd-none' : '' ?> mt-2">
            <div class="row">
                <div class="col-md-6">
                    <label for="societe" class="form-label">Nom de la societe</label>
                    <input type="text" class="form-control" id="societe" name="societe"
                           value="<?= htmlspecialchars($request['societe'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="siret" class="form-label">SIRET</label>
                    <input type="text" class="form-control" id="siret" name="siret"
                           placeholder="123 456 789 00012" maxlength="17"
                           value="<?= htmlspecialchars($request['siret'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Indicateur email Frenchy -->
    <div class="alert alert-info mt-4" style="border-radius: 10px;">
        <i class="fas fa-envelope-open-text fa-lg me-2"></i>
        <strong>Bonus inclus :</strong> Nous vous creerons une adresse
        <strong id="emailPreview">prenom.nom@frenchyconciergerie.fr</strong>
        pour gerer vos reservations.
    </div>

    <?php onboarding_footer(2, $token); ?>
</form>

<script>
// Live email preview
const prenomInput = document.getElementById('prenom');
const nomInput = document.getElementById('nom');
const emailPreview = document.getElementById('emailPreview');

function updateEmailPreview() {
    const p = (prenomInput.value || 'prenom').toLowerCase().replace(/[^a-z]/g, '');
    const n = (nomInput.value || 'nom').toLowerCase().replace(/[^a-z]/g, '');
    emailPreview.textContent = p + '.' + n + '@frenchyconciergerie.fr';
}
prenomInput.addEventListener('input', updateEmailPreview);
nomInput.addEventListener('input', updateEmailPreview);
updateEmailPreview();

// Form submit
document.getElementById('step2Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const data = {
        prenom: prenomInput.value,
        nom: nomInput.value,
        email: document.getElementById('email').value,
        telephone: document.getElementById('telephone').value,
        societe: document.getElementById('societe').value,
        siret: document.getElementById('siret').value,
    };

    const result = await saveStep(2, data);
    if (result.success) {
        window.location.href = 'etape-3-equipements.php?token=' + ONBOARDING_TOKEN;
    } else {
        btn.disabled = false;
        btn.innerHTML = 'Continuer <i class="fas fa-arrow-right"></i>';
        alert(result.error || 'Erreur lors de la sauvegarde');
    }
});
</script>
