<?php
/**
 * Header Minimal - Version simplifiee
 * Centre sur les 6 fonctionnalites principales :
 * 1. Dashboard
 * 2. Sync Reservations (iCal)
 * 3. SMS Recus
 * 4. Campagnes SMS
 * 5. Reservations du jour
 * 6. Superhote (Yield Management)
 */

// Protection par authentification
require_once __DIR__ . '/auth.php';

// Verifier que l'utilisateur est connecte (sauf pour login.php)
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
    <title>SMS Manager</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- jQuery (chargé en premier pour les scripts inline) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        /* Navigation principale */
        .main-nav {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .main-nav .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            padding: 1rem 1.5rem;
            color: #fff !important;
        }

        .main-nav .navbar-brand i {
            color: #4ade80;
        }

        /* Menu items */
        .nav-menu {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-menu .nav-item {
            position: relative;
        }

        .nav-menu .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 1rem 1.25rem;
            color: rgba(255,255,255,0.7) !important;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }

        .nav-menu .nav-link:hover {
            color: #fff !important;
            background: rgba(255,255,255,0.05);
        }

        .nav-menu .nav-link.active {
            color: #fff !important;
            background: rgba(255,255,255,0.1);
            border-bottom-color: #4ade80;
        }

        .nav-menu .nav-link i {
            font-size: 1.1rem;
        }

        /* Badge notification */
        .nav-badge {
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
            font-weight: 600;
        }

        /* User menu */
        .user-menu {
            margin-left: auto;
            padding-right: 1rem;
        }

        .user-menu .nav-link {
            padding: 0.75rem 1rem;
        }

        .user-menu .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
        }

        /* Quick actions bar */
        .quick-actions {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quick-actions .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 0.85rem;
        }

        .quick-actions .breadcrumb-item a {
            color: #6b7280;
            text-decoration: none;
        }

        .quick-actions .breadcrumb-item.active {
            color: #1f2937;
            font-weight: 500;
        }

        /* Container principal */
        .main-container {
            padding: 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            padding: 1rem 1.25rem;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }

        .btn-success {
            background: #10b981;
            border: none;
        }

        /* Tables */
        .table thead th {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .nav-menu {
                flex-direction: column;
                width: 100%;
            }
            .nav-menu .nav-link {
                padding: 0.75rem 1.5rem;
                border-bottom: none;
                border-left: 3px solid transparent;
            }
            .nav-menu .nav-link.active {
                border-bottom: none;
                border-left-color: #4ade80;
            }
        }

        /* Utility classes for cards */
        .border-left-primary { border-left: 4px solid #667eea !important; }
        .border-left-success { border-left: 4px solid #10b981 !important; }
        .border-left-info { border-left: 4px solid #17a2b8 !important; }
        .border-left-warning { border-left: 4px solid #f59e0b !important; }
        .border-left-danger { border-left: 4px solid #ef4444 !important; }

        .text-xs { font-size: 0.75rem; }
        .text-gray-300 { color: #d1d5db !important; }

        .shadow-custom { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

        /* Page header styling */
        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .page-header .lead {
            color: #6b7280;
            font-size: 1rem;
        }

        /* Stat cards */
        .stat-card .card-body {
            padding: 1rem;
        }
        .stat-card .h5 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Search bar in header */
        .search-form {
            margin-left: auto;
            margin-right: 1rem;
        }
        .search-form .search-input {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 20px 0 0 20px;
            padding: 0.4rem 1rem;
            width: 220px;
            font-size: 0.85rem;
        }
        .search-form .search-input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        .search-form .search-input:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
            box-shadow: none;
            color: #fff;
        }
        .search-form .btn-search {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            border-left: none;
            color: rgba(255,255,255,0.7);
            border-radius: 0 20px 20px 0;
            padding: 0.4rem 0.8rem;
        }
        .search-form .btn-search:hover {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }
    </style>
</head>
<body>

<!-- Navigation principale simplifiee -->
<nav class="navbar navbar-expand-lg main-nav">
    <a class="navbar-brand" href="dashboard.php">
        <i class="fas fa-bolt"></i> SMS Manager
    </a>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
        <ul class="nav-menu">
            <!-- 1. Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- 2. Sync iCal -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'update_reservations.php' ? 'active' : '' ?>" href="update_reservations.php">
                    <i class="fas fa-sync-alt"></i>
                    <span>Sync iCal</span>
                </a>
            </li>

            <!-- 3. SMS Recus -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'recus.php' ? 'active' : '' ?>" href="recus.php">
                    <i class="fas fa-inbox"></i>
                    <span>SMS Recus</span>
                </a>
            </li>

            <!-- 4. Campagnes SMS -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'campaigns.php' ? 'active' : '' ?>" href="campaigns.php">
                    <i class="fas fa-bullhorn"></i>
                    <span>Campagnes</span>
                </a>
            </li>

            <!-- 5. Reservations du jour -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'reservation_list.php' ? 'active' : '' ?>" href="reservation_list.php">
                    <i class="fas fa-calendar-day"></i>
                    <span>Reservations</span>
                </a>
            </li>

            <!-- 6. Superhote / Yield -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'superhote_config.php' ? 'active' : '' ?>" href="superhote_config.php">
                    <i class="fas fa-euro-sign"></i>
                    <span>Tarifs</span>
                </a>
            </li>

            <!-- 7. Analyse Marche -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= in_array($current_page, ['analyse_marche.php', 'analyse_concurrence.php', 'capture_airbnb.php']) ? 'active' : '' ?>" href="#" data-toggle="dropdown">
                    <i class="fas fa-chart-line"></i>
                    <span>Marche</span>
                </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="analyse_marche.php">
                        <i class="fas fa-plus-circle"></i> Capturer concurrents
                    </a>
                    <a class="dropdown-item" href="analyse_concurrence.php">
                        <i class="fas fa-chart-bar"></i> Analyse
                    </a>
                </div>
            </li>

            <!-- 8. Recherche -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'search.php' ? 'active' : '' ?>" href="search.php">
                    <i class="fas fa-search"></i>
                    <span>Recherche</span>
                </a>
            </li>
        </ul>

        <!-- Barre de recherche rapide -->
        <form class="search-form d-none d-lg-flex" action="search.php" method="GET">
            <div class="input-group">
                <input type="text" name="q" class="form-control search-input" placeholder="Rechercher (tel, nom, logement...)" autocomplete="off">
                <div class="input-group-append">
                    <button class="btn btn-search" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>

        <!-- Menu utilisateur -->
        <ul class="navbar-nav user-menu">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                    <i class="fas fa-user-circle"></i>
                    <span class="d-none d-md-inline"><?= htmlspecialchars(getCurrentUserName() ?? 'Admin') ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item text-muted" href="#">
                        <small><?= htmlspecialchars(getCurrentUserEmail() ?? '') ?></small>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logements.php">
                        <i class="fas fa-home"></i> Logements
                    </a>
                    <a class="dropdown-item" href="sites.php">
                        <i class="fas fa-globe"></i> Sites vitrine
                    </a>
                    <a class="dropdown-item" href="logement_equipements.php">
                        <i class="fas fa-bed"></i> Equipements
                    </a>
                    <a class="dropdown-item" href="villes.php">
                        <i class="fas fa-city"></i> Villes & Recommandations
                    </a>
                    <a class="dropdown-item" href="templates.php">
                        <i class="fas fa-file-alt"></i> Templates SMS
                    </a>
                    <a class="dropdown-item" href="clients.php">
                        <i class="fas fa-address-book"></i> Carnet clients
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="users.php">
                        <i class="fas fa-users"></i> Utilisateurs
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Deconnexion
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>

<!-- Barre d'actions rapides / Breadcrumb -->
<?php if ($current_page !== 'dashboard.php' && $current_page !== 'login.php'): ?>
<div class="quick-actions">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i></a></li>
            <?php
            $titles = [
                'update_reservations.php' => 'Synchronisation iCal',
                'recus.php' => 'SMS Recus',
                'campaigns.php' => 'Campagnes SMS',
                'reservation_list.php' => 'Reservations du jour',
                'superhote_config.php' => 'Gestion des tarifs',
                'logements.php' => 'Logements',
                'sites.php' => 'Sites vitrine',
                'logement_equipements.php' => 'Equipements logements',
                'villes.php' => 'Villes & Recommandations',
                'templates.php' => 'Templates SMS',
                'reservation_details.php' => 'Detail reservation',
                'users.php' => 'Gestion des utilisateurs',
                'clients.php' => 'Carnet de clients',
                'search.php' => 'Recherche'
            ];
            if (isset($titles[$current_page])) {
                echo '<li class="breadcrumb-item active">' . $titles[$current_page] . '</li>';
            }
            ?>
        </ol>
    </nav>
</div>
<?php endif; ?>

<!-- Container principal -->
<div class="main-container">
