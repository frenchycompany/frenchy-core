<?php
/**
 * Gestion des villes et recommandations
 * Partenaires, restaurants, activites par ville
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireAuth();

$message = '';
$messageType = '';

// Creer les tables si elles n'existent pas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS villes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ville_recommandations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ville_id INT NOT NULL,
            categorie ENUM('partenaire', 'restaurant', 'activite') NOT NULL,
            nom VARCHAR(200) NOT NULL,
            description TEXT,
            adresse VARCHAR(255),
            telephone VARCHAR(50),
            site_web VARCHAR(255),
            prix_indicatif VARCHAR(100),
            note_interne TEXT,
            ordre INT DEFAULT 0,
            actif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ville_categorie (ville_id, categorie),
            FOREIGN KEY (ville_id) REFERENCES villes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Tables existent deja
}

// Ajouter colonne ville_id a liste_logements si elle n'existe pas
try {
    $pdo->exec("ALTER TABLE liste_logements ADD COLUMN ville_id INT NULL");
} catch (PDOException $e) {
    // Colonne existe deja
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_ville':
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!empty($nom)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO villes (nom, description) VALUES (:nom, :description)");
                    $stmt->execute([':nom' => $nom, ':description' => $description ?: null]);
                    $message = "Ville ajoutee avec succes!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $message = "Cette ville existe deja!";
                    } else {
                        $message = "Erreur: " . $e->getMessage();
                    }
                    $messageType = "danger";
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
                    $message = "Ville mise a jour!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
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
                        $message = "Impossible de supprimer: $count logement(s) utilise(nt) cette ville.";
                        $messageType = "warning";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM villes WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $message = "Ville supprimee!";
                        $messageType = "success";
                        // Redirect pour eviter de rester sur une ville supprimee
                        header("Location: villes.php?msg=deleted");
                        exit;
                    }
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
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
                    $message = "Recommandation ajoutee!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
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
                    $message = "Recommandation mise a jour!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
            break;

        case 'delete_reco':
            $recoId = intval($_POST['reco_id'] ?? 0);
            if ($recoId > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM ville_recommandations WHERE id = :id");
                    $stmt->execute([':id' => $recoId]);
                    $message = "Recommandation supprimee!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erreur: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
            break;
    }
}

// Message de redirection
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Ville supprimee avec succes!";
    $messageType = "success";
}

// Recuperer toutes les villes avec stats
$villes = [];
try {
    // Essayer d'abord la requete complete avec le comptage des logements
    $stmt = $pdo->query("
        SELECT v.*,
               (SELECT COUNT(*) FROM ville_recommandations WHERE ville_id = v.id) as nb_recos,
               (SELECT COUNT(*) FROM liste_logements WHERE ville_id = v.id) as nb_logements
        FROM villes v
        ORDER BY v.nom
    ");
    $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si erreur (colonne ville_id n'existe pas), essayer sans le comptage des logements
    try {
        $stmt = $pdo->query("
            SELECT v.*,
                   (SELECT COUNT(*) FROM ville_recommandations WHERE ville_id = v.id) as nb_recos,
                   0 as nb_logements
            FROM villes v
            ORDER BY v.nom
        ");
        $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        // Ignorer les erreurs
    }
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
    } catch (PDOException $e) {}
}

require_once __DIR__ . '/../includes/header_minimal.php';
?>

<!-- Header de page -->
<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-4">
            <i class="fas fa-city text-primary"></i> Gestion des Villes
        </h1>
        <p class="lead text-muted">Configurez les recommandations (partenaires, restaurants, activites) par ville</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Liste des villes -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Villes</h5>
                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalAddVille">
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

        <div class="alert alert-info mt-3">
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
                        <button class="btn btn-light btn-sm" data-toggle="modal" data-target="#modalEditVille">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteVille()">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php if ($selectedVille['description']): ?>
                    <div class="card-body">
                        <p class="mb-0 text-muted"><?= htmlspecialchars($selectedVille['description']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Onglets recommandations -->
            <ul class="nav nav-tabs" id="recoTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tabPartenaires">
                        <i class="fas fa-handshake text-primary"></i> Partenaires
                        <span class="badge badge-primary"><?= count($recommandations['partenaire']) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tabRestaurants">
                        <i class="fas fa-utensils text-danger"></i> Restaurants
                        <span class="badge badge-danger"><?= count($recommandations['restaurant']) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tabActivites">
                        <i class="fas fa-hiking text-success"></i> Activites
                        <span class="badge badge-success"><?= count($recommandations['activite']) ?></span>
                    </a>
                </li>
            </ul>

            <div class="tab-content border border-top-0 p-3 bg-white">
                <!-- Partenaires -->
                <div class="tab-pane fade show active" id="tabPartenaires">
                    <?php if (!empty($recommandations['partenaire'])): ?>
                        <div class="row">
                            <?php foreach ($recommandations['partenaire'] as $reco): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 <?= $reco['actif'] ? '' : 'bg-light' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="card-title mb-1">
                                                    <i class="fas fa-handshake text-primary"></i>
                                                    <?= htmlspecialchars($reco['nom']) ?>
                                                    <?php if (!$reco['actif']): ?>
                                                        <span class="badge badge-secondary">Inactif</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-reco"
                                                        data-reco='<?= json_encode($reco) ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <?php if ($reco['description']): ?>
                                                <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($reco['adresse']): ?>
                                                <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($reco['telephone']): ?>
                                                <small><i class="fas fa-phone"></i> <?= htmlspecialchars($reco['telephone']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($reco['note_interne']): ?>
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
                        <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Aucun partenaire</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-success btn-add-reco" data-categorie="partenaire">
                        <i class="fas fa-plus"></i> Ajouter un partenaire
                    </button>
                </div>

                <!-- Restaurants -->
                <div class="tab-pane fade" id="tabRestaurants">
                    <?php if (!empty($recommandations['restaurant'])): ?>
                        <div class="row">
                            <?php foreach ($recommandations['restaurant'] as $reco): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 <?= $reco['actif'] ? '' : 'bg-light' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="card-title mb-1">
                                                    <i class="fas fa-utensils text-danger"></i>
                                                    <?= htmlspecialchars($reco['nom']) ?>
                                                    <?php if ($reco['prix_indicatif']): ?>
                                                        <span class="badge badge-success"><?= htmlspecialchars($reco['prix_indicatif']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!$reco['actif']): ?>
                                                        <span class="badge badge-secondary">Inactif</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-reco"
                                                        data-reco='<?= json_encode($reco) ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <?php if ($reco['description']): ?>
                                                <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($reco['adresse']): ?>
                                                <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($reco['telephone']): ?>
                                                <small><i class="fas fa-phone"></i> <?= htmlspecialchars($reco['telephone']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($reco['note_interne']): ?>
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
                        <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Aucun restaurant</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-success btn-add-reco" data-categorie="restaurant">
                        <i class="fas fa-plus"></i> Ajouter un restaurant
                    </button>
                </div>

                <!-- Activites -->
                <div class="tab-pane fade" id="tabActivites">
                    <?php if (!empty($recommandations['activite'])): ?>
                        <div class="row">
                            <?php foreach ($recommandations['activite'] as $reco): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 <?= $reco['actif'] ? '' : 'bg-light' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="card-title mb-1">
                                                    <i class="fas fa-hiking text-success"></i>
                                                    <?= htmlspecialchars($reco['nom']) ?>
                                                    <?php if ($reco['prix_indicatif']): ?>
                                                        <span class="badge badge-info"><?= htmlspecialchars($reco['prix_indicatif']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!$reco['actif']): ?>
                                                        <span class="badge badge-secondary">Inactif</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-reco"
                                                        data-reco='<?= json_encode($reco) ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <?php if ($reco['description']): ?>
                                                <p class="card-text small text-muted mb-1"><?= htmlspecialchars($reco['description']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($reco['adresse']): ?>
                                                <small><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($reco['adresse']) ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($reco['site_web']): ?>
                                                <small><i class="fas fa-globe"></i> <a href="<?= htmlspecialchars($reco['site_web']) ?>" target="_blank">Site web</a></small>
                                            <?php endif; ?>
                                            <?php if ($reco['note_interne']): ?>
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
                        <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Aucune activite</p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-success btn-add-reco" data-categorie="activite">
                        <i class="fas fa-plus"></i> Ajouter une activite
                    </button>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
                    <h5>Selectionnez une ville</h5>
                    <p class="text-muted">Cliquez sur une ville dans la liste pour gerer ses recommandations.</p>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#modalAddVille">
                        <i class="fas fa-plus"></i> Creer une nouvelle ville
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Ville -->
<div class="modal fade" id="modalAddVille" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" value="add_ville">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nouvelle ville</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom de la ville *</label>
                        <input type="text" name="nom" class="form-control" required placeholder="Ex: Paris, Lyon, Marseille...">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description optionnelle..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Creer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($selectedVille): ?>
<!-- Modal Modifier Ville -->
<div class="modal fade" id="modalEditVille" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="action" value="update_ville">
                <input type="hidden" name="ville_id" value="<?= $selectedVille['id'] ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier la ville</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom de la ville *</label>
                        <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($selectedVille['nom']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($selectedVille['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Recommandation -->
<div class="modal fade" id="modalReco" tabindex="-1">
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
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Nom *</label>
                        <input type="text" name="nom" id="reco_nom" class="form-control" required
                               placeholder="Nom du lieu ou de l'activite">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" id="reco_description" class="form-control" rows="2"
                                  placeholder="Courte description pour vos voyageurs"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Adresse</label>
                                <input type="text" name="adresse" id="reco_adresse" class="form-control"
                                       placeholder="Adresse complete">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Telephone</label>
                                <input type="text" name="telephone" id="reco_telephone" class="form-control"
                                       placeholder="06 XX XX XX XX">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Site web</label>
                                <input type="url" name="site_web" id="reco_site_web" class="form-control"
                                       placeholder="https://...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><i class="fas fa-euro-sign"></i> Prix indicatif</label>
                                <input type="text" name="prix_indicatif" id="reco_prix_indicatif" class="form-control"
                                       placeholder="Ex: 15-25EUR, Gratuit...">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Note interne (non visible par les voyageurs)</label>
                        <textarea name="note_interne" id="reco_note_interne" class="form-control" rows="2"
                                  placeholder="Notes personnelles, code promo partenaire..."></textarea>
                    </div>

                    <div class="custom-control custom-checkbox" id="reco_actif_container" style="display:none;">
                        <input type="checkbox" class="custom-control-input" name="actif" id="reco_actif" checked>
                        <label class="custom-control-label" for="reco_actif">Actif (visible)</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
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

<!-- Formulaires caches -->
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

<script>
const categorieLabels = {
    'partenaire': 'Partenaire',
    'restaurant': 'Restaurant',
    'activite': 'Activite'
};

// Supprimer la ville
function deleteVille() {
    if (confirm('Supprimer cette ville et toutes ses recommandations ?')) {
        document.getElementById('formDeleteVille').submit();
    }
}

// Supprimer une recommandation
function deleteReco() {
    if (confirm('Supprimer cette recommandation ?')) {
        document.getElementById('formDeleteReco').submit();
    }
}

// Ouvrir modal pour ajouter une reco
$('.btn-add-reco').click(function() {
    const categorie = $(this).data('categorie');

    $('#reco_action').val('add_reco');
    $('#reco_id').val('');
    $('#reco_categorie').val(categorie);
    $('#reco_nom').val('');
    $('#reco_description').val('');
    $('#reco_adresse').val('');
    $('#reco_telephone').val('');
    $('#reco_site_web').val('');
    $('#reco_prix_indicatif').val('');
    $('#reco_note_interne').val('');
    $('#reco_actif').prop('checked', true);

    $('#modalRecoTitle').html('<i class="fas fa-plus-circle"></i> Ajouter un ' + categorieLabels[categorie].toLowerCase());
    $('#reco_actif_container').hide();
    $('#btnDeleteReco').hide();

    $('#modalReco').modal('show');
});

// Ouvrir modal pour editer une reco
$('.btn-edit-reco').click(function() {
    const reco = $(this).data('reco');

    $('#reco_action').val('update_reco');
    $('#reco_id').val(reco.id);
    $('#delete_reco_id').val(reco.id);
    $('#reco_categorie').val(reco.categorie);
    $('#reco_nom').val(reco.nom);
    $('#reco_description').val(reco.description || '');
    $('#reco_adresse').val(reco.adresse || '');
    $('#reco_telephone').val(reco.telephone || '');
    $('#reco_site_web').val(reco.site_web || '');
    $('#reco_prix_indicatif').val(reco.prix_indicatif || '');
    $('#reco_note_interne').val(reco.note_interne || '');
    $('#reco_actif').prop('checked', reco.actif == 1);

    $('#modalRecoTitle').html('<i class="fas fa-edit"></i> Modifier : ' + reco.nom);
    $('#reco_actif_container').show();
    $('#btnDeleteReco').show();

    $('#modalReco').modal('show');
});
</script>
<?php endif; ?>

<style>
.bg-warning-light {
    background-color: #fff3cd;
}
</style>

<?php require_once __DIR__ . '/../includes/footer_minimal.php'; ?>
