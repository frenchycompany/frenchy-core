<?php
/**
 * Mentions légales — Obligatoire pour tout site professionnel en France.
 * Les données sont chargées depuis config/property.php et la BDD.
 */
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/db/helpers.php';

$property  = vf_load_property();
$settings  = vf_load_settings($conn);
$site      = vf_build_site_config($settings);
$css_vars  = vf_build_css_vars($settings);
$font_url  = vf_build_font_url($settings);
$db_photos = vf_load_photos($conn);
if (!empty($db_photos['logo'][0]['file_path'])) {
    $site['logo'] = $db_photos['logo'][0]['file_path'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentions légales — <?= htmlspecialchars($site['name']) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= htmlspecialchars($font_url) ?>" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style><?= $css_vars ?></style>
</head>
<body>

<header class="vf-header" id="top">
    <div class="vf-container vf-header-inner">
        <a href="/" class="vf-logo">
            <?php if (!empty($site['logo']) && file_exists($site['logo'])): ?>
                <img src="<?= htmlspecialchars($site['logo']) ?>" alt="Logo <?= htmlspecialchars($site['name']) ?>" class="vf-logo-img">
            <?php endif; ?>
            <span class="vf-logo-text">
                <span class="vf-logo-name"><?= htmlspecialchars($site['name']) ?></span>
                <span class="vf-logo-sub"><?= htmlspecialchars($site['location']) ?></span>
            </span>
        </a>
        <a href="/" class="vf-btn vf-btn-outline">Retour au site</a>
    </div>
</header>

<main>
    <section class="vf-section vf-section--white" style="padding-top:6rem">
        <div class="vf-container vf-section-narrow">
            <h1 class="vf-heading vf-heading--center">Mentions légales</h1>

            <div class="vf-legal-content" style="max-width:720px;margin:0 auto;line-height:1.8">

                <h2>Éditeur du site</h2>
                <p>
                    <?= htmlspecialchars($site['name']) ?><br>
                    <?= htmlspecialchars($site['address']) ?><br>
                    Téléphone : <?= htmlspecialchars($site['phone']) ?><br>
                    Email : <?= htmlspecialchars($site['email']) ?>
                </p>

                <h2>Hébergement</h2>
                <p>
                    Ce site est hébergé par l'hébergeur indiqué dans les conditions d'utilisation.
                    Pour toute question relative à l'hébergement, veuillez nous contacter à l'adresse email ci-dessus.
                </p>

                <h2>Propriété intellectuelle</h2>
                <p>
                    L'ensemble du contenu de ce site (textes, images, photographies, logo, icônes, vidéos)
                    est la propriété exclusive de l'éditeur ou de ses partenaires et est protégé par le droit d'auteur.
                    Toute reproduction, même partielle, est interdite sans autorisation préalable.
                </p>

                <h2>Données personnelles</h2>
                <p>
                    Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi
                    « Informatique et Libertés » du 6 janvier 1978, vous disposez d'un droit d'accès,
                    de rectification et de suppression des données vous concernant.
                </p>
                <p>
                    Ce site ne collecte aucune donnée personnelle à des fins commerciales.
                    Les seules informations collectées sont celles que vous transmettez volontairement
                    via le formulaire de contact. Ces données sont utilisées uniquement pour répondre
                    à votre demande et ne sont jamais transmises à des tiers.
                </p>
                <p>
                    Pour exercer vos droits, contactez-nous à :
                    <a href="mailto:<?= htmlspecialchars($site['email']) ?>"><?= htmlspecialchars($site['email']) ?></a>
                </p>

                <h2>Cookies</h2>
                <p>
                    Ce site utilise uniquement des cookies techniques strictement nécessaires
                    à son fonctionnement (session d'administration). Aucun cookie de suivi
                    ou publicitaire n'est déposé.
                </p>

                <h2>Responsabilité</h2>
                <p>
                    L'éditeur s'efforce d'assurer l'exactitude des informations diffusées sur ce site,
                    mais ne saurait être tenu responsable des omissions, inexactitudes ou résultats
                    qui pourraient être obtenus par un mauvais usage de ces informations.
                </p>

                <h2>Crédits</h2>
                <p>
                    Site conçu par <a href="https://frenchyconciergerie.com" target="_blank" rel="noopener">Frenchy Conciergerie</a>.
                </p>

            </div>
        </div>
    </section>
</main>

<footer class="vf-footer">
    <div class="vf-container">
        <div class="vf-footer-bottom">
            <p class="vf-footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars($site['name']) ?> — Tous droits réservés</p>
        </div>
    </div>
</footer>

</body>
</html>
