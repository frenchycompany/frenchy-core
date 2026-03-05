<?php
/**
 * Vérification d'authentification propriétaire — inclusion commune
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

$security = new Security($conn);
$settings = getAllSettings($conn);

if (!isset($_SESSION['proprietaire_id'])) {
    header('Location: login.php');
    exit;
}

$proprietaire_id = $_SESSION['proprietaire_id'];

$stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE id = ? AND actif = 1");
$stmt->execute([$proprietaire_id]);
$proprietaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proprietaire) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Logements du propriétaire
$stmt = $conn->prepare("SELECT * FROM liste_logements WHERE proprietaire_id = ? ORDER BY nom_du_logement");
$stmt->execute([$proprietaire_id]);
$logements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logement_ids = array_column($logements, 'id');
$placeholders = !empty($logement_ids) ? str_repeat('?,', count($logement_ids) - 1) . '?' : '';

// Sites vitrine (pour sidebar)
$sites_vitrine = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM frenchysite_instances WHERE logement_id IN ($placeholders) AND actif = 1");
        $stmt->execute($logement_ids);
        $has_sites = (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) { $has_sites = false; }
} else {
    $has_sites = false;
}

$currentPage = basename($_SERVER['PHP_SELF']);

function proprioSidebar($proprietaire, $currentPage, $has_sites) {
    ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../../frenchyconciergerie.png.png" alt="Logo" onerror="this.style.display='none'">
            <h2><?= e($proprietaire['prenom'] ?? '') ?> <?= e($proprietaire['nom']) ?></h2>
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
