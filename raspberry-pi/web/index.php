<?php
// Inclure la connexion à la base de données
require_once 'includes/db.php';
require_once 'includes/header_root.php';
?>

<div class="container">
    <!-- En-tête de bienvenue -->
    <div class="text-center mt-5 mb-5">
        <h1 class="display-4 font-weight-bold">
            <i class="fas fa-comments text-gradient-primary"></i>
            Interface de gestion SMS
        </h1>
        <p class="lead text-muted mt-3">
            Gérez vos communications SMS, réservations et scénarios automatiques en toute simplicité
        </p>
        <hr class="my-4" style="max-width: 200px; border-top: 3px solid; border-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%) 1;">
    </div>

    <!-- Cartes de navigation principales -->
    <div class="row mt-5 justify-content-center">
        <!-- SMS reçus -->
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card card-gradient-primary h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-inbox fa-4x icon-hover"></i>
                    </div>
                    <h4 class="card-title font-weight-bold">SMS reçus</h4>
                    <p class="card-text">
                        Consultez et gérez tous les messages entrants de vos clients par logement, date ou contact.
                    </p>
                    <a href="pages/recus.php" class="btn btn-light btn-lg mt-3">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Envoyer un SMS -->
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card card-gradient-success h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-paper-plane fa-4x icon-hover"></i>
                    </div>
                    <h4 class="card-title font-weight-bold">Envoyer un SMS</h4>
                    <p class="card-text">
                        Rédigez et envoyez rapidement un SMS à un numéro ou un client spécifique.
                    </p>
                    <a href="pages/envoyer.php" class="btn btn-light btn-lg mt-3">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Réservations -->
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card card-gradient-info h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-calendar-check fa-4x icon-hover"></i>
                    </div>
                    <h4 class="card-title font-weight-bold">Réservations</h4>
                    <p class="card-text">
                        Accédez à la liste complète des réservations avec détails clients et historique.
                    </p>
                    <a href="pages/reservation_list.php" class="btn btn-light btn-lg mt-3">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Scénarios automatiques -->
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="card card-gradient-warning h-100">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-project-diagram fa-4x icon-hover"></i>
                    </div>
                    <h4 class="card-title font-weight-bold">Scénarios automatiques</h4>
                    <p class="card-text">
                        Configurez et gérez vos séquences automatisées H+2, H+8 et scénarios personnalisés.
                    </p>
                    <a href="pages/scenario.php" class="btn btn-light btn-lg mt-3">
                        <i class="fas fa-arrow-right"></i> Accéder
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Section supplémentaire : Accès rapide -->
    <div class="row mt-5 mb-5">
        <div class="col-12">
            <div class="card shadow-custom">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-bolt text-warning"></i> Accès rapides
                    </h3>
                    <div class="row text-center">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="pages/dashboard.php" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="pages/conversation.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-comment-dots"></i> Conversations
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="pages/travel_accounts.php" class="btn btn-outline-success btn-block">
                                <i class="fas fa-building"></i> Comptes
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="pages/ical_sync_api.php" class="btn btn-outline-warning btn-block">
                                <i class="fas fa-sync"></i> Sync iCal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer_root.php';
?>
