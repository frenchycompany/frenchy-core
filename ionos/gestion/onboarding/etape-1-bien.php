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

    <!-- Annonce existante -->
    <div class="mb-3 mt-4">
        <label class="form-label fw-bold"><i class="fas fa-bullhorn"></i> Avez-vous deja une annonce en ligne ?</label>
        <div class="d-flex gap-3 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="annonce_existante" id="annonce_oui" value="1"
                    <?= ($request['annonce_existante'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="annonce_oui">Oui</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="annonce_existante" id="annonce_non" value="0"
                    <?= !($request['annonce_existante'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="annonce_non">Non, c'est mon premier lancement</label>
            </div>
        </div>
    </div>

    <?php $plateformes = json_decode($request['annonce_plateformes'] ?? '[]', true) ?: []; ?>
    <div id="annonceDetails" style="display: <?= ($request['annonce_existante'] ?? 0) ? 'block' : 'none' ?>;">
        <div class="mb-3">
            <label class="form-label fw-bold">Sur quelle(s) plateforme(s) ?</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['airbnb' => 'Airbnb', 'booking' => 'Booking.com', 'abritel' => 'Abritel/VRBO', 'leboncoin' => 'Leboncoin', 'autre' => 'Autre'] as $pVal => $pLabel): ?>
                <div class="form-check">
                    <input class="form-check-input plateforme-check" type="checkbox" id="plat_<?= $pVal ?>" value="<?= $pVal ?>"
                        <?= in_array($pVal, $plateformes) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="plat_<?= $pVal ?>"><?= $pLabel ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3" id="urlAirbnbBlock" style="display: <?= in_array('airbnb', $plateformes) ? 'block' : 'none' ?>;">
            <label for="annonce_url_airbnb" class="form-label">Lien de votre annonce Airbnb</label>
            <input type="url" class="form-control" id="annonce_url_airbnb" name="annonce_url_airbnb"
                   placeholder="https://www.airbnb.fr/rooms/..."
                   value="<?= htmlspecialchars($request['annonce_url_airbnb'] ?? '') ?>">
        </div>
        <div class="mb-3" id="urlBookingBlock" style="display: <?= in_array('booking', $plateformes) ? 'block' : 'none' ?>;">
            <label for="annonce_url_booking" class="form-label">Lien de votre annonce Booking</label>
            <input type="url" class="form-control" id="annonce_url_booking" name="annonce_url_booking"
                   placeholder="https://www.booking.com/hotel/..."
                   value="<?= htmlspecialchars($request['annonce_url_booking'] ?? '') ?>">
        </div>
        <div class="mb-3" id="urlAutreBlock" style="display: <?= (in_array('abritel', $plateformes) || in_array('leboncoin', $plateformes) || in_array('autre', $plateformes)) ? 'block' : 'none' ?>;">
            <label for="annonce_url_autre" class="form-label">Lien autre plateforme</label>
            <input type="url" class="form-control" id="annonce_url_autre" name="annonce_url_autre"
                   placeholder="https://..."
                   value="<?= htmlspecialchars($request['annonce_url_autre'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="experience_location" class="form-label fw-bold">Depuis combien de temps louez-vous ?</label>
            <select class="form-select" id="experience_location" name="experience_location">
                <option value="">-- Choisir --</option>
                <?php foreach (['jamais' => 'Jamais encore', 'moins_1an' => 'Moins d\'un an', '1_3ans' => '1 a 3 ans', '3_5ans' => '3 a 5 ans', 'plus_5ans' => 'Plus de 5 ans'] as $eVal => $eLabel): ?>
                    <option value="<?= $eVal ?>" <?= ($request['experience_location'] ?? '') === $eVal ? 'selected' : '' ?>><?= $eLabel ?></option>
                <?php endforeach; ?>
            </select>
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

// Annonce existante — toggle
document.querySelectorAll('input[name="annonce_existante"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('annonceDetails').style.display = r.value === '1' && r.checked ? 'block' : 'none';
    });
});
// Plateformes — toggle URL fields
document.querySelectorAll('.plateforme-check').forEach(cb => {
    cb.addEventListener('change', () => {
        const checked = [...document.querySelectorAll('.plateforme-check:checked')].map(c => c.value);
        document.getElementById('urlAirbnbBlock').style.display = checked.includes('airbnb') ? 'block' : 'none';
        document.getElementById('urlBookingBlock').style.display = checked.includes('booking') ? 'block' : 'none';
        document.getElementById('urlAutreBlock').style.display = (checked.includes('abritel') || checked.includes('leboncoin') || checked.includes('autre')) ? 'block' : 'none';
    });
});

// Form submit
document.getElementById('step1Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const annonceExistante = document.getElementById('annonce_oui').checked ? 1 : 0;
    const plateformes = [...document.querySelectorAll('.plateforme-check:checked')].map(c => c.value);

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
        annonce_existante: annonceExistante,
        annonce_plateformes: annonceExistante ? plateformes : [],
        annonce_url_airbnb: annonceExistante ? (document.getElementById('annonce_url_airbnb').value || '') : '',
        annonce_url_booking: annonceExistante ? (document.getElementById('annonce_url_booking').value || '') : '',
        annonce_url_autre: annonceExistante ? (document.getElementById('annonce_url_autre').value || '') : '',
        experience_location: document.getElementById('experience_location').value,
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
