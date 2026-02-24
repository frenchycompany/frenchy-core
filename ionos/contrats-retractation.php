<?php
/**
 * Frenchy Conciergerie - Contrats & Rétractation
 * Page d'information sur les contrats et le droit de rétractation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getAllSettings($conn);
$pageTitle = "Contrats & Rétractation";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <meta name="description" content="Informations sur les contrats de gestion locative et le droit de rétractation - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>">
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
            font-size: 1.5rem;
            margin: 2.5rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        .intro {
            background: var(--gris-clair);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--bleu-frenchy);
        }

        .intro p {
            margin: 0;
            font-size: 1.05rem;
        }

        .section {
            margin-bottom: 2.5rem;
        }

        .section p {
            margin-bottom: 1rem;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--bleu-frenchy);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            margin: 1rem 0;
        }

        .download-btn:hover {
            background: var(--bleu-clair);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);
        }

        .download-btn svg {
            width: 24px;
            height: 24px;
        }

        .note {
            background: #FEF3C7;
            border: 1px solid #FCD34D;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            font-size: 0.95rem;
        }

        .note strong {
            color: #92400E;
        }

        .legal-note {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            font-size: 0.95rem;
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
                font-size: 1.3rem;
            }

            .download-btn {
                display: flex;
                width: 100%;
                justify-content: center;
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
            <h1>Contrats & Rétractation</h1>

            <div class="intro">
                <p>Cette page met à disposition des documents d'information à titre indicatif. Toute collaboration fait l'objet d'un échange préalable et, le cas échéant, d'un contrat distinct signé entre le propriétaire et la SAS Frenchy Conciergerie. Aucun engagement n'est pris directement via ce site internet.</p>
            </div>

            <div class="section">
                <h2>Exemple de mandat de gestion (document indicatif)</h2>
                <p>Vous pouvez consulter un exemple de mandat de gestion à titre informatif :</p>

                <!-- TODO: Remplacer par le vrai fichier PDF du mandat de gestion -->
                <a href="docs/mandat-gestion-exemple.pdf" class="download-btn" target="_blank">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Télécharger le PDF : Exemple de mandat de gestion
                </a>

                <div class="note">
                    <p><strong>Note :</strong> Ce document est fourni à titre d'exemple et ne constitue pas une offre contractuelle. Les conditions effectives peuvent varier selon la situation du logement et les besoins du propriétaire.</p>
                </div>
            </div>

            <div class="section">
                <h2>Droit de rétractation (contrats conclus à distance ou hors établissement)</h2>
                <p>Lorsque la réglementation s'applique, le propriétaire peut disposer d'un délai de 14 jours pour exercer son droit de rétractation, conformément aux dispositions du Code de la consommation.</p>
                <p>Le formulaire type ci-dessous peut être utilisé pour exercer ce droit :</p>

                <!-- TODO: Remplacer par le vrai fichier PDF du formulaire de rétractation -->
                <a href="docs/formulaire-retractation.pdf" class="download-btn" target="_blank">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Télécharger le PDF : Formulaire de rétractation
                </a>

                <div class="legal-note">
                    <p><strong>Note :</strong> En cas de demande expresse de démarrage de la prestation avant la fin du délai légal, une renonciation expresse peut être requise dans les conditions prévues par la loi.</p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Tous droits réservés</p>
            <p><a href="index.php">Accueil</a> | <a href="index.php#legal">Mentions légales</a> | <a href="index.php#privacy">Politique de confidentialité</a> | <a href="politique-avis.php">Politique des avis</a></p>
        </div>
    </footer>
</body>
</html>
