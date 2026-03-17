<?php
/**
 * Etape 6 : Recap + validation finale
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request) { header('Location: index.php'); exit; }

$packs = onboarding_get_packs();
$packInfo = $packs[$request['pack'] ?? 'autonome'] ?? $packs['autonome'];
$equipements = json_decode($request['equipements'] ?? '{}', true) ?: [];
$nbEquip = count(array_filter($equipements));

// Estimation
$commission = (float)($request['commission_base'] ?? 10);
$prix = (float)($request['prix_souhaite'] ?? 80);
$estimation = onboarding_estimate_revenue($prix, $commission, 70);

onboarding_header(6, 'Recapitulatif', $request);
?>

<form id="step6Form" class="wizard-card">
    <h2><i class="fas fa-clipboard-check text-primary"></i> Recapitulatif</h2>
    <p class="subtitle">Verifiez les informations avant de valider votre inscription</p>

    <style>
        .recap-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .recap-section h6 {
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recap-section h6 a { font-size: 0.8rem; }
        .recap-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 0.9rem; }
        .recap-label { color: #6c757d; }
        .recap-value { font-weight: 600; text-align: right; }

        .pack-recap {
            background: linear-gradient(135deg, #f0f7ff, #e8f5e9);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            text-align: center;
        }
        .pack-recap .commission-big { font-size: 2.5rem; font-weight: 800; }

        .revenue-recap {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>

    <!-- Bien -->
    <div class="recap-section">
        <h6>
            <span><i class="fas fa-home text-success me-2"></i> Le bien</span>
            <a href="etape-1-bien.php?token=<?= urlencode($token) ?>">Modifier</a>
        </h6>
        <div class="recap-row">
            <span class="recap-label">Adresse</span>
            <span class="recap-value"><?= htmlspecialchars(implode(', ', array_filter([$request['adresse'], $request['code_postal'], $request['ville']]))) ?></span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Type</span>
            <span class="recap-value"><?= htmlspecialchars($request['typologie'] ?? '—') ?> · <?= (int)($request['superficie'] ?? 0) ?> m2</span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Couchages</span>
            <span class="recap-value"><?= (int)($request['nb_couchages'] ?? 0) ?> personnes</span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Equipements</span>
            <span class="recap-value"><?= $nbEquip ?> selectionne(s)</span>
        </div>
    </div>

    <!-- Proprietaire -->
    <div class="recap-section">
        <h6>
            <span><i class="fas fa-user text-primary me-2"></i> Proprietaire</span>
            <a href="etape-2-profil.php?token=<?= urlencode($token) ?>">Modifier</a>
        </h6>
        <div class="recap-row">
            <span class="recap-label">Nom</span>
            <span class="recap-value"><?= htmlspecialchars(trim(($request['prenom'] ?? '') . ' ' . ($request['nom'] ?? ''))) ?></span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Email</span>
            <span class="recap-value"><?= htmlspecialchars($request['email'] ?? '') ?></span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Telephone</span>
            <span class="recap-value"><?= htmlspecialchars($request['telephone'] ?? '') ?></span>
        </div>
        <?php if (!empty($request['societe'])): ?>
        <div class="recap-row">
            <span class="recap-label">Societe</span>
            <span class="recap-value"><?= htmlspecialchars($request['societe']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pack -->
    <div class="pack-recap">
        <i class="fas <?= $packInfo['icon'] ?> fa-2x mb-2" style="color: <?= $packInfo['color'] ?>;"></i>
        <h5 style="font-weight: 700;">Pack <?= $packInfo['label'] ?></h5>
        <div class="commission-big" style="color: <?= $packInfo['color'] ?>;"><?= $commission ?>%</div>
        <p class="text-muted mb-0"><?= $packInfo['slogan'] ?></p>
        <a href="etape-4-pack.php?token=<?= urlencode($token) ?>" style="font-size: 0.8rem;">Changer de formule</a>
    </div>

    <!-- Revenus -->
    <div class="revenue-recap">
        <h6 style="opacity: 0.8;"><i class="fas fa-chart-line"></i> Estimation de revenus</h6>
        <div class="row">
            <div class="col-4">
                <div style="font-size: 1.5rem; font-weight: 800;"><?= number_format($estimation['revenu_brut'], 0, ',', ' ') ?> EUR</div>
                <small style="opacity: 0.7;">Brut/mois</small>
            </div>
            <div class="col-4">
                <div style="font-size: 1.5rem; font-weight: 800; color: #ffc107;">-<?= number_format($estimation['commission'], 0, ',', ' ') ?> EUR</div>
                <small style="opacity: 0.7;">Commission</small>
            </div>
            <div class="col-4">
                <div style="font-size: 1.5rem; font-weight: 800; color: #28a745;"><?= number_format($estimation['revenu_net'], 0, ',', ' ') ?> EUR</div>
                <small style="opacity: 0.7;">Net/mois</small>
            </div>
        </div>
        <p style="font-size: 0.75rem; opacity: 0.6; margin-top: 10px;">
            Base : <?= number_format($prix, 0, ',', ' ') ?> EUR/nuit · 70% occupation ·
            soit ~<?= number_format($estimation['revenu_annuel'], 0, ',', ' ') ?> EUR/an net
        </p>
    </div>

    <!-- Parrainage -->
    <?php if (!empty($request['code_parrain'])): ?>
    <div class="alert alert-success" style="border-radius: 10px;">
        <i class="fas fa-gift me-2"></i>
        <strong>Code parrainage :</strong> <?= htmlspecialchars($request['code_parrain']) ?>
        — Seance photo professionnelle offerte !
    </div>
    <?php endif; ?>

    <!-- Conditions -->
    <div class="mt-4 p-3 border rounded" style="border-radius: 10px !important;">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="conditions" required>
            <label class="form-check-label" for="conditions" style="font-size: 0.85rem;">
                J'accepte les <a href="#" target="_blank">conditions generales</a> de FrenchyConciergerie
                et le mandat de gestion a <?= $commission ?>% de commission *
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rgpd" required>
            <label class="form-check-label" for="rgpd" style="font-size: 0.85rem;">
                J'accepte le traitement de mes donnees personnelles conformement a la
                <a href="#" target="_blank">politique de confidentialite</a> (RGPD) *
            </label>
        </div>
    </div>

    <?php onboarding_footer(6, $token); ?>
</form>

<script>
document.getElementById('step6Form').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!document.getElementById('conditions').checked || !document.getElementById('rgpd').checked) {
        alert('Veuillez accepter les conditions et la politique de confidentialite.');
        return;
    }

    const btn = document.getElementById('nextBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finalisation en cours...';

    const data = {
        conditions_acceptees: 1,
        rgpd_accepte: 1,
        finalize: true,
    };

    try {
        const result = await saveStep(6, data);
        if (result.success) {
            window.location.href = 'success.php?token=' + ONBOARDING_TOKEN;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Valider mon inscription';
            alert(result.error || 'Erreur lors de la finalisation');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Valider mon inscription';
        alert('Erreur réseau. Veuillez reessayer.');
    }
});
</script>
