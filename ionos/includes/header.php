<?php
/**
 * Header partagé - Frenchy Conciergerie
 */
if (!isset($settings)) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/functions.php';
    if (!$conn) {
        die('Erreur de connexion à la base de données.');
    }
    $settings = getAllSettings($conn);
}
?>
<header style="background: linear-gradient(135deg, var(--bleu-frenchy, #1E3A8A) 0%, var(--bleu-clair, #3B82F6) 100%); color: white; padding: 1rem 0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <a href="index.php" style="display: flex; align-items: center; gap: 0.8rem; text-decoration: none; color: white;">
                <img src="frenchyconciergerie.png.png" alt="Logo" style="width: 50px; height: 50px; border-radius: 50%; background: white; padding: 3px;" onerror="this.style.display='none'">
                <span style="font-size: 1.3rem; font-weight: bold;"><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></span>
            </a>

            <nav style="display: flex; gap: 1.5rem; align-items: center;">
                <a href="index.php#services" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.95rem;">Services</a>
                <a href="index.php#tarifs" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.95rem;">Tarifs</a>
                <a href="index.php#galerie" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.95rem;">Logements</a>
                <a href="blog.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.95rem;">Blog</a>
                <a href="calculateur.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 0.95rem;">Calculateur</a>
                <a href="index.php#contact" style="background: white; color: #1E3A8A; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Contact</a>
            </nav>
        </div>
    </div>
</header>
