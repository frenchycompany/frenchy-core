<?php
/**
 * Frenchy Self Boarding — Landing Page
 * "Louez votre bien sans vous prendre la tete"
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/onboarding-helper.php';

// Si token existant, reprendre le parcours
$token = $_GET['token'] ?? $_COOKIE['frenchy_onboarding'] ?? null;
if ($token) {
    $existing = onboarding_load($conn, $token);
    if ($existing && $existing['statut'] !== 'termine') {
        $etape = (int)$existing['etape_courante'];
        $slug = onboarding_step_slug($etape);
        header("Location: etape-{$etape}-{$slug}.php?token=" . urlencode($token));
        exit;
    }
}

$packs = onboarding_get_packs();

// Code parrainage
$parrain = $_GET['parrain'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenez Hote Frenchy — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --frenchy-green: #28a745;
            --frenchy-blue: #007bff;
            --frenchy-purple: #6f42c1;
            --frenchy-dark: #1a1a2e;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, var(--frenchy-dark) 0%, #16213e 100%);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
        }
        .hero h1 { font-size: 2.8rem; font-weight: 800; margin-bottom: 15px; }
        .hero h1 span { color: var(--frenchy-green); }
        .hero .lead { font-size: 1.3rem; opacity: 0.9; margin-bottom: 30px; }
        .hero .btn-start {
            background: var(--frenchy-green);
            border: none;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 50px;
            color: white;
            transition: all 0.3s;
        }
        .hero .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
            background: #218838;
            color: white;
        }
        .hero .sub-cta { margin-top: 12px; font-size: 0.9rem; opacity: 0.7; }

        /* Stats */
        .stats { background: white; padding: 30px 0; border-bottom: 1px solid #e9ecef; }
        .stat-item { text-align: center; padding: 15px; }
        .stat-number { font-size: 2rem; font-weight: 800; color: var(--frenchy-green); }
        .stat-label { font-size: 0.85rem; color: #6c757d; }

        /* Etapes */
        .steps-section { padding: 60px 0; background: #f8f9fa; }
        .step-card {
            text-align: center;
            padding: 25px 15px;
        }
        .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--frenchy-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 15px;
        }
        .step-card h5 { font-weight: 700; }
        .step-card p { font-size: 0.85rem; color: #6c757d; }

        /* Packs */
        .packs-section { padding: 60px 0; background: white; }
        .pack-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            position: relative;
        }
        .pack-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .pack-card.popular { border-color: var(--frenchy-blue); }
        .pack-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--frenchy-blue);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .pack-price { font-size: 2.5rem; font-weight: 800; margin: 15px 0 5px; }
        .pack-price small { font-size: 1rem; font-weight: 400; }
        .pack-services { list-style: none; padding: 0; text-align: left; margin: 20px 0; }
        .pack-services li { padding: 4px 0; font-size: 0.85rem; }
        .pack-services li i { color: var(--frenchy-green); margin-right: 8px; width: 16px; }

        /* Footer */
        .landing-footer { background: var(--frenchy-dark); color: rgba(255,255,255,0.7); padding: 30px 0; text-align: center; font-size: 0.85rem; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 1.8rem; }
            .hero .lead { font-size: 1rem; }
            .pack-card { margin-bottom: 20px; }
        }
    </style>
</head>
<body>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <h1>Louez votre bien<br>sans vous prendre la <span>tete</span></h1>
        <p class="lead">Conciergerie Airbnb & Booking a partir de 10% — Setup en 10 minutes</p>
        <a href="#start" class="btn btn-start" id="ctaStart">
            <i class="fas fa-rocket"></i> Commencer maintenant
        </a>
        <p class="sub-cta">Gratuit, sans engagement — 10 minutes chrono</p>
    </div>
</section>

<!-- STATS -->
<section class="stats">
    <div class="container">
        <div class="row">
            <div class="col-3 stat-item">
                <div class="stat-number">150+</div>
                <div class="stat-label">Biens geres</div>
            </div>
            <div class="col-3 stat-item">
                <div class="stat-number">4.8<small>/5</small></div>
                <div class="stat-label">Note voyageurs</div>
            </div>
            <div class="col-3 stat-item">
                <div class="stat-number">85%</div>
                <div class="stat-label">Taux occupation</div>
            </div>
            <div class="col-3 stat-item">
                <div class="stat-number">10min</div>
                <div class="stat-label">Pour demarrer</div>
            </div>
        </div>
    </div>
</section>

<!-- ETAPES -->
<section class="steps-section">
    <div class="container">
        <h2 class="text-center mb-5" style="font-weight: 800;">Comment ca marche ?</h2>
        <div class="row">
            <div class="col-md-4 step-card">
                <div class="step-icon"><i class="fas fa-home"></i></div>
                <h5>1. Decrivez votre bien</h5>
                <p>Adresse, photos, equipements. 3 minutes top chrono.</p>
            </div>
            <div class="col-md-4 step-card">
                <div class="step-icon" style="background: var(--frenchy-blue);"><i class="fas fa-tags"></i></div>
                <h5>2. Choisissez votre formule</h5>
                <p>De 10% a 30% selon le niveau de service souhaite. Configurable a tout moment.</p>
            </div>
            <div class="col-md-4 step-card">
                <div class="step-icon" style="background: var(--frenchy-purple);"><i class="fas fa-chart-line"></i></div>
                <h5>3. Encaissez</h5>
                <p>On gere les reservations, les voyageurs, le menage. Vous recevez vos revenus.</p>
            </div>
        </div>
    </div>
</section>

<!-- PACKS -->
<section class="packs-section" id="packs">
    <div class="container">
        <h2 class="text-center mb-2" style="font-weight: 800;">3 formules, 0 surprise</h2>
        <p class="text-center text-muted mb-5">Commission configurable par proprietaire — ces packs sont des points de depart</p>
        <div class="row">
            <?php foreach ($packs as $key => $pack): ?>
            <div class="col-md-4 mb-4">
                <div class="pack-card <?= !empty($pack['popular']) ? 'popular' : '' ?>">
                    <?php if (!empty($pack['popular'])): ?>
                        <div class="pack-badge">Le plus populaire</div>
                    <?php endif; ?>
                    <i class="fas <?= $pack['icon'] ?> fa-2x mb-3" style="color: <?= $pack['color'] ?>;"></i>
                    <h4 style="font-weight: 700;"><?= $pack['label'] ?></h4>
                    <p class="text-muted" style="font-size: 0.85rem;"><?= $pack['slogan'] ?></p>
                    <div class="pack-price" style="color: <?= $pack['color'] ?>;">
                        <?= $pack['commission'] ?>%<small> commission</small>
                    </div>
                    <p style="font-size: 0.75rem; color: #adb5bd;">Minimum <?= $pack['commission_min'] ?>% (parrainage)</p>
                    <ul class="pack-services">
                        <?php foreach ($pack['services'] as $s): ?>
                        <li><i class="fas fa-check-circle"></i> <?= $s ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <hr>
                    <p style="font-size: 0.8rem; font-weight: 600;">Vous gerez :</p>
                    <ul class="pack-services">
                        <?php foreach ($pack['vous_gerez'] as $s): ?>
                        <li><i class="fas fa-hand-point-right" style="color: #6c757d;"></i> <?= $s ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Final -->
<section class="hero" id="start" style="padding: 50px 0;">
    <div class="container">
        <h2 style="font-weight: 800;">Pret a vous lancer ?</h2>
        <p class="lead">Inscrivez votre bien en quelques minutes</p>
        <?php if ($parrain): ?>
            <p><span class="badge bg-success"><i class="fas fa-gift"></i> Code parrainage : <?= htmlspecialchars($parrain) ?> — Seance photo offerte !</span></p>
        <?php endif; ?>
        <form method="GET" action="etape-1-bien.php" style="display: inline;">
            <?php if ($parrain): ?>
                <input type="hidden" name="parrain" value="<?= htmlspecialchars($parrain) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-start">
                <i class="fas fa-play-circle"></i> Demarrer mon inscription
            </button>
        </form>
    </div>
</section>

<footer class="landing-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> FrenchyConciergerie — Tous droits reserves</p>
    </div>
</footer>

<script>
document.getElementById('ctaStart').addEventListener('click', (e) => {
    e.preventDefault();
    document.getElementById('start').scrollIntoView({ behavior: 'smooth' });
});
</script>
</body>
</html>
