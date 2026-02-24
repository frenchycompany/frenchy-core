<?php
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require 'db/connection.php'; // Connexion à la base de données

// Récupérer tous les événements
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
    <title>Administration des Événements</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4 text-center">Administration des Événements</h1>
    
    <!-- Bouton Ajouter un événement -->
    <div class="text-end mb-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="fa fa-plus"></i> Ajouter un événement
        </button>
    </div>

    <!-- Tableau des événements -->
    <div class="table-responsive">
        <table id="eventsTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Date Début</th>
                    <th>Date Fin</th>
                    <th>Lieu</th>
                    <th>Ville</th>
                    <th>Prix</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['titre'] ?? 'Sans titre') ?></td>
                        <td><?= htmlspecialchars($event['date_debut'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($event['date_fin'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($event['nom_lieu'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($event['ville'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($event['prix'] ?? 'NC') ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm editBtn" data-id="<?= $event['id'] ?>">
                                <i class="fa fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= $event['id'] ?>">
                                <i class="fa fa-trash"></i> Supprimer
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modale Ajouter un événement -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventLabel">Ajouter un événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEventForm">
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="titre" name="titre" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_debut" class="form-label">Date Début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_fin" class="form-label">Date Fin</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin">
                    </div>
                    <div class="mb-3">
                        <label for="nom_lieu" class="form-label">Lieu</label>
                        <input type="text" class="form-control" id="nom_lieu" name="nom_lieu">
                    </div>
                    <div class="mb-3">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville">
                    </div>
                    <div class="mb-3">
                        <label for="prix" class="form-label">Prix</label>
                        <input type="text" class="form-control" id="prix" name="prix">
                    </div>
                    <button type="submit" class="btn btn-success">Ajouter</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Modale Modifier un événement -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventLabel">Modifier un événement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEventForm">
                    <!-- Champ caché pour l'ID -->
                    <input type="hidden" id="edit_event_id" name="id">

                    <!-- Titre -->
                    <div class="mb-3">
                        <label for="edit_titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="edit_titre" name="titre" required>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <!-- Dates -->
                    <div class="mb-3">
                        <label for="edit_date_debut" class="form-label">Date Début</label>
                        <input type="date" class="form-control" id="edit_date_debut" name="date_debut" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_date_fin" class="form-label">Date Fin</label>
                        <input type="date" class="form-control" id="edit_date_fin" name="date_fin">
                    </div>

                    <!-- Heures -->
                    <div class="mb-3">
                        <label for="edit_heure_debut" class="form-label">Heure Début</label>
                        <input type="time" class="form-control" id="edit_heure_debut" name="heure_debut">
                    </div>
                    <div class="mb-3">
                        <label for="edit_heure_fin" class="form-label">Heure Fin</label>
                        <input type="time" class="form-control" id="edit_heure_fin" name="heure_fin">
                    </div>

                    <!-- Lieu -->
                    <div class="mb-3">
                        <label for="edit_nom_lieu" class="form-label">Nom du Lieu</label>
                        <input type="text" class="form-control" id="edit_nom_lieu" name="nom_lieu">
                    </div>
                    <div class="mb-3">
                        <label for="edit_adresse_lieu" class="form-label">Adresse du Lieu</label>
                        <input type="text" class="form-control" id="edit_adresse_lieu" name="adresse_lieu">
                    </div>
                    <div class="mb-3">
                        <label for="edit_ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="edit_ville" name="ville">
                    </div>
                    <div class="mb-3">
                        <label for="edit_code_postal" class="form-label">Code Postal</label>
                        <input type="text" class="form-control" id="edit_code_postal" name="code_postal">
                    </div>

                    <!-- Contact -->
                    <div class="mb-3">
                        <label for="edit_contact_nom" class="form-label">Contact Nom</label>
                        <input type="text" class="form-control" id="edit_contact_nom" name="contact_nom">
                    </div>
                    <div class="mb-3">
                        <label for="edit_contact_telephone" class="form-label">Contact Téléphone</label>
                        <input type="text" class="form-control" id="edit_contact_telephone" name="contact_telephone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="edit_contact_email" name="contact_email">
                    </div>

                    <!-- Site Web -->
                    <div class="mb-3">
                        <label for="edit_site_web" class="form-label">Site Web</label>
                        <input type="text" class="form-control" id="edit_site_web" name="site_web">
                    </div>

                    <!-- Prix -->
                    <div class="mb-3">
                        <label for="edit_prix" class="form-label">Prix</label>
                        <input type="text" class="form-control" id="edit_prix" name="prix">
                    </div>

                    <!-- Tags -->
                    <div class="mb-3">
                        <label for="edit_tags" class="form-label">Tags</label>
                        <input type="text" class="form-control" id="edit_tags" name="tags" placeholder="Séparés par des virgules">
                    </div>

                    <!-- Source Texte -->
                    <div class="mb-3">
                        <label for="edit_source_texte" class="form-label">Source Texte</label>
                        <textarea class="form-control" id="edit_source_texte" name="source_texte" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </form>
            </div>
        </div>
    </div>
</div>



<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $('#eventsTable').DataTable();

        // Gestion des suppressions
        $('.deleteBtn').on('click', function() {
            const eventId = $(this).data('id');
            if (confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
                fetch('delete_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `eventid=${eventId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Erreur : ' + data.message);
                    }
                })
                .catch(error => console.error('Erreur :', error));
            }
        });

        // Soumission du formulaire Ajouter
        $('#addEventForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            fetch('add_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Événement ajouté avec succès.');
                    window.location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => console.error('Erreur :', error));
        });
    });

    $(document).ready(function() {
    $('#eventsTable').DataTable();

    // Suppression d'un événement
    $('.deleteBtn').on('click', function() {
        const eventId = $(this).data('id');
        if (confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')) {
            fetch('delete_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `eventid=${eventId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Événement supprimé avec succès.');
                    window.location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => console.error('Erreur :', error));
        }
    });

    // Remplir les champs dans le formulaire Modifier
    $('.editBtn').on('click', function() {
        const eventId = $(this).data('id');
        fetch(`get_event.php?id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const event = data.event;
                    $('#edit_event_id').val(event.id);
                $('#edit_titre').val(event.titre);
                $('#edit_description').val(event.description);
                $('#edit_date_debut').val(event.date_debut);
                $('#edit_date_fin').val(event.date_fin);
                $('#edit_heure_debut').val(event.heure_debut);
                $('#edit_heure_fin').val(event.heure_fin);
                $('#edit_nom_lieu').val(event.nom_lieu);
                $('#edit_adresse_lieu').val(event.adresse_lieu);
                $('#edit_ville').val(event.ville);
                $('#edit_code_postal').val(event.code_postal);
                $('#edit_contact_nom').val(event.contact_nom);
                $('#edit_contact_telephone').val(event.contact_telephone);
                $('#edit_contact_email').val(event.contact_email);
                $('#edit_site_web').val(event.site_web);
                $('#edit_prix').val(event.prix);
                $('#edit_tags').val(event.tags);
                $('#edit_source_texte').val(event.source_texte);

                    $('#editEventModal').modal('show');
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => console.error('Erreur :', error));
    });

   // Soumission du formulaire Modifier
$('#editEventForm').on('submit', function (e) {
    e.preventDefault();
    const formData = $(this).serialize();

    fetch('edit_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            $('#editEventModal').modal('hide');
            window.location.reload();
        } else {
            alert('Erreur : ' + data.message);
        }
    })
    .catch(error => console.error('Erreur :', error));
});


    // Soumission du formulaire Ajouter
    $('#addEventForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        fetch('add_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Événement ajouté avec succès.');
                window.location.reload();
            } else {
                alert('Erreur : ' + data.message);
            }
        })
        .catch(error => console.error('Erreur :', error));
    });
});



</script>
</body>
</html>
