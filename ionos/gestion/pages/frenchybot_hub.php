<?php
/**
 * FrenchyBot — Admin HUB Sejours
 * Liste les reservations avec leur token HUB, permet de generer/partager les liens
 */
include '../config.php';
include '../pages/menu.php';

// --- Fonctions inline (pas de dependance frenchybot/) ---
function _getOrCreateHubToken(PDO $pdo, int $reservationId, int $logementId): string {
    $stmt = $pdo->prepare("SELECT token FROM hub_tokens WHERE reservation_id = ? AND logement_id = ? AND active = 1");
    $stmt->execute([$reservationId, $logementId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return $existing;

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("INSERT INTO hub_tokens (reservation_id, logement_id, token, active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$reservationId, $logementId, $token]);
    return $token;
}

function _getAppUrl(PDO $pdo): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'app_url'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val && $val !== '') return $val;
    } catch (\PDOException $e) {}
    return env('APP_URL', 'https://gestion.frenchyconciergerie.fr');
}

$appUrl = _getAppUrl($pdo);

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_token') {
        $resaId = (int)($_POST['reservation_id'] ?? 0);
        $logId = (int)($_POST['logement_id'] ?? 0);
        if ($resaId && $logId) {
            _getOrCreateHubToken($pdo, $resaId, $logId);
            $_SESSION['flash'] = 'Token HUB genere avec succes.';
        }
    }

    if ($action === 'deactivate_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        if ($tokenId) {
            $pdo->prepare("UPDATE hub_tokens SET active = 0 WHERE id = ?")->execute([$tokenId]);
            $_SESSION['flash'] = 'Token desactive.';
        }
    }

    if ($action === 'reactivate_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        if ($tokenId) {
            $pdo->prepare("UPDATE hub_tokens SET active = 1 WHERE id = ?")->execute([$tokenId]);
            $_SESSION['flash'] = 'Token reactive.';
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Donnees ---
$hubTokens = $pdo->query("
    SELECT ht.*, r.prenom, r.nom, r.date_arrivee, r.date_depart, r.telephone, r.plateforme,
           l.nom_du_logement,
           (SELECT COUNT(*) FROM hub_interactions WHERE hub_token_id = ht.id) AS nb_interactions
    FROM hub_tokens ht
    JOIN reservation r ON ht.reservation_id = r.id
    JOIN liste_logements l ON ht.logement_id = l.id
    ORDER BY r.date_arrivee DESC
")->fetchAll(PDO::FETCH_ASSOC);

$resasSansToken = $pdo->query("
    SELECT r.id, r.prenom, r.nom, r.date_arrivee, r.date_depart, r.telephone, r.plateforme,
           r.logement_id, l.nom_du_logement
    FROM reservation r
    JOIN liste_logements l ON r.logement_id = l.id
    LEFT JOIN hub_tokens ht ON ht.reservation_id = r.id
    WHERE ht.id IS NULL
      AND r.date_depart >= CURDATE()
      AND r.statut = 'confirmée'
    ORDER BY r.date_arrivee ASC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

try {
    $statsTotal = $pdo->query("SELECT COUNT(*) FROM hub_tokens WHERE active = 1")->fetchColumn();
    $statsViews = $pdo->query("SELECT COALESCE(SUM(access_count), 0) FROM hub_tokens")->fetchColumn();
    $statsInteractions = $pdo->query("SELECT COUNT(*) FROM hub_interactions")->fetchColumn();
} catch (\PDOException $e) {
    $statsTotal = $statsViews = $statsInteractions = 0;
}

$recentInteractions = $pdo->query("
    SELECT hi.created_at, hi.action_type, hi.action_data,
           r.prenom, r.nom, r.telephone,
           l.nom_du_logement
    FROM hub_interactions hi
    JOIN hub_tokens ht ON hi.hub_token_id = ht.id
    JOIN reservation r ON hi.reservation_id = r.id
    JOIN liste_logements l ON ht.logement_id = l.id
    ORDER BY hi.created_at DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

$actionLabels = [
    'view' => ['label' => 'Vue', 'badge' => 'bg-light text-dark'],
    'cleaning_request' => ['label' => 'Demande menage', 'badge' => 'bg-warning'],
    'access_problem' => ['label' => 'Probleme acces', 'badge' => 'bg-danger'],
    'wifi_help' => ['label' => 'Probleme wifi', 'badge' => 'bg-warning'],
    'checkout_info' => ['label' => 'Infos depart', 'badge' => 'bg-info'],
    'other' => ['label' => 'Autre question', 'badge' => 'bg-primary'],
    'chat' => ['label' => 'Chat IA', 'badge' => 'bg-success'],
    'upsell_request' => ['label' => 'Demande upsell', 'badge' => 'bg-success'],
    'upsell_checkout' => ['label' => 'Achat upsell', 'badge' => 'bg-success'],
];
?>

<div class="container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <h2 class="mb-4"><i class="fas fa-concierge-bell text-primary"></i> HUB Sejours</h2>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-primary"><?= $statsTotal ?></div>
                    <div class="text-muted small">HUB actifs</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-success"><?= $statsViews ?></div>
                    <div class="text-muted small">Vues totales</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-warning"><?= $statsInteractions ?></div>
                    <div class="text-muted small">Interactions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-info"><?= count($resasSansToken) ?></div>
                    <div class="text-muted small">Resas sans HUB</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernieres interactions -->
    <?php
    $importantInteractions = array_filter($recentInteractions, fn($i) => $i['action_type'] !== 'view');
    if (!empty($importantInteractions)):
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger bg-opacity-10">
            <h5 class="mb-0"><i class="fas fa-bell text-danger"></i> Dernieres interactions (<?= count($importantInteractions) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Voyageur</th>
                            <th>Logement</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($importantInteractions as $inter):
                        $al = $actionLabels[$inter['action_type']] ?? ['label' => $inter['action_type'], 'badge' => 'bg-secondary'];
                        $details = '';
                        if ($inter['action_data']) {
                            $d = json_decode($inter['action_data'], true);
                            if (isset($d['message'])) $details = mb_substr($d['message'], 0, 100);
                            elseif (isset($d['upsell_name'])) $details = $d['upsell_name'];
                        }
                    ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d/m H:i', strtotime($inter['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($inter['prenom'] . ' ' . ($inter['nom'] ?? '')) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($inter['telephone'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($inter['nom_du_logement']) ?></td>
                            <td><span class="badge <?= $al['badge'] ?>"><?= $al['label'] ?></span></td>
                            <td class="small"><?= htmlspecialchars($details) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dernieres vues -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-eye text-info"></i> Dernieres vues
                <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#viewsCollapse">
                    Afficher/Masquer
                </button>
            </h5>
        </div>
        <div class="collapse" id="viewsCollapse">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php
                        $views = array_filter($recentInteractions, fn($i) => $i['action_type'] === 'view');
                        foreach (array_slice($views, 0, 15) as $v): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m H:i', strtotime($v['created_at'])) ?></td>
                                <td><?= htmlspecialchars($v['prenom'] . ' ' . ($v['nom'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($v['nom_du_logement']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservations sans HUB -->
    <?php if (!empty($resasSansToken)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning bg-opacity-10">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-warning"></i> Reservations sans HUB (a venir / en cours)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Voyageur</th>
                            <th>Logement</th>
                            <th>Arrivee</th>
                            <th>Depart</th>
                            <th>Plateforme</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resasSansToken as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['prenom'] . ' ' . ($r['nom'] ?? '')) ?></strong></td>
                            <td><?= htmlspecialchars($r['nom_du_logement']) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['date_arrivee'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($r['date_depart'])) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['plateforme'] ?? '?') ?></span></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="generate_token">
                                    <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="logement_id" value="<?= $r['logement_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Generer HUB
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tous les HUB tokens -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-link text-primary"></i> HUB generes</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Voyageur</th>
                            <th>Logement</th>
                            <th>Sejour</th>
                            <th>Vues</th>
                            <th>Interactions</th>
                            <th>Dernier acces</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($hubTokens as $ht):
                        $hubUrl = rtrim($appUrl, '/') . '/frenchybot/hub/?id=' . $ht['token'];
                        $isActive = $ht['active'];
                        $isPast = strtotime($ht['date_depart']) < strtotime('today');
                    ?>
                        <tr class="<?= !$isActive ? 'table-secondary' : ($isPast ? 'opacity-75' : '') ?>">
                            <td><strong><?= htmlspecialchars($ht['prenom'] . ' ' . ($ht['nom'] ?? '')) ?></strong></td>
                            <td><?= htmlspecialchars($ht['nom_du_logement']) ?></td>
                            <td>
                                <?= date('d/m', strtotime($ht['date_arrivee'])) ?> → <?= date('d/m/Y', strtotime($ht['date_depart'])) ?>
                            </td>
                            <td><span class="badge bg-info"><?= $ht['access_count'] ?></span></td>
                            <td><span class="badge bg-warning"><?= $ht['nb_interactions'] ?></span></td>
                            <td><?= $ht['last_accessed_at'] ? date('d/m H:i', strtotime($ht['last_accessed_at'])) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="copyHubUrl('<?= htmlspecialchars($hubUrl) ?>', this)" title="Copier le lien">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <a href="<?= htmlspecialchars($hubUrl) ?>" target="_blank" class="btn btn-outline-success" title="Voir le HUB">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="token_id" value="<?= $ht['id'] ?>">
                                        <?php if ($isActive): ?>
                                            <input type="hidden" name="action" value="deactivate_token">
                                            <button type="submit" class="btn btn-outline-danger" title="Desactiver">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="reactivate_token">
                                            <button type="submit" class="btn btn-outline-success" title="Reactiver">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($hubTokens)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucun HUB genere pour le moment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function copyHubUrl(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-primary');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    });
}
</script>
