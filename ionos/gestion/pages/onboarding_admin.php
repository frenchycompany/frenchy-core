<?php
/**
 * Gestion des onboardings — FrenchyConciergerie
 * Liste, detail, relance des demandes d'onboarding
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

// S'assurer que les tables existent
require_once __DIR__ . '/../onboarding/includes/onboarding-helper.php';
onboarding_ensure_tables($conn);

$feedback = '';

// ============================================================
// ACTIONS POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // Relancer un onboarding abandonne
    if (isset($_POST['relancer'])) {
        $id = (int) $_POST['request_id'];
        try {
            $conn->prepare("UPDATE onboarding_requests SET statut = 'en_cours' WHERE id = ? AND statut = 'abandonne'")
                ->execute([$id]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Onboarding relance avec succes.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // Supprimer un brouillon
    if (isset($_POST['supprimer'])) {
        $id = (int) $_POST['request_id'];
        try {
            $conn->prepare("DELETE FROM onboarding_requests WHERE id = ? AND statut IN ('brouillon','abandonne')")
                ->execute([$id]);
            $feedback = '<div class="alert alert-success alert-dismissible fade show">Onboarding supprime.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// DONNEES
// ============================================================

// Filtres
$filtre_statut = $_GET['statut'] ?? '';
$filtre_search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($filtre_statut) {
    $where[] = "statut = ?";
    $params[] = $filtre_statut;
}
if ($filtre_search) {
    $where[] = "(prenom LIKE ? OR nom LIKE ? OR email LIKE ? OR ville LIKE ?)";
    $s = "%$filtre_search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $conn->prepare("SELECT * FROM onboarding_requests $whereClause ORDER BY created_at DESC LIMIT 100");
    $stmt->execute($params);
    $onboardings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $onboardings = [];
}

// Stats
$stats = ['total' => 0, 'brouillon' => 0, 'en_cours' => 0, 'termine' => 0, 'abandonne' => 0];
try {
    $res = $conn->query("SELECT statut, COUNT(*) as nb FROM onboarding_requests GROUP BY statut");
    foreach ($res as $r) {
        $stats[$r['statut']] = (int)$r['nb'];
        $stats['total'] += (int)$r['nb'];
    }
} catch (PDOException $e) {}

// Detail ?
$detail = null;
if (isset($_GET['detail'])) {
    $detailId = (int)$_GET['detail'];
    try {
        $stmt = $conn->prepare("SELECT * FROM onboarding_requests WHERE id = ?");
        $stmt->execute([$detailId]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$statusColors = [
    'brouillon' => 'secondary',
    'en_cours' => 'primary',
    'termine' => 'success',
    'abandonne' => 'danger',
];
$statusIcons = [
    'brouillon' => 'fa-pencil-alt',
    'en_cours' => 'fa-spinner',
    'termine' => 'fa-check-circle',
    'abandonne' => 'fa-times-circle',
];
$expLabels = ['jamais' => 'Jamais', 'moins_1an' => '< 1 an', '1_3ans' => '1-3 ans', '3_5ans' => '3-5 ans', 'plus_5ans' => '5+ ans'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Onboardings — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stat-card { text-align: center; padding: 18px; border-radius: 10px; background: #fff; border: 1px solid #e9ecef; }
        .stat-card .number { font-size: 2rem; font-weight: 800; }
        .stat-card .label { font-size: 0.8rem; color: #6c757d; }
        .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .onb-row { cursor: pointer; transition: background 0.15s; }
        .onb-row:hover { background: #f0f7ff !important; }
        .progress-bar-custom { height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; }
        .progress-bar-custom .fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
        .detail-section { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 15px; }
        .detail-section h6 { font-weight: 700; margin-bottom: 12px; }
        .detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .detail-row:last-child { border-bottom: none; }
        .annonce-link { display: inline-block; background: #e3f2fd; padding: 4px 10px; border-radius: 6px; font-size: 0.85em; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-user-plus text-success"></i> Gestion des onboardings</h2>
            <p class="text-muted mb-0">Suivi des inscriptions proprietaires</p>
        </div>
        <a href="../onboarding/index.php" target="_blank" class="btn btn-outline-success">
            <i class="fas fa-external-link-alt"></i> Voir le formulaire
        </a>
    </div>

    <?= $feedback ?>

    <!-- Stats -->
    <div class="row mb-4 g-3">
        <div class="col-md-2 col-6">
            <a href="?statut=" class="text-decoration-none">
                <div class="stat-card">
                    <div class="number"><?= $stats['total'] ?></div>
                    <div class="label">Total</div>
                </div>
            </a>
        </div>
        <?php foreach (['brouillon', 'en_cours', 'termine', 'abandonne'] as $s): ?>
        <div class="col-md-2 col-6">
            <a href="?statut=<?= $s ?>" class="text-decoration-none">
                <div class="stat-card" style="<?= $filtre_statut === $s ? 'border-color: var(--bs-' . $statusColors[$s] . '); border-width: 2px;' : '' ?>">
                    <div class="number text-<?= $statusColors[$s] ?>"><?= $stats[$s] ?></div>
                    <div class="label"><?= ucfirst(str_replace('_', ' ', $s)) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <form class="row mb-3 g-2" method="GET">
        <?php if ($filtre_statut): ?><input type="hidden" name="statut" value="<?= htmlspecialchars($filtre_statut) ?>"><?php endif; ?>
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" name="q" placeholder="Rechercher (nom, email, ville)..." value="<?= htmlspecialchars($filtre_search) ?>">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        </div>
        <?php if ($filtre_statut || $filtre_search): ?>
        <div class="col-auto">
            <a href="onboarding_admin.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> Reset</a>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($detail): ?>
    <!-- ============================================================ -->
    <!-- VUE DETAIL -->
    <!-- ============================================================ -->
    <?php
        $d = $detail;
        $dNom = htmlspecialchars(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? '')));
        $dPlateformes = json_decode($d['annonce_plateformes'] ?? '[]', true) ?: [];
        $dEquipements = json_decode($d['equipements'] ?? '{}', true) ?: [];
        $dEquipCount = count(array_filter($dEquipements));
        $packs = onboarding_get_packs();
        $dPackInfo = $packs[$d['pack'] ?? 'autonome'] ?? $packs['autonome'];
    ?>
    <div class="mb-3">
        <a href="onboarding_admin.php<?= $filtre_statut ? '?statut=' . urlencode($filtre_statut) : '' ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Retour a la liste
        </a>
        <?php if ($d['proprietaire_id']): ?>
        <a href="proprietaire_detail.php?id=<?= (int)$d['proprietaire_id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-user-tie"></i> Fiche proprietaire
        </a>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-<?= $statusColors[$d['statut']] ?> text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas <?= $statusIcons[$d['statut']] ?>"></i> <?= $dNom ?: 'Sans nom' ?></h5>
            <span class="badge bg-white text-<?= $statusColors[$d['statut']] ?>"><?= ucfirst(str_replace('_', ' ', $d['statut'])) ?> · Etape <?= (int)$d['etape_courante'] ?>/6</span>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Col gauche -->
                <div class="col-md-6">
                    <div class="detail-section">
                        <h6><i class="fas fa-home text-success"></i> Le bien</h6>
                        <div class="detail-row"><span>Adresse</span><span><?= htmlspecialchars(implode(', ', array_filter([$d['adresse'], $d['code_postal'], $d['ville']]))) ?: '-' ?></span></div>
                        <div class="detail-row"><span>Typologie</span><span><?= htmlspecialchars($d['typologie'] ?? '-') ?> · <?= (int)($d['superficie'] ?? 0) ?> m2</span></div>
                        <div class="detail-row"><span>Couchages</span><span><?= (int)($d['nb_couchages'] ?? 0) ?></span></div>
                        <div class="detail-row"><span>Equipements</span><span><?= $dEquipCount ?> selectionne(s)</span></div>
                    </div>

                    <div class="detail-section">
                        <h6><i class="fas fa-bullhorn text-warning"></i> Annonces existantes</h6>
                        <?php $annonceOui = (int)($d['annonce_existante'] ?? 0); ?>
                        <div class="detail-row">
                            <span>Deja en ligne</span>
                            <span>
                                <?php if ($annonceOui): ?>
                                    <span class="badge bg-success">Oui</span> <?= htmlspecialchars(implode(', ', array_map('ucfirst', $dPlateformes))) ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Non</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($d['annonce_url_airbnb'])): ?>
                        <div class="detail-row"><span>Airbnb</span><span><a href="<?= htmlspecialchars($d['annonce_url_airbnb']) ?>" target="_blank" class="annonce-link"><i class="fab fa-airbnb"></i> Ouvrir</a></span></div>
                        <?php endif; ?>
                        <?php if (!empty($d['annonce_url_booking'])): ?>
                        <div class="detail-row"><span>Booking</span><span><a href="<?= htmlspecialchars($d['annonce_url_booking']) ?>" target="_blank" class="annonce-link"><i class="fas fa-bed"></i> Ouvrir</a></span></div>
                        <?php endif; ?>
                        <?php if (!empty($d['annonce_url_autre'])): ?>
                        <div class="detail-row"><span>Autre</span><span><a href="<?= htmlspecialchars($d['annonce_url_autre']) ?>" target="_blank" class="annonce-link"><i class="fas fa-link"></i> Ouvrir</a></span></div>
                        <?php endif; ?>
                        <div class="detail-row"><span>Experience</span><span><?= htmlspecialchars($expLabels[$d['experience_location'] ?? ''] ?? '-') ?></span></div>
                    </div>
                </div>

                <!-- Col droite -->
                <div class="col-md-6">
                    <div class="detail-section">
                        <h6><i class="fas fa-user text-primary"></i> Proprietaire</h6>
                        <div class="detail-row"><span>Nom</span><span><?= $dNom ?: '-' ?></span></div>
                        <div class="detail-row"><span>Email</span><span><?= htmlspecialchars($d['email'] ?? '-') ?></span></div>
                        <div class="detail-row"><span>Telephone</span><span><?= htmlspecialchars($d['telephone'] ?? '-') ?></span></div>
                        <?php if (!empty($d['societe'])): ?>
                        <div class="detail-row"><span>Societe</span><span><?= htmlspecialchars($d['societe']) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h6><i class="fas fa-box text-info"></i> Pack & Tarifs</h6>
                        <div class="detail-row"><span>Pack</span><span><span class="badge" style="background:<?= $dPackInfo['color'] ?>;"><?= htmlspecialchars($dPackInfo['label']) ?></span></span></div>
                        <div class="detail-row"><span>Commission</span><span style="font-weight:700;"><?= (float)($d['commission_base'] ?? 10) ?>%</span></div>
                        <div class="detail-row"><span>Prix souhaite</span><span><?= $d['prix_souhaite'] ? number_format((float)$d['prix_souhaite'], 0) . ' EUR/nuit' : '-' ?></span></div>
                        <div class="detail-row"><span>Prix min/max</span><span><?= $d['prix_min'] ? number_format((float)$d['prix_min'], 0) : '-' ?> / <?= $d['prix_max'] ? number_format((float)$d['prix_max'], 0) : '-' ?> EUR</span></div>
                        <div class="detail-row"><span>Prix dynamique</span><span><?= ($d['accepte_prix_dynamique'] ?? 1) ? 'Oui' : 'Non' ?></span></div>
                        <?php if (!empty($d['code_parrain'])): ?>
                        <div class="detail-row"><span>Code parrain</span><span style="color:#28a745;font-weight:700;"><?= htmlspecialchars($d['code_parrain']) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h6><i class="fas fa-clock text-secondary"></i> Tracking</h6>
                        <div class="detail-row"><span>Cree le</span><span><?= $d['created_at'] ? date('d/m/Y H:i', strtotime($d['created_at'])) : '-' ?></span></div>
                        <div class="detail-row"><span>Derniere MAJ</span><span><?= $d['updated_at'] ? date('d/m/Y H:i', strtotime($d['updated_at'])) : '-' ?></span></div>
                        <div class="detail-row"><span>Termine le</span><span><?= $d['completed_at'] ? date('d/m/Y H:i', strtotime($d['completed_at'])) : '-' ?></span></div>
                        <div class="detail-row"><span>IP</span><span style="font-size:0.8em;"><?= htmlspecialchars($d['ip_address'] ?? '-') ?></span></div>
                    </div>

                    <!-- Actions -->
                    <?php if ($d['statut'] === 'abandonne'): ?>
                    <form method="POST" class="d-inline">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="request_id" value="<?= (int)$d['id'] ?>">
                        <button type="submit" name="relancer" class="btn btn-warning"><i class="fas fa-redo"></i> Relancer</button>
                    </form>
                    <?php endif; ?>
                    <?php if (in_array($d['statut'], ['brouillon', 'abandonne'])): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet onboarding ?');">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="request_id" value="<?= (int)$d['id'] ?>">
                        <button type="submit" name="supprimer" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Supprimer</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- VUE LISTE -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Statut</th>
                            <th>Nom</th>
                            <th>Email / Tel</th>
                            <th>Bien</th>
                            <th>Pack</th>
                            <th>Annonce</th>
                            <th>Progression</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($onboardings as $o):
                        $oNom = trim(($o['prenom'] ?? '') . ' ' . ($o['nom'] ?? ''));
                        $oPlat = json_decode($o['annonce_plateformes'] ?? '[]', true) ?: [];
                        $oProg = (int)($o['progression'] ?? 0);
                    ?>
                        <tr class="onb-row" onclick="window.location='?detail=<?= $o['id'] ?>'">
                            <td>
                                <span class="badge bg-<?= $statusColors[$o['statut']] ?>">
                                    <i class="fas <?= $statusIcons[$o['statut']] ?>"></i>
                                    <?= ucfirst(str_replace('_', ' ', $o['statut'])) ?>
                                </span>
                            </td>
                            <td style="font-weight:600;"><?= htmlspecialchars($oNom ?: '—') ?></td>
                            <td style="font-size:0.85em;">
                                <?= htmlspecialchars($o['email'] ?? '') ?><br>
                                <span class="text-muted"><?= htmlspecialchars($o['telephone'] ?? '') ?></span>
                            </td>
                            <td style="font-size:0.85em;">
                                <?= htmlspecialchars(($o['typologie'] ?? '') . ' ' . ($o['ville'] ?? '')) ?>
                            </td>
                            <td><span class="badge bg-<?= $statusColors['en_cours'] ?>"><?= ucfirst($o['pack'] ?? '-') ?></span></td>
                            <td>
                                <?php if ((int)($o['annonce_existante'] ?? 0)): ?>
                                    <span class="badge bg-success" title="<?= htmlspecialchars(implode(', ', $oPlat)) ?>">
                                        <i class="fas fa-check"></i> <?= count($oPlat) ?> plateforme(s)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.8em;">Nouveau</span>
                                <?php endif; ?>
                            </td>
                            <td style="min-width:100px;">
                                <div class="progress-bar-custom">
                                    <div class="fill bg-<?= $statusColors[$o['statut']] ?>" style="width:<?= $oProg ?>%;"></div>
                                </div>
                                <small class="text-muted"><?= $oProg ?>% · Etape <?= (int)$o['etape_courante'] ?>/6</small>
                            </td>
                            <td style="font-size:0.8em;color:#999;">
                                <?= $o['created_at'] ? date('d/m/Y', strtotime($o['created_at'])) : '' ?>
                            </td>
                            <td>
                                <?php if ($o['proprietaire_id']): ?>
                                <a href="proprietaire_detail.php?id=<?= (int)$o['proprietaire_id'] ?>" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">
                                    <i class="fas fa-user-tie"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($onboardings)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Aucun onboarding trouve.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
