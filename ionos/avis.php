<?php
/**
 * Frenchy Conciergerie - Page de soumission d'avis propriétaires
 * Système de vérification par code unique
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getAllSettings($conn);
$message = '';
$messageType = '';
$step = 1; // 1 = verification, 2 = form, 3 = success

// Créer la table des codes de vérification si nécessaire
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS FC_avis_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        email VARCHAR(255) NOT NULL,
        nom_proprietaire VARCHAR(255) NOT NULL,
        adresse_bien VARCHAR(255),
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table existe déjà
}

// Traitement de la vérification du code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $email = trim($_POST['email'] ?? '');

    if (empty($code) || empty($email)) {
        $message = 'Veuillez remplir tous les champs.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM FC_avis_codes WHERE code = ? AND email = ? AND used = 0");
            $stmt->execute([$code, $email]);
            $codeData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($codeData) {
                $_SESSION['avis_code'] = $code;
                $_SESSION['avis_email'] = $email;
                $_SESSION['avis_nom'] = $codeData['nom_proprietaire'];
                $_SESSION['avis_adresse'] = $codeData['adresse_bien'];
                $step = 2;
            } else {
                // Vérifier si le code a déjà été utilisé
                $stmt = $conn->prepare("SELECT * FROM FC_avis_codes WHERE code = ? AND email = ?");
                $stmt->execute([$code, $email]);
                $usedCode = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usedCode && $usedCode['used']) {
                    $message = 'Ce code a déjà été utilisé pour soumettre un avis.';
                } else {
                    $message = 'Code ou email invalide. Vérifiez vos informations ou contactez-nous.';
                }
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Erreur de vérification. Veuillez réessayer.';
            $messageType = 'error';
        }
    }
}

// Traitement de la soumission de l'avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_avis'])) {
    if (!isset($_SESSION['avis_code']) || !isset($_SESSION['avis_email'])) {
        $message = 'Session expirée. Veuillez recommencer la vérification.';
        $messageType = 'error';
    } else {
        $nom = trim($_POST['nom'] ?? $_SESSION['avis_nom']);
        $role = trim($_POST['role'] ?? 'Propriétaire');
        $note = intval($_POST['note'] ?? 5);
        $commentaire = trim($_POST['commentaire'] ?? '');
        $date_avis = date('Y-m-d'); // Date d'enregistrement officielle
        $rgpd_consent = isset($_POST['rgpd_consent']);

        if (empty($commentaire)) {
            $message = 'Veuillez écrire votre commentaire.';
            $messageType = 'error';
            $step = 2;
        } elseif (!$rgpd_consent) {
            $message = 'Veuillez accepter les conditions de publication.';
            $messageType = 'error';
            $step = 2;
        } elseif ($note < 1 || $note > 5) {
            $message = 'Note invalide.';
            $messageType = 'error';
            $step = 2;
        } else {
            try {
                // Ajouter la colonne source si elle n'existe pas (AVANT l'insertion)
                try {
                    $conn->exec("ALTER TABLE FC_avis ADD COLUMN source VARCHAR(50) DEFAULT 'admin'");
                } catch (PDOException $e) {
                    // Colonne existe déjà, on continue
                }

                // Insérer l'avis (en attente de modération)
                $stmt = $conn->prepare("INSERT INTO FC_avis (nom, role, note, commentaire, date_avis, actif, source) VALUES (?, ?, ?, ?, ?, 0, 'formulaire')");
                $stmt->execute([$nom, $role, $note, $commentaire, $date_avis]);

                // Marquer le code comme utilisé
                $stmt = $conn->prepare("UPDATE FC_avis_codes SET used = 1, used_at = NOW() WHERE code = ?");
                $stmt->execute([$_SESSION['avis_code']]);

                // Envoyer l'email de confirmation au propriétaire
                $emailProprietaire = $_SESSION['avis_email'];
                $nomSite = $settings['site_nom'] ?? 'Frenchy Conciergerie';
                $emailSite = $settings['email'] ?? '';

                $sujetEmail = "Confirmation de votre avis - $nomSite";
                $corpsEmail = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;'>
    <div style='background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); padding: 30px; text-align: center;'>
        <h1 style='color: white; margin: 0;'>$nomSite</h1>
    </div>

    <div style='padding: 30px; background: #f9f9f9;'>
        <h2 style='color: #1E3A8A;'>Merci pour votre témoignage !</h2>

        <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>

        <p>Nous avons bien reçu votre avis soumis le <strong>" . date('d/m/Y à H:i') . "</strong>.</p>

        <div style='background: white; border-left: 4px solid #10B981; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>Récapitulatif de votre avis :</strong></p>
            <p style='margin: 5px 0;'>Note : " . str_repeat('★', $note) . str_repeat('☆', 5 - $note) . " ($note/5)</p>
            <p style='margin: 5px 0;'>Date d'enregistrement : $date_avis</p>
            <p style='margin: 10px 0 0 0; font-style: italic; color: #666;'>\"" . htmlspecialchars(substr($commentaire, 0, 200)) . (strlen($commentaire) > 200 ? '...' : '') . "\"</p>
        </div>

        <p>Votre avis est actuellement <strong>en attente de validation</strong> par notre équipe. Une fois validé, il sera publié sur notre site internet.</p>

        <p>Nous vous remercions pour votre confiance et pour le temps que vous avez pris pour partager votre expérience.</p>

        <p style='margin-top: 30px;'>
            Cordialement,<br>
            <strong>L'équipe $nomSite</strong>
        </p>
    </div>

    <div style='background: #1E3A8A; color: white; padding: 20px; text-align: center; font-size: 12px;'>
        <p style='margin: 0;'>$nomSite</p>
        <p style='margin: 5px 0 0 0; opacity: 0.8;'>" . htmlspecialchars($settings['adresse'] ?? '') . "</p>
        <p style='margin: 5px 0 0 0; opacity: 0.8;'>" . htmlspecialchars($settings['telephone'] ?? '') . " | $emailSite</p>
    </div>
</body>
</html>";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: $nomSite <$emailSite>\r\n";
                $headers .= "Reply-To: $emailSite\r\n";

                @mail($emailProprietaire, $sujetEmail, $corpsEmail, $headers);

                // Nettoyer la session
                unset($_SESSION['avis_code'], $_SESSION['avis_email'], $_SESSION['avis_nom'], $_SESSION['avis_adresse']);

                $step = 3;
            } catch (PDOException $e) {
                $message = 'Erreur lors de l\'enregistrement. Veuillez réessayer.';
                $messageType = 'error';
                $step = 2;
            }
        }
    }
}

// Reprendre la session si elle existe
if (isset($_SESSION['avis_code']) && $step === 1) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donnez votre avis - <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --rouge-frenchy: #EF4444;
            --gris-clair: #F3F4F6;
            --gris-fonce: #1F2937;
            --vert: #10B981;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--gris-fonce);
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-section img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
        }

        .logo-section h1 {
            color: var(--bleu-frenchy);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .logo-section p {
            color: #6B7280;
        }

        h2 {
            color: var(--bleu-frenchy);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .steps {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            background: var(--gris-clair);
            color: #9CA3AF;
        }

        .step.active {
            background: var(--bleu-frenchy);
            color: white;
        }

        .step.completed {
            background: var(--vert);
            color: white;
        }

        .step-connector {
            width: 50px;
            height: 3px;
            background: var(--gris-clair);
            align-self: center;
        }

        .step-connector.completed {
            background: var(--vert);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-fonce);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--bleu-clair);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #9CA3AF;
            font-size: 0.85rem;
        }

        .code-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            text-transform: uppercase;
            font-weight: bold;
        }

        .rating-container {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1rem 0;
        }

        .rating-container input {
            display: none;
        }

        .rating-container label {
            font-size: 2.5rem;
            cursor: pointer;
            color: #E5E7EB;
            transition: color 0.2s;
        }

        .rating-container label:hover,
        .rating-container label:hover ~ label,
        .rating-container input:checked ~ label {
            color: #FCD34D;
        }

        .rating-container:hover label {
            color: #E5E7EB;
        }

        .rating-container label:hover,
        .rating-container label:hover ~ label {
            color: #FCD34D;
        }

        /* Reverse order for CSS sibling selector trick */
        .rating-container {
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--bleu-frenchy);
            color: white;
        }

        .btn-primary:hover {
            background: var(--bleu-clair);
        }

        .btn-success {
            background: var(--vert);
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .rgpd-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #F0FDF4;
            border-radius: 8px;
            border: 1px solid #BBF7D0;
        }

        .rgpd-checkbox input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .rgpd-checkbox label {
            font-size: 0.85rem;
            color: var(--gris-fonce);
            line-height: 1.5;
        }

        .rgpd-checkbox a {
            color: var(--bleu-clair);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--vert);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
        }

        .info-box {
            background: #DBEAFE;
            border-left: 4px solid var(--bleu-clair);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .info-box h4 {
            color: var(--bleu-frenchy);
            margin-bottom: 0.5rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: var(--bleu-clair);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .owner-info {
            background: var(--gris-clair);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .owner-info p {
            margin: 0.3rem 0;
        }

        .owner-info strong {
            color: var(--bleu-frenchy);
        }

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }

            .card {
                padding: 1.5rem;
            }

            .rating-container label {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo-section">
                <img src="frenchyconciergerie.png.png" alt="<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>">
                <h1><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></h1>
                <p>Votre avis compte pour nous</p>
            </div>

            <!-- Indicateur d'étapes -->
            <div class="steps">
                <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1</div>
                <div class="step-connector <?= $step > 1 ? 'completed' : '' ?>"></div>
                <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2</div>
                <div class="step-connector <?= $step > 2 ? 'completed' : '' ?>"></div>
                <div class="step <?= $step >= 3 ? 'active' : '' ?>">3</div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <!-- Étape 1: Vérification du code -->
            <h2>Vérification de votre identité</h2>

            <div class="info-box">
                <h4>Comment ça marche ?</h4>
                <p>Vous avez reçu un code unique par email ou SMS de la part de <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>. Ce code nous permet de vérifier que vous êtes bien l'un de nos propriétaires partenaires.</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="code">Votre code de vérification</label>
                    <input type="text" id="code" name="code" class="code-input" maxlength="10" placeholder="XXXXXX" required>
                    <small>Le code vous a été envoyé par email ou SMS</small>
                </div>

                <div class="form-group">
                    <label for="email">Votre adresse email</label>
                    <input type="email" id="email" name="email" placeholder="votre@email.com" required>
                    <small>L'email associé à votre compte propriétaire</small>
                </div>

                <button type="submit" name="verify_code" class="btn btn-primary">Vérifier mon identité</button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #E5E7EB; text-align: center;">
                <p style="color: #6B7280; font-size: 0.9rem;">Vous n'avez pas reçu de code ?</p>
                <p style="margin-top: 0.5rem;"><a href="mailto:<?= e($settings['email'] ?? '') ?>" style="color: var(--bleu-clair);">Contactez-nous</a></p>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- Étape 2: Formulaire d'avis -->
            <h2>Partagez votre expérience</h2>

            <div class="owner-info">
                <p><strong>Propriétaire :</strong> <?= e($_SESSION['avis_nom'] ?? '') ?></p>
                <?php if (!empty($_SESSION['avis_adresse'])): ?>
                <p><strong>Bien géré :</strong> <?= e($_SESSION['avis_adresse']) ?></p>
                <?php endif; ?>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="nom">Votre nom (tel qu'il apparaîtra)</label>
                    <input type="text" id="nom" name="nom" value="<?= e($_SESSION['avis_nom'] ?? '') ?>" required>
                    <small>Vous pouvez utiliser un pseudonyme ou vos initiales si vous préférez</small>
                </div>

                <div class="form-group">
                    <label for="role">Votre rôle</label>
                    <input type="text" id="role" name="role" value="Propriétaire" placeholder="Ex: Propriétaire à Compiègne">
                </div>

                <div class="form-group">
                    <label>Votre note</label>
                    <div class="rating-container">
                        <input type="radio" name="note" value="5" id="star5" checked>
                        <label for="star5">★</label>
                        <input type="radio" name="note" value="4" id="star4">
                        <label for="star4">★</label>
                        <input type="radio" name="note" value="3" id="star3">
                        <label for="star3">★</label>
                        <input type="radio" name="note" value="2" id="star2">
                        <label for="star2">★</label>
                        <input type="radio" name="note" value="1" id="star1">
                        <label for="star1">★</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="commentaire">Votre témoignage</label>
                    <textarea id="commentaire" name="commentaire" placeholder="Partagez votre expérience avec Frenchy Conciergerie : qualité du service, communication, gestion de votre bien..." required></textarea>
                    <small>Minimum 50 caractères. Soyez authentique et constructif.</small>
                </div>

                <div class="rgpd-checkbox">
                    <input type="checkbox" id="rgpd_consent" name="rgpd_consent" required>
                    <label for="rgpd_consent">
                        J'autorise <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> à publier mon témoignage sur son site web et ses supports de communication. Je certifie que cet avis reflète mon expérience réelle et personnelle. <a href="index.php#privacy" target="_blank">Politique de confidentialité</a>
                    </label>
                </div>

                <button type="submit" name="submit_avis" class="btn btn-primary">Envoyer mon témoignage</button>
            </form>

            <a href="?reset=1" class="back-link" onclick="return confirm('Voulez-vous vraiment annuler ?');">← Annuler et revenir à l'étape précédente</a>

            <?php elseif ($step === 3): ?>
            <!-- Étape 3: Confirmation -->
            <div class="success-icon">✓</div>
            <h2>Merci pour votre témoignage !</h2>

            <div class="alert alert-success">
                Votre avis a été enregistré avec succès et sera publié après validation par notre équipe.
            </div>

            <p style="text-align: center; margin-bottom: 1.5rem; color: #6B7280;">
                Nous apprécions vraiment que vous ayez pris le temps de partager votre expérience. Votre témoignage aide d'autres propriétaires à faire confiance à nos services.
            </p>

            <a href="index.php" class="btn btn-success">Retourner sur le site</a>
            <?php endif; ?>
        </div>

        <p style="text-align: center; margin-top: 1.5rem; color: rgba(255,255,255,0.8); font-size: 0.85rem;">
            <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - <?= e($settings['telephone'] ?? '') ?>
        </p>
    </div>

    <script>
    // Auto-uppercase pour le code
    document.getElementById('code')?.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
    });

    // Validation du commentaire
    document.querySelector('textarea[name="commentaire"]')?.addEventListener('input', function(e) {
        const minLength = 50;
        const small = this.parentElement.querySelector('small');
        if (this.value.length < minLength) {
            small.style.color = '#EF4444';
            small.textContent = `Encore ${minLength - this.value.length} caractères minimum`;
        } else {
            small.style.color = '#10B981';
            small.textContent = '✓ Longueur suffisante';
        }
    });

    // Reset session
    if (window.location.search.includes('reset=1')) {
        window.location.href = 'avis.php';
    }
    </script>
</body>
</html>
