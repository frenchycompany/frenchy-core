<?php
/**
 * Recommandations ville pour un logement
 * Page publique accessible aux voyageurs via lien dans le book de bienvenue
 * Affiche les recommandations locales associees a la ville du logement
 */

// Mode public : pas besoin de login
$public = isset($_GET['token']);

if ($public) {
    // Acces public avec token
    require_once __DIR__ . '/../includes/env_loader.php';
    require_once __DIR__ . '/../db/connection.php';
    require_once __DIR__ . '/../includes/rpi_db.php';
    $pdo = getRpiPdo();
} else {
    // Acces interne (admin)
    include '../config.php';
    include '../pages/menu.php';
    require_once __DIR__ . '/../includes/rpi_bridge.php';
}

// Verifier le token ou l'acces admin
$logement_id = (int)($_GET['logement'] ?? 0);
$logement = null;
$ville = null;
$recommandations = [];
$categories_labels = [
    'partenaire' => ['Partenaires', 'fa-handshake', '#0d6efd'],
    'restaurant' => ['Restaurants', 'fa-utensils', '#198754'],
    'activite'   => ['Activites', 'fa-hiking', '#fd7e14'],
];

if ($public) {
    // Verifier le token
    $token = $_GET['token'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT id, nom_du_logement, ville_id FROM liste_logements WHERE MD5(CONCAT(id, '-frenchybnb')) = ? LIMIT 1");
        $stmt->execute([$token]);
        $logement = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('recommandations_logement.php: ' . $e->getMessage()); }

    if (!$logement) {
        http_response_code(404);
        echo '<h1>Page non trouvee</h1>';
        exit;
    }
    $logement_id = $logement['id'];
} else {
    if ($logement_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id, nom_du_logement, ville_id FROM liste_logements WHERE id = ?");
            $stmt->execute([$logement_id]);
            $logement = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('recommandations_logement.php: ' . $e->getMessage()); }
    }
}

// Recuperer la ville et les recommandations
if ($logement && $logement['ville_id']) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM villes WHERE id = ?");
        $stmt->execute([$logement['ville_id']]);
        $ville = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ville) {
            $stmt = $pdo->prepare("
                SELECT * FROM ville_recommandations
                WHERE ville_id = ? AND actif = 1
                ORDER BY categorie, ordre, nom
            ");
            $stmt->execute([$ville['id']]);
            $recommandations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { error_log('recommandations_logement.php: ' . $e->getMessage()); }
}

// Grouper par categorie
$par_categorie = [];
foreach ($recommandations as $r) {
    $par_categorie[$r['categorie']][] = $r;
}

if (!$public && !$logement):
    // Vue admin : selection du logement
    $logements = [];
    try {
        $logements = $pdo->query("
            SELECT l.id, l.nom_du_logement, l.ville_id, v.nom as ville_nom
            FROM liste_logements l
            LEFT JOIN villes v ON l.ville_id = v.id
            ORDER BY l.nom_du_logement
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('recommandations_logement.php: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommandations — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-3">
    <h2><i class="fas fa-map-marked-alt"></i> Recommandations par logement</h2>
    <p class="text-muted">Selectionnez un logement pour voir les recommandations associees a sa ville</p>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Logement</th>
                                    <th>Ville</th>
                                    <th>Lien public</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($logements as $l):
                                $token = md5($l['id'] . '-frenchybnb');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong></td>
                                <td>
                                    <?php if ($l['ville_nom']): ?>
                                    <span class="badge bg-success"><?= htmlspecialchars($l['ville_nom']) ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark">Non associe</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($l['ville_id']): ?>
                                    <code class="small"><?= htmlspecialchars("recommandations_logement.php?token=$token") ?></code>
                                    <?php else: ?>
                                    <span class="text-muted small">Associez d'abord une ville</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($l['ville_id']): ?>
                                    <a href="?logement=<?= $l['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
                                    <a href="?token=<?= $token ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-external-link-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle"></i> Comment ca marche</h6></div>
                <div class="card-body">
                    <ol class="small">
                        <li>Associez une <strong>ville</strong> a chaque logement dans la page <a href="villes.php">Villes</a></li>
                        <li>Ajoutez des <strong>recommandations</strong> (restaurants, activites, partenaires) pour chaque ville</li>
                        <li>Copiez le <strong>lien public</strong> dans le book de bienvenue du logement</li>
                        <li>Les voyageurs accedent aux recommandations locales via ce lien</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
    exit;
endif;

// === Affichage des recommandations ===
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($logement['nom_du_logement'] ?? 'Recommandations') ?> — Nos recommandations</title>
    <?php if ($public): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>
    <style>
        .reco-header {
            background: linear-gradient(135deg, #1976d2, #0d47a1);
            color: #fff; padding: 40px 20px; text-align: center; border-radius: 0 0 20px 20px;
            margin-bottom: 24px;
        }
        .reco-header h1 { margin: 0 0 8px; font-size: 1.6em; }
        .reco-header p { opacity: 0.85; margin: 0; }
        .cat-section { margin-bottom: 30px; }
        .cat-title {
            font-size: 1.2em; font-weight: 700; margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .reco-card {
            background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-left: 4px solid;
        }
        .reco-card h5 { margin: 0 0 4px; font-size: 1em; }
        .reco-card .desc { font-size: 0.9em; color: #555; margin-bottom: 6px; }
        .reco-card .meta { font-size: 0.8em; color: #888; }
        .reco-card .meta a { color: #1976d2; text-decoration: none; }
        body.public-page { background: #f4f6f9; }
        .reco-container { max-width: 700px; margin: 0 auto; padding: 0 16px 40px; }
    </style>
</head>
<body class="<?= $public ? 'public-page' : '' ?>">

<?php if ($public): ?>
<div class="reco-header">
    <h1><i class="fas fa-map-marked-alt"></i> Nos recommandations</h1>
    <p><?= htmlspecialchars($logement['nom_du_logement']) ?><?= $ville ? ' — ' . htmlspecialchars($ville['nom']) : '' ?></p>
</div>
<?php else: ?>
<div class="container-fluid mt-3">
    <a href="recommandations_logement.php" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-arrow-left"></i> Retour</a>
    <h3><?= htmlspecialchars($logement['nom_du_logement']) ?> — Recommandations</h3>
<?php endif; ?>

<div class="reco-container">
    <?php if (empty($recommandations)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-compass fa-3x mb-3"></i>
            <h5>Pas encore de recommandations</h5>
            <p>Les recommandations pour cette ville seront bientot disponibles</p>
        </div>
    <?php else: ?>
        <?php foreach ($par_categorie as $cat => $items):
            [$cat_label, $cat_icon, $cat_color] = $categories_labels[$cat] ?? [$cat, 'fa-star', '#666'];
        ?>
        <div class="cat-section">
            <div class="cat-title" style="color:<?= $cat_color ?>">
                <i class="fas <?= $cat_icon ?>"></i> <?= $cat_label ?>
            </div>
            <?php foreach ($items as $item): ?>
            <div class="reco-card" style="border-left-color:<?= $cat_color ?>">
                <h5><?= htmlspecialchars($item['nom']) ?></h5>
                <?php if ($item['description']): ?>
                <div class="desc"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                <?php endif; ?>
                <div class="meta">
                    <?php if ($item['adresse']): ?>
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['adresse']) ?> &nbsp;
                    <?php endif; ?>
                    <?php if ($item['telephone']): ?>
                    <a href="tel:<?= htmlspecialchars($item['telephone']) ?>"><i class="fas fa-phone"></i> <?= htmlspecialchars($item['telephone']) ?></a> &nbsp;
                    <?php endif; ?>
                    <?php if ($item['site_web']): ?>
                    <a href="<?= htmlspecialchars($item['site_web']) ?>" target="_blank"><i class="fas fa-globe"></i> Site web</a> &nbsp;
                    <?php endif; ?>
                    <?php if ($item['prix_indicatif']): ?>
                    <span><i class="fas fa-euro-sign"></i> <?= htmlspecialchars($item['prix_indicatif']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($public): ?>
    <div class="text-center mt-4" style="opacity:0.5;font-size:0.8em;">
        <p>FrenchyConciergerie &copy; <?= date('Y') ?></p>
    </div>
    <?php endif; ?>
</div>

<?php if (!$public): ?>
</div>
<?php endif; ?>

</body>
</html>
