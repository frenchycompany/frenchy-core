<?php
/**
 * Administration — Tableau de bord & Configuration
 * FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Vérification admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../error.php?message=' . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

// ============================================================
// AUTO-MIGRATION : ajout des colonnes manquantes
// ============================================================
$colonnes_config = [
    'telephone'        => "VARCHAR(30) NOT NULL DEFAULT '+33 6 47 55 46 78'",
    'adresse'          => "VARCHAR(255) NOT NULL DEFAULT ''",
    'siret'            => "VARCHAR(20) NOT NULL DEFAULT ''",
    'nom_conciergerie' => "VARCHAR(100) NOT NULL DEFAULT 'Frenchy Conciergerie'",
    'site_web'         => "VARCHAR(255) NOT NULL DEFAULT ''",
];

try {
    $existantes = [];
    $cols = $conn->query("SHOW COLUMNS FROM configuration")->fetchAll();
    foreach ($cols as $c) {
        $existantes[] = $c['Field'];
    }
    foreach ($colonnes_config as $col => $def) {
        if (!in_array($col, $existantes)) {
            $conn->exec("ALTER TABLE configuration ADD COLUMN `$col` $def");
        }
    }
} catch (PDOException $e) {
    error_log('Migration admin.php : ' . $e->getMessage());
}

// ============================================================
// TRAITEMENT POST — mise à jour configuration
// ============================================================
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_config'])) {
    validateCsrfToken();

    $fields = [
        'nom_site'          => trim($_POST['nom_site'] ?? ''),
        'nom_conciergerie'  => trim($_POST['nom_conciergerie'] ?? ''),
        'email_contact'     => trim($_POST['email_contact'] ?? ''),
        'telephone'         => trim($_POST['telephone'] ?? ''),
        'adresse'           => trim($_POST['adresse'] ?? ''),
        'siret'             => trim($_POST['siret'] ?? ''),
        'site_web'          => trim($_POST['site_web'] ?? ''),
        'footer_text'       => trim($_POST['footer_text'] ?? ''),
        'mode_maintenance'  => isset($_POST['mode_maintenance']) ? 1 : 0,
    ];

    try {
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $stmt = $conn->prepare("UPDATE configuration SET " . implode(', ', $sets) . " WHERE id = 1");
        $stmt->execute($vals);
        $feedback = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Configuration mise à jour avec succès.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (PDOException $e) {
        $feedback = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ============================================================
// CHARGEMENT DES DONNÉES
// ============================================================
try {
    // Configuration
    $config_query = $conn->query("SELECT * FROM configuration LIMIT 1");
    $config = $config_query->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        // Insérer une ligne par défaut
        $conn->exec("INSERT INTO configuration (id, nom_site, email_contact, nom_conciergerie, telephone)
                      VALUES (1, 'FrenchyConciergerie', 'contact@frenchyconciergerie.fr', 'Frenchy Conciergerie', '+33 6 47 55 46 78')");
        $config = $conn->query("SELECT * FROM configuration LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    // Compteurs dashboard
    $counts = [];
    $queries = [
        'intervenants'  => "SELECT COUNT(*) FROM intervenant",
        'pages'         => "SELECT COUNT(*) FROM pages WHERE afficher_menu = 1",
        'taches'        => "SELECT COUNT(*) FROM todo_list WHERE statut = 'en attente'",
        'logements'     => "SELECT COUNT(*) FROM liste_logements",
        'reservations'  => "SELECT COUNT(*) FROM reservation",
    ];

    foreach ($queries as $key => $sql) {
        try {
            if ($key === 'reservations') {
                require_once __DIR__ . '/../includes/rpi_db.php';
                $pdoRpi = getRpiPdo();
                $counts[$key] = $pdoRpi->query($sql)->fetchColumn();
            } else {
                $counts[$key] = $conn->query($sql)->fetchColumn();
            }
        } catch (PDOException $e) {
            $counts[$key] = '?';
        }
    }
} catch (PDOException $e) {
    header("Location: error.php?message=" . urlencode("Erreur de base de données : " . $e->getMessage()));
    exit;
}

// Valeurs par défaut pour colonnes possiblement manquantes
$cfg = function($key, $default = '') use ($config) {
    return $config[$key] ?? $default;
};
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-card { transition: transform 0.2s, box-shadow 0.2s; border: none; border-radius: 12px; }
        .admin-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .admin-card .card-body { padding: 1.5rem; }
        .admin-card .card-icon { font-size: 2rem; opacity: 0.8; }
        .admin-card .card-count { font-size: 2rem; font-weight: 700; }
        .config-section { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .config-section h5 { color: #17a2b8; border-bottom: 2px solid #17a2b8; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #495057; }
        .maintenance-toggle { padding: 1rem; border-radius: 8px; background: #f8f9fa; }
        .quick-links a { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; color: #343a40; text-decoration: none; }
        .quick-links a:hover { color: #17a2b8; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-cog text-primary"></i> Administration</h2>
            <p class="text-muted mb-0">Tableau de bord & configuration générale</p>
        </div>
    </div>

    <?= $feedback ?>

    <!-- ════════════════ DASHBOARD ════════════════ -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-lg">
            <div class="card admin-card bg-primary text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="fas fa-building admin-card card-icon mb-2"></i>
                    <div class="card-count"><?= $counts['logements'] ?></div>
                    <div>Logement<?= ($counts['logements'] !== '?' && $counts['logements'] > 1) ? 's' : '' ?></div>
                    <a href="logements.php" class="btn btn-sm btn-outline-light mt-auto">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card admin-card bg-success text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="fas fa-calendar-check admin-card card-icon mb-2"></i>
                    <div class="card-count"><?= $counts['reservations'] ?></div>
                    <div>Réservation<?= ($counts['reservations'] !== '?' && $counts['reservations'] > 1) ? 's' : '' ?></div>
                    <a href="reservations.php" class="btn btn-sm btn-outline-light mt-auto">Voir</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card admin-card bg-info text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="fas fa-users admin-card card-icon mb-2"></i>
                    <div class="card-count"><?= $counts['intervenants'] ?></div>
                    <div>Intervenant<?= ($counts['intervenants'] !== '?' && $counts['intervenants'] > 1) ? 's' : '' ?></div>
                    <a href="intervenants.php" class="btn btn-sm btn-outline-light mt-auto">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card admin-card bg-warning text-dark h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="fas fa-tasks admin-card card-icon mb-2"></i>
                    <div class="card-count"><?= $counts['taches'] ?></div>
                    <div>Tâche<?= ($counts['taches'] !== '?' && $counts['taches'] > 1) ? 's' : '' ?> en attente</div>
                    <a href="todo.php" class="btn btn-sm btn-outline-dark mt-auto">Voir</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="card admin-card bg-secondary text-white h-100">
                <div class="card-body d-flex flex-column align-items-center text-center">
                    <i class="fas fa-file-alt admin-card card-icon mb-2"></i>
                    <div class="card-count"><?= $counts['pages'] ?></div>
                    <div>Page<?= ($counts['pages'] !== '?' && $counts['pages'] > 1) ? 's' : '' ?> actives</div>
                    <a href="gestion_pages.php" class="btn btn-sm btn-outline-light mt-auto">Gérer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════ CONFIGURATION ════════════════ -->
    <form method="POST">
        <?php echoCsrfField(); ?>

        <div class="row g-4">
            <!-- Colonne gauche : Identité & Contact -->
            <div class="col-lg-6">
                <div class="config-section h-100">
                    <h5><i class="fas fa-id-card"></i> Identité & Contact</h5>

                    <div class="mb-3">
                        <label class="form-label" for="nom_conciergerie">Nom de la conciergerie</label>
                        <input type="text" class="form-control" id="nom_conciergerie" name="nom_conciergerie"
                               value="<?= htmlspecialchars($cfg('nom_conciergerie', 'Frenchy Conciergerie')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nom_site">Nom du site (titre)</label>
                        <input type="text" class="form-control" id="nom_site" name="nom_site"
                               value="<?= htmlspecialchars($cfg('nom_site', 'FrenchyConciergerie')) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email_contact">Email de contact</label>
                            <input type="email" class="form-control" id="email_contact" name="email_contact"
                                   value="<?= htmlspecialchars($cfg('email_contact', 'contact@frenchyconciergerie.fr')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                   value="<?= htmlspecialchars($cfg('telephone', '+33 6 47 55 46 78')) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adresse">Adresse</label>
                        <input type="text" class="form-control" id="adresse" name="adresse"
                               value="<?= htmlspecialchars($cfg('adresse')) ?>"
                               placeholder="Ex: 12 rue de la Paix, 60350 Pierrefonds">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="siret">SIRET</label>
                            <input type="text" class="form-control" id="siret" name="siret"
                                   value="<?= htmlspecialchars($cfg('siret')) ?>"
                                   placeholder="XXX XXX XXX XXXXX" maxlength="20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="site_web">Site web</label>
                            <input type="url" class="form-control" id="site_web" name="site_web"
                                   value="<?= htmlspecialchars($cfg('site_web')) ?>"
                                   placeholder="https://frenchyconciergerie.fr">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite : Paramètres & Liens rapides -->
            <div class="col-lg-6">
                <div class="config-section mb-4">
                    <h5><i class="fas fa-sliders-h"></i> Paramètres</h5>

                    <div class="mb-3">
                        <label class="form-label" for="footer_text">Texte du pied de page</label>
                        <textarea class="form-control" id="footer_text" name="footer_text" rows="2"
                                  placeholder="© 2024 Frenchy Conciergerie — Tous droits réservés"><?= htmlspecialchars($cfg('footer_text')) ?></textarea>
                    </div>

                    <div class="maintenance-toggle">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="mode_maintenance" name="mode_maintenance"
                                   <?= !empty($config['mode_maintenance']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="mode_maintenance">
                                <i class="fas fa-tools"></i> Mode maintenance
                            </label>
                        </div>
                        <small class="text-muted">Bloque l'accès au site pour les non-administrateurs.</small>
                    </div>
                </div>

                <div class="config-section">
                    <h5><i class="fas fa-link"></i> Liens rapides</h5>
                    <div class="quick-links">
                        <a href="logements.php"><i class="fas fa-home text-primary"></i> Gérer les logements</a>
                        <a href="intervenants.php"><i class="fas fa-users text-info"></i> Gérer les intervenants</a>
                        <a href="gestion_pages.php"><i class="fas fa-file-circle-plus text-success"></i> Gérer les pages</a>
                        <a href="planning.php"><i class="fas fa-calendar-alt text-warning"></i> Voir le planning</a>
                        <a href="sites.php"><i class="fas fa-globe text-secondary"></i> Sites vitrine</a>
                        <a href="statistiques.php"><i class="fas fa-chart-bar text-danger"></i> Statistiques</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end mt-4 mb-5">
            <button type="submit" name="update_config" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Enregistrer la configuration
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
