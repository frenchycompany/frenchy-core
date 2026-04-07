<?php
/**
 * Gestion des logements — Page unifiée
 * Fusionne les fonctionnalités IONOS (ménage, m², prix) + Raspberry Pi (ICS, description, actif/inactif)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$feedback = '';

// ============================================================
// AUTO-MIGRATION : ajout colonne airbnb_url si absente
// ============================================================
try {
    $cols = array_column($conn->query("SHOW COLUMNS FROM liste_logements")->fetchAll(), 'Field');
    if (!in_array('airbnb_url', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `airbnb_url` VARCHAR(500) DEFAULT NULL AFTER `ics_url_2`");
    }
    if (!in_array('proprietaire_id', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `proprietaire_id` INT DEFAULT NULL");
    }
    if (!in_array('book_bienvenue_url', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `book_bienvenue_url` VARCHAR(500) DEFAULT NULL AFTER `airbnb_url`");
    }
    // Champs adresse structurée
    if (!in_array('adresse_ligne2', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `adresse_ligne2` VARCHAR(255) DEFAULT NULL AFTER `adresse`");
    }
    if (!in_array('code_postal', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `code_postal` VARCHAR(10) DEFAULT NULL AFTER `adresse_ligne2`");
    }
    if (!in_array('ville', $cols)) {
        $conn->exec("ALTER TABLE liste_logements ADD COLUMN `ville` VARCHAR(100) DEFAULT NULL AFTER `code_postal`");
    }
} catch (PDOException $e) {
    error_log('Migration logements : ' . $e->getMessage());
}

// ============================================================
// ACTIONS POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Ajouter un logement ---
    if (isset($_POST['add_logement'])) {
        $nom   = trim($_POST['nom_du_logement'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $adresse_ligne2 = trim($_POST['adresse_ligne2'] ?? '');
        $code_postal = trim($_POST['code_postal'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $m2    = (float) ($_POST['m2'] ?? 0);
        $nombre_de_personnes = (int) ($_POST['nombre_de_personnes'] ?? 0);
        $poid_menage = (float) ($_POST['poid_menage'] ?? 0);
        $prix_vente_menage = (float) ($_POST['prix_vente_menage'] ?? 0);
        $valeur_locative = (float) ($_POST['valeur_locative'] ?? 0);
        $valeur_fonciere = (float) ($_POST['valeur_fonciere'] ?? 0);
        $code  = trim($_POST['code'] ?? '');
        $ics_url = trim($_POST['ics_url'] ?? '');
        $ics_url_2 = trim($_POST['ics_url_2'] ?? '');
        $airbnb_url = trim($_POST['airbnb_url'] ?? '');
        $book_bienvenue_url = trim($_POST['book_bienvenue_url'] ?? '');
        $proprietaire_id = (int) ($_POST['proprietaire_id'] ?? 0) ?: null;

        if (empty($nom)) {
            $feedback = '<div class="alert alert-danger">Le nom du logement est obligatoire.</div>';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO liste_logements
                    (nom_du_logement, adresse, adresse_ligne2, code_postal, ville, description, m2, nombre_de_personnes, poid_menage,
                     prix_vente_menage, valeur_locative, valeur_fonciere, code, ics_url, ics_url_2, airbnb_url, book_bienvenue_url, proprietaire_id, actif)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$nom, $adresse ?: null, $adresse_ligne2 ?: null, $code_postal ?: null, $ville ?: null, $description ?: null, $m2, $nombre_de_personnes,
                    $poid_menage, $prix_vente_menage, $valeur_locative, $valeur_fonciere,
                    $code, $ics_url ?: null, $ics_url_2 ?: null, $airbnb_url ?: null, $book_bienvenue_url ?: null, $proprietaire_id]);
                $feedback = '<div class="alert alert-success">Logement ajouté avec succès.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Modifier un logement ---
    if (isset($_POST['update_logement'])) {
        $id    = (int) $_POST['logement_id'];
        $nom   = trim($_POST['nom_du_logement'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $adresse_ligne2 = trim($_POST['adresse_ligne2'] ?? '');
        $code_postal = trim($_POST['code_postal'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $m2    = (float) ($_POST['m2'] ?? 0);
        $nombre_de_personnes = (int) ($_POST['nombre_de_personnes'] ?? 0);
        $poid_menage = (float) ($_POST['poid_menage'] ?? 0);
        $prix_vente_menage = (float) ($_POST['prix_vente_menage'] ?? 0);
        $valeur_locative = (float) ($_POST['valeur_locative'] ?? 0);
        $valeur_fonciere = (float) ($_POST['valeur_fonciere'] ?? 0);
        $code  = trim($_POST['code'] ?? '');
        $ics_url = trim($_POST['ics_url'] ?? '');
        $ics_url_2 = trim($_POST['ics_url_2'] ?? '');
        $airbnb_url = trim($_POST['airbnb_url'] ?? '');
        $book_bienvenue_url = trim($_POST['book_bienvenue_url'] ?? '');
        $proprietaire_id = (int) ($_POST['proprietaire_id'] ?? 0) ?: null;

        if (empty($nom)) {
            $feedback = '<div class="alert alert-danger">Le nom du logement est obligatoire.</div>';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE liste_logements SET
                        nom_du_logement = ?, adresse = ?, adresse_ligne2 = ?, code_postal = ?, ville = ?, description = ?,
                        m2 = ?, nombre_de_personnes = ?, poid_menage = ?,
                        prix_vente_menage = ?, valeur_locative = ?, valeur_fonciere = ?,
                        code = ?, ics_url = ?, ics_url_2 = ?, airbnb_url = ?, book_bienvenue_url = ?, proprietaire_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $adresse ?: null, $adresse_ligne2 ?: null, $code_postal ?: null, $ville ?: null, $description ?: null, $m2, $nombre_de_personnes,
                    $poid_menage, $prix_vente_menage, $valeur_locative, $valeur_fonciere,
                    $code, $ics_url ?: null, $ics_url_2 ?: null, $airbnb_url ?: null, $book_bienvenue_url ?: null, $proprietaire_id, $id]);
                $feedback = '<div class="alert alert-success">Logement mis à jour.</div>';
            } catch (PDOException $e) {
                $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // --- Activer/Désactiver ---
    if (isset($_POST['toggle_actif'])) {
        $id = (int) $_POST['logement_id'];
        try {
            $stmt = $conn->prepare("UPDATE liste_logements SET actif = NOT actif WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $conn->prepare("SELECT actif, nom_du_logement FROM liste_logements WHERE id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            $status = $r['actif'] ? 'activé' : 'désactivé';
            $feedback = '<div class="alert alert-success">Logement "' . htmlspecialchars($r['nom_du_logement']) . '" ' . $status . '.</div>';
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // --- Supprimer ---
    if (isset($_POST['delete_logement'])) {
        $id = (int) $_POST['logement_id'];
        try {
            // Vérifier les réservations (RPi)
            $pdoRpi = getRpiPdo();
            $stmt = $pdoRpi->prepare("SELECT COUNT(*) FROM reservation WHERE logement_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $feedback = '<div class="alert alert-warning">Impossible de supprimer : ' . $count . ' réservation(s) associée(s).</div>';
            } else {
                $stmt = $conn->prepare("DELETE FROM liste_logements WHERE id = ?");
                $stmt->execute([$id]);
                $feedback = '<div class="alert alert-success">Logement supprimé.</div>';
            }
        } catch (PDOException $e) {
            $feedback = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES
// ============================================================

$logements = [];
try {
    // Logements + interventions (VPS)
    $logements = $conn->query("
        SELECT l.*,
               (SELECT COUNT(*) FROM planning p WHERE p.logement_id = l.id) AS nb_interventions
        FROM liste_logements l
        ORDER BY l.actif DESC, l.nom_du_logement ASC
    ")->fetchAll();

    // Comptage réservations par logement (RPi)
    $resaCounts = [];
    try {
        $pdoRpi = getRpiPdo();
        $rows = $pdoRpi->query("SELECT logement_id, COUNT(*) as cnt FROM reservation GROUP BY logement_id")->fetchAll();
        foreach ($rows as $row) { $resaCounts[$row['logement_id']] = $row['cnt']; }
    } catch (PDOException $e) { /* RPi injoignable */ }

    foreach ($logements as &$l) {
        $l['nb_reservations'] = $resaCounts[$l['id']] ?? 0;
    }
    unset($l);
} catch (PDOException $e) {
    $feedback .= '<div class="alert alert-danger">Erreur chargement logements : ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$nb_actifs = count(array_filter($logements, fn($l) => !empty($l['actif'])));
$nb_inactifs = count($logements) - $nb_actifs;

// Charger les propriétaires
$proprietaires_list = [];
try {
    $proprietaires_list = $conn->query("SELECT id, nom, prenom FROM FC_proprietaires WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table n'existe pas encore */ }
$proprietaires_index = [];
foreach ($proprietaires_list as $pr) {
    $proprietaires_index[$pr['id']] = trim($pr['nom'] . ' ' . ($pr['prenom'] ?? ''));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des logements — FrenchyConciergerie</title>
    <style>
        .badge-ics { font-size: 0.75rem; }
        .table td, .table th { vertical-align: middle; }
        .logement-inactif { opacity: 0.6; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-home text-primary"></i> Gestion des logements</h2>
            <p class="text-muted">
                <?= count($logements) ?> logement(s) —
                <span class="text-success"><?= $nb_actifs ?> actif(s)</span>
                <?php if ($nb_inactifs > 0): ?>
                    / <span class="text-secondary"><?= $nb_inactifs ?> inactif(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus"></i> Nouveau logement
            </button>
        </div>
    </div>

    <?= $feedback ?>

    <!-- Tableau des logements -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Adresse</th>
                            <th>m²</th>
                            <th>Pers.</th>
                            <th>Poids ménage</th>
                            <th>Prix ménage</th>
                            <th>iCal</th>
                            <th>Airbnb</th>
                            <th>Book</th>
                            <th>Proprio.</th>
                            <th>Résa.</th>
                            <th>Interv.</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logements as $l): ?>
                        <tr class="<?= empty($l['actif']) ? 'logement-inactif' : '' ?>">
                            <td><strong>#<?= $l['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($l['nom_du_logement']) ?></strong>
                                <?php if (!empty($l['description'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(mb_substr($l['description'], 0, 50)) ?><?= mb_strlen($l['description'] ?? '') > 50 ? '...' : '' ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= htmlspecialchars(mb_substr($l['adresse'] ?? '', 0, 40)) ?><?php if (!empty($l['code_postal']) || !empty($l['ville'])): ?><br><?= htmlspecialchars(trim(($l['code_postal'] ?? '') . ' ' . ($l['ville'] ?? ''))) ?><?php endif; ?></small></td>
                            <td><?= $l['m2'] ? $l['m2'] . ' m²' : '-' ?></td>
                            <td><?= $l['nombre_de_personnes'] ?: '-' ?></td>
                            <td><?= $l['poid_menage'] ? number_format($l['poid_menage'], 1) : '-' ?></td>
                            <td><?= $l['prix_vente_menage'] ? number_format($l['prix_vente_menage'], 2) . ' €' : '-' ?></td>
                            <td>
                                <?php if (!empty($l['ics_url'])): ?>
                                    <span class="badge bg-success badge-ics" title="<?= htmlspecialchars($l['ics_url']) ?>">
                                        <i class="fas fa-check"></i> Configuré
                                    </span>
                                    <?php if (!empty($l['ics_url_2'])): ?>
                                        <span class="badge bg-info badge-ics">+2</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-ics"><i class="fas fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['airbnb_url'])): ?>
                                    <a href="<?= htmlspecialchars($l['airbnb_url']) ?>" target="_blank" class="badge bg-danger badge-ics text-decoration-none" title="Voir l'annonce Airbnb">
                                        <i class="fas fa-external-link-alt"></i> Lien
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-ics"><i class="fas fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['book_bienvenue_url'])): ?>
                                    <a href="<?= htmlspecialchars($l['book_bienvenue_url']) ?>" target="_blank" class="badge bg-success badge-ics text-decoration-none" title="Book bienvenue">
                                        <i class="fas fa-book"></i> Lien
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-ics"><i class="fas fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($l['proprietaire_id']) && isset($proprietaires_index[$l['proprietaire_id']])): ?>
                                    <span class="badge bg-success"><?= htmlspecialchars($proprietaires_index[$l['proprietaire_id']]) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info"><?= $l['nb_reservations'] ?></span></td>
                            <td><span class="badge bg-primary"><?= $l['nb_interventions'] ?></span></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="logement_id" value="<?= $l['id'] ?>">
                                    <?php if (!empty($l['actif'])): ?>
                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-success" title="Désactiver">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="toggle_actif" class="btn btn-sm btn-secondary" title="Activer">
                                            <i class="fas fa-pause-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning"
                                        onclick="editLogement(<?= htmlspecialchars(json_encode($l)) ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($l['nb_reservations'] == 0 && $l['nb_interventions'] == 0): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce logement ?')">
                                    <?php echoCsrfField(); ?>
                                    <input type="hidden" name="logement_id" value="<?= $l['id'] ?>">
                                    <button type="submit" name="delete_logement" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logements)): ?>
                        <tr><td colspan="15" class="text-center text-muted py-4">Aucun logement enregistré.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL : Ajouter un logement                                  -->
<!-- ============================================================ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Nouveau logement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-body">
                    <div class="row">
                        <!-- Colonne gauche : infos générales -->
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Informations générales</h6>
                            <div class="mb-3">
                                <label class="form-label">Nom du logement *</label>
                                <input type="text" class="form-control" name="nom_du_logement" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 1</label>
                                <input type="text" class="form-control" name="adresse" placeholder="N° et rue">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 2</label>
                                <input type="text" class="form-control" name="adresse_ligne2" placeholder="Bâtiment, résidence, étage...">
                            </div>
                            <div class="row mb-3">
                                <div class="col-4">
                                    <label class="form-label">Code postal</label>
                                    <input type="text" class="form-control" name="code_postal" placeholder="60000" maxlength="10">
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Ville</label>
                                    <input type="text" class="form-control" name="ville" placeholder="Compiègne">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Surface (m²)</label>
                                    <input type="number" step="0.01" class="form-control" name="m2" value="0">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Capacité (pers.)</label>
                                    <input type="number" class="form-control" name="nombre_de_personnes" value="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Code d'accès</label>
                                <input type="text" class="form-control" name="code">
                            </div>
                        </div>
                        <!-- Colonne droite : ménage + sync -->
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Ménage & Tarification</h6>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Poids ménage</label>
                                    <input type="number" step="0.01" class="form-control" name="poid_menage" value="0">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Prix vente ménage (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="prix_vente_menage" value="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Valeur locative (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="valeur_locative" value="0">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Valeur foncière (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="valeur_fonciere" value="0">
                                </div>
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Synchronisation iCalendar</h6>
                            <div class="mb-3">
                                <label class="form-label">URL iCal principale</label>
                                <input type="url" class="form-control" name="ics_url" placeholder="https://www.airbnb.fr/calendar/ical/...">
                                <small class="form-text text-muted">Airbnb, Booking.com, etc.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL iCal secondaire</label>
                                <input type="url" class="form-control" name="ics_url_2" placeholder="https://...">
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Airbnb</h6>
                            <div class="mb-3">
                                <label class="form-label">URL annonce Airbnb</label>
                                <input type="url" class="form-control" name="airbnb_url" placeholder="https://www.airbnb.fr/rooms/...">
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Book de bienvenue</h6>
                            <div class="mb-3">
                                <label class="form-label">URL book bienvenue</label>
                                <input type="url" class="form-control" name="book_bienvenue_url" placeholder="https://...">
                                <small class="form-text text-muted">Lien vers le livret d'accueil du logement</small>
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Propriétaire</h6>
                            <div class="mb-3">
                                <select class="form-select" name="proprietaire_id">
                                    <option value="0">— Aucun —</option>
                                    <?php foreach ($proprietaires_list as $pr): ?>
                                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars(trim($pr['nom'] . ' ' . ($pr['prenom'] ?? ''))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_logement" class="btn btn-success">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL : Modifier un logement                                 -->
<!-- ============================================================ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Modifier le logement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echoCsrfField(); ?>
                <input type="hidden" name="logement_id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Informations générales</h6>
                            <div class="mb-3">
                                <label class="form-label">Nom du logement *</label>
                                <input type="text" class="form-control" name="nom_du_logement" id="edit_nom" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 1</label>
                                <input type="text" class="form-control" name="adresse" id="edit_adresse" placeholder="N° et rue">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse ligne 2</label>
                                <input type="text" class="form-control" name="adresse_ligne2" id="edit_adresse_ligne2" placeholder="Bâtiment, résidence, étage...">
                            </div>
                            <div class="row mb-3">
                                <div class="col-4">
                                    <label class="form-label">Code postal</label>
                                    <input type="text" class="form-control" name="code_postal" id="edit_code_postal" placeholder="60000" maxlength="10">
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Ville</label>
                                    <input type="text" class="form-control" name="ville" id="edit_ville" placeholder="Compiègne">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Surface (m²)</label>
                                    <input type="number" step="0.01" class="form-control" name="m2" id="edit_m2">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Capacité (pers.)</label>
                                    <input type="number" class="form-control" name="nombre_de_personnes" id="edit_pers">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Code d'accès</label>
                                <input type="text" class="form-control" name="code" id="edit_code">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Ménage & Tarification</h6>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Poids ménage</label>
                                    <input type="number" step="0.01" class="form-control" name="poid_menage" id="edit_poid">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Prix vente ménage (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="prix_vente_menage" id="edit_prix">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Valeur locative (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="valeur_locative" id="edit_locative">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Valeur foncière (€)</label>
                                    <input type="number" step="0.01" class="form-control" name="valeur_fonciere" id="edit_fonciere">
                                </div>
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Synchronisation iCalendar</h6>
                            <div class="mb-3">
                                <label class="form-label">URL iCal principale</label>
                                <input type="url" class="form-control" name="ics_url" id="edit_ics">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL iCal secondaire</label>
                                <input type="url" class="form-control" name="ics_url_2" id="edit_ics2">
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Airbnb</h6>
                            <div class="mb-3">
                                <label class="form-label">URL annonce Airbnb</label>
                                <input type="url" class="form-control" name="airbnb_url" id="edit_airbnb">
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Book de bienvenue</h6>
                            <div class="mb-3">
                                <label class="form-label">URL book bienvenue</label>
                                <input type="url" class="form-control" name="book_bienvenue_url" id="edit_book_bienvenue">
                                <small class="form-text text-muted">Lien vers le livret d'accueil du logement</small>
                            </div>

                            <h6 class="text-muted mb-3 mt-3">Propriétaire</h6>
                            <div class="mb-3">
                                <select class="form-select" name="proprietaire_id" id="edit_proprietaire">
                                    <option value="0">— Aucun —</option>
                                    <?php foreach ($proprietaires_list as $pr): ?>
                                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars(trim($pr['nom'] . ' ' . ($pr['prenom'] ?? ''))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_logement" class="btn btn-warning">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLogement(l) {
    document.getElementById('edit_id').value       = l.id;
    document.getElementById('edit_nom').value       = l.nom_du_logement || '';
    document.getElementById('edit_adresse').value   = l.adresse || '';
    document.getElementById('edit_adresse_ligne2').value = l.adresse_ligne2 || '';
    document.getElementById('edit_code_postal').value = l.code_postal || '';
    document.getElementById('edit_ville').value     = l.ville || '';
    document.getElementById('edit_description').value = l.description || '';
    document.getElementById('edit_m2').value        = l.m2 || 0;
    document.getElementById('edit_pers').value      = l.nombre_de_personnes || 0;
    document.getElementById('edit_poid').value      = l.poid_menage || 0;
    document.getElementById('edit_prix').value      = l.prix_vente_menage || 0;
    document.getElementById('edit_locative').value  = l.valeur_locative || 0;
    document.getElementById('edit_fonciere').value  = l.valeur_fonciere || 0;
    document.getElementById('edit_code').value      = l.code || '';
    document.getElementById('edit_ics').value       = l.ics_url || '';
    document.getElementById('edit_ics2').value      = l.ics_url_2 || '';
    document.getElementById('edit_airbnb').value    = l.airbnb_url || '';
    document.getElementById('edit_book_bienvenue').value = l.book_bienvenue_url || '';
    document.getElementById('edit_proprietaire').value = l.proprietaire_id || '0';

    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
