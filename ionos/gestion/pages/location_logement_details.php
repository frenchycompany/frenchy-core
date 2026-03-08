<?php
/**
 * Details personnalises par logement pour les contrats de location
 * Permet de definir description, equipements, regles, horaires par logement
 */
include '../config.php';
include '../pages/menu.php';

// Auto-creation table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS location_contract_logement_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logement_id INT NOT NULL,
        description_logement TEXT DEFAULT NULL,
        equipements TEXT DEFAULT NULL,
        regles_maison TEXT DEFAULT NULL,
        heure_arrivee VARCHAR(10) DEFAULT '16:00',
        heure_depart VARCHAR(10) DEFAULT '10:00',
        depot_garantie DECIMAL(10,2) DEFAULT NULL,
        taxe_sejour_par_nuit DECIMAL(10,2) DEFAULT NULL,
        conditions_annulation TEXT DEFAULT NULL,
        informations_supplementaires TEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$feedback = '';

// Traitement POST - sauvegarde des details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_details'])) {
    validateCsrfToken();

    $logement_id = (int)($_POST['logement_id'] ?? 0);
    if ($logement_id > 0) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO location_contract_logement_details
                    (logement_id, description_logement, equipements, regles_maison, heure_arrivee, heure_depart, depot_garantie, taxe_sejour_par_nuit, conditions_annulation, informations_supplementaires)
                VALUES
                    (:logement_id, :description, :equipements, :regles, :heure_arrivee, :heure_depart, :depot, :taxe, :annulation, :infos)
                ON DUPLICATE KEY UPDATE
                    description_logement = VALUES(description_logement),
                    equipements = VALUES(equipements),
                    regles_maison = VALUES(regles_maison),
                    heure_arrivee = VALUES(heure_arrivee),
                    heure_depart = VALUES(heure_depart),
                    depot_garantie = VALUES(depot_garantie),
                    taxe_sejour_par_nuit = VALUES(taxe_sejour_par_nuit),
                    conditions_annulation = VALUES(conditions_annulation),
                    informations_supplementaires = VALUES(informations_supplementaires),
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':logement_id' => $logement_id,
                ':description' => trim($_POST['description_logement'] ?? ''),
                ':equipements' => trim($_POST['equipements'] ?? ''),
                ':regles' => trim($_POST['regles_maison'] ?? ''),
                ':heure_arrivee' => trim($_POST['heure_arrivee'] ?? '16:00'),
                ':heure_depart' => trim($_POST['heure_depart'] ?? '10:00'),
                ':depot' => !empty($_POST['depot_garantie']) ? (float)$_POST['depot_garantie'] : null,
                ':taxe' => !empty($_POST['taxe_sejour_par_nuit']) ? (float)$_POST['taxe_sejour_par_nuit'] : null,
                ':annulation' => trim($_POST['conditions_annulation'] ?? ''),
                ':infos' => trim($_POST['informations_supplementaires'] ?? ''),
            ]);
            $feedback = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Details du logement enregistres</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Recuperer les logements actifs
$logements = [];
try {
    $stmt = $conn->query("SELECT id, nom_du_logement, adresse, ville FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement");
    $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Recuperer tous les details existants
$allDetails = [];
try {
    $stmt = $conn->query("SELECT * FROM location_contract_logement_details");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $allDetails[$row['logement_id']] = $row;
    }
} catch (PDOException $e) {}

// Logement selectionne
$selected_id = (int)($_GET['logement_id'] ?? $_POST['logement_id'] ?? 0);
$selectedDetails = $allDetails[$selected_id] ?? null;
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-home text-info"></i> Details logements — Contrats de location</h2>
            <p class="text-muted">Personnalisez les informations de chaque logement pour les contrats de location</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="list_location_templates.php" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt"></i> Modeles
            </a>
            <a href="create_location_contract.php" class="btn btn-warning text-dark">
                <i class="fas fa-file-signature"></i> Creer un contrat
            </a>
        </div>
    </div>

    <?= $feedback ?>

    <div class="row">
        <!-- Liste des logements -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Logements (<?= count($logements) ?>)</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($logements as $l): ?>
                        <?php
                        $hasDetails = isset($allDetails[$l['id']]);
                        $isSelected = $selected_id == $l['id'];
                        ?>
                        <a href="?logement_id=<?= $l['id'] ?>"
                           class="list-group-item list-group-item-action <?= $isSelected ? 'active' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong>
                                    <br><small class="<?= $isSelected ? 'text-white-50' : 'text-muted' ?>"><?= htmlspecialchars($l['adresse'] ?? '') ?> <?= htmlspecialchars($l['ville'] ?? '') ?></small>
                                </div>
                                <?php if ($hasDetails): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-minus"></i></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Formulaire details -->
        <div class="col-lg-8">
            <?php if ($selected_id > 0): ?>
                <?php
                $logementNom = '';
                foreach ($logements as $l) {
                    if ($l['id'] == $selected_id) { $logementNom = $l['nom_du_logement']; break; }
                }
                ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> <?= htmlspecialchars($logementNom) ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="logement_id" value="<?= $selected_id ?>">
                            <input type="hidden" name="save_details" value="1">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Heure d'arrivee</label>
                                    <input type="time" name="heure_arrivee" class="form-control"
                                           value="<?= htmlspecialchars($selectedDetails['heure_arrivee'] ?? '16:00') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Heure de depart</label>
                                    <input type="time" name="heure_depart" class="form-control"
                                           value="<?= htmlspecialchars($selectedDetails['heure_depart'] ?? '10:00') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Depot de garantie (EUR)</label>
                                    <input type="number" name="depot_garantie" class="form-control" step="0.01"
                                           value="<?= htmlspecialchars($selectedDetails['depot_garantie'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Taxe de sejour / nuit (EUR)</label>
                                    <input type="number" name="taxe_sejour_par_nuit" class="form-control" step="0.01"
                                           value="<?= htmlspecialchars($selectedDetails['taxe_sejour_par_nuit'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Description du logement</label>
                                <textarea name="description_logement" class="form-control" rows="3"
                                          placeholder="Description detaillee du logement..."><?= htmlspecialchars($selectedDetails['description_logement'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Equipements</label>
                                <textarea name="equipements" class="form-control" rows="3"
                                          placeholder="WiFi, TV, lave-linge, parking..."><?= htmlspecialchars($selectedDetails['equipements'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Regles de la maison</label>
                                <textarea name="regles_maison" class="form-control" rows="3"
                                          placeholder="Non-fumeur, pas d'animaux, silence apres 22h..."><?= htmlspecialchars($selectedDetails['regles_maison'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Conditions d'annulation</label>
                                <textarea name="conditions_annulation" class="form-control" rows="3"
                                          placeholder="Annulation gratuite jusqu'a 7 jours avant..."><?= htmlspecialchars($selectedDetails['conditions_annulation'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Informations supplementaires</label>
                                <textarea name="informations_supplementaires" class="form-control" rows="3"
                                          placeholder="Autres informations utiles..."><?= htmlspecialchars($selectedDetails['informations_supplementaires'] ?? '') ?></textarea>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fas fa-hand-pointer fa-4x mb-3"></i>
                        <h4>Selectionnez un logement</h4>
                        <p>Choisissez un logement dans la liste pour personnaliser ses details de contrat de location</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
