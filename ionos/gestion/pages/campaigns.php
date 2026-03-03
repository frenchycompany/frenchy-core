<?php
/**
 * Campagnes SMS - Envoi de messages en masse
 * Permet de relancer les anciens clients
 */
require_once __DIR__ . '/../includes/error_handler.php';
// header loaded via menu.php
// DB loaded via config.php
// csrf loaded via config.php

if (!($pdo instanceof PDO)) {
    die('Erreur: PDO non disponible. Verifiez la connexion a la base de donnees.');
}

$feedback = '';
$action = $_GET['action'] ?? 'list';

// --- Creer les tables si elles n'existent pas ---
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
            statut ENUM('brouillon', 'planifiee', 'envoyee', 'annulee') DEFAULT 'brouillon',
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
            statut ENUM('en_attente', 'envoye', 'echec') DEFAULT 'en_attente',
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            FOREIGN KEY (campaign_id) REFERENCES sms_campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (reservation_id) REFERENCES reservation(id) ON DELETE CASCADE
        )
    ");

} catch (PDOException $e) {
    // Tables probablement deja creees
}

// --- Traitement des actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Creer une nouvelle campagne
    if (isset($_POST['create_campaign'])) {
        validateCsrfToken();

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
                header("Location: campaigns.php?action=edit&id=$campaign_id&created=1");
                exit;
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            $feedback = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Le nom et le message sont obligatoires</div>";
        }
    }

    // Ajouter des destinataires a une campagne
    if (isset($_POST['add_recipients'])) {
        validateCsrfToken();

        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $logement_filter = !empty($_POST['recipient_logement']) ? (int)$_POST['recipient_logement'] : null;
        $date_debut = $_POST['recipient_date_debut'] ?? null;
        $date_fin = $_POST['recipient_date_fin'] ?? null;

        if ($campaign_id > 0) {
            try {
                // Construire la requete pour trouver les reservations
                $sql = "
                    SELECT DISTINCT r.id, r.telephone, r.prenom, r.nom
                    FROM reservation r
                    WHERE r.telephone IS NOT NULL
                      AND r.telephone != ''
                      AND r.statut != 'annulee'
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

                // Inserer les destinataires
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

                // Mettre a jour le total dans la campagne
                $pdo->prepare("UPDATE sms_campaigns SET total_recipients = (SELECT COUNT(*) FROM sms_campaign_recipients WHERE campaign_id = :id) WHERE id = :id")
                    ->execute([':id' => $campaign_id]);

                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $count destinataire(s) ajoute(s) a la campagne</div>";
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Envoyer la campagne
    if (isset($_POST['send_campaign'])) {
        validateCsrfToken();

        $campaign_id = (int)($_POST['campaign_id'] ?? 0);

        if ($campaign_id > 0) {
            try {
                // Recuperer la campagne
                $stmt = $pdo->prepare("SELECT * FROM sms_campaigns WHERE id = :id");
                $stmt->execute([':id' => $campaign_id]);
                $campaign = $stmt->fetch();

                if ($campaign) {
                    // Recuperer les destinataires en attente
                    $stmt = $pdo->prepare("
                        SELECT * FROM sms_campaign_recipients
                        WHERE campaign_id = :campaign_id AND statut = 'en_attente'
                    ");
                    $stmt->execute([':campaign_id' => $campaign_id]);
                    $recipients = $stmt->fetchAll();

                    $sent_count = 0;

                    foreach ($recipients as $recipient) {
                        // Personnaliser le message
                        $message = str_replace(
                            ['{prenom}', '{nom}'],
                            [$recipient['prenom'], $recipient['nom']],
                            $campaign['message_template']
                        );

                        // Normaliser le numero de telephone
                        $receiver_clean = preg_replace('/[^0-9+]/', '', $recipient['telephone']);
                        if (!str_starts_with($receiver_clean, '+')) {
                            if (str_starts_with($receiver_clean, '0')) {
                                $receiver_clean = '+33' . substr($receiver_clean, 1);
                            } elseif (!str_starts_with($receiver_clean, '33')) {
                                $receiver_clean = '+33' . $receiver_clean;
                            } else {
                                $receiver_clean = '+' . $receiver_clean;
                            }
                        }

                        // Inserer dans la table Gammu pour envoi reel
                        try {
                            $stmt_gammu = $pdo->prepare("
                                INSERT INTO outbox (
                                    DestinationNumber,
                                    TextDecoded,
                                    CreatorID,
                                    Coding,
                                    Class,
                                    InsertIntoDB,
                                    SendingTimeOut,
                                    DeliveryReport
                                ) VALUES (
                                    :receiver,
                                    :message,
                                    'Campaign',
                                    'Default_No_Compression',
                                    -1,
                                    NOW(),
                                    NOW(),
                                    'default'
                                )
                            ");
                            $stmt_gammu->execute([
                                ':receiver' => $receiver_clean,
                                ':message' => $message
                            ]);

                            // Marquer comme envoye
                            $stmt_update = $pdo->prepare("
                                UPDATE sms_campaign_recipients
                                SET statut = 'envoye', sent_at = NOW()
                                WHERE id = :id
                            ");
                            $stmt_update->execute([':id' => $recipient['id']]);

                            $sent_count++;
                        } catch (PDOException $e) {
                            // Marquer comme echec
                            $stmt_error = $pdo->prepare("
                                UPDATE sms_campaign_recipients
                                SET statut = 'echec', error_message = :error
                                WHERE id = :id
                            ");
                            $stmt_error->execute([
                                ':id' => $recipient['id'],
                                ':error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Mettre a jour la campagne
                    $stmt_campaign = $pdo->prepare("
                        UPDATE sms_campaigns
                        SET statut = 'envoyee', sent_at = NOW(), total_sent = :total_sent
                        WHERE id = :id
                    ");
                    $stmt_campaign->execute([
                        ':id' => $campaign_id,
                        ':total_sent' => $sent_count
                    ]);

                    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne envoyee! $sent_count SMS mis en file d'attente</div>";
                }
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // Supprimer une campagne
    if (isset($_POST['delete_campaign'])) {
        validateCsrfToken();

        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        if ($campaign_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM sms_campaigns WHERE id = :id");
                $stmt->execute([':id' => $campaign_id]);
                header("Location: campaigns.php?deleted=1");
                exit;
            } catch (PDOException $e) {
                $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Messages de redirection
if (isset($_GET['deleted'])) {
    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne supprimee avec succes</div>";
}
if (isset($_GET['created'])) {
    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Campagne creee avec succes! Ajoutez maintenant des destinataires.</div>";
}

// --- Recuperer la liste des logements ---
$logements = [];
try {
    $stmt = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignorer
}

// --- Affichage selon l'action ---
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="display-4">
            <i class="fas fa-bullhorn text-primary"></i> Campagnes SMS
        </h1>
        <p class="lead text-muted">Creez et gerez vos campagnes de relance client</p>
    </div>
    <div class="col-md-4 text-right">
        <?php if ($action === 'list'): ?>
            <a href="?action=create" class="btn btn-primary btn-lg">
                <i class="fas fa-plus-circle"></i> Nouvelle campagne
            </a>
        <?php else: ?>
            <a href="campaigns.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour a la liste
            </a>
        <?php endif; ?>
    </div>
</div>

<?= $feedback ?>

<?php if ($action === 'create'): ?>
    <!-- Formulaire de creation -->
    <?php include __DIR__ . '/campaigns_create.php'; ?>

<?php elseif ($action === 'edit'): ?>
    <!-- Edition d'une campagne -->
    <?php
    $campaign_id = (int)($_GET['id'] ?? 0);
    include __DIR__ . '/campaigns_edit.php';
    ?>

<?php else: ?>
    <!-- Liste des campagnes -->
    <?php
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

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-left-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Campagnes totales</div>
                            <div class="h5 mb-0 font-weight-bold"><?= count($campaigns) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Envoyees</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= count(array_filter($campaigns, fn($c) => $c['statut'] === 'envoyee')) ?>
                            </div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Brouillons</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= count(array_filter($campaigns, fn($c) => $c['statut'] === 'brouillon')) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">SMS envoyes</div>
                            <div class="h5 mb-0 font-weight-bold">
                                <?= array_sum(array_column($campaigns, 'total_sent')) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-paper-plane fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des campagnes -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Mes campagnes</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($campaigns)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Logement</th>
                                <th>Periode ciblee</th>
                                <th class="text-center">Destinataires</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($campaign['nom']) ?></strong>
                                        <?php if (!empty($campaign['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($campaign['description'], 0, 50)) ?><?= strlen($campaign['description']) > 50 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($campaign['nom_du_logement'])): ?>
                                            <span class="badge text-bg-info"><?= htmlspecialchars($campaign['nom_du_logement']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Tous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($campaign['date_debut'] && $campaign['date_fin']): ?>
                                            <?= date('d/m/Y', strtotime($campaign['date_debut'])) ?> - <?= date('d/m/Y', strtotime($campaign['date_fin'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-secondary"><?= $campaign['total_recipients'] ?></span>
                                        <?php if ($campaign['statut'] === 'envoyee'): ?>
                                            <br><small class="text-success"><?= $campaign['total_sent'] ?> envoyes</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        $status_label = $campaign['statut'];
                                        switch ($campaign['statut']) {
                                            case 'envoyee':
                                                $badge_class = 'success';
                                                $status_label = 'Envoyee';
                                                break;
                                            case 'planifiee':
                                                $badge_class = 'info';
                                                $status_label = 'Planifiee';
                                                break;
                                            case 'brouillon':
                                                $badge_class = 'warning';
                                                $status_label = 'Brouillon';
                                                break;
                                            case 'annulee':
                                                $badge_class = 'danger';
                                                $status_label = 'Annulee';
                                                break;
                                        }
                                        ?>
                                        <span class="badge badge-<?= $badge_class ?>"><?= $status_label ?></span>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?= $campaign['id'] ?>" class="btn btn-sm btn-info" title="Voir/Editer">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($campaign['statut'] === 'brouillon'): ?>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette campagne?');">
                                                <?php echoCsrfField(); ?>
                                                <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                <button type="submit" name="delete_campaign" class="btn btn-sm btn-danger" title="Supprimer">
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
                    <p class="text-muted">Creez votre premiere campagne pour relancer vos anciens clients</p>
                    <a href="?action=create" class="btn btn-primary mt-3">
                        <i class="fas fa-plus-circle"></i> Creer une campagne
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


