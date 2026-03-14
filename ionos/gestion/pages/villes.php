<?php
/**
 * Villes & Recommandations — Page unifiee CRUD complet
 * Gestion des villes et recommandations locales (partenaires, restaurants, activites)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_bridge.php';

// Tables requises : voir db/install_tables.php

// Ajouter colonne ville_id a liste_logements si elle n'existe pas
try {
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN ville_id INT NULL");
} catch (PDOException $e) {
    // Colonne existe deja
}

$feedback = '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_ville':
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (!empty($nom)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO villes (nom, description) VALUES (:nom, :description)");
                    $stmt->execute([':nom' => $nom, ':description' => $description ?: null]);
                    $newId = $pdo->lastInsertId();
                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Ville ajoutee avec succes.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    // Rediriger vers la nouvelle ville
                    header("Location: villes.php?id=$newId&msg=added");
                    exit;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $feedback = '<div class="alert alert-danger alert-dismissible fade show">Cette ville existe deja.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    }
                }
            }
            break;

        case 'update_ville':
            $id = intval($_POST['ville_id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if ($id > 0 && !empty($nom)) {
                try {
                    $stmt = $pdo->prepare("UPDATE villes SET nom = :nom, description = :description WHERE id = :id");
                    $stmt->execute([':nom' => $nom, ':description' => $description ?: null, ':id' => $id]);
                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Ville mise a jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } catch (PDOException $e) {
                    $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
            break;

        case 'delete_ville':
            $id = intval($_POST['ville_id'] ?? 0);
            if ($id > 0) {
                try {
                    // Verifier si des logements utilisent cette ville
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM liste_logements WHERE ville_id = ?");
                    $stmt->execute([$id]);
                    $count = $stmt->fetchColumn();
                    if ($count > 0) {
                        $feedback = '<div class="alert alert-warning alert-dismissible fade show">Impossible de supprimer : ' . $count . ' logement(s) utilise(nt) cette ville.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    } else {
                        $pdo->prepare("DELETE FROM villes WHERE id = :id")->execute([':id' => $id]);
                        header("Location: villes.php?msg=deleted");
                        exit;
                    }
                } catch (PDOException $e) {
                    $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
            break;

        case 'add_reco':
            $villeId = intval($_POST['ville_id'] ?? 0);
            $categorie = $_POST['categorie'] ?? '';
            $nom = trim($_POST['nom'] ?? '');
            if ($villeId > 0 && in_array($categorie, ['partenaire', 'restaurant', 'activite']) && !empty($nom)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO ville_recommandations
                        (ville_id, categorie, nom, description, adresse, telephone, site_web, prix_indicatif, note_interne)
                        VALUES (:ville_id, :categorie, :nom, :description, :adresse, :telephone, :site_web, :prix_indicatif, :note_interne)
                    ");
                    $stmt->execute([
                        ':ville_id' => $villeId,
                        ':categorie' => $categorie,
                        ':nom' => $nom,
                        ':description' => trim($_POST['description'] ?? '') ?: null,
                        ':adresse' => trim($_POST['adresse'] ?? '') ?: null,
                        ':telephone' => trim($_POST['telephone'] ?? '') ?: null,
                        ':site_web' => trim($_POST['site_web'] ?? '') ?: null,
                        ':prix_indicatif' => trim($_POST['prix_indicatif'] ?? '') ?: null,
                        ':note_interne' => trim($_POST['note_interne'] ?? '') ?: null
                    ]);
                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Recommandation ajoutee.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } catch (PDOException $e) {
                    $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
            break;

        case 'update_reco':
            $recoId = intval($_POST['reco_id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            if ($recoId > 0 && !empty($nom)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE ville_recommandations SET
                            nom = :nom,
                            description = :description,
                            adresse = :adresse,
                            telephone = :telephone,
                            site_web = :site_web,
                            prix_indicatif = :prix_indicatif,
                            note_interne = :note_interne,
                            actif = :actif
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':nom' => $nom,
                        ':description' => trim($_POST['description'] ?? '') ?: null,
                        ':adresse' => trim($_POST['adresse'] ?? '') ?: null,
                        ':telephone' => trim($_POST['telephone'] ?? '') ?: null,
                        ':site_web' => trim($_POST['site_web'] ?? '') ?: null,
                        ':prix_indicatif' => trim($_POST['prix_indicatif'] ?? '') ?: null,
                        ':note_interne' => trim($_POST['note_interne'] ?? '') ?: null,
                        ':actif' => isset($_POST['actif']) ? 1 : 0,
                        ':id' => $recoId
                    ]);
                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Recommandation mise a jour.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } catch (PDOException $e) {
                    $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
            break;

        case 'delete_reco':
            $recoId = intval($_POST['reco_id'] ?? 0);
            if ($recoId > 0) {
                try {
                    $pdo->prepare("DELETE FROM ville_recommandations WHERE id = :id")->execute([':id' => $recoId]);
                    $feedback = '<div class="alert alert-success alert-dismissible fade show">Recommandation supprimee.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } catch (PDOException $e) {
                    $feedback = '<div class="alert alert-danger alert-dismissible fade show">Erreur : ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                }
            }
            break;
    }
}

// Message de redirection
if (isset($_GET['msg'])) {
    $msgs = ['deleted' => 'Ville supprimee avec succes.', 'added' => 'Ville creee avec succes.'];
    if (isset($msgs[$_GET['msg']])) {
        $feedback = '<div class="alert alert-success alert-dismissible fade show">' . $msgs[$_GET['msg']] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Recuperer toutes les villes avec stats
$villes = [];
try {
    $stmt = $pdo->query("
        SELECT v.*,
               (SELECT COUNT(*) FROM ville_recommandations WHERE ville_id = v.id) as nb_recos,
               (SELECT COUNT(*) FROM liste_logements WHERE ville_id = v.id) as nb_logements
        FROM villes v
        ORDER BY v.nom
    ");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("
            SELECT v.*,
                   (SELECT COUNT(*) FROM ville_recommandations WHERE ville_id = v.id) as nb_recos,
                   0 as nb_logements
            FROM villes v
            ORDER BY v.nom
        ");
        $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { error_log('villes.php: ' . $e2->getMessage()); }
}

// Recuperer la ville selectionnee
$selectedVille = null;
$recommandations = ['partenaire' => [], 'restaurant' => [], 'activite' => []];
if (isset($_GET['id'])) {
    $villeId = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM villes WHERE id = ?");
        $stmt->execute([$villeId]);
        $selectedVille = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($selectedVille) {
            $stmt = $pdo->prepare("SELECT * FROM ville_recommandations WHERE ville_id = ? ORDER BY categorie, ordre, nom");
            $stmt->execute([$villeId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $recommandations[$row['categorie']][] = $row;
            }
        }
    } catch (PDOException $e) { error_log('villes.php: ' . $e->getMessage()); }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villes & Recommandations — FrenchyConciergerie</title>
    <style>
        .bg-warning-light { background-color: #fff3cd; }
        .reco-card { transition: transform 0.1s; }
        .reco-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .list-group-item.active .text-muted { color: rgba(255,255,255,.7) !important; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-city text-primary"></i> Villes & Recommandations</h2>
            <p class="text-muted">
                <?= count($villes) ?> ville(s) —
                Configurez les recommandations (partenaires, restaurants, activites) par ville
            </p>
        </div>
    </div>

    <?= $feedback ?>

    <div class="row">
        <!-- Liste des villes -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Villes</h5>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddVille">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                        <?php if (empty($villes)): ?>
                            <div class="list-group-item text-muted text-center py-4">
                                <i class="fas fa-city fa-2x mb-2"></i><br>
                                Aucune ville configuree
                            </div>
                        <?php else: ?>
                            <?php foreach ($villes as $v): ?>
                                <?php $isSelected = $selectedVille && $selectedVille['id'] == $v['id']; ?>
                                <a href="?id=<?= $v['id'] ?>"
                                   class="list-group-item list-group-item-action <?= $isSelected ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($v['nom']) ?></strong>
                                            <br>
                                            <small class="<?= $isSelected ? '' : 'text-muted' ?>">
                                                <i class="fas fa-home"></i> <?= $v['nb_logements'] ?> logement(s)
                                                &middot;
                                                <i class="fas fa-star"></i> <?= $v['nb_recos'] ?> reco(s)
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <small>
                    <i class="fas fa-info-circle"></i>
                    Les recommandations d'une ville sont partagees par tous les logements associes a cette ville.
                </small>
            </div>
        </div>

        <!-- Detail de la ville -->
        <div class="col-md-8">
            <?php if ($selectedVille): ?>
                <!-- En-tete ville -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-city"></i> <?= htmlspecialchars($selectedVille['nom']) ?>
                        </h4>
                        <div>
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditVille">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteVille()">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($selectedVille['description'])): ?>
                        <div class="card-body">
                            <p class="mb-0 text-muted"><?= htmlspecialchars($selectedVille['description']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Onglets recommandations -->
                <ul class="nav nav-tabs" id="recoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-partenaires" data-bs-toggle="tab" data-bs-target="#tabPartenaires" type="button" role="tab">
                            <i class="fas fa-handshake text-primary"></i> Partenaires
                            <span class="badge bg-primary"><?= count($recommandations['partenaire']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-restaurants" data-bs-toggle="tab" data-bs-target="#tabRestaurants" type="button" role="tab">
                            <i class="fas fa-utensils text-danger"></i> Restaurants
                            <span class="badge bg-danger"><?= count($recommandations['restaurant']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-activites" data-bs-toggle="tab" data-bs-target="#tabActivites" type="button" role="tab">
                            <i class="fas fa-hiking text-success"></i> Activites
                            <span class="badge bg-success"><?= count($recommandations['activite']) ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content border border-top-0 p-3 bg-white rounded-bottom">
                    <?php
                    $categories = [
                        'partenaire' => ['id' => 'tabPartenaires', 'icon' => 'fa-handshake', 'color' => 'primary', 'label' => 'partenaire', 'active' => true],
                        'restaurant' => ['id' => 'tabRestaurants', 'icon' => 'fa-utensils', 'color' => 'danger', 'label' => 'restaurant', 'active' => false],
                        'activite'   => ['id' => 'tabActivites', 'icon' => 'fa-hiking', 'color' => 'success', 'label' => 'activite', 'active' => false],
                    ];
                    foreach ($categories as $cat => $meta):
                    ?>
                    <div class="tab-pane fade <?= $meta['active'] ? 'show active' : '' ?>" id="<?= $meta['id'] ?>" role="tabpanel">
                        <?php if (!empty($recommandations[$cat])): ?>
                            <div class="row">
                                <?php foreach ($recommandations[$cat] as $reco): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 reco-card <?= $reco['actif'] ? '' : 'bg-light' ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="card-title mb-1">
                                                        <i class="fas <?= $meta['icon'] ?> text-<?= $meta['color'] ?>"></i>
                                                        <?= htmlspecialchars($reco['nom']) ?>
                                                        <?php if (!empty($reco['prix_indicatif'])): ?>
                                                            <span class="badge bg-info"><?= htmlspecialchars($reco['prix_indicatif']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!$reco['actif']): ?>
                                                            <span class="badge bg-secondary">Inactif</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-reco"
                                                            data-reco='<?= htmlspecialchars(json_encode($reco), ENT_QUOTES) ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                                <?php if (!empty($reco['description'])): ?>
                                                    <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($reco['adresse'])): ?>
                                                    <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                                <?php endif; ?>
                                                <?php if (!empty($reco['telephone'])): ?>
                                                    <small><i class="fas fa-phone"></i> <?= htmlspecialchars($reco['telephone']) ?></small><br>
                                                <?php endif; ?>
                                                <?php if (!empty($reco['site_web'])): ?>
                                                    <small><i class="fas fa-globe"></i> <a href="<?= htmlspecialchars($reco['site_web']) ?>" target="_blank" rel="noopener">Site web</a></small><br>
                                                <?php endif; ?>
                                                <?php if (!empty($reco['note_interne'])): ?>
                                                    <div class="mt-2 p-2 bg-warning-light rounded small">
                                                        <i class="fas fa-sticky-note text-warning"></i>
                                                        <em><?= htmlspecialchars($reco['note_interne']) ?></em>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Aucun <?= $meta['label'] ?></p>
                        <?php endif; ?>
                        <button type="button" class="btn btn-success btn-add-reco" data-categorie="<?= $cat ?>">
                            <i class="fas fa-plus"></i> Ajouter un <?= $meta['label'] ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                        <h5>Selectionnez une ville</h5>
                        <p class="text-muted">Cliquez sur une ville dans la liste pour gerer ses recommandations.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddVille">
                            <i class="fas fa-plus"></i> Creer une nouvelle ville
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Ajouter Ville -->
<div class="modal fade" id="modalAddVille" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" value="add_ville">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nouvelle ville</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom de la ville *</label>
                        <input type="text" name="nom" class="form-control" required placeholder="Ex: Paris, Lyon, Marseille...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description optionnelle..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Creer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($selectedVille): ?>
<!-- Modal Modifier Ville -->
<div class="modal fade" id="modalEditVille" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" value="update_ville">
                <input type="hidden" name="ville_id" value="<?= $selectedVille['id'] ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier la ville</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom de la ville *</label>
                        <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($selectedVille['nom']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($selectedVille['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Recommandation (ajout/edition) -->
<div class="modal fade" id="modalReco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" id="reco_action" value="add_reco">
                <input type="hidden" name="ville_id" value="<?= $selectedVille['id'] ?>">
                <input type="hidden" name="reco_id" id="reco_id" value="">
                <input type="hidden" name="categorie" id="reco_categorie" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalRecoTitle">
                        <i class="fas fa-plus-circle"></i> Ajouter une recommandation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-tag"></i> Nom *</label>
                        <input type="text" name="nom" id="reco_nom" class="form-control" required
                               placeholder="Nom du lieu ou de l'activite">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" id="reco_description" class="form-control" rows="2"
                                  placeholder="Courte description pour vos voyageurs"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                            <input type="text" name="adresse" id="reco_adresse" class="form-control"
                                   placeholder="Adresse complete">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-phone"></i> Telephone</label>
                            <input type="text" name="telephone" id="reco_telephone" class="form-control"
                                   placeholder="06 XX XX XX XX">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label"><i class="fas fa-globe"></i> Site web</label>
                            <input type="url" name="site_web" id="reco_site_web" class="form-control"
                                   placeholder="https://...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-euro-sign"></i> Prix indicatif</label>
                            <input type="text" name="prix_indicatif" id="reco_prix_indicatif" class="form-control"
                                   placeholder="Ex: 15-25EUR, Gratuit...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-sticky-note"></i> Note interne (non visible par les voyageurs)</label>
                        <textarea name="note_interne" id="reco_note_interne" class="form-control" rows="2"
                                  placeholder="Notes personnelles, code promo partenaire..."></textarea>
                    </div>
                    <div class="form-check" id="reco_actif_container" style="display:none;">
                        <input type="checkbox" class="form-check-input" name="actif" id="reco_actif" checked>
                        <label class="form-check-label" for="reco_actif">Actif (visible)</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="btnDeleteReco" style="display:none;"
                            onclick="deleteReco()">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaires caches pour suppression -->
<form method="POST" id="formDeleteVille" style="display:none;">
    <?php echoCsrfField(); ?>
    <input type="hidden" name="action" value="delete_ville">
    <input type="hidden" name="ville_id" value="<?= $selectedVille['id'] ?>">
</form>
<form method="POST" id="formDeleteReco" style="display:none;">
    <?php echoCsrfField(); ?>
    <input type="hidden" name="action" value="delete_reco">
    <input type="hidden" name="reco_id" id="delete_reco_id" value="">
</form>
<?php endif; ?>

<?php if ($selectedVille): ?>
<script>
const categorieLabels = {
    'partenaire': 'Partenaire',
    'restaurant': 'Restaurant',
    'activite': 'Activite'
};

function deleteVille() {
    if (confirm('Supprimer cette ville et toutes ses recommandations ?')) {
        document.getElementById('formDeleteVille').submit();
    }
}

function deleteReco() {
    if (confirm('Supprimer cette recommandation ?')) {
        document.getElementById('formDeleteReco').submit();
    }
}

// Ajouter une reco
document.querySelectorAll('.btn-add-reco').forEach(btn => {
    btn.addEventListener('click', function() {
        const categorie = this.dataset.categorie;
        document.getElementById('reco_action').value = 'add_reco';
        document.getElementById('reco_id').value = '';
        document.getElementById('reco_categorie').value = categorie;
        document.getElementById('reco_nom').value = '';
        document.getElementById('reco_description').value = '';
        document.getElementById('reco_adresse').value = '';
        document.getElementById('reco_telephone').value = '';
        document.getElementById('reco_site_web').value = '';
        document.getElementById('reco_prix_indicatif').value = '';
        document.getElementById('reco_note_interne').value = '';
        document.getElementById('reco_actif').checked = true;
        document.getElementById('modalRecoTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Ajouter un ' + categorieLabels[categorie].toLowerCase();
        document.getElementById('reco_actif_container').style.display = 'none';
        document.getElementById('btnDeleteReco').style.display = 'none';
        new bootstrap.Modal(document.getElementById('modalReco')).show();
    });
});

// Editer une reco
document.querySelectorAll('.btn-edit-reco').forEach(btn => {
    btn.addEventListener('click', function() {
        const reco = JSON.parse(this.dataset.reco);
        document.getElementById('reco_action').value = 'update_reco';
        document.getElementById('reco_id').value = reco.id;
        document.getElementById('delete_reco_id').value = reco.id;
        document.getElementById('reco_categorie').value = reco.categorie;
        document.getElementById('reco_nom').value = reco.nom;
        document.getElementById('reco_description').value = reco.description || '';
        document.getElementById('reco_adresse').value = reco.adresse || '';
        document.getElementById('reco_telephone').value = reco.telephone || '';
        document.getElementById('reco_site_web').value = reco.site_web || '';
        document.getElementById('reco_prix_indicatif').value = reco.prix_indicatif || '';
        document.getElementById('reco_note_interne').value = reco.note_interne || '';
        document.getElementById('reco_actif').checked = reco.actif == 1;
        document.getElementById('modalRecoTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier : ' + reco.nom;
        document.getElementById('reco_actif_container').style.display = 'block';
        document.getElementById('btnDeleteReco').style.display = 'inline-block';
        new bootstrap.Modal(document.getElementById('modalReco')).show();
    });
});
</script>
<?php endif; ?>
</body>
</html>
