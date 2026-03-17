<?php
/**
 * Etape 1 : Informations sur le bien
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
$request = onboarding_get_or_create($conn, $token);
$token = $request['token'];

// Sauvegarder le token en cookie pour reprise
setcookie('frenchy_onboarding', $token, time() + 86400 * 30, '/');

// Sauvegarder le code parrainage si present
if (!empty($_GET['parrain']) && empty($request['code_parrain'])) {
    $conn->prepare("UPDATE onboarding_requests SET code_parrain = ? WHERE token = ?")
        ->execute([$_GET['parrain'], $token]);
}

onboarding_header(1, 'Votre bien', $request);
?>

<form id="step1Form" class="wizard-card">
    <h2><i class="fas fa-home text-success"></i> Parlez-nous de votre bien</h2>
    <p class="subtitle">Ces informations serviront a creer votre annonce et votre site vitrine</p>

    <!-- Adresse -->
    <div class="mb-3">
        <label for="adresse" class="form-label fw-bold">Adresse du bien *</label>
        <input type="text" class="form-control form-control-lg" id="adresse" name="adresse"
               placeholder="12 rue de la Paix, Paris"
               value="<?= htmlspecialchars($request['adresse'] ?? '') ?>" required>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="complement_adresse" class="form-label">Complement</label>
            <input type="text" class="form-control" id="complement_adresse" name="complement_adresse"
                   placeholder="Batiment, etage, code..."
                   value="<?= htmlspecialchars($request['complement_adresse'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label for="code_postal" class="form-label fw-bold">Code postal *</label>
            <input type="text" class="form-control" id="code_postal" name="code_postal"
                   placeholder="75001" maxlength="5"
                   value="<?= htmlspecialchars($request['code_postal'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label for="ville" class="form-label fw-bold">Ville *</label>
            <input type="text" class="form-control" id="ville" name="ville"
                   placeholder="Paris"
                   value="<?= htmlspecialchars($request['ville'] ?? '') ?>" required>
        </div>
    </div>

    <!-- Typologie -->
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="typologie" class="form-label fw-bold">Type de bien *</label>
            <select class="form-select" id="typologie" name="typologie" required>
                <option value="">-- Choisir --</option>
                <?php foreach (['studio'=>'Studio','T1'=>'T1','T2'=>'T2','T3'=>'T3','T4'=>'T4','T5+'=>'T5+','maison'=>'Maison','villa'=>'Villa'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($request['typologie'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="superficie" class="form-label fw-bold">Superficie (m2) *</label>
            <input type="number" class="form-control" id="superficie" name="superficie"
                   min="10" max="500" placeholder="45"
                   value="<?= htmlspecialchars($request['superficie'] ?? '') ?>" required>
        </div>
        <div class="col-md-4">
            <label for="nb_couchages" class="form-label fw-bold">Couchages *</label>
            <input type="number" class="form-control" id="nb_couchages" name="nb_couchages"
                   min="1" max="20" placeholder="4"
                   value="<?= htmlspecialchars($request['nb_couchages'] ?? '2') ?>" required>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="nb_pieces" class="form-label">Nombre de pieces</label>
            <input type="number" class="form-control" id="nb_pieces" name="nb_pieces"
                   min="1" max="20" placeholder="2"
                   value="<?= htmlspecialchars($request['nb_pieces'] ?? '1') ?>">
        </div>
        <div class="col-md-4">
            <label for="etage" class="form-label">Etage</label>
            <input type="text" class="form-control" id="etage" name="etage"
                   placeholder="3eme"
                   value="<?= htmlspecialchars($request['etage'] ?? '') ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ascenseur" name="ascenseur" value="1"
                    <?= ($request['ascenseur'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ascenseur">Ascenseur</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="parking" name="parking" value="1"
                    <?= ($request['parking'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="parking">Parking</label>
            </div>
        </div>
    </div>

    <!-- Photos -->
    <div class="mb-3">
        <label class="form-label fw-bold"><i class="fas fa-camera"></i> Photos du bien</label>
        <div class="border rounded p-4 text-center" id="photoDropzone" style="border-style: dashed !important; cursor: pointer; background: #f8f9fa;">
            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
            <p class="mb-1">Glissez vos photos ici ou <strong>cliquez pour selectionner</strong></p>
            <small class="text-muted">JPG, PNG — max 10 photos, 5 Mo chacune</small>
            <input type="file" id="photoInput" accept="image/*" multiple style="display: none;">
        </div>
        <div id="photoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
        <small class="text-muted">Les photos sont optionnelles maintenant — vous pourrez les ajouter plus tard</small>
    </div>

    <?php onboarding_footer(1, $token); ?>
</form>

<script>
// Photo drag & drop
const dropzone = document.getElementById('photoDropzone');
const fileInput = document.getElementById('photoInput');
const preview = document.getElementById('photoPreview');

dropzone.addEventListener('click', () => fileInput.click());
dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.style.borderColor = '#007bff'; });
dropzone.addEventListener('dragleave', () => { dropzone.style.borderColor = '#dee2e6'; });
dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.style.borderColor = '#dee2e6';
    handleFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => handleFiles(fileInput.files));

function handleFiles(files) {
    Array.from(files).slice(0, 10).forEach(file => {
        if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.style.cssText = 'position:relative; width:80px; height:80px;';
            div.innerHTML = `<img src="${e.target.result}" style="width:80px;height:80px;object-fit:cover;border-radius:6px;">
                <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:-5px;right:-5px;background:#dc3545;color:white;border:none;border-radius:50%;width:20px;height:20px;font-size:0.6rem;cursor:pointer;">&times;</button>`;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// Form submit
document.getElementById('step1Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const data = {
        adresse: document.getElementById('adresse').value,
        complement_adresse: document.getElementById('complement_adresse').value,
        code_postal: document.getElementById('code_postal').value,
        ville: document.getElementById('ville').value,
        typologie: document.getElementById('typologie').value,
        superficie: document.getElementById('superficie').value,
        nb_pieces: document.getElementById('nb_pieces').value,
        nb_couchages: document.getElementById('nb_couchages').value,
        etage: document.getElementById('etage').value,
        ascenseur: document.getElementById('ascenseur').checked ? 1 : 0,
        parking: document.getElementById('parking').checked ? 1 : 0,
    };

    const result = await saveStep(1, data);
    if (result.success) {
        window.location.href = 'etape-2-profil.php?token=' + ONBOARDING_TOKEN;
    } else {
        btn.disabled = false;
        btn.innerHTML = 'Continuer <i class="fas fa-arrow-right"></i>';
        alert(result.error || 'Erreur lors de la sauvegarde');
    }
});
</script>
