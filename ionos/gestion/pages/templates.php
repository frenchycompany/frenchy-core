<?php
/**
 * Gestion des templates SMS - Version 2.0
 * Templates génériques et par logement
 */
require_once __DIR__ . '/../includes/error_handler.php';
// header loaded via menu.php
// DB loaded via config.php
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();
// csrf loaded via config.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

$feedback = '';

// Tables requises : voir db/install_tables.php

// S'assurer que la colonne description existe dans sms_templates
try {
    $pdo->exec("ALTER TABLE sms_templates ADD COLUMN description TEXT NULL AFTER template");
} catch (PDOException $e) {
    // Colonne existe deja
}

// S'assurer que la colonne campaign a une valeur par défaut
try {
    $pdo->exec("ALTER TABLE sms_templates MODIFY COLUMN campaign VARCHAR(50) DEFAULT 'default'");
} catch (PDOException $e) {
    // Colonne n'existe pas ou déjà modifiée
}

// --- Traitement des actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_default_template'])) {
        validateCsrfToken();

        $name = trim($_POST['template_name'] ?? '');
        $template = trim($_POST['template_content'] ?? '');

        if (!empty($name) && !empty($template)) {
            try {
                $stmt = $pdo->prepare("UPDATE sms_templates SET template = :template WHERE name = :name");
                $stmt->execute([':template' => $template, ':name' => $name]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Template mis à jour avec succès</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['update_logement_template'])) {
        validateCsrfToken();

        $logement_id = (int)($_POST['logement_id'] ?? 0);
        $type_message = trim($_POST['type_message'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $actif = isset($_POST['actif']) ? 1 : 0;

        if ($logement_id > 0 && !empty($type_message) && !empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_logement_templates (logement_id, type_message, message, actif)
                    VALUES (:logement_id, :type_message, :message, :actif)
                    ON DUPLICATE KEY UPDATE message = :message, actif = :actif
                ");
                $stmt->execute([
                    ':logement_id' => $logement_id,
                    ':type_message' => $type_message,
                    ':message' => $message,
                    ':actif' => $actif
                ]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Template du logement sauvegardé avec succès</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['delete_logement_template'])) {
        validateCsrfToken();

        $template_id = (int)($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM sms_logement_templates WHERE id = :id");
                $stmt->execute([':id' => $template_id]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Template supprimé avec succès</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['add_new_default_template'])) {
        validateCsrfToken();

        $name = trim($_POST['new_template_name'] ?? '');
        $template = trim($_POST['new_template_content'] ?? '');
        $description = trim($_POST['new_template_description'] ?? '');

        if (!empty($name) && !empty($template)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_templates (name, template, description)
                    VALUES (:name, :template, :description)
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':template' => $template,
                    ':description' => $description
                ]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Nouveau template créé avec succès</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Déterminer l'onglet actif
$active_tab = $_GET['tab'] ?? 'generiques';

// --- Récupérer les statistiques ---
$stats = [
    'templates_generiques' => 0,
    'templates_logements' => 0,
    'templates_actifs' => 0,
    'logements_configures' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_templates");
    $stats['templates_generiques'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_logement_templates");
    $stats['templates_logements'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_logement_templates WHERE actif = 1");
    $stats['templates_actifs'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT logement_id) FROM sms_logement_templates");
    $stats['logements_configures'] = $stmt->fetchColumn();
} catch (PDOException $e) { error_log('templates.php: ' . $e->getMessage()); }

// --- Récupérer les données ---
// Templates génériques
$default_templates = [];
try {
    $stmt = $pdo->query("SELECT * FROM sms_templates ORDER BY name");
    $default_templates = $stmt->fetchAll();
} catch (PDOException $e) { error_log('templates.php: ' . $e->getMessage()); }

// Logements (actifs uniquement)
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) { error_log('templates.php: ' . $e->getMessage()); }

// Templates par logement
$logement_templates = [];
try {
    $stmt = $pdo->query("
        SELECT lt.*, l.nom_du_logement
        FROM sms_logement_templates lt
        LEFT JOIN liste_logements l ON lt.logement_id = l.id
        ORDER BY l.nom_du_logement, lt.type_message
    ");
    $logement_templates = $stmt->fetchAll();
} catch (PDOException $e) { error_log('templates.php: ' . $e->getMessage()); }

// Grouper par logement
$templates_by_logement = [];
foreach ($logement_templates as $template) {
    $logement_id = $template['logement_id'];
    if (!isset($templates_by_logement[$logement_id])) {
        $templates_by_logement[$logement_id] = [
            'nom' => $template['nom_du_logement'],
            'templates' => []
        ];
    }
    $templates_by_logement[$logement_id]['templates'][] = $template;
}

$selected_logement = isset($_GET['logement']) ? (int)$_GET['logement'] : null;
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-file-alt text-primary"></i> Templates SMS
        </h1>
        <p class="lead text-muted">Gérez vos modèles de messages génériques et spécifiques par logement</p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Templates génériques</div>
                        <div class="h5 mb-0 font-weight-bold"><?= $stats['templates_generiques'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-globe fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Templates logements</div>
                        <div class="h5 mb-0 font-weight-bold"><?= $stats['templates_logements'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-home fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Templates actifs</div>
                        <div class="h5 mb-0 font-weight-bold"><?= $stats['templates_actifs'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Logements configurés</div>
                        <div class="h5 mb-0 font-weight-bold"><?= $stats['logements_configures'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $feedback ?>

<!-- Onglets de navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'generiques' ? 'active' : '' ?>" href="?tab=generiques">
            <i class="fas fa-globe"></i> Templates génériques (<?= $stats['templates_generiques'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'logements' ? 'active' : '' ?>" href="?tab=logements">
            <i class="fas fa-home"></i> Templates par logement (<?= $stats['templates_logements'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'aide' ? 'active' : '' ?>" href="?tab=aide">
            <i class="fas fa-question-circle"></i> Aide
        </a>
    </li>
</ul>

<!-- Contenu des onglets -->
<?php if ($active_tab === 'generiques'): ?>
    <!-- Templates génériques -->
    <div class="row">
        <?php foreach ($default_templates as $template): ?>
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100 border-left-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($template['name'])) ?>
                        </h5>
                        <?php if (!empty($template['description'])): ?>
                            <small><?= htmlspecialchars($template['description']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="template_name" value="<?= htmlspecialchars($template['name']) ?>">

                            <div class="form-group">
                                <label><i class="fas fa-comment-dots"></i> Message</label>
                                <textarea class="form-control template-textarea"
                                          name="template_content"
                                          rows="6"
                                          required><?= htmlspecialchars($template['template']) ?></textarea>
                                <small class="form-text text-muted">
                                    Variables: <code>{prenom}</code>, <code>{nom}</code>
                                </small>
                            </div>

                            <button type="submit" name="update_default_template" class="btn btn-primary">
                                <i class="fas fa-save"></i> Sauvegarder
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Ajouter un nouveau template générique -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100 border-left-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle"></i> Nouveau template
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nom du template</label>
                            <input type="text" class="form-control" name="new_template_name" required placeholder="Ex: rappel">
                            <small class="form-text text-muted">Nom unique sans espaces</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> Description</label>
                            <input type="text" class="form-control" name="new_template_description" placeholder="Brève description">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-comment-dots"></i> Message</label>
                            <textarea class="form-control"
                                      name="new_template_content"
                                      rows="6"
                                      required
                                      placeholder="Bonjour {prenom},&#10;&#10;Votre message ici..."></textarea>
                            <small class="form-text text-muted">
                                Variables: <code>{prenom}</code>, <code>{nom}</code>
                            </small>
                        </div>

                        <button type="submit" name="add_new_default_template" class="btn btn-success">
                            <i class="fas fa-plus"></i> Créer le template
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'logements'): ?>
    <!-- Templates par logement -->
    <div class="row">
        <!-- Sélection du logement -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-building"></i> Sélectionner un logement</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (count($logements) === 0): ?>
                        <div class="list-group-item text-muted">
                            <small>Aucun logement disponible</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logements as $logement): ?>
                            <a href="?tab=logements&logement=<?= $logement['id'] ?>"
                               class="list-group-item list-group-item-action <?= $selected_logement == $logement['id'] ? 'active' : '' ?>">
                                <i class="fas fa-home"></i> <?= htmlspecialchars($logement['nom_du_logement']) ?>
                                <?php if (isset($templates_by_logement[$logement['id']])): ?>
                                    <span class="badge badge-light float-right">
                                        <?= count($templates_by_logement[$logement['id']]['templates']) ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <small>
                    <i class="fas fa-info-circle"></i> Les templates spécifiques <strong>remplacent</strong> les templates génériques pour le logement concerné
                </small>
            </div>
        </div>

        <!-- Configuration des templates pour le logement sélectionné -->
        <div class="col-lg-9">
            <?php if ($selected_logement): ?>
                <?php
                $logement_name = '';
                foreach ($logements as $l) {
                    if ($l['id'] == $selected_logement) {
                        $logement_name = $l['nom_du_logement'];
                        break;
                    }
                }
                ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-home"></i> <?= htmlspecialchars($logement_name) ?>
                        </h4>
                    </div>
                </div>

                <!-- Templates existants -->
                <?php if (isset($templates_by_logement[$selected_logement])): ?>
                    <h5 class="mb-3"><i class="fas fa-list"></i> Templates configurés</h5>
                    <div class="row">
                        <?php foreach ($templates_by_logement[$selected_logement]['templates'] as $template): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card shadow-sm h-100 <?= $template['actif'] ? 'border-left-success' : 'border-left-secondary' ?>">
                                    <div class="card-header <?= $template['actif'] ? 'bg-success text-white' : 'bg-secondary text-white' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($template['type_message'])) ?>
                                            </span>
                                            <?php if ($template['actif']): ?>
                                                <span class="badge badge-light text-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-light text-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text small">
                                            <?= nl2br(htmlspecialchars(substr($template['message'], 0, 100))) ?>
                                            <?= strlen($template['message']) > 100 ? '...' : '' ?>
                                        </p>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-info"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal"
                                                    data-template-id="<?= $template['id'] ?>"
                                                    data-logement-id="<?= $template['logement_id'] ?>"
                                                    data-type="<?= htmlspecialchars($template['type_message']) ?>"
                                                    data-message="<?= htmlspecialchars($template['message']) ?>"
                                                    data-actif="<?= $template['actif'] ?>">
                                                <i class="fas fa-edit"></i> Éditer
                                            </button>
                                            <form method="POST" style="display:inline"
                                                  onsubmit="return confirm('Supprimer ce template ?');">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                <button type="submit" name="delete_logement_template" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Ajouter un nouveau template -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Ajouter un template pour ce logement</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="logement_id" value="<?= $selected_logement ?>">

                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Type de message</label>
                                <select class="form-control" name="type_message" required>
                                    <option value="">-- Sélectionnez --</option>
                                    <option value="checkout">Check-out</option>
                                    <option value="accueil">Accueil</option>
                                    <option value="preparation">Préparation</option>
                                    <option value="relance">Relance</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-comment-dots"></i> Message</label>
                                <textarea class="form-control"
                                          name="message"
                                          rows="6"
                                          required
                                          placeholder="Bonjour {prenom},&#10;&#10;Message personnalisé pour ce logement..."></textarea>
                                <small class="form-text text-muted">
                                    Variables: <code>{prenom}</code>, <code>{nom}</code>
                                </small>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="actif" id="actif" checked>
                                <label class="form-check-label" for="actif">
                                    Activer ce template
                                </label>
                            </div>

                            <button type="submit" name="update_logement_template" class="btn btn-primary">
                                <i class="fas fa-save"></i> Sauvegarder
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-arrow-left fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Sélectionnez un logement</h4>
                    <p class="text-muted">Choisissez un logement dans la liste pour configurer ses templates spécifiques</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($active_tab === 'aide'): ?>
    <!-- Aide -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h4><i class="fas fa-question-circle text-info"></i> Comment fonctionnent les templates ?</h4>

            <hr>

            <h5><i class="fas fa-globe text-primary"></i> Templates génériques</h5>
            <p>
                Les templates génériques sont utilisés pour <strong>tous les logements</strong> qui n'ont pas de template spécifique configuré.
                Ils constituent la base de vos messages automatiques et peuvent être réutilisés dans vos automatisations.
            </p>

            <div class="alert alert-primary">
                <strong><i class="fas fa-lightbulb"></i> Conseil :</strong><br>
                Créez des templates génériques pour les messages standard (accueil, départ, préparation) qui fonctionnent pour tous vos logements.
            </div>

            <h5><i class="fas fa-home text-success"></i> Templates par logement</h5>
            <p>
                Vous pouvez créer des templates spécifiques pour chaque logement. Ces templates <strong>remplacent</strong> les templates génériques
                lorsqu'ils sont actifs pour le logement concerné.
            </p>

            <div class="alert alert-success">
                <strong><i class="fas fa-lightbulb"></i> Exemple :</strong><br>
                Si vous configurez un template "accueil" pour "Appartement Paris", alors tous les messages d'accueil pour ce logement
                utiliseront ce template au lieu du template générique "accueil".
            </div>

            <h5><i class="fas fa-code text-info"></i> Variables disponibles</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Variable</th>
                            <th>Description</th>
                            <th>Exemple</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>{prenom}</code></td>
                            <td>Prénom du voyageur</td>
                            <td>Jean</td>
                        </tr>
                        <tr>
                            <td><code>{nom}</code></td>
                            <td>Nom du voyageur</td>
                            <td>Dupont</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h5><i class="fas fa-list-ol text-warning"></i> Types de messages standards</h5>
            <ul class="list-group mb-4">
                <li class="list-group-item">
                    <strong>Préparation</strong> : Envoyé quelques jours avant l'arrivée pour préparer le voyageur
                </li>
                <li class="list-group-item">
                    <strong>Accueil</strong> : Envoyé le jour de l'arrivée pour souhaiter la bienvenue
                </li>
                <li class="list-group-item">
                    <strong>Check-out</strong> : Envoyé le jour du départ pour remercier le voyageur
                </li>
                <li class="list-group-item">
                    <strong>Relance</strong> : Utilisé dans les campagnes de relance et communication groupée
                </li>
            </ul>

            <h5><i class="fas fa-robot text-info"></i> Utilisation avec les automatisations</h5>
            <p>
                Les templates peuvent être utilisés dans les <a href="automations.php">automatisations SMS</a> pour envoyer
                automatiquement des messages selon des critères définis (date d'arrivée, date de départ, etc.).
            </p>

            <div class="alert alert-info">
                <strong><i class="fas fa-info-circle"></i> Note importante :</strong><br>
                Les templates par logement doivent être <strong>activés</strong> pour être utilisés. Vous pouvez désactiver
                temporairement un template sans le supprimer en décochant la case "Activer ce template".
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Modal d'édition -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Éditer le template
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="logement_id" id="edit_logement_id">
                    <input type="hidden" name="type_message" id="edit_type_message">

                    <div class="form-group">
                        <label><i class="fas fa-comment-dots"></i> Message</label>
                        <textarea class="form-control"
                                  name="message"
                                  id="edit_message"
                                  rows="8"
                                  required></textarea>
                        <small class="form-text text-muted">
                            Variables: <code>{prenom}</code>, <code>{nom}</code>
                        </small>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="actif" id="edit_actif">
                        <label class="form-check-label" for="edit_actif">
                            Activer ce template
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="update_logement_template" class="btn btn-primary">
                        <i class="fas fa-save"></i> Sauvegarder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid #007bff !important;
}
.border-left-success {
    border-left: 4px solid #28a745 !important;
}
.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}
.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}
.border-left-secondary {
    border-left: 4px solid #6c757d !important;
}
</style>

<script>
// Remplir le modal d'édition
$('#editModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var logementId = button.data('logement-id');
    var type = button.data('type');
    var message = button.data('message');
    var actif = button.data('actif');

    var modal = $(this);
    modal.find('#edit_logement_id').val(logementId);
    modal.find('#edit_type_message').val(type);
    modal.find('#edit_message').val(message);
    modal.find('#edit_actif').prop('checked', actif == 1);
});
</script>


