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
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Barre de navigation moderne -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
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
                    <a class="nav-link" href="pages/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <!-- Lien vers la liste des réservations -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownReservations" role="button" data-toggle="dropdown">
                        <i class="fas fa-calendar-check"></i> Réservations
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="pages/reservations_listing.php">
                            <i class="fas fa-list"></i> Listing complet
                        </a>
                        <a class="dropdown-item" href="pages/reservation_list.php">
                            <i class="fas fa-sms"></i> Vue SMS automatiques
                        </a>
                    </div>
                </li>
                <!-- Lien vers la page de gestion des scénarios -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/scenario.php">
                        <i class="fas fa-project-diagram"></i> Scénarios
                    </a>
                </li>
                <!-- Lien vers les SMS reçus -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/recus.php">
                        <i class="fas fa-inbox"></i> SMS reçus
                    </a>
                </li>
                <!-- Lien vers l'envoi de SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/envoyer.php">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </a>
                </li>
                <!-- Lien vers les campagnes SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/campaigns.php">
                        <i class="fas fa-bullhorn"></i> Campagnes
                    </a>
                </li>
                <!-- Lien vers les templates SMS -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/templates.php">
                        <i class="fas fa-file-alt"></i> Templates
                    </a>
                </li>
                <!-- Lien vers la synchronisation des réservations -->
                <li class="nav-item">
                    <a class="nav-link" href="pages/update_reservations.php">
                        <i class="fas fa-sync-alt"></i> Sync iCal
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
