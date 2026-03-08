<?php
/**
 * CRM Prospection Proprietaires
 * Gestion complete des leads : Kanban, Liste, Entonnoir
 * Tables : prospection_leads, prospection_interactions
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';
require_once __DIR__ . '/../includes/lead_scoring.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../error.php?message=" . urlencode('Acces reserve aux administrateurs.'));
    exit;
}

$feedback = '';

// === STATUTS & LABELS ===
$statuts = ['nouveau', 'contacte', 'rdv_planifie', 'rdv_fait', 'proposition', 'negocie', 'converti', 'perdu'];
$statut_labels = [
    'nouveau'      => ['Nouveau', 'secondary'],
    'contacte'     => ['Contacte', 'info'],
    'rdv_planifie' => ['RDV planifie', 'primary'],
    'rdv_fait'     => ['RDV fait', 'indigo'],
    'proposition'  => ['Proposition', 'warning'],
    'negocie'      => ['Negocie', 'orange'],
    'converti'     => ['Converti', 'success'],
    'perdu'        => ['Perdu', 'danger'],
];
$statut_colors = [
    'nouveau'      => '#6c757d',
    'contacte'     => '#0dcaf0',
    'rdv_planifie' => '#0d6efd',
    'rdv_fait'     => '#6610f2',
    'proposition'  => '#ffc107',
    'negocie'      => '#fd7e14',
    'converti'     => '#198754',
    'perdu'        => '#dc3545',
];

$sources = ['simulateur', 'formulaire_contact', 'landing_page', 'concurrence', 'rdv_site', 'recommandation', 'demarchage', 'autre'];
$source_icons = [
    'simulateur'         => 'fa-calculator',
    'formulaire_contact' => 'fa-envelope',
    'landing_page'       => 'fa-gift',
    'concurrence'        => 'fa-chart-area',
    'rdv_site'           => 'fa-calendar-check',
    'recommandation'     => 'fa-handshake',
    'demarchage'         => 'fa-phone',
    'autre'              => 'fa-question-circle',
];

// === ACTIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    // --- Creer un lead ---
    if (isset($_POST['create_lead'])) {
        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $ville     = trim($_POST['ville'] ?? '');
        $source    = $_POST['source'] ?? 'autre';
        $notes     = trim($_POST['notes'] ?? '');

        if (!empty($nom)) {
            try {
                $data = [
                    'nom'       => $nom,
                    'prenom'    => $prenom ?: null,
                    'email'     => $email ?: null,
                    'telephone' => $telephone ?: null,
                    'ville'     => $ville ?: null,
                    'source'    => $source,
                    'notes'     => $notes ?: null,
                    'statut'    => 'nouveau',
                ];
                $newId = createLead($conn, $data);
                if ($newId) {
                    $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Lead cree avec succes (ID #$newId)</div>";
                } else {
                    $feedback = "<div class='alert alert-danger'>Erreur lors de la creation du lead.</div>";
                }
            } catch (Exception $e) {
                error_log("CRM create_lead error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Une erreur est survenue lors de la creation du lead.</div>";
            }
        }
    }

    // --- Mettre a jour le statut ---
    if (isset($_POST['update_statut'])) {
        $id     = (int)$_POST['lead_id'];
        $statut = $_POST['statut'] ?? '';

        if (in_array($statut, $statuts) && $id > 0) {
            try {
                $extraSql = '';
                if ($statut === 'contacte') {
                    $extraSql = ', date_premier_contact = COALESCE(date_premier_contact, CURDATE())';
                }
                $conn->prepare("UPDATE prospection_leads SET statut = ?, updated_at = NOW() $extraSql WHERE id = ?")
                    ->execute([$statut, $id]);
                updateLeadScore($conn, $id);
                $feedback = "<div class='alert alert-success'>Statut mis a jour.</div>";
            } catch (Exception $e) {
                error_log("CRM update_statut error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Erreur lors de la mise a jour du statut.</div>";
            }
        }
    }

    // --- Mise a jour complete d'un lead ---
    if (isset($_POST['update_lead'])) {
        $id = (int)$_POST['lead_id'];
        if ($id > 0) {
            try {
                $conn->prepare("
                    UPDATE prospection_leads SET
                        nom = ?, prenom = ?, telephone = ?, email = ?, ville = ?,
                        source = ?, priorite = ?, notes = ?,
                        prochaine_action = ?, date_prochaine_action = ?,
                        date_rdv = ?, type_rdv = ?, message_rdv = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    trim($_POST['nom']),
                    trim($_POST['prenom'] ?? '') ?: null,
                    trim($_POST['telephone'] ?? '') ?: null,
                    trim($_POST['email'] ?? '') ?: null,
                    trim($_POST['ville'] ?? '') ?: null,
                    $_POST['source'] ?? 'autre',
                    $_POST['priorite'] ?? 'moyenne',
                    trim($_POST['notes'] ?? '') ?: null,
                    trim($_POST['prochaine_action'] ?? '') ?: null,
                    !empty($_POST['date_prochaine_action']) ? $_POST['date_prochaine_action'] : null,
                    !empty($_POST['date_rdv']) ? $_POST['date_rdv'] : null,
                    !empty($_POST['type_rdv']) ? $_POST['type_rdv'] : null,
                    trim($_POST['message_rdv'] ?? '') ?: null,
                    $id,
                ]);
                updateLeadScore($conn, $id);
                $feedback = "<div class='alert alert-success'>Lead mis a jour.</div>";
            } catch (Exception $e) {
                error_log("CRM update_lead error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Erreur lors de la mise a jour du lead.</div>";
            }
        }
    }

    // --- Ajouter une interaction ---
    if (isset($_POST['add_interaction'])) {
        $lead_id = (int)$_POST['lead_id'];
        $type    = $_POST['type_interaction'] ?? 'note';
        $contenu = trim($_POST['contenu'] ?? '');

        if (!empty($contenu) && $lead_id > 0) {
            try {
                $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, ?, ?)")
                    ->execute([$lead_id, $type, $contenu]);
                $conn->prepare("UPDATE prospection_leads SET date_derniere_interaction = NOW(), updated_at = NOW() WHERE id = ?")
                    ->execute([$lead_id]);
                updateLeadScore($conn, $lead_id);
                $feedback = "<div class='alert alert-success'>Interaction ajoutee.</div>";
            } catch (Exception $e) {
                error_log("CRM add_interaction error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Erreur lors de l'ajout de l'interaction.</div>";
            }
        }
    }

    // --- Supprimer un lead ---
    if (isset($_POST['delete_lead'])) {
        $id = (int)$_POST['lead_id'];
        if ($id > 0) {
            try {
                $conn->prepare("DELETE FROM prospection_interactions WHERE lead_id = ?")->execute([$id]);
                $conn->prepare("DELETE FROM prospection_leads WHERE id = ?")->execute([$id]);
                $feedback = "<div class='alert alert-success'>Lead supprime.</div>";
                // Redirect to list after deletion
                header("Location: prospection_proprietaires.php?deleted=1");
                exit;
            } catch (Exception $e) {
                error_log("CRM delete_lead error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Erreur lors de la suppression du lead.</div>";
            }
        }
    }

    // --- Importer simulations ---
    if (isset($_POST['import_simulations'])) {
        try {
            $sims = $conn->query("
                SELECT s.* FROM FC_simulations s
                LEFT JOIN prospection_leads l ON l.legacy_simulation_id = s.id
                WHERE l.id IS NULL AND s.email IS NOT NULL AND s.email != ''
                ORDER BY s.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $imported = 0;
            foreach ($sims as $s) {
                $data = [
                    'nom'                   => $s['email'],
                    'email'                 => $s['email'],
                    'ville'                 => $s['ville'] ?? ($s['localisation'] ?? null),
                    'source'                => 'simulateur',
                    'surface'               => $s['surface'] ?? null,
                    'capacite'              => $s['capacite'] ?? ($s['nb_chambres'] ?? null),
                    'tarif_nuit_estime'     => $s['tarif_nuit_estime'] ?? null,
                    'revenu_mensuel_estime' => $s['revenu_mensuel_estime'] ?? null,
                    'statut'                => 'nouveau',
                    'legacy_simulation_id'  => $s['id'],
                    'notes'                 => 'Import automatique depuis simulateur. Type: ' . ($s['type_bien'] ?? '-') . ', Surface: ' . ($s['surface'] ?? '-') . 'm2',
                ];
                $newId = createLead($conn, $data);
                if ($newId) $imported++;
            }
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $imported simulation(s) importee(s) comme leads.</div>";
        } catch (Exception $e) {
            error_log("CRM import_simulations error: " . $e->getMessage());
            $feedback = "<div class='alert alert-danger'>Erreur lors de l'import des simulations.</div>";
        }
    }

    // --- Importer concurrence ---
    if (isset($_POST['import_concurrence'])) {
        try {
            $rpi = getRpiPdo();
            $multiOwners = $rpi->query("
                SELECT host_name, host_profile_id, COUNT(*) as nb_annonces,
                       ROUND(AVG(note_moyenne), 2) as note_moy,
                       GROUP_CONCAT(DISTINCT ville SEPARATOR ', ') as villes
                FROM market_competitors
                WHERE host_profile_id IS NOT NULL AND host_profile_id != ''
                GROUP BY host_profile_id
                HAVING COUNT(*) > 1
                ORDER BY nb_annonces DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $imported = 0;
            foreach ($multiOwners as $o) {
                // Check if already imported
                $existing = $conn->prepare("SELECT id FROM prospection_leads WHERE host_profile_id = ?");
                $existing->execute([$o['host_profile_id']]);
                if ($existing->fetch()) continue;

                $data = [
                    'nom'             => $o['host_name'] ?: 'Inconnu',
                    'host_profile_id' => $o['host_profile_id'],
                    'nb_annonces'     => $o['nb_annonces'],
                    'note_moyenne'    => $o['note_moy'],
                    'ville'           => $o['villes'],
                    'source'          => 'concurrence',
                    'statut'          => 'nouveau',
                ];
                $newId = createLead($conn, $data);
                if ($newId) $imported++;
            }
            $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $imported prospect(s) importe(s) depuis l'analyse concurrentielle.</div>";
        } catch (Exception $e) {
            error_log("CRM import_concurrence error: " . $e->getMessage());
            $feedback = "<div class='alert alert-danger'>Erreur lors de l'import depuis la concurrence.</div>";
        }
    }

    // --- Convertir un lead en proprietaire ---
    if (isset($_POST['convert_lead'])) {
        $id = (int)$_POST['lead_id'];
        if ($id > 0) {
            try {
                $conn->beginTransaction();

                // Retrieve lead data
                $stmt = $conn->prepare("SELECT * FROM prospection_leads WHERE id = ?");
                $stmt->execute([$id]);
                $lead = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {
                    throw new RuntimeException("Lead introuvable.");
                }

                // Create proprietaire entry
                $stmtProp = $conn->prepare("
                    INSERT INTO FC_proprietaires (nom, prenom, email, telephone, societe, siret, rib_iban, commission, notes, actif)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmtProp->execute([
                    trim($_POST['conv_nom'] ?? $lead['nom']),
                    trim($_POST['conv_prenom'] ?? $lead['prenom'] ?? '') ?: null,
                    trim($_POST['conv_email'] ?? $lead['email'] ?? '') ?: null,
                    trim($_POST['conv_telephone'] ?? $lead['telephone'] ?? '') ?: null,
                    trim($_POST['conv_societe'] ?? '') ?: null,
                    trim($_POST['conv_siret'] ?? '') ?: null,
                    trim($_POST['conv_iban'] ?? '') ?: null,
                    (float)($_POST['conv_commission'] ?? 20),
                    'Converti depuis CRM prospection, lead #' . $id,
                ]);
                $proprietaireId = $conn->lastInsertId();

                // Update lead
                $conn->prepare("
                    UPDATE prospection_leads SET statut = 'converti', proprietaire_id = ?, updated_at = NOW() WHERE id = ?
                ")->execute([$proprietaireId, $id]);

                // Add conversion interaction
                $conn->prepare("INSERT INTO prospection_interactions (lead_id, type, contenu) VALUES (?, 'conversion', ?)")
                    ->execute([$id, "Converti en proprietaire #$proprietaireId"]);

                updateLeadScore($conn, $id);
                $conn->commit();

                $feedback = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Lead converti en proprietaire #$proprietaireId.</div>";
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("CRM convert_lead error: " . $e->getMessage());
                $feedback = "<div class='alert alert-danger'>Erreur lors de la conversion du lead.</div>";
            }
        }
    }

    // Redirect to avoid repost (PRG) for non-redirect actions
    if (!headers_sent() && empty($feedback)) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Handle deleted redirect param
if (isset($_GET['deleted'])) {
    $feedback = "<div class='alert alert-success'>Lead supprime.</div>";
}

// === FILTERS ===
$filter_source  = $_GET['source'] ?? '';
$filter_statut  = $_GET['statut'] ?? '';
$filter_ville   = trim($_GET['ville'] ?? '');
$filter_score_min = $_GET['score_min'] ?? '';
$filter_score_max = $_GET['score_max'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to   = $_GET['date_to'] ?? '';

$where_clauses = [];
$where_params  = [];

if ($filter_source !== '' && in_array($filter_source, $sources)) {
    $where_clauses[] = "l.source = ?";
    $where_params[]  = $filter_source;
}
if ($filter_statut !== '' && in_array($filter_statut, $statuts)) {
    $where_clauses[] = "l.statut = ?";
    $where_params[]  = $filter_statut;
}
if ($filter_ville !== '') {
    $where_clauses[] = "l.ville LIKE ?";
    $where_params[]  = '%' . $filter_ville . '%';
}
if ($filter_score_min !== '' && is_numeric($filter_score_min)) {
    $where_clauses[] = "l.score >= ?";
    $where_params[]  = (int)$filter_score_min;
}
if ($filter_score_max !== '' && is_numeric($filter_score_max)) {
    $where_clauses[] = "l.score <= ?";
    $where_params[]  = (int)$filter_score_max;
}
if ($filter_date_from !== '') {
    $where_clauses[] = "l.created_at >= ?";
    $where_params[]  = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where_clauses[] = "l.created_at <= ?";
    $where_params[]  = $filter_date_to . ' 23:59:59';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// === Verifier que les tables existent, sinon les creer ===
try {
    $conn->query("SELECT 1 FROM prospection_leads LIMIT 1");
} catch (PDOException $e) {
    // Tables manquantes - les creer automatiquement
    $conn->exec("
        CREATE TABLE IF NOT EXISTS prospection_leads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            prenom VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            telephone VARCHAR(50) DEFAULT NULL,
            ville VARCHAR(255) DEFAULT NULL,
            source ENUM('simulateur','formulaire_contact','landing_page','concurrence','demarchage','recommandation','rdv_site','autre') DEFAULT 'autre',
            score INT DEFAULT 0,
            surface DECIMAL(10,2) DEFAULT NULL,
            capacite INT DEFAULT NULL,
            tarif_nuit_estime DECIMAL(10,2) DEFAULT NULL,
            revenu_mensuel_estime DECIMAL(10,2) DEFAULT NULL,
            equipements JSON DEFAULT NULL,
            statut ENUM('nouveau','contacte','rdv_planifie','rdv_fait','proposition','negocie','converti','perdu') DEFAULT 'nouveau',
            priorite ENUM('basse','moyenne','haute','urgente') DEFAULT 'moyenne',
            date_premier_contact DATE DEFAULT NULL,
            date_derniere_interaction DATETIME DEFAULT NULL,
            date_rdv DATETIME DEFAULT NULL,
            type_rdv ENUM('telephone','visio','physique') DEFAULT NULL,
            message_rdv TEXT DEFAULT NULL,
            prochaine_action VARCHAR(255) DEFAULT NULL,
            date_prochaine_action DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            proprietaire_id INT DEFAULT NULL,
            contrat_id INT DEFAULT NULL,
            legacy_simulation_id INT DEFAULT NULL,
            legacy_prospect_id INT DEFAULT NULL,
            host_profile_id VARCHAR(255) DEFAULT NULL,
            nb_annonces INT DEFAULT NULL,
            note_moyenne DECIMAL(3,2) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_statut (statut),
            INDEX idx_source (source),
            INDEX idx_score (score),
            INDEX idx_ville (ville),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS prospection_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            type ENUM('note','appel','email','sms','rdv','relance','proposition','contrat','conversion') DEFAULT 'note',
            contenu TEXT,
            user_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lead (lead_id),
            FOREIGN KEY (lead_id) REFERENCES prospection_leads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// === LOAD ALL LEADS (for kanban/funnel) ===
$all_leads = [];
$list_leads = [];
$villes_list = [];
$db_error = false;

try {
    $stmt_all = $conn->prepare("
        SELECT l.*,
            (SELECT COUNT(*) FROM prospection_interactions i WHERE i.lead_id = l.id) as nb_interactions
        FROM prospection_leads l
        $where_sql
        ORDER BY l.score DESC, l.updated_at DESC
    ");
    $stmt_all->execute($where_params);
    $all_leads = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("CRM load leads error: " . $e->getMessage());
    $db_error = true;
    $feedback = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Erreur de chargement des leads. Verifiez que les tables sont installees.</div>";
}

// === STATS ===
$total_leads = count($all_leads);
$stats = [];
foreach ($statuts as $s) {
    $stats[$s] = count(array_filter($all_leads, fn($l) => $l['statut'] === $s));
}
$taux_conversion = $total_leads > 0 ? round(($stats['converti'] / $total_leads) * 100, 1) : 0;

// === ALERTS ===
$alerts = [];

// Hot leads still at nouveau
$hot_nouveau = array_filter($all_leads, fn($l) => $l['score'] >= 60 && $l['statut'] === 'nouveau');
if (!empty($hot_nouveau)) {
    $alerts[] = [
        'type'  => 'danger',
        'icon'  => 'fa-fire',
        'text'  => count($hot_nouveau) . ' lead(s) chaud(s) encore au statut "Nouveau"',
        'leads' => $hot_nouveau,
    ];
}

// No contact for >48h
$no_contact = array_filter($all_leads, function ($l) {
    if (in_array($l['statut'], ['converti', 'perdu'])) return false;
    $ref = $l['date_derniere_interaction'] ?? $l['created_at'];
    return $ref && (time() - strtotime($ref)) > 48 * 3600;
});
if (!empty($no_contact)) {
    $alerts[] = [
        'type'  => 'warning',
        'icon'  => 'fa-clock',
        'text'  => count($no_contact) . ' lead(s) sans contact depuis plus de 48h',
        'leads' => $no_contact,
    ];
}

// RDV tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$rdv_tomorrow = array_filter($all_leads, fn($l) => !empty($l['date_rdv']) && date('Y-m-d', strtotime($l['date_rdv'])) === $tomorrow);
if (!empty($rdv_tomorrow)) {
    $alerts[] = [
        'type'  => 'info',
        'icon'  => 'fa-calendar-day',
        'text'  => count($rdv_tomorrow) . ' RDV demain',
        'leads' => $rdv_tomorrow,
    ];
}

// Propositions pending >7 days
$prop_old = array_filter($all_leads, function ($l) {
    if ($l['statut'] !== 'proposition') return false;
    return (time() - strtotime($l['updated_at'])) > 7 * 86400;
});
if (!empty($prop_old)) {
    $alerts[] = [
        'type'  => 'warning',
        'icon'  => 'fa-hourglass-half',
        'text'  => count($prop_old) . ' proposition(s) en attente depuis plus de 7 jours',
        'leads' => $prop_old,
    ];
}

// === VIEW MANAGEMENT ===
$view      = $_GET['view'] ?? 'kanban';
$detail_id = (int)($_GET['id'] ?? 0);
$detail    = null;
$interactions = [];

if ($detail_id > 0 && !$db_error) {
    $view = 'detail';
    try {
        $stmt = $conn->prepare("
            SELECT l.*,
                (SELECT COUNT(*) FROM prospection_interactions i WHERE i.lead_id = l.id) as nb_interactions
            FROM prospection_leads l WHERE l.id = ?
        ");
        $stmt->execute([$detail_id]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detail) {
            $stmt_inter = $conn->prepare("SELECT * FROM prospection_interactions WHERE lead_id = ? ORDER BY created_at DESC");
            $stmt_inter->execute([$detail_id]);
            $interactions = $stmt_inter->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("CRM detail lead error: " . $e->getMessage());
    }
}

// === PAGINATION (for list view) ===
$per_page     = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_pages  = max(1, ceil($total_leads / $per_page));
$offset       = ($current_page - 1) * $per_page;

// Sort for list view
$sort_col = $_GET['sort'] ?? 'score';
$sort_dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$valid_sorts = ['nom', 'score', 'ville', 'source', 'statut', 'created_at', 'updated_at'];
if (!in_array($sort_col, $valid_sorts)) $sort_col = 'score';

if (!$db_error) {
    try {
        $stmt_list = $conn->prepare("
            SELECT l.*,
                (SELECT COUNT(*) FROM prospection_interactions i WHERE i.lead_id = l.id) as nb_interactions
            FROM prospection_leads l
            $where_sql
            ORDER BY l.$sort_col $sort_dir
            LIMIT $per_page OFFSET $offset
        ");
        $stmt_list->execute($where_params);
        $list_leads = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

        // Get distinct villes for filter dropdown
        $villes_list = $conn->query("SELECT DISTINCT ville FROM prospection_leads WHERE ville IS NOT NULL AND ville != '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("CRM list leads error: " . $e->getMessage());
    }
}

// Helper: build filter query string
function buildFilterQs(array $overrides = []): string {
    $params = [];
    foreach (['source', 'statut', 'ville', 'score_min', 'score_max', 'date_from', 'date_to', 'view', 'sort', 'dir'] as $k) {
        $val = $overrides[$k] ?? ($_GET[$k] ?? '');
        if ($val !== '') $params[$k] = $val;
    }
    return http_build_query($params);
}

function sortLink(string $col, string $label): string {
    global $sort_col, $sort_dir;
    $newDir = ($sort_col === $col && $sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $qs = buildFilterQs(['sort' => $col, 'dir' => $newDir, 'view' => 'liste']);
    $icon = '';
    if ($sort_col === $col) {
        $icon = $sort_dir === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    }
    return "<a href=\"?$qs\" class=\"text-decoration-none text-dark\">$label$icon</a>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Prospection — FrenchyConciergerie</title>
    <style>
        /* Kanban */
        .kanban-board { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; min-height: 400px; }
        .kanban-col {
            min-width: 200px; max-width: 260px; flex: 1; background: #f8f9fa; border-radius: 10px; padding: 8px;
        }
        .kanban-col-header {
            text-align: center; padding: 6px 8px; border-radius: 8px; color: #fff; margin-bottom: 8px;
            font-size: 0.8em; font-weight: 700;
        }
        .lead-card {
            background: #fff; border-radius: 8px; padding: 10px; margin-bottom: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); cursor: pointer; transition: transform 0.15s;
            text-decoration: none; color: inherit; display: block;
        }
        .lead-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); color: inherit; }
        .lead-card .card-name { font-weight: 700; font-size: 0.88em; color: #333; }
        .lead-card .card-meta { font-size: 0.72em; color: #888; margin-top: 2px; }

        /* Funnel */
        .funnel-stage {
            display: flex; align-items: center; margin: 0 auto 6px; border-radius: 8px;
            color: #fff; font-weight: 600; font-size: 0.9em; padding: 10px 16px;
            transition: width 0.4s ease;
        }
        .funnel-stage .funnel-label { flex: 1; }
        .funnel-stage .funnel-count { font-size: 1.3em; font-weight: 800; }
        .funnel-stage .funnel-pct { font-size: 0.75em; opacity: 0.85; margin-left: 8px; }

        /* Score badges */
        .score-badge { font-size: 0.7em; padding: 2px 6px; border-radius: 10px; font-weight: 700; }

        /* Timeline */
        .timeline-item { position: relative; padding-left: 28px; padding-bottom: 16px; border-left: 2px solid #dee2e6; }
        .timeline-item:last-child { border-left-color: transparent; }
        .timeline-dot {
            position: absolute; left: -8px; top: 2px; width: 14px; height: 14px;
            border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #dee2e6;
        }

        /* Filter bar */
        .filter-bar { background: #f8f9fa; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; }
        .filter-bar .form-control, .filter-bar .form-select { font-size: 0.85em; }

        /* Stats cards */
        .stat-card { text-align: center; }
        .stat-card .stat-value { font-size: 1.6em; font-weight: 800; }
        .stat-card .stat-label { font-size: 0.75em; color: #888; }

        /* Responsive kanban */
        @media (max-width: 992px) {
            .kanban-board { flex-direction: column; }
            .kanban-col { min-width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="container-fluid mt-3">

    <?= $feedback ?>

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <div>
            <h2 class="mb-0"><i class="fas fa-funnel-dollar"></i> CRM Prospection</h2>
            <p class="text-muted mb-0">Gestion des leads proprietaires</p>
        </div>
        <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-file-import"></i> Importer
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <form method="POST" class="px-3 py-1">
                            <?php echoCsrfField(); ?>
                            <button type="submit" name="import_simulations" class="dropdown-item"><i class="fas fa-calculator"></i> Simulations</button>
                        </form>
                    </li>
                    <li>
                        <form method="POST" class="px-3 py-1">
                            <?php echoCsrfField(); ?>
                            <button type="submit" name="import_concurrence" class="dropdown-item"><i class="fas fa-chart-area"></i> Concurrence</button>
                        </form>
                    </li>
                </ul>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateLead">
                <i class="fas fa-plus"></i> Nouveau lead
            </button>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if (!empty($alerts) && $view !== 'detail'): ?>
    <div class="mb-3">
        <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?= $alert['type'] ?> py-2 mb-2">
            <i class="fas <?= $alert['icon'] ?>"></i>
            <strong><?= htmlspecialchars($alert['text']) ?></strong>
            <span class="ms-2">
                <?php foreach (array_slice($alert['leads'], 0, 5) as $al): ?>
                    <a href="?id=<?= $al['id'] ?>" class="badge bg-light text-dark text-decoration-none"><?= htmlspecialchars($al['nom'] ?? 'Sans nom') ?></a>
                <?php endforeach; ?>
                <?php if (count($alert['leads']) > 5): ?>
                    <span class="badge bg-light text-dark">+<?= count($alert['leads']) - 5 ?></span>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- STATS ROW -->
    <?php if ($view !== 'detail'): ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value"><?= $total_leads ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value" style="color:<?= $statut_colors['nouveau'] ?>"><?= $stats['nouveau'] ?></div>
                    <div class="stat-label">Nouveaux</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value" style="color:<?= $statut_colors['rdv_planifie'] ?>"><?= $stats['rdv_planifie'] + $stats['rdv_fait'] ?></div>
                    <div class="stat-label">RDV</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value" style="color:<?= $statut_colors['proposition'] ?>"><?= $stats['proposition'] + $stats['negocie'] ?></div>
                    <div class="stat-label">Propositions</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value" style="color:<?= $statut_colors['converti'] ?>"><?= $stats['converti'] ?></div>
                    <div class="stat-label">Convertis</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card stat-card">
                <div class="card-body py-2">
                    <div class="stat-value" style="color:#198754"><?= $taux_conversion ?>%</div>
                    <div class="stat-label">Taux conversion</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-0 small">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    <?php foreach ($sources as $src): ?>
                    <option value="<?= $src ?>" <?= $filter_source === $src ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $src)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    <?php foreach ($statuts as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_statut === $s ? 'selected' : '' ?>><?= $statut_labels[$s][0] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-0 small">Ville</label>
                <input type="text" name="ville" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_ville) ?>" placeholder="Ville">
            </div>
            <div class="col-md-1">
                <label class="form-label mb-0 small">Score min</label>
                <input type="number" name="score_min" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_score_min) ?>" min="0" max="100" placeholder="0">
            </div>
            <div class="col-md-1">
                <label class="form-label mb-0 small">Score max</label>
                <input type="number" name="score_max" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_score_max) ?>" min="0" max="100" placeholder="100">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small">Du</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-0 small">Au</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                <a href="?view=<?= htmlspecialchars($view) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>

    <!-- VIEW TABS -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'kanban' ? 'active' : '' ?>" href="?view=kanban&<?= buildFilterQs(['view' => 'kanban']) ?>">
                <i class="fas fa-columns"></i> Kanban
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'liste' ? 'active' : '' ?>" href="?view=liste&<?= buildFilterQs(['view' => 'liste']) ?>">
                <i class="fas fa-list"></i> Liste
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'entonnoir' ? 'active' : '' ?>" href="?view=entonnoir&<?= buildFilterQs(['view' => 'entonnoir']) ?>">
                <i class="fas fa-filter"></i> Entonnoir
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <?php if ($view === 'detail' && $detail): ?>
    <!-- ========== DETAIL VIEW ========== -->
    <div class="mb-3">
        <a href="?view=kanban" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="row">
        <!-- Left: Lead info -->
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-tie"></i>
                        <?= htmlspecialchars($detail['nom'] ?? '') ?>
                        <?= htmlspecialchars($detail['prenom'] ?? '') ?>
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        <?php $badge = getScoreBadge((int)$detail['score']); ?>
                        <span class="badge <?= $badge['class'] ?> score-badge"><?= (int)$detail['score'] ?> - <?= $badge['label'] ?></span>
                        <span class="badge" style="background:<?= $statut_colors[$detail['statut']] ?>; color:#fff;">
                            <?= $statut_labels[$detail['statut']][0] ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="lead_id" value="<?= $detail['id'] ?>">

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Nom *</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($detail['nom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Prenom</label>
                                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($detail['prenom'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ville</label>
                                <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($detail['ville'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Telephone</label>
                                <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($detail['telephone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($detail['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Source</label>
                                <select name="source" class="form-select">
                                    <?php foreach ($sources as $src): ?>
                                    <option value="<?= $src ?>" <?= ($detail['source'] ?? '') === $src ? 'selected' : '' ?>>
                                        <?= ucfirst(str_replace('_', ' ', $src)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priorite</label>
                                <select name="priorite" class="form-select">
                                    <?php foreach (['haute', 'moyenne', 'basse'] as $p): ?>
                                    <option value="<?= $p ?>" <?= ($detail['priorite'] ?? '') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Profil Airbnb</label>
                                <?php if (!empty($detail['host_profile_id'])): ?>
                                <a href="https://www.airbnb.fr/users/show/<?= htmlspecialchars($detail['host_profile_id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary d-block">
                                    <i class="fas fa-external-link-alt"></i> Voir profil
                                </a>
                                <?php else: ?>
                                <input type="text" class="form-control" disabled value="Non renseigne">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">RDV</label>
                                <input type="datetime-local" name="date_rdv" class="form-control" value="<?= !empty($detail['date_rdv']) ? date('Y-m-d\TH:i', strtotime($detail['date_rdv'])) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type RDV</label>
                                <select name="type_rdv" class="form-select">
                                    <option value="">-</option>
                                    <?php foreach (['telephone', 'visio', 'physique'] as $t): ?>
                                    <option value="<?= $t ?>" <?= ($detail['type_rdv'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Message RDV</label>
                                <input type="text" name="message_rdv" class="form-control" value="<?= htmlspecialchars($detail['message_rdv'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Prochaine action</label>
                                <input type="text" name="prochaine_action" class="form-control" value="<?= htmlspecialchars($detail['prochaine_action'] ?? '') ?>" placeholder="Ex: Appeler pour proposer un RDV">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date prochaine action</label>
                                <input type="date" name="date_prochaine_action" class="form-control" value="<?= htmlspecialchars($detail['date_prochaine_action'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($detail['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="update_lead" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                            <button type="submit" name="delete_lead" class="btn btn-outline-danger" onclick="return confirm('Supprimer definitivement ce lead et son historique ?')">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick status change -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Pipeline</h6></div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <?php foreach ($statuts as $s):
                        $active = $detail['statut'] === $s;
                    ?>
                    <form method="POST" class="d-inline">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="lead_id" value="<?= $detail['id'] ?>">
                        <input type="hidden" name="statut" value="<?= $s ?>">
                        <button type="submit" name="update_statut"
                            class="btn btn-sm <?= $active ? '' : 'btn-outline-' ?><?= $active ? 'btn-' : '' ?><?= $statut_labels[$s][1] === 'indigo' || $statut_labels[$s][1] === 'orange' ? 'secondary' : $statut_labels[$s][1] ?>"
                            style="<?= $active ? 'background:' . $statut_colors[$s] . ';border-color:' . $statut_colors[$s] . ';color:#fff;' : 'border-color:' . $statut_colors[$s] . ';color:' . $statut_colors[$s] . ';' ?>"
                            <?= $active ? 'disabled' : '' ?>>
                            <?= $statut_labels[$s][0] ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Simulator data if available -->
            <?php if (!empty($detail['surface']) || !empty($detail['capacite']) || !empty($detail['revenu_mensuel_estime']) || !empty($detail['nb_annonces'])): ?>
            <div class="card mb-3">
                <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-chart-bar"></i> Donnees du bien</h6></div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php if (!empty($detail['surface'])): ?>
                        <div class="col"><strong><?= $detail['surface'] ?> m<sup>2</sup></strong><br><small class="text-muted">Surface</small></div>
                        <?php endif; ?>
                        <?php if (!empty($detail['capacite'])): ?>
                        <div class="col"><strong><?= $detail['capacite'] ?></strong><br><small class="text-muted">Capacite</small></div>
                        <?php endif; ?>
                        <?php if (!empty($detail['tarif_nuit_estime'])): ?>
                        <div class="col"><strong><?= number_format($detail['tarif_nuit_estime'], 0, ',', ' ') ?> &euro;/nuit</strong><br><small class="text-muted">Tarif estime</small></div>
                        <?php endif; ?>
                        <?php if (!empty($detail['revenu_mensuel_estime'])): ?>
                        <div class="col"><strong><?= number_format($detail['revenu_mensuel_estime'], 0, ',', ' ') ?> &euro;/mois</strong><br><small class="text-muted">Revenu estime</small></div>
                        <?php endif; ?>
                        <?php if (!empty($detail['nb_annonces'])): ?>
                        <div class="col"><strong><?= $detail['nb_annonces'] ?></strong><br><small class="text-muted">Annonces</small></div>
                        <?php endif; ?>
                        <?php if (!empty($detail['note_moyenne'])): ?>
                        <div class="col"><strong><?= $detail['note_moyenne'] ?></strong><br><small class="text-muted">Note moy.</small></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick actions -->
            <div class="d-flex gap-2 mb-3">
                <?php if ($detail['statut'] !== 'converti'): ?>
                <button class="btn btn-outline-primary btn-sm" onclick="document.getElementById('interType').value='rdv'; document.getElementById('interContent').focus();">
                    <i class="fas fa-calendar-plus"></i> Planifier RDV
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('interType').value='note'; document.getElementById('interContent').focus();">
                    <i class="fas fa-sticky-note"></i> Ajouter note
                </button>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalConvert">
                    <i class="fas fa-exchange-alt"></i> Convertir en proprietaire
                </button>
                <?php else: ?>
                <?php if (!empty($detail['proprietaire_id'])): ?>
                <a href="proprietaires.php?id=<?= (int)$detail['proprietaire_id'] ?>" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-user-check"></i> Voir proprietaire #<?= (int)$detail['proprietaire_id'] ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Interactions -->
        <div class="col-lg-5">
            <!-- Add interaction -->
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus"></i> Nouvelle interaction</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <?php echoCsrfField(); ?>
                        <input type="hidden" name="lead_id" value="<?= $detail['id'] ?>">
                        <div class="mb-2">
                            <select name="type_interaction" id="interType" class="form-select form-select-sm">
                                <option value="note">Note</option>
                                <option value="appel">Appel</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="rdv">RDV</option>
                                <option value="relance">Relance</option>
                                <option value="proposition">Proposition</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="contenu" id="interContent" class="form-control form-control-sm" rows="3" placeholder="Details de l'interaction..." required></textarea>
                        </div>
                        <button type="submit" name="add_interaction" class="btn btn-sm btn-primary w-100"><i class="fas fa-plus"></i> Ajouter</button>
                    </form>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-history"></i> Historique (<?= count($interactions) ?>)</h6></div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($interactions)): ?>
                        <p class="text-muted text-center">Aucune interaction enregistree</p>
                    <?php else: ?>
                        <?php
                        $type_icons = [
                            'note'        => ['fa-sticky-note', '#6c757d'],
                            'appel'       => ['fa-phone', '#0d6efd'],
                            'email'       => ['fa-envelope', '#0dcaf0'],
                            'sms'         => ['fa-comment', '#198754'],
                            'rdv'         => ['fa-handshake', '#6610f2'],
                            'relance'     => ['fa-redo', '#fd7e14'],
                            'proposition' => ['fa-file-contract', '#ffc107'],
                            'contrat'     => ['fa-file-signature', '#198754'],
                            'conversion'  => ['fa-check-circle', '#198754'],
                        ];
                        foreach ($interactions as $inter):
                            $ti = $type_icons[$inter['type']] ?? ['fa-circle', '#6c757d'];
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-dot" style="background:<?= $ti[1] ?>;"></div>
                            <div class="d-flex justify-content-between">
                                <strong class="small"><i class="fas <?= $ti[0] ?>"></i> <?= ucfirst($inter['type']) ?></strong>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($inter['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small mt-1"><?= nl2br(htmlspecialchars($inter['contenu'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Convert -->
    <?php if ($detail && $detail['statut'] !== 'converti'): ?>
    <div class="modal fade" id="modalConvert" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?php echoCsrfField(); ?>
                    <input type="hidden" name="lead_id" value="<?= $detail['id'] ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Convertir en proprietaire</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Cette action va creer une fiche proprietaire et marquer le lead comme converti.</p>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom *</label>
                                <input type="text" name="conv_nom" class="form-control" value="<?= htmlspecialchars($detail['nom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prenom</label>
                                <input type="text" name="conv_prenom" class="form-control" value="<?= htmlspecialchars($detail['prenom'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="conv_email" class="form-control" value="<?= htmlspecialchars($detail['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telephone</label>
                                <input type="text" name="conv_telephone" class="form-control" value="<?= htmlspecialchars($detail['telephone'] ?? '') ?>">
                            </div>
                        </div>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Societe</label>
                                <input type="text" name="conv_societe" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SIRET</label>
                                <input type="text" name="conv_siret" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">IBAN</label>
                                <input type="text" name="conv_iban" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Commission %</label>
                                <input type="number" name="conv_commission" class="form-control" value="20" min="0" max="100" step="0.5">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="convert_lead" class="btn btn-success"><i class="fas fa-check"></i> Convertir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($view === 'kanban'): ?>
    <!-- ========== KANBAN VIEW ========== -->
    <div class="kanban-board">
        <?php
        $kanban_statuts = ['nouveau', 'contacte', 'rdv_planifie', 'rdv_fait', 'proposition', 'negocie', 'converti', 'perdu'];
        foreach ($kanban_statuts as $col_statut):
            $col_leads = array_filter($all_leads, fn($l) => $l['statut'] === $col_statut);
        ?>
        <div class="kanban-col">
            <div class="kanban-col-header" style="background:<?= $statut_colors[$col_statut] ?>;">
                <?= $statut_labels[$col_statut][0] ?> (<?= count($col_leads) ?>)
            </div>
            <?php foreach ($col_leads as $l):
                $scoreBadge = getScoreBadge((int)$l['score']);
                $srcIcon = $source_icons[$l['source'] ?? 'autre'] ?? 'fa-question-circle';
            ?>
            <a href="?id=<?= $l['id'] ?>" class="lead-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="card-name"><?= htmlspecialchars($l['nom'] ?? 'Sans nom') ?></div>
                    <span class="badge <?= $scoreBadge['class'] ?> score-badge"><?= (int)$l['score'] ?></span>
                </div>
                <div class="card-meta">
                    <i class="fas <?= $srcIcon ?>"></i> <?= ucfirst(str_replace('_', ' ', $l['source'] ?? 'autre')) ?>
                    <?php if (!empty($l['ville'])): ?>
                        &middot; <?= htmlspecialchars(mb_substr($l['ville'], 0, 18)) ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($l['telephone']) || !empty($l['email'])): ?>
                <div class="card-meta">
                    <?php if (!empty($l['telephone'])): ?><i class="fas fa-phone"></i> <?= htmlspecialchars($l['telephone']) ?><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($l['prochaine_action'])): ?>
                <div class="card-meta mt-1" style="<?= (!empty($l['date_prochaine_action']) && $l['date_prochaine_action'] <= date('Y-m-d')) ? 'color:#dc3545;font-weight:600;' : '' ?>">
                    <i class="fas fa-clock"></i> <?= htmlspecialchars(mb_substr($l['prochaine_action'], 0, 28)) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($l['date_rdv']) && in_array($l['statut'], ['rdv_planifie'])): ?>
                <div class="card-meta mt-1 text-primary">
                    <i class="fas fa-calendar"></i> <?= date('d/m H:i', strtotime($l['date_rdv'])) ?>
                </div>
                <?php endif; ?>
                <div class="mt-1">
                    <span class="badge <?= $scoreBadge['class'] ?> score-badge"><?= $scoreBadge['label'] ?></span>
                    <?php if (!empty($l['nb_interactions']) && $l['nb_interactions'] > 0): ?>
                    <span class="badge bg-light text-dark score-badge"><i class="fas fa-comments"></i> <?= $l['nb_interactions'] ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ($view === 'liste'): ?>
    <!-- ========== LIST VIEW ========== -->
    <div class="table-responsive">
        <table class="table table-sm table-hover table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= sortLink('nom', 'Nom') ?></th>
                    <th><?= sortLink('score', 'Score') ?></th>
                    <th><?= sortLink('source', 'Source') ?></th>
                    <th><?= sortLink('ville', 'Ville') ?></th>
                    <th><?= sortLink('statut', 'Statut') ?></th>
                    <th>Contact</th>
                    <th><?= sortLink('updated_at', 'Maj') ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list_leads)): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">Aucun lead trouve</td></tr>
                <?php else: ?>
                <?php foreach ($list_leads as $l):
                    $scoreBadge = getScoreBadge((int)$l['score']);
                    $srcIcon = $source_icons[$l['source'] ?? 'autre'] ?? 'fa-question-circle';
                ?>
                <tr>
                    <td>
                        <a href="?id=<?= $l['id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($l['nom'] ?? 'Sans nom') ?></a>
                        <?php if (!empty($l['prenom'])): ?>
                        <span class="text-muted small"><?= htmlspecialchars($l['prenom']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $scoreBadge['class'] ?>"><?= (int)$l['score'] ?> - <?= $scoreBadge['label'] ?></span></td>
                    <td><i class="fas <?= $srcIcon ?> text-muted"></i> <?= ucfirst(str_replace('_', ' ', $l['source'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($l['ville'] ?? '-') ?></td>
                    <td>
                        <form method="POST" class="d-inline status-form">
                            <?php echoCsrfField(); ?>
                            <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                            <select name="statut" class="form-select form-select-sm d-inline-block" style="width:auto;font-size:0.78em;padding:2px 6px;" onchange="this.form.submit()">
                                <?php foreach ($statuts as $s): ?>
                                <option value="<?= $s ?>" <?= $l['statut'] === $s ? 'selected' : '' ?>><?= $statut_labels[$s][0] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_statut" value="1">
                        </form>
                    </td>
                    <td class="small">
                        <?php if (!empty($l['telephone'])): ?><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($l['telephone']) ?><br><?php endif; ?>
                        <?php if (!empty($l['email'])): ?><i class="fas fa-envelope text-muted"></i> <?= htmlspecialchars(mb_substr($l['email'], 0, 22)) ?><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= date('d/m/y', strtotime($l['updated_at'])) ?></td>
                    <td>
                        <a href="?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= buildFilterQs(['view' => 'liste']) ?>&page=<?= $current_page - 1 ?>">&laquo;</a>
            </li>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= $p === $current_page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= buildFilterQs(['view' => 'liste']) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= buildFilterQs(['view' => 'liste']) ?>&page=<?= $current_page + 1 ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php elseif ($view === 'entonnoir'): ?>
    <!-- ========== FUNNEL VIEW ========== -->
    <div class="card">
        <div class="card-body">
            <?php
            $funnel_statuts = ['nouveau', 'contacte', 'rdv_planifie', 'rdv_fait', 'proposition', 'negocie', 'converti'];
            $max_count = max(1, max(array_map(fn($s) => $stats[$s], $funnel_statuts)));
            $total_non_perdu = array_sum(array_map(fn($s) => $stats[$s], $funnel_statuts));
            foreach ($funnel_statuts as $idx => $s):
                $count = $stats[$s];
                $width = max(25, ($count / $max_count) * 100);
                $pct = $total_non_perdu > 0 ? round(($count / $total_non_perdu) * 100, 1) : 0;
            ?>
            <div class="funnel-stage" style="width:<?= $width ?>%;background:<?= $statut_colors[$s] ?>;">
                <span class="funnel-label"><?= $statut_labels[$s][0] ?></span>
                <span class="funnel-count"><?= $count ?></span>
                <span class="funnel-pct">(<?= $pct ?>%)</span>
            </div>
            <?php endforeach; ?>

            <hr>
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="h5 text-muted"><?= $stats['perdu'] ?></div>
                    <small class="text-muted">Perdus</small>
                </div>
                <div class="col-md-3">
                    <div class="h5"><?= $taux_conversion ?>%</div>
                    <small class="text-muted">Taux de conversion global</small>
                </div>
                <div class="col-md-3">
                    <?php
                    $rdv_to_conv = ($stats['rdv_planifie'] + $stats['rdv_fait']) > 0
                        ? round(($stats['converti'] / ($stats['rdv_planifie'] + $stats['rdv_fait'] + $stats['proposition'] + $stats['negocie'] + $stats['converti'])) * 100, 1)
                        : 0;
                    ?>
                    <div class="h5"><?= $rdv_to_conv ?>%</div>
                    <small class="text-muted">RDV vers conversion</small>
                </div>
                <div class="col-md-3">
                    <?php
                    $avg_score = $total_leads > 0 ? round(array_sum(array_column($all_leads, 'score')) / $total_leads) : 0;
                    ?>
                    <div class="h5"><?= $avg_score ?></div>
                    <small class="text-muted">Score moyen</small>
                </div>
            </div>

            <hr>
            <h6 class="text-muted">Repartition par source</h6>
            <div class="row g-2">
                <?php
                $by_source = [];
                foreach ($all_leads as $l) {
                    $src = $l['source'] ?? 'autre';
                    $by_source[$src] = ($by_source[$src] ?? 0) + 1;
                }
                arsort($by_source);
                $source_colors = [
                    'simulateur' => '#0d6efd', 'formulaire_contact' => '#0dcaf0', 'landing_page' => '#198754',
                    'concurrence' => '#ffc107', 'rdv_site' => '#6610f2', 'recommandation' => '#fd7e14',
                    'demarchage' => '#dc3545', 'autre' => '#6c757d',
                ];
                foreach ($by_source as $src => $cnt):
                    $srcIcon = $source_icons[$src] ?? 'fa-question-circle';
                    $srcColor = $source_colors[$src] ?? '#6c757d';
                    $srcPct = $total_leads > 0 ? round(($cnt / $total_leads) * 100, 1) : 0;
                ?>
                <div class="col-md-3">
                    <div class="d-flex align-items-center gap-2 p-2 border rounded">
                        <i class="fas <?= $srcIcon ?>" style="color:<?= $srcColor ?>;font-size:1.2em;"></i>
                        <div class="flex-grow-1">
                            <div class="small fw-bold"><?= ucfirst(str_replace('_', ' ', $src)) ?></div>
                            <div class="progress" style="height:5px;">
                                <div class="progress-bar" style="width:<?= $srcPct ?>%;background:<?= $srcColor ?>;"></div>
                            </div>
                        </div>
                        <span class="fw-bold"><?= $cnt ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Create Lead -->
<div class="modal fade" id="modalCreateLead" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?php echoCsrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Nouveau lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Prenom</label>
                            <input type="text" name="prenom" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Telephone</label>
                            <input type="text" name="telephone" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-select">
                                <?php foreach ($sources as $src): ?>
                                <option value="<?= $src ?>"><?= ucfirst(str_replace('_', ' ', $src)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_lead" class="btn btn-primary"><i class="fas fa-check"></i> Creer</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
