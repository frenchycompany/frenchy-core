<?php
require 'db/connection.php'; // Connexion à la base de données
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Événements</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center">Gestion des Événements</h1>
        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-primary" id="addEventBtn">Ajouter un événement</button>
            <input type="text" id="searchBar" class="form-control w-50" placeholder="Rechercher un événement...">
        </div>

        <table id="eventsTable" class="display">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Date Début</th>
                    <th>Date Fin</th>
                    <th>Lieu</th>
                    <th>Ville</th>
                    <th>Tags</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT * FROM structured_events ORDER BY date_debut DESC");
                while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>
                        <td>{$event['titre']}</td>
                        <td>{$event['date_debut']}</td>
                        <td>{$event['date_fin']}</td>
                        <td>{$event['nom_lieu']}</td>
                        <td>{$event['ville']}</td>
                        <td>{$event['tags']}</td>
                        <td>
                            <button class='btn btn-sm btn-warning editBtn' data-id='{$event['id']}'>Modifier</button>
                            <button class='btn btn-sm btn-danger deleteBtn' data-id='{$event['id']}'>Supprimer</button>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Modal pour l'ajout/la modification -->
    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Ajouter / Modifier un événement</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <input type="hidden" id="eventId" name="eventid">
                        <div class="form-group">
                            <label for="titre">Titre</label>
                            <input type="text" class="form-control" id="titre" name="titre" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="date_debut">Date Début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                        </div>
                        <div class="form-group">
                            <label for="date_fin">Date Fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin">
                        </div>
                        <div class="form-group">
                            <label for="nom_lieu">Lieu</label>
                            <input type="text" class="form-control" id="nom_lieu" name="nom_lieu">
                        </div>
                        <div class="form-group">
                            <label for="ville">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville">
                        </div>
                        <div class="form-group">
                            <label for="tags">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags" placeholder="Séparez les tags par des virgules">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveEventBtn">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $('#eventsTable').DataTable();

            // Ajouter un événement
            $('#addEventBtn').on('click', function () {
                $('#eventForm')[0].reset();
                $('#eventId').val('');
                $('#eventModalLabel').text('Ajouter un événement');
                $('#eventModal').modal('show');
            });

            // Modifier un événement
            $('.editBtn').on('click', function () {
                const eventId = $(this).data('id');
                $.get('get_event.php', { eventid: eventId }, function (response) {
                    if (response.success) {
                        const event = response.data;
                        $('#eventId').val(event.id);
                        $('#titre').val(event.titre);
                        $('#description').val(event.description);
                        $('#date_debut').val(event.date_debut);
                        $('#date_fin').val(event.date_fin);
                        $('#nom_lieu').val(event.nom_lieu);
                        $('#ville').val(event.ville);
                        $('#tags').val(event.tags);
                        $('#eventModalLabel').text('Modifier un événement');
                        $('#eventModal').modal('show');
                    } else {
                        alert('Erreur : ' + response.message);
                    }
                }, 'json');
            });

            // Enregistrer un événement (ajout ou modification)
            $('#saveEventBtn').on('click', function () {
                const formData = $('#eventForm').serialize();
                $.post('save_event.php', formData, function (response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload(); // Recharge la page pour mettre à jour les données
                    } else {
                        alert('Erreur : ' + response.message);
                    }
                }, 'json');
            });

            // Supprimer un événement
            $('.deleteBtn').on('click', function () {
                const eventId = $(this).data('id');
                if (confirm('Voulez-vous vraiment supprimer cet événement ?')) {
                    $.post('delete_event.php', { eventid: eventId }, function (response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload(); // Recharge la page pour mettre à jour les données
                        } else {
                            alert('Erreur : ' + response.message);
                        }
                    }, 'json');
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
