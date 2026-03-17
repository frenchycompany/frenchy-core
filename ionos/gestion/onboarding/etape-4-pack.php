<?php
/**
 * Etape 4 : Choix du pack (10% / 20% / 30%) — Commission configurable
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request) { header('Location: index.php'); exit; }

$packs = onboarding_get_packs();
$currentPack = $request['pack'] ?? 'autonome';

onboarding_header(4, 'Votre formule', $request);
?>

<form id="step4Form" class="wizard-card">
    <h2><i class="fas fa-tags text-warning"></i> Choisissez votre formule</h2>
    <p class="subtitle">Commission configurable — vous pouvez changer a tout moment</p>

    <style>
        .pack-option {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .pack-option:hover { border-color: #6c757d; }
        .pack-option.selected { border-color: #007bff; background: #f0f7ff; box-shadow: 0 0 0 3px rgba(0,123,255,0.15); }
        .pack-option input[type=radio] { display: none; }

        .pack-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .pack-name { font-size: 1.2rem; font-weight: 700; }
        .pack-commission {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
        }
        .pack-commission small { font-size: 0.8rem; font-weight: 400; }
        .pack-desc { font-size: 0.85rem; color: #6c757d; margin-bottom: 12px; }

        .pack-services-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .pack-service-tag {
            background: #e8f5e9;
            color: #28a745;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .pack-vous-gerez {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .popular-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: #007bff;
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        /* Estimation */
        .estimation-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f5e9 100%);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .estimation-number { font-size: 2rem; font-weight: 800; color: #28a745; }
    </style>

    <?php foreach ($packs as $key => $pack): ?>
    <label class="pack-option <?= $currentPack === $key ? 'selected' : '' ?>" data-pack="<?= $key ?>">
        <input type="radio" name="pack" value="<?= $key ?>" <?= $currentPack === $key ? 'checked' : '' ?>>
        <?php if (!empty($pack['popular'])): ?>
            <div class="popular-badge"><i class="fas fa-star"></i> Populaire</div>
        <?php endif; ?>
        <div class="pack-header">
            <div>
                <div class="pack-name">
                    <i class="fas <?= $pack['icon'] ?>" style="color: <?= $pack['color'] ?>;"></i>
                    <?= $pack['label'] ?>
                </div>
            </div>
            <div class="pack-commission" style="color: <?= $pack['color'] ?>;">
                <?= $pack['commission'] ?>%<small> commission</small>
            </div>
        </div>
        <div class="pack-desc"><?= $pack['slogan'] ?></div>
        <div class="pack-services-list">
            <?php foreach (array_slice($pack['services'], 0, 5) as $s): ?>
                <span class="pack-service-tag"><i class="fas fa-check"></i> <?= $s ?></span>
            <?php endforeach; ?>
            <?php if (count($pack['services']) > 5): ?>
                <span class="pack-service-tag" style="background: #e9ecef; color: #6c757d;">+<?= count($pack['services']) - 5 ?> autres</span>
            <?php endif; ?>
        </div>
        <div class="pack-vous-gerez">
            <strong>Vous gerez :</strong> <?= implode(', ', $pack['vous_gerez']) ?>
        </div>
    </label>
    <?php endforeach; ?>

    <!-- Estimation en temps reel -->
    <div class="estimation-card" id="estimationCard">
        <h5 style="font-weight: 700;"><i class="fas fa-calculator"></i> Estimation de vos revenus</h5>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">
            Basee sur <?= htmlspecialchars($request['typologie'] ?? 'votre bien') ?>
            a <?= htmlspecialchars($request['ville'] ?? 'votre ville') ?>
            (<?= (int)($request['nb_couchages'] ?? 2) ?> couchages)
        </p>
        <div class="row text-center">
            <div class="col-4">
                <div class="estimation-number" id="estRevenuBrut">—</div>
                <small class="text-muted">Revenu brut/mois</small>
            </div>
            <div class="col-4">
                <div class="estimation-number" id="estCommission" style="color: #dc3545;">—</div>
                <small class="text-muted">Commission Frenchy</small>
            </div>
            <div class="col-4">
                <div class="estimation-number" id="estRevenuNet">—</div>
                <small class="text-muted">Dans votre poche</small>
            </div>
        </div>
        <p class="text-center mt-2" style="font-size: 0.75rem; color: #6c757d;">
            Estimation basee sur 70% d'occupation et un prix moyen estime
        </p>
    </div>

    <?php onboarding_footer(4, $token); ?>
</form>

<script>
// Pack selection
document.querySelectorAll('.pack-option').forEach(opt => {
    opt.addEventListener('click', () => {
        document.querySelectorAll('.pack-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        opt.querySelector('input[type=radio]').checked = true;
        updateEstimation();
    });
});

// Estimation revenus
const PRIX_MOYEN_PAR_TYPE = {
    'studio': 55, 'T1': 65, 'T2': 80, 'T3': 100, 'T4': 120, 'T5+': 150, 'maison': 130, 'villa': 180
};
const COMMISSIONS = { 'autonome': 10, 'serenite': 20, 'cle_en_main': 30 };

function updateEstimation() {
    const pack = document.querySelector('input[name=pack]:checked')?.value || 'autonome';
    const comm = COMMISSIONS[pack] || 10;
    const typo = <?= json_encode($request['typologie'] ?? 'T2') ?>;
    const prixNuit = PRIX_MOYEN_PAR_TYPE[typo] || 80;
    const occupation = 0.70;
    const jours = Math.round(30 * occupation);
    const brut = prixNuit * jours;
    const commission = Math.round(brut * comm / 100);
    const net = brut - commission;

    document.getElementById('estRevenuBrut').textContent = brut + ' EUR';
    document.getElementById('estCommission').textContent = '-' + commission + ' EUR';
    document.getElementById('estRevenuNet').textContent = net + ' EUR';
}
updateEstimation();

// Form submit
document.getElementById('step4Form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';

    const pack = document.querySelector('input[name=pack]:checked')?.value || 'autonome';
    const commissions = { 'autonome': 10, 'serenite': 20, 'cle_en_main': 30 };

    const data = {
        pack: pack,
        commission_base: commissions[pack],
    };

    const result = await saveStep(4, data);
    if (result.success) {
        window.location.href = 'etape-5-tarifs.php?token=' + ONBOARDING_TOKEN;
    } else {
        btn.disabled = false;
        btn.innerHTML = 'Continuer <i class="fas fa-arrow-right"></i>';
        alert(result.error || 'Erreur');
    }
});
</script>
