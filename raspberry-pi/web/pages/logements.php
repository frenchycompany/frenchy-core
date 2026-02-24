<?php
/**
 * Gestion des logements
 */
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/header_minimal.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

// Ajouter la colonne description si elle n'existe pas
try {
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN description TEXT NULL AFTER adresse");
} catch (PDOException $e) {
    // Colonne existe deja, ignorer
}

// Ajouter la colonne actif si elle n'existe pas
try {
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1 AFTER description");
} catch (PDOException $e) {
    // Colonne existe deja, ignorer
}

$feedback = '';

// Ajouter un nouveau logement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_logement'])) {
    validateCsrfToken();

    $nom_du_logement = trim($_POST['nom_du_logement'] ?? '');
    $ics_url = trim($_POST['ics_url'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($nom_du_logement)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le nom du logement est obligatoire</div>";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO liste_logements (nom_du_logement, ics_url, adresse, description)
                VALUES (:nom_du_logement, :ics_url, :adresse, :description)
            ");
            $stmt->execute([
                ':nom_du_logement' => $nom_du_logement,
                ':ics_url' => $ics_url ?: null,
                ':adresse' => $adresse ?: null,
                ':description' => $description ?: null
            ]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Logement ajouté avec succès</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Modifier un logement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_logement'])) {
    validateCsrfToken();

    $id = (int)$_POST['logement_id'];
    $nom_du_logement = trim($_POST['nom_du_logement'] ?? '');
    $ics_url = trim($_POST['ics_url'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($nom_du_logement)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le nom du logement est obligatoire</div>";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE liste_logements
                SET nom_du_logement = :nom_du_logement,
                    ics_url = :ics_url,
                    adresse = :adresse,
                    description = :description
                WHERE id = :id
            ");
            $stmt->execute([
                ':nom_du_logement' => $nom_du_logement,
                ':ics_url' => $ics_url ?: null,
                ':adresse' => $adresse ?: null,
                ':description' => $description ?: null,
                ':id' => $id
            ]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Logement mis à jour avec succès</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Activer/Desactiver un logement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_actif'])) {
    validateCsrfToken();

    $id = (int)$_POST['logement_id'];

    try {
        $stmt = $pdo->prepare("UPDATE liste_logements SET actif = NOT actif WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Verifier le nouveau statut
        $stmt = $pdo->prepare("SELECT actif, nom_du_logement FROM liste_logements WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        $status = $result['actif'] ? 'active' : 'desactive';
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Logement \"" . htmlspecialchars($result['nom_du_logement']) . "\" $status</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Supprimer un logement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logement'])) {
    validateCsrfToken();

    $id = (int)$_POST['logement_id'];

    try {
        // Vérifier si le logement a des réservations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservation WHERE logement_id = :id");
        $stmt->execute([':id' => $id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Impossible de supprimer ce logement car il a {$count} réservation(s) associée(s)</div>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM liste_logements WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Logement supprimé avec succès</div>";
        }
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Récupérer tous les logements avec le nombre de réservations
$logements = [];
try {
    $stmt = $pdo->query("
        SELECT l.*,
               COUNT(DISTINCT r.id) as nb_reservations
        FROM liste_logements l
        LEFT JOIN reservation r ON l.id = r.logement_id
        GROUP BY l.id
        ORDER BY l.nom_du_logement ASC
    ");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-home text-primary"></i> Gestion des logements
        </h1>
        <p class="lead text-muted">Gerez vos logements et leurs configurations de synchronisation</p>
    </div>
</div>

    <?= $feedback ?>

    <div class="row">
        <!-- Formulaire d'ajout -->
        <div class="col-md-4">
            <div class="card shadow-custom">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> Nouveau logement</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="form-group">
                            <label for="nom_du_logement"><i class="fas fa-tag"></i> Nom du logement *</label>
                            <input type="text" class="form-control" id="nom_du_logement" name="nom_du_logement" required placeholder="Ex: Appartement A">
                        </div>

                        <div class="form-group">
                            <label for="adresse"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2" placeholder="Adresse complète du logement"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="ics_url"><i class="fas fa-calendar-alt"></i> URL ICS (iCalendar)</label>
                            <input type="url" class="form-control" id="ics_url" name="ics_url" placeholder="https://www.airbnb.fr/calendar/ical/...">
                            <small class="form-text text-muted">
                                URL de synchronisation depuis Airbnb, Booking.com, etc.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="description"><i class="fas fa-comment"></i> Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Informations complémentaires"></textarea>
                        </div>

                        <button type="submit" name="add_logement" class="btn btn-success btn-block">
                            <i class="fas fa-plus"></i> Ajouter le logement
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info -->
            <div class="card shadow-custom mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Informations</h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>URL ICS :</strong><br>
                        Permet la synchronisation automatique des réservations depuis les plateformes de location.<br><br>

                        <strong>Airbnb :</strong><br>
                        Calendrier → Synchronisation → Exporter<br><br>

                        <strong>Booking.com :</strong><br>
                        Extranet → Calendrier → Synchronisation → Exporter
                    </small>
                </div>
            </div>
        </div>

        <!-- Liste des logements -->
        <div class="col-md-8">
            <div class="card shadow-custom">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Logements enregistrés (<?= count($logements) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($logements) === 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucun logement enregistré pour le moment.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Adresse</th>
                                        <th>ICS</th>
                                        <th>Statut</th>
                                        <th>Reservations</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logements as $logement): ?>
                                        <tr id="logement-<?= $logement['id'] ?>">
                                            <td><strong>#<?= $logement['id'] ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($logement['nom_du_logement']) ?></strong>
                                                <?php if ($logement['description']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($logement['description'], 0, 50)) ?><?= strlen($logement['description']) > 50 ? '...' : '' ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($logement['adresse']): ?>
                                                    <small><?= htmlspecialchars(substr($logement['adresse'], 0, 40)) ?><?= strlen($logement['adresse']) > 40 ? '...' : '' ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($logement['ics_url']): ?>
                                                    <span class="badge badge-success" title="<?= htmlspecialchars($logement['ics_url']) ?>">
                                                        <i class="fas fa-check"></i> Configure
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-times"></i> Non configure
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="logement_id" value="<?= $logement['id'] ?>">
                                                    <?php if (!empty($logement['actif'])): ?>
                                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-success" title="Cliquer pour desactiver">
                                                            <i class="fas fa-check-circle"></i> Actif
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-secondary" title="Cliquer pour activer">
                                                            <i class="fas fa-pause-circle"></i> Inactif
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= $logement['nb_reservations'] ?></span>
                                            </td>
                                            <td class="nowrap">
                                                <button type="button" class="btn btn-sm btn-warning"
                                                        onclick="editLogement(<?= htmlspecialchars(json_encode($logement)) ?>)"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <?php if ($logement['nb_reservations'] == 0): ?>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce logement ?')">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="logement_id" value="<?= $logement['id'] ?>">
                                                    <button type="submit" name="delete_logement" class="btn btn-sm btn-danger" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="Impossible de supprimer (<?= $logement['nb_reservations'] ?> réservations)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Modal de modification -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier le logement</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="logement_id" id="edit_id">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_nom_du_logement"><i class="fas fa-tag"></i> Nom du logement *</label>
                        <input type="text" class="form-control" id="edit_nom_du_logement" name="nom_du_logement" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_adresse"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                        <textarea class="form-control" id="edit_adresse" name="adresse" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit_ics_url"><i class="fas fa-calendar-alt"></i> URL ICS (iCalendar)</label>
                        <input type="url" class="form-control" id="edit_ics_url" name="ics_url">
                        <small class="form-text text-muted">
                            URL de synchronisation depuis Airbnb, Booking.com, etc.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="edit_description"><i class="fas fa-comment"></i> Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="update_logement" class="btn btn-warning">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLogement(logement) {
    document.getElementById('edit_id').value = logement.id;
    document.getElementById('edit_nom_du_logement').value = logement.nom_du_logement || '';
    document.getElementById('edit_adresse').value = logement.adresse || '';
    document.getElementById('edit_ics_url').value = logement.ics_url || '';
    document.getElementById('edit_description').value = logement.description || '';

    $('#editModal').modal('show');
}
</script>

<?php include '../includes/footer_minimal.php'; ?>
