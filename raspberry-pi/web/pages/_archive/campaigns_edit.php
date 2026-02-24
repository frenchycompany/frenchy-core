<?php
// Récupérer la campagne
try {
    $stmt = $pdo->prepare("
        SELECT c.*, l.nom_du_logement
        FROM sms_campaigns c
        LEFT JOIN liste_logements l ON c.logement_id = l.id
        WHERE c.id = :id
    ");
    $stmt->execute([':id' => $campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        echo "<div class='alert alert-danger'>Campagne introuvable</div>";
        echo "<a href='campaigns.php' class='btn btn-secondary'>Retour</a>";
        include '../includes/footer.php';
        exit;
    }

    // Récupérer les destinataires
    $stmt = $pdo->prepare("
        SELECT r.*, res.nom_du_logement
        FROM sms_campaign_recipients r
        LEFT JOIN reservation res_table ON r.reservation_id = res_table.id
        LEFT JOIN liste_logements res ON res_table.logement_id = res.id
        WHERE r.campaign_id = :campaign_id
        ORDER BY r.id DESC
    ");
    $stmt->execute([':campaign_id' => $campaign_id]);
    $recipients = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    include '../includes/footer.php';
    exit;
}

$can_edit = in_array($campaign['statut'], ['brouillon', 'planifiée']);
$can_send = ($campaign['statut'] === 'brouillon' && $campaign['total_recipients'] > 0);
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php"><i class="fas fa-home"></i> Accueil</a></li>
            <li class="breadcrumb-item"><a href="campaigns.php"><i class="fas fa-bullhorn"></i> Campagnes</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($campaign['nom']) ?></li>
        </ol>
    </nav>

    <?php if ($feedback): ?>
        <?= $feedback ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="text-gradient-primary">
                <i class="fas fa-bullhorn"></i> <?= htmlspecialchars($campaign['nom']) ?>
            </h1>
            <?php if (!empty($campaign['description'])): ?>
                <p class="text-muted"><?= htmlspecialchars($campaign['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-right">
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
            <h3><span class="badge badge-<?= $badge_class ?>"><?= htmlspecialchars($campaign['statut']) ?></span></h3>
            <a href="campaigns.php" class="btn btn-secondary mt-2">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche: Détails de la campagne -->
        <div class="col-lg-8">
            <!-- Informations -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-home"></i> Logement:</strong>
                                <?= !empty($campaign['nom_du_logement']) ? htmlspecialchars($campaign['nom_du_logement']) : 'Tous les logements' ?>
                            </p>
                            <p><strong><i class="fas fa-calendar"></i> Créée le:</strong>
                                <?= date('d/m/Y à H:i', strtotime($campaign['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($campaign['date_debut'] && $campaign['date_fin']): ?>
                                <p><strong><i class="fas fa-calendar-alt"></i> Période ciblée:</strong>
                                    Du <?= date('d/m/Y', strtotime($campaign['date_debut'])) ?>
                                    au <?= date('d/m/Y', strtotime($campaign['date_fin'])) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($campaign['sent_at']): ?>
                                <p><strong><i class="fas fa-paper-plane"></i> Envoyée le:</strong>
                                    <?= date('d/m/Y à H:i', strtotime($campaign['sent_at'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-comment-dots"></i> Message</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border">
                        <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($campaign['message_template']) ?></pre>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Variables: <code>{prenom}</code>, <code>{nom}</code> seront remplacées automatiquement
                    </small>
                </div>
            </div>

            <!-- Liste des destinataires -->
            <div class="card shadow-custom mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users"></i> Destinataires
                        <span class="badge badge-light text-dark"><?= count($recipients) ?></span>
                    </h5>
                    <?php if ($can_edit): ?>
                        <button class="btn btn-sm btn-light" data-toggle="collapse" data-target="#addRecipientsForm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($can_edit): ?>
                        <!-- Formulaire d'ajout de destinataires (collapsé par défaut) -->
                        <div class="collapse mb-4" id="addRecipientsForm">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Ajouter des destinataires</h6>
                                    <form method="POST">
                                        <input type="hidden" name="campaign_id" value="<?= $campaign_id ?>">

                                        <div class="form-group">
                                            <label><i class="fas fa-home"></i> Filtrer par logement</label>
                                            <select class="form-control" name="recipient_logement">
                                                <option value="">Tous les logements</option>
                                                <?php foreach ($logements as $logement): ?>
                                                    <option value="<?= $logement['id'] ?>" <?= $campaign['logement_id'] == $logement['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($logement['nom_du_logement']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><i class="fas fa-calendar"></i> Séjour du</label>
                                                    <input type="date"
                                                           class="form-control"
                                                           name="recipient_date_debut"
                                                           value="<?= $campaign['date_debut'] ?? '' ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><i class="fas fa-calendar"></i> Séjour au</label>
                                                    <input type="date"
                                                           class="form-control"
                                                           name="recipient_date_fin"
                                                           value="<?= $campaign['date_fin'] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="add_recipients" class="btn btn-primary">
                                            <i class="fas fa-user-plus"></i> Ajouter les destinataires correspondants
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($recipients)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user"></i> Nom</th>
                                        <th><i class="fas fa-phone"></i> Téléphone</th>
                                        <th><i class="fas fa-home"></i> Logement</th>
                                        <th><i class="fas fa-info-circle"></i> Statut</th>
                                        <?php if ($campaign['statut'] === 'envoyée'): ?>
                                            <th><i class="fas fa-clock"></i> Envoyé le</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($recipient['prenom'] . ' ' . $recipient['nom']) ?></td>
                                            <td><?= htmlspecialchars($recipient['telephone']) ?></td>
                                            <td>
                                                <?php if (!empty($recipient['nom_du_logement'])): ?>
                                                    <span class="badge badge-info"><?= htmlspecialchars($recipient['nom_du_logement']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badge = 'secondary';
                                                $status_text = $recipient['statut'];
                                                switch ($recipient['statut']) {
                                                    case 'envoyé':
                                                        $status_badge = 'success';
                                                        break;
                                                    case 'en_attente':
                                                        $status_badge = 'warning';
                                                        $status_text = 'En attente';
                                                        break;
                                                    case 'échec':
                                                        $status_badge = 'danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge badge-<?= $status_badge ?>"><?= $status_text ?></span>
                                            </td>
                                            <?php if ($campaign['statut'] === 'envoyée'): ?>
                                                <td class="text-nowrap small">
                                                    <?= $recipient['sent_at'] ? date('d/m/Y H:i', strtotime($recipient['sent_at'])) : '-' ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucun destinataire pour le moment</p>
                            <?php if ($can_edit): ?>
                                <button class="btn btn-primary" data-toggle="collapse" data-target="#addRecipientsForm">
                                    <i class="fas fa-plus"></i> Ajouter des destinataires
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne droite: Actions -->
        <div class="col-lg-4">
            <div class="card shadow-custom sticky-top" style="top: 20px;">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Actions</h5>
                </div>
                <div class="card-body">
                    <!-- Statistiques -->
                    <div class="text-center mb-4">
                        <h2 class="text-primary mb-0"><?= $campaign['total_recipients'] ?></h2>
                        <p class="text-muted mb-0">Destinataires</p>
                        <?php if ($campaign['statut'] === 'envoyée'): ?>
                            <hr>
                            <h3 class="text-success mb-0"><?= $campaign['total_sent'] ?></h3>
                            <p class="text-muted mb-0">SMS envoyés</p>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Boutons d'action -->
                    <?php if ($can_send): ?>
                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir envoyer cette campagne à <?= $campaign['total_recipients'] ?> destinataire(s) ?');">
                            <input type="hidden" name="campaign_id" value="<?= $campaign_id ?>">
                            <button type="submit" name="send_campaign" class="btn btn-success btn-block btn-lg">
                                <i class="fas fa-paper-plane"></i> Envoyer la campagne
                            </button>
                        </form>
                        <small class="text-muted d-block text-center mt-2">
                            <i class="fas fa-info-circle"></i> Les SMS seront mis en file d'attente
                        </small>
                    <?php elseif ($campaign['statut'] === 'brouillon'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Ajoutez des destinataires avant d'envoyer
                        </div>
                    <?php elseif ($campaign['statut'] === 'envoyée'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Campagne envoyée avec succès
                        </div>
                    <?php endif; ?>

                    <?php if ($campaign['statut'] === 'brouillon'): ?>
                        <hr>
                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette campagne?');">
                            <input type="hidden" name="campaign_id" value="<?= $campaign_id ?>">
                            <button type="submit" name="delete_campaign" class="btn btn-danger btn-block">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
