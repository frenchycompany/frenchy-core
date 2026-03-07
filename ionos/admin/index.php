<?php
/**
 * Panneau d'administration Frenchy Conciergerie
 *
 * MIGRATION: Ce panneau utilise désormais le système d'auth unifié.
 * Les credentials hardcodés ont été supprimés.
 * Connectez-vous via /gestion/login.php avec un compte admin/super_admin.
 */
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../gestion/includes/Auth.php';

// Vérifier la connexion à la base de données
if (!$conn) {
    die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
}

$auth = new Auth($conn);

// Logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: ../gestion/login.php');
    exit;
}

// Vérification authentification via le système unifié
// Accepte les sessions admin du nouveau système OU l'ancien système (transition)
$isLogged = false;

if ($auth->check() && $auth->isAdmin()) {
    $isLogged = true;
} elseif (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    // Ancien système — accepté temporairement pendant la migration
    $isLogged = true;
}

// Si pas connecté, rediriger vers le login unifié
if (!$isLogged) {
    header('Location: ../gestion/login.php');
    exit;
}

// Page courante
$page = $_GET['page'] ?? 'dashboard';

// Traitement des actions
$message = '';
$messageType = '';

if ($isLogged && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour des sections
    if (isset($_POST['update_sections'])) {
        // Créer la table si nécessaire
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(50) UNIQUE,
            section_label VARCHAR(100),
            actif TINYINT(1) DEFAULT 1,
            ordre INT DEFAULT 0
        )");

        // Insérer les sections par défaut si elles n'existent pas
        $defaultSections = [
            ['hero', 'Bannière d\'accueil (Hero)', 1, 1],
            ['services', 'Nos Services', 1, 2],
            ['tarifs', 'Tarifs', 1, 3],
            ['simulateur', 'Simulateur de revenus', 1, 4],
            ['galerie', 'Galerie / Logements', 1, 5],
            ['distinctions', 'Distinctions & Certifications', 1, 6],
            ['avis', 'Avis / Témoignages', 1, 7],
            ['blog', 'Blog / Actualités', 1, 8],
            ['legal', 'Informations légales', 1, 9],
            ['contact', 'Formulaire de contact', 1, 10],
        ];
        foreach ($defaultSections as $sec) {
            $stmt = $conn->prepare("INSERT IGNORE INTO FC_sections (section_key, section_label, actif, ordre) VALUES (?, ?, ?, ?)");
            $stmt->execute($sec);
        }

        // Mettre à jour les sections
        foreach ($_POST['sections'] ?? [] as $key => $data) {
            $actif = isset($data['actif']) ? 1 : 0;
            $ordre = intval($data['ordre'] ?? 0);
            $stmt = $conn->prepare("UPDATE FC_sections SET actif = ?, ordre = ? WHERE section_key = ?");
            $stmt->execute([$actif, $ordre, $key]);
        }
        $message = 'Sections mises à jour';
        $messageType = 'success';
    }

    // Mise à jour des paramètres
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("UPDATE FC_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $message = 'Paramètres mis à jour avec succès';
        $messageType = 'success';
    }

    // Ajout d'un logement
    if (isset($_POST['add_logement'])) {
        $imagePath = '';

        // Gestion de l'upload de photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/logements/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            if (in_array($_FILES['photo']['type'], $allowedTypes) && $_FILES['photo']['size'] <= $maxSize) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'logement-' . time() . '-' . uniqid() . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/logements/' . $filename;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO FC_logements (titre, description, image, localisation, type_bien, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titre'],
            $_POST['description'] ?? '',
            $imagePath,
            $_POST['localisation'],
            $_POST['type_bien'],
            $_POST['ordre'] ?? 0
        ]);
        $message = 'Logement ajouté avec succès';
        $messageType = 'success';
    }

    // Suppression d'un logement
    if (isset($_POST['delete_logement'])) {
        $stmt = $conn->prepare("DELETE FROM FC_logements WHERE id = ?");
        $stmt->execute([$_POST['logement_id']]);
        $message = 'Logement supprimé';
        $messageType = 'success';
    }

    // Ajout d'un avis
    if (isset($_POST['add_avis'])) {
        $stmt = $conn->prepare("INSERT INTO FC_avis (nom, role, date_avis, note, commentaire) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nom'],
            $_POST['role'],
            $_POST['date_avis'],
            $_POST['note'],
            $_POST['commentaire']
        ]);
        $message = 'Avis ajouté avec succès';
        $messageType = 'success';
    }

    // Suppression d'un avis
    if (isset($_POST['delete_avis'])) {
        $stmt = $conn->prepare("DELETE FROM FC_avis WHERE id = ?");
        $stmt->execute([$_POST['avis_id']]);
        $message = 'Avis supprimé';
        $messageType = 'success';
    }

    // Marquer contact comme lu
    if (isset($_POST['mark_read'])) {
        $stmt = $conn->prepare("UPDATE FC_contacts SET lu = 1 WHERE id = ?");
        $stmt->execute([$_POST['contact_id']]);
        $message = 'Contact marqué comme lu';
        $messageType = 'success';
    }

    // Archiver un contact
    if (isset($_POST['archive_contact'])) {
        $stmt = $conn->prepare("UPDATE FC_contacts SET archive = 1 WHERE id = ?");
        $stmt->execute([$_POST['contact_id']]);
        $message = 'Message archivé';
        $messageType = 'success';
    }

    // Désarchiver un contact
    if (isset($_POST['unarchive_contact'])) {
        $stmt = $conn->prepare("UPDATE FC_contacts SET archive = 0 WHERE id = ?");
        $stmt->execute([$_POST['contact_id']]);
        $message = 'Message restauré';
        $messageType = 'success';
    }

    // Supprimer un contact
    if (isset($_POST['delete_contact'])) {
        $stmt = $conn->prepare("DELETE FROM FC_contacts WHERE id = ?");
        $stmt->execute([$_POST['contact_id']]);
        $message = 'Message supprimé';
        $messageType = 'success';
    }

    // Mise à jour des paramètres du site
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO FC_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $message = 'Paramètres mis à jour';
        $messageType = 'success';
        // Rafraîchir les settings
        $settings = getAllSettings($conn);
    }

    // Ajout d'un service
    if (isset($_POST['add_service'])) {
        $listeItems = array_filter(array_map('trim', explode("\n", $_POST['liste_items'])));
        $stmt = $conn->prepare("INSERT INTO FC_services (titre, icone, carte_info, description, liste_items, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titre'],
            $_POST['icone'],
            $_POST['carte_info'] ?? '',
            $_POST['description'],
            json_encode($listeItems),
            $_POST['ordre'] ?? 0
        ]);
        $message = 'Service ajouté avec succès';
        $messageType = 'success';
    }

    // Modification d'un service
    if (isset($_POST['edit_service'])) {
        $listeItems = array_filter(array_map('trim', explode("\n", $_POST['liste_items'])));
        $stmt = $conn->prepare("UPDATE FC_services SET titre = ?, icone = ?, carte_info = ?, description = ?, liste_items = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([
            $_POST['titre'],
            $_POST['icone'],
            $_POST['carte_info'] ?? '',
            $_POST['description'],
            json_encode($listeItems),
            $_POST['ordre'] ?? 0,
            isset($_POST['actif']) ? 1 : 0,
            $_POST['service_id']
        ]);
        $message = 'Service mis à jour';
        $messageType = 'success';
    }

    // Suppression d'un service
    if (isset($_POST['delete_service'])) {
        $stmt = $conn->prepare("DELETE FROM FC_services WHERE id = ?");
        $stmt->execute([$_POST['service_id']]);
        $message = 'Service supprimé';
        $messageType = 'success';
    }

    // Ajout d'un tarif
    if (isset($_POST['add_tarif'])) {
        $stmt = $conn->prepare("INSERT INTO FC_tarifs (titre, montant, type_tarif, description, details, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titre'],
            $_POST['montant'],
            $_POST['type_tarif'] ?? 'pourcentage',
            $_POST['description'],
            $_POST['details'] ?? '',
            $_POST['ordre'] ?? 0
        ]);
        $message = 'Tarif ajouté avec succès';
        $messageType = 'success';
    }

    // Modification d'un tarif
    if (isset($_POST['edit_tarif'])) {
        $stmt = $conn->prepare("UPDATE FC_tarifs SET titre = ?, montant = ?, type_tarif = ?, description = ?, details = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([
            $_POST['titre'],
            $_POST['montant'],
            $_POST['type_tarif'] ?? 'pourcentage',
            $_POST['description'],
            $_POST['details'] ?? '',
            $_POST['ordre'] ?? 0,
            isset($_POST['actif']) ? 1 : 0,
            $_POST['tarif_id']
        ]);
        $message = 'Tarif mis à jour';
        $messageType = 'success';
    }

    // Suppression d'un tarif
    if (isset($_POST['delete_tarif'])) {
        $stmt = $conn->prepare("DELETE FROM FC_tarifs WHERE id = ?");
        $stmt->execute([$_POST['tarif_id']]);
        $message = 'Tarif supprimé';
        $messageType = 'success';
    }

    // Ajout d'une distinction
    if (isset($_POST['add_distinction'])) {
        $stmt = $conn->prepare("INSERT INTO FC_distinctions (titre, icone, description, image, ordre) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titre'],
            $_POST['icone'],
            $_POST['description'],
            $_POST['image'] ?? '',
            $_POST['ordre'] ?? 0
        ]);
        $message = 'Distinction ajoutée avec succès';
        $messageType = 'success';
    }

    // Modification d'une distinction
    if (isset($_POST['edit_distinction'])) {
        $stmt = $conn->prepare("UPDATE FC_distinctions SET titre = ?, icone = ?, description = ?, image = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([
            $_POST['titre'],
            $_POST['icone'],
            $_POST['description'],
            $_POST['image'] ?? '',
            $_POST['ordre'] ?? 0,
            isset($_POST['actif']) ? 1 : 0,
            $_POST['distinction_id']
        ]);
        $message = 'Distinction mise à jour';
        $messageType = 'success';
    }

    // Suppression d'une distinction
    if (isset($_POST['delete_distinction'])) {
        $stmt = $conn->prepare("DELETE FROM FC_distinctions WHERE id = ?");
        $stmt->execute([$_POST['distinction_id']]);
        $message = 'Distinction supprimée';
        $messageType = 'success';
    }

    // Marquer simulation comme contactée
    if (isset($_POST['mark_contacted'])) {
        $stmt = $conn->prepare("UPDATE FC_simulations SET contacted = 1, statut = 'contacte', notes = ? WHERE id = ?");
        $stmt->execute([$_POST['notes'] ?? '', $_POST['simulation_id']]);
        $message = 'Simulation marquée comme contactée';
        $messageType = 'success';
    }

    // Changer statut simulation
    if (isset($_POST['update_simulation_statut'])) {
        $stmt = $conn->prepare("UPDATE FC_simulations SET statut = ?, contacted = ? WHERE id = ?");
        $contacted = in_array($_POST['statut'], ['contacte', 'converti', 'perdu']) ? 1 : 0;
        $stmt->execute([$_POST['statut'], $contacted, $_POST['simulation_id']]);
        $message = 'Statut mis à jour';
        $messageType = 'success';
    }

    // Supprimer simulation
    if (isset($_POST['delete_simulation'])) {
        $stmt = $conn->prepare("DELETE FROM FC_simulations WHERE id = ?");
        $stmt->execute([$_POST['simulation_id']]);
        $message = 'Simulation supprimée';
        $messageType = 'success';
    }

    // Changer statut contact
    if (isset($_POST['update_contact_statut'])) {
        $lu = $_POST['statut'] !== 'nouveau' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE FC_contacts SET statut = ?, lu = ? WHERE id = ?");
        $stmt->execute([$_POST['statut'], $lu, $_POST['contact_id']]);
        $message = 'Statut mis à jour';
        $messageType = 'success';
    }

    // Envoyer email
    if (isset($_POST['send_email'])) {
        $to = $_POST['email_to'];
        $subject = $_POST['email_subject'];
        $body = $_POST['email_body'];
        $fromName = $settings['site_nom'] ?? 'Frenchy Conciergerie';
        $fromEmail = $settings['email'] ?? 'contact@frenchyconciergerie.fr';

        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        // Template email HTML
        $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">' . htmlspecialchars($fromName) . '</h1>
            </div>
            <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none;">
                ' . nl2br(htmlspecialchars($body)) . '
            </div>
            <div style="background: #1E3A8A; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px;">
                <p style="margin: 0;">' . htmlspecialchars($settings['adresse'] ?? '') . '</p>
                <p style="margin: 5px 0 0 0;">' . htmlspecialchars($settings['telephone'] ?? '') . ' | ' . htmlspecialchars($fromEmail) . '</p>
            </div>
        </body></html>';

        if (mail($to, $subject, $htmlBody, $headers)) {
            // Enregistrer dans l'historique
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS FC_emails_sent (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email_to VARCHAR(255),
                    subject VARCHAR(255),
                    body TEXT,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $stmt = $conn->prepare("INSERT INTO FC_emails_sent (email_to, subject, body) VALUES (?, ?, ?)");
                $stmt->execute([$to, $subject, $body]);
            } catch (PDOException $e) {}
            $message = 'Email envoyé avec succès à ' . $to;
            $messageType = 'success';
        } else {
            $message = 'Erreur lors de l\'envoi de l\'email';
            $messageType = 'error';
        }
    }

    // Envoyer newsletter
    if (isset($_POST['send_newsletter'])) {
        $subject = $_POST['newsletter_subject'];
        $body = $_POST['newsletter_body'];
        $recipients = [];

        if (isset($_POST['to_simulations'])) {
            $stmt = $conn->query("SELECT DISTINCT email FROM FC_simulations");
            while ($row = $stmt->fetch()) {
                $recipients[] = $row['email'];
            }
        }
        if (isset($_POST['to_contacts'])) {
            $stmt = $conn->query("SELECT DISTINCT email FROM FC_contacts");
            while ($row = $stmt->fetch()) {
                $recipients[] = $row['email'];
            }
        }

        $recipients = array_unique($recipients);
        $fromName = $settings['site_nom'] ?? 'Frenchy Conciergerie';
        $fromEmail = $settings['email'] ?? 'contact@frenchyconciergerie.fr';
        $sent = 0;

        foreach ($recipients as $to) {
            $headers = "From: $fromName <$fromEmail>\r\n";
            $headers .= "Reply-To: $fromEmail\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%); padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="color: white; margin: 0;">' . htmlspecialchars($fromName) . '</h1>
                </div>
                <div style="background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none;">
                    ' . nl2br(htmlspecialchars($body)) . '
                </div>
                <div style="background: #1E3A8A; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px;">
                    <p style="margin: 0;">' . htmlspecialchars($settings['adresse'] ?? '') . '</p>
                    <p style="margin: 5px 0 0 0;">' . htmlspecialchars($settings['telephone'] ?? '') . ' | ' . htmlspecialchars($fromEmail) . '</p>
                    <p style="margin: 10px 0 0 0; font-size: 10px; opacity: 0.8;">Pour vous désabonner, répondez à cet email avec "STOP"</p>
                </div>
            </body></html>';

            if (mail($to, $subject, $htmlBody, $headers)) {
                $sent++;
            }
            usleep(100000); // 100ms entre chaque envoi
        }

        $message = "Newsletter envoyée à $sent destinataire(s)";
        $messageType = 'success';
    }

    // Mise à jour configuration simulateur
    if (isset($_POST['update_simulateur_config'])) {
        foreach ($_POST['config'] as $key => $value) {
            $stmt = $conn->prepare("UPDATE FC_simulateur_config SET config_value = ? WHERE config_key = ?");
            $stmt->execute([$value, $key]);
        }
        $message = 'Configuration du simulateur mise à jour';
        $messageType = 'success';
    }

    // Ajout d'une ville
    if (isset($_POST['add_ville'])) {
        $stmt = $conn->prepare("INSERT INTO FC_simulateur_villes (ville, majoration_percent, ordre) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['ville'],
            $_POST['majoration_percent'],
            $_POST['ordre'] ?? 0
        ]);
        $message = 'Ville ajoutée avec succès';
        $messageType = 'success';
    }

    // Modification d'une ville
    if (isset($_POST['edit_ville'])) {
        $stmt = $conn->prepare("UPDATE FC_simulateur_villes SET ville = ?, majoration_percent = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([
            $_POST['ville'],
            $_POST['majoration_percent'],
            $_POST['ordre'] ?? 0,
            isset($_POST['actif']) ? 1 : 0,
            $_POST['ville_id']
        ]);
        $message = 'Ville mise à jour';
        $messageType = 'success';
    }

    // Suppression d'une ville
    if (isset($_POST['delete_ville'])) {
        $stmt = $conn->prepare("DELETE FROM FC_simulateur_villes WHERE id = ?");
        $stmt->execute([$_POST['ville_id']]);
        $message = 'Ville supprimée';
        $messageType = 'success';
    }
}

// Récupération des données si connecté
if ($isLogged) {
    $settings = getAllSettings($conn);
    $logements = getLogements($conn);
    $avis = getAvis($conn);

    // Sections du site
    try {
        // Créer la table et les sections par défaut si nécessaire
        $conn->exec("CREATE TABLE IF NOT EXISTS FC_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(50) UNIQUE,
            section_label VARCHAR(100),
            actif TINYINT(1) DEFAULT 1,
            ordre INT DEFAULT 0
        )");
        $defaultSections = [
            ['hero', 'Bannière d\'accueil (Hero)', 1, 1],
            ['services', 'Nos Services', 1, 2],
            ['tarifs', 'Tarifs', 1, 3],
            ['simulateur', 'Simulateur de revenus', 1, 4],
            ['galerie', 'Galerie / Logements', 1, 5],
            ['distinctions', 'Distinctions & Certifications', 1, 6],
            ['avis', 'Avis / Témoignages', 1, 7],
            ['blog', 'Blog / Actualités', 1, 8],
            ['legal', 'Informations légales', 1, 9],
            ['contact', 'Formulaire de contact', 1, 10],
        ];
        foreach ($defaultSections as $sec) {
            $stmt = $conn->prepare("INSERT IGNORE INTO FC_sections (section_key, section_label, actif, ordre) VALUES (?, ?, ?, ?)");
            $stmt->execute($sec);
        }
        $stmt = $conn->query("SELECT * FROM FC_sections ORDER BY ordre ASC");
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sections = [];
    }

    // Services
    $stmt = $conn->query("SELECT * FROM FC_services ORDER BY ordre ASC");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tarifs - Ajouter les colonnes montant et type_tarif si elles n'existent pas
    try {
        $conn->query("SELECT montant FROM FC_tarifs LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE FC_tarifs ADD COLUMN montant DECIMAL(10,2) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_tarifs ADD COLUMN type_tarif ENUM('pourcentage', 'euro') DEFAULT 'pourcentage'");
        // Migrer les données de pourcentage vers montant
        $conn->exec("UPDATE FC_tarifs SET montant = pourcentage WHERE montant = 0 OR montant IS NULL");
    }
    $stmt = $conn->query("SELECT * FROM FC_tarifs ORDER BY ordre ASC");
    $tarifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Distinctions
    $stmt = $conn->query("SELECT * FROM FC_distinctions ORDER BY ordre ASC");
    $distinctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Simulations - Ajouter les colonnes équipements si elles n'existent pas
    try {
        $conn->query("SELECT centre_ville FROM FC_simulations LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN centre_ville TINYINT(1) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN fibre TINYINT(1) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN equipements_speciaux TINYINT(1) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN machine_cafe TINYINT(1) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN machine_laver TINYINT(1) DEFAULT 0");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN autre_equipement VARCHAR(255)");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN tarif_nuit_estime DECIMAL(10,2)");
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN revenu_mensuel_estime DECIMAL(10,2)");
    }
    $stmt = $conn->query("SELECT * FROM FC_simulations ORDER BY created_at DESC LIMIT 50");
    $simulations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $newSimulations = count(array_filter($simulations, fn($s) => !$s['contacted']));

    // Contacts (non archivés par défaut)
    // Ajouter les colonnes archive et statut si elles n'existent pas
    try {
        $conn->query("SELECT archive FROM FC_contacts LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE FC_contacts ADD COLUMN archive TINYINT(1) DEFAULT 0");
    }
    try {
        $conn->query("SELECT statut FROM FC_contacts LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE FC_contacts ADD COLUMN statut VARCHAR(20) DEFAULT 'nouveau'");
    }

    // Simulations - Ajouter colonne statut si elle n'existe pas
    try {
        $conn->query("SELECT statut FROM FC_simulations LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE FC_simulations ADD COLUMN statut VARCHAR(20) DEFAULT 'a_contacter'");
    }

    $showArchived = isset($_GET['archives']) && $_GET['archives'] == '1';
    if ($showArchived) {
        $stmt = $conn->query("SELECT * FROM FC_contacts WHERE archive = 1 ORDER BY created_at DESC");
    } else {
        $stmt = $conn->query("SELECT * FROM FC_contacts WHERE archive = 0 ORDER BY created_at DESC");
    }
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unreadContacts = $conn->query("SELECT COUNT(*) FROM FC_contacts WHERE lu = 0 AND archive = 0")->fetchColumn();

    // Configuration du simulateur
    try {
        $stmt = $conn->query("SELECT * FROM FC_simulateur_config ORDER BY ordre ASC");
        $simulateurConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $simulateurConfig = [];
    }

    // Villes du simulateur
    try {
        $stmt = $conn->query("SELECT * FROM FC_simulateur_villes ORDER BY ordre ASC");
        $simulateurVilles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $simulateurVilles = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Frenchy Conciergerie</title>
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
            background: var(--gris-clair);
            min-height: 100vh;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-box h1 {
            color: var(--bleu-frenchy);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--bleu-clair);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        .btn-primary {
            background: var(--bleu-frenchy);
            color: white;
        }

        .btn-primary:hover {
            background: var(--bleu-clair);
        }

        .btn-danger {
            background: var(--rouge-frenchy);
            color: white;
        }

        .btn-danger:hover {
            background: #DC2626;
        }

        .btn-success {
            background: #10B981;
            color: white;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--bleu-frenchy);
            color: white;
            padding: 1.5rem;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            font-size: 1.3rem;
        }

        .sidebar nav a {
            display: block;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1rem;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            transition: background 0.3s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar nav a .badge {
            background: var(--rouge-frenchy);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 80px);
        }

        .nav-section {
            margin-bottom: 1rem;
        }

        .nav-label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
            padding: 0.5rem 1rem 0.3rem;
            margin-top: 0.5rem;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-title {
            color: var(--bleu-frenchy);
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .card h3 {
            color: var(--bleu-frenchy);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--gris-clair);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--bleu-clair);
        }

        .stat-card .label {
            color: var(--gris-fonce);
            margin-top: 0.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid var(--gris-clair);
        }

        table th {
            background: var(--gris-clair);
            font-weight: bold;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
        }

        .unread {
            background: #FEF3C7;
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php if (!$isLogged): ?>
    <!-- Page de connexion -->
    <div class="login-container">
        <div class="login-box">
            <h1>Administration</h1>
            <?php if (isset($loginError)): ?>
            <div class="alert alert-error"><?= e($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nom d'utilisateur</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">Connexion</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Interface d'administration -->
    <div class="admin-container">
        <aside class="sidebar">
            <h2 style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.5rem;">🏠</span>
                <span>Frenchy Admin</span>
            </h2>
            <nav>
                <div class="nav-section">
                    <span class="nav-label">GÉNÉRAL</span>
                    <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Tableau de bord</a>
                    <a href="?page=sections" class="<?= $page === 'sections' ? 'active' : '' ?>">🎛️ Sections du site</a>
                </div>

                <div class="nav-section">
                    <span class="nav-label">CONTENU</span>
                    <a href="?page=services" class="<?= $page === 'services' ? 'active' : '' ?>">⚙️ Services</a>
                    <a href="?page=tarifs" class="<?= $page === 'tarifs' ? 'active' : '' ?>">💰 Tarifs</a>
                    <a href="?page=logements" class="<?= $page === 'logements' ? 'active' : '' ?>">🏘️ Logements</a>
                    <a href="?page=avis" class="<?= $page === 'avis' ? 'active' : '' ?>">⭐ Avis</a>
                    <a href="?page=distinctions" class="<?= $page === 'distinctions' ? 'active' : '' ?>">🏆 Distinctions</a>
                    <a href="?page=blog" class="<?= $page === 'blog' ? 'active' : '' ?>">📝 Blog</a>
                </div>

                <div class="nav-section">
                    <span class="nav-label">PROSPECTS</span>
                    <a href="?page=simulations" class="<?= $page === 'simulations' ? 'active' : '' ?>">
                        📈 Simulations
                        <?php if ($newSimulations > 0): ?>
                        <span class="badge" style="background: #10B981;"><?= $newSimulations ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=contacts" class="<?= $page === 'contacts' ? 'active' : '' ?>">
                        ✉️ Messages
                        <?php if ($unreadContacts > 0): ?>
                        <span class="badge"><?= $unreadContacts ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=newsletter" class="<?= $page === 'newsletter' ? 'active' : '' ?>">📬 Newsletter</a>
                </div>

                <div class="nav-section">
                    <span class="nav-label">ANALYTICS</span>
                    <a href="?page=analytics" class="<?= $page === 'analytics' ? 'active' : '' ?>">📈 Statistiques</a>
                </div>

                <div class="nav-section">
                    <span class="nav-label">CONFIGURATION</span>
                    <a href="?page=simulateur_config" class="<?= $page === 'simulateur_config' ? 'active' : '' ?>">🧮 Simulateur</a>
                    <a href="?page=rgpd" class="<?= $page === 'rgpd' ? 'active' : '' ?>">🛡️ RGPD & Cookies</a>
                    <a href="?page=users" class="<?= $page === 'users' ? 'active' : '' ?>">👥 Utilisateurs</a>
                    <a href="?page=parametres" class="<?= $page === 'parametres' ? 'active' : '' ?>">⚡ Paramètres</a>
                </div>

                <div class="nav-section" style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <a href="../index.php" target="_blank" style="opacity: 0.8;">🌐 Voir le site</a>
                    <a href="?logout=1" style="opacity: 0.8;">🚪 Déconnexion</a>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
            <!-- Tableau de bord amélioré -->
            <h1 class="page-title">Tableau de bord</h1>

            <?php
            // Calcul des statistiques avancées
            $totalSimulations = count($simulations);
            $simulationsContactees = count(array_filter($simulations, fn($s) => $s['contacted']));
            $tauxConversion = $totalSimulations > 0 ? round(($simulationsContactees / $totalSimulations) * 100) : 0;

            // Stats par période (30 derniers jours)
            $stmt = $conn->query("SELECT DATE(created_at) as jour, COUNT(*) as nb FROM FC_simulations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY jour ASC");
            $simParJour = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->query("SELECT DATE(created_at) as jour, COUNT(*) as nb FROM FC_contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND archive = 0 GROUP BY DATE(created_at) ORDER BY jour ASC");
            $contactsParJour = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Simulations aujourd'hui et cette semaine
            $simAujourdhui = $conn->query("SELECT COUNT(*) FROM FC_simulations WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $simSemaine = $conn->query("SELECT COUNT(*) FROM FC_simulations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

            // Revenu mensuel moyen estimé
            $revenuMoyenEstime = $conn->query("SELECT AVG(revenu_mensuel_estime) FROM FC_simulations WHERE revenu_mensuel_estime > 0")->fetchColumn();
            ?>

            <!-- Alertes -->
            <?php if ($newSimulations > 0 || $unreadContacts > 0): ?>
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <?php if ($newSimulations > 0): ?>
                <div style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; padding: 1rem 1.5rem; border-radius: 10px; display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 250px;">
                    <span style="font-size: 2rem;">📊</span>
                    <div>
                        <strong style="font-size: 1.3rem;"><?= $newSimulations ?> nouvelle(s) simulation(s)</strong>
                        <p style="opacity: 0.9; font-size: 0.9rem;">à contacter rapidement</p>
                    </div>
                    <a href="?page=simulations" style="margin-left: auto; background: white; color: #10B981; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; font-weight: bold;">Voir</a>
                </div>
                <?php endif; ?>
                <?php if ($unreadContacts > 0): ?>
                <div style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: white; padding: 1rem 1.5rem; border-radius: 10px; display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 250px;">
                    <span style="font-size: 2rem;">✉️</span>
                    <div>
                        <strong style="font-size: 1.3rem;"><?= $unreadContacts ?> message(s) non lu(s)</strong>
                        <p style="opacity: 0.9; font-size: 0.9rem;">en attente de réponse</p>
                    </div>
                    <a href="?page=contacts" style="margin-left: auto; background: white; color: #F59E0B; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; font-weight: bold;">Voir</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Statistiques principales -->
            <div class="stats-grid">
                <div class="stat-card" style="border-left: 4px solid #10B981;">
                    <div class="number" style="color: #10B981;"><?= $totalSimulations ?></div>
                    <div class="label">Simulations totales</div>
                    <small style="color: #6B7280;">+<?= $simSemaine ?> cette semaine</small>
                </div>
                <div class="stat-card" style="border-left: 4px solid #3B82F6;">
                    <div class="number" style="color: #3B82F6;"><?= $tauxConversion ?>%</div>
                    <div class="label">Taux de suivi</div>
                    <small style="color: #6B7280;"><?= $simulationsContactees ?> contactées</small>
                </div>
                <div class="stat-card" style="border-left: 4px solid #8B5CF6;">
                    <div class="number" style="color: #8B5CF6;"><?= number_format($revenuMoyenEstime ?? 0, 0) ?>€</div>
                    <div class="label">Revenu moyen estimé</div>
                    <small style="color: #6B7280;">par simulation</small>
                </div>
                <div class="stat-card" style="border-left: 4px solid #EF4444;">
                    <div class="number" style="color: #EF4444;"><?= $unreadContacts ?></div>
                    <div class="label">Messages non lus</div>
                    <small style="color: #6B7280;">sur <?= count($contacts) ?> total</small>
                </div>
            </div>

            <!-- Graphiques -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="card">
                    <h3>Simulations (30 derniers jours)</h3>
                    <canvas id="chartSimulations" height="200"></canvas>
                </div>
                <div class="card">
                    <h3>Messages reçus (30 derniers jours)</h3>
                    <canvas id="chartContacts" height="200"></canvas>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
            // Données pour les graphiques
            const simData = <?= json_encode($simParJour) ?>;
            const contactData = <?= json_encode($contactsParJour) ?>;

            // Générer les 30 derniers jours
            const labels = [];
            const simValues = [];
            const contactValues = [];
            const today = new Date();

            for (let i = 29; i >= 0; i--) {
                const d = new Date(today);
                d.setDate(d.getDate() - i);
                const dateStr = d.toISOString().split('T')[0];
                labels.push(d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }));

                const simDay = simData.find(s => s.jour === dateStr);
                simValues.push(simDay ? parseInt(simDay.nb) : 0);

                const contDay = contactData.find(c => c.jour === dateStr);
                contactValues.push(contDay ? parseInt(contDay.nb) : 0);
            }

            // Graphique Simulations
            new Chart(document.getElementById('chartSimulations'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Simulations',
                        data: simValues,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 10 } }
                    }
                }
            });

            // Graphique Contacts
            new Chart(document.getElementById('chartContacts'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Messages',
                        data: contactValues,
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 10 } }
                    }
                }
            });
            </script>

            <!-- Dernières activités -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                <div class="card">
                    <h3>Dernières simulations</h3>
                    <?php if (empty($simulations)): ?>
                    <p>Aucune simulation.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Email</th>
                                <th>Estimation</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($simulations, 0, 5) as $sim): ?>
                            <tr class="<?= !$sim['contacted'] ? 'unread' : '' ?>">
                                <td><?= e(date('d/m H:i', strtotime($sim['created_at']))) ?></td>
                                <td><?= e($sim['email']) ?></td>
                                <td><strong><?= number_format($sim['revenu_mensuel_estime'] ?? 0, 0) ?>€</strong>/mois</td>
                                <td>
                                    <?php if ($sim['contacted']): ?>
                                    <span style="color: #10B981;">✓ Contacté</span>
                                    <?php else: ?>
                                    <span style="color: #F59E0B;">⏳ En attente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 1rem;"><a href="?page=simulations" style="color: var(--bleu-clair);">Voir toutes les simulations →</a></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Derniers messages</h3>
                    <?php if (empty($contacts)): ?>
                    <p>Aucun message.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Nom</th>
                                <th>Sujet</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($contacts, 0, 5) as $contact): ?>
                            <tr class="<?= !$contact['lu'] ? 'unread' : '' ?>">
                                <td><?= e(date('d/m H:i', strtotime($contact['created_at']))) ?></td>
                                <td><?= e($contact['nom']) ?></td>
                                <td><?= e($contact['sujet'] ?: 'Sans sujet') ?></td>
                                <td>
                                    <?php
                                    $statut = $contact['statut'] ?? ($contact['lu'] ? 'traite' : 'nouveau');
                                    $statutLabels = [
                                        'nouveau' => ['⭐ Nouveau', '#F59E0B'],
                                        'en_cours' => ['🔄 En cours', '#3B82F6'],
                                        'traite' => ['✓ Traité', '#10B981'],
                                    ];
                                    $label = $statutLabels[$statut] ?? ['?', '#6B7280'];
                                    ?>
                                    <span style="color: <?= $label[1] ?>;"><?= $label[0] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 1rem;"><a href="?page=contacts" style="color: var(--bleu-clair);">Voir tous les messages →</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($page === 'sections'): ?>
            <!-- Gestion des sections -->
            <h1 class="page-title">🎛️ Sections du site</h1>

            <p style="margin-bottom: 1.5rem; color: #6B7280;">
                Activez ou désactivez les différentes sections de votre site. Vous pouvez également modifier leur ordre d'affichage.
            </p>

            <form method="POST">
                <div class="card">
                    <h3>Sections disponibles</h3>
                    <div style="display: grid; gap: 1rem;">
                        <?php
                        $sectionIcons = [
                            'hero' => '🏠',
                            'services' => '⚙️',
                            'tarifs' => '💰',
                            'simulateur' => '📊',
                            'galerie' => '🏘️',
                            'distinctions' => '🏆',
                            'avis' => '⭐',
                            'legal' => '📜',
                            'contact' => '✉️',
                        ];
                        foreach ($sections as $section):
                            $icon = $sectionIcons[$section['section_key']] ?? '📄';
                        ?>
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: <?= $section['actif'] ? '#F0FDF4' : '#FEF2F2' ?>; border-radius: 8px; border-left: 4px solid <?= $section['actif'] ? '#10B981' : '#EF4444' ?>;">
                            <span style="font-size: 1.8rem;"><?= $icon ?></span>
                            <div style="flex: 1;">
                                <strong style="font-size: 1.1rem;"><?= e($section['section_label']) ?></strong>
                                <div style="font-size: 0.85rem; color: #6B7280;">Clé: <?= e($section['section_key']) ?></div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <label style="font-size: 0.85rem; color: #6B7280;">Ordre:</label>
                                    <input type="number" name="sections[<?= e($section['section_key']) ?>][ordre]" value="<?= $section['ordre'] ?>" style="width: 60px; padding: 0.3rem; border: 1px solid #e5e7eb; border-radius: 5px;">
                                </div>
                                <label class="switch" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="sections[<?= e($section['section_key']) ?>][actif]" <?= $section['actif'] ? 'checked' : '' ?> style="width: 20px; height: 20px; cursor: pointer;">
                                    <span style="font-weight: bold; color: <?= $section['actif'] ? '#10B981' : '#EF4444' ?>;"><?= $section['actif'] ? 'Activé' : 'Désactivé' ?></span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="update_sections" class="btn btn-primary" style="margin-top: 1.5rem;">💾 Enregistrer les modifications</button>
                </form>
            </div>

            <div class="card" style="background: #FEF3C7; border-left: 4px solid #F59E0B;">
                <h3 style="color: #92400E;">💡 Aide</h3>
                <ul style="margin-left: 1.5rem; color: #92400E;">
                    <li><strong>Hero</strong> : Bannière principale avec le slogan et bouton d'action</li>
                    <li><strong>Services</strong> : Liste de vos prestations</li>
                    <li><strong>Tarifs</strong> : Grille tarifaire</li>
                    <li><strong>Simulateur</strong> : Outil d'estimation des revenus</li>
                    <li><strong>Galerie</strong> : Photos de vos logements gérés</li>
                    <li><strong>Distinctions</strong> : Vos certifications et récompenses (Booking, Airbnb...)</li>
                    <li><strong>Avis</strong> : Témoignages de vos clients propriétaires</li>
                    <li><strong>Legal</strong> : Informations juridiques obligatoires</li>
                    <li><strong>Contact</strong> : Formulaire de contact</li>
                </ul>
            </div>

            <?php elseif ($page === 'settings'): ?>
            <!-- Paramètres -->
            <h1 class="page-title">Paramètres du site</h1>

            <div class="card">
                <h3>Informations générales</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Nom du site</label>
                        <input type="text" name="settings[site_nom]" value="<?= e($settings['site_nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Slogan</label>
                        <input type="text" name="settings[site_slogan]" value="<?= e($settings['site_slogan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="settings[site_description]"><?= e($settings['site_description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="settings[adresse]" value="<?= e($settings['adresse'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="settings[telephone]" value="<?= e($settings['telephone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="settings[email]" value="<?= e($settings['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Horaires</label>
                        <input type="text" name="settings[horaires]" value="<?= e($settings['horaires'] ?? '') ?>">
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>

            <?php elseif ($page === 'logements'): ?>
            <!-- Logements -->
            <h1 class="page-title">Gestion des logements</h1>

            <div class="card">
                <h3>Ajouter un logement</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="titre" required>
                        </div>
                        <div class="form-group">
                            <label>Localisation</label>
                            <input type="text" name="localisation" value="Compiègne">
                        </div>
                        <div class="form-group">
                            <label>Type de bien</label>
                            <select name="type_bien">
                                <option value="Appartement">Appartement</option>
                                <option value="Studio">Studio</option>
                                <option value="Maison">Maison</option>
                                <option value="Loft">Loft</option>
                                <option value="Duplex">Duplex</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ordre d'affichage</label>
                            <input type="number" name="ordre" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Photo du logement</label>
                        <input type="file" name="photo" accept="image/*" style="padding: 0.5rem;">
                        <small style="color: #6B7280; display: block; margin-top: 0.3rem;">Formats acceptés: JPG, PNG, WebP. Max 5 Mo.</small>
                    </div>
                    <button type="submit" name="add_logement" class="btn btn-primary">Ajouter</button>
                </form>
            </div>

            <div class="card">
                <h3>Logements existants</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Localisation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logements as $logement): ?>
                        <tr>
                            <td><?= e($logement['titre']) ?></td>
                            <td><?= e($logement['type_bien']) ?></td>
                            <td><?= e($logement['localisation']) ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce logement ?')">
                                    <input type="hidden" name="logement_id" value="<?= $logement['id'] ?>">
                                    <button type="submit" name="delete_logement" class="btn btn-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($page === 'avis'): ?>
            <!-- Avis -->
            <h1 class="page-title">Gestion des avis</h1>

            <?php
            // Créer la table des codes si nécessaire
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
            } catch (PDOException $e) {}

            // Générer un code
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
                $email = trim($_POST['code_email'] ?? '');
                $nom = trim($_POST['code_nom'] ?? '');
                $adresse = trim($_POST['code_adresse'] ?? '');

                if (!empty($email) && !empty($nom)) {
                    $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                    try {
                        $stmt = $conn->prepare("INSERT INTO FC_avis_codes (code, email, nom_proprietaire, adresse_bien) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$code, $email, $nom, $adresse]);

                        // Envoyer l'email avec le code au propriétaire
                        $nomSite = $settings['site_nom'] ?? 'Frenchy Conciergerie';
                        $emailSite = $settings['email'] ?? '';
                        $lienAvis = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/avis.php';

                        $sujetEmail = "Votre code pour donner votre avis - $nomSite";
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
        <h2 style='color: #1E3A8A;'>Bonjour " . htmlspecialchars($nom) . ",</h2>

        <p>Nous vous remercions pour votre confiance et souhaitons recueillir votre avis sur nos services de conciergerie.</p>

        <p>Votre témoignage est précieux et aidera d'autres propriétaires à découvrir nos services.</p>

        <div style='background: white; border: 2px dashed #1E3A8A; padding: 20px; margin: 25px 0; text-align: center; border-radius: 10px;'>
            <p style='margin: 0 0 10px 0; color: #666;'>Votre code personnel :</p>
            <p style='font-size: 2rem; font-weight: bold; letter-spacing: 8px; color: #1E3A8A; margin: 0;'>$code</p>
        </div>

        <div style='text-align: center; margin: 25px 0;'>
            <a href='$lienAvis' style='display: inline-block; background: #1E3A8A; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 1.1rem;'>Donner mon avis</a>
        </div>

        <p style='font-size: 0.9rem; color: #666;'><strong>Comment ça marche ?</strong></p>
        <ol style='font-size: 0.9rem; color: #666;'>
            <li>Cliquez sur le bouton ci-dessus</li>
            <li>Entrez votre code <strong>$code</strong> et votre email <strong>" . htmlspecialchars($email) . "</strong></li>
            <li>Rédigez votre témoignage</li>
        </ol>

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

                        $emailSent = @mail($email, $sujetEmail, $corpsEmail, $headers);

                        if ($emailSent) {
                            echo '<div class="alert alert-success">✅ Code généré et email envoyé à <strong>' . e($email) . '</strong> !<br>Code : <strong style="font-size: 1.3rem; letter-spacing: 2px;">' . e($code) . '</strong></div>';
                        } else {
                            echo '<div class="alert alert-warning">⚠️ Code généré : <strong style="font-size: 1.3rem; letter-spacing: 2px;">' . e($code) . '</strong><br>L\'email n\'a pas pu être envoyé automatiquement. Veuillez envoyer le code manuellement à ' . e($email) . '</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Erreur lors de la génération du code.</div>';
                    }
                }
            }

            // Supprimer un code
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_code'])) {
                $codeId = intval($_POST['code_id']);
                $stmt = $conn->prepare("DELETE FROM FC_avis_codes WHERE id = ?");
                $stmt->execute([$codeId]);
            }

            // Récupérer les codes existants
            $avisCodes = [];
            try {
                $stmt = $conn->query("SELECT * FROM FC_avis_codes ORDER BY created_at DESC LIMIT 50");
                $avisCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}

            // Récupérer les avis en attente
            $avisEnAttente = [];
            try {
                $stmt = $conn->query("SELECT * FROM FC_avis WHERE actif = 0 ORDER BY date_avis DESC");
                $avisEnAttente = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}

            // Valider un avis
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_avis'])) {
                $avisId = intval($_POST['avis_id']);
                $stmt = $conn->prepare("UPDATE FC_avis SET actif = 1 WHERE id = ?");
                $stmt->execute([$avisId]);
                header("Location: ?page=avis");
                exit;
            }
            ?>

            <!-- Onglets -->
            <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 2px solid #E5E7EB;">
                <button onclick="showTab('avis-list')" class="tab-btn active" id="tab-avis-list">📋 Avis publiés (<?= count($avis) ?>)</button>
                <button onclick="showTab('avis-pending')" class="tab-btn" id="tab-avis-pending">⏳ En attente (<?= count($avisEnAttente) ?>)</button>
                <button onclick="showTab('codes')" class="tab-btn" id="tab-codes">🔑 Codes d'invitation</button>
                <button onclick="showTab('add-avis')" class="tab-btn" id="tab-add-avis">➕ Ajouter manuellement</button>
            </div>

            <style>
                .tab-btn { background: none; border: none; padding: 0.8rem 1.2rem; cursor: pointer; font-size: 0.95rem; color: #6B7280; border-bottom: 3px solid transparent; margin-bottom: -2px; }
                .tab-btn.active { color: var(--bleu-frenchy); border-bottom-color: var(--bleu-frenchy); font-weight: 600; }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
            </style>

            <!-- Avis publiés -->
            <div id="avis-list" class="tab-content active">
                <div class="card">
                    <h3>Avis publiés sur le site</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Note</th>
                                <th>Commentaire</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avis as $avi): ?>
                            <tr>
                                <td><?= e($avi['nom']) ?></td>
                                <td><?= renderStars($avi['note']) ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($avi['commentaire']) ?></td>
                                <td><?= e($avi['date_avis']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cet avis ?')">
                                        <input type="hidden" name="avis_id" value="<?= $avi['id'] ?>">
                                        <button type="submit" name="delete_avis" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Avis en attente -->
            <div id="avis-pending" class="tab-content">
                <div class="card">
                    <h3>Avis en attente de validation</h3>
                    <?php if (empty($avisEnAttente)): ?>
                    <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucun avis en attente de validation.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Note</th>
                                <th>Commentaire</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avisEnAttente as $avi): ?>
                            <tr style="background: #FEF3C7;">
                                <td><?= e($avi['nom']) ?></td>
                                <td><?= renderStars($avi['note']) ?></td>
                                <td style="max-width: 300px;"><?= e($avi['commentaire']) ?></td>
                                <td><?= e($avi['date_avis']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="avis_id" value="<?= $avi['id'] ?>">
                                        <button type="submit" name="validate_avis" class="btn btn-success btn-sm">✓ Valider</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cet avis ?')">
                                        <input type="hidden" name="avis_id" value="<?= $avi['id'] ?>">
                                        <button type="submit" name="delete_avis" class="btn btn-danger btn-sm">✗ Refuser</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Codes d'invitation -->
            <div id="codes" class="tab-content">
                <div class="card" style="background: linear-gradient(135deg, #DBEAFE 0%, #EDE9FE 100%);">
                    <h3>🔑 Générer un code d'invitation</h3>
                    <p style="margin-bottom: 1rem; color: #4B5563;">Créez un code unique pour permettre à un propriétaire de soumettre son avis de manière authentifiée.</p>

                    <form method="POST">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Email du propriétaire *</label>
                                <input type="email" name="code_email" required placeholder="proprietaire@email.com">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Nom du propriétaire *</label>
                                <input type="text" name="code_nom" required placeholder="M. Dupont">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Adresse du bien (optionnel)</label>
                                <input type="text" name="code_adresse" placeholder="Appartement rue de Paris, Compiègne">
                            </div>
                        </div>
                        <button type="submit" name="generate_code" class="btn btn-primary" style="margin-top: 1rem;">🎫 Générer le code</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Codes générés</h3>
                    <p style="margin-bottom: 1rem; color: #6B7280;">Lien à envoyer aux propriétaires : <code style="background: #F3F4F6; padding: 0.3rem 0.6rem; border-radius: 4px;"><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/avis.php</code></p>

                    <?php if (empty($avisCodes)): ?>
                    <p style="color: #6B7280; text-align: center; padding: 2rem;">Aucun code généré pour le moment.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Propriétaire</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Créé le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avisCodes as $codeData): ?>
                            <tr style="<?= $codeData['used'] ? 'background: #D1FAE5;' : '' ?>">
                                <td><code style="font-size: 1.1rem; font-weight: bold; letter-spacing: 2px;"><?= e($codeData['code']) ?></code></td>
                                <td><?= e($codeData['nom_proprietaire']) ?></td>
                                <td><?= e($codeData['email']) ?></td>
                                <td>
                                    <?php if ($codeData['used']): ?>
                                    <span style="color: #059669; font-weight: bold;">✓ Utilisé</span>
                                    <?php else: ?>
                                    <span style="color: #D97706;">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($codeData['created_at'])) ?></td>
                                <td>
                                    <?php if (!$codeData['used']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce code ?')">
                                        <input type="hidden" name="code_id" value="<?= $codeData['id'] ?>">
                                        <button type="submit" name="delete_code" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ajouter manuellement -->
            <div id="add-avis" class="tab-content">
                <div class="card">
                    <h3>Ajouter un avis manuellement</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="nom" required>
                        </div>
                        <div class="form-group">
                            <label>Rôle</label>
                            <input type="text" name="role" value="Propriétaire">
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date_avis" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Note (1-5)</label>
                            <select name="note">
                                <option value="5">5 étoiles</option>
                                <option value="4">4 étoiles</option>
                                <option value="3">3 étoiles</option>
                                <option value="2">2 étoiles</option>
                                <option value="1">1 étoile</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Commentaire</label>
                            <textarea name="commentaire" required></textarea>
                        </div>
                        <button type="submit" name="add_avis" class="btn btn-primary">Ajouter</button>
                    </form>
                </div>
            </div>

            <script>
            function showTab(tabId) {
                document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
                document.getElementById('tab-' + tabId).classList.add('active');
            }
            </script>

            <?php elseif ($page === 'services'): ?>
            <!-- Services -->
            <h1 class="page-title">Gestion des services</h1>

            <div class="card">
                <h3>Ajouter un service</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="titre" required placeholder="Ex: Gestion Locative Complète">
                    </div>
                    <div class="form-group">
                        <label>Icône (emoji)</label>
                        <input type="text" name="icone" value="🏠" placeholder="🏠">
                    </div>
                    <div class="form-group">
                        <label>Info carte (sous le titre)</label>
                        <input type="text" name="carte_info" placeholder="Ex: Activité exercée sous carte...">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Description du service"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Liste des prestations (une par ligne)</label>
                        <textarea name="liste_items" rows="5" placeholder="Gestion des réservations&#10;Accueil des voyageurs&#10;Coordination ménage"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ordre d'affichage</label>
                        <input type="number" name="ordre" value="0">
                    </div>
                    <button type="submit" name="add_service" class="btn btn-primary">Ajouter</button>
                </form>
            </div>

            <div class="card">
                <h3>Services existants</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Icône</th>
                            <th>Titre</th>
                            <th>Actif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= $service['ordre'] ?></td>
                            <td style="font-size: 1.5rem;"><?= $service['icone'] ?></td>
                            <td><?= e($service['titre']) ?></td>
                            <td><?= $service['actif'] ? '✓ Oui' : '✗ Non' ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="editService(<?= htmlspecialchars(json_encode($service)) ?>)">Modifier</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce service ?')">
                                    <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                    <button type="submit" name="delete_service" class="btn btn-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal modification service -->
            <div id="editServiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="background: white; max-width: 600px; margin: 50px auto; padding: 2rem; border-radius: 10px; max-height: 90vh; overflow-y: auto;">
                    <h3>Modifier le service</h3>
                    <form method="POST">
                        <input type="hidden" name="service_id" id="edit_service_id">
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="titre" id="edit_service_titre" required>
                        </div>
                        <div class="form-group">
                            <label>Icône</label>
                            <input type="text" name="icone" id="edit_service_icone">
                        </div>
                        <div class="form-group">
                            <label>Info carte</label>
                            <input type="text" name="carte_info" id="edit_service_carte_info">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_service_description" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Liste des prestations (une par ligne)</label>
                            <textarea name="liste_items" id="edit_service_liste_items" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Ordre</label>
                            <input type="number" name="ordre" id="edit_service_ordre">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="actif" id="edit_service_actif"> Actif</label>
                        </div>
                        <button type="submit" name="edit_service" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn" onclick="closeEditService()">Annuler</button>
                    </form>
                </div>
            </div>

            <script>
            function editService(service) {
                document.getElementById('edit_service_id').value = service.id;
                document.getElementById('edit_service_titre').value = service.titre;
                document.getElementById('edit_service_icone').value = service.icone;
                document.getElementById('edit_service_carte_info').value = service.carte_info || '';
                document.getElementById('edit_service_description').value = service.description || '';
                const items = JSON.parse(service.liste_items || '[]');
                document.getElementById('edit_service_liste_items').value = items.join('\n');
                document.getElementById('edit_service_ordre').value = service.ordre;
                document.getElementById('edit_service_actif').checked = service.actif == 1;
                document.getElementById('editServiceModal').style.display = 'block';
            }
            function closeEditService() {
                document.getElementById('editServiceModal').style.display = 'none';
            }
            </script>

            <?php elseif ($page === 'tarifs'): ?>
            <!-- Tarifs -->
            <h1 class="page-title">Gestion des tarifs</h1>

            <div class="card">
                <h3>Ajouter un tarif</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="titre" required placeholder="Ex: Logement Équipé">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Montant</label>
                            <input type="number" name="montant" step="0.01" required placeholder="Ex: 24.00">
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select name="type_tarif">
                                <option value="pourcentage">Pourcentage (%)</option>
                                <option value="euro">Euro (€)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Ex: TTC des revenus locatifs">
                    </div>
                    <div class="form-group">
                        <label>Détails</label>
                        <textarea name="details" rows="2" placeholder="Détails supplémentaires"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Ordre d'affichage</label>
                        <input type="number" name="ordre" value="0">
                    </div>
                    <button type="submit" name="add_tarif" class="btn btn-primary">Ajouter</button>
                </form>
            </div>

            <div class="card">
                <h3>Tarifs existants</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Titre</th>
                            <th>Montant</th>
                            <th>Description</th>
                            <th>Actif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarifs as $tarif): ?>
                        <tr>
                            <td><?= $tarif['ordre'] ?></td>
                            <td><?= e($tarif['titre']) ?></td>
                            <td><strong><?= number_format($tarif['montant'] ?? $tarif['pourcentage'] ?? 0, 2) ?><?= ($tarif['type_tarif'] ?? 'pourcentage') === 'euro' ? ' €' : ' %' ?></strong></td>
                            <td><?= e($tarif['description']) ?></td>
                            <td><?= $tarif['actif'] ? '✓ Oui' : '✗ Non' ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="editTarif(<?= htmlspecialchars(json_encode($tarif)) ?>)">Modifier</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce tarif ?')">
                                    <input type="hidden" name="tarif_id" value="<?= $tarif['id'] ?>">
                                    <button type="submit" name="delete_tarif" class="btn btn-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal modification tarif -->
            <div id="editTarifModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="background: white; max-width: 500px; margin: 50px auto; padding: 2rem; border-radius: 10px;">
                    <h3>Modifier le tarif</h3>
                    <form method="POST">
                        <input type="hidden" name="tarif_id" id="edit_tarif_id">
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="titre" id="edit_tarif_titre" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Montant</label>
                                <input type="number" name="montant" id="edit_tarif_montant" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <select name="type_tarif" id="edit_tarif_type">
                                    <option value="pourcentage">Pourcentage (%)</option>
                                    <option value="euro">Euro (€)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" id="edit_tarif_description">
                        </div>
                        <div class="form-group">
                            <label>Détails</label>
                            <textarea name="details" id="edit_tarif_details" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Ordre</label>
                            <input type="number" name="ordre" id="edit_tarif_ordre">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="actif" id="edit_tarif_actif"> Actif</label>
                        </div>
                        <button type="submit" name="edit_tarif" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn" onclick="closeEditTarif()">Annuler</button>
                    </form>
                </div>
            </div>

            <script>
            function editTarif(tarif) {
                document.getElementById('edit_tarif_id').value = tarif.id;
                document.getElementById('edit_tarif_titre').value = tarif.titre;
                document.getElementById('edit_tarif_montant').value = tarif.montant || tarif.pourcentage || 0;
                document.getElementById('edit_tarif_type').value = tarif.type_tarif || 'pourcentage';
                document.getElementById('edit_tarif_description').value = tarif.description || '';
                document.getElementById('edit_tarif_details').value = tarif.details || '';
                document.getElementById('edit_tarif_ordre').value = tarif.ordre;
                document.getElementById('edit_tarif_actif').checked = tarif.actif == 1;
                document.getElementById('editTarifModal').style.display = 'block';
            }
            function closeEditTarif() {
                document.getElementById('editTarifModal').style.display = 'none';
            }
            </script>

            <?php elseif ($page === 'distinctions'): ?>
            <!-- Distinctions -->
            <h1 class="page-title">Distinctions & Certifications</h1>

            <div class="card">
                <h3>Ajouter une distinction</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="titre" required placeholder="Ex: Traveller Review Awards 2026">
                    </div>
                    <div class="form-group">
                        <label>Icône (emoji)</label>
                        <input type="text" name="icone" value="🏆" placeholder="🏆">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Description de la distinction"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Image (nom du fichier)</label>
                        <input type="text" name="image" placeholder="booking-award.png">
                    </div>
                    <div class="form-group">
                        <label>Ordre d'affichage</label>
                        <input type="number" name="ordre" value="0">
                    </div>
                    <button type="submit" name="add_distinction" class="btn btn-primary">Ajouter</button>
                </form>
            </div>

            <div class="card">
                <h3>Distinctions existantes</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Icône</th>
                            <th>Titre</th>
                            <th>Actif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distinctions as $distinction): ?>
                        <tr>
                            <td><?= $distinction['ordre'] ?></td>
                            <td style="font-size: 1.5rem;"><?= $distinction['icone'] ?></td>
                            <td><?= e($distinction['titre']) ?></td>
                            <td><?= $distinction['actif'] ? '✓ Oui' : '✗ Non' ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="editDistinction(<?= htmlspecialchars(json_encode($distinction)) ?>)">Modifier</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette distinction ?')">
                                    <input type="hidden" name="distinction_id" value="<?= $distinction['id'] ?>">
                                    <button type="submit" name="delete_distinction" class="btn btn-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal modification distinction -->
            <div id="editDistinctionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="background: white; max-width: 600px; margin: 50px auto; padding: 2rem; border-radius: 10px; max-height: 90vh; overflow-y: auto;">
                    <h3>Modifier la distinction</h3>
                    <form method="POST">
                        <input type="hidden" name="distinction_id" id="edit_distinction_id">
                        <div class="form-group">
                            <label>Titre</label>
                            <input type="text" name="titre" id="edit_distinction_titre" required>
                        </div>
                        <div class="form-group">
                            <label>Icône</label>
                            <input type="text" name="icone" id="edit_distinction_icone">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_distinction_description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Image</label>
                            <input type="text" name="image" id="edit_distinction_image">
                        </div>
                        <div class="form-group">
                            <label>Ordre</label>
                            <input type="number" name="ordre" id="edit_distinction_ordre">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="actif" id="edit_distinction_actif"> Actif</label>
                        </div>
                        <button type="submit" name="edit_distinction" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn" onclick="closeEditDistinction()">Annuler</button>
                    </form>
                </div>
            </div>

            <script>
            function editDistinction(distinction) {
                document.getElementById('edit_distinction_id').value = distinction.id;
                document.getElementById('edit_distinction_titre').value = distinction.titre;
                document.getElementById('edit_distinction_icone').value = distinction.icone;
                document.getElementById('edit_distinction_description').value = distinction.description || '';
                document.getElementById('edit_distinction_image').value = distinction.image || '';
                document.getElementById('edit_distinction_ordre').value = distinction.ordre;
                document.getElementById('edit_distinction_actif').checked = distinction.actif == 1;
                document.getElementById('editDistinctionModal').style.display = 'block';
            }
            function closeEditDistinction() {
                document.getElementById('editDistinctionModal').style.display = 'none';
            }
            </script>

            <?php elseif ($page === 'simulations'): ?>
            <!-- Simulations -->
            <h1 class="page-title">Demandes de Simulation</h1>

            <?php
            $simStatuts = [
                'a_contacter' => ['⏳ À contacter', '#F59E0B', 'warning'],
                'contacte' => ['📞 Contacté', '#3B82F6', 'info'],
                'converti' => ['✅ Converti', '#10B981', 'success'],
                'perdu' => ['❌ Perdu', '#EF4444', 'danger'],
            ];
            $nbParStatut = [];
            foreach ($simStatuts as $key => $val) {
                $nbParStatut[$key] = count(array_filter($simulations, fn($s) => ($s['statut'] ?? 'a_contacter') === $key));
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card" style="border-left: 4px solid #F59E0B;">
                    <div class="number" style="color: #F59E0B;"><?= $nbParStatut['a_contacter'] ?></div>
                    <div class="label">À contacter</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #3B82F6;">
                    <div class="number" style="color: #3B82F6;"><?= $nbParStatut['contacte'] ?></div>
                    <div class="label">Contactés</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #10B981;">
                    <div class="number" style="color: #10B981;"><?= $nbParStatut['converti'] ?></div>
                    <div class="label">Convertis</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #EF4444;">
                    <div class="number" style="color: #EF4444;"><?= $nbParStatut['perdu'] ?></div>
                    <div class="label">Perdus</div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card" style="padding: 1rem;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="?page=simulations" class="btn <?= !isset($_GET['statut']) ? 'btn-primary' : '' ?>">Tous (<?= count($simulations) ?>)</a>
                    <?php foreach ($simStatuts as $key => $val): ?>
                    <a href="?page=simulations&statut=<?= $key ?>" class="btn <?= ($_GET['statut'] ?? '') === $key ? 'btn-primary' : '' ?>" style="<?= ($_GET['statut'] ?? '') !== $key ? 'background: ' . $val[1] . '20; color: ' . $val[1] : '' ?>">
                        <?= $val[0] ?> (<?= $nbParStatut[$key] ?>)
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            $filteredSims = $simulations;
            if (isset($_GET['statut']) && isset($simStatuts[$_GET['statut']])) {
                $filteredSims = array_filter($simulations, fn($s) => ($s['statut'] ?? 'a_contacter') === $_GET['statut']);
            }
            ?>

            <div class="card">
                <h3>Demandes de simulation</h3>
                <?php if (empty($filteredSims)): ?>
                <p>Aucune demande de simulation.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Email</th>
                            <th>Bien</th>
                            <th>Estimation</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredSims as $sim): ?>
                        <?php $statut = $sim['statut'] ?? 'a_contacter'; ?>
                        <tr class="<?= $statut === 'a_contacter' ? 'unread' : '' ?>">
                            <td><?= e(date('d/m/Y H:i', strtotime($sim['created_at']))) ?></td>
                            <td>
                                <a href="mailto:<?= e($sim['email']) ?>"><?= e($sim['email']) ?></a>
                                <form method="POST" style="display: inline; margin-left: 0.5rem;">
                                    <input type="hidden" name="email_to" value="<?= e($sim['email']) ?>">
                                    <input type="hidden" name="email_subject" value="Votre simulation Frenchy Conciergerie">
                                    <input type="hidden" name="email_body" value="">
                                    <button type="button" onclick="openEmailModal('<?= e($sim['email']) ?>', 'Votre simulation Frenchy Conciergerie - <?= e($sim['surface']) ?>m² à <?= e($sim['ville']) ?>')" style="background: none; border: none; cursor: pointer; font-size: 1rem;" title="Envoyer un email">✉️</button>
                                </form>
                            </td>
                            <td>
                                <?= e($sim['surface'] ?? '-') ?> m² | <?= e($sim['capacite'] ?? '-') ?> pers.<br>
                                <small style="color: #6B7280;"><?= e($sim['ville'] ?? '-') ?></small>
                            </td>
                            <td>
                                <?php if (!empty($sim['tarif_nuit_estime'])): ?>
                                <strong><?= number_format($sim['tarif_nuit_estime'], 0) ?>€/nuit</strong><br>
                                <span style="color: #10B981; font-weight: bold;"><?= number_format($sim['revenu_mensuel_estime'] ?? 0, 0) ?>€/mois</span>
                                <?php else: ?>
                                <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="simulation_id" value="<?= $sim['id'] ?>">
                                    <select name="statut" onchange="this.form.submit()" style="padding: 0.3rem; border-radius: 5px; border: 2px solid <?= $simStatuts[$statut][1] ?>; background: <?= $simStatuts[$statut][1] ?>20; font-size: 0.85rem;">
                                        <?php foreach ($simStatuts as $key => $val): ?>
                                        <option value="<?= $key ?>" <?= $statut === $key ? 'selected' : '' ?>><?= $val[0] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_simulation_statut" value="1">
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette simulation ?')">
                                    <input type="hidden" name="simulation_id" value="<?= $sim['id'] ?>">
                                    <button type="submit" name="delete_simulation" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Informations légales du simulateur (Avis de valeur)</h3>
                <p style="background: #FEF3C7; padding: 1rem; border-radius: 5px; font-size: 0.9rem;">
                    <strong>Mentions affichées aux utilisateurs :</strong><br><br>
                    "Cet avis de valeur est fourni à titre purement indicatif et informatif. Il ne constitue en aucun cas une garantie de revenus,
                    un engagement contractuel, ni une promesse de résultats. Les montants affichés sont calculés sur la base de moyennes de marché
                    observées et peuvent varier significativement selon de nombreux facteurs non pris en compte : saisonnalité, état et qualité du logement,
                    équipements, qualité des photos et annonces, localisation exacte, concurrence locale, événements, réglementation locale, etc.
                    Seule une étude personnalisée réalisée par nos experts peut fournir une analyse précise de votre bien. En utilisant ce simulateur,
                    vous reconnaissez avoir pris connaissance de ces limitations et consentez à être recontacté par nos services pour une étude approfondie de votre projet."
                </p>
            </div>

            <?php elseif ($page === 'simulateur_config'): ?>
            <!-- Configuration du Simulateur -->
            <h1 class="page-title">Configuration du Simulateur</h1>

            <div class="card">
                <h3>Paramètres de calcul</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($simulateurConfig as $config): ?>
                        <div class="form-group">
                            <label><?= e($config['config_label']) ?></label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="number" name="config[<?= e($config['config_key']) ?>]"
                                       value="<?= e($config['config_value']) ?>"
                                       step="0.01" style="flex: 1;">
                                <span style="color: #6B7280;"><?= $config['config_type'] === 'percent' ? '%' : ($config['config_type'] === 'number' ? '€' : '') ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($simulateurConfig)): ?>
                    <p style="color: #F59E0B;">Aucune configuration trouvée. Veuillez relancer l'installation de la base de données.</p>
                    <?php else: ?>
                    <button type="submit" name="update_simulateur_config" class="btn btn-primary" style="margin-top: 1rem;">Enregistrer les paramètres</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <h3>Ajouter une ville</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Nom de la ville</label>
                            <input type="text" name="ville" required placeholder="Ex: Compiègne">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Majoration (%)</label>
                            <input type="number" name="majoration_percent" step="0.01" value="0" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Ordre</label>
                            <input type="number" name="ordre" value="0">
                        </div>
                        <button type="submit" name="add_ville" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Majorations par ville</h3>
                <p style="color: #6B7280; margin-bottom: 1rem;">Ces majorations s'appliquent au tarif de base en fonction de la localisation du bien.</p>
                <?php if (empty($simulateurVilles)): ?>
                <p>Aucune ville configurée.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Ville</th>
                            <th>Majoration</th>
                            <th>Actif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulateurVilles as $ville): ?>
                        <tr>
                            <td><?= $ville['ordre'] ?></td>
                            <td><?= e($ville['ville']) ?></td>
                            <td><strong><?= number_format($ville['majoration_percent'], 2) ?>%</strong></td>
                            <td><?= $ville['actif'] ? '✓ Oui' : '✗ Non' ?></td>
                            <td>
                                <button class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;" onclick="editVille(<?= htmlspecialchars(json_encode($ville)) ?>)">Modifier</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette ville ?')">
                                    <input type="hidden" name="ville_id" value="<?= $ville['id'] ?>">
                                    <button type="submit" name="delete_ville" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Suppr.</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Formule de calcul</h3>
                <div style="background: #F3F4F6; padding: 1.5rem; border-radius: 8px; font-family: monospace; font-size: 0.9rem;">
                    <p><strong>Tarif nuit =</strong> (Tarif base × Capacité) + (Majoration m² × Surface)</p>
                    <p style="margin-top: 0.5rem;"><strong>Tarif nuit final =</strong> Tarif nuit × (1 + Majorations équipements % + Majoration ville %)</p>
                    <p style="margin-top: 0.5rem;"><strong>Revenu brut/mois =</strong> Tarif nuit final × 30 × Taux occupation</p>
                    <p style="margin-top: 0.5rem;"><strong>Coût ménage/mois =</strong> Surface × Coût ménage/m² × Nb rotations</p>
                    <p style="margin-top: 0.5rem;"><strong>Revenu net/mois =</strong> Revenu brut - (Revenu brut × Commission %) - Coût ménage</p>
                </div>
            </div>

            <!-- Modal modification ville -->
            <div id="editVilleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="background: white; max-width: 400px; margin: 100px auto; padding: 2rem; border-radius: 10px;">
                    <h3>Modifier la ville</h3>
                    <form method="POST">
                        <input type="hidden" name="ville_id" id="edit_ville_id">
                        <div class="form-group">
                            <label>Nom de la ville</label>
                            <input type="text" name="ville" id="edit_ville_nom" required>
                        </div>
                        <div class="form-group">
                            <label>Majoration (%)</label>
                            <input type="number" name="majoration_percent" id="edit_ville_majoration" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Ordre</label>
                            <input type="number" name="ordre" id="edit_ville_ordre">
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="actif" id="edit_ville_actif"> Actif</label>
                        </div>
                        <button type="submit" name="edit_ville" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn" onclick="closeEditVille()">Annuler</button>
                    </form>
                </div>
            </div>

            <script>
            function editVille(ville) {
                document.getElementById('edit_ville_id').value = ville.id;
                document.getElementById('edit_ville_nom').value = ville.ville;
                document.getElementById('edit_ville_majoration').value = ville.majoration_percent;
                document.getElementById('edit_ville_ordre').value = ville.ordre;
                document.getElementById('edit_ville_actif').checked = ville.actif == 1;
                document.getElementById('editVilleModal').style.display = 'block';
            }
            function closeEditVille() {
                document.getElementById('editVilleModal').style.display = 'none';
            }
            </script>

            <?php elseif ($page === 'contacts'): ?>
            <!-- Messages -->
            <h1 class="page-title">Messages de contact</h1>

            <?php
            $contactStatuts = [
                'nouveau' => ['⭐ Nouveau', '#F59E0B'],
                'en_cours' => ['🔄 En cours', '#3B82F6'],
                'traite' => ['✅ Traité', '#10B981'],
            ];
            $nbParStatutContact = [];
            foreach ($contactStatuts as $key => $val) {
                $nbParStatutContact[$key] = count(array_filter($contacts, fn($c) => ($c['statut'] ?? 'nouveau') === $key));
            }
            ?>

            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <?php foreach ($contactStatuts as $key => $val): ?>
                <div class="stat-card" style="border-left: 4px solid <?= $val[1] ?>;">
                    <div class="number" style="color: <?= $val[1] ?>;"><?= $nbParStatutContact[$key] ?></div>
                    <div class="label"><?= $val[0] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="?page=contacts" class="btn <?= !$showArchived && !isset($_GET['statut']) ? 'btn-primary' : '' ?>">Tous actifs (<?= count($contacts) ?>)</a>
                <?php foreach ($contactStatuts as $key => $val): ?>
                <a href="?page=contacts&statut=<?= $key ?>" class="btn <?= ($_GET['statut'] ?? '') === $key ? 'btn-primary' : '' ?>" style="<?= ($_GET['statut'] ?? '') !== $key ? 'background: ' . $val[1] . '20; color: ' . $val[1] : '' ?>">
                    <?= $val[0] ?>
                </a>
                <?php endforeach; ?>
                <a href="?page=contacts&archives=1" class="btn <?= $showArchived ? 'btn-primary' : '' ?>" style="margin-left: auto;">📦 Archives</a>
            </div>

            <?php
            $filteredContacts = $contacts;
            if (isset($_GET['statut']) && isset($contactStatuts[$_GET['statut']])) {
                $filteredContacts = array_filter($contacts, fn($c) => ($c['statut'] ?? 'nouveau') === $_GET['statut']);
            }
            ?>

            <div class="card">
                <h3><?= $showArchived ? 'Messages archivés' : 'Messages' ?></h3>
                <?php if (empty($filteredContacts)): ?>
                <p>Aucun message.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Message</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredContacts as $contact): ?>
                        <?php $statut = $contact['statut'] ?? 'nouveau'; ?>
                        <tr class="<?= $statut === 'nouveau' ? 'unread' : '' ?>">
                            <td><?= e(date('d/m/Y H:i', strtotime($contact['created_at']))) ?></td>
                            <td><strong><?= e($contact['nom']) ?></strong></td>
                            <td>
                                <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
                                <button type="button" onclick="openEmailModal('<?= e($contact['email']) ?>', 'Re: <?= e($contact['sujet'] ?: 'Votre demande') ?>')" style="background: none; border: none; cursor: pointer; font-size: 1rem;" title="Répondre">✉️</button>
                                <?php if ($contact['telephone']): ?>
                                <br><a href="tel:<?= e($contact['telephone']) ?>"><?= e($contact['telephone']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 300px;">
                                <strong><?= e($contact['sujet'] ?: 'Sans sujet') ?></strong><br>
                                <small style="color: #6B7280;"><?= e(substr($contact['message'], 0, 100)) ?><?= strlen($contact['message']) > 100 ? '...' : '' ?></small>
                                <?php if (strlen($contact['message']) > 100): ?>
                                <button onclick="alert('<?= e(addslashes($contact['message'])) ?>')" style="background: none; border: none; color: #3B82F6; cursor: pointer; font-size: 0.8rem;">Voir tout</button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$showArchived): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <select name="statut" onchange="this.form.submit()" style="padding: 0.3rem; border-radius: 5px; border: 2px solid <?= $contactStatuts[$statut][1] ?>; background: <?= $contactStatuts[$statut][1] ?>20; font-size: 0.85rem;">
                                        <?php foreach ($contactStatuts as $key => $val): ?>
                                        <option value="<?= $key ?>" <?= $statut === $key ? 'selected' : '' ?>><?= $val[0] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_contact_statut" value="1">
                                </form>
                                <?php else: ?>
                                <span style="color: #6B7280;">Archivé</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ($showArchived): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <button type="submit" name="unarchive_contact" class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Restaurer</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <button type="submit" name="archive_contact" class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: #6B7280; color: white;">📦</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer définitivement ?')">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <button type="submit" name="delete_contact" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'rgpd'): ?>
            <!-- Configuration RGPD & Cookies -->
            <h1 class="page-title">🛡️ RGPD & Gestion des Cookies</h1>

            <p style="margin-bottom: 1.5rem; color: #6B7280;">
                Configurez les textes et paramètres relatifs à la conformité RGPD et à la gestion des cookies de votre site.
            </p>

            <?php
            // Charger les paramètres RGPD existants
            $rgpdSettings = [];
            try {
                $stmt = $conn->query("SELECT config_key, config_value FROM FC_rgpd_config");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rgpdSettings[$row['config_key']] = $row['config_value'];
                }
            } catch (PDOException $e) {
                // Table n'existe pas, on la crée
                $conn->exec("CREATE TABLE IF NOT EXISTS FC_rgpd_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(100) UNIQUE NOT NULL,
                    config_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }

            // Traitement du formulaire
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rgpd'])) {
                foreach ($_POST['rgpd'] as $key => $value) {
                    $stmt = $conn->prepare("INSERT INTO FC_rgpd_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                    $stmt->execute([$key, $value]);
                }
                echo '<div class="alert alert-success">Configuration RGPD enregistrée avec succès.</div>';
                // Recharger les paramètres
                $stmt = $conn->query("SELECT config_key, config_value FROM FC_rgpd_config");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rgpdSettings[$row['config_key']] = $row['config_value'];
                }
            }
            ?>

            <form method="POST">
                <input type="hidden" name="save_rgpd" value="1">

                <div class="card">
                    <h3>🍪 Bandeau de Cookies</h3>
                    <div class="form-group">
                        <label>Titre du bandeau</label>
                        <input type="text" name="rgpd[cookie_banner_title]" value="<?= e($rgpdSettings['cookie_banner_title'] ?? 'Gestion des cookies') ?>">
                    </div>
                    <div class="form-group">
                        <label>Texte du bandeau</label>
                        <textarea name="rgpd[cookie_banner_text]" rows="3"><?= e($rgpdSettings['cookie_banner_text'] ?? 'Nous utilisons des cookies pour améliorer votre expérience sur notre site, analyser notre trafic et personnaliser le contenu. En cliquant sur "Tout accepter", vous consentez à l\'utilisation de tous les cookies.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Texte bouton "Accepter"</label>
                        <input type="text" name="rgpd[cookie_btn_accept]" value="<?= e($rgpdSettings['cookie_btn_accept'] ?? 'Tout accepter') ?>">
                    </div>
                    <div class="form-group">
                        <label>Texte bouton "Refuser"</label>
                        <input type="text" name="rgpd[cookie_btn_refuse]" value="<?= e($rgpdSettings['cookie_btn_refuse'] ?? 'Tout refuser') ?>">
                    </div>
                    <div class="form-group">
                        <label>Texte bouton "Paramétrer"</label>
                        <input type="text" name="rgpd[cookie_btn_settings]" value="<?= e($rgpdSettings['cookie_btn_settings'] ?? 'Paramétrer') ?>">
                    </div>
                </div>

                <div class="card">
                    <h3>📋 Types de Cookies</h3>
                    <div class="form-group">
                        <label>Description cookies essentiels</label>
                        <textarea name="rgpd[cookie_essential_desc]" rows="2"><?= e($rgpdSettings['cookie_essential_desc'] ?? 'Ces cookies sont indispensables au fonctionnement du site et ne peuvent pas être désactivés.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Description cookies analytiques</label>
                        <textarea name="rgpd[cookie_analytics_desc]" rows="2"><?= e($rgpdSettings['cookie_analytics_desc'] ?? 'Ces cookies nous permettent de mesurer l\'audience de notre site et d\'améliorer son contenu.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Description cookies marketing</label>
                        <textarea name="rgpd[cookie_marketing_desc]" rows="2"><?= e($rgpdSettings['cookie_marketing_desc'] ?? 'Ces cookies sont utilisés pour vous proposer des publicités personnalisées.') ?></textarea>
                    </div>
                </div>

                <div class="card">
                    <h3>✅ Consentement Formulaires</h3>
                    <div class="form-group">
                        <label>Texte consentement formulaire de contact</label>
                        <textarea name="rgpd[contact_consent_text]" rows="2"><?= e($rgpdSettings['contact_consent_text'] ?? 'J\'accepte que mes données personnelles soient collectées et traitées pour répondre à ma demande de contact.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Texte consentement newsletter</label>
                        <textarea name="rgpd[newsletter_consent_text]" rows="2"><?= e($rgpdSettings['newsletter_consent_text'] ?? 'J\'accepte de recevoir des informations et actualités par email.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Texte consentement simulateur</label>
                        <textarea name="rgpd[simulator_consent_text]" rows="2"><?= e($rgpdSettings['simulator_consent_text'] ?? 'J\'accepte que mes données soient utilisées pour recevoir un avis de valeur personnalisé et être recontacté.') ?></textarea>
                    </div>
                </div>

                <div class="card">
                    <h3>📄 Politique de Confidentialité</h3>
                    <div class="form-group">
                        <label>Email du DPO (Délégué à la Protection des Données)</label>
                        <input type="email" name="rgpd[dpo_email]" value="<?= e($rgpdSettings['dpo_email'] ?? $settings['email_legal'] ?? $settings['email'] ?? '') ?>">
                        <small style="color: #6B7280;">Si non défini, l'email légal sera utilisé.</small>
                    </div>
                    <div class="form-group">
                        <label>Durée de conservation des données de contact (années)</label>
                        <input type="number" name="rgpd[retention_contact]" min="1" max="10" value="<?= e($rgpdSettings['retention_contact'] ?? '3') ?>">
                    </div>
                    <div class="form-group">
                        <label>Durée de conservation des données clients (années)</label>
                        <input type="number" name="rgpd[retention_client]" min="1" max="15" value="<?= e($rgpdSettings['retention_client'] ?? '5') ?>">
                    </div>
                    <div class="form-group">
                        <label>Durée des cookies (mois)</label>
                        <input type="number" name="rgpd[cookie_duration]" min="1" max="13" value="<?= e($rgpdSettings['cookie_duration'] ?? '13') ?>">
                    </div>
                </div>

                <div class="card">
                    <h3>🏛️ Médiation de la Consommation</h3>
                    <div class="form-group">
                        <label>Nom du médiateur</label>
                        <input type="text" name="rgpd[mediateur_nom]" value="<?= e($rgpdSettings['mediateur_nom'] ?? 'GIE IMMOMEDIATEURS') ?>">
                    </div>
                    <div class="form-group">
                        <label>Adresse du médiateur</label>
                        <input type="text" name="rgpd[mediateur_adresse]" value="<?= e($rgpdSettings['mediateur_adresse'] ?? '55 Avenue Marceau, 75116 Paris') ?>">
                    </div>
                    <div class="form-group">
                        <label>Site web du médiateur</label>
                        <input type="url" name="rgpd[mediateur_site]" value="<?= e($rgpdSettings['mediateur_site'] ?? 'https://www.immomediateurs.fr') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email du médiateur</label>
                        <input type="email" name="rgpd[mediateur_email]" value="<?= e($rgpdSettings['mediateur_email'] ?? 'contact@immomediateurs.fr') ?>">
                    </div>
                    <div class="form-group">
                        <label>Lien de saisine en ligne</label>
                        <input type="url" name="rgpd[mediateur_saisine]" value="<?= e($rgpdSettings['mediateur_saisine'] ?? 'https://www.immomediateurs.fr/saisir-le-mediateur/') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">💾 Enregistrer la configuration RGPD</button>
            </form>

            <div class="card" style="margin-top: 2rem; background: #F0FDF4; border-left: 4px solid #10B981;">
                <h3>✅ Checklist de conformité RGPD</h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 0.5rem 0;">✔️ Bandeau de consentement cookies configuré</li>
                    <li style="padding: 0.5rem 0;">✔️ Politique de confidentialité présente sur le site</li>
                    <li style="padding: 0.5rem 0;">✔️ Mentions légales détaillées</li>
                    <li style="padding: 0.5rem 0;">✔️ Formulaires avec cases de consentement</li>
                    <li style="padding: 0.5rem 0;">✔️ Coordonnées du médiateur de la consommation</li>
                    <li style="padding: 0.5rem 0;">✔️ Email de contact pour exercer les droits RGPD</li>
                    <li style="padding: 0.5rem 0;">✔️ Durées de conservation des données définies</li>
                </ul>
                <p style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 8px;">
                    <strong>💡 Conseil :</strong> Pensez à vérifier régulièrement que votre email de contact RGPD (<?= e($rgpdSettings['dpo_email'] ?? $settings['email_legal'] ?? $settings['email'] ?? 'non défini') ?>) est bien fonctionnel et que les demandes d'exercice de droits sont traitées dans les délais légaux (1 mois maximum).
                </p>
            </div>

            <?php elseif ($page === 'parametres'): ?>
            <!-- Paramètres du site -->
            <h1 class="page-title">Paramètres du site</h1>

            <form method="POST">
                <div class="card">
                    <h3>Informations de l'entreprise</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>Nom du site</label>
                            <input type="text" name="settings[site_nom]" value="<?= e($settings['site_nom'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Slogan</label>
                            <input type="text" name="settings[site_slogan]" value="<?= e($settings['site_slogan'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="settings[site_description]" rows="2"><?= e($settings['site_description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Adresse</label>
                            <input type="text" name="settings[adresse]" value="<?= e($settings['adresse'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="text" name="settings[telephone]" value="<?= e($settings['telephone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="settings[email]" value="<?= e($settings['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Horaires</label>
                            <input type="text" name="settings[horaires]" value="<?= e($settings['horaires'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Informations légales</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>Forme juridique</label>
                            <input type="text" name="settings[forme_juridique]" value="<?= e($settings['forme_juridique'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Capital social</label>
                            <input type="text" name="settings[capital]" value="<?= e($settings['capital'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>SIRET</label>
                            <input type="text" name="settings[siret]" value="<?= e($settings['siret'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>RCS</label>
                            <input type="text" name="settings[rcs]" value="<?= e($settings['rcs'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>N° TVA intracommunautaire</label>
                            <input type="text" name="settings[tva_intra]" value="<?= e($settings['tva_intra'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Présidente / Gérant(e)</label>
                            <input type="text" name="settings[presidente]" value="<?= e($settings['presidente'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email légal</label>
                            <input type="email" name="settings[email_legal]" value="<?= e($settings['email_legal'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Téléphone légal</label>
                            <input type="text" name="settings[telephone_legal]" value="<?= e($settings['telephone_legal'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Cartes professionnelles</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>N° Carte Transaction</label>
                            <input type="text" name="settings[carte_transaction]" value="<?= e($settings['carte_transaction'] ?? '') ?>" placeholder="Ex: CPI 6002 2025 001 000 003">
                        </div>
                        <div class="form-group">
                            <label>Validité Carte Transaction</label>
                            <input type="text" name="settings[carte_transaction_validite]" value="<?= e($settings['carte_transaction_validite'] ?? '') ?>" placeholder="Ex: 23 janvier 2028">
                        </div>
                        <div class="form-group">
                            <label>N° Carte Gestion</label>
                            <input type="text" name="settings[carte_gestion]" value="<?= e($settings['carte_gestion'] ?? '') ?>" placeholder="Ex: CPI 6002 2025 001 000 003">
                        </div>
                        <div class="form-group">
                            <label>Validité Carte Gestion</label>
                            <input type="text" name="settings[carte_gestion_validite]" value="<?= e($settings['carte_gestion_validite'] ?? '') ?>" placeholder="Ex: 22 septembre 2028">
                        </div>
                        <div class="form-group">
                            <label>CCI émettrice</label>
                            <input type="text" name="settings[cci_emettrice]" value="<?= e($settings['cci_emettrice'] ?? 'CCI de l\'Oise') ?>">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Garanties & Assurance</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>Assureur</label>
                            <input type="text" name="settings[assureur]" value="<?= e($settings['assureur'] ?? '') ?>" placeholder="Ex: GENERALI IARD">
                        </div>
                        <div class="form-group">
                            <label>Adresse assureur</label>
                            <input type="text" name="settings[assureur_adresse]" value="<?= e($settings['assureur_adresse'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>N° contrat assurance</label>
                            <input type="text" name="settings[assurance_contrat]" value="<?= e($settings['assurance_contrat'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Validité assurance</label>
                            <input type="text" name="settings[assurance_validite]" value="<?= e($settings['assurance_validite'] ?? '') ?>" placeholder="Ex: du 01/01/2026 au 31/12/2026">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Médiateur</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>Nom du médiateur</label>
                            <input type="text" name="settings[mediateur_nom]" value="<?= e($settings['mediateur_nom'] ?? 'GIE IMMOMEDIATEURS') ?>">
                        </div>
                        <div class="form-group">
                            <label>Adresse médiateur</label>
                            <input type="text" name="settings[mediateur_adresse]" value="<?= e($settings['mediateur_adresse'] ?? '55 Avenue Marceau, 75116 Paris') ?>">
                        </div>
                        <div class="form-group">
                            <label>Site web médiateur</label>
                            <input type="text" name="settings[mediateur_site]" value="<?= e($settings['mediateur_site'] ?? 'www.immomediateurs.fr') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email médiateur</label>
                            <input type="email" name="settings[mediateur_email]" value="<?= e($settings['mediateur_email'] ?? 'contact@immomediateurs.fr') ?>">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3>Mentions légales du simulateur</h3>
                    <div class="form-group">
                        <label>Texte des conditions (case à cocher)</label>
                        <textarea name="settings[simulateur_conditions]" rows="3" style="width: 100%;"><?= e($settings['simulateur_conditions'] ?? 'J\'accepte que mes données soient utilisées pour recevoir un avis de valeur personnalisé et être recontacté par Frenchy Conciergerie. Je comprends que cet avis est indicatif et ne constitue pas une garantie de revenus.') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Mentions légales (affichées sous le simulateur)</label>
                        <textarea name="settings[simulateur_mentions]" rows="5" style="width: 100%;"><?= e($settings['simulateur_mentions'] ?? 'Cet avis de valeur est fourni à titre purement indicatif et informatif. Il ne constitue en aucun cas une garantie de revenus, un engagement contractuel, ni une promesse de résultats. Les montants affichés sont calculés sur la base de moyennes de marché observées et peuvent varier significativement selon de nombreux facteurs non pris en compte : saisonnalité, état et qualité du logement, équipements, qualité des photos et annonces, localisation exacte, concurrence locale, événements, réglementation locale, etc. Seule une étude personnalisée réalisée par nos experts peut fournir une analyse précise de votre bien. En utilisant ce simulateur, vous reconnaissez avoir pris connaissance de ces limitations et consentez à être recontacté par nos services pour une étude approfondie de votre projet.') ?></textarea>
                    </div>
                </div>

                <button type="submit" name="update_settings" class="btn btn-primary" style="margin-top: 1rem;">Enregistrer les paramètres</button>
            </form>

            <?php elseif ($page === 'newsletter'): ?>
            <!-- Newsletter -->
            <h1 class="page-title">Newsletter & Emails</h1>

            <?php
            // Comptage des destinataires potentiels
            $nbEmailsSim = $conn->query("SELECT COUNT(DISTINCT email) FROM FC_simulations")->fetchColumn();
            $nbEmailsContacts = $conn->query("SELECT COUNT(DISTINCT email) FROM FC_contacts")->fetchColumn();

            // Historique des emails envoyés
            try {
                $stmt = $conn->query("SELECT * FROM FC_emails_sent ORDER BY sent_at DESC LIMIT 20");
                $emailsSent = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $emailsSent = [];
            }
            ?>

            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-card" style="border-left: 4px solid #10B981;">
                    <div class="number" style="color: #10B981;"><?= $nbEmailsSim ?></div>
                    <div class="label">Emails simulations</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #3B82F6;">
                    <div class="number" style="color: #3B82F6;"><?= $nbEmailsContacts ?></div>
                    <div class="label">Emails contacts</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #8B5CF6;">
                    <div class="number" style="color: #8B5CF6;"><?= count($emailsSent) ?></div>
                    <div class="label">Emails envoyés</div>
                </div>
            </div>

            <!-- Envoyer un email individuel -->
            <div class="card">
                <h3>✉️ Envoyer un email individuel</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Destinataire</label>
                            <input type="email" name="email_to" required placeholder="email@exemple.com">
                        </div>
                        <div class="form-group">
                            <label>Sujet</label>
                            <input type="text" name="email_subject" required placeholder="Sujet de l'email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="email_body" rows="6" required placeholder="Votre message..."></textarea>
                    </div>
                    <button type="submit" name="send_email" class="btn btn-primary">Envoyer l'email</button>
                </form>
            </div>

            <!-- Envoyer une newsletter -->
            <div class="card">
                <h3>📬 Envoyer une newsletter</h3>
                <form method="POST" onsubmit="return confirm('Envoyer cette newsletter à tous les destinataires sélectionnés ?')">
                    <div class="form-group">
                        <label>Destinataires</label>
                        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="to_simulations" checked>
                                <span>Prospects simulations (<?= $nbEmailsSim ?> emails)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="to_contacts">
                                <span>Contacts (<?= $nbEmailsContacts ?> emails)</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sujet de la newsletter</label>
                        <input type="text" name="newsletter_subject" required placeholder="Ex: Actualités Frenchy Conciergerie - Février 2026">
                    </div>
                    <div class="form-group">
                        <label>Contenu</label>
                        <textarea name="newsletter_body" rows="10" required placeholder="Bonjour,

Nous avons le plaisir de vous informer...

Cordialement,
L'équipe Frenchy Conciergerie"></textarea>
                    </div>
                    <div style="background: #FEF3C7; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <strong>⚠️ Attention :</strong> L'envoi de newsletters doit respecter le RGPD. Assurez-vous que les destinataires ont consenti à recevoir vos communications.
                    </div>
                    <button type="submit" name="send_newsletter" class="btn btn-primary" style="background: #8B5CF6;">📬 Envoyer la newsletter</button>
                </form>
            </div>

            <!-- Templates prédéfinis -->
            <div class="card">
                <h3>📝 Templates d'emails</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                    <div style="background: #F3F4F6; padding: 1rem; border-radius: 8px;">
                        <h4 style="margin-bottom: 0.5rem;">Relance simulation</h4>
                        <p style="font-size: 0.85rem; color: #6B7280; margin-bottom: 1rem;">Pour relancer un prospect qui a fait une simulation</p>
                        <button type="button" class="btn btn-primary" style="font-size: 0.85rem;" onclick="useTemplate('relance')">Utiliser</button>
                    </div>
                    <div style="background: #F3F4F6; padding: 1rem; border-radius: 8px;">
                        <h4 style="margin-bottom: 0.5rem;">Bienvenue nouveau client</h4>
                        <p style="font-size: 0.85rem; color: #6B7280; margin-bottom: 1rem;">Message de bienvenue après signature</p>
                        <button type="button" class="btn btn-primary" style="font-size: 0.85rem;" onclick="useTemplate('bienvenue')">Utiliser</button>
                    </div>
                    <div style="background: #F3F4F6; padding: 1rem; border-radius: 8px;">
                        <h4 style="margin-bottom: 0.5rem;">Newsletter mensuelle</h4>
                        <p style="font-size: 0.85rem; color: #6B7280; margin-bottom: 1rem;">Template pour actualités mensuelles</p>
                        <button type="button" class="btn btn-primary" style="font-size: 0.85rem;" onclick="useTemplate('newsletter')">Utiliser</button>
                    </div>
                </div>
            </div>

            <!-- Historique -->
            <?php if (!empty($emailsSent)): ?>
            <div class="card">
                <h3>📋 Historique des envois</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Destinataire</th>
                            <th>Sujet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailsSent as $email): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime($email['sent_at']))) ?></td>
                            <td><?= e($email['email_to']) ?></td>
                            <td><?= e($email['subject']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <script>
            const templates = {
                relance: {
                    subject: 'Votre projet de location saisonnière - Frenchy Conciergerie',
                    body: `Bonjour,

Suite à votre simulation sur notre site, nous souhaitions prendre de vos nouvelles concernant votre projet de location saisonnière.

Votre bien présente un excellent potentiel de rentabilité et nous serions ravis d'en discuter plus en détail avec vous.

Seriez-vous disponible cette semaine pour un échange téléphonique de 15 minutes ?

Cordialement,
L'équipe Frenchy Conciergerie`
                },
                bienvenue: {
                    subject: 'Bienvenue chez Frenchy Conciergerie !',
                    body: `Bonjour,

Nous sommes ravis de vous compter parmi nos clients !

Votre confiance nous honore et nous mettons tout en œuvre pour optimiser la rentabilité de votre bien.

Voici les prochaines étapes :
1. Prise de photos professionnelles
2. Création et optimisation de vos annonces
3. Mise en ligne sur les plateformes

N'hésitez pas à nous contacter si vous avez des questions.

Cordialement,
L'équipe Frenchy Conciergerie`
                },
                newsletter: {
                    subject: 'Actualités Frenchy Conciergerie - ' + new Date().toLocaleDateString('fr-FR', {month: 'long', year: 'numeric'}),
                    body: `Bonjour,

Voici les dernières actualités de Frenchy Conciergerie :

📊 MARCHÉ LOCATIF
[Vos actualités sur le marché]

🏠 NOS SERVICES
[Nouveautés de vos services]

💡 CONSEIL DU MOIS
[Un conseil pratique pour les propriétaires]

À bientôt !
L'équipe Frenchy Conciergerie`
                }
            };

            function useTemplate(name) {
                const tpl = templates[name];
                if (tpl) {
                    document.querySelector('input[name="newsletter_subject"]').value = tpl.subject;
                    document.querySelector('textarea[name="newsletter_body"]').value = tpl.body;
                    window.scrollTo({ top: document.querySelector('textarea[name="newsletter_body"]').offsetTop - 100, behavior: 'smooth' });
                }
            }
            </script>

            <?php elseif ($page === 'analytics'): ?>
            <!-- Analytics -->
            <h1 class="page-title">📈 Statistiques & Analytics</h1>

            <?php
            // Statistiques des visites
            try {
                // Visites aujourd'hui
                $visitesToday = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                // Visites cette semaine
                $visitesWeek = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
                // Visites ce mois
                $visitesMonth = $conn->query("SELECT COUNT(*) FROM FC_visites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
                // Visiteurs uniques (par IP)
                $uniqueVisitors = $conn->query("SELECT COUNT(DISTINCT ip_address) FROM FC_visites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

                // Top pages
                $topPages = $conn->query("SELECT page, COUNT(*) as nb FROM FC_visites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY page ORDER BY nb DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

                // Conversions
                $conversions = $conn->query("SELECT type, COUNT(*) as nb FROM FC_conversions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);

                // Visites par jour (7 derniers jours)
                $visitesParJour = $conn->query("SELECT DATE(created_at) as jour, COUNT(*) as nb FROM FC_visites WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY jour ASC")->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                $visitesToday = $visitesWeek = $visitesMonth = $uniqueVisitors = 0;
                $topPages = $conversions = $visitesParJour = [];
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card" style="border-left: 4px solid #10B981;">
                    <div class="number" style="color: #10B981;"><?= $visitesToday ?></div>
                    <div class="label">Visites aujourd'hui</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #3B82F6;">
                    <div class="number" style="color: #3B82F6;"><?= $visitesWeek ?></div>
                    <div class="label">Visites (7 jours)</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #8B5CF6;">
                    <div class="number" style="color: #8B5CF6;"><?= $visitesMonth ?></div>
                    <div class="label">Visites (30 jours)</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #F59E0B;">
                    <div class="number" style="color: #F59E0B;"><?= $uniqueVisitors ?></div>
                    <div class="label">Visiteurs uniques</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div class="card">
                    <h3>📊 Visites des 7 derniers jours</h3>
                    <div style="display: flex; align-items: flex-end; height: 200px; gap: 8px; padding: 1rem 0;">
                        <?php
                        $maxVisites = max(array_column($visitesParJour, 'nb') ?: [1]);
                        foreach ($visitesParJour as $v):
                            $height = ($v['nb'] / $maxVisites) * 100;
                            $jourNom = date('D', strtotime($v['jour']));
                        ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                            <div style="font-size: 0.8rem; margin-bottom: 5px;"><?= $v['nb'] ?></div>
                            <div style="width: 100%; background: linear-gradient(to top, #3B82F6, #60A5FA); height: <?= $height ?>%; border-radius: 4px 4px 0 0; min-height: 5px;"></div>
                            <div style="font-size: 0.75rem; color: #6B7280; margin-top: 5px;"><?= $jourNom ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>🎯 Conversions (30 jours)</h3>
                    <?php if (empty($conversions)): ?>
                        <p style="color: #6B7280;">Aucune conversion enregistrée</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Type</th><th>Nombre</th></tr></thead>
                            <tbody>
                            <?php foreach ($conversions as $c): ?>
                                <tr>
                                    <td><?= e($c['type']) ?></td>
                                    <td><strong><?= $c['nb'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 1.5rem;">
                <h3>📄 Top 10 pages visitées (30 jours)</h3>
                <?php if (empty($topPages)): ?>
                    <p style="color: #6B7280;">Aucune donnée disponible</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Page</th><th>Visites</th><th>%</th></tr></thead>
                        <tbody>
                        <?php foreach ($topPages as $p): ?>
                            <tr>
                                <td><?= e($p['page'] ?: '/') ?></td>
                                <td><strong><?= $p['nb'] ?></strong></td>
                                <td><?= round($p['nb'] / max($visitesMonth, 1) * 100, 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'users'): ?>
            <!-- Gestion Utilisateurs -->
            <h1 class="page-title">👥 Gestion des utilisateurs</h1>

            <?php
            // Traitement ajout utilisateur
            if (isset($_POST['add_user'])) {
                $username = trim($_POST['new_username'] ?? '');
                $email = trim($_POST['new_email'] ?? '');
                $password = $_POST['new_password'] ?? '';
                $role = $_POST['new_role'] ?? 'viewer';
                $nom = trim($_POST['new_nom'] ?? '');
                $prenom = trim($_POST['new_prenom'] ?? '');

                if (empty($username) || empty($email) || empty($password)) {
                    $message = 'Tous les champs obligatoires doivent être remplis';
                    $messageType = 'error';
                } else {
                    try {
                        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                        $stmt = $conn->prepare("INSERT INTO FC_users (username, email, password_hash, role, nom, prenom) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $passwordHash, $role, $nom, $prenom]);
                        $message = 'Utilisateur créé avec succès';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Erreur: cet utilisateur ou email existe déjà';
                        $messageType = 'error';
                    }
                }
            }

            // Traitement suppression
            if (isset($_POST['delete_user'])) {
                $userId = intval($_POST['user_id']);
                $stmt = $conn->prepare("DELETE FROM FC_users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'Utilisateur supprimé';
                $messageType = 'success';
            }

            // Traitement modification statut
            if (isset($_POST['toggle_user'])) {
                $userId = intval($_POST['user_id']);
                $conn->query("UPDATE FC_users SET actif = NOT actif WHERE id = $userId");
                $message = 'Statut modifié';
                $messageType = 'success';
            }

            // Liste des utilisateurs
            try {
                $users = $conn->query("SELECT * FROM FC_users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $users = [];
            }
            ?>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>➕ Ajouter un utilisateur</h3>
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label>Nom d'utilisateur *</label>
                            <input type="text" name="new_username" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="new_email" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe *</label>
                            <input type="password" name="new_password" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label>Rôle</label>
                            <select name="new_role">
                                <option value="viewer">Lecteur</option>
                                <option value="editor">Éditeur</option>
                                <option value="admin">Administrateur</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="new_prenom">
                        </div>
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="new_nom">
                        </div>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Créer l'utilisateur</button>
                </form>
            </div>

            <div class="card" style="margin-top: 1.5rem;">
                <h3>📋 Utilisateurs existants</h3>
                <?php if (empty($users)): ?>
                    <p style="color: #6B7280;">Aucun utilisateur créé</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['username']) ?></strong>
                                    <?php if ($u['prenom'] || $u['nom']): ?>
                                        <br><small style="color: #6B7280;"><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; background: <?= $u['role'] === 'super_admin' ? '#7C3AED' : ($u['role'] === 'admin' ? '#3B82F6' : ($u['role'] === 'editor' ? '#10B981' : '#6B7280')) ?>; color: white;">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: <?= $u['actif'] ? '#10B981' : '#EF4444' ?>;">
                                        <?= $u['actif'] ? '✅ Actif' : '❌ Inactif' ?>
                                    </span>
                                </td>
                                <td><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Jamais' ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="toggle_user" class="btn btn-sm"><?= $u['actif'] ? 'Désactiver' : 'Activer' ?></button>
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet utilisateur ?')">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php elseif ($page === 'blog'): ?>
            <!-- Gestion Blog -->
            <h1 class="page-title">📝 Gestion du Blog</h1>

            <?php
            // Traitement ajout/modification article
            if (isset($_POST['save_article'])) {
                $articleId = intval($_POST['article_id'] ?? 0);
                $titre = trim($_POST['article_titre'] ?? '');
                $slug = trim($_POST['article_slug'] ?? '');
                $contenu = $_POST['article_contenu'] ?? '';
                $extrait = trim($_POST['article_extrait'] ?? '');
                $categorieId = intval($_POST['article_categorie'] ?? 0) ?: null;
                $metaTitle = trim($_POST['article_meta_title'] ?? '');
                $metaDesc = trim($_POST['article_meta_description'] ?? '');
                $actif = isset($_POST['article_actif']) ? 1 : 0;
                $datePublication = $_POST['article_date'] ?: date('Y-m-d H:i:s');

                // Générer le slug si vide
                if (empty($slug)) {
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $titre));
                    $slug = trim($slug, '-');
                }

                if (empty($titre)) {
                    $message = 'Le titre est obligatoire';
                    $messageType = 'error';
                } else {
                    try {
                        if ($articleId > 0) {
                            $stmt = $conn->prepare("UPDATE FC_articles SET titre = ?, slug = ?, contenu = ?, extrait = ?, categorie_id = ?, meta_title = ?, meta_description = ?, actif = ?, date_publication = ? WHERE id = ?");
                            $stmt->execute([$titre, $slug, $contenu, $extrait, $categorieId, $metaTitle, $metaDesc, $actif, $datePublication, $articleId]);
                            $message = 'Article mis à jour';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO FC_articles (titre, slug, contenu, extrait, categorie_id, meta_title, meta_description, actif, date_publication) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$titre, $slug, $contenu, $extrait, $categorieId, $metaTitle, $metaDesc, $actif, $datePublication]);
                            $message = 'Article créé';
                        }
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Erreur: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            }

            // Suppression article
            if (isset($_POST['delete_article'])) {
                $articleId = intval($_POST['article_id']);
                $stmt = $conn->prepare("DELETE FROM FC_articles WHERE id = ?");
                $stmt->execute([$articleId]);
                $message = 'Article supprimé';
                $messageType = 'success';
            }

            // Gestion catégories
            if (isset($_POST['add_category'])) {
                $catNom = trim($_POST['cat_nom'] ?? '');
                $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $catNom));
                if (!empty($catNom)) {
                    $stmt = $conn->prepare("INSERT INTO FC_categories (nom, slug) VALUES (?, ?)");
                    $stmt->execute([$catNom, $catSlug]);
                    $message = 'Catégorie ajoutée';
                    $messageType = 'success';
                }
            }

            // Récupérer les articles
            try {
                $articles = $conn->query("SELECT a.*, c.nom as categorie_nom FROM FC_articles a LEFT JOIN FC_categories c ON a.categorie_id = c.id ORDER BY a.date_publication DESC")->fetchAll(PDO::FETCH_ASSOC);
                $categories = $conn->query("SELECT * FROM FC_categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $articles = [];
                $categories = [];
            }

            $editArticle = null;
            if (isset($_GET['edit'])) {
                $editId = intval($_GET['edit']);
                $stmt = $conn->prepare("SELECT * FROM FC_articles WHERE id = ?");
                $stmt->execute([$editId]);
                $editArticle = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            ?>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                <!-- Formulaire article -->
                <div class="card">
                    <h3><?= $editArticle ? '✏️ Modifier l\'article' : '➕ Nouvel article' ?></h3>
                    <form method="POST">
                        <input type="hidden" name="article_id" value="<?= $editArticle['id'] ?? 0 ?>">

                        <div class="form-group">
                            <label>Titre *</label>
                            <input type="text" name="article_titre" value="<?= e($editArticle['titre'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Slug (URL)</label>
                            <input type="text" name="article_slug" value="<?= e($editArticle['slug'] ?? '') ?>" placeholder="genere-automatiquement">
                        </div>

                        <div class="form-group">
                            <label>Extrait</label>
                            <textarea name="article_extrait" rows="2"><?= e($editArticle['extrait'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Contenu</label>
                            <textarea name="article_contenu" rows="15" style="font-family: monospace;"><?= e($editArticle['contenu'] ?? '') ?></textarea>
                            <small style="color: #6B7280;">HTML autorisé</small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Catégorie</label>
                                <select name="article_categorie">
                                    <option value="">-- Aucune --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($editArticle['categorie_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date de publication</label>
                                <input type="datetime-local" name="article_date" value="<?= $editArticle ? date('Y-m-d\TH:i', strtotime($editArticle['date_publication'])) : date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Meta Title (SEO)</label>
                                <input type="text" name="article_meta_title" value="<?= e($editArticle['meta_title'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Meta Description (SEO)</label>
                                <input type="text" name="article_meta_description" value="<?= e($editArticle['meta_description'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="article_actif" <?= ($editArticle['actif'] ?? 0) ? 'checked' : '' ?>>
                                Publié
                            </label>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" name="save_article" class="btn btn-primary"><?= $editArticle ? 'Mettre à jour' : 'Créer l\'article' ?></button>
                            <?php if ($editArticle): ?>
                                <a href="?page=blog" class="btn">Annuler</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Liste catégories -->
                <div>
                    <div class="card">
                        <h3>📂 Catégories</h3>
                        <form method="POST" style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <input type="text" name="cat_nom" placeholder="Nouvelle catégorie" style="flex: 1;">
                            <button type="submit" name="add_category" class="btn btn-primary">+</button>
                        </form>
                        <?php if (empty($categories)): ?>
                            <p style="color: #6B7280;">Aucune catégorie</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($categories as $cat): ?>
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid #E5E7EB;"><?= e($cat['nom']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Liste articles -->
            <div class="card" style="margin-top: 1.5rem;">
                <h3>📰 Articles existants (<?= count($articles) ?>)</h3>
                <?php if (empty($articles)): ?>
                    <p style="color: #6B7280;">Aucun article</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Catégorie</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($articles as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= e($a['titre']) ?></strong>
                                    <br><small style="color: #6B7280;">/article.php?slug=<?= e($a['slug']) ?></small>
                                </td>
                                <td><?= e($a['categorie_nom'] ?? '-') ?></td>
                                <td>
                                    <span style="padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; background: <?= $a['actif'] ? '#10B981' : '#F59E0B' ?>; color: white;">
                                        <?= $a['actif'] ? 'Publié' : 'Brouillon' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($a['date_publication'])) ?></td>
                                <td>
                                    <a href="?page=blog&edit=<?= $a['id'] ?>" class="btn btn-sm">Modifier</a>
                                    <a href="../article.php?slug=<?= e($a['slug']) ?>" target="_blank" class="btn btn-sm">Voir</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                        <button type="submit" name="delete_article" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?')">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <!-- Modal Email -->
    <div id="emailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 600px; margin: 50px auto; padding: 2rem; border-radius: 10px; max-height: 90vh; overflow-y: auto;">
            <h3>✉️ Envoyer un email</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Destinataire</label>
                    <input type="email" name="email_to" id="modal_email_to" required readonly style="background: #F3F4F6;">
                </div>
                <div class="form-group">
                    <label>Sujet</label>
                    <input type="text" name="email_subject" id="modal_email_subject" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="email_body" id="modal_email_body" rows="8" required placeholder="Votre message..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="send_email" class="btn btn-primary">Envoyer</button>
                    <button type="button" class="btn" onclick="closeEmailModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEmailModal(email, subject) {
        document.getElementById('modal_email_to').value = email;
        document.getElementById('modal_email_subject').value = subject || '';
        document.getElementById('modal_email_body').value = '';
        document.getElementById('emailModal').style.display = 'block';
    }
    function closeEmailModal() {
        document.getElementById('emailModal').style.display = 'none';
    }
    </script>
    <?php endif; ?>
</body>
</html>
