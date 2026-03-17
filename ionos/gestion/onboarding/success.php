<?php
/**
 * Page de succes — Onboarding termine
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

$token = $_GET['token'] ?? null;
if (!$token) { header('Location: index.php'); exit; }
$request = onboarding_load($conn, $token);
if (!$request || $request['statut'] !== 'termine') { header('Location: index.php'); exit; }

// Charger le code parrainage du proprio
$codeParrainage = '';
if ($request['proprietaire_id']) {
    $stmt = $conn->prepare("SELECT code FROM codes_parrainage WHERE proprietaire_id = ? LIMIT 1");
    $stmt->execute([$request['proprietaire_id']]);
    $codeParrainage = $stmt->fetchColumn() ?: '';
}

$packs = onboarding_get_packs();
$packInfo = $packs[$request['pack'] ?? 'autonome'] ?? $packs['autonome'];
$commission = (float)($request['commission_base'] ?? 10);
$estimation = onboarding_estimate_revenue((float)($request['prix_souhaite'] ?? 80), $commission, 70);

// Supprimer le cookie onboarding
setcookie('frenchy_onboarding', '', time() - 3600, '/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue chez Frenchy ! — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; color: white; }
        .success-container { max-width: 700px; margin: 0 auto; padding: 40px 20px; text-align: center; }

        .confetti { font-size: 4rem; margin-bottom: 20px; animation: bounce 0.6s ease; }
        @keyframes bounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .card-dark {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .next-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }
        .next-step:last-child { border-bottom: none; }
        .step-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .step-icon-circle.pending { background: rgba(255,255,255,0.2); }

        .parrainage-box {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
        }
        .parrainage-code {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 3px;
            background: rgba(0,0,0,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            margin: 10px 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="success-container">
    <div class="confetti">&#x1F389;</div>
    <h1 style="font-weight: 800; font-size: 2.2rem;">Bienvenue chez Frenchy !</h1>
    <p style="font-size: 1.1rem; opacity: 0.8;">
        <?= htmlspecialchars(trim(($request['prenom'] ?? '') . ' ' . ($request['nom'] ?? ''))) ?>,
        votre bien est en cours d'activation
    </p>

    <!-- Resume -->
    <div class="card-dark">
        <div class="row text-center">
            <div class="col-4">
                <div style="font-size: 0.75rem; opacity: 0.6;">Pack</div>
                <div style="font-weight: 700;"><?= $packInfo['label'] ?></div>
            </div>
            <div class="col-4">
                <div style="font-size: 0.75rem; opacity: 0.6;">Commission</div>
                <div style="font-weight: 700; color: <?= $packInfo['color'] ?>;"><?= $commission ?>%</div>
            </div>
            <div class="col-4">
                <div style="font-size: 0.75rem; opacity: 0.6;">Revenu estime</div>
                <div style="font-weight: 700; color: #28a745;"><?= number_format($estimation['revenu_net'], 0, ',', ' ') ?> EUR/mois</div>
            </div>
        </div>
    </div>

    <!-- Prochaines etapes -->
    <div class="card-dark">
        <h5 style="font-weight: 700; margin-bottom: 15px;"><i class="fas fa-tasks"></i> Prochaines etapes</h5>

        <div class="next-step">
            <div class="step-icon-circle"><i class="fas fa-check"></i></div>
            <div>
                <div style="font-weight: 600;">Inscription validee</div>
                <div style="font-size: 0.8rem; opacity: 0.7;">Votre compte proprietaire est cree</div>
            </div>
        </div>

        <div class="next-step">
            <div class="step-icon-circle pending"><i class="fas fa-envelope"></i></div>
            <div>
                <div style="font-weight: 600;">Creation email @frenchyconciergerie.fr</div>
                <div style="font-size: 0.8rem; opacity: 0.7;">Sous 24h — vous recevrez vos identifiants</div>
            </div>
        </div>

        <div class="next-step">
            <div class="step-icon-circle pending"><i class="fas fa-globe"></i></div>
            <div>
                <div style="font-weight: 600;">Site vitrine genere</div>
                <div style="font-size: 0.8rem; opacity: 0.7;">Votre page personnalisee avec photos et guide</div>
            </div>
        </div>

        <div class="next-step">
            <div class="step-icon-circle pending"><i class="fas fa-camera"></i></div>
            <div>
                <div style="font-weight: 600;">
                    <?php if ($request['pack'] === 'serenite' || $request['pack'] === 'cle_en_main'): ?>
                        Seance photo professionnelle
                    <?php else: ?>
                        Optimisation de vos photos
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.8rem; opacity: 0.7;">Prise de RDV sous 48h</div>
            </div>
        </div>

        <div class="next-step">
            <div class="step-icon-circle pending"><i class="fas fa-chart-line"></i></div>
            <div>
                <div style="font-weight: 600;">Activation tarification dynamique</div>
                <div style="font-size: 0.8rem; opacity: 0.7;">Configuration Superhote automatique</div>
            </div>
        </div>
    </div>

    <!-- Acces -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <a href="../proprietaire/login.php" class="btn btn-success btn-lg w-100">
                <i class="fas fa-tachometer-alt"></i> Mon dashboard
            </a>
        </div>
        <div class="col-md-6">
            <a href="../proprietaire/profil.php" class="btn btn-outline-light btn-lg w-100">
                <i class="fas fa-user-edit"></i> Mon profil
            </a>
        </div>
    </div>

    <!-- Parrainage -->
    <?php if ($codeParrainage): ?>
    <div class="parrainage-box">
        <h5 style="font-weight: 700;"><i class="fas fa-gift"></i> Parrainez et economisez !</h5>
        <p style="font-size: 0.9rem; opacity: 0.9;">
            Chaque filleul actif = <strong>-1% sur votre commission</strong> (jusqu'a -5%)
        </p>
        <div class="parrainage-code" onclick="copyCode()" id="parrainCode" title="Cliquer pour copier">
            <?= htmlspecialchars($codeParrainage) ?>
        </div>
        <p style="font-size: 0.8rem; opacity: 0.8;">
            Partagez ce lien : <br>
            <code style="color: white;" id="parrainUrl"><?= 'https://frenchyconciergerie.fr/onboarding/?parrain=' . urlencode($codeParrainage) ?></code>
        </p>
        <button onclick="copyCode()" class="btn btn-light btn-sm"><i class="fas fa-copy"></i> Copier le lien</button>
    </div>
    <?php endif; ?>

    <p class="mt-4" style="font-size: 0.8rem; opacity: 0.5;">
        Un email de confirmation a ete envoye a <?= htmlspecialchars($request['email'] ?? '') ?>
    </p>
</div>

<script>
function copyCode() {
    const url = document.getElementById('parrainUrl')?.textContent?.trim() || '';
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target.closest('button') || event.target;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copie !';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>
</body>
</html>
