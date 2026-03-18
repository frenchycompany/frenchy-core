<?php
/**
 * Verification d'authentification proprietaire — inclusion commune
 * Utilise le systeme Auth.php unifie
 */
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../db/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction d'echappement HTML
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Protection CSRF pour les formulaires proprietaire
function proprio_csrf_token() {
    if (empty($_SESSION['proprio_csrf_token'])) {
        $_SESSION['proprio_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['proprio_csrf_token'];
}

function proprio_csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(proprio_csrf_token()) . '">';
}

function proprio_validate_csrf($token) {
    return !empty($token) && hash_equals($_SESSION['proprio_csrf_token'] ?? '', $token);
}

// Détecter si le nouveau système est disponible (table users)
$useNewAuth = false;
try {
    $conn->query("SELECT 1 FROM users LIMIT 1");
    $useNewAuth = true;
} catch (PDOException $e) {}

$auth = null;
if ($useNewAuth) {
    require_once __DIR__ . '/../includes/Auth.php';
    $auth = new Auth($conn);
}

// Verifier l'authentification proprietaire
if ($auth) {
    if (!$auth->isProprietaire() && !$auth->isAdmin() && !isset($_SESSION['proprietaire_id'])) {
        header('Location: login.php');
        exit;
    }
} else {
    if (!isset($_SESSION['proprietaire_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Charger les donnees proprietaire
$proprietaire = null;
$proprietaire_id = null;

if ($auth && $auth->check() && $auth->isProprietaire()) {
    // Systeme unifie Auth.php
    $proprietaire_id = $_SESSION['user_id'];
    $user = $auth->user();

    if (!$user) {
        $auth->logout();
        header('Location: login.php');
        exit;
    }

    $proprietaire = [
        'id'         => $user['id'],
        'nom'        => $user['nom'],
        'prenom'     => $user['prenom'],
        'email'      => $user['email'],
        'telephone'  => $user['telephone'],
        'adresse'    => $user['adresse'],
        'photo'      => $user['photo'] ?? null,
        'societe'    => $user['societe'] ?? null,
        'siret'      => $user['siret'] ?? null,
        'commission' => $user['commission'] ?? null,
        'actif'      => $user['actif'],
        'role'       => $user['role'],
    ];

    $_SESSION['proprietaire_id'] = $user['legacy_proprietaire_id'] ?? $user['id'];
    $proprietaire_id = $_SESSION['proprietaire_id'];

} elseif (isset($_SESSION['proprietaire_id'])) {
    // Fallback ancien systeme (sera supprime apres migration complete)
    $proprietaire_id = $_SESSION['proprietaire_id'];

    try {
        $stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE id = ? AND actif = 1");
        $stmt->execute([$proprietaire_id]);
        $proprietaire = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('proprietaire/auth.php: ' . $e->getMessage());
    }

    if (!$proprietaire) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// Logements du proprietaire
$stmt = $conn->prepare("SELECT * FROM liste_logements WHERE proprietaire_id = ? ORDER BY nom_du_logement");
$stmt->execute([$proprietaire_id]);
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logement_ids = array_column($logements, 'id');
$placeholders = !empty($logement_ids) ? str_repeat('?,', count($logement_ids) - 1) . '?' : '';

// Sites vitrine (pour sidebar)
$has_sites = false;
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM frenchysite_instances WHERE logement_id IN ($placeholders) AND actif = 1");
        $stmt->execute($logement_ids);
        $has_sites = (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('proprietaire/auth.php: ' . $e->getMessage());
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);

function proprioSidebar($proprietaire, $currentPage, $has_sites) {
    $e = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };
    ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../../frenchyconciergerie.png.png" alt="Logo" onerror="this.style.display='none'">
            <h2><?= $e($proprietaire['prenom'] ?? '') ?> <?= $e($proprietaire['nom']) ?></h2>
            <p>Proprietaire</p>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-tachometer-alt"></i></span> Tableau de bord
            </a>
            <a href="taches.php" class="<?= $currentPage === 'taches.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-tasks"></i></span> Taches
            </a>
            <a href="calendrier.php" class="<?= $currentPage === 'calendrier.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-calendar-alt"></i></span> Calendrier
            </a>
            <a href="checkups.php" class="<?= $currentPage === 'checkups.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-clipboard-check"></i></span> Checkups
            </a>
            <a href="interventions.php" class="<?= $currentPage === 'interventions.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-broom"></i></span> Interventions
            </a>
            <a href="inventaires.php" class="<?= $currentPage === 'inventaires.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-boxes-stacked"></i></span> Inventaires
            </a>
            <?php if ($has_sites): ?>
            <a href="sites.php" class="<?= $currentPage === 'sites.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-globe"></i></span> Sites vitrine
            </a>
            <?php endif; ?>
            <a href="coffre_fort.php" class="<?= $currentPage === 'coffre_fort.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-vault"></i></span> Coffre-fort
            </a>
            <div class="nav-separator"></div>
            <a href="profil.php" class="<?= $currentPage === 'profil.php' ? 'active' : '' ?>">
                <span class="icon"><i class="fas fa-user"></i></span> Mon profil
            </a>
            <a href="logout.php">
                <span class="icon"><i class="fas fa-sign-out-alt"></i></span> Deconnexion
            </a>
        </nav>
    </aside>
    <?php
}
