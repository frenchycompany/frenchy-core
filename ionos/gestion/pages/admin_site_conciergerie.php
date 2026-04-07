<?php
/**
 * Admin du site frenchyconciergerie.fr — intégré au panel gestion
 * Gestion complète : sections, services, tarifs, logements, avis, blog,
 * contacts, simulations, newsletter, analytics, RGPD, paramètres
 */
include '../config.php';
include '../pages/menu.php';

if (!($conn instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Vérifier que l'utilisateur est admin
if (($role ?? '') !== 'admin') {
    echo "<div class='fc-main'><div class='container mt-4'><div class='alert alert-danger'>Accès réservé aux administrateurs.</div></div></div>";
    exit;
}

// Charger les fonctions helper
require_once __DIR__ . '/../../../site-frenchyconciergerie-main/includes/functions.php';

// Créer toutes les tables FC_* si nécessaire
ensureFcTables($conn);

// CSRF helper
function fcCsrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
}
function fcCsrfCheck(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

$feedback = '';
$page = $_GET['fc_page'] ?? 'dashboard';

// ── POST handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && fcCsrfCheck()) {

    // Sections
    if (isset($_POST['update_sections'])) {
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
        foreach ($_POST['sections'] ?? [] as $key => $data) {
            $actif = isset($data['actif']) ? 1 : 0;
            $ordre = intval($data['ordre'] ?? 0);
            $stmt = $conn->prepare("UPDATE FC_sections SET actif = ?, ordre = ? WHERE section_key = ?");
            $stmt->execute([$actif, $ordre, $key]);
        }
        $feedback = "<div class='alert alert-success'>Sections mises à jour.</div>";
    }

    // Settings
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO FC_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $feedback = "<div class='alert alert-success'>Paramètres enregistrés.</div>";
    }

    // Services
    if (isset($_POST['add_service'])) {
        $listeItems = array_filter(array_map('trim', explode("\n", $_POST['liste_items'])));
        $stmt = $conn->prepare("INSERT INTO FC_services (titre, icone, carte_info, description, liste_items, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titre'], $_POST['icone'], $_POST['carte_info'] ?? '', $_POST['description'], json_encode($listeItems), $_POST['ordre'] ?? 0]);
        $feedback = "<div class='alert alert-success'>Service ajouté.</div>";
    }
    if (isset($_POST['edit_service'])) {
        $listeItems = array_filter(array_map('trim', explode("\n", $_POST['liste_items'])));
        $stmt = $conn->prepare("UPDATE FC_services SET titre = ?, icone = ?, carte_info = ?, description = ?, liste_items = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([$_POST['titre'], $_POST['icone'], $_POST['carte_info'] ?? '', $_POST['description'], json_encode($listeItems), $_POST['ordre'] ?? 0, isset($_POST['actif']) ? 1 : 0, $_POST['service_id']]);
        $feedback = "<div class='alert alert-success'>Service mis à jour.</div>";
    }
    if (isset($_POST['delete_service'])) {
        $conn->prepare("DELETE FROM FC_services WHERE id = ?")->execute([$_POST['service_id']]);
        $feedback = "<div class='alert alert-info'>Service supprimé.</div>";
    }

    // Tarifs
    if (isset($_POST['add_tarif'])) {
        $stmt = $conn->prepare("INSERT INTO FC_tarifs (titre, montant, type_tarif, description, details, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titre'], $_POST['montant'], $_POST['type_tarif'] ?? 'pourcentage', $_POST['description'], $_POST['details'] ?? '', $_POST['ordre'] ?? 0]);
        $feedback = "<div class='alert alert-success'>Tarif ajouté.</div>";
    }
    if (isset($_POST['edit_tarif'])) {
        $stmt = $conn->prepare("UPDATE FC_tarifs SET titre = ?, montant = ?, type_tarif = ?, description = ?, details = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([$_POST['titre'], $_POST['montant'], $_POST['type_tarif'] ?? 'pourcentage', $_POST['description'], $_POST['details'] ?? '', $_POST['ordre'] ?? 0, isset($_POST['actif']) ? 1 : 0, $_POST['tarif_id']]);
        $feedback = "<div class='alert alert-success'>Tarif mis à jour.</div>";
    }
    if (isset($_POST['delete_tarif'])) {
        $conn->prepare("DELETE FROM FC_tarifs WHERE id = ?")->execute([$_POST['tarif_id']]);
        $feedback = "<div class='alert alert-info'>Tarif supprimé.</div>";
    }

    // Distinctions
    if (isset($_POST['add_distinction'])) {
        $stmt = $conn->prepare("INSERT INTO FC_distinctions (titre, icone, description, image, ordre) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titre'], $_POST['icone'], $_POST['description'], $_POST['image'] ?? '', $_POST['ordre'] ?? 0]);
        $feedback = "<div class='alert alert-success'>Distinction ajoutée.</div>";
    }
    if (isset($_POST['edit_distinction'])) {
        $stmt = $conn->prepare("UPDATE FC_distinctions SET titre = ?, icone = ?, description = ?, image = ?, ordre = ?, actif = ? WHERE id = ?");
        $stmt->execute([$_POST['titre'], $_POST['icone'], $_POST['description'], $_POST['image'] ?? '', $_POST['ordre'] ?? 0, isset($_POST['actif']) ? 1 : 0, $_POST['distinction_id']]);
        $feedback = "<div class='alert alert-success'>Distinction mise à jour.</div>";
    }
    if (isset($_POST['delete_distinction'])) {
        $conn->prepare("DELETE FROM FC_distinctions WHERE id = ?")->execute([$_POST['distinction_id']]);
        $feedback = "<div class='alert alert-info'>Distinction supprimée.</div>";
    }

    // Logements
    if (isset($_POST['add_logement'])) {
        $imagePath = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../../site-frenchyconciergerie-main/uploads/logements/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (in_array($_FILES['photo']['type'], $allowedTypes) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = 'logement-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = 'uploads/logements/' . $filename;
                }
            }
        }
        $stmt = $conn->prepare("INSERT INTO FC_logements (titre, description, image, localisation, type_bien, ordre) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titre'], $_POST['description'] ?? '', $imagePath, $_POST['localisation'], $_POST['type_bien'], $_POST['ordre'] ?? 0]);
        $feedback = "<div class='alert alert-success'>Logement ajouté.</div>";
    }
    if (isset($_POST['delete_logement'])) {
        $conn->prepare("DELETE FROM FC_logements WHERE id = ?")->execute([$_POST['logement_id']]);
        $feedback = "<div class='alert alert-info'>Logement supprimé.</div>";
    }

    // Avis
    if (isset($_POST['add_avis'])) {
        $stmt = $conn->prepare("INSERT INTO FC_avis (nom, role, date_avis, note, commentaire) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['nom'], $_POST['role'], $_POST['date_avis'], $_POST['note'], $_POST['commentaire']]);
        $feedback = "<div class='alert alert-success'>Avis ajouté.</div>";
    }
    if (isset($_POST['delete_avis'])) {
        $conn->prepare("DELETE FROM FC_avis WHERE id = ?")->execute([$_POST['avis_id']]);
        $feedback = "<div class='alert alert-info'>Avis supprimé.</div>";
    }
    if (isset($_POST['validate_avis'])) {
        $conn->prepare("UPDATE FC_avis SET actif = 1 WHERE id = ?")->execute([$_POST['avis_id']]);
        $feedback = "<div class='alert alert-success'>Avis validé.</div>";
    }

    // Contacts
    if (isset($_POST['update_contact_statut'])) {
        $lu = $_POST['statut'] !== 'nouveau' ? 1 : 0;
        $conn->prepare("UPDATE FC_contacts SET statut = ?, lu = ? WHERE id = ?")->execute([$_POST['statut'], $lu, $_POST['contact_id']]);
        $feedback = "<div class='alert alert-info'>Statut mis à jour.</div>";
    }
    if (isset($_POST['archive_contact'])) {
        $conn->prepare("UPDATE FC_contacts SET archive = 1 WHERE id = ?")->execute([$_POST['contact_id']]);
        $feedback = "<div class='alert alert-info'>Message archivé.</div>";
    }
    if (isset($_POST['unarchive_contact'])) {
        $conn->prepare("UPDATE FC_contacts SET archive = 0 WHERE id = ?")->execute([$_POST['contact_id']]);
        $feedback = "<div class='alert alert-info'>Message restauré.</div>";
    }
    if (isset($_POST['delete_contact'])) {
        $conn->prepare("DELETE FROM FC_contacts WHERE id = ?")->execute([$_POST['contact_id']]);
        $feedback = "<div class='alert alert-info'>Message supprimé.</div>";
    }

    // Simulations
    if (isset($_POST['update_simulation_statut'])) {
        $contacted = in_array($_POST['statut'], ['contacte', 'converti', 'perdu']) ? 1 : 0;
        $conn->prepare("UPDATE FC_simulations SET statut = ?, contacted = ? WHERE id = ?")->execute([$_POST['statut'], $contacted, $_POST['simulation_id']]);
        $feedback = "<div class='alert alert-info'>Statut mis à jour.</div>";
    }
    if (isset($_POST['delete_simulation'])) {
        $conn->prepare("DELETE FROM FC_simulations WHERE id = ?")->execute([$_POST['simulation_id']]);
        $feedback = "<div class='alert alert-info'>Simulation supprimée.</div>";
    }

    // Blog
    if (isset($_POST['save_article'])) {
        $articleId = intval($_POST['article_id'] ?? 0);
        $titre = trim($_POST['article_titre'] ?? '');
        $slug = trim($_POST['article_slug'] ?? '');
        $contenu = $_POST['article_contenu'] ?? '';
        $extrait = trim($_POST['article_extrait'] ?? '');
        $categorieId = intval($_POST['article_categorie'] ?? 0) ?: null;
        $metaTitle = trim($_POST['article_meta_title'] ?? '');
        $metaDesc = trim($_POST['article_meta_description'] ?? '');
        $actifArticle = isset($_POST['article_actif']) ? 1 : 0;
        $datePublication = $_POST['article_date'] ?: date('Y-m-d H:i:s');
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $titre));
            $slug = trim($slug, '-');
        }
        if (!empty($titre)) {
            if ($articleId > 0) {
                $stmt = $conn->prepare("UPDATE FC_articles SET titre = ?, slug = ?, contenu = ?, extrait = ?, categorie_id = ?, meta_title = ?, meta_description = ?, actif = ?, date_publication = ? WHERE id = ?");
                $stmt->execute([$titre, $slug, $contenu, $extrait, $categorieId, $metaTitle, $metaDesc, $actifArticle, $datePublication, $articleId]);
                $feedback = "<div class='alert alert-success'>Article mis à jour.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO FC_articles (titre, slug, contenu, extrait, categorie_id, meta_title, meta_description, actif, date_publication) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titre, $slug, $contenu, $extrait, $categorieId, $metaTitle, $metaDesc, $actifArticle, $datePublication]);
                $feedback = "<div class='alert alert-success'>Article créé.</div>";
            }
        }
    }
    if (isset($_POST['delete_article'])) {
        $conn->prepare("DELETE FROM FC_articles WHERE id = ?")->execute([$_POST['article_id']]);
        $feedback = "<div class='alert alert-info'>Article supprimé.</div>";
    }
    if (isset($_POST['add_category'])) {
        $catNom = trim($_POST['cat_nom'] ?? '');
        if (!empty($catNom)) {
            $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $catNom));
            $conn->prepare("INSERT INTO FC_categories (nom, slug) VALUES (?, ?)")->execute([$catNom, $catSlug]);
            $feedback = "<div class='alert alert-success'>Catégorie ajoutée.</div>";
        }
    }

    // Simulateur config
    if (isset($_POST['update_simulateur_config'])) {
        foreach ($_POST['config'] as $key => $value) {
            $conn->prepare("UPDATE FC_simulateur_config SET config_value = ? WHERE config_key = ?")->execute([$value, $key]);
        }
        $feedback = "<div class='alert alert-success'>Configuration du simulateur mise à jour.</div>";
    }
    if (isset($_POST['add_ville'])) {
        $conn->prepare("INSERT INTO FC_simulateur_villes (ville, majoration_percent, ordre) VALUES (?, ?, ?)")->execute([$_POST['ville'], $_POST['majoration_percent'], $_POST['ordre'] ?? 0]);
        $feedback = "<div class='alert alert-success'>Ville ajoutée.</div>";
    }
    if (isset($_POST['edit_ville'])) {
        $conn->prepare("UPDATE FC_simulateur_villes SET ville = ?, majoration_percent = ?, ordre = ?, actif = ? WHERE id = ?")->execute([$_POST['ville'], $_POST['majoration_percent'], $_POST['ordre'] ?? 0, isset($_POST['actif']) ? 1 : 0, $_POST['ville_id']]);
        $feedback = "<div class='alert alert-success'>Ville mise à jour.</div>";
    }
    if (isset($_POST['delete_ville'])) {
        $conn->prepare("DELETE FROM FC_simulateur_villes WHERE id = ?")->execute([$_POST['ville_id']]);
        $feedback = "<div class='alert alert-info'>Ville supprimée.</div>";
    }

    // RGPD
    if (isset($_POST['save_rgpd'])) {
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS FC_rgpd_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (PDOException $e) {}
        foreach ($_POST['rgpd'] as $key => $value) {
            $conn->prepare("INSERT INTO FC_rgpd_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)")->execute([$key, $value]);
        }
        $feedback = "<div class='alert alert-success'>Configuration RGPD enregistrée.</div>";
    }

    // Codes d'invitation avis
    if (isset($_POST['generate_code'])) {
        $codeEmail = trim($_POST['code_email'] ?? '');
        $codeNom = trim($_POST['code_nom'] ?? '');
        $codeAdresse = trim($_POST['code_adresse'] ?? '');
        if (!empty($codeEmail) && !empty($codeNom)) {
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
                $code = strtoupper(substr(md5(random_bytes(16)), 0, 6));
                $conn->prepare("INSERT INTO FC_avis_codes (code, email, nom_proprietaire, adresse_bien) VALUES (?, ?, ?, ?)")->execute([$code, $codeEmail, $codeNom, $codeAdresse]);

                // Envoyer par email
                $nomSite = $settings['site_nom'] ?? 'Frenchy Conciergerie';
                $emailSite = $settings['email'] ?? 'contact@frenchyconciergerie.fr';
                $lienAvis = 'https://frenchyconciergerie.fr/avis.php';
                $sujetEmail = "Votre code pour donner votre avis - $nomSite";
                $corpsEmail = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#1E3A8A,#3B82F6);padding:30px;text-align:center;'><h1 style='color:white;margin:0;'>$nomSite</h1></div>
                    <div style='padding:30px;background:#f9f9f9;'>
                        <h2 style='color:#1E3A8A;'>Bonjour " . htmlspecialchars($codeNom) . ",</h2>
                        <p>Nous souhaitons recueillir votre avis sur nos services.</p>
                        <div style='background:white;border:2px dashed #1E3A8A;padding:20px;margin:25px 0;text-align:center;border-radius:10px;'>
                            <p style='margin:0 0 10px;color:#666;'>Votre code personnel :</p>
                            <p style='font-size:2rem;font-weight:bold;letter-spacing:8px;color:#1E3A8A;margin:0;'>$code</p>
                        </div>
                        <div style='text-align:center;margin:25px 0;'>
                            <a href='$lienAvis' style='display:inline-block;background:#1E3A8A;color:white;padding:15px 30px;text-decoration:none;border-radius:8px;font-weight:bold;'>Donner mon avis</a>
                        </div>
                    </div></body></html>";
                $headers = "MIME-Version:1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom:$nomSite <$emailSite>\r\nReply-To:$emailSite\r\n";
                $emailSent = @mail($codeEmail, $sujetEmail, $corpsEmail, $headers);

                // Envoyer aussi par SMS si le système SMS est dispo
                try {
                    $smsMsg = "Bonjour $codeNom, votre code pour donner votre avis sur $nomSite : $code - Rendez-vous sur $lienAvis";
                    // Vérifier si on a un numéro de téléphone dans les contacts/simulations
                    $stmtTel = $conn->prepare("SELECT telephone FROM FC_contacts WHERE email = ? AND telephone IS NOT NULL AND telephone != '' LIMIT 1");
                    $stmtTel->execute([$codeEmail]);
                    $telRow = $stmtTel->fetch(PDO::FETCH_ASSOC);
                    if ($telRow && !empty($telRow['telephone'])) {
                        $conn->prepare("INSERT INTO sms_outbox (numero, message, statut) VALUES (?, ?, 'a_envoyer')")->execute([$telRow['telephone'], $smsMsg]);
                    }
                } catch (PDOException $e) { /* SMS optionnel */ }

                $feedback = $emailSent
                    ? "<div class='alert alert-success'>Code <strong>$code</strong> généré et email envoyé à <strong>" . e($codeEmail) . "</strong>.</div>"
                    : "<div class='alert alert-warning'>Code <strong>$code</strong> généré. Email non envoyé — communiquez le code manuellement.</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : " . e($e->getMessage()) . "</div>";
            }
        }
    }
    if (isset($_POST['delete_code'])) {
        try {
            $conn->prepare("DELETE FROM FC_avis_codes WHERE id = ?")->execute([$_POST['code_id']]);
            $feedback = "<div class='alert alert-info'>Code supprimé.</div>";
        } catch (PDOException $e) {}
    }

    // Envoi email individuel
    if (isset($_POST['send_email'])) {
        $to = $_POST['email_to'];
        $subject = $_POST['email_subject'];
        $body = $_POST['email_body'];
        $fromName = $settings['site_nom'] ?? 'Frenchy Conciergerie';
        $fromEmail = $settings['email'] ?? 'contact@frenchyconciergerie.fr';
        $headers = "From: $fromName <$fromEmail>\r\nReply-To: $fromEmail\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $htmlBody = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
            <div style='background:linear-gradient(135deg,#1E3A8A,#3B82F6);padding:20px;text-align:center;border-radius:10px 10px 0 0;'><h1 style='color:white;margin:0;'>" . htmlspecialchars($fromName) . "</h1></div>
            <div style='background:#f9fafb;padding:30px;border:1px solid #e5e7eb;border-top:none;'>" . nl2br(htmlspecialchars($body)) . "</div>
            <div style='background:#1E3A8A;color:white;padding:15px;text-align:center;font-size:12px;border-radius:0 0 10px 10px;'>
                <p style='margin:0;'>" . htmlspecialchars($settings['adresse'] ?? '') . "</p>
                <p style='margin:5px 0 0;'>" . htmlspecialchars($settings['telephone'] ?? '') . " | " . htmlspecialchars($fromEmail) . "</p>
            </div></body></html>";
        if (mail($to, $subject, $htmlBody, $headers)) {
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS FC_emails_sent (id INT AUTO_INCREMENT PRIMARY KEY, email_to VARCHAR(255), subject VARCHAR(255), body TEXT, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $conn->prepare("INSERT INTO FC_emails_sent (email_to, subject, body) VALUES (?, ?, ?)")->execute([$to, $subject, $body]);
            } catch (PDOException $e) {}
            $feedback = "<div class='alert alert-success'>Email envoyé à $to.</div>";
        } else {
            $feedback = "<div class='alert alert-danger'>Erreur lors de l'envoi.</div>";
        }
    }

    // Newsletter
    if (isset($_POST['send_newsletter'])) {
        $subject = $_POST['newsletter_subject'];
        $body = $_POST['newsletter_body'];
        $recipients = [];
        if (isset($_POST['to_simulations'])) {
            try { $stmt = $conn->query("SELECT DISTINCT email FROM FC_simulations"); while ($r = $stmt->fetch()) $recipients[] = $r['email']; } catch (PDOException $e) {}
        }
        if (isset($_POST['to_contacts'])) {
            try { $stmt = $conn->query("SELECT DISTINCT email FROM FC_contacts"); while ($r = $stmt->fetch()) $recipients[] = $r['email']; } catch (PDOException $e) {}
        }
        $recipients = array_unique($recipients);
        $fromName = $settings['site_nom'] ?? 'Frenchy Conciergerie';
        $fromEmail = $settings['email'] ?? 'contact@frenchyconciergerie.fr';
        $sent = 0;
        foreach ($recipients as $to) {
            $headers = "From: $fromName <$fromEmail>\r\nReply-To: $fromEmail\r\nContent-Type: text/html; charset=UTF-8\r\n";
            $htmlBody = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg,#1E3A8A,#3B82F6);padding:20px;text-align:center;border-radius:10px 10px 0 0;'><h1 style='color:white;margin:0;'>" . htmlspecialchars($fromName) . "</h1></div>
                <div style='background:#f9fafb;padding:30px;border:1px solid #e5e7eb;border-top:none;'>" . nl2br(htmlspecialchars($body)) . "</div>
                <div style='background:#1E3A8A;color:white;padding:15px;text-align:center;font-size:12px;border-radius:0 0 10px 10px;'>
                    <p style='margin:0;'>" . htmlspecialchars($settings['adresse'] ?? '') . "</p>
                    <p style='margin:10px 0 0;font-size:10px;opacity:0.8;'>Pour vous désabonner, répondez STOP</p>
                </div></body></html>";
            if (mail($to, $subject, $htmlBody, $headers)) $sent++;
            usleep(100000);
        }
        $feedback = "<div class='alert alert-success'>Newsletter envoyée à $sent destinataire(s).</div>";
    }

    // Users FC
    if (isset($_POST['add_fc_user'])) {
        $username = trim($_POST['new_username'] ?? '');
        $email = trim($_POST['new_email'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $fcRole = $_POST['new_role'] ?? 'viewer';
        if (!empty($username) && !empty($email) && !empty($password)) {
            try {
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                $conn->prepare("INSERT INTO FC_users (username, email, password_hash, role, nom, prenom) VALUES (?, ?, ?, ?, ?, ?)")->execute([$username, $email, $passwordHash, $fcRole, $_POST['new_nom'] ?? '', $_POST['new_prenom'] ?? '']);
                $feedback = "<div class='alert alert-success'>Utilisateur créé.</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'>Erreur : utilisateur ou email déjà existant.</div>";
            }
        }
    }
    if (isset($_POST['delete_fc_user'])) {
        $conn->prepare("DELETE FROM FC_users WHERE id = ?")->execute([$_POST['user_id']]);
        $feedback = "<div class='alert alert-info'>Utilisateur supprimé.</div>";
    }
    if (isset($_POST['toggle_fc_user'])) {
        $conn->prepare("UPDATE FC_users SET actif = NOT actif WHERE id = ?")->execute([$_POST['user_id']]);
        $feedback = "<div class='alert alert-info'>Statut modifié.</div>";
    }
}

// ── Load data ──
$settings = getAllSettings($conn);
$logements = getLogements($conn);
$avis = getAvis($conn);

// Sections
try {
    $defaultSections = [
        ['hero', 'Bannière d\'accueil (Hero)', 1, 1], ['services', 'Nos Services', 1, 2],
        ['tarifs', 'Tarifs', 1, 3], ['simulateur', 'Simulateur de revenus', 1, 4],
        ['galerie', 'Galerie / Logements', 1, 5], ['distinctions', 'Distinctions & Certifications', 1, 6],
        ['avis', 'Avis / Témoignages', 1, 7], ['blog', 'Blog / Actualités', 1, 8],
        ['legal', 'Informations légales', 1, 9], ['contact', 'Formulaire de contact', 1, 10],
    ];
    foreach ($defaultSections as $sec) {
        $conn->prepare("INSERT IGNORE INTO FC_sections (section_key, section_label, actif, ordre) VALUES (?, ?, ?, ?)")->execute($sec);
    }
    $sections = $conn->query("SELECT * FROM FC_sections ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sections = []; }

$services = $conn->query("SELECT * FROM FC_services ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC);
$tarifs = $conn->query("SELECT * FROM FC_tarifs ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC);
$distinctions = $conn->query("SELECT * FROM FC_distinctions ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Simulations
try {
    $simulations = $conn->query("SELECT * FROM FC_simulations ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $simulations = []; }
$newSimulations = count(array_filter($simulations, fn($s) => !($s['contacted'] ?? 0)));

// Contacts
$showArchived = isset($_GET['archives']) && $_GET['archives'] == '1';
try {
    $contacts = $conn->query("SELECT * FROM FC_contacts WHERE archive = " . ($showArchived ? 1 : 0) . " ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $unreadContacts = $conn->query("SELECT COUNT(*) FROM FC_contacts WHERE lu = 0 AND archive = 0")->fetchColumn();
} catch (PDOException $e) { $contacts = []; $unreadContacts = 0; }

// Sub-navigation tabs
$fcPages = [
    'dashboard' => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
    'sections' => ['icon' => 'fa-sliders-h', 'label' => 'Sections'],
    'services' => ['icon' => 'fa-concierge-bell', 'label' => 'Services'],
    'tarifs' => ['icon' => 'fa-tags', 'label' => 'Tarifs'],
    'logements' => ['icon' => 'fa-home', 'label' => 'Logements'],
    'avis' => ['icon' => 'fa-star', 'label' => 'Avis'],
    'distinctions' => ['icon' => 'fa-trophy', 'label' => 'Distinctions'],
    'blog' => ['icon' => 'fa-newspaper', 'label' => 'Blog'],
    'simulations' => ['icon' => 'fa-chart-line', 'label' => 'Simulations'],
    'contacts' => ['icon' => 'fa-envelope', 'label' => 'Messages'],
    'newsletter' => ['icon' => 'fa-paper-plane', 'label' => 'Newsletter'],
    'analytics' => ['icon' => 'fa-chart-bar', 'label' => 'Analytics'],
    'simulateur_config' => ['icon' => 'fa-calculator', 'label' => 'Simulateur'],
    'rgpd' => ['icon' => 'fa-shield-alt', 'label' => 'RGPD'],
    'users' => ['icon' => 'fa-users-cog', 'label' => 'Utilisateurs'],
    'parametres' => ['icon' => 'fa-cog', 'label' => 'Paramètres'],
];
?>

<div class="fc-main">
    <div class="container-fluid mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-globe"></i> Admin frenchyconciergerie.fr</h4>
            <a href="https://frenchyconciergerie.fr" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-external-link-alt"></i> Voir le site
            </a>
        </div>

        <!-- Sub-navigation -->
        <div class="mb-3" style="overflow-x: auto; white-space: nowrap;">
            <?php foreach ($fcPages as $key => $pg): ?>
                <a href="?fc_page=<?= $key ?>"
                   class="btn btn-sm <?= $page === $key ? 'btn-primary' : 'btn-outline-secondary' ?> mb-1">
                    <i class="fas <?= $pg['icon'] ?>"></i> <?= $pg['label'] ?>
                    <?php if ($key === 'simulations' && $newSimulations > 0): ?>
                        <span class="badge bg-success"><?= $newSimulations ?></span>
                    <?php elseif ($key === 'contacts' && $unreadContacts > 0): ?>
                        <span class="badge bg-danger"><?= $unreadContacts ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?= $feedback ?>

        <?php if ($page === 'dashboard'): ?>
        <!-- ═══════ DASHBOARD ═══════ -->
        <?php
        $totalSimulations = count($simulations);
        $simulationsContactees = count(array_filter($simulations, fn($s) => $s['contacted'] ?? 0));
        $tauxConversion = $totalSimulations > 0 ? round(($simulationsContactees / $totalSimulations) * 100) : 0;
        $revenuMoyenEstime = 0;
        try { $revenuMoyenEstime = $conn->query("SELECT AVG(revenu_mensuel_estime) FROM FC_simulations WHERE revenu_mensuel_estime > 0")->fetchColumn(); } catch (PDOException $e) {}
        ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-success"><?= $totalSimulations ?></h2><small>Simulations</small></div></div>
            <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-primary"><?= $tauxConversion ?>%</h2><small>Taux de suivi</small></div></div>
            <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-info"><?= number_format($revenuMoyenEstime ?? 0, 0) ?>&euro;</h2><small>Revenu moyen estimé</small></div></div>
            <div class="col-md-3"><div class="card text-center p-3"><h2 class="text-danger"><?= $unreadContacts ?></h2><small>Messages non lus</small></div></div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Dernières simulations</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Date</th><th>Email</th><th>Estimation</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($simulations, 0, 5) as $sim): ?>
                                <tr class="<?= !($sim['contacted'] ?? 0) ? 'table-warning' : '' ?>">
                                    <td><small><?= date('d/m H:i', strtotime($sim['created_at'])) ?></small></td>
                                    <td><small><?= e($sim['email']) ?></small></td>
                                    <td><strong><?= number_format($sim['revenu_mensuel_estime'] ?? 0, 0) ?>&euro;</strong>/mois</td>
                                    <td><?= ($sim['contacted'] ?? 0) ? '<span class="badge bg-success">Contacté</span>' : '<span class="badge bg-warning text-dark">En attente</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Derniers messages</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Date</th><th>Nom</th><th>Sujet</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($contacts, 0, 5) as $contact): ?>
                                <tr class="<?= !($contact['lu'] ?? 0) ? 'table-warning' : '' ?>">
                                    <td><small><?= date('d/m H:i', strtotime($contact['created_at'])) ?></small></td>
                                    <td><small><?= e($contact['nom']) ?></small></td>
                                    <td><small><?= e($contact['sujet'] ?: 'Sans sujet') ?></small></td>
                                    <td>
                                        <?php $s = $contact['statut'] ?? 'nouveau'; ?>
                                        <span class="badge bg-<?= $s === 'nouveau' ? 'warning text-dark' : ($s === 'en_cours' ? 'primary' : 'success') ?>"><?= $s ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'sections'): ?>
        <!-- ═══════ SECTIONS ═══════ -->
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-sliders-h"></i> Sections du site</h5></div>
            <div class="card-body">
                <form method="POST">
                    <?= fcCsrfField() ?>
                    <?php foreach ($sections as $section): ?>
                    <div class="d-flex align-items-center p-2 mb-2 rounded" style="background: <?= $section['actif'] ? '#d1fae5' : '#fee2e2' ?>">
                        <strong class="me-3"><?= e($section['section_label']) ?></strong>
                        <span class="text-muted me-auto"><small><?= e($section['section_key']) ?></small></span>
                        <label class="me-2"><small>Ordre:</small></label>
                        <input type="number" name="sections[<?= e($section['section_key']) ?>][ordre]" value="<?= $section['ordre'] ?>" style="width:60px" class="form-control form-control-sm me-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="sections[<?= e($section['section_key']) ?>][actif]" <?= $section['actif'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" name="update_sections" class="btn btn-primary mt-3"><i class="fas fa-save"></i> Enregistrer</button>
                </form>
            </div>
        </div>

        <?php elseif ($page === 'services'): ?>
        <!-- ═══════ SERVICES ═══════ -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter un service</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Icône (emoji)</label><input type="text" name="icone" class="form-control" value="🏠"></div>
                            <div class="mb-2"><label class="form-label">Info carte</label><input type="text" name="carte_info" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                            <div class="mb-2"><label class="form-label">Prestations (une/ligne)</label><textarea name="liste_items" class="form-control" rows="4"></textarea></div>
                            <div class="mb-2"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                            <button type="submit" name="add_service" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Services (<?= count($services) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th></th><th>Titre</th><th>Actif</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?= $service['ordre'] ?></td>
                                    <td style="font-size:1.5rem"><?= $service['icone'] ?></td>
                                    <td><?= e($service['titre']) ?></td>
                                    <td><?= $service['actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="openEditModal('service', <?= htmlspecialchars(json_encode($service), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                                            <?= fcCsrfField() ?>
                                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                            <button type="submit" name="delete_service" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'tarifs'): ?>
        <!-- ═══════ TARIFS ═══════ -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter un tarif</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" required></div>
                            <div class="row mb-2">
                                <div class="col"><label class="form-label">Montant</label><input type="number" name="montant" step="0.01" class="form-control" required></div>
                                <div class="col"><label class="form-label">Type</label><select name="type_tarif" class="form-select"><option value="pourcentage">%</option><option value="euro">&euro;</option></select></div>
                            </div>
                            <div class="mb-2"><label class="form-label">Description</label><input type="text" name="description" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Détails</label><textarea name="details" class="form-control" rows="2"></textarea></div>
                            <div class="mb-2"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                            <button type="submit" name="add_tarif" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Tarifs (<?= count($tarifs) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>Titre</th><th>Montant</th><th>Actif</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($tarifs as $tarif): ?>
                                <tr>
                                    <td><?= $tarif['ordre'] ?></td>
                                    <td><?= e($tarif['titre']) ?></td>
                                    <td><strong><?= number_format($tarif['montant'] ?? $tarif['pourcentage'] ?? 0, 2) ?><?= ($tarif['type_tarif'] ?? 'pourcentage') === 'euro' ? ' &euro;' : ' %' ?></strong></td>
                                    <td><?= $tarif['actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="openEditModal('tarif', <?= htmlspecialchars(json_encode($tarif), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                                            <?= fcCsrfField() ?>
                                            <input type="hidden" name="tarif_id" value="<?= $tarif['id'] ?>">
                                            <button type="submit" name="delete_tarif" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'logements'): ?>
        <!-- ═══════ LOGEMENTS ═══════ -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter un logement</h6></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Localisation</label><input type="text" name="localisation" class="form-control" value="Compiègne"></div>
                            <div class="mb-2"><label class="form-label">Type</label>
                                <select name="type_bien" class="form-select"><option>Appartement</option><option>Studio</option><option>Maison</option><option>Loft</option><option>Duplex</option></select>
                            </div>
                            <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                            <div class="mb-2"><label class="form-label">Photo</label><input type="file" name="photo" accept="image/*" class="form-control"></div>
                            <div class="mb-2"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                            <button type="submit" name="add_logement" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Logements (<?= count($logements) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Titre</th><th>Type</th><th>Lieu</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($logements as $logement): ?>
                                <tr>
                                    <td><?= e($logement['titre']) ?></td>
                                    <td><?= e($logement['type_bien']) ?></td>
                                    <td><?= e($logement['localisation']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                                            <?= fcCsrfField() ?>
                                            <input type="hidden" name="logement_id" value="<?= $logement['id'] ?>">
                                            <button type="submit" name="delete_logement" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'avis'): ?>
        <!-- ═══════ AVIS ═══════ -->
        <?php
        $avisEnAttente = [];
        try { $avisEnAttente = $conn->query("SELECT * FROM FC_avis WHERE actif = 0 ORDER BY date_avis DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}
        ?>
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter un avis</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Nom</label><input type="text" name="nom" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Rôle</label><input type="text" name="role" class="form-control" value="Propriétaire"></div>
                            <div class="mb-2"><label class="form-label">Date</label><input type="date" name="date_avis" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="mb-2"><label class="form-label">Note</label><select name="note" class="form-select"><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select></div>
                            <div class="mb-2"><label class="form-label">Commentaire</label><textarea name="commentaire" class="form-control" rows="3" required></textarea></div>
                            <button type="submit" name="add_avis" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <?php if (!empty($avisEnAttente)): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-warning text-dark"><h6 class="mb-0">En attente de validation (<?= count($avisEnAttente) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nom</th><th>Note</th><th>Commentaire</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($avisEnAttente as $avi): ?>
                                <tr>
                                    <td><?= e($avi['nom']) ?></td>
                                    <td><?= renderStars($avi['note']) ?></td>
                                    <td><small><?= e(mb_substr($avi['commentaire'], 0, 80)) ?>...</small></td>
                                    <td>
                                        <form method="POST" style="display:inline"><?= fcCsrfField() ?><input type="hidden" name="avis_id" value="<?= $avi['id'] ?>"><button type="submit" name="validate_avis" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="avis_id" value="<?= $avi['id'] ?>"><button type="submit" name="delete_avis" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Avis publiés (<?= count($avis) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Nom</th><th>Note</th><th>Commentaire</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($avis as $avi): ?>
                                <tr>
                                    <td><?= e($avi['nom']) ?></td>
                                    <td><?= renderStars($avi['note']) ?></td>
                                    <td><small><?= e(mb_substr($avi['commentaire'], 0, 80)) ?>...</small></td>
                                    <td><small><?= e($avi['date_avis']) ?></small></td>
                                    <td><form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="avis_id" value="<?= $avi['id'] ?>"><button type="submit" name="delete_avis" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ CODES INVITATION AVIS ═══════ -->
        <?php
        $avisCodes = [];
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS FC_avis_codes (
                id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) UNIQUE NOT NULL, email VARCHAR(255) NOT NULL,
                nom_proprietaire VARCHAR(255) NOT NULL, adresse_bien VARCHAR(255),
                used TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, used_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $avisCodes = $conn->query("SELECT * FROM FC_avis_codes ORDER BY created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
        ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-ticket-alt"></i> Codes d'invitation pour avis (SMS + Email)</h6></div>
            <div class="card-body">
                <form method="POST" class="row g-2 mb-3 align-items-end">
                    <?= fcCsrfField() ?>
                    <div class="col-md-3"><label class="form-label">Email *</label><input type="email" name="code_email" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Nom proprietaire *</label><input type="text" name="code_nom" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Adresse du bien</label><input type="text" name="code_adresse" class="form-control"></div>
                    <div class="col-md-3"><button type="submit" name="generate_code" class="btn btn-info w-100"><i class="fas fa-paper-plane"></i> Generer &amp; Envoyer</button></div>
                </form>
                <div class="alert alert-info py-2"><small><i class="fas fa-info-circle"></i> Le code est envoye par email et par SMS (si un numero est associe dans les contacts).</small></div>
                <?php if (!empty($avisCodes)): ?>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Code</th><th>Email</th><th>Nom</th><th>Adresse</th><th>Utilise</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($avisCodes as $ac): ?>
                        <tr class="<?= $ac['used'] ? 'table-success' : '' ?>">
                            <td><code class="fs-6"><?= e($ac['code']) ?></code></td>
                            <td><small><?= e($ac['email']) ?></small></td>
                            <td><small><?= e($ac['nom_proprietaire']) ?></small></td>
                            <td><small><?= e($ac['adresse_bien'] ?? '-') ?></small></td>
                            <td><?= $ac['used'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-warning text-dark">Non</span>' ?></td>
                            <td><small><?= date('d/m/Y H:i', strtotime($ac['created_at'])) ?></small></td>
                            <td><form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="code_id" value="<?= $ac['id'] ?>"><button type="submit" name="delete_code" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($page === 'distinctions'): ?>
        <!-- ═══════ DISTINCTIONS ═══════ -->
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter une distinction</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Icône</label><input type="text" name="icone" class="form-control" value="🏆"></div>
                            <div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                            <div class="mb-2"><label class="form-label">Image</label><input type="text" name="image" class="form-control" placeholder="booking-award.png"></div>
                            <div class="mb-2"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                            <button type="submit" name="add_distinction" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Distinctions (<?= count($distinctions) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th></th><th>Titre</th><th>Actif</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($distinctions as $d): ?>
                                <tr>
                                    <td><?= $d['ordre'] ?></td>
                                    <td style="font-size:1.5rem"><?= $d['icone'] ?></td>
                                    <td><?= e($d['titre']) ?></td>
                                    <td><?= $d['actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="openEditModal('distinction', <?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="distinction_id" value="<?= $d['id'] ?>"><button type="submit" name="delete_distinction" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'blog'): ?>
        <!-- ═══════ BLOG ═══════ -->
        <?php
        try {
            $articles = $conn->query("SELECT a.*, c.nom as categorie_nom FROM FC_articles a LEFT JOIN FC_categories c ON a.categorie_id = c.id ORDER BY a.date_publication DESC")->fetchAll(PDO::FETCH_ASSOC);
            $categories = $conn->query("SELECT * FROM FC_categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $articles = []; $categories = []; }
        $editArticle = null;
        if (isset($_GET['edit'])) {
            $stmt = $conn->prepare("SELECT * FROM FC_articles WHERE id = ?");
            $stmt->execute([intval($_GET['edit'])]);
            $editArticle = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        ?>
        <div class="row g-3">
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-<?= $editArticle ? 'warning text-dark' : 'success text-white' ?>">
                        <h6 class="mb-0"><?= $editArticle ? 'Modifier l\'article' : 'Nouvel article' ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <input type="hidden" name="article_id" value="<?= $editArticle['id'] ?? 0 ?>">
                            <div class="mb-2"><label class="form-label">Titre *</label><input type="text" name="article_titre" class="form-control" value="<?= e($editArticle['titre'] ?? '') ?>" required></div>
                            <div class="mb-2"><label class="form-label">Slug</label><input type="text" name="article_slug" class="form-control" value="<?= e($editArticle['slug'] ?? '') ?>" placeholder="auto-généré"></div>
                            <div class="mb-2"><label class="form-label">Extrait</label><textarea name="article_extrait" class="form-control" rows="2"><?= e($editArticle['extrait'] ?? '') ?></textarea></div>
                            <div class="mb-2"><label class="form-label">Contenu</label><textarea name="article_contenu" class="form-control" rows="10" style="font-family:monospace"><?= e($editArticle['contenu'] ?? '') ?></textarea></div>
                            <div class="row mb-2">
                                <div class="col"><label class="form-label">Catégorie</label><select name="article_categorie" class="form-select"><option value="">--</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= ($editArticle['categorie_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['nom']) ?></option><?php endforeach; ?></select></div>
                                <div class="col"><label class="form-label">Date</label><input type="datetime-local" name="article_date" class="form-control" value="<?= $editArticle ? date('Y-m-d\TH:i', strtotime($editArticle['date_publication'])) : date('Y-m-d\TH:i') ?>"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col"><label class="form-label">Meta Title</label><input type="text" name="article_meta_title" class="form-control" value="<?= e($editArticle['meta_title'] ?? '') ?>"></div>
                                <div class="col"><label class="form-label">Meta Desc</label><input type="text" name="article_meta_description" class="form-control" value="<?= e($editArticle['meta_description'] ?? '') ?>"></div>
                            </div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="article_actif" id="article_actif" <?= ($editArticle['actif'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="article_actif">Publié</label></div>
                            <button type="submit" name="save_article" class="btn btn-<?= $editArticle ? 'warning' : 'success' ?>"><?= $editArticle ? 'Mettre à jour' : 'Créer' ?></button>
                            <?php if ($editArticle): ?><a href="?fc_page=blog" class="btn btn-secondary">Annuler</a><?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-info text-white"><h6 class="mb-0">Catégories</h6></div>
                    <div class="card-body">
                        <form method="POST" class="d-flex gap-2 mb-2">
                            <?= fcCsrfField() ?>
                            <input type="text" name="cat_nom" class="form-control form-control-sm" placeholder="Nouvelle catégorie">
                            <button type="submit" name="add_category" class="btn btn-primary btn-sm">+</button>
                        </form>
                        <?php foreach ($categories as $cat): ?><span class="badge bg-secondary me-1"><?= e($cat['nom']) ?></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Articles (<?= count($articles) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Titre</th><th>Statut</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($articles as $a): ?>
                                <tr>
                                    <td><small><?= e($a['titre']) ?></small></td>
                                    <td><span class="badge bg-<?= $a['actif'] ? 'success' : 'warning text-dark' ?>"><?= $a['actif'] ? 'Publié' : 'Brouillon' ?></span></td>
                                    <td>
                                        <a href="?fc_page=blog&edit=<?= $a['id'] ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="article_id" value="<?= $a['id'] ?>"><button type="submit" name="delete_article" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'simulations'): ?>
        <!-- ═══════ SIMULATIONS ═══════ -->
        <?php
        $simStatuts = ['a_contacter' => ['En attente', 'warning'], 'contacte' => ['Contacté', 'primary'], 'converti' => ['Converti', 'success'], 'perdu' => ['Perdu', 'danger']];
        $filteredSims = $simulations;
        if (isset($_GET['statut']) && isset($simStatuts[$_GET['statut']])) {
            $filteredSims = array_filter($simulations, fn($s) => ($s['statut'] ?? 'a_contacter') === $_GET['statut']);
        }
        ?>
        <div class="mb-3">
            <a href="?fc_page=simulations" class="btn btn-sm <?= !isset($_GET['statut']) ? 'btn-primary' : 'btn-outline-secondary' ?>">Tous (<?= count($simulations) ?>)</a>
            <?php foreach ($simStatuts as $key => $val): ?>
                <?php $nb = count(array_filter($simulations, fn($s) => ($s['statut'] ?? 'a_contacter') === $key)); ?>
                <a href="?fc_page=simulations&statut=<?= $key ?>" class="btn btn-sm <?= ($_GET['statut'] ?? '') === $key ? 'btn-' . $val[1] : 'btn-outline-' . $val[1] ?>"><?= $val[0] ?> (<?= $nb ?>)</a>
            <?php endforeach; ?>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Email</th><th>Bien</th><th>Estimation</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($filteredSims as $sim): ?>
                        <?php $statut = $sim['statut'] ?? 'a_contacter'; ?>
                        <tr class="<?= $statut === 'a_contacter' ? 'table-warning' : '' ?>">
                            <td><small><?= date('d/m/Y H:i', strtotime($sim['created_at'])) ?></small></td>
                            <td><a href="mailto:<?= e($sim['email']) ?>"><?= e($sim['email']) ?></a></td>
                            <td><small><?= e($sim['surface'] ?? '-') ?> m² | <?= e($sim['capacite'] ?? '-') ?> pers. | <?= e($sim['ville'] ?? '-') ?></small></td>
                            <td><strong><?= number_format($sim['revenu_mensuel_estime'] ?? 0, 0) ?>&euro;</strong>/mois</td>
                            <td>
                                <form method="POST" style="display:inline"><?= fcCsrfField() ?><input type="hidden" name="simulation_id" value="<?= $sim['id'] ?>"><input type="hidden" name="update_simulation_statut" value="1">
                                    <select name="statut" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto;display:inline">
                                        <?php foreach ($simStatuts as $k => $v): ?><option value="<?= $k ?>" <?= $statut === $k ? 'selected' : '' ?>><?= $v[0] ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openEmailModal('<?= e($sim['email']) ?>', 'Votre simulation Frenchy Conciergerie')"><i class="fas fa-envelope"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="simulation_id" value="<?= $sim['id'] ?>"><button type="submit" name="delete_simulation" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page === 'contacts'): ?>
        <!-- ═══════ CONTACTS ═══════ -->
        <?php
        $contactStatuts = ['nouveau' => ['Nouveau', 'warning'], 'en_cours' => ['En cours', 'primary'], 'traite' => ['Traité', 'success']];
        $filteredContacts = $contacts;
        if (isset($_GET['statut']) && isset($contactStatuts[$_GET['statut']])) {
            $filteredContacts = array_filter($contacts, fn($c) => ($c['statut'] ?? 'nouveau') === $_GET['statut']);
        }
        ?>
        <div class="mb-3">
            <a href="?fc_page=contacts" class="btn btn-sm <?= !$showArchived && !isset($_GET['statut']) ? 'btn-primary' : 'btn-outline-secondary' ?>">Actifs</a>
            <?php foreach ($contactStatuts as $key => $val): ?><a href="?fc_page=contacts&statut=<?= $key ?>" class="btn btn-sm <?= ($_GET['statut'] ?? '') === $key ? 'btn-' . $val[1] : 'btn-outline-' . $val[1] ?>"><?= $val[0] ?></a><?php endforeach; ?>
            <a href="?fc_page=contacts&archives=1" class="btn btn-sm <?= $showArchived ? 'btn-secondary' : 'btn-outline-secondary' ?> ms-2">Archives</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Nom</th><th>Contact</th><th>Message</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($filteredContacts as $contact): ?>
                        <?php $statut = $contact['statut'] ?? 'nouveau'; ?>
                        <tr class="<?= $statut === 'nouveau' ? 'table-warning' : '' ?>">
                            <td><small><?= date('d/m H:i', strtotime($contact['created_at'])) ?></small></td>
                            <td><strong><?= e($contact['nom']) ?></strong></td>
                            <td><small><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?= $contact['telephone'] ? '<br>' . e($contact['telephone']) : '' ?></small></td>
                            <td><small><strong><?= e($contact['sujet'] ?: 'Sans sujet') ?></strong><br><?= e(mb_substr($contact['message'], 0, 100)) ?></small></td>
                            <td>
                                <?php if (!$showArchived): ?>
                                <form method="POST" style="display:inline"><?= fcCsrfField() ?><input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"><input type="hidden" name="update_contact_statut" value="1">
                                    <select name="statut" onchange="this.form.submit()" class="form-select form-select-sm" style="width:auto;display:inline">
                                        <?php foreach ($contactStatuts as $k => $v): ?><option value="<?= $k ?>" <?= $statut === $k ? 'selected' : '' ?>><?= $v[0] ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                                <?php else: ?><span class="badge bg-secondary">Archivé</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($showArchived): ?>
                                    <form method="POST" style="display:inline"><?= fcCsrfField() ?><input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"><button type="submit" name="unarchive_contact" class="btn btn-outline-primary btn-sm"><i class="fas fa-undo"></i></button></form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline"><?= fcCsrfField() ?><input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"><button type="submit" name="archive_contact" class="btn btn-outline-secondary btn-sm"><i class="fas fa-archive"></i></button></form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openEmailModal('<?= e($contact['email']) ?>', 'Re: <?= e(addslashes($contact['sujet'] ?: '')) ?>')"><i class="fas fa-reply"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"><button type="submit" name="delete_contact" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($page === 'simulateur_config'): ?>
        <!-- ═══════ SIMULATEUR CONFIG ═══════ -->
        <?php
        try { $simulateurConfig = $conn->query("SELECT * FROM FC_simulateur_config ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { $simulateurConfig = []; }
        try { $simulateurVilles = $conn->query("SELECT * FROM FC_simulateur_villes ORDER BY ordre ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { $simulateurVilles = []; }
        ?>
        <?php if (!empty($simulateurConfig)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-info text-white"><h6 class="mb-0">Paramètres de calcul</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= fcCsrfField() ?>
                    <div class="row g-3">
                        <?php foreach ($simulateurConfig as $config): ?>
                        <div class="col-md-4">
                            <label class="form-label"><small><?= e($config['config_label']) ?></small></label>
                            <div class="input-group">
                                <input type="number" name="config[<?= e($config['config_key']) ?>]" value="<?= e($config['config_value']) ?>" step="0.01" class="form-control">
                                <span class="input-group-text"><?= $config['config_type'] === 'percent' ? '%' : '&euro;' ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="update_simulateur_config" class="btn btn-primary mt-3">Enregistrer</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white"><h6 class="mb-0">Ajouter une ville</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= fcCsrfField() ?>
                            <div class="mb-2"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">Majoration (%)</label><input type="number" name="majoration_percent" step="0.01" class="form-control" value="0"></div>
                            <div class="mb-2"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="0"></div>
                            <button type="submit" name="add_ville" class="btn btn-success w-100">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0">Villes (<?= count($simulateurVilles) ?>)</h6></div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>Ville</th><th>Majoration</th><th>Actif</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($simulateurVilles as $ville): ?>
                                <tr>
                                    <td><?= $ville['ordre'] ?></td>
                                    <td><?= e($ville['ville']) ?></td>
                                    <td><strong><?= number_format($ville['majoration_percent'], 2) ?>%</strong></td>
                                    <td><?= $ville['actif'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="openEditModal('ville', <?= htmlspecialchars(json_encode($ville), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="ville_id" value="<?= $ville['id'] ?>"><button type="submit" name="delete_ville" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($page === 'parametres'): ?>
        <!-- ═══════ PARAMÈTRES ═══════ -->
        <form method="POST">
            <?= fcCsrfField() ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Informations de l'entreprise</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Nom du site</label><input type="text" name="settings[site_nom]" class="form-control" value="<?= e($settings['site_nom'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Slogan</label><input type="text" name="settings[site_slogan]" class="form-control" value="<?= e($settings['site_slogan'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="settings[email]" class="form-control" value="<?= e($settings['email'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Téléphone</label><input type="text" name="settings[telephone]" class="form-control" value="<?= e($settings['telephone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Adresse</label><input type="text" name="settings[adresse]" class="form-control" value="<?= e($settings['adresse'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Horaires</label><input type="text" name="settings[horaires]" class="form-control" value="<?= e($settings['horaires'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-secondary text-white"><h6 class="mb-0">Informations légales</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Forme juridique</label><input type="text" name="settings[forme_juridique]" class="form-control" value="<?= e($settings['forme_juridique'] ?? '') ?>" placeholder="Société par Actions Simplifiée (SAS)"></div>
                        <div class="col-md-4"><label class="form-label">Capital social</label><input type="text" name="settings[capital]" class="form-control" value="<?= e($settings['capital'] ?? '') ?>" placeholder="1000 euros"></div>
                        <div class="col-md-4"><label class="form-label">SIRET</label><input type="text" name="settings[siret]" class="form-control" value="<?= e($settings['siret'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">RCS</label><input type="text" name="settings[rcs]" class="form-control" value="<?= e($settings['rcs'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">N° TVA intracommunautaire</label><input type="text" name="settings[tva_intra]" class="form-control" value="<?= e($settings['tva_intra'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Président(e)</label><input type="text" name="settings[presidente]" class="form-control" value="<?= e($settings['presidente'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Email légal</label><input type="email" name="settings[email_legal]" class="form-control" value="<?= e($settings['email_legal'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Téléphone légal</label><input type="text" name="settings[telephone_legal]" class="form-control" value="<?= e($settings['telephone_legal'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Carte Transaction Immobilière n°</label><input type="text" name="settings[carte_transaction]" class="form-control" value="<?= e($settings['carte_transaction'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Carte Gestion Immobilière n°</label><input type="text" name="settings[carte_gestion]" class="form-control" value="<?= e($settings['carte_gestion'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-font"></i> Textes du site</h6></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Modifiez les titres et textes affichés sur chaque section du site. Laissez vide pour garder le texte par défaut.</p>

                    <h6 class="text-primary mt-3 mb-2">Hero (Bannière)</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Description sous le slogan</label><input type="text" name="settings[site_description]" class="form-control" value="<?= e($settings['site_description'] ?? '') ?>" placeholder="Nous gérons votre bien de A à Z pour optimiser vos revenus"></div>
                        <div class="col-md-6"><label class="form-label">Bouton CTA</label><input type="text" name="settings[hero_cta]" class="form-control" value="<?= e($settings['hero_cta'] ?? '') ?>" placeholder="Contactez-nous"></div>
                    </div>

                    <h6 class="text-primary mt-4 mb-2">Titres des sections</h6>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Services</label><input type="text" name="settings[titre_services]" class="form-control" value="<?= e($settings['titre_services'] ?? '') ?>" placeholder="Nos Services"></div>
                        <div class="col-md-4"><label class="form-label">Tarifs</label><input type="text" name="settings[titre_tarifs]" class="form-control" value="<?= e($settings['titre_tarifs'] ?? '') ?>" placeholder="Tarifs Transparents"></div>
                        <div class="col-md-4"><label class="form-label">Simulateur</label><input type="text" name="settings[titre_simulateur]" class="form-control" value="<?= e($settings['titre_simulateur'] ?? '') ?>" placeholder="Estimez vos Revenus Locatifs"></div>
                        <div class="col-md-4"><label class="form-label">Logements</label><input type="text" name="settings[titre_logements]" class="form-control" value="<?= e($settings['titre_logements'] ?? '') ?>" placeholder="Nos Logements Gérés"></div>
                        <div class="col-md-4"><label class="form-label">Distinctions</label><input type="text" name="settings[titre_distinctions]" class="form-control" value="<?= e($settings['titre_distinctions'] ?? '') ?>" placeholder="Nos Distinctions & Certifications"></div>
                        <div class="col-md-4"><label class="form-label">Avis</label><input type="text" name="settings[titre_avis]" class="form-control" value="<?= e($settings['titre_avis'] ?? '') ?>" placeholder="Témoignages de Propriétaires"></div>
                        <div class="col-md-4"><label class="form-label">Blog</label><input type="text" name="settings[titre_blog]" class="form-control" value="<?= e($settings['titre_blog'] ?? '') ?>" placeholder="Nos Actualités"></div>
                        <div class="col-md-4"><label class="form-label">Contact</label><input type="text" name="settings[titre_contact]" class="form-control" value="<?= e($settings['titre_contact'] ?? '') ?>" placeholder="Contactez-nous"></div>
                    </div>

                    <h6 class="text-primary mt-4 mb-2">Sous-titres et descriptions</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Sous-titre simulateur</label><input type="text" name="settings[sous_titre_simulateur]" class="form-control" value="<?= e($settings['sous_titre_simulateur'] ?? '') ?>" placeholder="Découvrez le potentiel de votre bien..."></div>
                        <div class="col-md-6"><label class="form-label">Sous-titre blog</label><input type="text" name="settings[sous_titre_blog]" class="form-control" value="<?= e($settings['sous_titre_blog'] ?? '') ?>" placeholder="Conseils, astuces et actualités..."></div>
                        <div class="col-md-6"><label class="form-label">Sous-titre contact</label><input type="text" name="settings[sous_titre_contact]" class="form-control" value="<?= e($settings['sous_titre_contact'] ?? '') ?>" placeholder="Vous avez un projet de location saisonnière ?"></div>
                        <div class="col-md-6"><label class="form-label">Description footer</label><input type="text" name="settings[footer_description]" class="form-control" value="<?= e($settings['footer_description'] ?? '') ?>" placeholder="Votre partenaire de confiance..."></div>
                        <div class="col-12"><label class="form-label">Sous-titre logements</label><textarea name="settings[sous_titre_logements]" class="form-control" rows="2" placeholder="Découvrez quelques exemples de biens que nous gérons..."><?= e($settings['sous_titre_logements'] ?? '') ?></textarea></div>
                    </div>

                    <h6 class="text-primary mt-4 mb-2">Tarifs — "Ce qui est inclus"</h6>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Titre</label><input type="text" name="settings[titre_inclus_tarifs]" class="form-control" value="<?= e($settings['titre_inclus_tarifs'] ?? '') ?>" placeholder="Ce qui est inclus :"></div>
                        <div class="col-md-8"><label class="form-label">Elements (un par ligne)</label><textarea name="settings[inclus_tarifs]" class="form-control" rows="4" placeholder="Gestion complète des réservations&#10;Ménage professionnel entre chaque séjour&#10;..."><?= e($settings['inclus_tarifs'] ?? '') ?></textarea></div>
                    </div>

                    <h6 class="text-primary mt-4 mb-2">Disclaimers</h6>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Disclaimer services</label><textarea name="settings[disclaimer_services]" class="form-control" rows="2" placeholder="Les informations présentées sur ce site sont fournies à titre informatif..."><?= e($settings['disclaimer_services'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Disclaimer avis</label><textarea name="settings[disclaimer_avis]" class="form-control" rows="2" placeholder="Les témoignages publiés sur notre site proviennent de propriétaires..."><?= e($settings['disclaimer_avis'] ?? '') ?></textarea></div>
                    </div>

                    <h6 class="text-primary mt-4 mb-2">CTA Logements</h6>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Titre CTA</label><input type="text" name="settings[cta_logements_titre]" class="form-control" value="<?= e($settings['cta_logements_titre'] ?? '') ?>" placeholder="Vous souhaitez confier votre bien ?"></div>
                        <div class="col-md-4"><label class="form-label">Texte CTA</label><input type="text" name="settings[cta_logements_texte]" class="form-control" value="<?= e($settings['cta_logements_texte'] ?? '') ?>" placeholder="Notre équipe vous accompagne..."></div>
                        <div class="col-md-4"><label class="form-label">Bouton CTA</label><input type="text" name="settings[cta_logements_bouton]" class="form-control" value="<?= e($settings['cta_logements_bouton'] ?? '') ?>" placeholder="Contactez-nous pour un devis personnalisé"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="update_settings" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>

        <?php elseif ($page === 'newsletter'): ?>
        <!-- ═══════ NEWSLETTER ═══════ -->
        <?php include __DIR__ . '/fc_admin/newsletter.php'; ?>

        <?php elseif ($page === 'analytics'): ?>
        <!-- ═══════ ANALYTICS ═══════ -->
        <?php include __DIR__ . '/fc_admin/analytics.php'; ?>

        <?php elseif ($page === 'rgpd'): ?>
        <!-- ═══════ RGPD ═══════ -->
        <?php include __DIR__ . '/fc_admin/rgpd.php'; ?>

        <?php elseif ($page === 'users'): ?>
        <!-- ═══════ UTILISATEURS ═══════ -->
        <?php include __DIR__ . '/fc_admin/users.php'; ?>

        <?php endif; ?>
    </div>
</div>

<!-- ═══════ MODAL EDIT (services, tarifs, distinctions, villes) ═══════ -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editModalTitle">Modifier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" id="editForm">
        <?= fcCsrfField() ?>
        <div class="modal-body" id="editModalBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-warning" id="editModalSubmit">Enregistrer</button>
        </div>
    </form>
</div></div></div>

<!-- ═══════ MODAL EMAIL ═══════ -->
<div class="modal fade" id="emailModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-envelope"></i> Envoyer un email</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <form method="POST">
        <?= fcCsrfField() ?>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label">Destinataire</label><input type="email" name="email_to" id="emailModalTo" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Sujet</label><input type="text" name="email_subject" id="emailModalSubject" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Message</label><textarea name="email_body" class="form-control" rows="6" required></textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" name="send_email" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
        </div>
    </form>
</div></div></div>

<script>
function openEmailModal(email, subject) {
    document.getElementById('emailModalTo').value = email || '';
    document.getElementById('emailModalSubject').value = subject || '';
    new bootstrap.Modal(document.getElementById('emailModal')).show();
}

function openEditModal(type, data) {
    var title = '', body = '', submitName = '';
    var csrf = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

    if (type === 'service') {
        title = 'Modifier le service';
        submitName = 'edit_service';
        var items = '';
        try { items = JSON.parse(data.liste_items || '[]').join("\n"); } catch(e) { items = data.liste_items || ''; }
        body = '<input type="hidden" name="service_id" value="'+data.id+'">'
            + '<div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" value="'+esc(data.titre)+'" required></div>'
            + '<div class="mb-2"><label class="form-label">Icone (emoji)</label><input type="text" name="icone" class="form-control" value="'+esc(data.icone)+'"></div>'
            + '<div class="mb-2"><label class="form-label">Info carte</label><input type="text" name="carte_info" class="form-control" value="'+esc(data.carte_info || '')+'"></div>'
            + '<div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">'+esc(data.description || '')+'</textarea></div>'
            + '<div class="mb-2"><label class="form-label">Prestations (une/ligne)</label><textarea name="liste_items" class="form-control" rows="4">'+esc(items)+'</textarea></div>'
            + '<div class="row mb-2"><div class="col"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="'+(data.ordre||0)+'"></div>'
            + '<div class="col"><label class="form-label">Actif</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="actif" '+(data.actif == 1 ? 'checked' : '')+'></div></div></div>';
    } else if (type === 'tarif') {
        title = 'Modifier le tarif';
        submitName = 'edit_tarif';
        body = '<input type="hidden" name="tarif_id" value="'+data.id+'">'
            + '<div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" value="'+esc(data.titre)+'" required></div>'
            + '<div class="row mb-2"><div class="col"><label class="form-label">Montant</label><input type="number" step="0.01" name="montant" class="form-control" value="'+(data.montant||data.pourcentage||0)+'"></div>'
            + '<div class="col"><label class="form-label">Type</label><select name="type_tarif" class="form-select"><option value="pourcentage" '+((data.type_tarif||'pourcentage')==='pourcentage'?'selected':'')+'>%</option><option value="euro" '+((data.type_tarif)==='euro'?'selected':'')+'>Euro</option></select></div></div>'
            + '<div class="mb-2"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="'+esc(data.description || '')+'"></div>'
            + '<div class="mb-2"><label class="form-label">Details</label><textarea name="details" class="form-control" rows="2">'+esc(data.details || '')+'</textarea></div>'
            + '<div class="row mb-2"><div class="col"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="'+(data.ordre||0)+'"></div>'
            + '<div class="col"><label class="form-label">Actif</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="actif" '+(data.actif == 1 ? 'checked' : '')+'></div></div></div>';
    } else if (type === 'distinction') {
        title = 'Modifier la distinction';
        submitName = 'edit_distinction';
        body = '<input type="hidden" name="distinction_id" value="'+data.id+'">'
            + '<div class="mb-2"><label class="form-label">Titre</label><input type="text" name="titre" class="form-control" value="'+esc(data.titre)+'" required></div>'
            + '<div class="mb-2"><label class="form-label">Icone</label><input type="text" name="icone" class="form-control" value="'+esc(data.icone)+'"></div>'
            + '<div class="mb-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">'+esc(data.description || '')+'</textarea></div>'
            + '<div class="mb-2"><label class="form-label">Image</label><input type="text" name="image" class="form-control" value="'+esc(data.image || '')+'"></div>'
            + '<div class="row mb-2"><div class="col"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="'+(data.ordre||0)+'"></div>'
            + '<div class="col"><label class="form-label">Actif</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="actif" '+(data.actif == 1 ? 'checked' : '')+'></div></div></div>';
    } else if (type === 'ville') {
        title = 'Modifier la ville';
        submitName = 'edit_ville';
        body = '<input type="hidden" name="ville_id" value="'+data.id+'">'
            + '<div class="mb-2"><label class="form-label">Ville</label><input type="text" name="ville" class="form-control" value="'+esc(data.ville)+'" required></div>'
            + '<div class="mb-2"><label class="form-label">Majoration (%)</label><input type="number" step="0.01" name="majoration_percent" class="form-control" value="'+(data.majoration_percent||0)+'"></div>'
            + '<div class="row mb-2"><div class="col"><label class="form-label">Ordre</label><input type="number" name="ordre" class="form-control" value="'+(data.ordre||0)+'"></div>'
            + '<div class="col"><label class="form-label">Actif</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="actif" '+(data.actif == 1 ? 'checked' : '')+'></div></div></div>';
    }

    document.getElementById('editModalTitle').textContent = title;
    document.getElementById('editModalBody').innerHTML = body;
    document.getElementById('editModalSubmit').setAttribute('name', submitName);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function esc(s) {
    if (s === null || s === undefined) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
