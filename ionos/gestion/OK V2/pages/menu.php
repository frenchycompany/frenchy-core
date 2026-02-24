<?php
// pages/menu.php

// Définition des constantes de chemin si non déjà définies
if (!defined('BASE_PATH')) {
    // Chemin absolu vers la racine du projet (un niveau au-dessus du dossier pages)
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}
if (!defined('BASE_URL')) {
    // Chemin URL relatif à la racine du domaine
    // Ici l'application est à la racine, donc simplement '/'
    define('BASE_URL', '/');
}

// Démarrage de la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de la connexion de l'utilisateur
if (!isset($_SESSION['id_intervenant'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Récupération des informations de session
$id_intervenant  = $_SESSION['id_intervenant'];
$role            = $_SESSION['role']            ?? 'user';
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'Compte';

// Mise en place du jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Récupération des pages accessibles via la base
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

    // Vérification de l'accès à la page courante pour les non-admin
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($role !== 'admin') {
        $accessCheck = false;
        foreach ($pages_accessibles as $page) {
            if (basename($page['chemin']) === $currentFile) {
                $accessCheck = true;
                break;
            }
        }
        if (!$accessCheck) {
            header('Location: ' . BASE_URL . 'error.php?message=' . urlencode('Accès non autorisé à cette page.'));
            exit;
        }
    }
} catch (PDOException $e) {
    error_log('Erreur BD dans menu.php : ' . $e->getMessage());
    die('Erreur lors de la récupération des données du menu.');
}
?>

<!-- Barre de navigation Bootstrap 5 -->
<header>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>index.php">FrenchyConciergerie</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" aria-controls="navbarNav"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php
                $currentFile = basename($_SERVER['PHP_SELF']);
                foreach ($pages_accessibles as $page):
                    // Construction du lien absolu
                    $url = BASE_URL . ltrim($page['chemin'], '/');
                    $isActive = (basename($page['chemin']) === $currentFile);
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?> href="<?= htmlspecialchars($url) ?>">
                            <?= htmlspecialchars($page['nom']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($nom_utilisateur) ?>
                        <?php if ($role === 'admin'): ?>
                            <span class="badge bg-warning text-dark ms-2">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>profil.php">Mon Profil</a></li>
                        <li>
                            <form action="<?= BASE_URL ?>logout.php" method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <button type="submit" class="dropdown-item text-danger">Déconnexion</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
</header>
