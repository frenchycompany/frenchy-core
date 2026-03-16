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
    'simulateur_config' => ['icon' => 'fa-calculator', 'label' => 'Simulateur'],
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
                                    <td><form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="distinction_id" value="<?= $d['id'] ?>"><button type="submit" name="delete_distinction" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>
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
                                    <td><form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')"><?= fcCsrfField() ?><input type="hidden" name="ville_id" value="<?= $ville['id'] ?>"><button type="submit" name="delete_ville" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button></form></td>
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
                        <div class="col-md-4"><label class="form-label">Forme juridique</label><input type="text" name="settings[forme_juridique]" class="form-control" value="<?= e($settings['forme_juridique'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">SIRET</label><input type="text" name="settings[siret]" class="form-control" value="<?= e($settings['siret'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">RCS</label><input type="text" name="settings[rcs]" class="form-control" value="<?= e($settings['rcs'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">N° TVA</label><input type="text" name="settings[tva_intra]" class="form-control" value="<?= e($settings['tva_intra'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Président(e)</label><input type="text" name="settings[presidente]" class="form-control" value="<?= e($settings['presidente'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Email légal</label><input type="email" name="settings[email_legal]" class="form-control" value="<?= e($settings['email_legal'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
            <button type="submit" name="update_settings" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>

        <?php endif; ?>
    </div>
</div>
