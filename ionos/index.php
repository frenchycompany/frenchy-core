<?php
/**
 * Frenchy Conciergerie - Page d'accueil
 * Site dynamique basé sur la base de données FC_*
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// ============================================
// TRACKING DES VISITES (Analytics)
// ============================================
if ($security && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['ajax'])) {
    try {
        $security->trackVisit($_SERVER['REQUEST_URI'] ?? '/');
    } catch (Exception $e) {
        // Silently fail
    }
}

// ============================================
// TRAITEMENT AJAX SIMULATION (avant tout output)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_simulation'])) {
    header('Content-Type: application/json');

    if (!$conn) {
        echo json_encode(['success' => false, 'error' => 'Connexion DB échouée']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $surface = floatval($_POST['surface'] ?? 0);
    $capacite = intval($_POST['capacite'] ?? 0);
    $ville = trim($_POST['ville'] ?? '');
    $centreVille = intval($_POST['centre_ville'] ?? 0);
    $fibre = intval($_POST['fibre'] ?? 0);
    $equipementsSpeciaux = intval($_POST['equipements_speciaux'] ?? 0);
    $machineCafe = intval($_POST['machine_cafe'] ?? 0);
    $machineLaver = intval($_POST['machine_laver'] ?? 0);
    $autreEquipement = trim($_POST['autre_equipement'] ?? '');
    $tarifNuit = floatval($_POST['tarif_nuit_estime'] ?? 0);
    $revenuMensuel = floatval($_POST['revenu_mensuel_estime'] ?? 0);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $surface <= 0 || $capacite <= 0 || empty($telephone)) {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }

    try {
        // Créer la table si elle n'existe pas
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_simulations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            surface DECIMAL(10,2),
            capacite INT,
            ville VARCHAR(100),
            centre_ville TINYINT(1) DEFAULT 0,
            fibre TINYINT(1) DEFAULT 0,
            equipements_speciaux TINYINT(1) DEFAULT 0,
            machine_cafe TINYINT(1) DEFAULT 0,
            machine_laver TINYINT(1) DEFAULT 0,
            autre_equipement VARCHAR(255),
            tarif_nuit_estime DECIMAL(10,2),
            revenu_mensuel_estime DECIMAL(10,2),
            contacted TINYINT(1) DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ajouter les colonnes manquantes si la table existe déjà avec une ancienne structure
        $colonnes = ['telephone', 'surface', 'capacite', 'ville', 'centre_ville', 'fibre', 'equipements_speciaux', 'machine_cafe', 'machine_laver', 'autre_equipement', 'tarif_nuit_estime', 'revenu_mensuel_estime', 'contacted', 'notes'];
        $types = [
            'telephone' => 'VARCHAR(20)',
            'surface' => 'DECIMAL(10,2)',
            'capacite' => 'INT',
            'ville' => 'VARCHAR(100)',
            'centre_ville' => 'TINYINT(1) DEFAULT 0',
            'fibre' => 'TINYINT(1) DEFAULT 0',
            'equipements_speciaux' => 'TINYINT(1) DEFAULT 0',
            'machine_cafe' => 'TINYINT(1) DEFAULT 0',
            'machine_laver' => 'TINYINT(1) DEFAULT 0',
            'autre_equipement' => 'VARCHAR(255)',
            'tarif_nuit_estime' => 'DECIMAL(10,2)',
            'revenu_mensuel_estime' => 'DECIMAL(10,2)',
            'contacted' => 'TINYINT(1) DEFAULT 0',
            'notes' => 'TEXT'
        ];
        foreach ($colonnes as $col) {
            try {
                $conn->exec("ALTER TABLE FC_simulations ADD COLUMN $col {$types[$col]}");
            } catch (PDOException $e) {
                // Colonne existe déjà, on continue
            }
        }

        $stmt = $conn->prepare("INSERT INTO FC_simulations
            (email, telephone, surface, capacite, ville, centre_ville, fibre, equipements_speciaux, machine_cafe, machine_laver, autre_equipement, tarif_nuit_estime, revenu_mensuel_estime)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([$email, $telephone, $surface, $capacite, $ville, $centreVille, $fibre, $equipementsSpeciaux, $machineCafe, $machineLaver, $autreEquipement, $tarifNuit, $revenuMensuel]);
        $simulationId = $conn->lastInsertId();

        // Creer le lead dans le CRM
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS prospection_leads (
                id INT AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(150), prenom VARCHAR(100),
                email VARCHAR(255), telephone VARCHAR(30), ville VARCHAR(100),
                source ENUM('simulateur','formulaire_contact','landing_page','concurrence','demarchage','recommandation','rdv_site','autre') NOT NULL DEFAULT 'autre',
                score INT DEFAULT 0, surface DECIMAL(10,2), capacite INT,
                tarif_nuit_estime DECIMAL(10,2), revenu_mensuel_estime DECIMAL(10,2), equipements JSON,
                statut ENUM('nouveau','contacte','rdv_planifie','rdv_fait','proposition','negocie','converti','perdu') DEFAULT 'nouveau',
                priorite ENUM('haute','moyenne','basse') DEFAULT 'moyenne',
                date_rdv DATETIME, type_rdv ENUM('telephone','visio','physique'), message_rdv TEXT,
                proprietaire_id INT, contrat_id INT, date_premier_contact DATE,
                date_derniere_interaction DATETIME, prochaine_action TEXT, date_prochaine_action DATE,
                notes TEXT, legacy_simulation_id INT, legacy_prospect_id INT,
                host_profile_id VARCHAR(100), nb_annonces INT, note_moyenne DECIMAL(3,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email), INDEX idx_statut (statut), INDEX idx_source (source), INDEX idx_score (score DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Calculer le score
            $score = 40; // base simulateur
            if (!empty($email)) $score += 5;
            if (!empty($telephone)) $score += 15;
            if ($surface > 50) $score += 5;
            if ($capacite > 4) $score += 3;
            if ($revenuMensuel > 2000) $score += 7;
            $score = min(100, $score);

            $equipJson = json_encode([
                'centre_ville' => $centreVille, 'fibre' => $fibre,
                'equipements_speciaux' => $equipementsSpeciaux,
                'machine_cafe' => $machineCafe, 'machine_laver' => $machineLaver,
                'autre' => $autreEquipement
            ]);

            $leadStmt = $conn->prepare("INSERT INTO prospection_leads
                (email, telephone, ville, source, score, surface, capacite, tarif_nuit_estime, revenu_mensuel_estime, equipements, legacy_simulation_id)
                VALUES (?, ?, ?, 'simulateur', ?, ?, ?, ?, ?, ?, ?)");
            $leadStmt->execute([$email, $telephone, $ville, $score, $surface, $capacite, $tarifNuit, $revenuMensuel, $equipJson, $simulationId]);
        } catch (PDOException $e) {
            error_log('index.php lead creation: ' . $e->getMessage());
        }

        // Récupérer les settings pour l'email
        $settingsStmt = $conn->query("SELECT setting_key, setting_value FROM FC_settings");
        $emailSettings = [];
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $emailSettings[$row['setting_key']] = $row['setting_value'];
        }

        $siteNom = $emailSettings['site_nom'] ?? 'Frenchy Conciergerie';
        $siteEmail = $emailSettings['email'] ?? '';
        $siteTel = $emailSettings['telephone'] ?? '';
        $revenuAnnuel = round($revenuMensuel * 12);

        // Email au visiteur avec les résultats
        $resultatSubject = "Votre avis de valeur locative - $siteNom";
        $resultatBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; padding: 20px; background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>$siteNom</h1>
            <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0;'>Avis de valeur locative</p>
        </div>
        <div style='padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <h2 style='color: #1E3A8A; margin-top: 0;'>Bonjour,</h2>
            <p>Merci d'avoir utilisé notre simulateur de revenus locatifs. Voici le récapitulatif de votre avis de valeur :</p>

            <div style='text-align: center; padding: 25px; background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border-radius: 12px; margin: 20px 0; border: 2px solid #10B981;'>
                <div style='color: #6B7280; font-size: 14px;'>Revenu net estimé par an</div>
                <div style='font-size: 42px; font-weight: bold; color: #10B981;'>" . number_format($revenuAnnuel, 0, ',', ' ') . " €</div>
                <div style='color: #065F46; font-size: 14px; margin-top: 5px;'>soit " . number_format($revenuMensuel, 0, ',', ' ') . " € / mois</div>
            </div>

            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #1E3A8A; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;'>Caractéristiques de votre bien</h3>
                <table style='width: 100%;'>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Surface</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>" . $surface . " m²</td></tr>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Capacité</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>" . $capacite . " personnes</td></tr>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Ville</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>" . htmlspecialchars($ville) . "</td></tr>
                    <tr><td style='padding: 8px 0; color: #6B7280;'>Tarif/nuit estimé</td><td style='padding: 8px 0; text-align: right; font-weight: bold;'>" . number_format($tarifNuit, 0, ',', ' ') . " €</td></tr>
                </table>
            </div>

            <div style='background: #FEF3C7; padding: 15px; border-radius: 8px; border-left: 4px solid #F59E0B; margin: 20px 0;'>
                <p style='margin: 0; font-size: 13px; color: #92400E;'><strong>Note :</strong> Cette estimation est fournie à titre indicatif et ne constitue pas un engagement contractuel. Les revenus réels peuvent varier selon la saisonnalité, la demande et la qualité de la gestion.</p>
            </div>

            <div style='text-align: center; margin-top: 30px;'>
                <p style='color: #1E3A8A; font-weight: bold;'>Vous souhaitez en savoir plus ?</p>
                <p>Contactez-nous pour discuter de votre projet !</p>
                <p style='margin-top: 15px;'>
                    <a href='tel:" . preg_replace('/[^0-9+]/', '', $siteTel) . "' style='background: #1E3A8A; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>📞 $siteTel</a>
                    <a href='mailto:$siteEmail' style='background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>📧 Nous contacter</a>
                </p>
            </div>

            <p style='margin-top: 30px;'>Cordialement,<br><strong>L'équipe $siteNom</strong></p>
        </div>
        <div style='text-align: center; padding: 20px; background: #1E3A8A; border-radius: 0 0 10px 10px; color: white;'>
            <p style='margin: 0;'>$siteTel | $siteEmail</p>
        </div>
    </div>
</body>
</html>";

        $resultatHeaders = "MIME-Version: 1.0\r\n";
        $resultatHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
        $resultatHeaders .= "From: $siteNom <$siteEmail>\r\n";
        $resultatHeaders .= "Reply-To: $siteEmail\r\n";

        @mail($email, $resultatSubject, $resultatBody, $resultatHeaders);

        // Notification admin
        $adminSubject = "Nouvelle simulation - " . htmlspecialchars($ville) . " - " . number_format($revenuAnnuel, 0, ',', ' ') . "€/an";
        $adminBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #10B981; padding: 20px; border-radius: 10px 10px 0 0;'>
            <h2 style='color: white; margin: 0;'>📊 Nouvelle simulation de revenus</h2>
        </div>
        <div style='padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <div style='text-align: center; padding: 15px; background: #DCFCE7; border-radius: 8px; margin-bottom: 20px;'>
                <div style='font-size: 28px; font-weight: bold; color: #10B981;'>" . number_format($revenuAnnuel, 0, ',', ' ') . " €/an</div>
                <div style='color: #065F46;'>(" . number_format($revenuMensuel, 0, ',', ' ') . " €/mois)</div>
            </div>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Email</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Téléphone</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><a href='tel:" . htmlspecialchars($telephone) . "'>" . htmlspecialchars($telephone) . "</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Ville</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($ville) . "</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Surface</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . $surface . " m²</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Capacité</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . $capacite . " personnes</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Tarif/nuit</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . number_format($tarifNuit, 0, ',', ' ') . " €</td></tr>
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

        // Tracker la conversion
        try {
            $securityTemp = new Security($conn);
            $securityTemp->trackConversion('simulation', 'simulateur', ['ville' => $ville, 'revenu_annuel' => $revenuAnnuel]);
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Récupération des données
$settings = getAllSettings($conn);
$services = getServices($conn);
$tarifs = getTarifs($conn);
$logements = getLogements($conn);
$avis = getAvis($conn);
$distinctions = getDistinctions($conn);

// Récupération des sections actives
$sectionsActives = [];
try {
    $stmt = $conn->query("SELECT section_key, actif FROM FC_sections");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sectionsActives[$row['section_key']] = (bool)$row['actif'];
    }
} catch (PDOException $e) {
    // Table n'existe pas, toutes les sections sont actives par défaut
}
// Par défaut, toutes les sections sont actives
$defaultSections = ['hero', 'services', 'tarifs', 'simulateur', 'galerie', 'distinctions', 'avis', 'blog', 'legal', 'contact'];
foreach ($defaultSections as $sec) {
    if (!isset($sectionsActives[$sec])) {
        $sectionsActives[$sec] = true;
    }
}
// Fonction helper pour vérifier si une section est active
function sectionActive($key) {
    global $sectionsActives;
    return $sectionsActives[$key] ?? true;
}

// Récupération de la config du simulateur
$simulateurConfig = [
    'tarif_base_couchage' => 15,
    'majoration_m2' => 0.5,
    'majoration_centre_ville' => 10,
    'majoration_fibre' => 5,
    'majoration_equipements_speciaux' => 15,
    'majoration_machine_cafe' => 3,
    'majoration_machine_laver' => 5,
    'taux_occupation' => 70,
    'commission' => 24,
    'cout_menage_m2' => 1,
    'rotations_mois' => 12,
];
try {
    $stmt = $conn->query("SELECT config_key, config_value FROM FC_simulateur_config");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $simulateurConfig[$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {
    // Table n'existe pas, on garde les valeurs par défaut
}

// Traitement du formulaire de contact
$contactSuccess = false;
$contactError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Validation CSRF
    $csrfValid = true;
    if ($security) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        $csrfValid = $security->validateCSRFToken($csrfToken);
    }

    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$csrfValid) {
        $contactError = 'Session expirée. Veuillez rafraîchir la page et réessayer.';
    } elseif (empty($nom) || empty($email) || empty($message)) {
        $contactError = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Veuillez entrer une adresse email valide.';
    } else {
        if (saveContact($conn, $nom, $email, $telephone, $sujet, $message)) {
            $contactSuccess = true;

            // Tracker la conversion
            if ($security) {
                $security->trackConversion('contact', 'formulaire', ['sujet' => $sujet]);
            }

            // Email de confirmation au visiteur
            $siteNom = $settings['site_nom'] ?? 'Frenchy Conciergerie';
            $siteEmail = $settings['email'] ?? '';
            $siteTel = $settings['telephone'] ?? '';

            $confirmationSubject = "Confirmation de votre message - $siteNom";
            $confirmationBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; padding: 20px; background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>$siteNom</h1>
        </div>
        <div style='padding: 30px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <h2 style='color: #1E3A8A; margin-top: 0;'>Bonjour " . htmlspecialchars($nom) . ",</h2>
            <p>Nous avons bien reçu votre message et nous vous en remercions.</p>
            <p>Notre équipe reviendra vers vous dans les plus brefs délais.</p>

            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #1E3A8A;'>
                <h3 style='margin-top: 0; color: #1E3A8A;'>Récapitulatif de votre message :</h3>
                <p><strong>Sujet :</strong> " . htmlspecialchars($sujet ?: 'Non précisé') . "</p>
                <p><strong>Message :</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
            </div>

            <p>En attendant, n'hésitez pas à consulter notre site pour découvrir nos services.</p>
            <p style='margin-top: 30px;'>Cordialement,<br><strong>L'équipe $siteNom</strong></p>
        </div>
        <div style='text-align: center; padding: 20px; background: #1E3A8A; border-radius: 0 0 10px 10px; color: white;'>
            <p style='margin: 0;'>$siteTel | $siteEmail</p>
        </div>
    </div>
</body>
</html>";

            $confirmationHeaders = "MIME-Version: 1.0\r\n";
            $confirmationHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
            $confirmationHeaders .= "From: $siteNom <$siteEmail>\r\n";
            $confirmationHeaders .= "Reply-To: $siteEmail\r\n";

            @mail($email, $confirmationSubject, $confirmationBody, $confirmationHeaders);

            // Notification à l'admin
            $adminSubject = "Nouveau message de contact - $nom";
            $adminBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: #1E3A8A; padding: 20px; border-radius: 10px 10px 0 0;'>
            <h2 style='color: white; margin: 0;'>📩 Nouveau message de contact</h2>
        </div>
        <div style='padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb;'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Nom :</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($nom) . "</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Email :</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Téléphone :</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($telephone ?: 'Non renseigné') . "</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'><strong>Sujet :</strong></td><td style='padding: 10px; border-bottom: 1px solid #e5e7eb;'>" . htmlspecialchars($sujet ?: 'Non précisé') . "</td></tr>
            </table>
            <div style='margin-top: 20px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #3B82F6;'>
                <strong>Message :</strong><br>" . nl2br(htmlspecialchars($message)) . "
            </div>
            <p style='margin-top: 20px; text-align: center;'>
                <a href='mailto:" . htmlspecialchars($email) . "' style='background: #1E3A8A; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Répondre à " . htmlspecialchars($nom) . "</a>
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

        } else {
            $contactError = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Gestion locative Airbnb & Booking dans l'Oise</title>
    <meta name="description" content="<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> : conciergerie Airbnb et gestion locative saisonnière dans la région de Compiègne (Oise). Nous gérons votre bien de A à Z pour optimiser vos revenus.">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($settings['site_url'] ?? 'https://frenchyconciergerie.fr') ?>">
    <meta property="og:title" content="<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Gestion locative Airbnb & Booking">
    <meta property="og:description" content="Conciergerie Airbnb et gestion locative saisonnière dans la région de Compiègne (Oise). Nous gérons votre bien de A à Z.">
    <meta property="og:image" content="<?= e($settings['site_url'] ?? '') ?>/logo.png">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>">
    <meta name="twitter:description" content="Conciergerie Airbnb et gestion locative saisonnière dans la région de Compiègne (Oise).">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "@id": "<?= e($settings['site_url'] ?? 'https://frenchyconciergerie.fr') ?>",
        "name": "<?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>",
        "description": "Conciergerie Airbnb et gestion locative saisonnière dans la région de Compiègne (Oise)",
        "url": "<?= e($settings['site_url'] ?? 'https://frenchyconciergerie.fr') ?>",
        "logo": "<?= e($settings['site_url'] ?? '') ?>/logo.png",
        "image": "<?= e($settings['site_url'] ?? '') ?>/logo.png",
        "telephone": "<?= e($settings['telephone'] ?? '') ?>",
        "email": "<?= e($settings['email'] ?? '') ?>",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "<?= e(explode("\n", $settings['adresse'] ?? '')[0] ?? '') ?>",
            "addressLocality": "Compiègne",
            "addressRegion": "Oise",
            "postalCode": "60200",
            "addressCountry": "FR"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": "49.4178",
            "longitude": "2.8261"
        },
        "areaServed": {
            "@type": "GeoCircle",
            "geoMidpoint": {
                "@type": "GeoCoordinates",
                "latitude": "49.4178",
                "longitude": "2.8261"
            },
            "geoRadius": "50000"
        },
        "priceRange": "€€",
        "openingHoursSpecification": {
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
            "opens": "09:00",
            "closes": "19:00"
        },
        "sameAs": [
            "<?= e($settings['facebook'] ?? '') ?>",
            "<?= e($settings['instagram'] ?? '') ?>"
        ],
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Services de conciergerie",
            "itemListElement": [
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Gestion locative Airbnb",
                        "description": "Gestion complète de votre bien sur Airbnb, Booking et autres plateformes"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Conciergerie",
                        "description": "Accueil des voyageurs, ménage, gestion du linge"
                    }
                },
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "Optimisation des annonces",
                        "description": "Photos professionnelles, rédaction d'annonces, tarification dynamique"
                    }
                }
            ]
        }
    }
    </script>

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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--gris-fonce);
        }

        /* Header */
        header {
            background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);
            color: white;
            padding: 2rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }

        .contact-header {
            text-align: right;
        }

        .contact-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .contact-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.95) 0%, rgba(59, 130, 246, 0.95) 100%),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%231E3A8A" width="1200" height="600"/><path fill="%233B82F6" opacity="0.1" d="M0 300 Q300 150 600 300 T1200 300 V600 H0 Z"/></svg>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .hero h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        /* Services Section */
        .services {
            padding: 4rem 0;
            background: white;
        }

        .section-title {
            text-align: center;
            font-size: 2.2rem;
            color: var(--bleu-frenchy);
            margin-bottom: 3rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .service-card {
            background: var(--gris-clair);
            padding: 2rem;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .service-card h3 {
            color: var(--bleu-frenchy);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .service-card ul {
            list-style: none;
            padding-left: 0;
        }

        .service-card li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .service-card li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--bleu-clair);
            font-weight: bold;
        }

        /* Tarifs */
        .tarifs {
            padding: 4rem 0;
            background: var(--gris-clair);
        }

        .tarif-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .tarif-card {
            background: white;
            padding: 2.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .tarif-card h3 {
            color: var(--bleu-frenchy);
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .tarif-price {
            font-size: 3rem;
            font-weight: bold;
            color: var(--bleu-clair);
            margin: 1rem 0;
        }

        .tarif-description {
            color: var(--gris-fonce);
            margin-top: 1rem;
        }

        /* Avis Section */
        .avis {
            padding: 4rem 0;
            background: white;
        }

        .avis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .avis-card {
            background: var(--gris-clair);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .avis-card h4 {
            color: var(--bleu-frenchy);
            margin-bottom: 0.5rem;
        }

        .avis-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .stars {
            color: #FFA500;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        /* Informations légales */
        .legal-info {
            padding: 3rem 0;
            background: var(--gris-clair);
        }

        .legal-box {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--bleu-clair);
        }

        .legal-box h3 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .legal-box p, .legal-box ul {
            margin-bottom: 0.8rem;
        }

        .legal-box ul {
            padding-left: 1.5rem;
        }

        .highlight {
            background: #FEF3C7;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border-left: 4px solid #F59E0B;
        }

        .mediateur-box {
            background: #DBEAFE;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--bleu-clair);
        }

        /* Footer */
        footer {
            background: var(--bleu-frenchy);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: white;
        }

        .footer-section p, .footer-section a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
        }

        .footer-section a:hover {
            color: white;
            text-decoration: underline;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h2 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .contact-header h1 {
                font-size: 1.5rem;
            }

            .tarif-price {
                font-size: 2.5rem;
            }
        }

        /* Cookie Consent Banner */
        .cookie-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 58, 138, 0.98);
            color: white;
            padding: 1.5rem;
            z-index: 9999;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.3);
            display: none;
        }

        .cookie-banner.show {
            display: block;
        }

        .cookie-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .cookie-text {
            flex: 1;
            min-width: 300px;
        }

        .cookie-text h4 {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .cookie-text p {
            font-size: 0.9rem;
            opacity: 0.95;
            line-height: 1.5;
        }

        .cookie-text a {
            color: #93C5FD;
            text-decoration: underline;
        }

        .cookie-buttons {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .cookie-btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .cookie-btn-accept {
            background: #10B981;
            color: white;
        }

        .cookie-btn-accept:hover {
            background: #059669;
        }

        .cookie-btn-refuse {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .cookie-btn-refuse:hover {
            background: rgba(255,255,255,0.1);
        }

        .cookie-btn-settings {
            background: transparent;
            color: #93C5FD;
            border: 2px solid #93C5FD;
        }

        .cookie-btn-settings:hover {
            background: rgba(147, 197, 253, 0.1);
        }

        /* Cookie Settings Modal */
        .cookie-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .cookie-modal.show {
            display: flex;
        }

        .cookie-modal-content {
            background: white;
            border-radius: 10px;
            max-width: 600px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 2rem;
        }

        .cookie-modal-content h3 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
        }

        .cookie-option {
            padding: 1rem;
            margin: 0.8rem 0;
            background: var(--gris-clair);
            border-radius: 8px;
        }

        .cookie-option label {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: pointer;
            font-weight: bold;
        }

        .cookie-option p {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            margin-left: 2rem;
        }

        .cookie-option input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .cookie-modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .cookie-content {
                flex-direction: column;
                text-align: center;
            }
            .cookie-buttons {
                justify-content: center;
            }
        }

        /* RGPD Checkbox in forms */
        .rgpd-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #F0FDF4;
            border-radius: 8px;
            border: 1px solid #BBF7D0;
        }

        .rgpd-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .rgpd-checkbox label {
            font-size: 0.9rem;
            color: var(--gris-fonce);
            line-height: 1.5;
        }

        .rgpd-checkbox a {
            color: var(--bleu-clair);
        }

        /* Privacy Policy Section */
        .privacy-section {
            padding: 4rem 0;
            background: white;
        }

        .privacy-content {
            max-width: 900px;
            margin: 0 auto;
        }

        .privacy-content h3 {
            color: var(--bleu-frenchy);
            margin: 2rem 0 1rem;
            font-size: 1.3rem;
        }

        .privacy-content p, .privacy-content ul {
            margin-bottom: 1rem;
            line-height: 1.7;
        }

        .privacy-content ul {
            padding-left: 1.5rem;
        }

        .privacy-content li {
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            display: inline-block;
            background: var(--rouge-frenchy);
            color: white;
            padding: 1rem 2rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .btn-primary:hover {
            background: #DC2626;
        }

        .btn-primary:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
        }

        /* Loading Spinner */
        .btn-loading {
            position: relative;
            color: transparent !important;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            display: none;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        .success-message.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Galerie Photos */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .gallery-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .gallery-image {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 75%;
            overflow: hidden;
        }

        .gallery-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .gallery-item:hover .gallery-image img {
            transform: scale(1.1);
        }

        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(30, 58, 138, 0.95), transparent);
            color: white;
            padding: 2rem 1.5rem 1.5rem;
            transform: translateY(60%);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover .gallery-overlay {
            transform: translateY(0);
        }

        .gallery-text h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: white;
        }

        .gallery-text p {
            font-size: 0.95rem;
            opacity: 0.9;
            color: white;
        }

        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .gallery-overlay {
                transform: translateY(0);
                background: linear-gradient(to top, rgba(30, 58, 138, 0.85), transparent);
            }
        }

        /* Formulaire de contact */
        .contact-form {
            max-width: 600px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: var(--gris-fonce);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
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

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <img src="frenchyconciergerie.png.png" alt="Logo <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?>" class="logo">
                </div>
                <div class="contact-header">
                    <h1><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></h1>
                    <p><?= e($settings['adresse'] ?? '') ?></p>
                    <p><?= e($settings['telephone'] ?? '') ?> | <?= e($settings['email'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </header>

    <?php if (sectionActive('hero')): ?>
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h2><?= e($settings['site_slogan'] ?? 'Conciergerie Airbnb & Gestion Locative Saisonnière') ?></h2>
            <p><?= e($settings['site_description'] ?? 'Nous gérons votre bien de A à Z pour optimiser vos revenus') ?></p>
            <a href="#contact" class="btn-primary">Contactez-nous</a>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('services')): ?>
    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <h2 class="section-title">Nos Services</h2>

            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <h3><?= e($service['icone']) ?> <?= e($service['titre']) ?></h3>
                    <?php if (!empty($service['carte_info'])): ?>
                    <p><strong><?= e($service['carte_info']) ?></strong></p>
                    <?php endif; ?>
                    <ul>
                        <?php
                        $items = json_decode($service['liste_items'], true);
                        if (is_array($items)):
                            foreach ($items as $item):
                        ?>
                        <li><?= e($item) ?></li>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Disclaimer informatif -->
            <div style="margin-top: 2rem; padding: 1rem 1.5rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; text-align: center; font-size: 0.9rem; color: #64748B;">
                Les informations présentées sur ce site sont fournies à titre informatif. Toute prestation fait l'objet d'un échange préalable et, le cas échéant, d'un contrat distinct signé. Aucun engagement n'est pris directement via ce site.
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('tarifs')): ?>
    <!-- Tarifs Section -->
    <section class="tarifs" id="tarifs">
        <div class="container">
            <h2 class="section-title">Tarifs Transparents</h2>

            <div class="tarif-cards">
                <?php foreach ($tarifs as $tarif): ?>
                <div class="tarif-card">
                    <h3><?= e($tarif['titre']) ?></h3>
                    <div class="tarif-price">
                        <?= intval($tarif['montant'] ?? $tarif['pourcentage'] ?? 0) ?><?= ($tarif['type_tarif'] ?? 'pourcentage') === 'euro' ? '€' : '%' ?>
                    </div>
                    <p class="tarif-description"><strong><?= e($tarif['description']) ?></strong></p>
                    <p class="tarif-description"><?= e($tarif['details']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="highlight">
                <h3>Ce qui est inclus :</h3>
                <ul>
                    <li>✓ Gestion complète des réservations</li>
                    <li>✓ Ménage professionnel entre chaque séjour</li>
                    <li>✓ Accueil des voyageurs 7j/7</li>
                    <li>✓ Maintenance et dépannages</li>
                    <li>✓ Optimisation des revenus</li>
                    <li>✓ Reporting mensuel détaillé</li>
                </ul>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('simulateur')): ?>
    <!-- Simulateur de Revenus -->
    <section class="services" id="simulateur" style="background: linear-gradient(135deg, var(--bleu-frenchy) 0%, var(--bleu-clair) 100%);">
        <div class="container">
            <h2 class="section-title" style="color: white;">Estimez vos Revenus Locatifs</h2>
            <p style="text-align: center; color: rgba(255,255,255,0.9); margin-bottom: 2rem; max-width: 700px; margin-left: auto; margin-right: auto;">
                Découvrez le potentiel de votre bien en location saisonnière grâce à notre simulateur gratuit.
            </p>

            <div style="background: white; border-radius: 15px; padding: 2rem; max-width: 800px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <form id="simulateurForm">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="sim_surface">Surface (m²)</label>
                            <input type="number" id="sim_surface" name="surface" min="10" max="500" required placeholder="Ex: 45">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="sim_capacite">Capacité (personnes)</label>
                            <input type="number" id="sim_capacite" name="capacite" min="1" max="20" required placeholder="Ex: 4">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="sim_ville">Ville</label>
                            <input type="text" id="sim_ville" name="ville" required placeholder="Ex: Compiègne" list="villes-list">
                            <datalist id="villes-list">
                                <option value="Compiègne">
                                <option value="Paris">
                                <option value="Margny-lès-Compiègne">
                                <option value="Venette">
                                <option value="Lacroix-Saint-Ouen">
                            </datalist>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="sim_email">Votre email *</label>
                            <input type="email" id="sim_email" name="email" required placeholder="votre@email.com">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="sim_telephone">Votre téléphone *</label>
                            <input type="tel" id="sim_telephone" name="telephone" required placeholder="06 12 34 56 78">
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; padding: 1rem; background: #F3F4F6; border-radius: 8px;">
                        <label style="display: block; margin-bottom: 0.8rem; font-weight: bold; color: var(--gris-fonce);">Équipements du logement :</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.8rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="sim_centre_ville" name="centre_ville" style="width: auto;">
                                <span>Centre-ville</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="sim_fibre" name="fibre" style="width: auto;">
                                <span>Fibre optique</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="sim_equipements_speciaux" name="equipements_speciaux" style="width: auto;">
                                <span>Équipements spéciaux (sauna, jacuzzi...)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="sim_machine_cafe" name="machine_cafe" style="width: auto;">
                                <span>Machine café Nespresso</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" id="sim_machine_laver" name="machine_laver" style="width: auto;">
                                <span>Machine à laver</span>
                            </label>
                        </div>
                        <div style="margin-top: 0.8rem;">
                            <label for="sim_autre" style="display: block; margin-bottom: 0.3rem; font-size: 0.9rem;">Autre équipement :</label>
                            <input type="text" id="sim_autre" name="autre_equipement" placeholder="Précisez..." style="width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 5px;">
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; padding: 1rem; background: #FEF3C7; border-radius: 8px;">
                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; color: #92400E;">
                            <input type="checkbox" id="sim_conditions" name="conditions" style="width: auto; margin-top: 3px;" required>
                            <span><?= e($settings['simulateur_conditions'] ?? 'J\'accepte que mes données soient utilisées pour recevoir un avis de valeur personnalisé et être recontacté par Frenchy Conciergerie. Je comprends que cet avis est indicatif et ne constitue pas une garantie de revenus.') ?> <a href="#legal" style="color: #1E3A8A;">Voir les mentions légales</a></span>
                        </label>
                    </div>
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <button type="button" onclick="calculerRevenus()" class="btn-primary" style="background: var(--bleu-frenchy); padding: 1rem 3rem; font-size: 1.1rem;">
                        Calculer mes revenus éventuels
                    </button>
                </div>

                <div id="simulateurResultat" style="display: none; margin-top: 2rem; padding: 2rem; background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border-radius: 15px; border: 2px solid #10B981;">
                    <h3 style="color: #065F46; margin-bottom: 1.5rem; text-align: center; font-size: 1.4rem;">Avis de valeur locative</h3>

                    <!-- Revenu annuel mis en avant -->
                    <div style="text-align: center; padding: 1.5rem; background: white; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);">
                        <div style="color: #6B7280; font-size: 1rem; margin-bottom: 0.5rem;">Revenu net par an</div>
                        <div id="res_annuel" style="font-size: 3rem; font-weight: bold; color: #10B981; line-height: 1;">-</div>
                        <div style="color: #065F46; font-size: 0.9rem; margin-top: 0.5rem;">soit <span id="res_mensuel_inline" style="font-weight: bold;">-</span> / mois</div>
                    </div>

                    <!-- Détail des calculs -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.8rem; text-align: center; padding: 1rem; background: white; border-radius: 10px;">
                        <div style="padding: 0.8rem; border-right: 1px solid #E5E7EB;">
                            <div id="res_tarif_nuit" style="font-size: 1.3rem; font-weight: bold; color: var(--bleu-frenchy);">-</div>
                            <div style="color: #6B7280; font-size: 0.75rem;">Tarif/nuit</div>
                        </div>
                        <div style="padding: 0.8rem; border-right: 1px solid #E5E7EB;">
                            <div id="res_brut" style="font-size: 1.3rem; font-weight: bold; color: #6B7280;">-</div>
                            <div style="color: #6B7280; font-size: 0.75rem;">Brut/mois</div>
                        </div>
                        <div style="padding: 0.8rem; border-right: 1px solid #E5E7EB;">
                            <div id="res_menage" style="font-size: 1.3rem; font-weight: bold; color: #EF4444;">-</div>
                            <div style="color: #6B7280; font-size: 0.75rem;">Frais/mois</div>
                        </div>
                        <div style="padding: 0.8rem;">
                            <div id="res_mensuel" style="font-size: 1.3rem; font-weight: bold; color: #10B981;">-</div>
                            <div style="color: #6B7280; font-size: 0.75rem;">Net/mois</div>
                        </div>
                    </div>

                    <p style="margin-top: 1rem; font-size: 0.8rem; color: #6B7280; text-align: center;">
                        * Estimation basée sur : taux d'occupation <?= e($simulateurConfig['taux_occupation'] ?? 70) ?>% | commission <?= e($simulateurConfig['commission'] ?? 24) ?>% | ~<?= e($simulateurConfig['rotations_mois'] ?? 12) ?> rotations/mois
                    </p>

                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="#contact" class="btn-primary" style="background: #10B981; padding: 1rem 2.5rem; font-size: 1.1rem;">Contactez-nous pour aller plus loin</a>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background: #FEF3C7; border-radius: 8px; font-size: 0.85rem; color: #92400E;">
                    <strong>Mentions légales :</strong> <?= e($settings['simulateur_mentions'] ?? 'Cet avis de valeur est fourni à titre purement indicatif et informatif. Il ne constitue en aucun cas une garantie de revenus, un engagement contractuel, ni une promesse de résultats. Les montants affichés sont calculés sur la base de moyennes de marché observées et peuvent varier significativement selon de nombreux facteurs non pris en compte : saisonnalité, état et qualité du logement, équipements, qualité des photos et annonces, localisation exacte, concurrence locale, événements, réglementation locale, etc. Seule une étude personnalisée réalisée par nos experts peut fournir une analyse précise de votre bien. En utilisant ce simulateur, vous reconnaissez avoir pris connaissance de ces limitations et consentez à être recontacté par nos services pour une étude approfondie de votre projet.') ?>
                </div>
            </div>
        </div>
    </section>

    <script>
    function calculerRevenus() {


        const surface = parseFloat(document.getElementById('sim_surface').value) || 0;
        const capacite = parseInt(document.getElementById('sim_capacite').value) || 0;
        const ville = document.getElementById('sim_ville').value.toLowerCase();
        const email = document.getElementById('sim_email').value;
        const telephone = document.getElementById('sim_telephone').value.trim();



        // Récupération des équipements
        const centreVille = document.getElementById('sim_centre_ville').checked;
        const fibre = document.getElementById('sim_fibre').checked;
        const equipementsSpeciaux = document.getElementById('sim_equipements_speciaux').checked;
        const machineCafe = document.getElementById('sim_machine_cafe').checked;
        const machineLaver = document.getElementById('sim_machine_laver').checked;
        const autreEquipement = document.getElementById('sim_autre').value;

        // Vérification des conditions
        const conditions = document.getElementById('sim_conditions').checked;

        if (!conditions) {
            alert('Veuillez accepter les conditions pour continuer.');
            return;
        }

        if (surface < 10 || capacite < 1 || !email || !telephone) {
            alert('Veuillez remplir tous les champs obligatoires (surface, capacité, email et téléphone).');
            return;
        }

        // Validation basique du téléphone (au moins 10 chiffres)
        const phoneDigits = telephone.replace(/\D/g, '');
        if (phoneDigits.length < 10) {
            alert('Veuillez entrer un numéro de téléphone valide.');
            return;
        }

        // Paramètres depuis la config admin
        const tarifBaseCouchage = <?= json_encode(floatval($simulateurConfig['tarif_base_couchage'] ?? 15)) ?>;
        const majorationM2 = <?= json_encode(floatval($simulateurConfig['majoration_m2'] ?? 0.5)) ?>;
        const coutMenageM2 = <?= json_encode(floatval($simulateurConfig['cout_menage_m2'] ?? 1)) ?>;
        const rotationsMois = <?= json_encode(intval($simulateurConfig['rotations_mois'] ?? 12)) ?>;
        const tauxOccupationConfig = <?= json_encode(floatval($simulateurConfig['taux_occupation'] ?? 70)) ?> / 100;
        const commissionConfig = <?= json_encode(floatval($simulateurConfig['commission'] ?? 24)) ?> / 100;


        // Majorations équipements (en %) depuis config
        const majorations = {
            centreVille: <?= json_encode(floatval($simulateurConfig['majoration_centre_ville'] ?? 10)) ?>,
            fibre: <?= json_encode(floatval($simulateurConfig['majoration_fibre'] ?? 5)) ?>,
            equipementsSpeciaux: <?= json_encode(floatval($simulateurConfig['majoration_equipements_speciaux'] ?? 15)) ?>,
            machineCafe: <?= json_encode(floatval($simulateurConfig['majoration_machine_cafe'] ?? 3)) ?>,
            machineLaver: <?= json_encode(floatval($simulateurConfig['majoration_machine_laver'] ?? 5)) ?>
        };

        // Majorations par ville (en %)
        const majorationsVille = {
            'compiègne': 10, 'compiegne': 10,
            'paris': 50,
            'margny': 5,
            'venette': 5,
            'lacroix': 0,
            'jaux': 5,
            'default': 0
        };

        // Calcul du tarif de base
        let tarifBase = (tarifBaseCouchage * capacite) + (majorationM2 * surface);

        // Ajout des majorations équipements
        let totalMajorationEquip = 0;
        if (centreVille) totalMajorationEquip += majorations.centreVille;
        if (fibre) totalMajorationEquip += majorations.fibre;
        if (equipementsSpeciaux) totalMajorationEquip += majorations.equipementsSpeciaux;
        if (machineCafe) totalMajorationEquip += majorations.machineCafe;
        if (machineLaver) totalMajorationEquip += majorations.machineLaver;

        // Majoration ville
        let majorationVille = majorationsVille['default'];
        for (const [key, val] of Object.entries(majorationsVille)) {
            if (ville.includes(key) || key.includes(ville)) {
                majorationVille = val;
                break;
            }
        }

        // Calcul final avec valeurs de config
        const tarifNuit = Math.round(tarifBase * (1 + (totalMajorationEquip + majorationVille) / 100));
        const nuitsParMois = 30 * tauxOccupationConfig;
        const revenuBrut = tarifNuit * nuitsParMois;
        const commission = revenuBrut * commissionConfig;

        // Calcul du coût ménage mensuel
        const coutMenageParLocation = surface * coutMenageM2;
        const coutMenageMensuel = coutMenageParLocation * rotationsMois;

        // Revenu net après commission et ménage
        const revenuNet = revenuBrut - commission - coutMenageMensuel;
        const revenuAnnuel = revenuNet * 12;

        document.getElementById('res_tarif_nuit').textContent = tarifNuit + ' €';
        document.getElementById('res_brut').textContent = Math.round(revenuBrut).toLocaleString('fr-FR') + ' €';
        document.getElementById('res_menage').textContent = '-' + Math.round(coutMenageMensuel + commission).toLocaleString('fr-FR') + ' €';
        document.getElementById('res_mensuel').textContent = Math.round(revenuNet).toLocaleString('fr-FR') + ' €';
        document.getElementById('res_annuel').textContent = Math.round(revenuAnnuel).toLocaleString('fr-FR') + ' €';
        document.getElementById('res_mensuel_inline').textContent = Math.round(revenuNet).toLocaleString('fr-FR') + ' €';
        document.getElementById('simulateurResultat').style.display = 'block';

        // Scroll vers les résultats
        document.getElementById('simulateurResultat').scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Afficher le loading sur le bouton
        const submitBtn = document.querySelector('#simulateur .btn-primary');
        const originalText = submitBtn.textContent;
        submitBtn.classList.add('btn-loading');
        submitBtn.disabled = true;

        // Enregistrer la simulation (appel AJAX vers index.php)

        const formData = new URLSearchParams({
            surface: surface,
            capacite: capacite,
            ville: document.getElementById('sim_ville').value,
            email: email,
            telephone: telephone,
            centre_ville: centreVille ? 1 : 0,
            fibre: fibre ? 1 : 0,
            equipements_speciaux: equipementsSpeciaux ? 1 : 0,
            machine_cafe: machineCafe ? 1 : 0,
            machine_laver: machineLaver ? 1 : 0,
            autre_equipement: autreEquipement,
            tarif_nuit_estime: tarifNuit,
            revenu_mensuel_estime: Math.round(revenuNet),
            save_simulation: 1
        });
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        }).then(response => response.text())
          .then(text => {
              submitBtn.classList.remove('btn-loading');
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;

              try {
                  const data = JSON.parse(text);
                  if (data.success) {
                      let successMsg = document.getElementById('simulateurSuccess');
                      if (!successMsg) {
                          successMsg = document.createElement('div');
                          successMsg.id = 'simulateurSuccess';
                          successMsg.className = 'success-message';
                          const emailVal = formData.get('email') || '';
                          const telVal = formData.get('telephone') || '';
                          const rdvParams = new URLSearchParams({email: emailVal, tel: telVal}).toString();
                          successMsg.innerHTML = '<div style="text-align:center">'
                              + '<div style="font-size:1.1rem;margin-bottom:15px">✅ <strong>Résultats envoyés par email !</strong><br>Vous allez recevoir un récapitulatif détaillé.</div>'
                              + '<div style="background:linear-gradient(135deg,#1E3A8A,#3B82F6);padding:20px;border-radius:12px;margin-top:15px">'
                              + '<p style="color:white;font-size:1.1rem;margin:0 0 12px 0"><strong>Envie d\'en savoir plus ?</strong></p>'
                              + '<a href="rendez-vous.php?' + rdvParams + '" style="display:inline-block;background:white;color:#1E3A8A;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1rem;transition:transform 0.2s" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">'
                              + '📅 Prendre rendez-vous gratuitement</a>'
                              + '<p style="color:rgba(255,255,255,0.8);font-size:0.85rem;margin:10px 0 0 0">Echange de 15 min sans engagement</p>'
                              + '</div></div>';
                          document.getElementById('simulateurResultat').appendChild(successMsg);
                      }
                      successMsg.classList.add('show');
                  }
              } catch (e) {
                  // Erreur de parsing silencieuse
              }
          })
          .catch(() => {
              submitBtn.classList.remove('btn-loading');
              submitBtn.disabled = false;
              submitBtn.textContent = originalText;
          });
    }
    </script>
    <?php endif; ?>

    <?php if (sectionActive('galerie')): ?>
    <!-- Galerie Photos -->
    <section class="services" id="galerie">
        <div class="container">
            <h2 class="section-title">Nos Logements Gérés</h2>
            <p style="text-align: center; max-width: 800px; margin: 0 auto 3rem; font-size: 1.1rem; color: var(--gris-fonce);">
                Découvrez quelques exemples de biens que nous gérons avec passion dans la région de Compiègne.
                Chaque logement bénéficie de notre expertise pour offrir une expérience exceptionnelle aux voyageurs.
            </p>

            <div class="gallery-grid">
                <?php foreach ($logements as $logement): ?>
                <div class="gallery-item">
                    <div class="gallery-image">
                        <img src="<?= e($logement['image']) ?>" alt="<?= e($logement['titre']) ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%231E3A8A%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%22 font-size=%2220%22 fill=%22white%22%3E<?= e($logement['titre']) ?>%3C/text%3E%3C/svg%3E'">
                        <div class="gallery-overlay">
                            <div class="gallery-text">
                                <h3><?= e($logement['titre']) ?></h3>
                                <p><?= e($logement['description']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="highlight" style="margin-top: 3rem;">
                <p style="text-align: center;"><strong>Vous souhaitez confier votre bien à <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> ?</strong></p>
                <p style="text-align: center; margin-top: 1rem;">Notre équipe vous accompagne pour optimiser la rentabilité de votre investissement locatif.</p>
                <p style="text-align: center; margin-top: 1.5rem;">
                    <a href="#contact" class="btn-primary">Contactez-nous pour un devis personnalisé</a>
                </p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('distinctions')): ?>
    <!-- Nos Distinctions -->
    <section class="services" id="distinctions" style="background: white;">
        <div class="container">
            <h2 class="section-title">Nos Distinctions & Certifications</h2>

            <div style="text-align: center; margin-bottom: 2rem;">
                <?php
                $mainDistinction = array_filter($distinctions, fn($d) => !empty($d['image']));
                if (!empty($mainDistinction)):
                    $first = reset($mainDistinction);
                ?>
                <img src="<?= e($first['image']) ?>" alt="<?= e($first['titre']) ?>" style="max-width: 600px; width: 100%; height: auto; margin: 2rem 0;">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">
                    <?php foreach ($distinctions as $distinction): ?>
                    <div class="service-card" style="text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;"><?= e($distinction['icone']) ?></div>
                        <h3><?= e($distinction['titre']) ?></h3>
                        <p><?= e($distinction['description']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('avis')): ?>
    <!-- Témoignages -->
    <section class="avis" id="avis">
        <div class="container">
            <h2 class="section-title">Témoignages de Propriétaires</h2>

            <div class="avis-grid">
                <?php foreach ($avis as $avi): ?>
                <div class="avis-card">
                    <h4><?= e($avi['nom']) ?></h4>
                    <p class="avis-date"><?= e($avi['role']) ?> - <?= formatDateFr($avi['date_avis']) ?></p>
                    <div class="stars"><?= renderStars($avi['note']) ?></div>
                    <p><?= e($avi['commentaire']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="highlight">
                <p><strong>Information sur les témoignages :</strong> Les témoignages publiés sur notre site proviennent de propriétaires dont nous gérons actuellement les biens. Ces retours authentiques reflètent leur expérience réelle avec nos services de conciergerie et de gestion locative.</p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('blog')): ?>
    <!-- Blog / Actualités -->
    <section class="services" id="blog" style="background: var(--gris-clair);">
        <div class="container">
            <h2 class="section-title">Nos Actualités</h2>
            <p style="text-align: center; max-width: 700px; margin: 0 auto 2rem; color: #6B7280;">
                Conseils, astuces et actualités sur la gestion locative et la conciergerie Airbnb.
            </p>

            <?php
            // Récupérer les 3 derniers articles publiés
            try {
                $stmtBlog = $conn->query("SELECT id, titre, slug, extrait, image, date_publication FROM FC_articles WHERE actif = 1 ORDER BY date_publication DESC LIMIT 3");
                $latestArticles = $stmtBlog->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $latestArticles = [];
            }
            ?>

            <?php if (!empty($latestArticles)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <?php foreach ($latestArticles as $art): ?>
                <article style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: transform 0.3s, box-shadow 0.3s;">
                    <?php if (!empty($art['image'])): ?>
                    <a href="article.php?slug=<?= e($art['slug']) ?>">
                        <img src="<?= e($art['image']) ?>" alt="<?= e($art['titre']) ?>" style="width: 100%; height: 180px; object-fit: cover;">
                    </a>
                    <?php else: ?>
                    <div style="width: 100%; height: 180px; background: linear-gradient(135deg, var(--bleu-frenchy), var(--bleu-clair)); display: flex; align-items: center; justify-content: center;">
                        <span style="font-size: 3rem; opacity: 0.5;">📰</span>
                    </div>
                    <?php endif; ?>
                    <div style="padding: 1.5rem;">
                        <p style="color: #6B7280; font-size: 0.85rem; margin-bottom: 0.5rem;">
                            <?= date('d/m/Y', strtotime($art['date_publication'])) ?>
                        </p>
                        <h3 style="margin-bottom: 0.75rem; font-size: 1.15rem;">
                            <a href="article.php?slug=<?= e($art['slug']) ?>" style="color: var(--gris-fonce); text-decoration: none;">
                                <?= e($art['titre']) ?>
                            </a>
                        </h3>
                        <?php if (!empty($art['extrait'])): ?>
                        <p style="color: #6B7280; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1rem;">
                            <?= e(substr($art['extrait'], 0, 120)) ?>...
                        </p>
                        <?php endif; ?>
                        <a href="article.php?slug=<?= e($art['slug']) ?>" style="color: var(--bleu-clair); text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                            Lire la suite →
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="blog.php" class="btn-primary" style="display: inline-block; padding: 0.8rem 2rem;">
                    Voir tous les articles
                </a>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #6B7280;">Aucun article publié pour le moment.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (sectionActive('legal')): ?>
    <!-- Informations Légales -->
    <section class="legal-info" id="legal">
        <div class="container">
            <h2 class="section-title">Informations Légales</h2>

            <div class="legal-box">
                <h3>Identité de l'Entreprise</h3>
                <p><strong>Raison sociale :</strong> SAS FRENCHY CONCIERGERIE</p>
                <p><strong>Forme juridique :</strong> <?= e($settings['forme_juridique'] ?? 'Société par Actions Simplifiée (SAS)') ?></p>
                <p><strong>Capital social :</strong> <?= e($settings['capital'] ?? '100 euros') ?></p>
                <p><strong>Siège social :</strong> <?= e($settings['adresse'] ?? '') ?></p>
                <p><strong>SIRET :</strong> <?= e($settings['siret'] ?? '') ?></p>
                <p><strong>RCS :</strong> <?= e($settings['rcs'] ?? '') ?></p>
                <p><strong>N° TVA intracommunautaire :</strong> <?= e($settings['tva_intra'] ?? '') ?></p>
                <p><strong>Présidente :</strong> <?= e($settings['presidente'] ?? '') ?></p>
                <p><strong>Email :</strong> <?= e($settings['email_legal'] ?? '') ?></p>
                <p><strong>Téléphone :</strong> <?= e($settings['telephone_legal'] ?? '') ?></p>
            </div>

            <div class="legal-box">
                <h3>Cartes Professionnelles</h3>
                <p><strong>Carte de Transaction Immobilière n° <?= e($settings['carte_transaction'] ?? '') ?></strong></p>
                <p>Délivrée par la CCI de l'Oise</p>
                <p>Activité : Transactions sur immeubles et fonds de commerce</p>

                <p style="margin-top: 1.5rem;"><strong>Carte de Gestion Immobilière n° <?= e($settings['carte_gestion'] ?? '') ?></strong></p>
                <p>Délivrée par la CCI de l'Oise</p>
                <p>Activité : Gestion immobilière - Prestations touristiques</p>
            </div>

            <div class="mediateur-box">
                <h3>🛡️ Médiation de la Consommation</h3>
                <p>Conformément aux articles L.611-1 et suivants et R.612-1 et suivants du Code de la consommation, nous vous informons que tout consommateur a le droit de recourir gratuitement à un médiateur de la consommation en vue de la résolution amiable d'un litige l'opposant à un professionnel.</p>

                <p style="margin-top: 1rem;"><strong>Médiateur désigné :</strong> GIE IMMOMEDIATEURS</p>
                <p><strong>Adresse :</strong> 55 Avenue Marceau, 75116 Paris</p>
                <p><strong>Site internet :</strong> <a href="https://www.immomediateurs.fr" target="_blank" rel="noopener noreferrer" style="color: #1E3A8A; font-weight: bold;">🌐 www.immomediateurs.fr</a></p>
                <p><strong>Email :</strong> <a href="mailto:contact@immomediateurs.fr" style="color: #1E3A8A; font-weight: bold;">📧 contact@immomediateurs.fr</a></p>

                <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 8px; border: 1px solid #93C5FD;">
                    <p style="font-size: 0.9rem; color: #1E40AF;">
                        <strong>📋 Comment saisir le médiateur ?</strong><br>
                        Avant de saisir le médiateur, vous devez d'abord contacter notre service client par email à <a href="mailto:<?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?>" style="color: #1E3A8A;"><?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?></a> pour tenter de résoudre votre litige à l'amiable.
                        En cas d'échec ou d'absence de réponse dans un délai de 2 mois, vous pourrez saisir le médiateur.
                    </p>
                    <p style="margin-top: 0.8rem; text-align: center;">
                        <a href="https://www.immomediateurs.fr/saisir-le-mediateur/" target="_blank" rel="noopener noreferrer" class="btn-primary" style="display: inline-block; background: var(--bleu-frenchy); padding: 0.6rem 1.2rem; font-size: 0.9rem;">
                            Saisir le médiateur en ligne →
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Politique de Confidentialité RGPD -->
    <section class="privacy-section" id="privacy">
        <div class="container">
            <h2 class="section-title">Politique de Confidentialité</h2>

            <div class="privacy-content">
                <p><strong>Dernière mise à jour :</strong> <?= date('d/m/Y') ?></p>

                <p>La société <strong><?= e($settings['site_nom'] ?? 'FRENCHY CONCIERGERIE') ?></strong> (ci-après « nous », « notre » ou « la Société ») s'engage à protéger la vie privée des utilisateurs de son site web et de ses services. Cette politique de confidentialité explique comment nous collectons, utilisons et protégeons vos données personnelles, conformément au Règlement Général sur la Protection des Données (RGPD - Règlement UE 2016/679).</p>

                <h3>1. Responsable du traitement</h3>
                <p>Le responsable du traitement des données personnelles est :</p>
                <ul>
                    <li><strong>Société :</strong> SAS FRENCHY CONCIERGERIE</li>
                    <li><strong>Siège social :</strong> <?= e($settings['adresse'] ?? '') ?></li>
                    <li><strong>SIRET :</strong> <?= e($settings['siret'] ?? '') ?></li>
                    <li><strong>Email DPO/Contact :</strong> <a href="mailto:<?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?>"><?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?></a></li>
                </ul>

                <h3>2. Données collectées</h3>
                <p>Nous collectons les catégories de données suivantes :</p>
                <ul>
                    <li><strong>Données d'identification :</strong> nom, prénom, adresse email, numéro de téléphone</li>
                    <li><strong>Données relatives à votre bien :</strong> localisation, surface, équipements (via le simulateur de revenus)</li>
                    <li><strong>Données de navigation :</strong> adresse IP, cookies, pages visitées, durée de visite (avec votre consentement)</li>
                    <li><strong>Données de correspondance :</strong> messages envoyés via le formulaire de contact</li>
                </ul>

                <h3>3. Finalités du traitement</h3>
                <p>Vos données sont traitées pour les finalités suivantes :</p>
                <ul>
                    <li>Répondre à vos demandes de contact et de renseignement</li>
                    <li>Vous fournir un avis de valeur locative personnalisé (simulateur)</li>
                    <li>Gérer la relation commerciale et les contrats de conciergerie</li>
                    <li>Vous envoyer des communications marketing (avec votre consentement)</li>
                    <li>Améliorer nos services et notre site web</li>
                    <li>Respecter nos obligations légales</li>
                </ul>

                <h3>4. Base légale du traitement</h3>
                <p>Le traitement de vos données repose sur :</p>
                <ul>
                    <li><strong>Votre consentement :</strong> pour l'envoi de newsletters et la collecte de cookies non essentiels</li>
                    <li><strong>L'exécution d'un contrat :</strong> pour la gestion des services de conciergerie</li>
                    <li><strong>L'intérêt légitime :</strong> pour améliorer nos services et répondre à vos demandes</li>
                    <li><strong>Les obligations légales :</strong> pour conserver certaines données fiscales et comptables</li>
                </ul>

                <h3>5. Durée de conservation</h3>
                <p>Nous conservons vos données personnelles pendant :</p>
                <ul>
                    <li><strong>Données de contact/simulation :</strong> 3 ans après le dernier contact</li>
                    <li><strong>Données clients actifs :</strong> pendant la durée de la relation contractuelle + 5 ans</li>
                    <li><strong>Données comptables :</strong> 10 ans conformément aux obligations fiscales</li>
                    <li><strong>Cookies :</strong> 13 mois maximum</li>
                </ul>

                <h3>6. Destinataires des données</h3>
                <p>Vos données peuvent être transmises à :</p>
                <ul>
                    <li>Notre équipe interne pour le traitement de vos demandes</li>
                    <li>Notre hébergeur web (dans l'UE)</li>
                    <li>Nos prestataires de services (ménage, maintenance) - uniquement les données nécessaires</li>
                    <li>Les autorités compétentes en cas d'obligation légale</li>
                </ul>
                <p>Nous ne vendons jamais vos données à des tiers.</p>

                <h3>7. Vos droits</h3>
                <p>Conformément au RGPD, vous disposez des droits suivants :</p>
                <ul>
                    <li><strong>Droit d'accès :</strong> obtenir une copie de vos données personnelles</li>
                    <li><strong>Droit de rectification :</strong> corriger vos données inexactes ou incomplètes</li>
                    <li><strong>Droit à l'effacement :</strong> demander la suppression de vos données</li>
                    <li><strong>Droit à la limitation :</strong> limiter le traitement de vos données</li>
                    <li><strong>Droit à la portabilité :</strong> recevoir vos données dans un format structuré</li>
                    <li><strong>Droit d'opposition :</strong> vous opposer au traitement de vos données</li>
                    <li><strong>Droit de retirer votre consentement :</strong> à tout moment, sans affecter la légalité du traitement antérieur</li>
                </ul>
                <p>Pour exercer vos droits, contactez-nous à : <a href="mailto:<?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?>"><?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?></a></p>
                <p>Vous pouvez également introduire une réclamation auprès de la <strong>CNIL</strong> (Commission Nationale de l'Informatique et des Libertés) : <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer">www.cnil.fr</a></p>

                <h3>8. Cookies</h3>
                <p>Notre site utilise des cookies pour :</p>
                <ul>
                    <li><strong>Cookies essentiels :</strong> fonctionnement du site (sessions, sécurité)</li>
                    <li><strong>Cookies analytiques :</strong> mesure d'audience et amélioration du site (avec consentement)</li>
                    <li><strong>Cookies marketing :</strong> personnalisation des publicités (avec consentement)</li>
                </ul>
                <p>Vous pouvez gérer vos préférences via notre <a href="javascript:void(0)" onclick="openCookieSettings()">panneau de configuration des cookies</a>.</p>

                <h3>9. Sécurité</h3>
                <p>Nous mettons en œuvre des mesures techniques et organisationnelles appropriées pour protéger vos données contre la perte, l'accès non autorisé, la divulgation ou la destruction :</p>
                <ul>
                    <li>Chiffrement SSL/TLS des communications</li>
                    <li>Accès restreint aux données personnelles</li>
                    <li>Sauvegardes régulières</li>
                    <li>Mise à jour des systèmes de sécurité</li>
                </ul>

                <h3>10. Modifications</h3>
                <p>Nous nous réservons le droit de modifier cette politique de confidentialité à tout moment. Toute modification sera publiée sur cette page avec une nouvelle date de mise à jour.</p>

                <div style="margin-top: 2rem; padding: 1.5rem; background: var(--gris-clair); border-radius: 10px; text-align: center;">
                    <p><strong>Des questions sur vos données ?</strong></p>
                    <p style="margin-top: 0.5rem;">Contactez notre responsable de la protection des données :</p>
                    <p style="margin-top: 0.5rem;"><a href="mailto:<?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?>" class="btn-primary" style="display: inline-block; margin-top: 0.5rem;">📧 <?= e($settings['email_legal'] ?? $settings['email'] ?? '') ?></a></p>
                </div>
            </div>
        </div>
    </section>

    <?php if (sectionActive('contact')): ?>
    <!-- Contact Section -->
    <section class="services" id="contact">
        <div class="container">
            <h2 class="section-title">Contactez-nous</h2>
            <div style="text-align: center; max-width: 600px; margin: 0 auto;">
                <p style="font-size: 1.2rem; margin-bottom: 2rem;">Vous avez un projet de location saisonnière ? Parlons-en !</p>

                <?php if ($contactSuccess): ?>
                <div class="alert alert-success">
                    Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.
                </div>
                <?php elseif ($contactError): ?>
                <div class="alert alert-error">
                    <?= e($contactError) ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="contact-form" id="contactForm" onsubmit="handleContactSubmit(this)">
                    <?php if ($security): echo $security->csrfField(); endif; ?>
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone">
                    </div>
                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet">
                    </div>
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <div class="rgpd-checkbox">
                        <input type="checkbox" id="rgpd_consent" name="rgpd_consent" required>
                        <label for="rgpd_consent">
                            J'accepte que mes données personnelles soient collectées et traitées par <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> pour répondre à ma demande de contact, conformément à la <a href="#privacy">politique de confidentialité</a>. *
                        </label>
                    </div>
                    <div class="rgpd-checkbox" style="background: #FEF3C7; border-color: #FCD34D;">
                        <input type="checkbox" id="newsletter_consent" name="newsletter_consent">
                        <label for="newsletter_consent">
                            J'accepte de recevoir des informations et actualités de <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> par email. Je peux me désinscrire à tout moment.
                        </label>
                    </div>
                    <button type="submit" name="contact_submit" class="btn-primary">Envoyer</button>
                </form>

                <div style="background: var(--gris-clair); padding: 2rem; border-radius: 10px; margin-top: 2rem;">
                    <p><strong>Adresse :</strong><br><?= nl2br(e($settings['adresse'] ?? '')) ?></p>
                    <p style="margin-top: 1rem;"><strong>Téléphone :</strong><br><a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings['telephone'] ?? '') ?>" style="color: var(--bleu-clair); text-decoration: none;"><?= e($settings['telephone'] ?? '') ?></a></p>
                    <p style="margin-top: 1rem;"><strong>Email :</strong><br><a href="mailto:<?= e($settings['email'] ?? '') ?>" style="color: var(--bleu-clair); text-decoration: none;"><?= e($settings['email'] ?? '') ?></a></p>

                    <p style="margin-top: 2rem;"><strong>Horaires :</strong><br><?= e($settings['horaires'] ?? 'Du lundi au samedi : 9h - 19h') ?></p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?></h3>
                    <p>Votre partenaire de confiance pour la gestion locative premium dans la région de Compiègne.</p>
                    <p style="margin-top: 1rem;"><strong>SIRET :</strong> <?= e($settings['siret'] ?? '') ?></p>
                </div>

                <div class="footer-section">
                    <h3>Nos Services</h3>
                    <a href="#services">Gestion Locative</a>
                    <a href="#services">Publication & Diffusion</a>
                    <a href="#services">Accompagnement Investisseurs</a>
                    <a href="#tarifs">Nos Tarifs</a>
                    <a href="#simulateur">Simulateur de Revenus</a>
                    <a href="#galerie">Nos Logements</a>
                    <a href="#distinctions">Nos Distinctions</a>
                    <a href="avis.php" style="color: #FCD34D;">⭐ Donner mon avis</a>
                </div>

                <div class="footer-section">
                    <h3>Informations Légales</h3>
                    <a href="#legal">Mentions Légales</a>
                    <a href="#legal">Cartes Professionnelles</a>
                    <a href="#legal">Médiation de la Consommation</a>
                    <a href="#privacy">Politique de Confidentialité</a>
                    <a href="contrats-retractation.php">Contrats & Rétractation</a>
                    <a href="politique-avis.php">Politique des avis</a>
                    <a href="javascript:void(0)" onclick="openCookieSettings()">Paramètres des cookies</a>
                </div>

                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><?= nl2br(e($settings['adresse'] ?? '')) ?></p>
                    <p><?= e($settings['telephone'] ?? '') ?></p>
                    <p><?= e($settings['email'] ?? '') ?></p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e($settings['site_nom'] ?? 'Frenchy Conciergerie') ?> - Tous droits réservés</p>
                <p style="margin-top: 0.5rem;"><strong>SAS au capital de <?= e($settings['capital'] ?? '100€') ?></strong> - SIRET : <?= e($settings['siret'] ?? '') ?> - RCS <?= e($settings['rcs'] ?? '') ?></p>
                <p style="margin-top: 0.5rem;">TVA intracommunautaire : <?= e($settings['tva_intra'] ?? '') ?></p>
                <p style="margin-top: 0.5rem;">Carte Transaction n° <?= e($settings['carte_transaction'] ?? '') ?> | Carte Gestion n° <?= e($settings['carte_gestion'] ?? '') ?></p>
            </div>
        </div>
    </footer>

    <!-- Cookie Consent Banner -->
    <div id="cookieBanner" class="cookie-banner">
        <div class="cookie-content">
            <div class="cookie-text">
                <h4>🍪 Gestion des cookies</h4>
                <p>
                    Nous utilisons des cookies pour améliorer votre expérience sur notre site, analyser notre trafic et personnaliser le contenu.
                    En cliquant sur "Tout accepter", vous consentez à l'utilisation de tous les cookies.
                    <a href="#privacy">En savoir plus sur notre politique de confidentialité</a>
                </p>
            </div>
            <div class="cookie-buttons">
                <button class="cookie-btn cookie-btn-refuse" onclick="refuseCookies()">Tout refuser</button>
                <button class="cookie-btn cookie-btn-settings" onclick="openCookieSettings()">Paramétrer</button>
                <button class="cookie-btn cookie-btn-accept" onclick="acceptAllCookies()">Tout accepter</button>
            </div>
        </div>
    </div>

    <!-- Cookie Settings Modal -->
    <div id="cookieModal" class="cookie-modal">
        <div class="cookie-modal-content">
            <h3>🍪 Paramètres des cookies</h3>
            <p>Personnalisez vos préférences en matière de cookies. Les cookies essentiels ne peuvent pas être désactivés car ils sont nécessaires au fonctionnement du site.</p>

            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookieEssential" checked disabled>
                    Cookies essentiels (obligatoires)
                </label>
                <p>Ces cookies sont indispensables au fonctionnement du site et ne peuvent pas être désactivés. Ils permettent la navigation et l'utilisation des fonctionnalités de base.</p>
            </div>

            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookieAnalytics">
                    Cookies analytiques
                </label>
                <p>Ces cookies nous permettent de mesurer l'audience de notre site et d'améliorer son contenu. Les données sont anonymisées.</p>
            </div>

            <div class="cookie-option">
                <label>
                    <input type="checkbox" id="cookieMarketing">
                    Cookies marketing
                </label>
                <p>Ces cookies sont utilisés pour vous proposer des publicités personnalisées et mesurer l'efficacité de nos campagnes.</p>
            </div>

            <div class="cookie-modal-buttons">
                <button class="cookie-btn cookie-btn-refuse" onclick="closeCookieSettings()">Annuler</button>
                <button class="cookie-btn cookie-btn-accept" onclick="saveCookieSettings()">Enregistrer mes préférences</button>
            </div>
        </div>
    </div>

    <!-- Cookie Consent Script -->
    <script>
    (function() {
        // Vérifier si le consentement a déjà été donné
        const consent = localStorage.getItem('cookieConsent');
        if (!consent) {
            // Afficher le bandeau après un court délai
            setTimeout(function() {
                document.getElementById('cookieBanner').classList.add('show');
            }, 1000);
        }
    })();

    function acceptAllCookies() {
        const consent = {
            essential: true,
            analytics: true,
            marketing: true,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('cookieConsent', JSON.stringify(consent));
        document.getElementById('cookieBanner').classList.remove('show');
        // Activer les services analytics/marketing si nécessaire
        console.log('Tous les cookies acceptés');
    }

    function refuseCookies() {
        const consent = {
            essential: true,
            analytics: false,
            marketing: false,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('cookieConsent', JSON.stringify(consent));
        document.getElementById('cookieBanner').classList.remove('show');
        console.log('Cookies optionnels refusés');
    }

    function openCookieSettings() {
        // Charger les préférences actuelles
        const consent = JSON.parse(localStorage.getItem('cookieConsent') || '{}');
        document.getElementById('cookieAnalytics').checked = consent.analytics || false;
        document.getElementById('cookieMarketing').checked = consent.marketing || false;

        document.getElementById('cookieModal').classList.add('show');
        document.getElementById('cookieBanner').classList.remove('show');
    }

    function closeCookieSettings() {
        document.getElementById('cookieModal').classList.remove('show');
        // Réafficher le bandeau si pas de consentement
        if (!localStorage.getItem('cookieConsent')) {
            document.getElementById('cookieBanner').classList.add('show');
        }
    }

    function saveCookieSettings() {
        const consent = {
            essential: true,
            analytics: document.getElementById('cookieAnalytics').checked,
            marketing: document.getElementById('cookieMarketing').checked,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('cookieConsent', JSON.stringify(consent));
        document.getElementById('cookieModal').classList.remove('show');
        console.log('Préférences cookies enregistrées:', consent);
    }

    // Fermer le modal en cliquant à l'extérieur
    document.getElementById('cookieModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCookieSettings();
        }
    });

    // Loading sur formulaire de contact
    function handleContactSubmit(form) {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
        }
        return true; // Continuer la soumission normale
    }
    </script>
</body>
</html>
