<?php
// Protection par authentification
require_once __DIR__ . '/auth.php';

// Vérifier que l'utilisateur est connecté (sauf pour login.php)
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page !== 'login.php') {
    requireAuth();
    checkSessionTimeout();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des SMS - Interface Moderne</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<!-- Barre de navigation moderne -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../pages/dashboard.php">
            <i class="fas fa-comments"></i> Gestion SMS
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <!-- Lien vers le Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <!-- Lien vers la liste des réservations -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownReservations" role="button" data-toggle="dropdown">
                        <i class="fas fa-calendar-check"></i> Réservations
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="../pages/reservations_listing.php">
                            <i class="fas fa-list"></i> Listing complet
                        </a>
                        <a class="dropdown-item" href="../pages/reservation_list.php">
                            <i class="fas fa-sms"></i> Vue SMS automatiques
                        </a>
                    </div>
                </li>
                <!-- Lien vers la page de gestion des scénarios -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/scenario.php">
                        <i class="fas fa-project-diagram"></i> Scénarios
                    </a>
                </li>
                <!-- Lien vers les SMS reçus -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/recus.php">
                        <i class="fas fa-inbox"></i> SMS reçus
                    </a>
                </li>
                <!-- Lien vers l'envoi de SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/envoyer.php">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </a>
                </li>
                <!-- Lien vers les campagnes SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/campaigns.php">
                        <i class="fas fa-bullhorn"></i> Campagnes
                    </a>
                </li>
                <!-- Lien vers les templates SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/templates.php">
                        <i class="fas fa-file-alt"></i> Templates
                    </a>
                </li>
                <!-- Lien vers les logements -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/logements.php">
                        <i class="fas fa-home"></i> Logements
                    </a>
                </li>
                <!-- Lien vers l'analyse du marché -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMarche" role="button" data-toggle="dropdown">
                        <i class="fas fa-chart-line"></i> Marché
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="../pages/analyse_marche.php">
                            <i class="fas fa-plus-circle"></i> Capturer concurrents
                        </a>
                        <a class="dropdown-item" href="../pages/analyse_concurrence.php">
                            <i class="fas fa-chart-bar"></i> Analyse concurrence
                        </a>
                    </div>
                </li>
                <!-- Lien vers l'automatisation -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAutomation" role="button" data-toggle="dropdown">
                        <i class="fas fa-robot"></i> Automatisation
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="../pages/automation_config.php">
                            <i class="fas fa-cog"></i> Configuration
                        </a>
                        <a class="dropdown-item" href="../pages/custom_automations.php">
                            <i class="fas fa-plus-circle"></i> Automatisations personnalisées
                        </a>
                    </div>
                </li>
                <!-- Lien vers la synchronisation des réservations -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/update_reservations.php">
                        <i class="fas fa-sync-alt"></i> Sync iCal
                    </a>
                </li>
                <!-- Séparateur -->
                <li class="nav-item">
                    <span class="nav-link text-muted">|</span>
                </li>
                <!-- Menu utilisateur -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars(getCurrentUserName() ?? 'Utilisateur') ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <div class="dropdown-item disabled">
                            <small class="text-muted"><?= htmlspecialchars(getCurrentUserEmail() ?? '') ?></small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../pages/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
