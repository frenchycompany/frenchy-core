<?php
// index.php — Accueil FrenchyConciergerie

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

if (!isset($_SESSION['nom_utilisateur']) || !isset($_SESSION['id_intervenant'])) {
    header("Location: login.php");
    exit;
}
$nom_utilisateur = filter_var($_SESSION['nom_utilisateur'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$intervenant_id = (int) $_SESSION['id_intervenant'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pages/menu.php';

$today = date('Y-m-d');
$mois_courant = date('m');
$annee_courante = date('Y');

// === DONNEES ===

// Paie du mois
$stmt = $conn->prepare("
    SELECT SUM(montant) AS total FROM comptabilite
    WHERE intervenant_id = ? AND type = 'Charge'
      AND MONTH(date_comptabilisation) = ? AND YEAR(date_comptabilisation) = ?
");
$stmt->execute([$intervenant_id, (int)$mois_courant, (int)$annee_courante]);
$paie_mois = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Interventions du jour (planning)
$stmt = $conn->prepare("
    SELECT p.*, l.nom_du_logement,
           c.nom AS conducteur_nom,
           fm1.nom AS fm1_nom,
           fm2.nom AS fm2_nom,
           lav.nom AS laverie_nom
    FROM planning p
    JOIN liste_logements l ON p.logement_id = l.id
    LEFT JOIN intervenant c ON p.conducteur = c.id
    LEFT JOIN intervenant fm1 ON p.femme_de_menage_1 = fm1.id
    LEFT JOIN intervenant fm2 ON p.femme_de_menage_2 = fm2.id
    LEFT JOIN intervenant lav ON p.laverie = lav.id
    WHERE p.date = ?
    " . ($is_admin ? '' : "AND (p.conducteur = ? OR p.femme_de_menage_1 = ? OR p.femme_de_menage_2 = ? OR p.laverie = ?)") . "
    ORDER BY l.nom_du_logement ASC
");
if ($is_admin) {
    $stmt->execute([$today]);
} else {
    $stmt->execute([$today, $intervenant_id, $intervenant_id, $intervenant_id, $intervenant_id]);
}
$interventions_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prochaines interventions (7 jours)
$stmt = $conn->prepare("
    SELECT p.date, l.nom_du_logement, p.statut,
           c.nom AS conducteur_nom
    FROM planning p
    JOIN liste_logements l ON p.logement_id = l.id
    LEFT JOIN intervenant c ON p.conducteur = c.id
    WHERE p.date > ? AND p.date <= DATE_ADD(?, INTERVAL 7 DAY)
      AND p.statut IN ('À Faire', 'À Vérifier')
    " . ($is_admin ? '' : "AND (p.conducteur = ? OR p.femme_de_menage_1 = ? OR p.femme_de_menage_2 = ? OR p.laverie = ?)") . "
    ORDER BY p.date ASC
    LIMIT 10
");
if ($is_admin) {
    $stmt->execute([$today, $today]);
} else {
    $stmt->execute([$today, $today, $intervenant_id, $intervenant_id, $intervenant_id, $intervenant_id]);
}
$prochaines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Checkups en cours
$checkups_en_cours = [];
try {
    $stmt = $conn->prepare("
        SELECT cs.id, cs.created_at, l.nom_du_logement, cs.logement_id
        FROM checkup_sessions cs
        JOIN liste_logements l ON cs.logement_id = l.id
        WHERE cs.statut = 'en_cours'
        " . ($is_admin ? '' : "AND cs.intervenant_id = ?") . "
        ORDER BY cs.created_at DESC LIMIT 5
    ");
    if ($is_admin) { $stmt->execute([]); } else { $stmt->execute([$intervenant_id]); }
    $checkups_en_cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Notifications
$notifications = [];
try {
    $stmt = $conn->prepare("SELECT message, type, date_notification FROM notifications WHERE nom_utilisateur = ? ORDER BY date_notification DESC LIMIT 5");
    $stmt->execute([$nom_utilisateur]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Stats rapides (admin)
$stats = null;
if ($is_admin) {
    $stmt = $conn->prepare("SELECT
        (SELECT COUNT(*) FROM planning WHERE date = ? AND statut = 'À Faire') AS a_faire,
        (SELECT COUNT(*) FROM planning WHERE date = ? AND statut = 'Fait') AS fait,
        (SELECT COUNT(*) FROM planning WHERE date = ?) AS total
    ");
    $stmt->execute([$today, $today, $today]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil — FrenchyConciergerie</title>
    <style>
        .accueil-container { max-width: 900px; margin: 0 auto; padding: 0 10px 40px; }
        .welcome-card {
            background: linear-gradient(135deg, #1976d2, #1565c0);
            color: #fff; border-radius: 16px; padding: 24px; margin-bottom: 20px;
        }
        .welcome-card h2 { margin: 0 0 6px; font-size: 1.4em; }
        .welcome-card .paie { font-size: 1.8em; font-weight: 700; }
        .welcome-card small { opacity: 0.8; }

        .stat-row { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .stat-card {
            flex: 1; min-width: 120px; background: #fff; border-radius: 12px;
            padding: 16px; text-align: center; box-shadow: 0 1px 6px rgba(0,0,0,0.08);
        }
        .stat-card .num { font-size: 1.8em; font-weight: 700; }
        .stat-card .label { font-size: 0.85em; color: #888; }

        .section-title {
            font-weight: 700; font-size: 1.1em; margin: 22px 0 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-title i { color: #1976d2; }

        .intervention-card {
            background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 10px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.06); border-left: 4px solid #1976d2;
        }
        .intervention-card.statut-fait { border-left-color: #43a047; opacity: 0.7; }
        .intervention-card.statut-annule { border-left-color: #bbb; opacity: 0.5; }
        .intervention-card.statut-a-verifier { border-left-color: #ff9800; }
        .intervention-card .logement-name { font-weight: 700; font-size: 1.05em; }
        .intervention-card .badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .intervention-card .badge-role {
            padding: 3px 10px; border-radius: 20px; font-size: 0.78em; font-weight: 600;
        }
        .intervention-card .actions-row { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
        .intervention-card .actions-row a, .intervention-card .actions-row button {
            padding: 6px 14px; border-radius: 8px; font-size: 0.85em; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-checkup { background: #e8f5e9; color: #2e7d32; }
        .btn-checkup:hover { background: #c8e6c9; }
        .btn-valider { background: #e3f2fd; color: #1565c0; }
        .btn-valider:hover { background: #bbdefb; }

        .next-card {
            display: flex; align-items: center; gap: 12px;
            background: #fff; border-radius: 10px; padding: 12px 14px; margin-bottom: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .next-card .date-badge {
            background: #e3f2fd; color: #1565c0; border-radius: 8px;
            padding: 6px 10px; font-weight: 700; font-size: 0.9em; white-space: nowrap;
        }

        .checkup-card {
            display: flex; align-items: center; justify-content: space-between;
            background: #fff3e0; border-radius: 10px; padding: 12px 14px; margin-bottom: 6px;
        }
        .checkup-card a {
            background: #ff9800; color: #fff; padding: 6px 14px; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: 0.85em;
        }

        .notif-item {
            background: #f5f5f5; border-radius: 8px; padding: 10px 12px; margin-bottom: 6px;
            font-size: 0.9em;
        }
        .notif-item .notif-date { color: #999; font-size: 0.8em; }

        .empty-state { text-align: center; color: #bbb; padding: 30px; font-size: 0.95em; }

        @media (max-width: 600px) {
            .stat-row { gap: 6px; }
            .stat-card { padding: 12px 8px; }
            .stat-card .num { font-size: 1.4em; }
        }
    </style>
</head>
<body>
<div class="accueil-container">

    <!-- Welcome -->
    <div class="welcome-card">
        <h2>Bonjour, <?= htmlspecialchars($nom_utilisateur) ?></h2>
        <small><?= strftime('%A %d %B %Y') ?: date('d/m/Y') ?></small>
        <div class="paie"><?= number_format($paie_mois, 2, ',', ' ') ?> &euro;</div>
        <small>Paie du mois en cours</small>
    </div>

    <!-- Stats du jour (admin) -->
    <?php if ($stats): ?>
    <div class="stat-row">
        <div class="stat-card">
            <div class="num" style="color:#1976d2;"><?= (int)$stats['total'] ?></div>
            <div class="label">Interventions aujourd'hui</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#ff9800;"><?= (int)$stats['a_faire'] ?></div>
            <div class="label">A faire</div>
        </div>
        <div class="stat-card">
            <div class="num" style="color:#43a047;"><?= (int)$stats['fait'] ?></div>
            <div class="label">Fait</div>
        </div>
        <div class="stat-card">
            <div class="num"><?= count($interventions_jour) ?></div>
            <div class="label"><?= $is_admin ? 'Total' : 'Mes interv.' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Interventions du jour -->
    <div class="section-title"><i class="fas fa-calendar-day"></i> Interventions du jour</div>

    <?php if (empty($interventions_jour)): ?>
        <div class="empty-state"><i class="fas fa-coffee"></i><br>Aucune intervention aujourd'hui</div>
    <?php else: ?>
        <?php foreach ($interventions_jour as $interv):
            $statutSlugs = ['Fait'=>'fait','À Faire'=>'a-faire','À Vérifier'=>'a-verifier','Annulé'=>'annule'];
            $slug = $statutSlugs[$interv['statut']] ?? '';
            $statutColors = ['À Faire'=>'#ff9800','Fait'=>'#43a047','À Vérifier'=>'#1976d2','Annulé'=>'#999'];
            $statutColor = $statutColors[$interv['statut']] ?? '#666';

            // Roles de l'utilisateur dans cette intervention
            $roles = [];
            if ($interv['conducteur'] == $intervenant_id) $roles[] = ['Conducteur', '#1565c0', '#e3f2fd'];
            if ($interv['femme_de_menage_1'] == $intervenant_id) $roles[] = ['Menage', '#6a1b9a', '#f3e5f5'];
            if ($interv['femme_de_menage_2'] == $intervenant_id) $roles[] = ['Menage 2', '#6a1b9a', '#f3e5f5'];
            if ($interv['laverie'] == $intervenant_id) $roles[] = ['Laverie', '#00695c', '#e0f2f1'];
            if ($is_admin && empty($roles)) $roles[] = ['Admin', '#333', '#f5f5f5'];
        ?>
        <div class="intervention-card statut-<?= $slug ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="logement-name"><?= htmlspecialchars($interv['nom_du_logement']) ?></span>
                <span style="color:<?= $statutColor ?>;font-weight:700;font-size:0.85em;"><?= htmlspecialchars($interv['statut']) ?></span>
            </div>

            <div class="badges">
                <?php foreach ($roles as [$rLabel, $rColor, $rBg]): ?>
                    <span class="badge-role" style="background:<?= $rBg ?>;color:<?= $rColor ?>;"><?= $rLabel ?></span>
                <?php endforeach; ?>
                <?php if ($interv['nombre_de_personnes']): ?>
                    <span class="badge-role" style="background:#fff3e0;color:#e65100;"><i class="fas fa-users"></i> <?= (int)$interv['nombre_de_personnes'] ?> pers.</span>
                <?php endif; ?>
                <?php if ($interv['nombre_de_jours_reservation']): ?>
                    <span class="badge-role" style="background:#e8eaf6;color:#283593;"><?= (int)$interv['nombre_de_jours_reservation'] ?> jours</span>
                <?php endif; ?>
                <?php if (!empty($interv['lit_bebe'])): ?>
                    <span class="badge-role" style="background:#fce4ec;color:#c62828;"><i class="fas fa-baby"></i> Lit bebe</span>
                <?php endif; ?>
                <?php if (!empty($interv['early_check_in'])): ?>
                    <span class="badge-role" style="background:#e8f5e9;color:#2e7d32;">Early CI</span>
                <?php endif; ?>
                <?php if (!empty($interv['late_check_out'])): ?>
                    <span class="badge-role" style="background:#fff3e0;color:#e65100;">Late CO</span>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
            <div style="font-size:0.82em;color:#888;margin-top:6px;">
                <?php if ($interv['conducteur_nom']): ?><i class="fas fa-car"></i> <?= htmlspecialchars($interv['conducteur_nom']) ?> &nbsp;<?php endif; ?>
                <?php if ($interv['fm1_nom']): ?><i class="fas fa-broom"></i> <?= htmlspecialchars($interv['fm1_nom']) ?> &nbsp;<?php endif; ?>
                <?php if ($interv['fm2_nom']): ?><i class="fas fa-broom"></i> <?= htmlspecialchars($interv['fm2_nom']) ?> &nbsp;<?php endif; ?>
                <?php if ($interv['laverie_nom']): ?><i class="fas fa-tshirt"></i> <?= htmlspecialchars($interv['laverie_nom']) ?> &nbsp;<?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($interv['note'])): ?>
            <div style="font-size:0.85em;color:#555;margin-top:6px;background:#f9f9f9;padding:6px 10px;border-radius:6px;">
                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($interv['note']) ?>
            </div>
            <?php endif; ?>

            <div class="actions-row">
                <a href="pages/checkup_logement.php?auto_logement=<?= $interv['logement_id'] ?>" class="btn-checkup">
                    <i class="fas fa-clipboard-check"></i> Checkup
                </a>
                <a href="pages/planning.php?date_debut=<?= $today ?>&date_fin=<?= $today ?>" class="btn-valider">
                    <i class="fas fa-calendar"></i> Planning
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Checkups en cours -->
    <?php if (!empty($checkups_en_cours)): ?>
    <div class="section-title"><i class="fas fa-clipboard-check" style="color:#ff9800;"></i> Checkups en cours</div>
    <?php foreach ($checkups_en_cours as $ck): ?>
        <div class="checkup-card">
            <div>
                <strong><?= htmlspecialchars($ck['nom_du_logement']) ?></strong>
                <div style="font-size:0.8em;color:#999;">Commence le <?= date('d/m H:i', strtotime($ck['created_at'])) ?></div>
            </div>
            <a href="pages/checkup_faire.php?session_id=<?= $ck['id'] ?>"><i class="fas fa-play"></i> Reprendre</a>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Prochaines interventions -->
    <?php if (!empty($prochaines)): ?>
    <div class="section-title"><i class="fas fa-calendar-alt"></i> A venir (7 jours)</div>
    <?php foreach ($prochaines as $next): ?>
        <div class="next-card">
            <span class="date-badge"><?= date('d/m', strtotime($next['date'])) ?></span>
            <div style="flex:1;">
                <strong><?= htmlspecialchars($next['nom_du_logement']) ?></strong>
                <?php if ($next['conducteur_nom']): ?>
                    <span style="font-size:0.8em;color:#888;"> — <?= htmlspecialchars($next['conducteur_nom']) ?></span>
                <?php endif; ?>
            </div>
            <span style="font-size:0.8em;font-weight:600;color:<?= $next['statut'] === 'À Faire' ? '#ff9800' : '#1976d2' ?>;">
                <?= htmlspecialchars($next['statut']) ?>
            </span>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
    <div class="section-title"><i class="fas fa-bell"></i> Notifications</div>
    <?php foreach ($notifications as $notif): ?>
        <div class="notif-item">
            <?= htmlspecialchars($notif['message']) ?>
            <div class="notif-date"><?= htmlspecialchars($notif['date_notification'] ?? '') ?></div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>
