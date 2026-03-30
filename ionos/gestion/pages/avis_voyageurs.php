<?php
/**
 * Avis voyageurs — Import et consultation des avis Booking.com
 * Coller le texte brut copié depuis Booking → parsing automatique → stockage avec détection doublons
 */
include '../config.php';
include '../pages/menu.php';
include '../includes/template_helper.php';

// --- Templates SMS pour suivi avis ---
$sms_avis_templates = [
    'avis_remerciement' => [
        'label' => 'Remerciement',
        'icon' => 'fa-heart',
        'color' => 'success',
        'default' => "Bonjour {prenom}, merci d'avoir séjourné chez nous du {date_arrivee} au {date_depart} à {logement}. Nous espérons que tout s'est bien passé ! L'équipe Frenchy Conciergerie"
    ],
    'avis_relance' => [
        'label' => 'Demande d\'avis',
        'icon' => 'fa-star',
        'color' => 'warning',
        'default' => "Bonjour {prenom}, nous espérons que votre séjour à {logement} vous a plu ! Votre avis sur Booking compte beaucoup pour nous. Pourriez-vous prendre 2 min pour laisser un commentaire ? Merci ! Frenchy Conciergerie"
    ],
    'avis_amelioration' => [
        'label' => 'Amélioration',
        'icon' => 'fa-comments',
        'color' => 'info',
        'default' => "Bonjour {prenom}, suite à votre séjour à {logement}, nous aimerions avoir votre retour. Y a-t-il quelque chose que nous pourrions améliorer ? Votre avis nous aide à progresser. Merci ! Frenchy Conciergerie"
    ],
    'avis_commercial' => [
        'label' => 'Offre fidélité',
        'icon' => 'fa-gift',
        'color' => 'primary',
        'default' => "Bonjour {prenom}, merci pour votre séjour à {logement} ! Pour votre prochaine visite, bénéficiez de -10% en réservant directement chez nous. Contactez-nous pour en profiter ! Frenchy Conciergerie"
    ],
];

// Charger les templates personnalisés depuis sms_templates si existants
foreach ($sms_avis_templates as $key => &$tpl) {
    $custom = get_sms_template($pdo, $key);
    if ($custom) $tpl['default'] = $custom;
}
unset($tpl);

// --- Traitement POST ---
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Suppression d'un avis
    if ($action === 'delete_avis') {
        $avis_id = (int)($_POST['avis_id'] ?? 0);
        if ($avis_id) {
            try {
                $pdo->prepare("DELETE FROM avis_voyageurs WHERE id = ?")->execute([$avis_id]);
                $flash = ['type' => 'success', 'msg' => 'Avis supprimé.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'msg' => 'Erreur suppression : ' . $e->getMessage()];
            }
        }
    }

    // Attribution manuelle d'une réservation
    if ($action === 'link_reservation') {
        $avis_id = (int)($_POST['avis_id'] ?? 0);
        $resa_id = (int)($_POST['reservation_id'] ?? 0);
        if ($avis_id && $resa_id) {
            try {
                // Récupérer le logement_id de la réservation
                $stmt = $pdo->prepare("SELECT logement_id FROM reservation WHERE id = ?");
                $stmt->execute([$resa_id]);
                $logId = $stmt->fetchColumn();
                $pdo->prepare("UPDATE avis_voyageurs SET reservation_id = ?, logement_id = ? WHERE id = ?")->execute([$resa_id, $logId ?: null, $avis_id]);
                $flash = ['type' => 'success', 'msg' => 'Réservation associée.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'msg' => 'Erreur : ' . $e->getMessage()];
            }
        }
    }

    // Créer une fiche client depuis un avis
    if ($action === 'create_client') {
        $nom = trim($_POST['client_nom'] ?? '');
        $prenom = trim($_POST['client_prenom'] ?? '');
        $telephone = trim($_POST['client_telephone'] ?? '');
        $email = trim($_POST['client_email'] ?? '');
        $avis_id = (int)($_POST['avis_id'] ?? 0);
        if ($prenom && $telephone) {
            try {
                $stmt = $pdo->prepare("INSERT INTO client (prenom, nom, telephone, email) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nom = VALUES(nom), email = VALUES(email)");
                $stmt->execute([$prenom, $nom, $telephone, $email]);
                $flash = ['type' => 'success', 'msg' => "Fiche client créée pour $prenom $nom."];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'msg' => 'Erreur : ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'warning', 'msg' => 'Prénom et téléphone obligatoires.'];
        }
    }

    // Envoi SMS suivi avis
    if ($action === 'send_sms_avis') {
    $resa_id = (int)($_POST['reservation_id'] ?? 0);
    $sms_message = trim($_POST['sms_message'] ?? '');
    $sms_receiver = trim($_POST['sms_receiver'] ?? '');

    if ($resa_id && $sms_message && $sms_receiver) {
        $cleanReceiver = preg_replace('/\s/', '', $sms_receiver);
        try {
            $stmt = $pdo->prepare("INSERT INTO sms_outbox (receiver, message, modem, status, reservation_id, created_at) VALUES (?, ?, 'modem1', 'pending', ?, NOW())");
            $stmt->execute([$cleanReceiver, $sms_message, $resa_id]);
            $flash = ['type' => 'success', 'msg' => 'SMS envoyé à ' . ($_POST['sms_prenom'] ?? '') . '.'];
        } catch (PDOException $e) {
            $flash = ['type' => 'danger', 'msg' => 'Erreur SMS : ' . $e->getMessage()];
        }
    }
    }

    // Envoi SMS groupé (relance tous)
    if ($action === 'send_bulk_sms') {
        $template_key = $_POST['bulk_template'] ?? 'avis_relance';
        $resa_ids = $_POST['bulk_resa_ids'] ?? [];
        if (!is_array($resa_ids)) $resa_ids = [];
        $sent = 0;
        $errors = 0;
        foreach ($resa_ids as $rid) {
            $rid = (int)$rid;
            if (!$rid) continue;
            try {
                $stmt = $pdo->prepare("SELECT r.id, r.prenom, r.nom, r.telephone, r.date_arrivee, r.date_depart, l.nom_du_logement
                    FROM reservation r LEFT JOIN liste_logements l ON r.logement_id = l.id WHERE r.id = ?");
                $stmt->execute([$rid]);
                $resa = $stmt->fetch();
                if (!$resa || empty($resa['telephone'])) { $errors++; continue; }

                $tplText = $sms_avis_templates[$template_key]['default'] ?? $sms_avis_templates['avis_relance']['default'];
                $msg = str_replace(
                    ['{prenom}', '{nom}', '{logement}', '{date_arrivee}', '{date_depart}'],
                    [
                        $resa['prenom'] ?? '',
                        $resa['nom'] ?? '',
                        $resa['nom_du_logement'] ?? '',
                        $resa['date_arrivee'] ? date('d/m/Y', strtotime($resa['date_arrivee'])) : '',
                        $resa['date_depart'] ? date('d/m/Y', strtotime($resa['date_depart'])) : '',
                    ],
                    $tplText
                );
                $phone = preg_replace('/\s/', '', $resa['telephone']);
                $pdo->prepare("INSERT INTO sms_outbox (receiver, message, modem, status, reservation_id, created_at) VALUES (?, ?, 'modem1', 'pending', ?, NOW())")
                    ->execute([$phone, $msg, $rid]);
                $sent++;
            } catch (PDOException $e) { $errors++; }
        }
        if ($sent > 0) {
            $flash = ['type' => 'success', 'msg' => "$sent SMS de relance envoyé(s) en file d'attente." . ($errors ? " $errors erreur(s)." : '')];
        } else {
            $flash = ['type' => 'warning', 'msg' => 'Aucun SMS envoyé.' . ($errors ? " $errors erreur(s)." : ' Aucune réservation sélectionnée.')];
        }
    }
}

// --- Seed : créer les templates avis dans sms_templates s'ils n'existent pas ---
try {
    // S'assurer que la colonne description existe
    try {
        $pdo->exec("ALTER TABLE sms_templates ADD COLUMN `description` VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) { /* colonne existe déjà */ }

    foreach ($sms_avis_templates as $key => $tpl) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_templates WHERE name = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO sms_templates (campaign, name, template, description) VALUES ('avis', ?, ?, ?)");
            $stmt->execute([$key, $tpl['default'], $tpl['label'] . ' — Suivi avis voyageurs']);
        }
    }

    // Dédoublonnage : garder uniquement le plus récent par name
    $pdo->exec("DELETE t1 FROM sms_templates t1
                 INNER JOIN sms_templates t2
                 ON t1.name = t2.name AND t1.id < t2.id");
} catch (PDOException $e) { error_log("Seed templates avis: " . $e->getMessage()); }

// Créer la table si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `avis_voyageurs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `numero_reservation` VARCHAR(50) NOT NULL,
        `reservation_id` INT(11) DEFAULT NULL,
        `logement_id` INT(11) DEFAULT NULL,
        `nom_voyageur` VARCHAR(100) DEFAULT NULL,
        `pays_voyageur` VARCHAR(10) DEFAULT NULL,
        `date_avis` DATE DEFAULT NULL,
        `note_globale` DECIMAL(3,1) DEFAULT NULL,
        `note_personnel` DECIMAL(3,1) DEFAULT NULL,
        `note_proprete` DECIMAL(3,1) DEFAULT NULL,
        `note_situation` DECIMAL(3,1) DEFAULT NULL,
        `note_equipements` DECIMAL(3,1) DEFAULT NULL,
        `note_confort` DECIMAL(3,1) DEFAULT NULL,
        `note_rapport_qualite_prix` DECIMAL(3,1) DEFAULT NULL,
        `note_lit` DECIMAL(3,1) DEFAULT NULL,
        `commentaire_positif` TEXT DEFAULT NULL,
        `commentaire_negatif` TEXT DEFAULT NULL,
        `commentaire_general` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_numero_reservation` (`numero_reservation`),
        KEY `idx_reservation_id` (`reservation_id`),
        KEY `idx_logement_id` (`logement_id`),
        KEY `idx_date_avis` (`date_avis`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) { /* table existe déjà */ }

// --- Traitement POST : Re-matcher les avis non liés ---
$rematch_results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rematch') {
    $rematch_results = ['matched' => 0, 'already' => 0, 'unmatched' => 0, 'details' => []];
    $unmatched = $pdo->query("SELECT id, numero_reservation, nom_voyageur, date_avis FROM avis_voyageurs WHERE reservation_id IS NULL");
    foreach ($unmatched as $avis) {
        $num = trim($avis['numero_reservation']);
        $nom = trim($avis['nom_voyageur'] ?? '');
        $resa = null;
        $match_method = '';

        // 1. Match exact sur reservation.reference
        $stmt = $pdo->prepare("SELECT id, logement_id FROM reservation WHERE reference = ? LIMIT 1");
        $stmt->execute([$num]);
        $resa = $stmt->fetch();
        if ($resa) $match_method = 'reference';

        // 2. Match via ical_reservations.platform_reservation_id → guest_name → reservation
        if (!$resa) {
            try {
                $stmt = $pdo->prepare("SELECT ir.guest_name, ir.start_date
                    FROM ical_reservations ir
                    WHERE ir.platform_reservation_id = ?
                    LIMIT 1");
                $stmt->execute([$num]);
                $ical = $stmt->fetch();
                if ($ical && $ical['guest_name']) {
                    // Extraire le prénom du guest_name ical
                    $ical_prenom = explode(' ', trim($ical['guest_name']))[0];
                    $stmt2 = $pdo->prepare("SELECT id, logement_id FROM reservation
                        WHERE LOWER(prenom) = LOWER(?) AND date_arrivee = ?
                        LIMIT 1");
                    $stmt2->execute([$ical_prenom, $ical['start_date']]);
                    $resa = $stmt2->fetch();
                    if ($resa) $match_method = 'ical';
                }
            } catch (PDOException $e) { /* table ical_reservations peut ne pas exister */ }
        }

        // 3. Match par prénom du voyageur + date proche de l'avis
        if (!$resa && $nom !== '' && $avis['date_avis']) {
            $stmt = $pdo->prepare("SELECT id, logement_id FROM reservation
                WHERE LOWER(prenom) = LOWER(?)
                AND date_depart <= ? AND date_depart >= DATE_SUB(?, INTERVAL 30 DAY)
                ORDER BY date_depart DESC LIMIT 1");
            $stmt->execute([$nom, $avis['date_avis'], $avis['date_avis']]);
            $resa = $stmt->fetch();
            if ($resa) $match_method = 'nom+date';
        }

        if ($resa) {
            $upd = $pdo->prepare("UPDATE avis_voyageurs SET reservation_id = ?, logement_id = ? WHERE id = ?");
            $upd->execute([$resa['id'], $resa['logement_id'], $avis['id']]);
            $rematch_results['matched']++;
            $rematch_results['details'][] = ['nom' => $nom, 'num' => $num, 'status' => 'matched', 'method' => $match_method];
        } else {
            $rematch_results['unmatched']++;
            $rematch_results['details'][] = ['nom' => $nom, 'num' => $num, 'status' => 'unmatched'];
        }
    }
}

// --- Traitement POST : sauvegarde des avis parsés ---
$resultats = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avis_json'])) {
    $avis_list = json_decode($_POST['avis_json'], true);
    if (is_array($avis_list)) {
        foreach ($avis_list as $avis) {
            $num = trim($avis['numero_reservation'] ?? '');
            if (empty($num)) continue;

            // Vérifier doublon
            $exists = $pdo->prepare("SELECT id FROM avis_voyageurs WHERE numero_reservation = ?");
            $exists->execute([$num]);
            if ($exists->fetch()) {
                $resultats[] = ['num' => $num, 'nom' => $avis['nom_voyageur'] ?? '', 'status' => 'doublon'];
                continue;
            }

            // Chercher la réservation correspondante
            $res_id = null;
            $log_id = null;
            // 1. Match exact sur reference
            $stmt = $pdo->prepare("SELECT id, logement_id FROM reservation WHERE reference = ? LIMIT 1");
            $stmt->execute([$num]);
            $resa = $stmt->fetch();
            // 2. Match via ical_reservations
            if (!$resa) {
                try {
                    $stmt = $pdo->prepare("SELECT guest_name, start_date FROM ical_reservations WHERE platform_reservation_id = ? LIMIT 1");
                    $stmt->execute([$num]);
                    $ical = $stmt->fetch();
                    if ($ical && $ical['guest_name']) {
                        $ical_prenom = explode(' ', trim($ical['guest_name']))[0];
                        $stmt2 = $pdo->prepare("SELECT id, logement_id FROM reservation WHERE LOWER(prenom) = LOWER(?) AND date_arrivee = ? LIMIT 1");
                        $stmt2->execute([$ical_prenom, $ical['start_date']]);
                        $resa = $stmt2->fetch();
                    }
                } catch (PDOException $e) { /* table peut ne pas exister */ }
            }
            if ($resa) {
                $res_id = $resa['id'];
                $log_id = $resa['logement_id'];
            }

            // Parser la date
            $date_avis = null;
            if (!empty($avis['date_avis'])) {
                $date_avis = parseDateFr($avis['date_avis']);
            }

            // Insérer
            try {
                $ins = $pdo->prepare("INSERT INTO avis_voyageurs
                    (numero_reservation, reservation_id, logement_id, nom_voyageur, pays_voyageur,
                     date_avis, note_globale, note_personnel, note_proprete, note_situation,
                     note_equipements, note_confort, note_rapport_qualite_prix, note_lit,
                     commentaire_positif, commentaire_negatif, commentaire_general)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $num, $res_id, $log_id,
                    $avis['nom_voyageur'] ?? null,
                    $avis['pays_voyageur'] ?? null,
                    $date_avis,
                    parseNote($avis['note_globale'] ?? null),
                    parseNote($avis['note_personnel'] ?? null),
                    parseNote($avis['note_proprete'] ?? null),
                    parseNote($avis['note_situation'] ?? null),
                    parseNote($avis['note_equipements'] ?? null),
                    parseNote($avis['note_confort'] ?? null),
                    parseNote($avis['note_rapport_qualite_prix'] ?? null),
                    parseNote($avis['note_lit'] ?? null),
                    $avis['commentaire_positif'] ?? null,
                    $avis['commentaire_negatif'] ?? null,
                    $avis['commentaire_general'] ?? null,
                ]);
                $resultats[] = ['num' => $num, 'nom' => $avis['nom_voyageur'] ?? '', 'status' => 'ok', 'matched' => $res_id !== null];
            } catch (PDOException $e) {
                $resultats[] = ['num' => $num, 'nom' => $avis['nom_voyageur'] ?? '', 'status' => 'erreur', 'msg' => $e->getMessage()];
            }
        }
    }
}

function parseNote($val) {
    if ($val === null || $val === '') return null;
    $val = str_replace(',', '.', trim($val));
    return is_numeric($val) ? (float)$val : null;
}

function parseDateFr($str) {
    $str = trim($str);
    // Retirer "Nouveau !" et autres suffixes
    $str = preg_replace('/Nouveau\s*!?\s*$/i', '', $str);
    $str = trim($str);

    $mois_fr = [
        'janv.' => '01', 'janvier' => '01',
        'févr.' => '02', 'février' => '02', 'fevr.' => '02',
        'mars' => '03',
        'avr.' => '04', 'avril' => '04',
        'mai' => '05',
        'juin' => '06',
        'juil.' => '07', 'juillet' => '07',
        'août' => '08', 'aout' => '08',
        'sept.' => '09', 'septembre' => '09',
        'oct.' => '10', 'octobre' => '10',
        'nov.' => '11', 'novembre' => '11',
        'déc.' => '12', 'décembre' => '12', 'dec.' => '12',
    ];

    foreach ($mois_fr as $fr => $num) {
        if (stripos($str, $fr) !== false) {
            if (preg_match('/(\d{1,2})\s+' . preg_quote($fr, '/') . '\s+(\d{4})/i', $str, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[2], (int)$num, (int)$m[1]);
            }
        }
    }
    return null;
}

// --- Charger les avis existants ---
$filtre_logement = $_GET['logement'] ?? '';
$avis_existants = [];
try {
    $sql = "SELECT a.*, r.prenom AS resa_prenom, r.nom AS resa_nom, r.telephone AS resa_telephone,
                   r.date_arrivee, r.date_depart, r.plateforme,
                   l.nom_du_logement
            FROM avis_voyageurs a
            LEFT JOIN reservation r ON a.reservation_id = r.id
            LEFT JOIN liste_logements l ON a.logement_id = l.id";
    $params = [];
    if ($filtre_logement) {
        $sql .= " WHERE a.logement_id = ?";
        $params[] = (int)$filtre_logement;
    }
    $sql .= " ORDER BY a.date_avis DESC, a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avis_existants = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Stats
$stats = ['total' => 0, 'moyenne' => 0, 'matched' => 0];
if ($avis_existants) {
    $stats['total'] = count($avis_existants);
    $notes = array_filter(array_column($avis_existants, 'note_globale'));
    $stats['moyenne'] = $notes ? round(array_sum($notes) / count($notes), 1) : 0;
    $stats['matched'] = count(array_filter(array_column($avis_existants, 'reservation_id')));
}

// Logements pour filtre
$logements = $pdo->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll();

// Réservations récentes pour attribution manuelle
$reservations_recentes = [];
try {
    $reservations_recentes = $pdo->query("SELECT id, reference, prenom, nom, date_arrivee, date_depart, logement_id FROM reservation ORDER BY date_depart DESC LIMIT 200")->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// --- Réservations Booking sans avis (déjà parties, avec téléphone) ---
$resas_sans_avis = [];
try {
    $sql_sans_avis = "SELECT r.id, r.reference, r.prenom, r.nom, r.telephone, r.email,
                             r.date_arrivee, r.date_depart, r.logement_id, r.plateforme,
                             l.nom_du_logement,
                             DATEDIFF(CURDATE(), r.date_depart) AS jours_depuis_depart
                      FROM reservation r
                      LEFT JOIN liste_logements l ON r.logement_id = l.id
                      LEFT JOIN avis_voyageurs av ON av.reservation_id = r.id
                      WHERE r.date_depart < CURDATE()
                        AND r.date_depart >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        AND r.statut = 'confirmée'
                        AND r.telephone IS NOT NULL AND r.telephone != ''
                        AND av.id IS NULL";
    $params_sans_avis = [];
    if ($filtre_logement) {
        $sql_sans_avis .= " AND r.logement_id = ?";
        $params_sans_avis[] = (int)$filtre_logement;
    }
    $sql_sans_avis .= " ORDER BY r.date_depart DESC";
    $stmt = $pdo->prepare($sql_sans_avis);
    $stmt->execute($params_sans_avis);
    $resas_sans_avis = $stmt->fetchAll();
} catch (PDOException $e) { /* ignore */ }
?>

<div class="container-fluid py-4">
    <h2><i class="fas fa-star text-warning"></i> Avis voyageurs</h2>

    <!-- Flash message -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <?= htmlspecialchars($flash['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <?php if ($stats['total'] > 0): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                    <small class="text-muted">Avis importés</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['moyenne'] ?>/10</h3>
                    <small class="text-muted">Note moyenne</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['matched'] ?></h3>
                    <small class="text-muted">Liés à une réservation</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="mb-0"><?= $stats['total'] - $stats['matched'] ?></h3>
                    <small class="text-muted">Non matchés</small>
                    <?php if ($stats['total'] - $stats['matched'] > 0): ?>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="action" value="rematch">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-sync"></i> Re-matcher
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Résultats re-match -->
    <?php if ($rematch_results !== null): ?>
    <div class="alert <?= $rematch_results['matched'] > 0 ? 'alert-success' : 'alert-warning' ?> alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <strong>Re-matching terminé :</strong>
        <?= $rematch_results['matched'] ?> avis associé(s) à une réservation,
        <?= $rematch_results['unmatched'] ?> toujours non matché(s).
        <?php if ($rematch_results['details']): ?>
        <ul class="mb-0 mt-2">
            <?php foreach ($rematch_results['details'] as $d): ?>
                <li>
                    <?php if ($d['status'] === 'matched'): ?>
                        <i class="fas fa-check text-success"></i>
                    <?php else: ?>
                        <i class="fas fa-times text-danger"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($d['nom'] ?? 'Anonyme') ?> — Résa #<?= htmlspecialchars($d['num']) ?>
                    <?php if (!empty($d['method'])): ?>
                        <span class="badge bg-info"><?= htmlspecialchars($d['method']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Résultats import -->
    <?php if ($resultats): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <strong>Résultat de l'import :</strong>
        <ul class="mb-0 mt-2">
        <?php foreach ($resultats as $r): ?>
            <li>
                <?php if ($r['status'] === 'ok'): ?>
                    <i class="fas fa-check text-success"></i> <?= htmlspecialchars($r['nom']) ?> (<?= htmlspecialchars($r['num']) ?>)
                    <?= $r['matched'] ? '<span class="badge bg-success">Réservation trouvée</span>' : '<span class="badge bg-warning">Réservation non trouvée</span>' ?>
                <?php elseif ($r['status'] === 'doublon'): ?>
                    <i class="fas fa-copy text-warning"></i> <?= htmlspecialchars($r['nom']) ?> (<?= htmlspecialchars($r['num']) ?>) — <strong>Doublon ignoré</strong>
                <?php else: ?>
                    <i class="fas fa-times text-danger"></i> <?= htmlspecialchars($r['nom']) ?> — Erreur
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Zone de collage -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-paste"></i> Importer des avis Booking.com</span>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#importZone">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="collapse <?= empty($avis_existants) ? 'show' : '' ?>" id="importZone">
            <div class="card-body">
                <p class="text-muted">Copiez-collez le texte brut des avis depuis la page Booking.com. Le système détecte automatiquement chaque avis et ignore les doublons.</p>
                <textarea id="texteAvis" class="form-control mb-3" rows="8" placeholder="Collez ici le texte copié depuis Booking.com..."></textarea>
                <button type="button" class="btn btn-primary" id="btnParser" disabled>
                    <i class="fas fa-magic"></i> Analyser le texte
                </button>

                <!-- Preview des avis parsés -->
                <div id="previewAvis" class="mt-3" style="display:none;">
                    <h5>Avis détectés : <span id="nbAvis" class="badge bg-primary">0</span></h5>
                    <div id="listePreview"></div>
                    <form method="post" id="formImport">
                        <input type="hidden" name="avis_json" id="avisJson">
                        <button type="submit" class="btn btn-success mt-3">
                            <i class="fas fa-save"></i> Enregistrer les avis
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre logement -->
    <div class="row mb-3">
        <div class="col-md-4">
            <select class="form-select" onchange="window.location='?logement='+this.value">
                <option value="">Tous les logements</option>
                <?php foreach ($logements as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filtre_logement == $l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['nom_du_logement']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Liste des avis existants -->
    <?php if ($avis_existants): ?>
    <div class="row">
    <?php foreach ($avis_existants as $a): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($a['nom_voyageur'] ?? 'Anonyme') ?></strong>
                        <?php if ($a['pays_voyageur']): ?>
                            <small class="text-muted">(<?= htmlspecialchars($a['pays_voyageur']) ?>)</small>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= $a['note_globale'] >= 8 ? 'bg-success' : ($a['note_globale'] >= 6 ? 'bg-warning' : 'bg-danger') ?> fs-6">
                        <?= number_format($a['note_globale'], 1, ',', '') ?>/10
                    </span>
                </div>
                <div class="card-body">
                    <!-- Logement + réservation -->
                    <?php if ($a['nom_du_logement']): ?>
                        <p class="mb-1"><i class="fas fa-home text-muted"></i> <?= htmlspecialchars($a['nom_du_logement']) ?></p>
                    <?php endif; ?>
                    <?php if ($a['reservation_id']): ?>
                        <p class="mb-1">
                            <i class="fas fa-link text-success"></i>
                            <a href="reservation_details.php?id=<?= $a['reservation_id'] ?>">
                                Résa #<?= htmlspecialchars($a['numero_reservation']) ?>
                            </a>
                            <?php if ($a['date_arrivee']): ?>
                                <small class="text-muted">(<?= date('d/m/Y', strtotime($a['date_arrivee'])) ?> → <?= date('d/m/Y', strtotime($a['date_depart'])) ?>)</small>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="mb-1"><i class="fas fa-unlink text-warning"></i> Résa #<?= htmlspecialchars($a['numero_reservation']) ?> <small class="text-muted">(non matchée)</small></p>
                    <?php endif; ?>

                    <!-- Notes détaillées -->
                    <div class="row mt-2 small">
                        <?php
                        $categories = [
                            'Personnel' => $a['note_personnel'],
                            'Propreté' => $a['note_proprete'],
                            'Situation' => $a['note_situation'],
                            'Équipements' => $a['note_equipements'],
                            'Confort' => $a['note_confort'],
                            'Qualité/Prix' => $a['note_rapport_qualite_prix'],
                        ];
                        if ($a['note_lit']) $categories['Lit'] = $a['note_lit'];
                        foreach ($categories as $cat => $note):
                            if ($note === null) continue;
                        ?>
                            <div class="col-6 mb-1">
                                <span class="text-muted"><?= $cat ?></span>
                                <span class="float-end fw-bold <?= $note >= 8 ? 'text-success' : ($note >= 6 ? 'text-warning' : 'text-danger') ?>">
                                    <?= number_format($note, 1, ',', '') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Commentaires -->
                    <?php if ($a['commentaire_general']): ?>
                        <p class="mt-2 mb-1"><?= nl2br(htmlspecialchars($a['commentaire_general'])) ?></p>
                    <?php endif; ?>
                    <?php if ($a['commentaire_positif']): ?>
                        <p class="mt-2 mb-1 text-success"><i class="fas fa-thumbs-up"></i> <?= nl2br(htmlspecialchars($a['commentaire_positif'])) ?></p>
                    <?php endif; ?>
                    <?php if ($a['commentaire_negatif']): ?>
                        <p class="mb-1 text-danger"><i class="fas fa-thumbs-down"></i> <?= nl2br(htmlspecialchars($a['commentaire_negatif'])) ?></p>
                    <?php endif; ?>
                </div>
                <div class="card-footer small">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted">
                            <?php if ($a['date_avis']): ?>
                                <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($a['date_avis'])) ?>
                            <?php endif; ?>
                        </span>
                        <div class="btn-group btn-group-sm">
                            <?php if (!empty($a['resa_telephone']) && $a['reservation_id']): ?>
                                <?php foreach ($sms_avis_templates as $tpl_key => $tpl): ?>
                                    <button type="button" class="btn btn-outline-<?= $tpl['color'] ?> btn-sm py-0" title="<?= htmlspecialchars($tpl['label']) ?>"
                                        onclick="ouvrirModalSms(<?= htmlspecialchars(json_encode([
                                            'reservation_id' => $a['reservation_id'],
                                            'prenom' => $a['resa_prenom'] ?? $a['nom_voyageur'] ?? '',
                                            'nom' => $a['resa_nom'] ?? '',
                                            'telephone' => $a['resa_telephone'],
                                            'logement' => $a['nom_du_logement'] ?? '',
                                            'date_arrivee' => $a['date_arrivee'] ? date('d/m/Y', strtotime($a['date_arrivee'])) : '',
                                            'date_depart' => $a['date_depart'] ? date('d/m/Y', strtotime($a['date_depart'])) : '',
                                            'template_key' => $tpl_key,
                                        ])) ?>)">
                                        <i class="fas <?= $tpl['icon'] ?>"></i>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <!-- Attribuer réservation -->
                        <?php if (!$a['reservation_id']): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0" title="Attribuer une réservation"
                            onclick="ouvrirModalLinkResa(<?= $a['id'] ?>, <?= htmlspecialchars(json_encode($a['nom_voyageur'] ?? '')) ?>)">
                            <i class="fas fa-link"></i> Résa
                        </button>
                        <?php endif; ?>
                        <!-- Créer fiche client -->
                        <button type="button" class="btn btn-outline-info btn-sm py-0" title="Créer fiche client"
                            onclick="ouvrirModalClient(<?= htmlspecialchars(json_encode([
                                'avis_id' => $a['id'],
                                'prenom' => $a['resa_prenom'] ?? $a['nom_voyageur'] ?? '',
                                'nom' => $a['resa_nom'] ?? '',
                                'telephone' => $a['resa_telephone'] ?? '',
                                'email' => '',
                            ])) ?>)">
                            <i class="fas fa-user-plus"></i>
                        </button>
                        <!-- Supprimer -->
                        <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cet avis ?')">
                            <input type="hidden" name="action" value="delete_avis">
                            <input type="hidden" name="avis_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm py-0" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php elseif (empty($resultats)): ?>
    <div class="text-center text-muted py-5">
        <i class="fas fa-star fa-3x mb-3"></i>
        <p>Aucun avis importé. Collez le texte des avis Booking.com ci-dessus pour commencer.</p>
    </div>
    <?php endif; ?>

    <!-- ============================================== -->
    <!-- RÉSERVATIONS SANS AVIS — RELANCE SMS (30 jours) -->
    <!-- ============================================== -->
    <hr class="my-4">
    <h3><i class="fas fa-sms text-primary"></i> Relance SMS — Sans avis (30 derniers jours)</h3>
    <p class="text-muted">Réservations terminées dans les 30 derniers jours dont les voyageurs n'ont pas laissé d'avis. Sélectionnez et relancez en un clic.</p>

    <?php if ($resas_sans_avis): ?>
    <form method="post" id="formBulkSms">
        <input type="hidden" name="action" value="send_bulk_sms">

        <!-- Barre d'actions groupées -->
        <div class="card mb-3 border-primary">
            <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="bulkSelectAll" onchange="toggleBulkAll(this)">
                    <label class="form-check-label fw-bold" for="bulkSelectAll">Tout sélectionner</label>
                </div>
                <span class="text-muted" id="bulkCount">0 sélectionné(s)</span>
                <select name="bulk_template" class="form-select form-select-sm" style="width:auto">
                    <?php foreach ($sms_avis_templates as $tpl_key => $tpl): ?>
                        <option value="<?= $tpl_key ?>" <?= $tpl_key === 'avis_relance' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tpl['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" id="btnBulkSend" disabled onclick="return confirm('Envoyer un SMS à tous les voyageurs sélectionnés ?')">
                    <i class="fas fa-paper-plane"></i> Relancer la sélection
                </button>
                <span class="text-muted small"><?= count($resas_sans_avis) ?> voyageur(s) à relancer</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px"></th>
                        <th>Voyageur</th>
                        <th>Logement</th>
                        <th>Séjour</th>
                        <th>Depuis</th>
                        <th>Téléphone</th>
                        <th class="text-center">SMS individuel</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resas_sans_avis as $r): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bulk-check" name="bulk_resa_ids[]" value="<?= $r['id'] ?>" onchange="updateBulkCount()">
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['prenom']) ?></strong>
                            <?= htmlspecialchars($r['nom'] ?? '') ?>
                            <?php if ($r['plateforme']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($r['plateforme']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['nom_du_logement'] ?? '—') ?></td>
                        <td>
                            <small>
                                <?= date('d/m', strtotime($r['date_arrivee'])) ?> → <?= date('d/m/Y', strtotime($r['date_depart'])) ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge <?= $r['jours_depuis_depart'] <= 7 ? 'bg-success' : ($r['jours_depuis_depart'] <= 14 ? 'bg-warning' : 'bg-secondary') ?>">
                                <?= $r['jours_depuis_depart'] ?>j
                            </span>
                        </td>
                        <td><small><?= htmlspecialchars($r['telephone']) ?></small></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <?php foreach ($sms_avis_templates as $tpl_key => $tpl): ?>
                                    <button type="button" class="btn btn-outline-<?= $tpl['color'] ?>" title="<?= htmlspecialchars($tpl['label']) ?>"
                                        onclick="ouvrirModalSms(<?= htmlspecialchars(json_encode([
                                            'reservation_id' => $r['id'],
                                            'prenom' => $r['prenom'],
                                            'nom' => $r['nom'] ?? '',
                                            'telephone' => $r['telephone'],
                                            'logement' => $r['nom_du_logement'] ?? '',
                                            'date_arrivee' => $r['date_arrivee'] ? date('d/m/Y', strtotime($r['date_arrivee'])) : '',
                                            'date_depart' => $r['date_depart'] ? date('d/m/Y', strtotime($r['date_depart'])) : '',
                                            'template_key' => $tpl_key,
                                        ])) ?>)">
                                        <i class="fas <?= $tpl['icon'] ?>"></i>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php else: ?>
    <div class="text-center text-muted py-4">
        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
        <p>Aucune réservation sans avis dans les 30 derniers jours.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal attribution réservation -->
<div class="modal fade" id="modalLinkResa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="link_reservation">
                <input type="hidden" name="avis_id" id="linkResaAvisId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-link"></i> Attribuer une réservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Avis de <strong id="linkResaNom"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Réservation</label>
                        <select name="reservation_id" class="form-select" id="linkResaSelect" required>
                            <option value="">-- Choisir une réservation --</option>
                            <?php foreach ($reservations_recentes as $rr): ?>
                                <option value="<?= $rr['id'] ?>">
                                    #<?= htmlspecialchars($rr['reference'] ?? $rr['id']) ?> — <?= htmlspecialchars($rr['prenom'] . ' ' . ($rr['nom'] ?? '')) ?> (<?= $rr['date_arrivee'] ? date('d/m/Y', strtotime($rr['date_arrivee'])) : '?' ?> → <?= $rr['date_depart'] ? date('d/m/Y', strtotime($rr['date_depart'])) : '?' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Associer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal création fiche client -->
<div class="modal fade" id="modalClient" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_client">
                <input type="hidden" name="avis_id" id="clientAvisId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Créer fiche client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" name="client_prenom" id="clientPrenom" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="client_nom" id="clientNom" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                        <input type="text" name="client_telephone" id="clientTelephone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="client_email" id="clientEmail" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-user-plus"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal envoi SMS -->
<div class="modal fade" id="modalSms" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="send_sms_avis">
                <input type="hidden" name="reservation_id" id="smsResaId">
                <input type="hidden" name="sms_receiver" id="smsReceiver">
                <input type="hidden" name="sms_prenom" id="smsPrenom">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sms"></i> Envoyer un SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <strong id="smsDestinataire"></strong>
                        <small class="text-muted" id="smsNumero"></small>
                    </div>
                    <div class="mb-2">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <?php foreach ($sms_avis_templates as $tpl_key => $tpl): ?>
                                <button type="button" class="btn btn-outline-<?= $tpl['color'] ?> btn-tpl" data-tpl="<?= $tpl_key ?>">
                                    <i class="fas <?= $tpl['icon'] ?>"></i> <?= htmlspecialchars($tpl['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <textarea name="sms_message" id="smsMessage" class="form-control" rows="5" required></textarea>
                    <small class="text-muted"><span id="smsCount">0</span> caractères</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const textarea = document.getElementById('texteAvis');
const btnParser = document.getElementById('btnParser');
const previewDiv = document.getElementById('previewAvis');
const listePreview = document.getElementById('listePreview');
const nbAvisSpan = document.getElementById('nbAvis');
const avisJsonInput = document.getElementById('avisJson');

textarea.addEventListener('input', () => {
    btnParser.disabled = textarea.value.trim().length < 20;
});

btnParser.addEventListener('click', () => {
    const texte = textarea.value.trim();
    if (!texte) return;

    const avis = parserAvisBooking(texte);
    if (avis.length === 0) {
        alert('Aucun avis détecté dans le texte collé. Vérifiez le format.');
        return;
    }

    nbAvisSpan.textContent = avis.length;
    avisJsonInput.value = JSON.stringify(avis);

    // Afficher la preview
    listePreview.innerHTML = avis.map((a, i) => `
        <div class="card mb-2">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${esc(a.nom_voyageur)}</strong>
                        <small class="text-muted">(${esc(a.pays_voyageur)})</small>
                        — Résa #${esc(a.numero_reservation)}
                    </div>
                    <span class="badge ${parseFloat(a.note_globale.replace(',','.')) >= 8 ? 'bg-success' : 'bg-warning'} fs-6">
                        ${esc(a.note_globale)}/10
                    </span>
                </div>
                <small class="text-muted">${esc(a.date_avis)}</small>
                ${a.commentaire_general ? `<p class="mb-0 mt-1 small">${esc(a.commentaire_general).substring(0, 150)}${a.commentaire_general.length > 150 ? '...' : ''}</p>` : ''}
            </div>
        </div>
    `).join('');

    previewDiv.style.display = 'block';
});

function esc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// --- SMS Avis : Modal + Templates ---
const smsTemplates = <?= json_encode(array_map(fn($t) => $t['default'], $sms_avis_templates)) ?>;
let currentSmsData = {};

function ouvrirModalSms(data) {
    currentSmsData = data;
    document.getElementById('smsResaId').value = data.reservation_id;
    document.getElementById('smsReceiver').value = data.telephone;
    document.getElementById('smsPrenom').value = data.prenom;
    document.getElementById('smsDestinataire').textContent = data.prenom + ' ' + data.nom;
    document.getElementById('smsNumero').textContent = ' — ' + data.telephone;

    // Appliquer le template demandé
    appliquerTemplate(data.template_key);

    // Highlight du bouton template actif
    document.querySelectorAll('.btn-tpl').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tpl === data.template_key);
    });

    new bootstrap.Modal(document.getElementById('modalSms')).show();
}

function appliquerTemplate(key) {
    let msg = smsTemplates[key] || '';
    msg = msg.replace(/\{prenom\}/g, currentSmsData.prenom || '')
             .replace(/\{nom\}/g, currentSmsData.nom || '')
             .replace(/\{logement\}/g, currentSmsData.logement || '')
             .replace(/\{date_arrivee\}/g, currentSmsData.date_arrivee || '')
             .replace(/\{date_depart\}/g, currentSmsData.date_depart || '');
    document.getElementById('smsMessage').value = msg;
    document.getElementById('smsCount').textContent = msg.length;
}

document.querySelectorAll('.btn-tpl').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-tpl').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        appliquerTemplate(btn.dataset.tpl);
    });
});

document.getElementById('smsMessage')?.addEventListener('input', function() {
    document.getElementById('smsCount').textContent = this.value.length;
});

// --- Sélection groupée relance ---
function toggleBulkAll(master) {
    document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = master.checked);
    updateBulkCount();
}
function updateBulkCount() {
    const checked = document.querySelectorAll('.bulk-check:checked').length;
    document.getElementById('bulkCount').textContent = checked + ' sélectionné(s)';
    document.getElementById('btnBulkSend').disabled = checked === 0;
    const all = document.querySelectorAll('.bulk-check').length;
    document.getElementById('bulkSelectAll').checked = checked === all && all > 0;
}

// --- Attribution réservation ---
function ouvrirModalLinkResa(avisId, nomVoyageur) {
    document.getElementById('linkResaAvisId').value = avisId;
    document.getElementById('linkResaNom').textContent = nomVoyageur || 'Anonyme';
    document.getElementById('linkResaSelect').value = '';
    new bootstrap.Modal(document.getElementById('modalLinkResa')).show();
}

// --- Création fiche client ---
function ouvrirModalClient(data) {
    document.getElementById('clientAvisId').value = data.avis_id || '';
    document.getElementById('clientPrenom').value = data.prenom || '';
    document.getElementById('clientNom').value = data.nom || '';
    document.getElementById('clientTelephone').value = data.telephone || '';
    document.getElementById('clientEmail').value = data.email || '';
    new bootstrap.Modal(document.getElementById('modalClient')).show();
}

function parserAvisBooking(texte) {
    const lignes = texte.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    const avis = [];
    let i = 0;

    while (i < lignes.length) {
        // Chercher le début d'un avis : une note globale (nombre seul comme "10", "8,0", "9,0")
        const noteMatch = lignes[i].match(/^(\d{1,2}[,\.]\d)?$|^(\d{1,2})$/);
        if (!noteMatch) { i++; continue; }

        const noteGlobale = lignes[i];
        i++;
        if (i >= lignes.length) break;

        // Ligne suivante = nom, pays (ex: "Laurine, fr")
        const nomMatch = lignes[i].match(/^(.+),\s*([a-z]{2})$/i);
        if (!nomMatch) { continue; } // Pas un avis, fausse note

        const nom = nomMatch[1].trim();
        const pays = nomMatch[2].trim().toLowerCase();
        i++;

        // Numéro de réservation
        let numResa = '';
        if (i < lignes.length) {
            const resaMatch = lignes[i].match(/Numéro de réservation\s+(\d+)/i);
            if (resaMatch) {
                numResa = resaMatch[1];
                i++;
            }
        }

        if (!numResa) continue; // Pas un avis valide sans numéro de réservation

        // Date
        let dateAvis = '';
        if (i < lignes.length && !lignes[i].match(/^Catégories/i)) {
            dateAvis = lignes[i].replace(/Nouveau\s*!?\s*$/i, '').trim();
            i++;
        }

        // Parser les catégories
        const notes = {};
        const categoriesMap = {
            'Personnel': 'note_personnel',
            'Propreté': 'note_proprete',
            'Situation géographique': 'note_situation',
            'Équipements': 'note_equipements',
            'Confort': 'note_confort',
            'Rapport qualité/prix': 'note_rapport_qualite_prix',
            'Évaluation du lit': 'note_lit',
        };

        // Avancer à travers "Catégories principales" et "Catégories supplémentaires"
        while (i < lignes.length) {
            const ligne = lignes[i];

            if (ligne.match(/^Catégories (principales|supplémentaires)$/i)) {
                i++;
                continue;
            }

            // Vérifier si c'est un nom de catégorie connu
            let foundCat = false;
            for (const [catNom, catKey] of Object.entries(categoriesMap)) {
                if (ligne === catNom) {
                    i++;
                    if (i < lignes.length && lignes[i].match(/^\d{1,2}([,\.]\d)?$/)) {
                        notes[catKey] = lignes[i];
                        i++;
                    }
                    foundCat = true;
                    break;
                }
            }

            if (!foundCat) break; // Plus de catégories, on est dans les commentaires
        }

        // Le reste = commentaires jusqu'au prochain avis ou "Répondre" / "__Consulter" / "__Votre réponse"
        let commentaires = [];
        while (i < lignes.length) {
            const ligne = lignes[i];

            // Fin de l'avis ?
            if (ligne.match(/^Répondre$/i) ||
                ligne.match(/^__/) ||
                ligne.match(/^Traduit de/i)) {
                // "Traduit de..." → skip cette ligne et continuer les commentaires
                if (ligne.match(/^Traduit de/i)) {
                    i++;
                    continue;
                }
                i++;
                break;
            }

            // Début d'un nouvel avis ? (note seule suivie de nom,pays)
            if (ligne.match(/^(\d{1,2}[,\.]\d)?$|^(\d{1,2})$/) &&
                i + 1 < lignes.length &&
                lignes[i + 1].match(/^.+,\s*[a-z]{2}$/i)) {
                break; // Ne pas incrémenter i, on le traitera au prochain tour
            }

            commentaires.push(ligne);
            i++;
        }

        // Skip les lignes de réponse
        while (i < lignes.length && (lignes[i].match(/^__/) || lignes[i].match(/^Répondre$/i))) {
            i++;
        }

        avis.push({
            note_globale: noteGlobale,
            nom_voyageur: nom,
            pays_voyageur: pays,
            numero_reservation: numResa,
            date_avis: dateAvis,
            note_personnel: notes.note_personnel || '',
            note_proprete: notes.note_proprete || '',
            note_situation: notes.note_situation || '',
            note_equipements: notes.note_equipements || '',
            note_confort: notes.note_confort || '',
            note_rapport_qualite_prix: notes.note_rapport_qualite_prix || '',
            note_lit: notes.note_lit || '',
            commentaire_general: commentaires.join('\n').trim(),
            commentaire_positif: '',
            commentaire_negatif: '',
        });
    }

    return avis;
}
</script>
