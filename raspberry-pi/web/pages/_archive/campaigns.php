<?php
// Système de campagnes SMS pour relance des clients
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Vérifiez la connexion à la base de données.');
}

$feedback = '';
$action = $_GET['action'] ?? 'list';

// --- Créer les tables si elles n'existent pas ---
try {
    // Table pour les campagnes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            description TEXT,
            logement_id INT DEFAULT NULL,
            message_template TEXT NOT NULL,
            date_debut DATE DEFAULT NULL,
            date_fin DATE DEFAULT NULL,
            statut ENUM('brouillon', 'planifiée', 'envoyée', 'annulée') DEFAULT 'brouillon',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            total_recipients INT DEFAULT 0,
            total_sent INT DEFAULT 0,
            FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE SET NULL
        )
    ");

    // Table pour les destinataires de campagne
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_campaign_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            reservation_id INT NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            prenom VARCHAR(100),
            nom VARCHAR(100),
            statut ENUM('en_attente', 'envoyé', 'échec') DEFAULT 'en_attente',
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
        )
    ");

    // Table pour les templates SMS par logement
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_logement_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            logement_id INT NOT NULL,
            type_message VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            actif BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (logement_id) REFERENCES liste_logements(id) ON DELETE CASCADE,
            UNIQUE KEY unique_logement_type (logement_id, type_message)
        )
    ");

} catch (PDOException $e) {
    // Tables probablement déjà créées
}

// --- Traitement des actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_campaign'])) {
        // Créer une nouvelle campagne
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;
        $message = trim($_POST['message'] ?? '');
        $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
        $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

        if (!empty($nom) && !empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_campaigns (nom, description, logement_id, message_template, date_debut, date_fin, statut)
                    VALUES (:nom, :description, :logement_id, :message, :date_debut, :date_fin, 'brouillon')
                ");
                $stmt->execute([
                    ':nom' => $nom,
                    ':description' => $description,
                    ':logement_id' => $logement_id,
                    ':message' => $message,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin
                ]);

                $campaign_id = $pdo->lastInsertId();
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne créée avec succès! <a href='?action=edit&id=$campaign_id' class='alert-link'>Configurer les destinataires</a></div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Le nom et le message sont obligatoires</div>";
        }
    }

    if (isset($_POST['add_recipients'])) {
        // Ajouter des destinataires à une campagne
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $logement_filter = !empty($_POST['recipient_logement']) ? (int)$_POST['recipient_logement'] : null;
        $date_debut = $_POST['recipient_date_debut'] ?? null;
        $date_fin = $_POST['recipient_date_fin'] ?? null;

        if ($campaign_id > 0) {
            try {
                // Construire la requête pour trouver les réservations
                $sql = "
                    SELECT DISTINCT r.id, r.telephone, r.prenom, r.nom
                    FROM reservation r
                    WHERE r.telephone IS NOT NULL
                      AND r.telephone != ''
                      AND r.statut != 'annulée'
                ";

                $params = [];

                if ($logement_filter) {
                    $sql .= " AND r.logement_id = :logement_id";
                    $params[':logement_id'] = $logement_filter;
                }

                if ($date_debut) {
                    $sql .= " AND r.date_depart >= :date_debut";
                    $params[':date_debut'] = $date_debut;
                }

                if ($date_fin) {
                    $sql .= " AND r.date_depart <= :date_fin";
                    $params[':date_fin'] = $date_fin;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reservations = $stmt->fetchAll();

                // Insérer les destinataires
                $count = 0;
                $stmt_insert = $pdo->prepare("
                    INSERT IGNORE INTO sms_campaign_recipients (campaign_id, reservation_id, telephone, prenom, nom)
                    VALUES (:campaign_id, :reservation_id, :telephone, :prenom, :nom)
                ");

                foreach ($reservations as $res) {
                    $stmt_insert->execute([
                        ':campaign_id' => $campaign_id,
                        ':reservation_id' => $res['id'],
                        ':telephone' => $res['telephone'],
                        ':prenom' => $res['prenom'],
                        ':nom' => $res['nom']
                    ]);
                    if ($stmt_insert->rowCount() > 0) {
                        $count++;
                    }
                }

                // Mettre à jour le total dans la campagne
                $pdo->prepare("UPDATE sms_campaigns SET total_recipients = (SELECT COUNT(*) FROM sms_campaign_recipients WHERE campaign_id = :id) WHERE id = :id")
                    ->execute([':id' => $campaign_id]);

                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $count destinataire(s) ajouté(s) à la campagne</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['send_campaign'])) {
        // Envoyer la campagne
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);

        if ($campaign_id > 0) {
            try {
                // Récupérer la campagne
                $stmt = $pdo->prepare("SELECT * FROM sms_campaigns WHERE id = :id");
                $stmt->execute([':id' => $campaign_id]);
                $campaign = $stmt->fetch();

                if ($campaign) {
                    // Récupérer les destinataires en attente
                    $stmt = $pdo->prepare("
                        SELECT * FROM sms_campaign_recipients
                        WHERE campaign_id = :campaign_id AND statut = 'en_attente'
                    ");
                    $stmt->execute([':campaign_id' => $campaign_id]);
                    $recipients = $stmt->fetchAll();

                    $modem = '/dev/ttyUSB0';
                    $sent_count = 0;

                    foreach ($recipients as $recipient) {
                        // Personnaliser le message
                        $message = str_replace(
                            ['{prenom}', '{nom}'],
                            [$recipient['prenom'], $recipient['nom']],
                            $campaign['message_template']
                        );

                        // Insérer dans la file d'attente
                        try {
                            $stmt_sms = $pdo->prepare("
                                INSERT INTO sms_outbox (receiver, message, modem, status)
                                VALUES (:receiver, :message, :modem, 'pending')
                            ");
                            $stmt_sms->execute([
                                ':receiver' => $recipient['telephone'],
                                ':message' => $message,
                                ':modem' => $modem
                            ]);

                            // Marquer comme envoyé
                            $stmt_update = $pdo->prepare("
                                UPDATE sms_campaign_recipients
                                SET statut = 'envoyé', sent_at = NOW()
                                WHERE id = :id
                            ");
                            $stmt_update->execute([':id' => $recipient['id']]);

                            $sent_count++;
                        } catch (PDOException $e) {
                            // Marquer comme échec
                            $stmt_error = $pdo->prepare("
                                UPDATE sms_campaign_recipients
                                SET statut = 'échec', error_message = :error
                                WHERE id = :id
                            ");
                            $stmt_error->execute([
                                ':id' => $recipient['id'],
                                ':error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Mettre à jour la campagne
                    $stmt_campaign = $pdo->prepare("
                        UPDATE sms_campaigns
                        SET statut = 'envoyée', sent_at = NOW(), total_sent = :total_sent
                        WHERE id = :id
                    ");
                    $stmt_campaign->execute([
                        ':id' => $campaign_id,
                        ':total_sent' => $sent_count
                    ]);

                    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne envoyée! $sent_count SMS mis en file d'attente</div>";
                }
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['delete_campaign'])) {
        // Supprimer une campagne
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        if ($campaign_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM sms_campaigns WHERE id = :id");
                $stmt->execute([':id' => $campaign_id]);
                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne supprimée</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// --- Récupérer la liste des logements ---
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}

// --- Affichage selon l'action ---
if ($action === 'create') {
    // Formulaire de création
    include 'campaigns_create.php';
} elseif ($action === 'edit') {
    // Édition d'une campagne
    $campaign_id = (int)($_GET['id'] ?? 0);
    include 'campaigns_edit.php';
} else {
    // Liste des campagnes
    try {
        $stmt = $pdo->query("
            SELECT c.*, l.nom_du_logement
            FROM sms_campaigns c
            LEFT JOIN liste_logements l ON c.logement_id = l.id
            ORDER BY c.created_at DESC
        ");
        $campaigns = $stmt->fetchAll();
    } catch (PDOException $e) {
        $campaigns = [];
    }
    ?>

    <div class="container mt-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="text-gradient-primary">
                    <i class="fas fa-bullhorn"></i> Campagnes SMS
                </h1>
                <p class="text-muted">Créez et gérez vos campagnes de relance client</p>
            </div>
            <div class="col-md-4 text-right">
                <a href="?action=create" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle"></i> Nouvelle campagne
                </a>
            </div>
        </div>

        <?php if ($feedback): ?>
            <?= $feedback ?>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-custom border-primary">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-3x text-primary mb-2"></i>
                        <h3 class="mb-0"><?= count($campaigns) ?></h3>
                        <p class="text-muted mb-0">Campagnes totales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-custom border-success">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                        <h3 class="mb-0">
                            <?php
                            $count = array_filter($campaigns, fn($c) => $c['statut'] === 'envoyée');
                            echo count($count);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Envoyées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-custom border-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-3x text-warning mb-2"></i>
                        <h3 class="mb-0">
                            <?php
                            $count = array_filter($campaigns, fn($c) => $c['statut'] === 'brouillon');
                            echo count($count);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Brouillons</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-custom border-info">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-info mb-2"></i>
                        <h3 class="mb-0">
                            <?php
                            $total = array_sum(array_column($campaigns, 'total_sent'));
                            echo $total;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">SMS envoyés</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des campagnes -->
        <div class="card shadow-custom">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Mes campagnes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($campaigns)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-tag"></i> Nom</th>
                                    <th><i class="fas fa-home"></i> Logement</th>
                                    <th><i class="fas fa-calendar"></i> Période</th>
                                    <th><i class="fas fa-users"></i> Destinataires</th>
                                    <th><i class="fas fa-info-circle"></i> Statut</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?= $campaign['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($campaign['nom']) ?></strong>
                                            <?php if (!empty($campaign['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($campaign['description'], 0, 50)) ?><?= strlen($campaign['description']) > 50 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($campaign['nom_du_logement'])): ?>
                                                <span class="badge badge-info"><?= htmlspecialchars($campaign['nom_du_logement']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Tous les logements</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap small">
                                            <?php if ($campaign['date_debut'] && $campaign['date_fin']): ?>
                                                <?= date('d/m/Y', strtotime($campaign['date_debut'])) ?><br>
                                                <i class="fas fa-arrow-down"></i><br>
                                                <?= date('d/m/Y', strtotime($campaign['date_fin'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary"><?= $campaign['total_recipients'] ?></span>
                                            <?php if ($campaign['statut'] === 'envoyée'): ?>
                                                <br><small class="text-success"><?= $campaign['total_sent'] ?> envoyés</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            switch ($campaign['statut']) {
                                                case 'envoyée':
                                                    $badge_class = 'success';
                                                    break;
                                                case 'planifiée':
                                                    $badge_class = 'info';
                                                    break;
                                                case 'brouillon':
                                                    $badge_class = 'warning';
                                                    break;
                                                case 'annulée':
                                                    $badge_class = 'danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge badge-<?= $badge_class ?>">
                                                <?= htmlspecialchars($campaign['statut']) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="?action=edit&id=<?= $campaign['id'] ?>"
                                               class="btn btn-sm btn-info"
                                               title="Éditer">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($campaign['statut'] === 'brouillon'): ?>
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette campagne?');">
                                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                    <button type="submit" name="delete_campaign"
                                                            class="btn btn-sm btn-danger"
                                                            title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune campagne pour le moment</h4>
                        <p class="text-muted">Créez votre première campagne pour relancer vos clients</p>
                        <a href="?action=create" class="btn btn-primary mt-3">
                            <i class="fas fa-plus-circle"></i> Créer une campagne
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
}

include '../includes/footer.php';
?>
