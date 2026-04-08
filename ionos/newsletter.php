<?php
/**
 * Système de Newsletter
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

$message = '';
$messageType = '';

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting
    $rateCheck = $security->checkRateLimit('newsletter');
    if (!$rateCheck['allowed']) {
        $message = $rateCheck['message'];
        $messageType = 'error';
    } else {
        $email = $security->sanitize($_POST['email'] ?? '', 'email');
        $nom = $security->sanitize($_POST['nom'] ?? '');

        if (empty($email) || !$security->validateEmail($email)) {
            $message = 'Veuillez entrer une adresse email valide.';
            $messageType = 'error';
            $security->recordAttempt('newsletter');
        } else {
            // Vérifier si déjà inscrit
            $stmt = $conn->prepare("SELECT id, actif FROM FC_newsletter WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['actif']) {
                    $message = 'Cette adresse email est déjà inscrite à notre newsletter.';
                    $messageType = 'info';
                } else {
                    // Réactiver l'inscription
                    $stmt = $conn->prepare("UPDATE FC_newsletter SET actif = 1 WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                    $message = 'Votre inscription a été réactivée avec succès !';
                    $messageType = 'success';
                }
            } else {
                // Nouvelle inscription
                $token = $security->generateToken();

                $stmt = $conn->prepare("INSERT INTO FC_newsletter (email, nom, token_unsubscribe, source) VALUES (?, ?, ?, 'site')");
                if ($stmt->execute([$email, $nom, $token])) {
                    $message = 'Merci ! Vous êtes maintenant inscrit à notre newsletter.';
                    $messageType = 'success';
                    $security->trackConversion('newsletter', 'inscription');
                    $security->resetAttempts('newsletter');
                } else {
                    $message = 'Une erreur est survenue. Veuillez réessayer.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Désinscription
if (isset($_GET['unsubscribe']) && !empty($_GET['token'])) {
    $token = $security->sanitize($_GET['token']);

    $stmt = $conn->prepare("UPDATE FC_newsletter SET actif = 0 WHERE token_unsubscribe = ?");
    if ($stmt->execute([$token]) && $stmt->rowCount() > 0) {
        $message = 'Vous avez été désinscrit de notre newsletter avec succès.';
        $messageType = 'success';
    } else {
        $message = 'Lien de désinscription invalide ou expiré.';
        $messageType = 'error';
    }
}

// Redirection si requête AJAX
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $messageType === 'success',
        'message' => $message
    ]);
    exit;
}

// Si c'est une simple soumission de formulaire, rediriger avec message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $_SESSION['newsletter_message'] = $message;
    $_SESSION['newsletter_type'] = $messageType;
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $host = parse_url($referer, PHP_URL_HOST);
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $redirect = ($host === null || $host === $serverHost) ? $referer : 'index.php';
    header('Location: ' . $redirect);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            padding: 1rem;
        }

        .newsletter-card {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .newsletter-card h1 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .newsletter-card p {
            color: #6B7280;
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success { background: #D1FAE5; color: #065F46; }
        .alert-error { background: #FEE2E2; color: #991B1B; }
        .alert-info { background: #DBEAFE; color: #1E40AF; }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-fonce);
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--bleu-clair);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--bleu-clair);
            text-decoration: none;
        }

        .benefits {
            text-align: left;
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--gris-clair);
            border-radius: 10px;
        }

        .benefits h3 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .benefits ul {
            list-style: none;
        }

        .benefits li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .benefits li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--bleu-clair);
        }
    </style>
</head>
<body>
    <div class="newsletter-card">
        <h1>Newsletter</h1>
        <p>Recevez nos meilleurs conseils sur la location saisonnière directement dans votre boîte mail !</p>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if (!isset($_GET['unsubscribe'])): ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Adresse email *</label>
                <input type="email" id="email" name="email" required placeholder="votre@email.fr">
            </div>
            <div class="form-group">
                <label for="nom">Prénom (optionnel)</label>
                <input type="text" id="nom" name="nom" placeholder="Votre prénom">
            </div>
            <button type="submit" class="btn-submit">S'inscrire à la newsletter</button>
        </form>

        <div class="benefits">
            <h3>En vous inscrivant, vous recevrez :</h3>
            <ul>
                <li>Des conseils pour optimiser vos revenus locatifs</li>
                <li>Les dernières actualités du marché</li>
                <li>Des offres exclusives</li>
                <li>Nos nouveaux articles de blog</li>
            </ul>
        </div>

        <p style="font-size: 0.85rem; color: #6B7280;">
            Pas de spam, désinscription en un clic. Vos données restent confidentielles.
        </p>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Retour au site</a>
    </div>
</body>
</html>
