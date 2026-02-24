<?php
/**
 * Page Automatisations unifiée - Version 2.0
 * Fusionne automation_config.php + custom_automations.php
 * Interface claire avec onglets : Mes règles | Créer | Monitoring
 */
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header_new.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible.');
}

$feedback = '';
$active_tab = $_GET['tab'] ?? 'liste';

// Créer les tables si nécessaire
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
          `logement_id` int(11) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_logement` (`logement_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table existe déjà
}

// Ajouter colonnes custom_sent si nécessaire
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
    // Colonnes existent déjà
}

// ===== TRAITEMENT DES ACTIONS =====

// Créer une automatisation
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
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Règle d'automatisation créée avec succès !</div>";
            $active_tab = 'liste';
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
                SET nom = :nom, description = :description, actif = :actif,
                    declencheur_type = :declencheur_type, declencheur_jours = :declencheur_jours,
                    template_name = :template_name, condition_statut = :condition_statut,
                    flag_field = :flag_field, logement_id = :logement_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':nom' => $nom, ':description' => $description, ':actif' => $actif,
                ':declencheur_type' => $declencheur_type, ':declencheur_jours' => $declencheur_jours,
                ':template_name' => $template_name, ':condition_statut' => $condition_statut,
                ':flag_field' => $flag_field, ':logement_id' => $logement_id, ':id' => $id
            ]);
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Règle modifiée avec succès !</div>";
        } catch (PDOException $e) {
            $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Supprimer une automatisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_automation'])) {
    validateCsrfToken();
    $id = (int)$_POST['automation_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM sms_automations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Règle supprimée !</div>";
    } catch (PDOException $e) {
        $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ===== RÉCUPÉRATION DES DONNÉES =====

// Automatisations
$automations = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, l.nom_du_logement
        FROM sms_automations a
        LEFT JOIN liste_logements l ON a.logement_id = l.id
        ORDER BY a.actif DESC, a.created_at DESC
    ");
    $automations = $stmt->fetchAll();
} catch (PDOException $e) {}

// Logements
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {}

// Templates
$templates = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT template_type FROM sms_templates ORDER BY template_type");
    $templates = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Statistiques pour le monitoring
$stats = [
    'total' => count($automations),
    'actives' => count(array_filter($automations, fn($a) => $a['actif'] == 1)),
    'inactives' => count(array_filter($automations, fn($a) => $a['actif'] == 0))
];

// SMS envoyés aujourd'hui
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_outbox WHERE DATE(timestamp) = CURDATE()");
    $stats['sms_today'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $stats['sms_today'] = 0;
}

$available_flags = ['custom1_sent', 'custom2_sent', 'custom3_sent', 'custom4_sent', 'custom5_sent'];
?>

<style>
    .stats-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    .automation-card {
        border-left: 4px solid;
        transition: all 0.2s;
    }
    .automation-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .automation-card.active {
        border-left-color: #28a745;
    }
    .automation-card.inactive {
        border-left-color: #6c757d;
        opacity: 0.7;
    }
</style>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-robot text-primary"></i> Automatisations SMS
        </h1>
        <p class="lead text-muted">Définissez des règles pour envoyer automatiquement des SMS selon vos critères</p>
    </div>
</div>

<?= $feedback ?>

<!-- Onglets de navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'liste' ? 'active' : '' ?>" href="?tab=liste">
            <i class="fas fa-list"></i> Mes règles (<?= $stats['total'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'creer' ? 'active' : '' ?>" href="?tab=creer">
            <i class="fas fa-plus-circle"></i> Créer une règle
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'monitoring' ? 'active' : '' ?>" href="?tab=monitoring">
            <i class="fas fa-chart-line"></i> Monitoring
        </a>
    </li>
</ul>

<?php if ($active_tab === 'liste'): ?>
    <!-- ONGLET : MES RÈGLES -->
    <div class="row">
        <div class="col-md-12">
            <!-- Statistiques rapides -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card" style="border-left-color: #28a745;">
                        <div class="card-body">
                            <h5><i class="fas fa-check-circle text-success"></i> Règles actives</h5>
                            <h2 class="mb-0"><?= $stats['actives'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card" style="border-left-color: #6c757d;">
                        <div class="card-body">
                            <h5><i class="fas fa-pause-circle text-secondary"></i> Règles inactives</h5>
                            <h2 class="mb-0"><?= $stats['inactives'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card" style="border-left-color: #007bff;">
                        <div class="card-body">
                            <h5><i class="fas fa-paper-plane text-primary"></i> SMS aujourd'hui</h5>
                            <h2 class="mb-0"><?= $stats['sms_today'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($automations) === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Aucune règle d'automatisation pour le moment.</strong><br>
                    Cliquez sur l'onglet "Créer une règle" pour commencer !
                </div>
            <?php else: ?>
                <!-- Liste des automatisations -->
                <?php foreach ($automations as $auto): ?>
                    <div class="card automation-card <?= (int)$auto['actif'] === 1 ? 'active' : 'inactive' ?> mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-1">
                                        <?php if ((int)$auto['actif'] === 1): ?>
                                            <span class="badge badge-success mr-2">ACTIF</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary mr-2">INACTIF</span>
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($auto['nom']) ?></strong>
                                    </h5>
                                    <?php if ($auto['description']): ?>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($auto['description']) ?></p>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap">
                                        <?php
                                        $type_labels = [
                                            'date_arrivee' => 'Arrivée',
                                            'date_depart' => 'Départ',
                                            'date_reservation' => 'Réservation'
                                        ];
                                        $jours = (int)$auto['declencheur_jours'];
                                        $jours_text = '';
                                        if ($jours < 0) {
                                            $jours_text = abs($jours) . ' jour(s) avant';
                                        } elseif ($jours > 0) {
                                            $jours_text = $jours . ' jour(s) après';
                                        } else {
                                            $jours_text = 'Le jour même';
                                        }
                                        ?>
                                        <span class="badge badge-info mr-2 mb-2">
                                            <i class="fas fa-calendar"></i> <?= $type_labels[$auto['declencheur_type']] ?? $auto['declencheur_type'] ?> - <?= $jours_text ?>
                                        </span>
                                        <span class="badge badge-primary mr-2 mb-2">
                                            <i class="fas fa-file-alt"></i> Template: <?= htmlspecialchars($auto['template_name']) ?>
                                        </span>
                                        <?php if (!empty($auto['nom_du_logement'])): ?>
                                            <span class="badge badge-secondary mr-2 mb-2">
                                                <i class="fas fa-home"></i> <?= htmlspecialchars($auto['nom_du_logement']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-light mr-2 mb-2">Tous les logements</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="editAutomation(<?= htmlspecialchars(json_encode($auto)) ?>)">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette règle ?')">
                                        <?php echoCsrfField(); ?>
                                        <input type="hidden" name="automation_id" value="<?= $auto['id'] ?>">
                                        <button type="submit" name="delete_automation" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($active_tab === 'creer'): ?>
    <!-- ONGLET : CRÉER UNE RÈGLE -->
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Créer une nouvelle règle d'automatisation</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>

                        <div class="form-group">
                            <label for="nom"><i class="fas fa-tag"></i> Nom de la règle *</label>
                            <input type="text" class="form-control form-control-lg" id="nom" name="nom" required placeholder="Ex: Rappel 7j avant arrivée">
                        </div>

                        <div class="form-group">
                            <label for="description"><i class="fas fa-comment"></i> Description (optionnelle)</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Décrivez le but de cette automatisation"></textarea>
                        </div>

                        <hr>

                        <h6 class="text-primary"><i class="fas fa-calendar-alt"></i> Quand envoyer le SMS ?</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="declencheur_type">Basé sur *</label>
                                    <select class="form-control" id="declencheur_type" name="declencheur_type" required>
                                        <option value="date_arrivee">Date d'arrivée</option>
                                        <option value="date_depart">Date de départ</option>
                                        <option value="date_reservation">Date de réservation</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="declencheur_jours">Décalage en jours</label>
                                    <input type="number" class="form-control" id="declencheur_jours" name="declencheur_jours" value="0">
                                    <small class="form-text text-muted">
                                        -7 = 7 jours avant | 0 = le jour même | +1 = 1 jour après
                                    </small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="text-primary"><i class="fas fa-file-alt"></i> Contenu du SMS</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="template_name">Template à utiliser *</label>
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
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="flag_field">Champ de suivi</label>
                                    <select class="form-control" id="flag_field" name="flag_field">
                                        <?php foreach ($available_flags as $flag): ?>
                                            <option value="<?= $flag ?>"><?= $flag ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Pour éviter les doublons d'envoi</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="text-primary"><i class="fas fa-filter"></i> Filtres (optionnels)</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logement_id">Logement</label>
                                    <select class="form-control" id="logement_id" name="logement_id">
                                        <option value="">Tous les logements</option>
                                        <?php foreach ($logements as $logement): ?>
                                            <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_statut">Statut de la réservation</label>
                                    <select class="form-control" id="condition_statut" name="condition_statut">
                                        <option value="confirmée">Confirmée uniquement</option>
                                        <option value="">Tous les statuts</option>
                                        <option value="annulée">Annulée uniquement</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="actif" name="actif" checked>
                            <label class="form-check-label" for="actif">
                                <strong>Activer cette règle immédiatement</strong>
                            </label>
                        </div>

                        <button type="submit" name="add_automation" class="btn btn-success btn-lg btn-block">
                            <i class="fas fa-check"></i> Créer cette règle d'automatisation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'monitoring'): ?>
    <!-- ONGLET : MONITORING -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Monitoring des automatisations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h1 class="text-success"><?= $stats['actives'] ?></h1>
                                    <p class="mb-0">Règles actives</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h1 class="text-secondary"><?= $stats['inactives'] ?></h1>
                                    <p class="mb-0">Règles inactives</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h1 class="text-primary"><?= $stats['sms_today'] ?></h1>
                                    <p class="mb-0">SMS envoyés aujourd'hui</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h1 class="text-info"><?= $stats['total'] ?></h1>
                                    <p class="mb-0">Total règles</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mt-4"><i class="fas fa-info-circle"></i> Informations importantes</h5>
                    <ul>
                        <li>Le script d'automatisation s'exécute via cron toutes les heures</li>
                        <li>Les logs sont disponibles dans <code>logs/auto_send_sms.log</code></li>
                        <li>Les SMS sont ajoutés à la file d'attente (<code>sms_outbox</code>) puis envoyés par le modem</li>
                        <li>Chaque règle utilise un "flag" pour éviter d'envoyer plusieurs fois le même SMS</li>
                    </ul>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Configuration du cron :</strong><br>
                        Pour que les automatisations fonctionnent, assurez-vous que le cron est configuré :<br>
                        <code>0 * * * * /usr/bin/php /home/raphael/sms_project/scripts/auto_send_sms.php</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal de modification -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier la règle</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="automation_id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_nom">Nom *</label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_declencheur_type">Basé sur *</label>
                                <select class="form-control" id="edit_declencheur_type" name="declencheur_type" required>
                                    <option value="date_arrivee">Date d'arrivée</option>
                                    <option value="date_depart">Date de départ</option>
                                    <option value="date_reservation">Date de réservation</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_declencheur_jours">Décalage en jours</label>
                                <input type="number" class="form-control" id="edit_declencheur_jours" name="declencheur_jours" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_template_name">Template *</label>
                                <select class="form-control" id="edit_template_name" name="template_name" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="checkout">Checkout</option>
                                    <option value="accueil">Accueil</option>
                                    <option value="preparation">Préparation</option>
                                    <option value="remerciement">Remerciement</option>
                                    <option value="satisfaction">Satisfaction</option>
                                    <option value="rappel_7j">Rappel 7j</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_flag_field">Flag</label>
                                <select class="form-control" id="edit_flag_field" name="flag_field">
                                    <?php foreach ($available_flags as $flag): ?>
                                        <option value="<?= $flag ?>"><?= $flag ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_logement_id">Logement</label>
                                <select class="form-control" id="edit_logement_id" name="logement_id">
                                    <option value="">Tous les logements</option>
                                    <?php foreach ($logements as $logement): ?>
                                        <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_condition_statut">Statut</label>
                                <select class="form-control" id="edit_condition_statut" name="condition_statut">
                                    <option value="confirmée">Confirmée</option>
                                    <option value="">Tous</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_actif" name="actif">
                        <label class="form-check-label" for="edit_actif"><strong>Règle active</strong></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_automation" class="btn btn-warning">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAutomation(automation) {
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
    $('#editModal').modal('show');
}
</script>

<?php include '../includes/footer.php'; ?>
