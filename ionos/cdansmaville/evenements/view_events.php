<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: text/html; charset=UTF-8');

require 'db/connection.php'; // Connexion à la base de données

// Récupérer les événements depuis la base de données
$query = "SELECT * FROM structured_events ORDER BY date_debut DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Événements</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome (icônes) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 1.2rem;
        }
        .badge {
            font-size: 0.9rem;
        }
    </style>
<script>
function deleteEvent(eventId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
        fetch('delete_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `eventid=${eventId}`
        })
        .then(response => response.json()) // Décoder la réponse JSON
        .then(data => {
            if (data.success) {
                // Afficher un message de succès
                alert(data.message);

                // Recharger la page après 2 secondes
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                // Afficher un message d'erreur
                alert('Erreur : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur :', error);
            alert('Une erreur est survenue lors de la suppression.');
        });
    }
}
</script>
</head>
<body>
    <div class="container mt-4">
    
        <?php if (count($events) > 0): ?>
            <!-- Afficher les événements sous forme de cartes -->
            <?php foreach ($events as $event): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?= htmlspecialchars($event['titre'] ?? 'Événement sans titre') ?>
                            <span class="badge bg-primary">
                                <?= htmlspecialchars($event['prix'] ?? 'NC') ?>
                            </span>
                        </h5>
                        <p class="card-text">
                            📍 <strong>Lieu :</strong> <?= htmlspecialchars($event['nom_lieu'] ?? 'Non précisé') ?> - <?= htmlspecialchars($event['ville'] ?? '') ?><br>
                            🗓️ <strong>Date :</strong> <?= htmlspecialchars($event['date_debut']) ?> <?= ($event['date_fin'] && $event['date_fin'] !== $event['date_debut']) ? "au {$event['date_fin']}" : '' ?><br>
                            ⏰ <strong>Horaires :</strong> <?= htmlspecialchars($event['heure_debut'] ?? '') ?> - <?= htmlspecialchars($event['heure_fin'] ?? '') ?><br>
                            📞 <strong>Contact :</strong> <?= htmlspecialchars($event['contact_nom'] ?? 'Non disponible') ?>
                        </p>
                        <p class="card-text">
                            <?= nl2br(htmlspecialchars($event['description'] ?? 'Aucune description')) ?>
                        </p>

                        <!-- Bouton de suppression -->
                        <form method="POST" action="delete_event.php" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?');">
                            <input type="hidden" name="eventid" value="<?= $event['id'] ?>">
                            <button type="button" class="btn btn-danger" onclick="deleteEvent(<?= $event['id'] ?>)">
                                <i class="fa-solid fa-trash"></i> Supprimer l'événement
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                Aucun événement trouvé.
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
