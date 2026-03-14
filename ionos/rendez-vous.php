<?php
/**
 * Frenchy Conciergerie - Prise de rendez-vous
 * Page publique pour planifier un RDV depuis le site marketing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// ============================================
// TRACKING DES VISITES
// ============================================
if ($security && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['ajax'])) {
    try {
        $security->trackVisit($_SERVER['REQUEST_URI'] ?? '/rendez-vous');
    } catch (Exception $e) {
        // Silently fail
    }
}

// ============================================
// CREATION TABLE prospection_leads SI NECESSAIRE
// ============================================
if ($conn) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS prospection_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(150),
            prenom VARCHAR(100),
            email VARCHAR(255),
            telephone VARCHAR(30),
            ville VARCHAR(100),
            source ENUM('simulateur','formulaire_contact','landing_page','concurrence','demarchage','recommandation','rdv_site','autre') NOT NULL DEFAULT 'autre',
            score INT DEFAULT 0,
            surface DECIMAL(10,2),
            capacite INT,
            tarif_nuit_estime DECIMAL(10,2),
            revenu_mensuel_estime DECIMAL(10,2),
            equipements JSON,
            statut ENUM('nouveau','contacte','rdv_planifie','rdv_fait','proposition','negocie','converti','perdu') DEFAULT 'nouveau',
            priorite ENUM('haute','moyenne','basse') DEFAULT 'moyenne',
            date_rdv DATETIME,
            type_rdv ENUM('telephone','visio','physique'),
            message_rdv TEXT,
            proprietaire_id INT,
            contrat_id INT,
            date_premier_contact DATE,
            date_derniere_interaction DATETIME,
            prochaine_action TEXT,
            date_prochaine_action DATE,
            notes TEXT,
            legacy_simulation_id INT,
            legacy_prospect_id INT,
            host_profile_id VARCHAR(100),
            nb_annonces INT,
            note_moyenne DECIMAL(3,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_statut (statut),
            INDEX idx_source (source),
            INDEX idx_score (score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Table already exists
    }
}

// ============================================
// RECUPERATION DES DONNEES
// ============================================
$settings = getAllSettings($conn);
$siteNom = $settings['site_nom'] ?? 'Frenchy Conciergerie';
$siteEmail = $settings['email'] ?? '';
$siteTel = $settings['telephone'] ?? '';

// Pre-remplissage depuis GET (venant du simulateur)
$prefillEmail = htmlspecialchars(trim($_GET['email'] ?? ''));
$prefillTel = htmlspecialchars(trim($_GET['tel'] ?? ''));

// ============================================
// TRAITEMENT DU FORMULAIRE (POST)
// ============================================
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$security || !$security->validateCSRFToken($csrfToken)) {
        $errors[] = 'Jeton de sécurité invalide. Veuillez recharger la page et réessayer.';
    }

    // Recuperation des champs
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $typeRdv = trim($_POST['type_rdv'] ?? '');
    $dateSouhaitee = trim($_POST['date_souhaitee'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation des champs requis
    if (empty($nom)) {
        $errors[] = 'Le nom est requis.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Une adresse email valide est requise.';
    }
    if (empty($telephone)) {
        $errors[] = 'Le numéro de téléphone est requis.';
    }
    if (!in_array($typeRdv, ['telephone', 'visio', 'physique'])) {
        $errors[] = 'Veuillez sélectionner un type de rendez-vous valide.';
    }

    // Traitement si pas d'erreurs
    if (empty($errors) && $conn) {
        try {
            // Calcul du score
            $score = 60; // base pour RDV
            if (!empty($email)) $score += 5;
            if (!empty($telephone)) $score += 15;
            $score = min(100, $score);

            // Priorite basee sur le type de RDV
            $priorite = 'moyenne';
            if ($typeRdv === 'physique') $priorite = 'haute';

            // Date RDV
            $dateRdv = null;
            if (!empty($dateSouhaitee)) {
                $dateRdv = $dateSouhaitee . ' 00:00:00';
            }

            // INSERT dans prospection_leads
            $stmt = $conn->prepare("INSERT INTO prospection_leads
                (nom, prenom, email, telephone, source, score, statut, priorite, type_rdv, date_rdv, message_rdv, date_premier_contact, date_derniere_interaction)
                VALUES (?, ?, ?, ?, 'rdv_site', ?, 'rdv_planifie', ?, ?, ?, ?, CURDATE(), NOW())");
            $stmt->execute([
                $nom, $prenom, $email, $telephone,
                $score, $priorite, $typeRdv, $dateRdv, $message
            ]);

            $leadId = $conn->lastInsertId();

            // Tracker la conversion
            try {
                $security->trackConversion('rdv', 'rdv_site', [
                    'lead_id' => $leadId,
                    'type_rdv' => $typeRdv,
                    'nom' => $nom
                ]);
            } catch (Exception $e) {}

            // ---- Email de confirmation au prospect ----
            $typeRdvLabel = ['telephone' => 'Téléphonique', 'visio' => 'Visioconférence', 'physique' => 'En personne'][$typeRdv] ?? $typeRdv;
            $dateLabel = !empty($dateSouhaitee) ? date('d/m/Y', strtotime($dateSouhaitee)) : 'À convenir';

            $confirmSubject = "Confirmation de votre demande de rendez-vous - $siteNom";
            $confirmBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; padding: 20px; background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>$siteNom</h1>
            <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0;'>Demande de rendez-vous</p>
        </div>
        <div style='padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <h2 style='color: #1E3A8A; margin-top: 0;'>Bonjour " . htmlspecialchars($prenom ?: $nom) . ",</h2>
            <p>Nous avons bien recu votre demande de rendez-vous. Notre equipe vous recontactera sous 24h pour confirmer le creneau.</p>

            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3B82F6;'>
                <h3 style='margin-top: 0; color: #1E3A8A;'>Recapitulatif</h3>
                <table style='width: 100%;'>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Type de RDV</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>$typeRdvLabel</td></tr>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Date souhaitee</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>$dateLabel</td></tr>
                </table>
            </div>

            <p style='margin-top: 20px;'>En attendant, n'hesitez pas a nous contacter :</p>
            <p style='text-align: center; margin-top: 15px;'>
                <a href='tel:" . preg_replace('/[^0-9+]/', '', $siteTel) . "' style='background: #1E3A8A; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>$siteTel</a>
                <a href='mailto:$siteEmail' style='background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>$siteEmail</a>
            </p>

            <p style='margin-top: 30px;'>Cordialement,<br><strong>L'equipe $siteNom</strong></p>
        </div>
        <div style='text-align: center; padding: 20px; background: #1E3A8A; border-radius: 0 0 10px 10px; color: white;'>
            <p style='margin: 0;'>$siteTel | $siteEmail</p>
        </div>
    </div>
</body>
</html>";

            $confirmHeaders = "MIME-Version: 1.0\r\n";
            $confirmHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
            $confirmHeaders .= "From: $siteNom <$siteEmail>\r\n";
            $confirmHeaders .= "Reply-To: $siteEmail\r\n";

            @mail($email, $confirmSubject, $confirmBody, $confirmHeaders);

            // ---- Email de notification admin ----
            $adminSubject = "Nouveau RDV demande - " . htmlspecialchars($nom) . " (" . $typeRdvLabel . ")";
            $adminBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #3B82F6; padding: 20px; border-radius: 10px 10px 0 0;'>
            <h2 style='color: white; margin: 0;'>Nouveau rendez-vous demande</h2>
        </div>
        <div style='padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <div style='text-align: center; padding: 15px; background: #DBEAFE; border-radius: 8px; margin-bottom: 20px;'>
                <div style='font-size: 24px; font-weight: bold; color: #1E3A8A;'>RDV $typeRdvLabel</div>
                <div style='color: #1E40AF; margin-top: 5px;'>Score : $score/100 | Priorite : $priorite</div>
            </div>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Nom</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($nom) . "</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Prenom</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($prenom) . "</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Email</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Telephone</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><a href='tel:" . htmlspecialchars($telephone) . "'>" . htmlspecialchars($telephone) . "</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Type RDV</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>$typeRdvLabel</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Date souhaitee</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>$dateLabel</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Message</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . nl2br(htmlspecialchars($message)) . "</td></tr>
            </table>
            <p style='margin-top: 20px; text-align: center;'>
                <a href='mailto:" . htmlspecialchars($email) . "' style='background: #1E3A8A; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Contacter ce prospect</a>
            </p>
        </div>
    </div>
</body>
</html>";

            $adminHeaders = "MIME-Version: 1.0\r\n";
            $adminHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
            $adminHeaders .= "From: $siteNom <$siteEmail>\r\n";
            $adminHeaders .= "Reply-To: " . htmlspecialchars($email) . "\r\n";

            @mail($siteEmail, $adminSubject, $adminBody, $adminHeaders);

            $success = true;

        } catch (PDOException $e) {
            error_log('rendez-vous.php: ' . $e->getMessage());
            $errors[] = 'Une erreur est survenue. Veuillez réessayer ou nous contacter directement.';
        }
    }
}

// Generer le token CSRF
$csrfToken = $security ? $security->generateCSRFToken() : '';

// Page title for header
$pageTitle = 'Prendre rendez-vous';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre rendez-vous - <?= e($siteNom) ?></title>
    <meta name="description" content="Prenez rendez-vous avec <?= e($siteNom) ?> pour discuter de votre projet de location courte durée.">
    <style>
        :root {
            --bleu-frenchy: #1E3A8A;
            --bleu-clair: #3B82F6;
            --gris-clair: #F3F4F6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            background: var(--gris-clair);
            line-height: 1.6;
        }
        .rdv-hero {
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            color: white;
            padding: 4rem 1.5rem 3rem;
            text-align: center;
        }
        .rdv-hero h1 {
            font-size: 2.2rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }
        .rdv-hero p {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            max-width: 600px;
            margin: 0 auto;
        }
        .rdv-container {
            max-width: 700px;
            margin: -2rem auto 3rem;
            padding: 0 1.5rem;
        }
        .rdv-form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }
        .rdv-form-card h2 {
            color: var(--bleu-frenchy);
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #374151;
            font-size: 0.95rem;
        }
        .form-group label .required {
            color: #EF4444;
            margin-left: 2px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: white;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .rdv-types {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin-top: 0.4rem;
        }
        .rdv-type-option {
            position: relative;
        }
        .rdv-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .rdv-type-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0.5rem;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .rdv-type-option label .rdv-icon {
            font-size: 1.8rem;
            margin-bottom: 0.4rem;
            display: block;
        }
        .rdv-type-option input[type="radio"]:checked + label {
            border-color: var(--bleu-clair);
            background: #EFF6FF;
            color: var(--bleu-frenchy);
        }
        .rdv-type-option label:hover {
            border-color: var(--bleu-clair);
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 0.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .alert-success {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #166534;
        }
        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }
        .success-card {
            text-align: center;
            padding: 3rem 2rem;
        }
        .success-card .success-icon {
            width: 80px;
            height: 80px;
            background: #10B981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .success-card .success-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        .success-card h2 {
            color: #10B981;
            font-size: 1.6rem;
            margin-bottom: 1rem;
        }
        .success-card p {
            color: #6B7280;
            font-size: 1.05rem;
            margin-bottom: 0.5rem;
        }
        .success-card .highlight {
            color: var(--bleu-frenchy);
            font-weight: 700;
            font-size: 1.15rem;
        }
        .success-card .btn-back {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.8rem 2rem;
            background: var(--bleu-frenchy);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .success-card .btn-back:hover {
            background: var(--bleu-clair);
        }
        .info-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
        }
        .info-bar-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6B7280;
            font-size: 0.9rem;
        }
        .info-bar-item svg {
            width: 20px;
            height: 20px;
            fill: var(--bleu-clair);
            flex-shrink: 0;
        }
        @media (max-width: 600px) {
            .rdv-hero { padding: 3rem 1rem 2rem; }
            .rdv-hero h1 { font-size: 1.6rem; }
            .rdv-form-card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .rdv-types { grid-template-columns: 1fr; }
            .info-bar { flex-direction: column; align-items: center; gap: 0.8rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<?php if ($success): ?>
    <!-- SUCCESS STATE -->
    <section class="rdv-hero">
        <h1>Rendez-vous demande !</h1>
        <p>Votre demande a ete enregistree avec succes.</p>
    </section>

    <div class="rdv-container">
        <div class="rdv-form-card success-card">
            <div class="success-icon">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
            </div>
            <h2>Demande confirmee</h2>
            <p>Merci <?= e($nom) ?>, votre demande de rendez-vous a bien ete enregistree.</p>
            <p class="highlight">Nous vous recontacterons sous 24h</p>
            <p>Un email de confirmation vous a ete envoye a l'adresse <strong><?= e($email) ?></strong>.</p>

            <div class="info-bar">
                <div class="info-bar-item">
                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                    Reponse sous 24h
                </div>
                <div class="info-bar-item">
                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    Confirmation envoyee
                </div>
            </div>

            <a href="index.php" class="btn-back">Retour a l'accueil</a>
        </div>
    </div>

<?php else: ?>
    <!-- FORM STATE -->
    <section class="rdv-hero">
        <h1>Prendre rendez-vous</h1>
        <p>Discutons de votre projet de location courte duree. Choisissez le format qui vous convient le mieux.</p>
    </section>

    <div class="rdv-container">
        <div class="rdv-form-card">
            <h2>Planifier un echange</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Erreur :</strong>
                    <ul style="margin: 0.5rem 0 0 1.2rem; padding: 0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="rendez-vous.php" id="rdvForm">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <input type="text" id="nom" name="nom" required placeholder="Votre nom"
                               value="<?= e($_POST['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prenom</label>
                        <input type="text" id="prenom" name="prenom" placeholder="Votre prenom"
                               value="<?= e($_POST['prenom'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required placeholder="votre@email.com"
                               value="<?= e($_POST['email'] ?? $prefillEmail) ?>">
                    </div>
                    <div class="form-group">
                        <label for="telephone">Telephone <span class="required">*</span></label>
                        <input type="tel" id="telephone" name="telephone" required placeholder="06 12 34 56 78"
                               value="<?= e($_POST['telephone'] ?? $prefillTel) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Type de rendez-vous <span class="required">*</span></label>
                    <div class="rdv-types">
                        <div class="rdv-type-option">
                            <input type="radio" id="type_telephone" name="type_rdv" value="telephone"
                                   <?= ($_POST['type_rdv'] ?? '') === 'telephone' ? 'checked' : '' ?>>
                            <label for="type_telephone">
                                <span class="rdv-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></span>
                                Telephone
                            </label>
                        </div>
                        <div class="rdv-type-option">
                            <input type="radio" id="type_visio" name="type_rdv" value="visio"
                                   <?= ($_POST['type_rdv'] ?? '') === 'visio' ? 'checked' : '' ?>>
                            <label for="type_visio">
                                <span class="rdv-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg></span>
                                Visio
                            </label>
                        </div>
                        <div class="rdv-type-option">
                            <input type="radio" id="type_physique" name="type_rdv" value="physique"
                                   <?= ($_POST['type_rdv'] ?? '') === 'physique' ? 'checked' : '' ?>>
                            <label for="type_physique">
                                <span class="rdv-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg></span>
                                En personne
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="date_souhaitee">Date souhaitee</label>
                    <input type="date" id="date_souhaitee" name="date_souhaitee"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= e($_POST['date_souhaitee'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="message">Message (optionnel)</label>
                    <textarea id="message" name="message" placeholder="Decrivez brievement votre projet ou vos questions..."><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit">Demander un rendez-vous</button>

                <div class="info-bar">
                    <div class="info-bar-item">
                        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                        Sans engagement
                    </div>
                    <div class="info-bar-item">
                        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
                        Reponse sous 24h
                    </div>
                    <div class="info-bar-item">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        100% gratuit
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default radio if none selected
    var radios = document.querySelectorAll('input[name="type_rdv"]');
    var anyChecked = false;
    radios.forEach(function(r) { if (r.checked) anyChecked = true; });
    if (!anyChecked && radios.length > 0) {
        radios[0].checked = true;
    }

    // Basic client-side validation feedback
    var form = document.getElementById('rdvForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            var nom = document.getElementById('nom').value.trim();
            var email = document.getElementById('email').value.trim();
            var tel = document.getElementById('telephone').value.trim();
            var typeSelected = document.querySelector('input[name="type_rdv"]:checked');

            if (!nom || !email || !tel || !typeSelected) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return false;
            }

            // Basic email check
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Veuillez saisir une adresse email valide.');
                return false;
            }
        });
    }
});
</script>

</body>
</html>
