<?php
/**
 * SMS reçus — Page unifiée
 * Affiche les SMS entrants et conversations avec les clients
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

function fmt_relative_time($s) {
    if (empty($s)) return '';
    try {
        $dt = new DateTime($s);
        $now = new DateTime();
        $diff = $now->diff($dt);
        if ($diff->days == 0 && $dt->format('Y-m-d') === $now->format('Y-m-d')) {
            return $diff->h == 0 ? ($diff->i == 0 ? 'maintenant' : $diff->i . ' min') : $dt->format('H:i');
        } elseif ($diff->days < 7) {
            $days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
            return $days[$dt->format('w')] . ' ' . $dt->format('H:i');
        }
        return $dt->format('d/m/Y');
    } catch (Exception $e) { return ''; }
}

// Récupérer les SMS reçus
$sms_list = [];
try {
    $sms_list = $pdo->query("
        SELECT s.*, l.nom_du_logement,
               r.prenom as client_prenom, r.nom as client_nom
        FROM sms_in s
        LEFT JOIN reservation r ON s.sender = r.telephone
        LEFT JOIN liste_logements l ON r.logement_id = l.id
        ORDER BY s.received_at DESC
        LIMIT 200
    ")->fetchAll();
} catch (PDOException $e) { /* table peut ne pas exister */ }

// Récupérer les conversations récentes
$conversations = [];
try {
    $conversations = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM conversation_messages cm WHERE cm.conversation_id = c.id) as nb_messages
        FROM conversations c
        ORDER BY c.updated_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS reçus — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-inbox text-primary"></i> SMS reçus</h2>
    <p class="text-muted"><?= count($sms_list) ?> message(s) récent(s)</p>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-sms">
                <i class="fas fa-sms"></i> Messages (<?= count($sms_list) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-conversations">
                <i class="fas fa-comments"></i> Conversations (<?= count($conversations) ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Tab SMS -->
        <div class="tab-pane fade show active" id="tab-sms">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Expéditeur</th>
                                    <th>Client</th>
                                    <th>Logement</th>
                                    <th>Message</th>
                                    <th>Modem</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sms_list as $sms): ?>
                                <tr>
                                    <td><small><?= fmt_relative_time($sms['received_at'] ?? $sms['date'] ?? '') ?></small></td>
                                    <td><strong><?= htmlspecialchars($sms['sender'] ?? $sms['number'] ?? '') ?></strong></td>
                                    <td>
                                        <?php if (!empty($sms['client_prenom'])): ?>
                                            <?= htmlspecialchars($sms['client_prenom'] . ' ' . ($sms['client_nom'] ?? '')) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Inconnu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($sms['nom_du_logement'] ?? '') ?></small></td>
                                    <td><?= htmlspecialchars(mb_substr($sms['message'] ?? $sms['text'] ?? '', 0, 100)) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($sms['modem'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sms_list)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Aucun SMS reçu.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Conversations -->
        <div class="tab-pane fade" id="tab-conversations">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Téléphone</th>
                                    <th>Messages</th>
                                    <th>Statut</th>
                                    <th>Dernière activité</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($conversations as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['phone'] ?? $c['telephone'] ?? '') ?></strong></td>
                                    <td><span class="badge bg-info"><?= $c['nb_messages'] ?? 0 ?></span></td>
                                    <td><span class="badge bg-<?= ($c['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>">
                                        <?= htmlspecialchars($c['status'] ?? 'N/A') ?>
                                    </span></td>
                                    <td><small><?= fmt_relative_time($c['updated_at'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($conversations)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aucune conversation.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
