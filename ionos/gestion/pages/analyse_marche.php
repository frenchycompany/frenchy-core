<?php
/**
 * Dashboard d'analyse du marche et des concurrents
 * Version VPS — connexion distante au RPI (sms_db)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$pdo = getRpiPdo();

if (!($pdo instanceof PDO)) {
    die('Erreur: connexion RPI non disponible.');
}

// Actions AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'delete_competitor':
            $id = intval($_POST['id']);
            $pdo->prepare("DELETE FROM market_competitors WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'toggle_active':
            $id = intval($_POST['id']);
            $pdo->prepare("UPDATE market_competitors SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'add_mapping':
            $logementId = intval($_POST['logement_id']);
            $competitorId = intval($_POST['competitor_id']);
            try {
                $pdo->prepare("INSERT IGNORE INTO market_competitor_mapping (logement_id, competitor_id) VALUES (?, ?)")
                    ->execute([$logementId, $competitorId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;

        case 'remove_mapping':
            $id = intval($_POST['id']);
            $pdo->prepare("DELETE FROM market_competitor_mapping WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;

        case 'add_price':
            $competitorId = intval($_POST['competitor_id']);
            $date = $_POST['date_sejour'];
            $prix = floatval($_POST['prix_nuit']);
            try {
                $pdo->prepare("INSERT INTO market_prices (competitor_id, date_sejour, prix_nuit, source) VALUES (?, ?, ?, 'manuel')")
                    ->execute([$competitorId, $date, $prix]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
    }
}

// Recuperer les concurrents
$competitors = $pdo->query("
    SELECT mc.*,
           (SELECT COUNT(*) FROM market_prices WHERE competitor_id = mc.id) as nb_prix,
           (SELECT AVG(prix_nuit) FROM market_prices WHERE competitor_id = mc.id) as prix_moyen
    FROM market_competitors mc
    ORDER BY mc.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Recuperer nos logements pour le mapping
$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);

// Recuperer les mappings existants
$mappings = $pdo->query("
    SELECT mcm.*, l.nom_du_logement, mc.nom as competitor_nom
    FROM market_competitor_mapping mcm
    JOIN liste_logements l ON mcm.logement_id = l.id
    JOIN market_competitors mc ON mcm.competitor_id = mc.id
")->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
$totalCompetitors = count($competitors);
$activeCompetitors = count(array_filter($competitors, fn($c) => $c['is_active']));
$totalPrices = array_sum(array_column($competitors, 'nb_prix'));
$avgPrice = $totalCompetitors > 0 ? round(array_sum(array_filter(array_column($competitors, 'prix_moyen'))) / max(1, count(array_filter(array_column($competitors, 'prix_moyen')))), 0) : 0;

// Multi-proprietaires (hotes avec plusieurs annonces)
$multiOwners = $pdo->query("
    SELECT host_name, host_profile_id, COUNT(*) as nb_annonces,
           GROUP_CONCAT(nom SEPARATOR '||') as annonces_list
    FROM market_competitors
    WHERE host_profile_id IS NOT NULL AND host_profile_id != ''
    GROUP BY host_profile_id
    HAVING COUNT(*) > 1
    ORDER BY nb_annonces DESC
")->fetchAll(PDO::FETCH_ASSOC);

// URL de l'API pour le bookmarklet
$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/market_capture.php';
$bookmarkletUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/bookmarklet_loader.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analyse du marche — FrenchyConciergerie</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-chart-line text-primary"></i> Analyse du marche</h1>
            <p class="text-muted">Suivi des prix concurrents sur Airbnb</p>
        </div>
    </div>

    <!-- Stats globales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $totalCompetitors ?></h2>
                    <small>Concurrents</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $activeCompetitors ?></h2>
                    <small>Actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $totalPrices ?></h2>
                    <small>Prix releves</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= $avgPrice ?>&euro;</h2>
                    <small>Prix moyen</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookmarklet -->
    <?php
    $captureUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/pages/capture_airbnb.php';
    // Bookmarklet ameliore avec selecteurs specifiques Airbnb
    $bookmarkletCode = "javascript:(function(){if(!location.hostname.includes('airbnb')){alert('Ouvrez une annonce Airbnb!');return;}var d=document,t=d.body.innerText,u=encodeURIComponent;var h1=d.querySelector('h1[elementtiming]');var h2=d.querySelector('h2[elementtiming]');var nom=h1?h1.textContent.trim():'';var h2txt=h2?h2.textContent:'';var villeMatch=h2txt.match(/-\\s*([A-Za-zÀ-ÿ\\s-]+),\\s*France/i);var ville=villeMatch?villeMatch[1].trim():'';var typeMatch=h2txt.match(/appartement|maison|studio|chambre|loft|villa/i);var type=typeMatch?typeMatch[0].toLowerCase():'appartement';var noteEl=d.querySelector('[data-testid=\"pdp-reviews-highlight-banner-host-rating\"]')||d.querySelector('.rmtgcc3');var note=noteEl?(noteEl.textContent.match(/([0-9][.,][0-9]+)/)||[])[1]||'':'';var avisEl=d.querySelector('[data-testid=\"pdp-reviews-highlight-banner-host-review\"]');var avis=avisEl?(avisEl.textContent.match(/(\\d+)/)||[])[1]||'':'';if(!avis){var am=t.match(/(\\d+)\\s*(?:avis|comment)/i);if(am)avis=am[1];}var cap=(t.match(/(\\d+)\\s*voyageur/i)||[])[1]||'';var ch=(t.match(/(\\d+)\\s*chambre/i)||[])[1]||'';var prixEl=d.querySelector('.u174bpcy');var prix='';if(prixEl){var pm=prixEl.textContent.match(/(\\d+)/);if(pm)prix=pm[1];}if(!prix){var pm2=t.match(/(\\d+)\\s*€\\s*(?:par|\\/)\\s*nuit/i);if(pm2)prix=pm2[1];}var sh=t.match(/super.?h[oô]te/i)?1:0;var cohotes=d.querySelectorAll('.ato18ul li').length||0;var hostName='';var hostId='';var hostEl=d.querySelector('.t1lpv951');if(hostEl){var hm=hostEl.textContent.match(/H[oô]te\\s*[:\\s]+(.+)/i);if(hm)hostName=hm[1].trim();}var hostImg=d.querySelector('[data-section-id=\"HOST_OVERVIEW_DEFAULT\"] img[src*=\"User-\"]')||d.querySelector('button[aria-label*=\"En savoir plus sur\"] img[src*=\"User-\"]');if(hostImg){var im=hostImg.src.match(/User-(\\d+)/);if(im)hostId=im[1];}var cohostNames=[];d.querySelectorAll('.ato18ul li').forEach(function(li){var sp=li.querySelector('.a7xbq6p');if(sp)cohostNames.push(sp.textContent.trim());});var url='" . $captureUrl . "?url='+u(location.href)+'&nom='+u(nom)+'&capacite='+cap+'&chambres='+ch+'&note='+u((note||'').replace(',','.'))+'&avis='+avis+'&prix='+prix+'&superhost='+sh+'&ville='+u(ville)+'&type='+type+'&cohotes='+cohotes+'&host_name='+u(hostName)+'&host_id='+hostId+'&cohost_names='+u(cohostNames.join(','));window.open(url,'_blank');})();";
    ?>
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-bookmark"></i> Bookmarklet - Capture rapide</h5>
        </div>
        <div class="card-body">
            <p>Glissez ce bouton dans votre barre de favoris, puis cliquez dessus quand vous etes sur une annonce Airbnb :</p>
            <p class="text-center">
                <a href="<?= htmlspecialchars($bookmarkletCode) ?>"
                   class="btn btn-lg btn-primary"
                   onclick="alert('Glissez ce bouton dans vos favoris !'); return false;">
                    <i class="fas fa-plus-circle"></i> Capturer Airbnb
                </a>
            </p>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Comment utiliser :</strong>
                <ol class="mb-0 mt-2">
                    <li>Glissez le bouton ci-dessus dans votre barre de favoris</li>
                    <li>Allez sur une annonce Airbnb</li>
                    <li>Cliquez sur le favori "Capturer Airbnb"</li>
                    <li>Une fenetre s'ouvre avec les infos pre-remplies</li>
                    <li>Verifiez et cliquez "Enregistrer"</li>
                </ol>
            </div>
            <div class="alert alert-secondary mt-3">
                <i class="fas fa-keyboard"></i> <strong>Alternative :</strong>
                <a href="capture_airbnb.php" class="alert-link">Saisie manuelle</a> - Copiez-collez l'URL Airbnb
            </div>
        </div>
    </div>

    <!-- Liste des concurrents -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-users"></i> Concurrents (<?= $totalCompetitors ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($competitors)): ?>
            <div class="alert alert-secondary text-center">
                <i class="fas fa-info-circle"></i> Aucun concurrent enregistre. Utilisez le bookmarklet pour en ajouter.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Annonce</th>
                            <th>Proprietaire</th>
                            <th>Details</th>
                            <th class="text-center">Note</th>
                            <th class="text-center">Prix moyen</th>
                            <th class="text-center">Releves</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitors as $c): ?>
                        <tr class="<?= $c['is_active'] ? '' : 'table-secondary' ?>">
                            <td style="width: 80px;">
                                <?php if ($c['photo_url']): ?>
                                <img src="<?= htmlspecialchars($c['photo_url']) ?>" alt="" class="img-thumbnail" style="max-width: 60px;">
                                <?php else: ?>
                                <span class="text-muted"><i class="fas fa-image fa-2x"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['nom']) ?></strong>
                                <?php if ($c['superhost']): ?>
                                <span class="badge text-bg-warning ml-1">Superhost</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($c['ville'] ?? '') ?>
                                    <?= $c['quartier'] ? '- ' . htmlspecialchars($c['quartier']) : '' ?>
                                </small>
                                <?php if ($c['url']): ?>
                                <br><a href="<?= htmlspecialchars($c['url']) ?>" target="_blank" class="small">
                                    <i class="fas fa-external-link-alt"></i> Voir sur Airbnb
                                </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['host_name'])): ?>
                                <strong><?= htmlspecialchars($c['host_name']) ?></strong>
                                <?php if (!empty($c['host_profile_id'])): ?>
                                <br><a href="https://www.airbnb.fr/users/show/<?= htmlspecialchars($c['host_profile_id']) ?>" target="_blank" class="small">
                                    <i class="fas fa-user"></i> Profil
                                </a>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                                <?php if (!empty($c['cohost_names'])): ?>
                                <?php $cohosts = json_decode($c['cohost_names'], true); ?>
                                <?php if (!empty($cohosts)): ?>
                                <br><small class="text-info"><i class="fas fa-users"></i> Co-hotes: <?= htmlspecialchars(implode(', ', $cohosts)) ?></small>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($c['cohotes'] > 0): ?>
                                <br><span class="badge text-bg-secondary"><?= $c['cohotes'] ?> co-hote<?= $c['cohotes'] > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php if ($c['capacite']): ?>
                                    <i class="fas fa-user"></i> <?= $c['capacite'] ?> pers.
                                    <?php endif; ?>
                                    <?php if ($c['chambres']): ?>
                                    | <i class="fas fa-bed"></i> <?= $c['chambres'] ?> ch.
                                    <?php endif; ?>
                                    <?php if ($c['salles_bain']): ?>
                                    | <i class="fas fa-bath"></i> <?= $c['salles_bain'] ?> sdb
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php if ($c['note_moyenne']): ?>
                                <span class="badge text-bg-success">
                                    <i class="fas fa-star"></i> <?= number_format($c['note_moyenne'], 2) ?>
                                </span>
                                <br><small class="text-muted"><?= $c['nb_avis'] ?> avis</small>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($c['prix_moyen']): ?>
                                <strong><?= round($c['prix_moyen']) ?>&euro;</strong>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-info"><?= $c['nb_prix'] ?></span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick="toggleActive(<?= $c['id'] ?>)" title="Activer/Desactiver">
                                        <i class="fas fa-<?= $c['is_active'] ? 'eye' : 'eye-slash' ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="showAddPrice(<?= $c['id'] ?>, '<?= addslashes($c['nom']) ?>')" title="Ajouter un prix">
                                        <i class="fas fa-euro-sign"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteCompetitor(<?= $c['id'] ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Multi-proprietaires -->
    <?php if (!empty($multiOwners)): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-building"></i> Multi-proprietaires (<?= count($multiOwners) ?>)</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Proprietaires ayant plusieurs annonces sur Airbnb :</p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Proprietaire</th>
                            <th class="text-center">Annonces</th>
                            <th>Liste des biens</th>
                            <th>Profil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multiOwners as $owner): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($owner['host_name'] ?: 'Inconnu') ?></strong></td>
                            <td class="text-center"><span class="badge text-bg-warning"><?= $owner['nb_annonces'] ?></span></td>
                            <td>
                                <small>
                                    <?php
                                    $annonces = explode('||', $owner['annonces_list']);
                                    echo htmlspecialchars(implode(', ', array_slice($annonces, 0, 3)));
                                    if (count($annonces) > 3) echo '...';
                                    ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($owner['host_profile_id']): ?>
                                <a href="https://www.airbnb.fr/users/show/<?= htmlspecialchars($owner['host_profile_id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-external-link-alt"></i> Voir
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mappings -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-link"></i> Associations logement/concurrent</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-5">
                    <select id="mappingLogement" class="form-control">
                        <option value="">-- Selectionner un logement --</option>
                        <?php foreach ($logements as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <select id="mappingCompetitor" class="form-control">
                        <option value="">-- Selectionner un concurrent --</option>
                        <?php foreach ($competitors as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" onclick="addMapping()">
                        <i class="fas fa-plus"></i> Associer
                    </button>
                </div>
            </div>

            <?php if (!empty($mappings)): ?>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Notre logement</th>
                        <th>Concurrent</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nom_du_logement']) ?></td>
                        <td><?= htmlspecialchars($m['competitor_nom']) ?></td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeMapping(<?= $m['id'] ?>)">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted text-center">Aucune association configuree</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal ajout prix -->
<div class="modal fade" id="addPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un prix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="priceCompetitorId">
                <p id="priceCompetitorName" class="font-weight-bold"></p>
                <div class="form-group">
                    <label>Date du sejour</label>
                    <input type="date" id="priceDateSejour" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Prix par nuit (EUR)</label>
                    <input type="number" id="pricePrixNuit" class="form-control" step="1" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="savePrice()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleActive(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_active&id=' + id
    }).then(() => location.reload());
}

function deleteCompetitor(id) {
    if (confirm('Supprimer ce concurrent et tous ses prix ?')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_competitor&id=' + id
        }).then(() => location.reload());
    }
}

function showAddPrice(id, nom) {
    document.getElementById('priceCompetitorId').value = id;
    document.getElementById('priceCompetitorName').textContent = nom;
    document.getElementById('pricePrixNuit').value = '';
    new bootstrap.Modal(document.getElementById('addPriceModal')).show();
}

function savePrice() {
    const competitorId = document.getElementById('priceCompetitorId').value;
    const date = document.getElementById('priceDateSejour').value;
    const prix = document.getElementById('pricePrixNuit').value;

    if (!date || !prix) {
        alert('Veuillez remplir tous les champs');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_price&competitor_id=${competitorId}&date_sejour=${date}&prix_nuit=${prix}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addPriceModal')).hide();
            location.reload();
        } else {
            alert('Erreur: ' + (data.error || 'Erreur inconnue'));
        }
    });
}

function addMapping() {
    const logementId = document.getElementById('mappingLogement').value;
    const competitorId = document.getElementById('mappingCompetitor').value;

    if (!logementId || !competitorId) {
        alert('Selectionnez un logement et un concurrent');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_mapping&logement_id=${logementId}&competitor_id=${competitorId}`
    }).then(() => location.reload());
}

function removeMapping(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove_mapping&id=' + id
    }).then(() => location.reload());
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
