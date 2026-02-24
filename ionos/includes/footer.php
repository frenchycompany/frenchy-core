<?php
/**
 * Footer partagé - Frenchy Conciergerie
 */
if (!isset($settings)) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/functions.php';
    if ($conn) {
        $settings = getAllSettings($conn);
    } else {
        $settings = [];
    }
}
?>
<footer style="background: #1E3A8A; color: white; padding: 3rem 0 1rem;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="margin-bottom: 1rem;"><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">Votre partenaire de confiance pour la gestion locative premium dans la région de Compiègne.</p>
                <p style="margin-top: 1rem;"><strong>SIRET :</strong> <?= e($settings['siret'] ?? '') ?></p>
            </div>

            <div>
                <h3 style="margin-bottom: 1rem;">Navigation</h3>
                <a href="index.php#services" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Nos Services</a>
                <a href="index.php#tarifs" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Tarifs</a>
                <a href="index.php#galerie" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Nos Logements</a>
                <a href="blog.php" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Blog</a>
                <a href="calculateur.php" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Calculateur</a>
            </div>

            <div>
                <h3 style="margin-bottom: 1rem;">Informations</h3>
                <a href="index.php#legal" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Mentions Légales</a>
                <a href="proprietaire/login.php" style="display: block; color: rgba(255,255,255,0.8); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.9rem;">Espace Propriétaire</a>
            </div>

            <div>
                <h3 style="margin-bottom: 1rem;">Contact</h3>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem;"><?= e($settings['adresse'] ?? '') ?></p>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-top: 0.5rem;"><?= e($settings['telephone'] ?? '') ?></p>
                <p style="margin-top: 0.5rem;"><a href="mailto:<?= e($settings['email'] ?? '') ?>" style="color: rgba(255,255,255,0.9);"><?= e($settings['email'] ?? '') ?></a></p>
            </div>
        </div>

        <div style="text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); font-size: 0.85rem;">
            <p>&copy; <?= date('Y') ?> <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Tous droits réservés</p>
            <p style="margin-top: 0.3rem;">SIRET : <?= e($settings['siret'] ?? '') ?> - RCS <?= e($settings['rcs'] ?? '') ?></p>
        </div>
    </div>
</footer>

<!-- Theme Toggle -->
<button class="theme-toggle" aria-label="Changer le thème">
    <span class="icon-moon">🌙</span>
    <span class="icon-sun">☀️</span>
</button>

<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/main.js"></script>
