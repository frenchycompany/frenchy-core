<?php
/**
 * Frenchy Conciergerie - Politique des avis
 * Page décrivant la collecte, modération et publication des avis
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getAllSettings($conn);
$pageTitle = "Politique des avis";
$contactEmail = $settings['email_legal'] ?? $settings['email'] ?? 'contact@frenchyconciergerie.fr';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="Politique de collecte, modération et publication des avis clients - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>">
    <link rel="icon" type="image/png" href="logo.png">
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --or-frenchy: #D4AF37;
            --gris-clair: #F3F4F6;
            --texte: #1F2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--texte);
            background: #fff;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Header */
        header {
            background: var(--bleu-frenchy);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: white;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo span {
            font-size: 1.3rem;
            font-weight: bold;
        }

        .back-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            transition: all 0.3s;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
        }

        /* Main Content */
        main {
            padding: 3rem 0;
        }

        h1 {
            color: var(--bleu-frenchy);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--or-frenchy);
        }

        h2 {
            color: var(--bleu-frenchy);
            font-size: 1.4rem;
            margin: 2.5rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section p {
            margin-bottom: 1rem;
        }

        ul {
            margin: 1rem 0 1rem 1.5rem;
        }

        ul li {
            margin-bottom: 0.5rem;
        }

        .highlight-box {
            background: var(--gris-clair);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--bleu-frenchy);
        }

        .contact-box {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .contact-box a {
            color: var(--bleu-frenchy);
            font-weight: 600;
        }

        /* Footer */
        footer {
            background: var(--bleu-frenchy);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        footer .container {
            text-align: center;
        }

        footer a {
            color: var(--or-frenchy);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        footer p {
            margin: 0.5rem 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 {
                font-size: 1.8rem;
            }

            h2 {
                font-size: 1.2rem;
            }

            header .container {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">
                <img src="logo.png" alt="<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>">
                <span><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></span>
            </a>
            <a href="index.php" class="back-link">← Retour à l'accueil</a>
        </div>
    </header>

    <main>
        <div class="container">
            <h1>Politique des avis</h1>

            <div class="section">
                <h2>Origine des avis</h2>
                <p>Les avis publiés sur ce site proviennent de propriétaires ayant réellement utilisé nos services de conciergerie / gestion. Un avis est associé à un propriétaire identifié, à une adresse de logement et à un horodatage.</p>
            </div>

            <div class="section">
                <h2>Modalités de collecte</h2>
                <p>Après une période d'exploitation, un email est envoyé au propriétaire avec un code unique permettant de déposer un avis. Ce code est strictement personnel et empêche les dépôts non autorisés.</p>

                <div class="highlight-box">
                    <p><strong>Processus de vérification :</strong> Chaque propriétaire reçoit un code personnel par email. Ce code, associé à son adresse email, permet de garantir l'authenticité de l'avis déposé.</p>
                </div>
            </div>

            <div class="section">
                <h2>Modération et publication</h2>
                <p>Chaque avis est soumis à modération avant publication afin de vérifier qu'il respecte les règles suivantes :</p>
                <ul>
                    <li>l'avis doit concerner une expérience réelle ;</li>
                    <li>l'avis ne doit pas contenir de propos illicites, diffamatoires, haineux, ou de données sensibles ;</li>
                    <li>l'avis ne doit pas contenir de données personnelles inutiles (numéros, emails, etc.).</li>
                </ul>
                <p>Nous ne modifions pas le contenu d'un avis (hors suppression éventuelle de données personnelles ou éléments manifestement illicites).</p>
            </div>

            <div class="section">
                <h2>Délai de publication et conservation</h2>
                <p>Les avis sont en principe publiés sous un délai raisonnable après validation. Ils peuvent être conservés en ligne tant qu'ils présentent un intérêt informatif.</p>
            </div>

            <div class="section">
                <h2>Contestation</h2>
                <p>Si une personne estime qu'un avis est inexact ou contraire aux règles ci-dessus, elle peut demander une vérification en nous contactant.</p>

                <div class="contact-box">
                    <p><strong>Contact pour contestation d'avis :</strong></p>
                    <p><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Tous droits réservés</p>
            <p><a href="index.php">Accueil</a> | <a href="index.php#legal">Mentions légales</a> | <a href="index.php#privacy">Politique de confidentialité</a> | <a href="contrats-retractation.php">Contrats & Rétractation</a></p>
        </div>
    </footer>
</body>
</html>
