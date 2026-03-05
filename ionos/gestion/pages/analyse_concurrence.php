<?php
/**
 * Analyse de la concurrence
 * Version VPS — connexion distante au RPI (sms_db)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$pdo = getRpiPdo();

// Verifier si les colonnes host existent
$hasHostColumns = false;
try {
    $pdo->query("SELECT host_profile_id FROM market_competitors LIMIT 1");
    $hasHostColumns = true;
} catch (Exception $e) {
    // Colonnes non presentes, on continue sans
}

// Stats globales
try {
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        COUNT(DISTINCT host_profile_id) as nb_proprietaires,
        SUM(superhost) as superhosts,
        ROUND(AVG(note_moyenne), 2) as note_moyenne,
        ROUND(AVG(capacite), 1) as capacite_moyenne,
        ROUND(AVG(chambres), 1) as chambres_moyenne,
        COUNT(DISTINCT ville) as nb_villes
    FROM market_competitors
    WHERE is_active = 1
")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'nb_proprietaires' => 0, 'superhosts' => 0, 'note_moyenne' => 0, 'capacite_moyenne' => 0, 'chambres_moyenne' => 0, 'nb_villes' => 0];
}

// Prix moyens
try {
$prixStats = $pdo->query("
    SELECT
        ROUND(AVG(prix_nuit), 0) as prix_moyen,
        MIN(prix_nuit) as prix_min,
        MAX(prix_nuit) as prix_max,
        COUNT(*) as nb_releves
    FROM market_prices
")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prixStats = ['prix_moyen' => 0, 'prix_min' => 0, 'prix_max' => 0, 'nb_releves' => 0];
}

// Multi-proprietaires (seulement si colonnes host existent)
$multiOwners = [];
if ($hasHostColumns) {
    try {
        $multiOwners = $pdo->query("
            SELECT host_name, host_profile_id, COUNT(*) as nb_annonces,
                   ROUND(AVG(note_moyenne), 2) as note_moy,
                   GROUP_CONCAT(nom SEPARATOR '||') as annonces
            FROM market_competitors
            WHERE host_profile_id IS NOT NULL AND host_profile_id != ''
            GROUP BY host_profile_id
            HAVING COUNT(*) > 1
            ORDER BY nb_annonces DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $multiOwners = [];
    }
}

// Distribution par capacite
try {
$capaciteDistrib = $pdo->query("
    SELECT capacite, COUNT(*) as nb,
           ROUND(AVG(note_moyenne), 2) as note_moy,
           (SELECT ROUND(AVG(prix_nuit), 0) FROM market_prices mp
            JOIN market_competitors mc2 ON mp.competitor_id = mc2.id
            WHERE mc2.capacite = mc.capacite) as prix_moy
    FROM market_competitors mc
    WHERE capacite IS NOT NULL AND capacite > 0
    GROUP BY capacite
    ORDER BY capacite
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $capaciteDistrib = []; }

// Top notes
try {
$topNotesQuery = $hasHostColumns
    ? "SELECT nom, ville, note_moyenne, nb_avis, superhost, host_name,
              (SELECT ROUND(AVG(prix_nuit), 0) FROM market_prices WHERE competitor_id = mc.id) as prix_moy
       FROM market_competitors mc WHERE note_moyenne IS NOT NULL ORDER BY note_moyenne DESC, nb_avis DESC LIMIT 10"
    : "SELECT nom, ville, note_moyenne, nb_avis, superhost, '' as host_name,
              (SELECT ROUND(AVG(prix_nuit), 0) FROM market_prices WHERE competitor_id = mc.id) as prix_moy
       FROM market_competitors mc WHERE note_moyenne IS NOT NULL ORDER BY note_moyenne DESC, nb_avis DESC LIMIT 10";
$topNotes = $pdo->query($topNotesQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $topNotes = []; }

// Distribution par type
try {
$typeDistrib = $pdo->query("
    SELECT type_logement, COUNT(*) as nb,
           ROUND(AVG(note_moyenne), 2) as note_moy,
           SUM(superhost) as superhosts
    FROM market_competitors
    WHERE type_logement IS NOT NULL
    GROUP BY type_logement
    ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $typeDistrib = []; }

// Distribution par ville
try {
$villeDistrib = $pdo->query("
    SELECT ville, COUNT(*) as nb,
           ROUND(AVG(note_moyenne), 2) as note_moy,
           SUM(superhost) as superhosts,
           (SELECT ROUND(AVG(prix_nuit), 0) FROM market_prices mp
            JOIN market_competitors mc2 ON mp.competitor_id = mc2.id
            WHERE mc2.ville = mc.ville) as prix_moy
    FROM market_competitors mc
    WHERE ville IS NOT NULL AND ville != ''
    GROUP BY ville
    ORDER BY nb DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $villeDistrib = []; }

// Nos logements pour comparaison
try {
$nosLogements = $pdo->query("
    SELECT l.id, l.nom_du_logement, l.capacite_voyageurs, l.nbre_chambres_couchages,
           (SELECT AVG(prix) FROM prix_nuitee WHERE logement_id = l.id) as notre_prix_moy
    FROM liste_logements l
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $nosLogements = []; }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analyse concurrentielle — FrenchyConciergerie</title>
</head>
<body>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-chart-bar"></i> Analyse de la Concurrence</h2>
    <p class="text-muted">Exploitation des <?= $stats['total'] ?> concurrents collectes</p>

    <!-- Stats globales -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                    <small>Concurrents</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['nb_proprietaires'] ?: '?' ?></h3>
                    <small>Proprietaires</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['superhosts'] ?></h3>
                    <small>Superhosts</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['note_moyenne'] ?></h3>
                    <small>Note moyenne</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $prixStats['prix_moyen'] ?: '-' ?>&euro;</h3>
                    <small>Prix moyen</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-dark text-white text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['nb_villes'] ?></h3>
                    <small>Villes</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Distribution par capacite -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Par Capacite</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Capacite</th>
                                <th class="text-center">Nb</th>
                                <th class="text-center">Note moy.</th>
                                <th class="text-center">Prix moy.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($capaciteDistrib as $c): ?>
                            <tr>
                                <td><i class="fas fa-user"></i> <?= $c['capacite'] ?> pers.</td>
                                <td class="text-center"><?= $c['nb'] ?></td>
                                <td class="text-center">
                                    <?php if ($c['note_moy']): ?>
                                    <span class="badge text-bg-success"><?= $c['note_moy'] ?></span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['prix_moy']): ?>
                                    <strong><?= $c['prix_moy'] ?>&euro;</strong>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Distribution par ville -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Par Ville</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ville</th>
                                <th class="text-center">Nb</th>
                                <th class="text-center">Superhosts</th>
                                <th class="text-center">Prix moy.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($villeDistrib as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['ville']) ?></td>
                                <td class="text-center"><?= $v['nb'] ?></td>
                                <td class="text-center">
                                    <?php if ($v['superhosts'] > 0): ?>
                                    <span class="badge text-bg-warning"><?= $v['superhosts'] ?></span>
                                    <?php else: ?>0<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($v['prix_moy']): ?>
                                    <strong><?= $v['prix_moy'] ?>&euro;</strong>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Multi-proprietaires -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-building"></i> Multi-Proprietaires (<?= count($multiOwners) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($multiOwners)): ?>
                    <p class="text-muted text-center">Aucun multi-proprietaire detecte</p>
                    <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Proprietaire</th>
                                <th class="text-center">Annonces</th>
                                <th class="text-center">Note</th>
                                <th>Profil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multiOwners as $o): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($o['host_name'] ?: 'Inconnu') ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars(implode(', ', array_slice(explode('||', $o['annonces']), 0, 2))) ?><?= count(explode('||', $o['annonces'])) > 2 ? '...' : '' ?></small>
                                </td>
                                <td class="text-center"><span class="badge text-bg-warning"><?= $o['nb_annonces'] ?></span></td>
                                <td class="text-center"><?= $o['note_moy'] ?: '-' ?></td>
                                <td>
                                    <?php if ($o['host_profile_id']): ?>
                                    <a href="https://www.airbnb.fr/users/show/<?= $o['host_profile_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top notes -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-star"></i> Top 10 Notes</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Annonce</th>
                                <th class="text-center">Note</th>
                                <th class="text-center">Avis</th>
                                <th class="text-center">Prix</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topNotes as $t): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(mb_substr($t['nom'], 0, 30)) ?><?= mb_strlen($t['nom']) > 30 ? '...' : '' ?>
                                    <?php if ($t['superhost']): ?><span class="badge text-bg-warning">SH</span><?php endif; ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($t['ville']) ?></small>
                                </td>
                                <td class="text-center"><span class="badge text-bg-success"><?= $t['note_moyenne'] ?></span></td>
                                <td class="text-center"><?= $t['nb_avis'] ?></td>
                                <td class="text-center"><?= $t['prix_moy'] ? $t['prix_moy'] . '€' : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparaison avec nos logements -->
    <?php if (!empty($nosLogements)): ?>
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-balance-scale"></i> Comparaison avec vos logements</h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Votre logement</th>
                        <th class="text-center">Capacite</th>
                        <th class="text-center">Votre prix moy.</th>
                        <th class="text-center">Prix marche</th>
                        <th class="text-center">Ecart</th>
                        <th>Recommandation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nosLogements as $l):
                        // Trouver le prix moyen du marche pour cette capacite
                        $prixMarche = null;
                        foreach ($capaciteDistrib as $c) {
                            if ($c['capacite'] == $l['capacite_voyageurs']) {
                                $prixMarche = $c['prix_moy'];
                                break;
                            }
                        }
                        $ecart = ($l['notre_prix_moy'] && $prixMarche)
                            ? round((($l['notre_prix_moy'] - $prixMarche) / $prixMarche) * 100)
                            : null;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong></td>
                        <td class="text-center"><?= $l['capacite_voyageurs'] ?> pers.</td>
                        <td class="text-center">
                            <?= $l['notre_prix_moy'] ? round($l['notre_prix_moy']) . '€' : '-' ?>
                        </td>
                        <td class="text-center">
                            <?= $prixMarche ? $prixMarche . '€' : '-' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($ecart !== null): ?>
                                <?php if ($ecart > 10): ?>
                                <span class="badge text-bg-danger">+<?= $ecart ?>%</span>
                                <?php elseif ($ecart < -10): ?>
                                <span class="badge text-bg-success"><?= $ecart ?>%</span>
                                <?php else: ?>
                                <span class="badge text-bg-secondary"><?= $ecart > 0 ? '+' : '' ?><?= $ecart ?>%</span>
                                <?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ecart !== null): ?>
                                <?php if ($ecart > 15): ?>
                                <span class="text-danger"><i class="fas fa-arrow-down"></i> Prix eleve vs marche</span>
                                <?php elseif ($ecart < -15): ?>
                                <span class="text-success"><i class="fas fa-arrow-up"></i> Marge d'augmentation</span>
                                <?php else: ?>
                                <span class="text-secondary"><i class="fas fa-check"></i> Prix competitif</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">Donnees insuffisantes</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Insights -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="alert alert-info">
                        <strong>Taux de Superhosts :</strong>
                        <?php $tauxSH = $stats['total'] > 0 ? round(($stats['superhosts'] / $stats['total']) * 100) : 0; ?>
                        <?= $tauxSH ?>% des concurrents
                        <br><small><?= $tauxSH > 30 ? 'Marche competitif, le statut Superhost est important' : 'Opportunite de se demarquer avec le statut Superhost' ?></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning">
                        <strong>Multi-proprietaires :</strong>
                        <?= count($multiOwners) ?> detectes
                        <br><small><?= count($multiOwners) > 5 ? 'Presence de professionnels sur le marche' : 'Marche plutot individuel' ?></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success">
                        <strong>Fourchette de prix :</strong>
                        <?= $prixStats['prix_min'] ?? '?' ?>€ - <?= $prixStats['prix_max'] ?? '?' ?>€
                        <br><small>Moyenne : <?= $prixStats['prix_moyen'] ?? '?' ?>€/nuit</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mb-4">
        <a href="analyse_marche.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Ajouter des concurrents
        </a>
    </div>
</div>

</body>
</html>
