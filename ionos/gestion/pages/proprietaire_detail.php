<?php
/**
 * Fiche detail proprietaire — FrenchyConciergerie
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

$proprietaire_id = (int) ($_GET['id'] ?? 0);
if (!$proprietaire_id) {
    header("Location: proprietaires.php");
    exit;
}

// Charger le proprietaire
try {
    $stmt = $conn->prepare("SELECT * FROM FC_proprietaires WHERE id = ?");
    $stmt->execute([$proprietaire_id]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $prop = null;
}

if (!$prop) {
    header("Location: proprietaires.php?error=" . urlencode('Propriétaire introuvable.'));
    exit;
}

// Logements
$logements = [];
try {
    $stmt = $conn->prepare("SELECT * FROM liste_logements WHERE proprietaire_id = ? ORDER BY nom_du_logement");
    $stmt->execute([$proprietaire_id]);
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Commission config
$commConfig = null;
try {
    $stmt = $conn->prepare("SELECT * FROM proprietaire_commission_config WHERE proprietaire_id = ?");
    $stmt->execute([$proprietaire_id]);
    $commConfig = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Onboarding source
$onboarding = null;
try {
    $stmt = $conn->prepare("SELECT * FROM onboarding_requests WHERE proprietaire_id = ? ORDER BY completed_at DESC LIMIT 1");
    $stmt->execute([$proprietaire_id]);
    $onboarding = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Code parrainage
$codeParrain = '';
try {
    $stmt = $conn->prepare("SELECT code, nb_utilisations FROM codes_parrainage WHERE proprietaire_id = ?");
    $stmt->execute([$proprietaire_id]);
    $parrainage = $stmt->fetch(PDO::FETCH_ASSOC);
    $codeParrain = $parrainage['code'] ?? '';
    $nbFilleuls = (int)($parrainage['nb_utilisations'] ?? 0);
} catch (PDOException $e) {}

// Reservations recentes
$reservations = [];
try {
    $stmt = $conn->prepare("
        SELECT r.*, l.nom_du_logement
        FROM ical_reservations r
        JOIN liste_logements l ON r.logement_id = l.id
        WHERE l.proprietaire_id = ?
        ORDER BY r.checkin DESC LIMIT 10
    ");
    $stmt->execute([$proprietaire_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Taches onboarding
$tasks = [];
if ($onboarding) {
    try {
        $stmt = $conn->prepare("SELECT * FROM onboarding_tasks WHERE request_id = ? ORDER BY id");
        $stmt->execute([$onboarding['id']]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$nomComplet = trim(($prop['prenom'] ?? '') . ' ' . ($prop['nom'] ?? ''));
$packLabel = $commConfig ? ucfirst($commConfig['pack'] ?? 'autonome') : '-';
$commEffective = $commConfig ? (float)$commConfig['commission_effective'] : (float)($prop['commission'] ?? 10);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nomComplet) ?> — Proprietaire — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .detail-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; border: 1px solid #e9ecef; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .detail-card h5 { font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6c757d; min-width: 140px; }
        .info-value { font-weight: 600; text-align: right; word-break: break-all; }
        .stat-box { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .stat-box .number { font-size: 2rem; font-weight: 800; }
        .stat-box .label { font-size: 0.8rem; color: #6c757d; }
        .badge-task { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-done { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .annonce-link { display: inline-block; background: #f0f7ff; padding: 6px 12px; border-radius: 8px; margin: 3px 0; font-size: 0.85em; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="proprietaires.php" class="text-muted text-decoration-none" style="font-size:0.85rem;">
                <i class="fas fa-arrow-left"></i> Retour proprietaires
            </a>
            <h2 class="mt-1">
                <i class="fas fa-user-tie text-primary"></i>
                <?= htmlspecialchars($nomComplet) ?>
                <?php if ($prop['actif'] ?? 1): ?>
                    <span class="badge bg-success" style="font-size:0.5em;">Actif</span>
                <?php else: ?>
                    <span class="badge bg-secondary" style="font-size:0.5em;">Inactif</span>
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0">
                Pack <?= htmlspecialchars($packLabel) ?> · <?= $commEffective ?>% commission
                <?php if ($codeParrain): ?> · Code : <strong><?= htmlspecialchars($codeParrain) ?></strong><?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="proprietaires.php?edit=<?= $proprietaire_id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <?php if (!empty($prop['email'])): ?>
            <a href="mailto:<?= htmlspecialchars($prop['email']) ?>" class="btn btn-outline-primary">
                <i class="fas fa-envelope"></i> Email
            </a>
            <?php endif; ?>
            <?php if (!empty($prop['telephone'])): ?>
            <a href="tel:<?= htmlspecialchars($prop['telephone']) ?>" class="btn btn-outline-success">
                <i class="fas fa-phone"></i> Appeler
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-box">
                <div class="number text-primary"><?= count($logements) ?></div>
                <div class="label">Logement(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="number" style="color:#28a745;"><?= $commEffective ?>%</div>
                <div class="label">Commission effective</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="number text-info"><?= count($reservations) ?></div>
                <div class="label">Reservations recentes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <div class="number text-warning"><?= $nbFilleuls ?? 0 ?></div>
                <div class="label">Filleul(s)</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche -->
        <div class="col-md-6">

            <!-- Coordonnees -->
            <div class="detail-card">
                <h5><i class="fas fa-address-card text-primary"></i> Coordonnees</h5>
                <div class="info-row"><span class="info-label">Nom</span><span class="info-value"><?= htmlspecialchars($nomComplet) ?></span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($prop['email'] ?? '-') ?></span></div>
                <div class="info-row"><span class="info-label">Telephone</span><span class="info-value"><?= htmlspecialchars($prop['telephone'] ?? '-') ?></span></div>
                <div class="info-row"><span class="info-label">Adresse</span><span class="info-value"><?= htmlspecialchars(implode(', ', array_filter([$prop['adresse'] ?? '', $prop['code_postal'] ?? '', $prop['ville'] ?? '']))) ?: '-' ?></span></div>
                <?php if (!empty($prop['societe'])): ?>
                <div class="info-row"><span class="info-label">Societe</span><span class="info-value"><?= htmlspecialchars($prop['societe']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($prop['siret'])): ?>
                <div class="info-row"><span class="info-label">SIRET</span><span class="info-value"><?= htmlspecialchars($prop['siret']) ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Bancaire -->
            <?php if (!empty($prop['rib_iban'])): ?>
            <div class="detail-card">
                <h5><i class="fas fa-university text-success"></i> Informations bancaires</h5>
                <div class="info-row"><span class="info-label">IBAN</span><span class="info-value"><?= htmlspecialchars($prop['rib_iban']) ?></span></div>
                <div class="info-row"><span class="info-label">BIC</span><span class="info-value"><?= htmlspecialchars($prop['rib_bic'] ?? '-') ?></span></div>
                <div class="info-row"><span class="info-label">Banque</span><span class="info-value"><?= htmlspecialchars($prop['rib_banque'] ?? '-') ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Annonces existantes (depuis onboarding) -->
            <?php if ($onboarding): ?>
            <div class="detail-card">
                <h5><i class="fas fa-bullhorn text-warning"></i> Annonces en ligne</h5>
                <?php
                $annonceExistante = (int)($onboarding['annonce_existante'] ?? 0);
                $plateformes = json_decode($onboarding['annonce_plateformes'] ?? '[]', true) ?: [];
                $expLabels = ['jamais' => 'Jamais', 'moins_1an' => '< 1 an', '1_3ans' => '1-3 ans', '3_5ans' => '3-5 ans', 'plus_5ans' => '5+ ans'];
                ?>
                <div class="info-row">
                    <span class="info-label">Annonce existante</span>
                    <span class="info-value">
                        <?php if ($annonceExistante): ?>
                            <span class="badge bg-success">Oui</span> <?= htmlspecialchars(implode(', ', array_map('ucfirst', $plateformes))) ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non — premier lancement</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($onboarding['annonce_url_airbnb'])): ?>
                <div class="info-row">
                    <span class="info-label">Airbnb</span>
                    <span class="info-value"><a href="<?= htmlspecialchars($onboarding['annonce_url_airbnb']) ?>" target="_blank" class="annonce-link"><i class="fab fa-airbnb"></i> Voir l'annonce</a></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($onboarding['annonce_url_booking'])): ?>
                <div class="info-row">
                    <span class="info-label">Booking</span>
                    <span class="info-value"><a href="<?= htmlspecialchars($onboarding['annonce_url_booking']) ?>" target="_blank" class="annonce-link"><i class="fas fa-bed"></i> Voir l'annonce</a></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($onboarding['annonce_url_autre'])): ?>
                <div class="info-row">
                    <span class="info-label">Autre</span>
                    <span class="info-value"><a href="<?= htmlspecialchars($onboarding['annonce_url_autre']) ?>" target="_blank" class="annonce-link"><i class="fas fa-external-link-alt"></i> Voir</a></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Experience</span>
                    <span class="info-value"><?= htmlspecialchars($expLabels[$onboarding['experience_location'] ?? ''] ?? 'Non renseignee') ?></span>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Colonne droite -->
        <div class="col-md-6">

            <!-- Commission -->
            <?php if ($commConfig): ?>
            <div class="detail-card">
                <h5><i class="fas fa-percentage text-success"></i> Commission</h5>
                <div class="info-row"><span class="info-label">Pack</span><span class="info-value"><span class="badge bg-primary"><?= htmlspecialchars(ucfirst($commConfig['pack'])) ?></span></span></div>
                <div class="info-row"><span class="info-label">Commission de base</span><span class="info-value"><?= (float)$commConfig['commission_base'] ?>%</span></div>
                <?php if ((float)($commConfig['reduction_parrainage'] ?? 0) > 0): ?>
                <div class="info-row"><span class="info-label">Reduction parrainage</span><span class="info-value" style="color:#28a745;">-<?= (float)$commConfig['reduction_parrainage'] ?>%</span></div>
                <?php endif; ?>
                <div class="info-row"><span class="info-label">Commission effective</span><span class="info-value" style="font-size:1.1em;color:#1976d2;"><?= (float)$commConfig['commission_effective'] ?>%</span></div>
            </div>
            <?php endif; ?>

            <!-- Logements -->
            <div class="detail-card">
                <h5><i class="fas fa-home text-info"></i> Logements (<?= count($logements) ?>)</h5>
                <?php if (empty($logements)): ?>
                    <p class="text-muted">Aucun logement attribue.</p>
                <?php else: ?>
                    <?php foreach ($logements as $log): ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:12px;margin-bottom:8px;">
                        <div style="font-weight:600;"><?= htmlspecialchars($log['nom_du_logement'] ?? 'Sans nom') ?></div>
                        <div style="font-size:0.85em;color:#666;"><?= htmlspecialchars($log['adresse'] ?? '') ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Taches onboarding -->
            <?php if (!empty($tasks)): ?>
            <div class="detail-card">
                <h5><i class="fas fa-tasks text-warning"></i> Taches post-onboarding</h5>
                <?php foreach ($tasks as $task): ?>
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f0f0f0;">
                    <span style="font-size:0.9em;"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($task['type']))) ?></span>
                    <span class="badge-task <?= $task['statut'] === 'done' ? 'badge-done' : 'badge-pending' ?>">
                        <?= $task['statut'] === 'done' ? 'Fait' : 'A faire' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Reservations recentes -->
            <?php if (!empty($reservations)): ?>
            <div class="detail-card">
                <h5><i class="fas fa-calendar-check text-primary"></i> Dernieres reservations</h5>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Logement</th><th>Arrivee</th><th>Depart</th><th>Source</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reservations as $resa): ?>
                            <tr>
                                <td style="font-size:0.85em;"><?= htmlspecialchars($resa['nom_du_logement'] ?? '') ?></td>
                                <td style="font-size:0.85em;"><?= $resa['checkin'] ? date('d/m/Y', strtotime($resa['checkin'])) : '-' ?></td>
                                <td style="font-size:0.85em;"><?= $resa['checkout'] ? date('d/m/Y', strtotime($resa['checkout'])) : '-' ?></td>
                                <td style="font-size:0.85em;"><?= htmlspecialchars($resa['source'] ?? $resa['platform'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <?php if (!empty($prop['notes'])): ?>
            <div class="detail-card">
                <h5><i class="fas fa-sticky-note text-secondary"></i> Notes</h5>
                <p style="white-space:pre-wrap;font-size:0.9em;"><?= htmlspecialchars($prop['notes']) ?></p>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
