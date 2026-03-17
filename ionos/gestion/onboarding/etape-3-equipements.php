<?php
/**
 * Etape 3 : Equipements — Checklist visuelle
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request) { header('Location: index.php'); exit; }

$categories = onboarding_get_equipements_checklist();
$existing = json_decode($request['equipements'] ?? '{}', true) ?: [];

onboarding_header(3, 'Equipements', $request);
?>

<form id="step3Form" class="wizard-card">
    <h2><i class="fas fa-check-square text-success"></i> Equipements de votre bien</h2>
    <p class="subtitle">Cochez ce qui est disponible — cela servira pour l'annonce et le guide d'accueil</p>

    <style>
        .equip-category { margin-bottom: 25px; }
        .equip-category h5 { font-weight: 700; margin-bottom: 12px; color: #343a40; }
        .equip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
        .equip-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .equip-item:hover { border-color: #007bff; background: #f0f7ff; }
        .equip-item.checked {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .equip-item input { display: none; }
        .equip-item .equip-check {
            width: 22px;
            height: 22px;
            border: 2px solid #ced4da;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .equip-item.checked .equip-check {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        .equip-item .equip-label { font-size: 0.85rem; }

        /* Compteur */
        .equip-counter {
            position: sticky;
            top: 0;
            background: white;
            padding: 10px 0;
            z-index: 2;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
    </style>

    <div class="equip-counter">
        <span class="badge bg-success" id="equipCount" style="font-size: 0.9rem;">0 equipement(s) selectionne(s)</span>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="toggleAll(true)">Tout cocher</button>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="toggleAll(false)">Tout decocher</button>
    </div>

    <?php foreach ($categories as $catKey => $cat): ?>
    <div class="equip-category">
        <h5><i class="fas <?= $cat['icon'] ?> me-2 text-muted"></i> <?= $cat['label'] ?></h5>
        <div class="equip-grid">
            <?php foreach ($cat['items'] as $itemKey => $itemLabel): ?>
            <label class="equip-item <?= !empty($existing[$itemKey]) ? 'checked' : '' ?>">
                <input type="checkbox" name="equipements[<?= $itemKey ?>]" value="1"
                    <?= !empty($existing[$itemKey]) ? 'checked' : '' ?>>
                <div class="equip-check">
                    <i class="fas fa-check" style="font-size: 0.7rem;"></i>
                </div>
                <span class="equip-label"><?= $itemLabel ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Description libre -->
    <div class="mt-4">
        <label for="description_bien" class="form-label fw-bold">
            <i class="fas fa-pencil-alt"></i> Description libre (optionnel)
        </label>
        <textarea class="form-control" id="description_bien" name="description_bien" rows="3"
                  placeholder="Decrivez votre bien en quelques mots : charme, vue, particularites..."><?= htmlspecialchars($request['description_bien'] ?? '') ?></textarea>
        <small class="text-muted">Servira de base pour la description de votre annonce</small>
    </div>

    <?php onboarding_footer(3, $token); ?>
</form>

<script>
// Toggle checkbox visual
document.querySelectorAll('.equip-item').forEach(item => {
    item.addEventListener('click', (e) => {
        if (e.target.tagName === 'INPUT') return; // Let native checkbox handle it
        const cb = item.querySelector('input[type=checkbox]');
        cb.checked = !cb.checked;
        item.classList.toggle('checked', cb.checked);
        updateCount();
    });
    // Sync initial state
    const cb = item.querySelector('input[type=checkbox]');
    cb.addEventListener('change', () => {
        item.classList.toggle('checked', cb.checked);
        updateCount();
    });
});

function updateCount() {
    const checked = document.querySelectorAll('.equip-item input:checked').length;
    document.getElementById('equipCount').textContent = checked + ' equipement(s) selectionne(s)';
}
updateCount();

function toggleAll(state) {
    document.querySelectorAll('.equip-item').forEach(item => {
        const cb = item.querySelector('input[type=checkbox]');
        cb.checked = state;
        item.classList.toggle('checked', state);
    });
    updateCount();
}

// Form submit
document.getElementById('step3Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const equipements = {};
    document.querySelectorAll('.equip-item input[type=checkbox]').forEach(cb => {
        const name = cb.name.match(/\[(.+)\]/)?.[1];
        if (name) equipements[name] = cb.checked;
    });

    const data = {
        equipements: equipements,
        description_bien: document.getElementById('description_bien').value,
    };

    const result = await saveStep(3, data);
    if (result.success) {
        window.location.href = 'etape-4-pack.php?token=' + ONBOARDING_TOKEN;
    } else {
        btn.disabled = false;
        btn.innerHTML = 'Continuer <i class="fas fa-arrow-right"></i>';
        alert(result.error || 'Erreur');
    }
});
</script>
