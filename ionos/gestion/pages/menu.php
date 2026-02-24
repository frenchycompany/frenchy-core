<?php
/**
 * Menu de navigation unifié — FrenchyConciergerie
 * Combine les modules IONOS (planning, ménage, comptabilité, inventaire)
 * et les modules Raspberry Pi (SMS, réservations, sync iCal, tarifs, marché)
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de la connexion
if (!isset($_SESSION['id_intervenant'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$id_intervenant  = $_SESSION['id_intervenant'];
$role            = $_SESSION['role']            ?? 'user';
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'Compte';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Pages accessibles depuis la BDD (système existant IONOS)
$pages_accessibles = [];
try {
    if ($role === 'admin') {
        $stmt = $conn->query("SELECT id, nom, chemin FROM pages WHERE afficher_menu = 1");
    } else {
        $stmt = $conn->prepare(
            "SELECT p.id, p.nom, p.chemin
             FROM pages p
             INNER JOIN intervenants_pages ip ON p.id = ip.page_id
             WHERE ip.intervenant_id = :id_intervenant
               AND p.afficher_menu = 1"
        );
        $stmt->bindValue(':id_intervenant', $id_intervenant, PDO::PARAM_INT);
        $stmt->execute();
    }
    $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erreur BD dans menu.php : ' . $e->getMessage());
}

$currentFile = basename($_SERVER['PHP_SELF']);

// Vérification d'accès pour non-admin
if ($role !== 'admin') {
    $knownPages = array_map(fn($p) => basename($p['chemin']), $pages_accessibles);
    // Les nouvelles pages intégrées sont accessibles aux admins uniquement pour l'instant
    // On peut étendre le système de permissions plus tard
}
?>

<!-- Barre de navigation Bootstrap 5 -->
<header>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">
            <i class="fas fa-bolt text-success"></i> FrenchyConciergerie
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" aria-controls="navbarNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Pages dynamiques depuis la BDD (planning, comptabilité, etc.) -->
                <?php foreach ($pages_accessibles as $page):
                    $url = BASE_URL . ltrim($page['chemin'], '/');
                    $isActive = (basename($page['chemin']) === $currentFile);
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                            <?= htmlspecialchars($page['nom']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($role === 'admin'): ?>
                <!-- ============================================ -->
                <!-- MODULES INTÉGRÉS (ex-Raspberry Pi)           -->
                <!-- ============================================ -->

                <!-- Réservations & Sync -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($currentFile, ['reservations.php','sync_ical.php','occupation.php']) ? 'active' : '' ?>"
                       href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar-check"></i> Réservations
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/reservations.php">
                            <i class="fas fa-list"></i> Listing complet</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sync_ical.php">
                            <i class="fas fa-sync-alt"></i> Synchronisation iCal</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/occupation.php">
                            <i class="fas fa-chart-pie"></i> Taux d'occupation</a></li>
                    </ul>
                </li>

                <!-- SMS & Communication -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($currentFile, ['sms_recus.php','sms_envoyer.php','sms_templates.php','sms_campagnes.php','sms_automations.php']) ? 'active' : '' ?>"
                       href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-sms"></i> SMS
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sms_recus.php">
                            <i class="fas fa-inbox"></i> SMS reçus</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sms_envoyer.php">
                            <i class="fas fa-paper-plane"></i> Envoyer SMS</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sms_templates.php">
                            <i class="fas fa-file-alt"></i> Templates</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sms_automations.php">
                            <i class="fas fa-robot"></i> Automatisations</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/sms_campagnes.php">
                            <i class="fas fa-bullhorn"></i> Campagnes</a></li>
                    </ul>
                </li>

                <!-- Outils avancés -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($currentFile, ['superhote.php','analyse_marche.php','analyse_concurrence.php','clients.php']) ? 'active' : '' ?>"
                       href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-tools"></i> Outils
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/superhote.php">
                            <i class="fas fa-euro-sign"></i> Superhôte (Tarifs)</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/analyse_marche.php">
                            <i class="fas fa-chart-line"></i> Analyse de marché</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/analyse_concurrence.php">
                            <i class="fas fa-chart-bar"></i> Concurrence</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/clients.php">
                            <i class="fas fa-address-book"></i> Carnet clients</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/villes.php">
                            <i class="fas fa-city"></i> Villes & Recommandations</a></li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <!-- Partie droite : utilisateur -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle"></i>
                        <?= htmlspecialchars($nom_utilisateur) ?>
                        <?php if ($role === 'admin'): ?>
                            <span class="badge bg-warning text-dark ms-1">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>profil.php">
                            <i class="fas fa-user"></i> Mon Profil</a></li>
                        <?php if ($role === 'admin'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/admin.php">
                            <i class="fas fa-cog"></i> Administration</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="<?= BASE_URL ?>logout.php" method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
</header>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
