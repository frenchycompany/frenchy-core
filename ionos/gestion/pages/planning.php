<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// pages/planning.php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu de navigation

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');

// Auto-migration : colonne actif pour intervenant
try {
    $conn->exec("ALTER TABLE intervenant ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
} catch (PDOException $e) { /* colonne existe déjà */ }

// Auto-migration : colonnes checkup pour planning
try { $conn->exec("ALTER TABLE planning ADD COLUMN checkup_planifie TINYINT(1) NOT NULL DEFAULT 0 AFTER bonus"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE planning ADD COLUMN checkup_prix DECIMAL(10,2) DEFAULT 50.00 AFTER checkup_planifie"); } catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE planning ADD COLUMN checkup_intervenant_id INT DEFAULT NULL AFTER checkup_prix"); } catch (PDOException $e) {}

// ---------------------------------------------------------------------
// BONUS/COMPTA : enregistre le bonus fixe 10€ (5€ pour F1, 5€ pour F2)
// - supprime d'abord les anciens bonus liés à l'intervention
// - insère 0/1/2 lignes selon présence de F1/F2
// - si F1 = F2 : 2 lignes pour la même personne (5€ + 5€)
// Table comptabilité (schéma donné) : colonnes *Index (typeIndex, ...).
// Sécurisé par try/catch pour éviter une 500 si la table n'existe pas.
// ---------------------------------------------------------------------
function handleBonus(PDO $conn, int $planningId, bool $hasBonus, ?int $f1, ?int $f2, string $date_intervention): void {
    try {
        // supprimer anciens bonus
        $del = $conn->prepare("
            DELETE FROM comptabilite
            WHERE source_typeIndex = 'intervention'
              AND source_idIndex   = ?
              AND description LIKE 'Bonus%'
        ");
        $del->execute([$planningId]);

        if (!$hasBonus) return; // rien à insérer

        // insérer 2 lignes de 5€ (si f1/f2 non vides)
        $desc = "Bonus ménage (10€ réparti)";
        foreach ([$f1, $f2] as $iid) {
            if (empty($iid)) continue;
            $ins = $conn->prepare("
                INSERT INTO comptabilite
                (typeIndex, source_typeIndex, source_idIndex, intervenant_idIndex, montant, date_comptabilisationIndex, description)
                VALUES ('Charge','intervention', ?, ?, 5.00, ?, ?)
            ");
            $ins->execute([$planningId, $iid, $date_intervention, $desc]);
        }
    } catch (Throwable $e) {
        // on loggue si besoin mais on ne bloque pas l'UX
        // error_log('handleBonus error: '.$e->getMessage());
    }
}

// ---------------------------------------------------------------------

// Filtres de période, de statut et de logement
$date_debut      = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$date_fin        = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t');
$statut_filter   = isset($_GET['statut_filter']) ? $_GET['statut_filter'] : 'all';
$logement_filter = isset($_GET['logement_filter']) ? (int)$_GET['logement_filter'] : 0;

// --- Export CSV ---
if (isset($_GET['export_csv']) && $_GET['export_csv'] === '1') {
    $csvQuery = "
        SELECT p.date, l.nom_du_logement, p.nombre_de_personnes, p.nombre_de_jours_reservation,
               p.statut, c.nom AS conducteur_nom, fm1.nom AS femme1_nom, fm2.nom AS femme2_nom,
               lav.nom AS laverie_nom, p.note,
               CASE WHEN p.lit_bebe=1 THEN 'Oui' ELSE 'Non' END AS lit_bebe,
               CASE WHEN p.early_check_in=1 THEN 'Oui' ELSE 'Non' END AS early_check_in,
               CASE WHEN p.late_check_out=1 THEN 'Oui' ELSE 'Non' END AS late_check_out,
               CASE WHEN p.bonus=1 THEN 'Oui' ELSE 'Non' END AS bonus
        FROM planning p
        JOIN liste_logements l ON p.logement_id = l.id
        LEFT JOIN intervenant c ON p.conducteur = c.id
        LEFT JOIN intervenant fm1 ON p.femme_de_menage_1 = fm1.id
        LEFT JOIN intervenant fm2 ON p.femme_de_menage_2 = fm2.id
        LEFT JOIN intervenant lav ON p.laverie = lav.id
        WHERE p.date BETWEEN ? AND ?
    ";
    $csvParams = [$date_debut, $date_fin];
    if ($statut_filter !== 'all' && $statut_filter !== '') {
        $csvQuery .= " AND p.statut = ? ";
        $csvParams[] = $statut_filter;
    }
    if ($logement_filter > 0) {
        $csvQuery .= " AND p.logement_id = ? ";
        $csvParams[] = $logement_filter;
    }
    $csvQuery .= " ORDER BY p.date ASC";
    $csvStmt = $conn->prepare($csvQuery);
    $csvStmt->execute($csvParams);
    $csvRows = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="planning_' . $date_debut . '_' . $date_fin . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Logement', 'Personnes', 'Jours', 'Statut', 'Conducteur', 'Femme 1', 'Femme 2', 'Laverie', 'Note', 'Lit bébé', 'Early Check-in', 'Late Check-out', 'Bonus'], ';');
    foreach ($csvRows as $row) {
        fputcsv($out, array_values($row), ';');
    }
    fclose($out);
    exit;
}

// Gestion des actions individuelles (hors bulk update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_update'])) {
    $action = $_POST['action'] ?? '';

    // --- MODIFIER ---
    if ($action === 'modifier') {
        $id = (int)$_POST['id'];
        $statut = $_POST['statut'];

        if ($is_admin) {
            $date = $_POST['date'];
            $nombre_de_personnes = (int)$_POST['nombre_de_personnes'];
            $nombre_de_jours_reservation = (int)$_POST['nombre_de_jours_reservation'];
            $conducteur_id = !empty($_POST['conducteur_id']) ? (int)$_POST['conducteur_id'] : null;
            $femme_de_menage_1_id = !empty($_POST['femme_de_menage_1_id']) ? (int)$_POST['femme_de_menage_1_id'] : null;
            $femme_de_menage_2_id = !empty($_POST['femme_de_menage_2_id']) ? (int)$_POST['femme_de_menage_2_id'] : null;
            $laverie_id = !empty($_POST['laverie_id']) ? (int)$_POST['laverie_id'] : null;

            $lit_bebe = isset($_POST['lit_bebe']) ? 1 : 0;
            $nombre_lits_specifique = !empty($_POST['nombre_lits_specifique']) ? (int)$_POST['nombre_lits_specifique'] : null;
            $early_check_in = isset($_POST['early_check_in']) ? 1 : 0;
            $late_check_out = isset($_POST['late_check_out']) ? 1 : 0;
            $bonus = isset($_POST['bonus']) ? 1 : 0; // <-- BONUS (checkbox)
            $checkup_planifie = isset($_POST['checkup_planifie']) ? 1 : 0;
            $checkup_prix = !empty($_POST['checkup_prix']) ? (float)$_POST['checkup_prix'] : 50.00;
            $checkup_intervenant_id = !empty($_POST['checkup_intervenant_id']) ? (int)$_POST['checkup_intervenant_id'] : null;
            $note = $_POST['note'] ?? '';

            if ($nombre_de_jours_reservation < 0) {
                die("Erreur : Le nombre de jours réservés doit être positif ou nul.");
            }

            $stmt = $conn->prepare("
                UPDATE planning
                SET date = ?, nombre_de_personnes = ?, nombre_de_jours_reservation = ?, statut = ?,
                    conducteur = ?, femme_de_menage_1 = ?, femme_de_menage_2 = ?, laverie = ?,
                    lit_bebe = ?, nombre_lits_specifique = ?, early_check_in = ?, late_check_out = ?,
                    bonus = ?, checkup_planifie = ?, checkup_prix = ?, checkup_intervenant_id = ?, note = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $date, $nombre_de_personnes, $nombre_de_jours_reservation, $statut,
                $conducteur_id, $femme_de_menage_1_id, $femme_de_menage_2_id, $laverie_id,
                $lit_bebe, $nombre_lits_specifique, $early_check_in, $late_check_out,
                $bonus, $checkup_planifie, $checkup_prix, $checkup_intervenant_id, $note, $id
            ]);

            // BONUS/COMPTA
            handleBonus($conn, $id, (bool)$bonus, $femme_de_menage_1_id, $femme_de_menage_2_id, $date);
        } else {
            $stmt = $conn->prepare("UPDATE planning SET statut = ? WHERE id = ?");
            $stmt->execute([$statut, $id]);
        }

    // --- SUPPRIMER ---
    } elseif ($action === 'supprimer' && $is_admin) {
        $id = (int)$_POST['id'];

        // Nettoyer la compta associée avant suppression (best effort)
        try {
            $delC = $conn->prepare("DELETE FROM comptabilite WHERE source_typeIndex='intervention' AND source_idIndex=?");
            $delC->execute([$id]);
        } catch (Throwable $e) {}

        $stmt = $conn->prepare("DELETE FROM planning WHERE id = ?");
        $stmt->execute([$id]);

    // --- AJOUTER + GÉNÉRATION TOKEN ---
    } elseif ($action === 'ajouter' && $is_admin) {
        // Récupération des champs du formulaire
        $logement_id                 = (int)$_POST['logement_id'];
        $date                        = $_POST['date'];
        $nombre_de_personnes         = (int)$_POST['nombre_de_personnes'];
        $nombre_de_jours_reservation = (int)$_POST['nombre_de_jours_reservation'];
        $statut                      = $_POST['statut'];
        $conducteur_id               = !empty($_POST['conducteur_id']) ? (int)$_POST['conducteur_id'] : null;
        $femme_de_menage_1_id        = !empty($_POST['femme_de_menage_1_id']) ? (int)$_POST['femme_de_menage_1_id'] : null;
        $femme_de_menage_2_id        = !empty($_POST['femme_de_menage_2_id']) ? (int)$_POST['femme_de_menage_2_id'] : null;
        $laverie_id                  = !empty($_POST['laverie_id']) ? (int)$_POST['laverie_id'] : null;
        $lit_bebe                    = isset($_POST['lit_bebe']) ? 1 : 0;
        $nombre_lits_specifique      = !empty($_POST['nombre_lits_specifique']) ? (int)$_POST['nombre_lits_specifique'] : null;
        $early_check_in              = isset($_POST['early_check_in']) ? 1 : 0;
        $late_check_out              = isset($_POST['late_check_out']) ? 1 : 0;
        $bonus                       = isset($_POST['bonus']) ? 1 : 0; // <-- BONUS (checkbox)
        $checkup_planifie            = isset($_POST['checkup_planifie']) ? 1 : 0;
        $checkup_prix                = !empty($_POST['checkup_prix']) ? (float)$_POST['checkup_prix'] : 50.00;
        $checkup_intervenant_id      = !empty($_POST['checkup_intervenant_id']) ? (int)$_POST['checkup_intervenant_id'] : null;
        $note                        = $_POST['note'] ?? '';

        if ($nombre_de_jours_reservation < 0) {
            die("Erreur : Le nombre de jours réservés doit être positif ou nul.");
        }

        // 1) Insertion planning
        $stmt = $conn->prepare("
            INSERT INTO planning (
                logement_id,
                date,
                nombre_de_personnes,
                nombre_de_jours_reservation,
                statut,
                conducteur,
                femme_de_menage_1,
                femme_de_menage_2,
                laverie,
                lit_bebe,
                nombre_lits_specifique,
                early_check_in,
                late_check_out,
                bonus,
                checkup_planifie,
                checkup_prix,
                checkup_intervenant_id,
                note
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $logement_id,
            $date,
            $nombre_de_personnes,
            $nombre_de_jours_reservation,
            $statut,
            $conducteur_id,
            $femme_de_menage_1_id,
            $femme_de_menage_2_id,
            $laverie_id,
            $lit_bebe,
            $nombre_lits_specifique,
            $early_check_in,
            $late_check_out,
            $bonus,
            $checkup_planifie,
            $checkup_prix,
            $checkup_intervenant_id,
            $note
        ]);

        $intervention_id = (int)$conn->lastInsertId();

        // BONUS/COMPTA
        handleBonus($conn, $intervention_id, (bool)$bonus, $femme_de_menage_1_id, $femme_de_menage_2_id, $date);

        // 2) Génération token
        $token      = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

        // 3) Insertion token
        $tokStmt = $conn->prepare("
            INSERT INTO intervention_tokens (intervention_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $tokStmt->execute([$intervention_id, $token, $expires_at]);

        // 4) Liens
        $domain = 'https://gestion.frenchyconciergerie.fr';
        $validation_link = $domain . '/pages/validate.php?token=' . $token;

        // Recuperer nom du logement et intervenant checkup pour le message WhatsApp
        $logNom = $conn->prepare("SELECT nom_du_logement FROM liste_logements WHERE id = ?");
        $logNom->execute([$logement_id]);
        $nomLogement = $logNom->fetchColumn() ?: 'Logement';

        echo '<div class="alert alert-info">';
        echo 'Lien de validation : <a href="' . htmlspecialchars($validation_link) . '" target="_blank">' . htmlspecialchars($validation_link) . '</a>';
        echo '</div>';

        // Creer le checkup + WhatsApp si checkup planifie
        if ($checkup_planifie) {
            // Determiner l'intervenant du checkup (si pas specifie, prendre le conducteur)
            $ckIntervenantId = $checkup_intervenant_id ?: $conducteur_id;

            // Creer automatiquement la session de checkup
            require_once __DIR__ . '/../includes/checkup_create.php';
            // On utilise une fonction dediee pour creer la session avec tous les items
            $ckSessionId = createCheckupSession($conn, $logement_id, $ckIntervenantId);

            $checkup_link = $domain . '/pages/checkup_faire.php?session_id=' . $ckSessionId;

            // Recuperer le nom de l'intervenant checkup
            $ckNomStmt = $conn->prepare("SELECT nom FROM intervenant WHERE id = ?");
            $ckNomStmt->execute([$ckIntervenantId]);
            $ckNom = $ckNomStmt->fetchColumn() ?: '';

            $whatsMsg = "Bonjour" . ($ckNom ? " " . $ckNom : "") . " !\n\n";
            $whatsMsg .= "Un checkup est planifie pour *" . $nomLogement . "* le " . date('d/m/Y', strtotime($date)) . ".\n";
            $whatsMsg .= "Remuneration : " . number_format($checkup_prix, 2, ',', ' ') . " EUR\n\n";
            $whatsMsg .= "Lancer le checkup ici :\n" . $checkup_link;
            $whatsUrl = 'https://wa.me/';

            // Recuperer le numero de l'intervenant checkup
            if ($ckIntervenantId) {
                $phoneStmt = $conn->prepare("SELECT numero FROM intervenant WHERE id = ?");
                $phoneStmt->execute([$ckIntervenantId]);
                $phone = $phoneStmt->fetchColumn();
                if ($phone) {
                    $phone = preg_replace('/[^0-9]/', '', $phone);
                    if (substr($phone, 0, 1) === '0') $phone = '33' . substr($phone, 1);
                    $whatsUrl .= $phone;
                }
            }
            $whatsUrl .= '?text=' . rawurlencode($whatsMsg);

            echo '<div class="alert alert-success">';
            echo '<i class="fas fa-clipboard-check"></i> <strong>Checkup cree et attribue</strong>';
            if ($ckNom) echo ' a <strong>' . htmlspecialchars($ckNom) . '</strong>';
            echo ' (' . number_format($checkup_prix, 2, ',', ' ') . ' &euro;)<br>';
            echo '<a href="' . htmlspecialchars($checkup_link) . '" target="_blank" class="btn btn-sm btn-success mt-1"><i class="fas fa-clipboard-check"></i> Voir le Checkup</a> ';
            echo '<a href="' . htmlspecialchars($whatsUrl) . '" target="_blank" class="btn btn-sm mt-1" style="background:#25D366;color:#fff;"><i class="fab fa-whatsapp"></i> Envoyer par WhatsApp</a>';
            echo '</div>';
        }
    }
}


// --- Requêtes de liste/pagination ---

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$countQuery = "
    SELECT COUNT(*)
    FROM planning p
    JOIN liste_logements l ON p.logement_id = l.id
    WHERE p.date BETWEEN ? AND ?
";
$countParams = [$date_debut, $date_fin];
if ($statut_filter !== 'all' && $statut_filter !== '') {
    $countQuery .= " AND p.statut = ? ";
    $countParams[] = $statut_filter;
}
if ($logement_filter > 0) {
    $countQuery .= " AND p.logement_id = ? ";
    $countParams[] = $logement_filter;
}
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($countParams);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

$query = "
    SELECT 
        p.*, 
        l.nom_du_logement,
        c.nom AS conducteur_nom,
        fm1.nom AS femme_de_menage_1_nom,
        fm2.nom AS femme_de_menage_2_nom,
        lav.nom AS laverie_nom
    FROM planning p 
    JOIN liste_logements l ON p.logement_id = l.id
    LEFT JOIN intervenant c ON p.conducteur = c.id
    LEFT JOIN intervenant fm1 ON p.femme_de_menage_1 = fm1.id
    LEFT JOIN intervenant fm2 ON p.femme_de_menage_2 = fm2.id
    LEFT JOIN intervenant lav ON p.laverie = lav.id
    WHERE p.date BETWEEN ? AND ?
";
$params = [$date_debut, $date_fin];
if ($statut_filter !== 'all' && $statut_filter !== '') {
    $query .= " AND p.statut = ? ";
    $params[] = $statut_filter;
}
if ($logement_filter > 0) {
    $query .= " AND p.logement_id = ? ";
    $params[] = $logement_filter;
}
$query .= " ORDER BY p.date ASC LIMIT ? OFFSET ? ";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($query);
$stmt->bindValue(count($params)-1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params), $offset, PDO::PARAM_INT);
for ($i = 0; $i < count($params)-2; $i++) {
    $stmt->bindValue($i+1, $params[$i]);
}
$stmt->execute();
$interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
$allLogements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
$intervenants = $conn->query("SELECT id, nom FROM intervenant WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Compteur de charge par intervenant sur la période
$chargeQuery = "
    SELECT i.nom,
           SUM(CASE WHEN p.conducteur = i.id THEN 1 ELSE 0 END) AS nb_conducteur,
           SUM(CASE WHEN p.femme_de_menage_1 = i.id OR p.femme_de_menage_2 = i.id THEN 1 ELSE 0 END) AS nb_menage,
           SUM(CASE WHEN p.laverie = i.id THEN 1 ELSE 0 END) AS nb_laverie,
           COUNT(*) AS nb_total
    FROM intervenant i
    JOIN planning p ON (p.conducteur = i.id OR p.femme_de_menage_1 = i.id OR p.femme_de_menage_2 = i.id OR p.laverie = i.id)
    WHERE i.actif = 1 AND p.date BETWEEN ? AND ?
    GROUP BY i.id, i.nom
    ORDER BY nb_total DESC
";
$chargeStmt = $conn->prepare($chargeQuery);
$chargeStmt->execute([$date_debut, $date_fin]);
$charges = $chargeStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Éditer le planning</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
  <link
    rel="stylesheet"
    crossorigin="anonymous"
  />
  <style>
    /* Colorisation des lignes par statut */
    tr.statut-fait { background-color: #e8f5e9 !important; }
    tr.statut-a-faire { background-color: #fff3e0 !important; }
    tr.statut-a-verifier { background-color: #e3f2fd !important; }
    tr.statut-verifier { background-color: #fce4ec !important; }
    tr.statut-annule { background-color: #f5f5f5 !important; opacity: 0.65; text-decoration: line-through; }
    /* Compteur charge */
    .charge-cards { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
    .charge-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px 14px; min-width: 150px; flex: 1; }
    .charge-card .name { font-weight: 700; font-size: 0.95em; }
    .charge-card .counts { font-size: 0.82em; color: #666; }
    .charge-card .total { font-size: 1.3em; font-weight: 800; color: #1976d2; }
  </style>
</head>
<body>
<div class="container mt-4">
    <h2>Gestion du Planning</h2>

    <form method="GET" action="planning.php" class="mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date de début :</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($date_debut) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date de fin :</label>
                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($date_fin) ?>" required>
            </div>
            <div class="col-md-2">
                <label for="statut_filter" class="form-label">Statut :</label>
                <select id="statut_filter" name="statut_filter" class="form-control">
                    <option value="all" <?= $statut_filter == 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="À Faire" <?= $statut_filter == 'À Faire' ? 'selected' : '' ?>>À Faire</option>
                    <option value="À Vérifier" <?= $statut_filter == 'À Vérifier' ? 'selected' : '' ?>>À Vérifier</option>
                    <option value="Fait" <?= $statut_filter == 'Fait' ? 'selected' : '' ?>>Fait</option>
                    <option value="Vérifier" <?= $statut_filter == 'Vérifier' ? 'selected' : '' ?>>Vérifier</option>
                    <option value="Annulé" <?= $statut_filter == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="logement_filter" class="form-label">Logement :</label>
                <select id="logement_filter" name="logement_filter" class="form-control">
                    <option value="0">Tous</option>
                    <?php foreach ($allLogements as $lg): ?>
                    <option value="<?= $lg['id'] ?>" <?= $logement_filter == $lg['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lg['nom_du_logement']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="?date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&statut_filter=<?= urlencode($statut_filter) ?>&logement_filter=<?= $logement_filter ?>&export_csv=1" class="btn btn-outline-success" title="Export CSV">
                    <i class="fas fa-file-csv"></i>
                </a>
                <?php if ($is_admin): ?>
                <button type="button" id="sync_today_btn" style="display:none"></button>
                <div class="input-group">
                  <input type="date" id="sync_target_date" class="form-control" value="<?= htmlspecialchars(date(‘Y-m-d’)) ?>">
                  <button type="button" id="sync_by_date_btn" class="btn btn-outline-secondary">
                    <i class="fas fa-sync"></i> Sync
                  </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($is_admin): ?>
    <div class="mb-3">
        <h4>Modification en masse</h4>
        <div class="form-inline">
            <label for="bulk_status" class="mr-2">Nouveau statut :</label>
            <select id="bulk_status" class="form-control mr-2">
                <option value="À Faire">À Faire</option>
                <option value="À Vérifier">À Vérifier</option>
                <option value="Fait">Fait</option>
                <option value="Vérifier">Vérifier</option>
                <option value="Annulé">Annulé</option>
            </select>
            <button id="bulk_update_btn" type="button" class="btn btn-success">Appliquer aux sélectionnées</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Compteur de charge par intervenant -->
    <?php if (!empty($charges)): ?>
    <div class="mb-3">
        <h5>Charge intervenants (<?= date('d/m', strtotime($date_debut)) ?> — <?= date('d/m', strtotime($date_fin)) ?>)</h5>
        <div class="charge-cards">
            <?php foreach ($charges as $ch): ?>
            <div class="charge-card">
                <div class="name"><?= htmlspecialchars($ch['nom']) ?></div>
                <div class="total"><?= $ch['nb_total'] ?> interv.</div>
                <div class="counts">
                    <?php if ($ch['nb_conducteur'] > 0): ?>Cond: <?= $ch['nb_conducteur'] ?> <?php endif; ?>
                    <?php if ($ch['nb_menage'] > 0): ?>Mén: <?= $ch['nb_menage'] ?> <?php endif; ?>
                    <?php if ($ch['nb_laverie'] > 0): ?>Lav: <?= $ch['nb_laverie'] ?> <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <table class="table table-striped">
        <thead>
            <tr>
                <?php if ($is_admin): ?>
                <th data-label="Sélection"><input type="checkbox" id="select_all"></th>
                <?php endif; ?>
                <th data-label="Logement">Logement</th>
                <th data-label="Date">Date</th>
                <th data-label="Personnes">Personnes</th>
                <th data-label="Jours Réservés">Jours Réservés</th>
                <th data-label="Particularités">Particularités</th>
                <th data-label="Note">Note</th>
                <th data-label="Conducteur">Conducteur</th>
                <th data-label="Femme 1">Femme 1</th>
                <th data-label="Femme 2">Femme 2</th>
                <th data-label="Laverie">Laverie</th>
                <th data-label="Statut">Statut</th>
                <th data-label="Actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($interventions as $intervention):
                $statutSlugs = ['Fait'=>'fait','À Faire'=>'a-faire','À Vérifier'=>'a-verifier','Vérifier'=>'verifier','Annulé'=>'annule'];
                $statutSlug = $statutSlugs[$intervention['statut']] ?? '';
            ?>
                <tr id="row_<?= $intervention['id'] ?>" class="statut-<?= $statutSlug ?>">
                    <?php if ($is_admin): ?>
                    <td data-label="Sélection">
                        <input type="checkbox" class="bulk_checkbox" value="<?= $intervention['id'] ?>">
                    </td>
                    <?php endif; ?>
                    <td data-label="Logement"><?= htmlspecialchars($intervention['nom_du_logement']) ?></td>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" value="<?= $intervention['id'] ?>">
                        <td data-label="Date">
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($intervention['date']) ?>" <?= $is_admin ? '' : 'readonly' ?> required>
                        </td>
                        <td data-label="Personnes">
                            <input type="number" name="nombre_de_personnes" class="form-control" value="<?= htmlspecialchars($intervention['nombre_de_personnes'] ?? '') ?>" min="1" <?= $is_admin ? '' : 'readonly' ?>>
                        </td>
                        <td data-label="Jours Réservés">
                            <input type="number" name="nombre_de_jours_reservation" class="form-control" value="<?= htmlspecialchars($intervention['nombre_de_jours_reservation']) ?>" min="0" <?= $is_admin ? '' : 'readonly' ?> required>
                        </td>
                        
                        <td data-label="Particularités">
                            <div class="particulars-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lit_bebe" id="lit_bebe_<?= $intervention['id'] ?>" <?= !empty($intervention['lit_bebe']) ? 'checked' : '' ?> <?= $is_admin ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="lit_bebe_<?= $intervention['id'] ?>">Lit bébé</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="early_check_in" id="early_check_in_<?= $intervention['id'] ?>" <?= !empty($intervention['early_check_in']) ? 'checked' : '' ?> <?= $is_admin ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="early_check_in_<?= $intervention['id'] ?>">Early Check-in</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="late_check_out" id="late_check_out_<?= $intervention['id'] ?>" <?= !empty($intervention['late_check_out']) ? 'checked' : '' ?> <?= $is_admin ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="late_check_out_<?= $intervention['id'] ?>">Late Check-out</label>
                                </div>
                                <!-- BONUS (10€) -->
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="bonus" id="bonus_<?= $intervention['id'] ?>" <?= !empty($intervention['bonus']) ? 'checked' : '' ?> <?= $is_admin ? '' : 'disabled' ?>>
                                  <label class="form-check-label" for="bonus_<?= $intervention['id'] ?>">Bonus (10 €)</label>
                                </div>
                                <!-- CHECKUP -->
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="checkup_planifie" id="checkup_<?= $intervention['id'] ?>" <?= !empty($intervention['checkup_planifie']) ? 'checked' : '' ?> <?= $is_admin ? '' : 'disabled' ?> onchange="document.getElementById('ck_opts_<?= $intervention['id'] ?>').style.display=this.checked?'block':'none'">
                                  <label class="form-check-label" for="checkup_<?= $intervention['id'] ?>"><i class="fas fa-clipboard-check text-success"></i> Checkup</label>
                                </div>
                                <div id="ck_opts_<?= $intervention['id'] ?>" style="<?= !empty($intervention['checkup_planifie']) ? '' : 'display:none;' ?>font-size:0.82em;">
                                  <input type="number" name="checkup_prix" value="<?= htmlspecialchars($intervention['checkup_prix'] ?? '50') ?>" class="form-control form-control-sm mt-1" placeholder="Prix €" step="0.01" min="0" <?= $is_admin ? '' : 'readonly' ?>>
                                  <select name="checkup_intervenant_id" class="form-control form-control-sm mt-1" <?= $is_admin ? '' : 'disabled' ?>>
                                    <option value="">-- Checkup --</option>
                                    <?php foreach ($intervenants as $intv): ?>
                                      <option value="<?= $intv['id'] ?>" <?= ($intervention['checkup_intervenant_id'] ?? null) == $intv['id'] ? 'selected' : '' ?>><?= htmlspecialchars($intv['nom']) ?></option>
                                    <?php endforeach; ?>
                                  </select>
                                  <?php if (!empty($intervention['checkup_planifie'])): ?>
                                  <a href="checkup_logement.php?auto_logement=<?= $intervention['logement_id'] ?>" class="btn btn-sm btn-success mt-1" style="font-size:0.8em;"><i class="fas fa-clipboard-check"></i> Lancer</a>
                                  <?php endif; ?>
                                </div>

                                <select name="nombre_lits_specifique" class="form-control form-control-sm mt-1" <?= $is_admin ? '' : 'disabled' ?>>
                                    <option value="" <?= empty($intervention['nombre_lits_specifique']) ? 'selected' : '' ?>>Nb Lits...</option>
                                    <option value="2" <?= ($intervention['nombre_lits_specifique'] ?? null) == 2 ? 'selected' : '' ?>>2 Lits</option>
                                    <option value="3" <?= ($intervention['nombre_lits_specifique'] ?? null) == 3 ? 'selected' : '' ?>>3 Lits</option>
                                    <option value="4" <?= ($intervention['nombre_lits_specifique'] ?? null) == 4 ? 'selected' : '' ?>>4 Lits</option>
                                </select>
                            </div>
                        </td>
                        <td data-label="Note">
                            <textarea name="note" class="form-control" rows="2" <?= $is_admin ? '' : 'readonly' ?>><?= htmlspecialchars($intervention['note'] ?? '') ?></textarea>
                        </td>
                        
                        <td data-label="Conducteur">
                            <select name="conducteur_id" class="form-control" <?= $is_admin ? '' : 'disabled' ?>>
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($intervenants as $intervenant): ?>
                                <option value="<?= $intervenant['id'] ?>" <?= ($intervention['conducteur'] ?? null) == $intervenant['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($intervenant['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <!-- Femme 1 (facultatif) -->
<td data-label="Femme 1">
  <select name="femme_de_menage_1_id" class="form-control" <?= $is_admin ? '' : 'disabled' ?>>
    <option value="">-- Sélectionnez --</option>
    <?php foreach ($intervenants as $intervenant): ?>
      <option value="<?= $intervenant['id'] ?>"
        <?= ($intervention['femme_de_menage_1'] ?? null) == $intervenant['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($intervenant['nom']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</td>

<!-- Femme 2 (facultatif) -->
<td data-label="Femme 2">
  <select name="femme_de_menage_2_id" class="form-control" <?= $is_admin ? '' : 'disabled' ?>>
    <option value="">-- Sélectionnez --</option>
    <?php foreach ($intervenants as $intervenant): ?>
      <option value="<?= $intervenant['id'] ?>"
        <?= ($intervention['femme_de_menage_2'] ?? null) == $intervenant['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($intervenant['nom']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</td>

<!-- Laverie (facultatif) -->
<td data-label="Laverie">
  <select name="laverie_id" class="form-control" <?= $is_admin ? '' : 'disabled' ?>>
    <option value="">-- Sélectionnez --</option>
    <?php foreach ($intervenants as $intervenant): ?>
      <option value="<?= $intervenant['id'] ?>"
        <?= ($intervention['laverie'] ?? null) == $intervenant['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($intervenant['nom']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</td>


                        <td data-label="Statut" class="status_cell">
                            <select name="statut" class="form-control" required>
                                <option value="À Faire" <?= $intervention['statut'] === 'À Faire' ? 'selected' : '' ?>>À Faire</option>
                                <option value="À Vérifier" <?= $intervention['statut'] === 'À Vérifier' ? 'selected' : '' ?>>À Vérifier</option>
                                <option value="Fait" <?= $intervention['statut'] === 'Fait' ? 'selected' : '' ?>>Fait</option>
                                <option value="Vérifier" <?= $intervention['statut'] === 'Vérifier' ? 'selected' : '' ?>>Vérifier</option>
                                <option value="Annulé" <?= $intervention['statut'] === 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                            </select>
                        </td>
                        <td data-label="Actions">
                            <button type="submit" class="btn btn-primary btn-sm">Modifier</button>
                            <a href="checkup_logement.php?auto_logement=<?= $intervention['logement_id'] ?>" class="btn btn-success btn-sm" title="Lancer un checkup">
                                <i class="fas fa-clipboard-check"></i> Checkup
                            </a>
                            <?php if ($is_admin): ?>
                            <button type="submit" name="action" value="supprimer" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression ?')">Supprimer</button>
                            <?php endif; ?>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <nav>
      <ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
            <a class="page-link" href="?date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>&statut_filter=<?= urlencode($statut_filter) ?>&logement_filter=<?= $logement_filter ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>

    <?php if ($is_admin): ?>
    <h3>Créer une nouvelle intervention</h3>
    <form method="POST" action="">
        <input type="hidden" name="action" value="ajouter">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="logement_id">Logement :</label>
                <select name="logement_id" id="logement_id" class="form-control" required>
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($logements as $logement): ?>
                        <option value="<?= $logement['id'] ?>"><?= htmlspecialchars($logement['nom_du_logement']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-6">
                <label for="date">Date :</label>
                <input type="date" name="date" class="form-control" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="nombre_de_personnes">Nombre de personnes :</label>
                <input type="number" name="nombre_de_personnes" class="form-control" min="1">
            </div>
            <div class="form-group col-md-6">
                <label for="nombre_de_jours_reservation">Nombre de jours réservés :</label>
                <input type="number" name="nombre_de_jours_reservation" class="form-control" min="0" required>
            </div>
        </div>
        
        <fieldset class="border p-3 mb-3">
            <legend class="w-auto px-2">Particularités</legend>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Options :</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="lit_bebe" id="add_lit_bebe">
                        <label class="form-check-label" for="add_lit_bebe">Lit bébé</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="early_check_in" id="add_early_check_in">
                        <label class="form-check-label" for="add_early_check_in">Early Check-in</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="late_check_out" id="add_late_check_out">
                        <label class="form-check-label" for="add_late_check_out">Late Check-out</label>
                    </div>
                    <!-- BONUS (10€) -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="bonus" id="add_bonus">
                        <label class="form-check-label" for="add_bonus">Bonus (10 €)</label>
                    </div>
                    <!-- CHECKUP -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="checkup_planifie" id="add_checkup" onchange="document.getElementById('checkup_options').style.display=this.checked?'block':'none'">
                        <label class="form-check-label" for="add_checkup"><i class="fas fa-clipboard-check text-success"></i> Checkup planifie</label>
                    </div>
                    <div id="checkup_options" style="display:none;margin-top:6px;padding:8px;background:#e8f5e9;border-radius:8px;">
                        <div class="form-group mb-2">
                            <label for="add_checkup_prix" class="form-label" style="font-size:0.85em;">Prix checkup (&euro;)</label>
                            <input type="number" name="checkup_prix" id="add_checkup_prix" class="form-control form-control-sm" value="50" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="add_checkup_intervenant" class="form-label" style="font-size:0.85em;">Attribuer le checkup a</label>
                            <select name="checkup_intervenant_id" id="add_checkup_intervenant" class="form-control form-control-sm">
                                <option value="">-- Meme conducteur --</option>
                                <?php foreach ($intervenants as $intervenant): ?>
                                    <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                 <div class="form-group col-md-2">
                    <label for="add_nombre_lits_specifique">Nombre de lits :</label>
                    <select name="nombre_lits_specifique" id="add_nombre_lits_specifique" class="form-control">
                        <option value="">Aucun</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="add_note">Note :</label>
                    <textarea name="note" id="add_note" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </fieldset>

        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="conducteur_id">Conducteur :</label>
                <select name="conducteur_id" class="form-control">
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($intervenants as $intervenant): ?>
                        <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="femme_de_menage_1_id">Femme de Ménage 1 :</label>
                <select name="femme_de_menage_1_id" class="form-control">
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($intervenants as $intervenant): ?>
                        <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="femme_de_menage_2_id">Femme de Ménage 2 :</label>
                <select name="femme_de_menage_2_id" class="form-control">
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($intervenants as $intervenant): ?>
                        <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="laverie_id">Laverie :</label>
                <select name="laverie_id" class="form-control">
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($intervenants as $intervenant): ?>
                        <option value="<?= $intervenant['id'] ?>"><?= htmlspecialchars($intervenant['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="statut">Statut :</label>
            <select name="statut" class="form-control" required>
                <option value="À Faire">À Faire</option>
                <option value="À Vérifier">À Vérifier</option>
                <option value="Annulé">Annulé</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Créer</button>
    </form>
    <?php endif; ?>
</div>

<!-- Toast Bootstrap 5 -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1050;">
  <div id="notification_toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Notification</strong>
      <small class="text-muted"></small>
      <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Fermer"></button>
    </div>
    <div class="toast-body" id="toast_body"></div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>

<script>
  function showToast(message, type = 'success') {
    const body = document.getElementById('toast_body');
    if (body) body.textContent = message;

    const el = document.getElementById('notification_toast');
    if (!el) return;

    el.classList.remove('border-danger','border-success');
    el.classList.add(type === 'error' ? 'border-danger' : 'border-success');

    const t = new bootstrap.Toast(el, { delay: 3000 });
    t.show();
  }

  // Sync du jour
  document.addEventListener('DOMContentLoaded', function () {
    const syncBtn = document.getElementById('sync_today_btn');
    if (syncBtn) {
      syncBtn.addEventListener('click', function (e) {
        e.preventDefault();
        syncBtn.disabled = true;
        syncBtn.textContent = 'Synchronisation...';

        $.getJSON('sync_reservations_today.php?debug=1')
          .done(function(resp){
            if (resp.status === 'success') {
              showToast(`Synchro du jour OK : ${resp.inserted} créées, ${resp.updated} mises à jour.`);
              setTimeout(()=> location.reload(), 800);
            } else {
              showToast('Erreur synchro : ' + (resp.message || 'Inconnue'), 'error');
              console.error('SYNC error:', resp);
            }
          })
          .fail(function(xhr){
            let msg = `Erreur ${xhr.status || ''} pendant la synchro du jour.`;
            try {
              const j = JSON.parse(xhr.responseText);
              if (j && j.message) msg = j.message;
              if (j && j.ex) console.error('SYNC exception:', j.ex);
            } catch(e) {
              console.error('SYNC raw response:', xhr.responseText);
            }
            showToast(msg, 'error');
          })
          .always(function(){
            syncBtn.disabled = false;
            syncBtn.textContent = 'Synchroniser (aujourd\'hui)';
          });
      });
    }
  });

  function refreshSelectAllState() {
    const $items = $("input.bulk_checkbox");
    if ($items.length === 0) return;

    const total = $items.length;
    const checked = $items.filter(":checked").length;

    const $master = $("#select_all")[0];
    if (!$master) return;

    if (checked === 0) {
      $master.indeterminate = false;
      $master.checked = false;
    } else if (checked === total) {
      $master.indeterminate = false;
      $master.checked = true;
    } else {
      $master.indeterminate = true;
      $master.checked = false;
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    const master = document.getElementById("select_all");
    if (master) {
      const onMasterToggle = function (e) {
        const checked = e.currentTarget.checked;
        document.querySelectorAll("input.bulk_checkbox").forEach(cb => {
          cb.checked = checked;
        });
        refreshSelectAllState();
      };
      master.addEventListener("click", onMasterToggle);
      master.addEventListener("change", onMasterToggle);
    }

    document.addEventListener("change", function (e) {
      if (e.target && e.target.matches("input.bulk_checkbox")) {
        refreshSelectAllState();
      }
    });

    const bulkBtn = document.getElementById("bulk_update_btn");
    if (bulkBtn) {
      bulkBtn.addEventListener("click", function () {
        const new_status = document.getElementById("bulk_status").value;
        const ids = Array.from(document.querySelectorAll("input.bulk_checkbox:checked")).map(cb => cb.value);

        console.log("DEBUG - IDs sélectionnés:", ids);
        console.log("DEBUG - Statut sélectionné:", new_status);
        console.log("DEBUG - Données envoyées:", { intervention_ids: ids, new_status: new_status });

        if (ids.length === 0) {
          alert("Veuillez sélectionner au moins une intervention.");
          return;
        }
        if (!confirm("Confirmer la modification en masse ?")) return;

        $.ajax({
          url: 'bulk_update.php',
          type: 'POST',
          contentType: 'application/json',
          dataType: 'json',
          data: JSON.stringify({ intervention_ids: ids, new_status: new_status }),
          success: function (response) {
            if (response.status === 'success') {
              (response.updated_ids || []).forEach(function (id) {
                $("#row_" + id).find("select[name='statut']").val(response.new_status);
              });
              showToast("Mise à jour réussie (" + (response.updated_count || 0) + ").");
            } else {
              showToast("Erreur : " + (response.message || "Échec de la mise à jour."), 'error');
            }
          },
          error: function (xhr) {
            console.error("DEBUG - Erreur AJAX:", xhr);
            console.error("DEBUG - Status:", xhr.status);
            console.error("DEBUG - Response:", xhr.responseText);

            let msg = "Une erreur de communication est survenue lors de la mise à jour.";
            if (xhr && xhr.responseText) {
              try {
                const j = JSON.parse(xhr.responseText);
                console.error("DEBUG - Réponse JSON:", j);
                if (j && j.message) msg = j.message;

                // Afficher les infos de debug si disponibles
                if (j.debug_ids !== undefined) {
                  console.error("DEBUG - IDs reçus côté serveur:", j.debug_ids, "Type:", j.debug_type);
                }
                if (j.debug_status_received !== undefined) {
                  console.error("DEBUG - Statut reçu côté serveur:", j.debug_status_received);
                  console.error("DEBUG - Statuts autorisés:", j.debug_allowed);
                }
              } catch (e) {
                console.error("DEBUG - Erreur parsing JSON:", e);
              }
            }
            showToast(msg, 'error');
          }
        });
      });
    }

    refreshSelectAllState();
  });
</script>
<script>
$(document).on('click', '#sync_by_date_btn', function(e){
  e.preventDefault();
  const d = $('#sync_target_date').val();
  if(!d){ showToast("Choisis une date.", 'error'); return; }

  $.getJSON('sync_reservations_by_date.php', { date: d })
    .done(function(resp){
      if(resp.status === 'success'){
        showToast(`Synchro ${resp.day} OK : ${resp.inserted} créées, ${resp.skipped} ignorées.`);
        setTimeout(()=> location.search = `?date_debut=${d}&date_fin=${d}`, 650);
      } else {
        showToast('Erreur synchro : ' + (resp.message || 'Inconnue'), 'error');
        console.error(resp);
      }
    })
    .fail(function(xhr){
      console.error('SYNC BY DATE fail:', xhr.status, xhr.responseText);
      showToast(`Erreur ${xhr.status} pendant la synchro.`, 'error');
    });
});
</script>

</body>
</html>
