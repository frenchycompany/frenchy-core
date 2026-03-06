<?php
/**
 * Gestion des automatisations personnalisées
 */
require_once __DIR__ . '/../includes/error_handler.php';
// DB loaded via config.php
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
// header loaded via menu.php
// csrf loaded via config.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

$feedback = '';

// Créer la table si elle n'existe pas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sms_automations` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nom` varchar(100) NOT NULL,
          `description` text,
          `actif` tinyint(1) DEFAULT 1,
          `declencheur_type` enum('date_arrivee','date_depart','date_reservation') NOT NULL,
          `declencheur_jours` int(11) DEFAULT 0,
          `template_name` varchar(50) NOT NULL,
          `condition_statut` varchar(50) DEFAULT 'confirmée',
          `flag_field` varchar(50) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (PDOException $e) {
    // Table existe déjà
}

// Ajouter la colonne logement_id si elle n'existe pas
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM sms_automations LIKE 'logement_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE `sms_automations`
            ADD COLUMN `logement_id` int(11) DEFAULT NULL COMMENT 'Si défini, automatisation limitée à ce logement uniquement',
            ADD KEY `idx_logement` (`logement_id`)
        ");
    }
} catch (PDOException $e) {
    // Colonne existe déjà ou erreur
}

// Ajouter les colonnes custom_sent si elles n'existent pas
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM reservation LIKE 'custom1_sent'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE `reservation`
            ADD COLUMN `custom1_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom2_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom3_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom4_sent` tinyint(1) DEFAULT 0,
            ADD COLUMN `custom5_sent` tinyint(1) DEFAULT 0
        ");
    }
} catch (PDOException $e) {
    // Colonnes existent déjà ou erreur
}

// Ajouter une nouvelle automatisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_automation'])) {
    validateCsrfToken();

    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $declencheur_type = $_POST['declencheur_type'] ?? 'date_arrivee';
    $declencheur_jours = (int)($_POST['declencheur_jours'] ?? 0);
    $template_name = $_POST['template_name'] ?? '';
    $condition_statut = $_POST['condition_statut'] ?? 'confirmée';
    $flag_field = $_POST['flag_field'] ?? 'custom1_sent';
    $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
    $actif = isset($_POST['actif']) ? 1 : 0;

    if (empty($nom) || empty($template_name)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le nom et le template sont obligatoires</div>";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sms_automations (nom, description, actif, declencheur_type, declencheur_jours, template_name, condition_statut, flag_field, logement_id)
                VALUES (:nom, :description, :actif, :declencheur_type, :declencheur_jours, :template_name, :condition_statut, :flag_field, :logement_id)
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':actif' => $actif,
                ':declencheur_type' => $declencheur_type,
                ':declencheur_jours' => $declencheur_jours,
                ':template_name' => $template_name,
                ':condition_statut' => $condition_statut,
                ':flag_field' => $flag_field,
                ':logement_id' => $logement_id
            ]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Automatisation créée avec succès</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Modifier une automatisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_automation'])) {
    validateCsrfToken();

    $id = (int)$_POST['automation_id'];
    $nom = $_POST['nom'] ?? '';
    $description = $_POST['description'] ?? '';
    $declencheur_type = $_POST['declencheur_type'] ?? 'date_arrivee';
    $declencheur_jours = (int)($_POST['declencheur_jours'] ?? 0);
    $template_name = $_POST['template_name'] ?? '';
    $condition_statut = $_POST['condition_statut'] ?? 'confirmée';
    $flag_field = $_POST['flag_field'] ?? 'custom1_sent';
    $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
    $actif = isset($_POST['actif']) ? 1 : 0;

    if (empty($nom) || empty($template_name)) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Le nom et le template sont obligatoires</div>";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE sms_automations
                SET nom = :nom,
                    description = :description,
                    actif = :actif,
                    declencheur_type = :declencheur_type,
                    declencheur_jours = :declencheur_jours,
                    template_name = :template_name,
                    condition_statut = :condition_statut,
                    flag_field = :flag_field,
                    logement_id = :logement_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':actif' => $actif,
                ':declencheur_type' => $declencheur_type,
                ':declencheur_jours' => $declencheur_jours,
                ':template_name' => $template_name,
                ':condition_statut' => $condition_statut,
                ':flag_field' => $flag_field,
                ':logement_id' => $logement_id,
                ':id' => $id
            ]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Automatisation modifiée avec succès</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Activer/Désactiver une automatisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_automation'])) {
    validateCsrfToken();

    $id = (int)$_POST['automation_id'];
    $actif = isset($_POST['actif']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE sms_automations SET actif = :actif WHERE id = :id");
        $stmt->execute([':actif' => $actif, ':id' => $id]);
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Automatisation mise à jour</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Supprimer une automatisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_automation'])) {
    validateCsrfToken();

    $id = (int)$_POST['automation_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM sms_automations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Automatisation supprimée</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Récupérer toutes les automatisations avec les noms de logements
$automations = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, l.nom_du_logement
        FROM sms_automations a
        LEFT JOIN liste_logements l ON a.logement_id = l.id
        ORDER BY a.created_at DESC
    ");
    $automations = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}

// Récupérer les templates disponibles
$templates = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT template_type FROM sms_templates ORDER BY template_type");
    $templates = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer
}

// Récupérer la liste des logements
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}

// Flags disponibles
$available_flags = ['custom1_sent', 'custom2_sent', 'custom3_sent', 'custom4_sent', 'custom5_sent'];
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="text-gradient-primary">
                <i class="fas fa-plus-circle"></i> Automatisations personnalisées
            </h1>
            <p class="text-muted">Créez vos propres règles d'envoi automatique de SMS</p>
        </div>
    </div>

    <?= $feedback ?>

    <div class="row">
        <!-- Formulaire d'ajout -->
        <div class="col-md-5">
            <div class="card shadow-custom">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> Nouvelle automatisation</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="form-group">
                            <label for="nom"><i class="fas fa-tag"></i> Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required placeholder="Ex: Rappel 7j avant">
                        </div>

                        <div class="form-group">
                            <label for="description"><i class="fas fa-comment"></i> Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Description de cette automatisation"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="declencheur_type"><i class="fas fa-calendar"></i> Déclencheur *</label>
                            <select class="form-control" id="declencheur_type" name="declencheur_type" required>
                                <option value="date_arrivee">Date d'arrivée</option>
                                <option value="date_depart">Date de départ</option>
                                <option value="date_reservation">Date de réservation</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="declencheur_jours"><i class="fas fa-calendar-day"></i> Nombre de jours</label>
                            <input type="number" class="form-control" id="declencheur_jours" name="declencheur_jours" value="0">
                            <small class="form-text text-muted">
                                • Négatif = avant (ex: -7 = 7 jours avant)<br>
                                • Positif = après (ex: +1 = 1 jour après)<br>
                                • 0 = le jour même
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="template_name"><i class="fas fa-file-alt"></i> Template SMS *</label>
                            <select class="form-control" id="template_name" name="template_name" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="checkout">Checkout (départ)</option>
                                <option value="accueil">Accueil (arrivée)</option>
                                <option value="preparation">Préparation</option>
                                <option value="remerciement">Remerciement</option>
                                <option value="satisfaction">Satisfaction</option>
                                <option value="rappel_7j">Rappel 7 jours</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?= htmlspecialchars($tpl) ?>"><?= htmlspecialchars($tpl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Le template doit exister dans la table sms_templates
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="condition_statut"><i class="fas fa-filter"></i> Statut requis</label>
                            <select class="form-control" id="condition_statut" name="condition_statut">
                                <option value="confirmée">Confirmée</option>
                                <option value="annulée">Annulée</option>
                                <option value="">Tous</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="flag_field"><i class="fas fa-flag"></i> Champ flag</label>
                            <select class="form-control" id="flag_field" name="flag_field">
                                <?php foreach ($available_flags as $flag): ?>
                                    <option value="<?= $flag ?>"><?= $flag ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Champ utilisé pour marquer que le SMS a été envoyé
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="logement_id"><i class="fas fa-home"></i> Logement (optionnel)</label>
                            <select class="form-control" id="logement_id" name="logement_id">
                                <option value="">Tous les logements</option>
                                <?php foreach ($logements as $logement): ?>
                                    <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Si défini, l'automatisation ne s'appliquera qu'à ce logement
                            </small>
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="actif" name="actif" checked>
                            <label class="form-check-label" for="actif">
                                <strong>Activer immédiatement</strong>
                            </label>
                        </div>

                        <button type="submit" name="add_automation" class="btn btn-success btn-block">
                            <i class="fas fa-plus"></i> Créer l'automatisation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Aide -->
            <div class="card shadow-custom mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-question-circle"></i> Exemples</h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>Rappel 7j avant :</strong><br>
                        • Déclencheur : Date d'arrivée<br>
                        • Jours : -7<br>
                        • Template : rappel_7j<br><br>

                        <strong>Remerciement après départ :</strong><br>
                        • Déclencheur : Date de départ<br>
                        • Jours : +1<br>
                        • Template : remerciement<br><br>

                        <strong>Satisfaction mi-séjour :</strong><br>
                        • Déclencheur : Date d'arrivée<br>
                        • Jours : +3<br>
                        • Template : satisfaction
                    </small>
                </div>
            </div>
        </div>

        <!-- Liste des automatisations -->
        <div class="col-md-7">
            <div class="card shadow-custom">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Automatisations existantes (<?= count($automations) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($automations) === 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Aucune automatisation personnalisée pour le moment.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Nom & Description</th>
                                        <th>Déclencheur</th>
                                        <th>Template / Flag</th>
                                        <th>Logement</th>
                                        <th>Statut</th>
                                        <th style="min-width: 220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($automations as $auto): ?>
                                        <tr id="automation-<?= $auto['id'] ?>" style="border-left: 4px solid <?= (int)$auto['actif'] === 1 ? '#28a745' : '#6c757d' ?>;">
                                            <td>
                                                <strong class="text-primary"><?= htmlspecialchars($auto['nom']) ?></strong>
                                                <?php if ($auto['description']): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($auto['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $type_labels = [
                                                    'date_arrivee' => 'Arrivée',
                                                    'date_depart' => 'Départ',
                                                    'date_reservation' => 'Réservation'
                                                ];
                                                $jours = (int)$auto['declencheur_jours'];
                                                $jours_text = '';
                                                if ($jours < 0) {
                                                    $jours_text = abs($jours) . 'j avant';
                                                } elseif ($jours > 0) {
                                                    $jours_text = $jours . 'j après';
                                                } else {
                                                    $jours_text = 'Le jour même';
                                                }
                                                ?>
                                                <span class="badge text-bg-info badge-pill">
                                                    <?= $type_labels[$auto['declencheur_type']] ?? $auto['declencheur_type'] ?>
                                                </span><br>
                                                <small class="font-weight-bold"><?= $jours_text ?></small>
                                            </td>
                                            <td>
                                                <code style="font-size: 13px;"><?= htmlspecialchars($auto['template_name']) ?></code><br>
                                                <small class="text-muted"><?= htmlspecialchars($auto['flag_field']) ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($auto['nom_du_logement'])): ?>
                                                    <span class="badge text-bg-secondary badge-pill">
                                                        <i class="fas fa-home"></i> <?= htmlspecialchars($auto['nom_du_logement']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-light badge-pill">Tous</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$auto['actif'] === 1): ?>
                                                    <span class="badge text-bg-success badge-pill px-3 py-2">
                                                        <i class="fas fa-check-circle"></i> ACTIF
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary badge-pill px-3 py-2">
                                                        <i class="fas fa-pause-circle"></i> INACTIF
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button"
                                                        class="btn btn-warning btn-sm mb-1"
                                                        style="min-width: 90px;"
                                                        onclick="editAutomation(<?= htmlspecialchars(json_encode($auto)) ?>)"
                                                        title="Modifier l'automatisation">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <form method="POST" style="display:inline" onsubmit="return confirm('⚠️ ATTENTION !\n\nVoulez-vous vraiment supprimer cette automatisation ?\n\n📝 Nom: <?= htmlspecialchars($auto['nom']) ?>\n\nCette action est irréversible.')">
                                                    <?php echoCsrfField(); ?>
                                                    <input type="hidden" name="automation_id" value="<?= $auto['id'] ?>">
                                                    <button type="submit" name="delete_automation" class="btn btn-danger btn-sm mb-1" style="min-width: 90px;" title="Supprimer définitivement">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info sur les automatisations natives -->
            <div class="card shadow-custom mt-4">
                <div class="card-header bg-warning text-white">
                    <h6 class="mb-0"><i class="fas fa-robot"></i> Automatisations natives</h6>
                </div>
                <div class="card-body">
                    <p>Les automatisations natives suivantes sont configurées dans <a href="automation_config.php">Automatisation</a> :</p>
                    <ul>
                        <li><strong>Check-out</strong> : SMS le jour du départ (template: checkout, flag: dep_sent)</li>
                        <li><strong>Check-in</strong> : SMS le jour de l'arrivée (template: accueil, flag: j1_sent)</li>
                        <li><strong>Préparation</strong> : SMS 4 jours avant l'arrivée (template: preparation, flag: start_sent)</li>
                    </ul>
                    <p class="mb-0"><small class="text-muted">Les automatisations personnalisées viennent en complément.</small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de modification -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier l'automatisation</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="automation_id" id="edit_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_nom"><i class="fas fa-tag"></i> Nom *</label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_template_name"><i class="fas fa-file-alt"></i> Template SMS *</label>
                                <select class="form-control" id="edit_template_name" name="template_name" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="checkout">Checkout (départ)</option>
                                    <option value="accueil">Accueil (arrivée)</option>
                                    <option value="preparation">Préparation</option>
                                    <option value="remerciement">Remerciement</option>
                                    <option value="satisfaction">Satisfaction</option>
                                    <option value="rappel_7j">Rappel 7 jours</option>
                                    <?php foreach ($templates as $tpl): ?>
                                        <option value="<?= htmlspecialchars($tpl) ?>"><?= htmlspecialchars($tpl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_description"><i class="fas fa-comment"></i> Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_declencheur_type"><i class="fas fa-calendar"></i> Déclencheur *</label>
                                <select class="form-control" id="edit_declencheur_type" name="declencheur_type" required>
                                    <option value="date_arrivee">Date d'arrivée</option>
                                    <option value="date_depart">Date de départ</option>
                                    <option value="date_reservation">Date de réservation</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_declencheur_jours"><i class="fas fa-calendar-day"></i> Nombre de jours</label>
                                <input type="number" class="form-control" id="edit_declencheur_jours" name="declencheur_jours" value="0">
                                <small class="form-text text-muted">
                                    Négatif = avant • Positif = après • 0 = le jour même
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_condition_statut"><i class="fas fa-filter"></i> Statut requis</label>
                                <select class="form-control" id="edit_condition_statut" name="condition_statut">
                                    <option value="confirmée">Confirmée</option>
                                    <option value="annulée">Annulée</option>
                                    <option value="">Tous</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_flag_field"><i class="fas fa-flag"></i> Champ flag</label>
                                <select class="form-control" id="edit_flag_field" name="flag_field">
                                    <?php foreach ($available_flags as $flag): ?>
                                        <option value="<?= $flag ?>"><?= $flag ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_logement_id"><i class="fas fa-home"></i> Logement</label>
                                <select class="form-control" id="edit_logement_id" name="logement_id">
                                    <option value="">Tous les logements</option>
                                    <?php foreach ($logements as $logement): ?>
                                        <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="edit_actif" name="actif">
                        <label class="form-check-label" for="edit_actif">
                            <strong>Automatisation active</strong>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="update_automation" class="btn btn-warning">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAutomation(automation) {
    // Remplir le formulaire avec les données de l'automatisation
    document.getElementById('edit_id').value = automation.id;
    document.getElementById('edit_nom').value = automation.nom || '';
    document.getElementById('edit_description').value = automation.description || '';
    document.getElementById('edit_declencheur_type').value = automation.declencheur_type || 'date_arrivee';
    document.getElementById('edit_declencheur_jours').value = automation.declencheur_jours || 0;
    document.getElementById('edit_template_name').value = automation.template_name || '';
    document.getElementById('edit_condition_statut').value = automation.condition_statut || '';
    document.getElementById('edit_flag_field').value = automation.flag_field || 'custom1_sent';
    document.getElementById('edit_logement_id').value = automation.logement_id || '';
    document.getElementById('edit_actif').checked = parseInt(automation.actif) === 1;

    // Ouvrir le modal
    $('#editModal').modal('show');
}
</script>


