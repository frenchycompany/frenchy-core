<?php
/**
 * Header avec nouvelle architecture - Version 2.0
 * Architecture claire : Communication → Automatisations → Templates
 */

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
    <title>Gestion SMS - Locations courte durée</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <style>
        /* Menu avec indicateur actif */
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .dropdown-menu {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .nav-section-title {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>

<!-- Barre de navigation moderne et claire -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="../pages/dashboard.php">
            <i class="fas fa-comments-dollar"></i> <strong>SMS Manager</strong>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="../pages/dashboard.php">
                        <i class="fas fa-chart-line"></i> Tableau de bord
                    </a>
                </li>

                <!-- Réservations -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['reservations_listing.php', 'reservation_details.php', 'update_reservations.php']) ? 'active' : '' ?>"
                       href="#" id="navbarReservations" role="button" data-toggle="dropdown">
                        <i class="fas fa-calendar-check"></i> Réservations
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="../pages/reservations_listing.php">
                            <i class="fas fa-list"></i> Liste des réservations
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../pages/update_reservations.php">
                            <i class="fas fa-sync-alt"></i> Synchronisation iCal
                        </a>
                    </div>
                </li>

                <!-- Communication -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['communication.php', 'recus.php', 'campaigns.php']) ? 'active' : '' ?>"
                       href="#" id="navbarCommunication" role="button" data-toggle="dropdown">
                        <i class="fas fa-comments"></i> Communication
                    </a>
                    <div class="dropdown-menu">
                        <h6 class="dropdown-header">Envoyer des SMS</h6>
                        <a class="dropdown-item" href="../pages/communication.php">
                            <i class="fas fa-paper-plane"></i> Envoyer un SMS
                        </a>
                        <a class="dropdown-item" href="../pages/campaigns.php">
                            <i class="fas fa-bullhorn"></i> Campagnes groupées
                        </a>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header">Historique</h6>
                        <a class="dropdown-item" href="../pages/recus.php">
                            <i class="fas fa-inbox"></i> Historique des SMS
                        </a>
                    </div>
                </li>

                <!-- Automatisations -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'automations.php' ? 'active' : '' ?>" href="../pages/automations.php">
                        <i class="fas fa-robot"></i> Automatisations
                    </a>
                </li>

                <!-- Templates -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'templates.php' ? 'active' : '' ?>" href="../pages/templates.php">
                        <i class="fas fa-file-alt"></i> Templates
                    </a>
                </li>

                <!-- Logements -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'logements.php' ? 'active' : '' ?>" href="../pages/logements.php">
                        <i class="fas fa-home"></i> Logements
                    </a>
                </li>

            </ul>

            <!-- Menu utilisateur -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarUser" role="button" data-toggle="dropdown">
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

<!-- Breadcrumb pour indiquer où on est -->
<?php if ($current_page !== 'dashboard.php' && $current_page !== 'login.php'): ?>
<div class="container-fluid mt-2">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light">
            <li class="breadcrumb-item"><a href="../pages/dashboard.php"><i class="fas fa-home"></i> Accueil</a></li>
            <?php
            $page_titles = [
                'reservations_listing.php' => 'Réservations',
                'update_reservations.php' => 'Synchronisation iCal',
                'communication.php' => 'Envoyer un SMS',
                'recus.php' => 'Historique des SMS',
                'campaigns.php' => 'Campagnes',
                'automations.php' => 'Automatisations',
                'templates.php' => 'Templates SMS',
                'logements.php' => 'Gestion des logements',
                'reservation_details.php' => 'Détail réservation'
            ];

            if (isset($page_titles[$current_page])) {
                echo '<li class="breadcrumb-item active">' . $page_titles[$current_page] . '</li>';
            }
            ?>
        </ol>
    </nav>
</div>
<?php endif; ?>

<div class="container-fluid mt-4">
