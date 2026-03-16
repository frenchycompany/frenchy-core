<?php
/**
 * Recherche globale - Recherche unifiee dans tout le systeme
 * Telephone, nom, logement, reference reservation, email
 */
include __DIR__ . '/../config.php';
include __DIR__ . '/../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';
$pdo = getRpiPdo();

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/** Normalisation telephone */
function normalizePhone($raw) {
    if (!$raw) return '';
    $p = preg_replace('/[()\.\s-]+/', '', $raw);
    if (strpos($p, '00') === 0) $p = '+' . substr($p, 2);
    if (strlen($p) === 10 && $p[0] === '0') return '+33' . substr($p, 1);
    if (strlen($p) === 11 && substr($p, 0, 2) === '33') return '+' . $p;
    if (substr($p, 0, 1) === '+') return $p;
    return $p;
}

$q = trim($_GET['q'] ?? '');
$results = [
    'clients' => [],
    'reservations' => [],
    'logements' => [],
    'proprietaires' => [],
    'intervenants' => [],
    'leads' => [],
    'sms' => []
];
$hasResults = false;

// S'assurer que la colonne description existe
try {
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN description TEXT NULL");
} catch (PDOException $e) {
    error_log('search.php: ' . $e->getMessage());
}

if (!empty($q)) {
    $searchTerm = '%' . $q . '%';
    $phoneNormalized = normalizePhone($q);

    // --- Recherche clients (par telephone) ---
    try {
        $sql = "
            SELECT
                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(r.telephone,' ',''),'.',''),'-',''),'(',''),')','') as phone_clean,
                COUNT(*) as nb_resa,
                MAX(r.date_arrivee) as last_arrivee,
                GROUP_CONCAT(DISTINCT CONCAT(TRIM(COALESCE(r.prenom,'')), ' ', TRIM(COALESCE(r.nom,''))) SEPARATOR ', ') as names,
                MAX(r.email) as email
            FROM reservation r
            WHERE r.telephone IS NOT NULL
              AND r.telephone <> ''
              AND (
                  r.telephone LIKE :q
                  OR r.prenom LIKE :q
                  OR r.nom LIKE :q
                  OR r.email LIKE :q
                  OR CONCAT(r.prenom, ' ', r.nom) LIKE :q
              )
            GROUP BY phone_clean
            ORDER BY nb_resa DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => $searchTerm]);
        $results['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['clients'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php: ' . $e->getMessage()); }

    // --- Recherche reservations ---
    try {
        $sql = "
            SELECT r.*, l.nom_du_logement
            FROM reservation r
            LEFT JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.reference LIKE :q
               OR r.prenom LIKE :q
               OR r.nom LIKE :q
               OR r.email LIKE :q
               OR r.telephone LIKE :q
               OR CONCAT(r.prenom, ' ', r.nom) LIKE :q
            ORDER BY r.date_arrivee DESC
            LIMIT 15
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => $searchTerm]);
        $results['reservations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['reservations'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php: ' . $e->getMessage()); }

    // --- Recherche logements ---
    try {
        $sql = "
            SELECT l.*,
                   (SELECT COUNT(*) FROM reservation WHERE logement_id = l.id) as nb_resa,
                   le.wifi_name, le.wifi_password, le.digicode
            FROM liste_logements l
            LEFT JOIN logement_equipements le ON l.id = le.logement_id
            WHERE l.nom_du_logement LIKE :q1
               OR l.adresse LIKE :q2
               OR COALESCE(l.description, '') LIKE :q3
            ORDER BY l.nom_du_logement
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm]);
        $results['logements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['logements'])) $hasResults = true;
    } catch (PDOException $e) {
        // Fallback: recherche simple sans description ni equipements
        try {
            $sql = "
                SELECT l.*, 0 as nb_resa, NULL as wifi_name, NULL as wifi_password, NULL as digicode
                FROM liste_logements l
                WHERE l.nom_du_logement LIKE :q
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':q' => $searchTerm]);
            $results['logements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($results['logements'])) $hasResults = true;
        } catch (PDOException $e2) { error_log('search.php: ' . $e2->getMessage()); }
    }

    // --- Recherche proprietaires ---
    try {
        $sql = "
            SELECT *
            FROM FC_proprietaires
            WHERE nom LIKE :q1
               OR prenom LIKE :q2
               OR email LIKE :q3
               OR telephone LIKE :q4
               OR COALESCE(societe, '') LIKE :q5
               OR CONCAT(prenom, ' ', nom) LIKE :q6
            ORDER BY nom
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm, ':q4' => $searchTerm, ':q5' => $searchTerm, ':q6' => $searchTerm]);
        $results['proprietaires'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['proprietaires'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php proprietaires: ' . $e->getMessage()); }

    // --- Recherche intervenants ---
    try {
        $sql = "
            SELECT *
            FROM intervenant
            WHERE nom LIKE :q1
               OR COALESCE(telephone, '') LIKE :q2
               OR COALESCE(email, '') LIKE :q3
            ORDER BY nom
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm]);
        $results['intervenants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['intervenants'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php intervenants: ' . $e->getMessage()); }

    // --- Recherche leads/prospection ---
    try {
        $sql = "
            SELECT *
            FROM prospection_leads
            WHERE nom LIKE :q1
               OR COALESCE(email, '') LIKE :q2
               OR COALESCE(telephone, '') LIKE :q3
               OR COALESCE(adresse, '') LIKE :q4
            ORDER BY created_at DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm, ':q4' => $searchTerm]);
        $results['leads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results['leads'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php leads: ' . $e->getMessage()); }

    // --- Recherche SMS ---
    try {
        // SMS recus
        $sql = "
            SELECT 'in' as direction, SenderNumber as phone, TextDecoded as message, ReceivingDateTime as date
            FROM inbox
            WHERE SenderNumber LIKE :q OR TextDecoded LIKE :q
            ORDER BY ReceivingDateTime DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => $searchTerm]);
        $smsIn = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // SMS envoyes
        $sql = "
            SELECT 'out' as direction, DestinationNumber as phone, TextDecoded as message, SendingDateTime as date
            FROM sentitems
            WHERE DestinationNumber LIKE :q OR TextDecoded LIKE :q
            ORDER BY SendingDateTime DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':q' => $searchTerm]);
        $smsOut = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['sms'] = array_merge($smsIn, $smsOut);
        usort($results['sms'], fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        $results['sms'] = array_slice($results['sms'], 0, 15);
        if (!empty($results['sms'])) $hasResults = true;
    } catch (PDOException $e) { error_log('search.php: ' . $e->getMessage()); }
}

$totalResults = count($results['clients']) + count($results['reservations']) + count($results['logements']) + count($results['proprietaires']) + count($results['intervenants']) + count($results['leads']) + count($results['sms']);
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-search text-primary"></i> Recherche globale
        </h1>
        <p class="lead text-muted">Recherchez dans les clients, réservations, logements, propriétaires, intervenants, leads et SMS</p>
    </div>
</div>

<!-- Barre de recherche principale -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="search.php">
            <div class="input-group input-group-lg">
                <input type="text" name="q" class="form-control"
                       placeholder="Entrez un numero de telephone, nom, email, reference ou nom de logement..."
                       value="<?= e($q) ?>" autofocus>
                <div class="input-group-append">
                    <button class="btn btn-primary px-4" type="submit">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </div>
            </div>
            <small class="form-text text-muted mt-2">
                <i class="fas fa-lightbulb"></i>
                Exemples : <code>0612345678</code>, <code>Dupont</code>, <code>Studio A</code>, <code>ABC123</code>
            </small>
        </form>
    </div>
</div>

<?php if (!empty($q)): ?>
    <?php if (!$hasResults): ?>
        <!-- Aucun resultat -->
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Aucun resultat pour "<?= e($q) ?>"</h4>
            <p class="text-muted">Essayez avec d'autres termes de recherche</p>
        </div>
    <?php else: ?>
        <!-- Resume des resultats -->
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i>
            <strong><?= $totalResults ?> resultat(s)</strong> trouve(s) pour "<?= e($q) ?>"
        </div>

        <div class="row">
            <!-- Colonne gauche : Clients et Reservations -->
            <div class="col-lg-8">
                <?php if (!empty($results['clients'])): ?>
                    <!-- Resultats Clients -->
                    <div class="card shadow-sm mb-4 border-left-primary">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-users text-primary"></i> Clients
                                <span class="badge text-bg-primary"><?= count($results['clients']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Telephone</th>
                                            <th>Nom(s)</th>
                                            <th>Email</th>
                                            <th>Sejours</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results['clients'] as $client): ?>
                                            <tr>
                                                <td><code><?= e($client['phone_clean']) ?></code></td>
                                                <td><?= e($client['names']) ?></td>
                                                <td><small><?= e($client['email'] ?? '-') ?></small></td>
                                                <td>
                                                    <span class="badge badge-<?= $client['nb_resa'] >= 2 ? 'success' : 'secondary' ?>">
                                                        <?= $client['nb_resa'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="clients.php?phone=<?= urlencode($client['phone_clean']) ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-user"></i> Fiche
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['reservations'])): ?>
                    <!-- Resultats Reservations -->
                    <div class="card shadow-sm mb-4 border-left-success">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-check text-success"></i> Reservations
                                <span class="badge text-bg-success"><?= count($results['reservations']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Client</th>
                                            <th>Logement</th>
                                            <th>Dates</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results['reservations'] as $resa): ?>
                                            <tr>
                                                <td><code><?= e($resa['reference'] ?? '#'.$resa['id']) ?></code></td>
                                                <td>
                                                    <?= e(trim(($resa['prenom'] ?? '') . ' ' . ($resa['nom'] ?? ''))) ?>
                                                    <?php if (!empty($resa['telephone'])): ?>
                                                        <br><small class="text-muted"><?= e($resa['telephone']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($resa['nom_du_logement'])): ?>
                                                        <span class="badge text-bg-info"><?= e($resa['nom_du_logement']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small">
                                                    <?= date('d/m/Y', strtotime($resa['date_arrivee'])) ?>
                                                    <i class="fas fa-arrow-right text-muted"></i>
                                                    <?= date('d/m/Y', strtotime($resa['date_depart'])) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statut = $resa['statut'] ?? '';
                                                    $badge = 'secondary';
                                                    if (stripos($statut, 'confirm') !== false) $badge = 'success';
                                                    elseif (stripos($statut, 'annul') !== false) $badge = 'danger';
                                                    ?>
                                                    <span class="badge badge-<?= $badge ?>"><?= e($statut ?: '-') ?></span>
                                                </td>
                                                <td>
                                                    <a href="reservation_details.php?id=<?= $resa['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['sms'])): ?>
                    <!-- Resultats SMS -->
                    <div class="card shadow-sm mb-4 border-left-info">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-sms text-info"></i> Messages SMS
                                <span class="badge text-bg-info"><?= count($results['sms']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['sms'] as $sms): ?>
                                <div class="d-flex mb-3 p-2 bg-light rounded">
                                    <div class="mr-3">
                                        <?php if ($sms['direction'] === 'in'): ?>
                                            <span class="badge text-bg-success"><i class="fas fa-arrow-down"></i> Recu</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-primary"><i class="fas fa-arrow-up"></i> Envoye</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= e($sms['phone']) ?></strong>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($sms['date'])) ?></small>
                                        </div>
                                        <p class="mb-0 small"><?= e(substr($sms['message'], 0, 150)) ?><?= strlen($sms['message']) > 150 ? '...' : '' ?></p>
                                    </div>
                                    <div class="ml-2">
                                        <a href="recus.php?filter=<?= urlencode($sms['phone']) ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['proprietaires'])): ?>
                    <!-- Resultats Proprietaires -->
                    <div class="card shadow-sm mb-4" style="border-left: 4px solid #6f42c1;">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-tie" style="color:#6f42c1"></i> Propriétaires
                                <span class="badge" style="background:#6f42c1;color:#fff"><?= count($results['proprietaires']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Société</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['proprietaires'] as $prop): ?>
                                            <tr>
                                                <td><?= e(trim(($prop['prenom'] ?? '') . ' ' . ($prop['nom'] ?? ''))) ?></td>
                                                <td><small><?= e($prop['email'] ?? '-') ?></small></td>
                                                <td><code><?= e($prop['telephone'] ?? '-') ?></code></td>
                                                <td><small><?= e($prop['societe'] ?? '-') ?></small></td>
                                                <td>
                                                    <a href="proprietaires.php?id=<?= $prop['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Fiche
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['intervenants'])): ?>
                    <!-- Resultats Intervenants -->
                    <div class="card shadow-sm mb-4" style="border-left: 4px solid #17a2b8;">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-hard-hat text-info"></i> Intervenants
                                <span class="badge text-bg-info"><?= count($results['intervenants']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>Nom</th><th>Rôle</th><th>Téléphone</th><th>Email</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['intervenants'] as $inter): ?>
                                            <tr>
                                                <td><?= e($inter['nom']) ?></td>
                                                <td><span class="badge text-bg-secondary"><?= e($inter['role'] ?? '-') ?></span></td>
                                                <td><code><?= e($inter['telephone'] ?? '-') ?></code></td>
                                                <td><small><?= e($inter['email'] ?? '-') ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($results['leads'])): ?>
                    <!-- Resultats Leads -->
                    <div class="card shadow-sm mb-4" style="border-left: 4px solid #fd7e14;">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bullseye" style="color:#fd7e14"></i> Leads / Prospection
                                <span class="badge" style="background:#fd7e14;color:#fff"><?= count($results['leads']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Statut</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['leads'] as $lead): ?>
                                            <tr>
                                                <td><?= e($lead['nom']) ?></td>
                                                <td><small><?= e($lead['email'] ?? '-') ?></small></td>
                                                <td><code><?= e($lead['telephone'] ?? '-') ?></code></td>
                                                <td><span class="badge text-bg-secondary"><?= e($lead['statut'] ?? '-') ?></span></td>
                                                <td>
                                                    <a href="prospection_proprietaires.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Colonne droite : Logements -->
            <div class="col-lg-4">
                <?php if (!empty($results['logements'])): ?>
                    <!-- Resultats Logements -->
                    <div class="card shadow-sm mb-4 border-left-warning">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-home text-warning"></i> Logements
                                <span class="badge text-bg-warning"><?= count($results['logements']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($results['logements'] as $logement): ?>
                                <div class="card mb-3 <?= empty($logement['actif']) ? 'bg-light' : '' ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1">
                                            <?= e($logement['nom_du_logement']) ?>
                                            <?php if (empty($logement['actif'])): ?>
                                                <span class="badge text-bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </h6>
                                        <?php if (!empty($logement['adresse'])): ?>
                                            <p class="small text-muted mb-2">
                                                <i class="fas fa-map-marker-alt"></i> <?= e(substr($logement['adresse'], 0, 50)) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="small mb-2">
                                            <span class="badge text-bg-info"><?= $logement['nb_resa'] ?> reservation(s)</span>
                                        </div>

                                        <?php if (!empty($logement['wifi_name']) || !empty($logement['digicode'])): ?>
                                            <div class="bg-light p-2 rounded small">
                                                <?php if (!empty($logement['wifi_name'])): ?>
                                                    <div><strong>Wifi:</strong> <?= e($logement['wifi_name']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($logement['wifi_password'])): ?>
                                                    <div><strong>MDP:</strong> <code><?= e($logement['wifi_password']) ?></code></div>
                                                <?php endif; ?>
                                                <?php if (!empty($logement['digicode'])): ?>
                                                    <div><strong>Digicode:</strong> <code><?= e($logement['digicode']) ?></code></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-2">
                                            <a href="logements.php#logement-<?= $logement['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-cog"></i> Gerer
                                            </a>
                                            <a href="logement_equipements.php?id=<?= $logement['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-bed"></i> Equipements
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Aide recherche -->
                <div class="card shadow-sm border-left-info">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle text-info"></i> Aide</h6>
                        <ul class="small mb-0">
                            <li><strong>Telephone</strong> : 0612345678 ou +33612345678</li>
                            <li><strong>Nom</strong> : Dupont, Jean Dupont</li>
                            <li><strong>Email</strong> : exemple@mail.com</li>
                            <li><strong>Reference</strong> : ABC123, HMABCDEF</li>
                            <li><strong>Logement</strong> : Studio, Appartement A</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Page d'accueil recherche -->
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h5>Clients</h5>
                <p class="text-muted small">Recherchez par nom, telephone ou email</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                <h5>Reservations</h5>
                <p class="text-muted small">Recherchez par reference ou nom du client</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-4">
                <i class="fas fa-home fa-3x text-warning mb-3"></i>
                <h5>Logements</h5>
                <p class="text-muted small">Recherchez par nom ou adresse</p>
            </div>
        </div>
    </div>
<?php endif; ?>


