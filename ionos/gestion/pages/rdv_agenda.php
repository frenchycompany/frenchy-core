<?php
/**
 * Agenda des rendez-vous — FrenchyConciergerie
 * Vue calendrier et liste des RDV planifies depuis prospection_leads
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/lead_scoring.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$feedback = '';

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Confirmer un RDV (passer de rdv_planifie a rdv_fait)
    if (isset($_POST['confirm_rdv'])) {
        $id = (int)$_POST['lead_id'];
        try {
            $conn->prepare("UPDATE prospection_leads SET statut = 'rdv_fait', date_derniere_interaction = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$id]);
            $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'rdv', 'RDV confirme comme effectue')")
                ->execute([$id]);
            updateLeadScore($conn, $id);
            $feedback = '<div class="alert alert-success"><i class="fas fa-check"></i> RDV confirme</div>';
        } catch (PDOException $e) {
            error_log('rdv_agenda.php confirm: ' . $e->getMessage());
            $feedback = '<div class="alert alert-danger">Erreur interne</div>';
        }
    }

    // Reporter un RDV
    if (isset($_POST['reschedule_rdv'])) {
        $id = (int)$_POST['lead_id'];
        $newDate = $_POST['new_date'] ?? '';
        $newType = $_POST['new_type'] ?? null;
        if (!empty($newDate)) {
            try {
                $sql = "UPDATE prospection_leads SET date_rdv = ?, updated_at = NOW()";
                $params = [$newDate];
                if ($newType) {
                    $sql .= ", type_rdv = ?";
                    $params[] = $newType;
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;
                $conn->prepare($sql)->execute($params);
                $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'rdv', ?)")
                    ->execute([$id, "RDV reporte au $newDate"]);
                $feedback = '<div class="alert alert-success"><i class="fas fa-calendar-alt"></i> RDV reporte</div>';
            } catch (PDOException $e) {
                error_log('rdv_agenda.php reschedule: ' . $e->getMessage());
                $feedback = '<div class="alert alert-danger">Erreur interne</div>';
            }
        }
    }

    // Annuler un RDV
    if (isset($_POST['cancel_rdv'])) {
        $id = (int)$_POST['lead_id'];
        try {
            $conn->prepare("UPDATE prospection_leads SET date_rdv = NULL, statut = 'contacte', updated_at = NOW() WHERE id = ?")
                ->execute([$id]);
            $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'note', 'RDV annule')")
                ->execute([$id]);
            $feedback = '<div class="alert alert-warning"><i class="fas fa-times"></i> RDV annule</div>';
        } catch (PDOException $e) {
            error_log('rdv_agenda.php cancel: ' . $e->getMessage());
            $feedback = '<div class="alert alert-danger">Erreur interne</div>';
        }
    }
}

// === DONNEES ===
$filter = $_GET['filter'] ?? 'upcoming';
$now = date('Y-m-d H:i:s');

try {
    // RDV a venir
    $upcoming = $conn->query("
        SELECT * FROM prospection_leads
        WHERE date_rdv IS NOT NULL AND date_rdv >= '$now' AND statut NOT IN ('converti','perdu')
        ORDER BY date_rdv ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // RDV passes (non confirmes)
    $past = $conn->query("
        SELECT * FROM prospection_leads
        WHERE date_rdv IS NOT NULL AND date_rdv < '$now' AND statut = 'rdv_planifie'
        ORDER BY date_rdv DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // RDV effectues
    $done = $conn->query("
        SELECT * FROM prospection_leads
        WHERE statut = 'rdv_fait'
        ORDER BY date_rdv DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $todayCount = $conn->query("SELECT COUNT(*) FROM prospection_leads WHERE date_rdv IS NOT NULL AND DATE(date_rdv) = CURDATE()")->fetchColumn();
    $weekCount = $conn->query("SELECT COUNT(*) FROM prospection_leads WHERE date_rdv IS NOT NULL AND date_rdv BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $overdueCount = count($past);
    $doneCount = $conn->query("SELECT COUNT(*) FROM prospection_leads WHERE statut = 'rdv_fait'")->fetchColumn();
} catch (PDOException $e) {
    error_log('rdv_agenda.php: ' . $e->getMessage());
    $upcoming = $past = $done = [];
    $todayCount = $weekCount = $overdueCount = $doneCount = 0;
}

$typeIcons = ['telephone' => 'fa-phone', 'visio' => 'fa-video', 'physique' => 'fa-handshake'];
$typeLabels = ['telephone' => 'Telephone', 'visio' => 'Visio', 'physique' => 'En personne'];
?>

<div class="container-fluid mt-3">

    <?= $feedback ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-calendar-check text-primary"></i> Agenda RDV</h2>
            <p class="text-muted mb-0">Rendez-vous planifies avec les prospects</p>
        </div>
        <a href="prospection_proprietaires.php" class="btn btn-outline-primary">
            <i class="fas fa-funnel-dollar"></i> CRM
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="h3 mb-0 text-primary"><?= $todayCount ?></div>
                    <small class="text-muted">Aujourd'hui</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h3 mb-0 text-info"><?= $weekCount ?></div>
                    <small class="text-muted">Cette semaine</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center <?= $overdueCount > 0 ? 'border-danger' : '' ?>">
                <div class="card-body py-2">
                    <div class="h3 mb-0 <?= $overdueCount > 0 ? 'text-danger' : '' ?>"><?= $overdueCount ?></div>
                    <small class="text-muted">En retard</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-2">
                    <div class="h3 mb-0 text-success"><?= $doneCount ?></div>
                    <small class="text-muted">Effectues</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'upcoming' ? 'active' : '' ?>" href="?filter=upcoming">
                <i class="fas fa-clock"></i> A venir (<?= count($upcoming) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'overdue' ? 'active' : '' ?>" href="?filter=overdue">
                <i class="fas fa-exclamation-triangle text-danger"></i> En retard (<?= $overdueCount ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'done' ? 'active' : '' ?>" href="?filter=done">
                <i class="fas fa-check-circle text-success"></i> Effectues (<?= count($done) ?>)
            </a>
        </li>
    </ul>

    <?php
    $displayList = match($filter) {
        'overdue' => $past,
        'done'    => $done,
        default   => $upcoming,
    };
    ?>

    <?php if (empty($displayList)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-calendar-times fa-3x mb-3"></i>
            <p>Aucun rendez-vous <?= $filter === 'upcoming' ? 'a venir' : ($filter === 'overdue' ? 'en retard' : 'effectue') ?></p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($displayList as $rdv):
                $badge = getScoreBadge($rdv['score'] ?? 0);
                $typeIcon = $typeIcons[$rdv['type_rdv'] ?? ''] ?? 'fa-calendar';
                $typeLabel = $typeLabels[$rdv['type_rdv'] ?? ''] ?? 'Non defini';
                $isToday = $rdv['date_rdv'] && date('Y-m-d', strtotime($rdv['date_rdv'])) === date('Y-m-d');
                $isPast = $rdv['date_rdv'] && strtotime($rdv['date_rdv']) < time();
            ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 <?= $isToday ? 'border-primary border-2' : ($isPast && $filter !== 'done' ? 'border-danger' : '') ?>">
                    <div class="card-header d-flex justify-content-between align-items-center <?= $isToday ? 'bg-primary text-white' : '' ?>">
                        <div>
                            <i class="fas <?= $typeIcon ?>"></i>
                            <strong><?= htmlspecialchars($rdv['nom'] ?? $rdv['email'] ?? 'Sans nom') ?></strong>
                        </div>
                        <span class="badge <?= $badge['class'] ?>"><?= $rdv['score'] ?? 0 ?></span>
                    </div>
                    <div class="card-body">
                        <p class="mb-1">
                            <i class="fas fa-calendar me-1 text-muted"></i>
                            <strong><?= $rdv['date_rdv'] ? date('d/m/Y H:i', strtotime($rdv['date_rdv'])) : '-' ?></strong>
                            <span class="badge bg-light text-dark ms-1"><?= $typeLabel ?></span>
                        </p>
                        <?php if ($rdv['email']): ?>
                        <p class="mb-1 small"><i class="fas fa-envelope me-1 text-muted"></i> <?= htmlspecialchars($rdv['email']) ?></p>
                        <?php endif; ?>
                        <?php if ($rdv['telephone']): ?>
                        <p class="mb-1 small"><i class="fas fa-phone me-1 text-muted"></i>
                            <a href="tel:<?= htmlspecialchars($rdv['telephone']) ?>"><?= htmlspecialchars($rdv['telephone']) ?></a>
                        </p>
                        <?php endif; ?>
                        <?php if ($rdv['ville']): ?>
                        <p class="mb-1 small"><i class="fas fa-map-marker-alt me-1 text-muted"></i> <?= htmlspecialchars($rdv['ville']) ?></p>
                        <?php endif; ?>
                        <?php if ($rdv['message_rdv']): ?>
                        <p class="mb-0 small text-muted fst-italic mt-2">"<?= htmlspecialchars(mb_substr($rdv['message_rdv'], 0, 100)) ?><?= mb_strlen($rdv['message_rdv']) > 100 ? '...' : '' ?>"</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex gap-1">
                        <?php if ($filter !== 'done'): ?>
                        <form method="POST" class="d-inline">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="lead_id" value="<?= $rdv['id'] ?>">
                            <button type="submit" name="confirm_rdv" class="btn btn-sm btn-success" title="Confirmer">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reschedule<?= $rdv['id'] ?>" title="Reporter">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Annuler ce RDV ?')">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="lead_id" value="<?= $rdv['id'] ?>">
                            <button type="submit" name="cancel_rdv" class="btn btn-sm btn-outline-danger" title="Annuler">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <a href="prospection_proprietaires.php?id=<?= $rdv['id'] ?>" class="btn btn-sm btn-outline-secondary ms-auto" title="Voir fiche">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Modal Reporter -->
            <div class="modal fade" id="reschedule<?= $rdv['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="lead_id" value="<?= $rdv['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Reporter le RDV</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Nouvelle date et heure</label>
                                <input type="datetime-local" name="new_date" class="form-control" required
                                       value="<?= $rdv['date_rdv'] ? date('Y-m-d\TH:i', strtotime($rdv['date_rdv'])) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type de RDV</label>
                                <select name="new_type" class="form-select">
                                    <option value="telephone" <?= ($rdv['type_rdv'] ?? '') === 'telephone' ? 'selected' : '' ?>>Telephone</option>
                                    <option value="visio" <?= ($rdv['type_rdv'] ?? '') === 'visio' ? 'selected' : '' ?>>Visio</option>
                                    <option value="physique" <?= ($rdv['type_rdv'] ?? '') === 'physique' ? 'selected' : '' ?>>En personne</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="reschedule_rdv" class="btn btn-primary">Reporter</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
