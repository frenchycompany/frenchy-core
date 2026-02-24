<?php
/**
 * Listing des réservations — Page unifiée
 * Intègre le module réservations du Raspberry Pi dans l'interface gestion
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Paramètres de filtrage
$filtre_logement = isset($_GET['logement']) && $_GET['logement'] !== '' ? (int)$_GET['logement'] : null;
$filtre_statut = isset($_GET['statut']) && $_GET['statut'] !== '' ? $_GET['statut'] : null;
$filtre_date_debut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$filtre_date_fin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;
$filtre_plateforme = isset($_GET['plateforme']) && $_GET['plateforme'] !== '' ? $_GET['plateforme'] : null;
$tri = $_GET['tri'] ?? 'date_desc';

// Récupérer les logements
$logements = [];
try {
    $logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Récupérer les plateformes
$plateformes = [];
try {
    $plateformes = $pdo->query("SELECT DISTINCT plateforme FROM reservation WHERE plateforme IS NOT NULL AND plateforme != '' ORDER BY plateforme")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* ignore */ }

// Construire la requête
$sql = "
    SELECT r.*, l.nom_du_logement,
           DATEDIFF(r.date_depart, r.date_arrivee) as duree_sejour,
           DATEDIFF(r.date_arrivee, CURDATE()) as jours_avant_arrivee
    FROM reservation r
    LEFT JOIN liste_logements l ON r.logement_id = l.id
    WHERE 1=1
";
$params = [];

if ($filtre_logement !== null) {
    $sql .= " AND r.logement_id = :logement";
    $params[':logement'] = $filtre_logement;
}
if ($filtre_statut !== null) {
    $sql .= " AND r.statut = :statut";
    $params[':statut'] = $filtre_statut;
}
if ($filtre_date_debut !== null) {
    $sql .= " AND r.date_arrivee >= :date_debut";
    $params[':date_debut'] = $filtre_date_debut;
}
if ($filtre_date_fin !== null) {
    $sql .= " AND r.date_depart <= :date_fin";
    $params[':date_fin'] = $filtre_date_fin;
}
if ($filtre_plateforme !== null) {
    $sql .= " AND r.plateforme = :plateforme";
    $params[':plateforme'] = $filtre_plateforme;
}

switch ($tri) {
    case 'date_asc': $sql .= " ORDER BY r.date_arrivee ASC"; break;
    case 'logement': $sql .= " ORDER BY l.nom_du_logement ASC, r.date_arrivee DESC"; break;
    default: $sql .= " ORDER BY r.date_arrivee DESC"; break;
}

$reservations = [];
$stats = ['total' => 0, 'en_cours' => 0, 'a_venir' => 0, 'passees' => 0];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
    $stats['total'] = count($reservations);
    foreach ($reservations as $r) {
        if ($r['jours_avant_arrivee'] > 0) $stats['a_venir']++;
        elseif ($r['jours_avant_arrivee'] <= 0 && ($r['duree_sejour'] + $r['jours_avant_arrivee']) > 0) $stats['en_cours']++;
        else $stats['passees']++;
    }
} catch (PDOException $e) { /* ignore */ }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations — FrenchyConciergerie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-calendar-check text-primary"></i> Réservations</h2>
    <p class="text-muted"><?= $stats['total'] ?> réservation(s) —
        <span class="text-success"><?= $stats['en_cours'] ?> en cours</span>,
        <span class="text-info"><?= $stats['a_venir'] ?> à venir</span>,
        <span class="text-secondary"><?= $stats['passees'] ?> passées</span>
    </p>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Logement</label>
                    <select name="logement" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <?php foreach ($logements as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $filtre_logement == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Plateforme</label>
                    <select name="plateforme" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        <?php foreach ($plateformes as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $filtre_plateforme === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($filtre_date_debut ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtre_date_fin ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tri</label>
                    <select name="tri" class="form-select form-select-sm">
                        <option value="date_desc" <?= $tri === 'date_desc' ? 'selected' : '' ?>>Date (récent)</option>
                        <option value="date_asc" <?= $tri === 'date_asc' ? 'selected' : '' ?>>Date (ancien)</option>
                        <option value="logement" <?= $tri === 'logement' ? 'selected' : '' ?>>Par logement</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> Filtrer</button>
                    <a href="reservations.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Logement</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Arrivée</th>
                            <th>Départ</th>
                            <th>Durée</th>
                            <th>Plateforme</th>
                            <th>Statut</th>
                            <th>SMS</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <?php
                        $today = date('Y-m-d');
                        $isEnCours = ($r['date_arrivee'] <= $today && $r['date_depart'] >= $today);
                        $isAVenir = $r['date_arrivee'] > $today;
                        $rowClass = $isEnCours ? 'table-success' : ($isAVenir ? '' : 'text-muted');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td>#<?= $r['id'] ?></td>
                            <td><strong><?= htmlspecialchars($r['nom_du_logement'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')) ?></td>
                            <td><small><?= htmlspecialchars($r['telephone'] ?? '') ?></small></td>
                            <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                            <td><span class="badge bg-secondary"><?= $r['duree_sejour'] ?>n</span></td>
                            <td><small><?= htmlspecialchars($r['plateforme'] ?? '') ?></small></td>
                            <td>
                                <?php if (($r['statut'] ?? '') === 'confirmée'): ?>
                                    <span class="badge bg-success">Confirmée</span>
                                <?php elseif (($r['statut'] ?? '') === 'annulée'): ?>
                                    <span class="badge bg-danger">Annulée</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($r['statut'] ?? 'N/A') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($r['start_sent']) ? '<i class="fas fa-check text-success" title="Préparation envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                                <?= !empty($r['j1_sent']) ? '<i class="fas fa-check text-success" title="Check-in envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                                <?= !empty($r['dep_sent']) ? '<i class="fas fa-check text-success" title="Check-out envoyé"></i>' : '<i class="fas fa-minus text-muted"></i>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Aucune réservation trouvée.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
