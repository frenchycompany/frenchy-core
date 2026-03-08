<?php
/**
 * Menu de navigation unifié — FrenchyConciergerie
 * Toutes les pages organisées par catégories
 * Permissions : admin = tout, user = pages assignées en BDD
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

// Vérification de la connexion (compatible ancien + nouveau système)
if (!isset($_SESSION['id_intervenant']) && !isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Timeout de session (30 min d'inactivite)
$sessionTimeout = 1800;
if (isset($_SESSION['_auth_last_activity']) && (time() - $_SESSION['_auth_last_activity']) > $sessionTimeout) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?expired=1');
    exit;
}
$_SESSION['_auth_last_activity'] = time();

$id_intervenant  = $_SESSION['id_intervenant'] ?? $_SESSION['user_id'] ?? 0;
$role            = $_SESSION['role'] ?? (in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin']) ? 'admin' : 'user');
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? $_SESSION['user_nom'] ?? 'Compte';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Charger le systeme i18n
if (file_exists(__DIR__ . '/../includes/i18n.php')) {
    require_once __DIR__ . '/../includes/i18n.php';
}

// Pages accessibles depuis la BDD (système de permissions)
// Compatible avec l'ancien système (intervenants_pages) et le nouveau (user_permissions)
$pages_accessibles = [];
try {
    if ($role === 'admin') {
        $stmt = $conn->query("SELECT id, nom, chemin FROM pages WHERE afficher_menu = 1");
    } else {
        // Essayer d'abord le nouveau système (user_permissions)
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $stmt = $conn->prepare(
                "SELECT p.id, p.nom, p.chemin
                 FROM pages p
                 INNER JOIN user_permissions up ON p.id = up.page_id
                 WHERE up.user_id = :user_id
                   AND p.afficher_menu = 1"
            );
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fallback : ancien système (intervenants_pages)
        if (empty($pages_accessibles)) {
            $stmt = $conn->prepare(
                "SELECT p.id, p.nom, p.chemin
                 FROM pages p
                 INNER JOIN intervenants_pages ip ON p.id = ip.page_id
                 WHERE ip.intervenant_id = :id_intervenant
                   AND p.afficher_menu = 1"
            );
            $stmt->bindValue(':id_intervenant', $id_intervenant, PDO::PARAM_INT);
            $stmt->execute();
            $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    if (empty($pages_accessibles) && $role !== 'admin') {
        // Déjà récupéré ci-dessus
    } else if ($role === 'admin') {
        $pages_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Erreur BD dans menu.php : ' . $e->getMessage());
}

$currentFile = basename($_SERVER['PHP_SELF']);

// Set de noms de fichiers accessibles pour vérification rapide
$fichiers_accessibles = array_map(fn($p) => basename($p['chemin']), $pages_accessibles);

/**
 * Vérifie si l'utilisateur a accès à une page
 */
function userCanAccess(string $chemin, string $role, array $fichiers_accessibles): bool {
    if ($role === 'admin') return true;
    return in_array(basename($chemin), $fichiers_accessibles);
}

// ============================================
// DÉFINITION DES CATÉGORIES DU MENU (centralisée)
// ============================================
require_once __DIR__ . '/menu_categories.php';

// Pages déjà listées dans les catégories (pour éviter les doublons)
$pages_dans_categories = [];
foreach ($menu_categories as $cat) {
    foreach ($cat['items'] as $item) {
        $pages_dans_categories[] = basename($item['chemin']);
    }
}

// Pages dynamiques de la BDD qui ne sont dans aucune catégorie
$pages_hors_categories = [];
foreach ($pages_accessibles as $page) {
    $bn = basename($page['chemin']);
    if (!in_array($bn, $pages_dans_categories)) {
        $pages_hors_categories[] = $page;
    }
}
?>

<!-- Bootstrap 5 + FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/menu.css">

<!-- Barre de navigation -->
<header>
<nav class="navbar navbar-expand-lg navbar-dark fc-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>index.php">
            <i class="fas fa-bolt"></i> Frenchy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" aria-controls="navbarNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Accueil -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentFile === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>index.php">
                        <i class="fas fa-tachometer-alt"></i> Accueil
                    </a>
                </li>

                <!-- Catégories organisées en dropdowns -->
                <?php foreach ($menu_categories as $categorie_nom => $categorie): ?>
                    <?php
                    // Filtrer les items accessibles à cet utilisateur
                    $items_visibles = [];
                    $cat_active = false;
                    foreach ($categorie['items'] as $item) {
                        if (userCanAccess($item['chemin'], $role, $fichiers_accessibles)) {
                            $items_visibles[] = $item;
                            if (basename($item['chemin']) === $currentFile) {
                                $cat_active = true;
                            }
                        }
                    }
                    if (empty($items_visibles)) continue;
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $cat_active ? 'active' : '' ?>"
                           href="#" data-bs-toggle="dropdown">
                            <i class="fas <?= $categorie['icon'] ?>"></i> <?= $categorie_nom ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($items_visibles as $item):
                                $url = BASE_URL . ltrim($item['chemin'], '/');
                                $isActive = (basename($item['chemin']) === $currentFile);
                            ?>
                                <li>
                                    <a class="dropdown-item <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                                        <i class="fas <?= $item['icon'] ?>"></i> <?= htmlspecialchars($item['nom']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>

                <!-- Pages dynamiques non catégorisées (ajoutées via admin) -->
                <?php if (!empty($pages_hors_categories)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h"></i> Autres
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($pages_hors_categories as $page):
                                $chemin = $page['chemin'];
                                if (!str_starts_with($chemin, 'pages/') && file_exists(BASE_PATH . '/pages/' . basename($chemin))) {
                                    $chemin = 'pages/' . basename($chemin);
                                }
                                $url = BASE_URL . ltrim($chemin, '/');
                                $isActive = (basename($chemin) === $currentFile);
                            ?>
                                <li>
                                    <a class="dropdown-item <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($url) ?>">
                                        <?= htmlspecialchars($page['nom']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>

            <!-- Partie droite : langue + utilisateur -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (function_exists('langSelector')): ?>
                <li class="nav-item d-flex align-items-center me-2">
                    <?= langSelector() ?>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/gestion_pages.php">
                            <i class="fas fa-file-circle-plus"></i> Gestion pages</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>pages/intervenants.php">
                            <i class="fas fa-users"></i> Intervenants</a></li>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
