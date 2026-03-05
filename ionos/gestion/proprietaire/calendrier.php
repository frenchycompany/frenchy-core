<?php
/**
 * Espace Propriétaire - Calendrier des tâches et checkups
 */
require_once __DIR__ . '/auth.php';

// Mois courant ou paramètre
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startDow = (int)date('N', $firstDay); // 1=lundi
$moisNoms = ['', 'Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];

// Événements du mois
$events = []; // date => [items]
if (!empty($logement_ids)) {
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

    // Tâches avec date limite
    try {
        $stmt = $conn->prepare("SELECT t.date_limite, t.description, t.statut, l.nom_du_logement, 'tache' as type
            FROM todo_list t JOIN liste_logements l ON t.logement_id = l.id
            WHERE t.logement_id IN ($placeholders) AND t.date_limite BETWEEN ? AND ?");
        $params = array_merge($logement_ids, [$monthStart, $monthEnd]);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = (int)date('j', strtotime($row['date_limite']));
            $events[$d][] = $row;
        }
    } catch (PDOException $e) {}

    // Checkups
    try {
        $stmt = $conn->prepare("SELECT DATE(cs.created_at) as date_evt, cs.nb_ok, cs.nb_problemes, cs.statut, l.nom_du_logement, 'checkup' as type
            FROM checkup_sessions cs JOIN liste_logements l ON cs.logement_id = l.id
            WHERE cs.logement_id IN ($placeholders) AND DATE(cs.created_at) BETWEEN ? AND ?");
        $params = array_merge($logement_ids, [$monthStart, $monthEnd]);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = (int)date('j', strtotime($row['date_evt']));
            $events[$d][] = $row;
        }
    } catch (PDOException $e) {}

    // Réservations
    try {
        $stmt = $conn->prepare("SELECT r.date_arrivee, r.date_depart, r.prenom, r.nom, r.plateforme, l.nom_du_logement
            FROM reservation r JOIN liste_logements l ON r.logement_id = l.id
            WHERE r.logement_id IN ($placeholders) AND r.date_arrivee <= ? AND r.date_depart >= ? AND r.statut != 'annulée'");
        $params = array_merge($logement_ids, [$monthEnd, $monthStart]);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $start = max((int)date('j', strtotime($row['date_arrivee'])), 1);
            $end = min((int)date('j', strtotime($row['date_depart'])), $daysInMonth);
            // Adjust if reservation starts before this month
            if (strtotime($row['date_arrivee']) < strtotime($monthStart)) $start = 1;
            if (strtotime($row['date_depart']) > strtotime($monthEnd)) $end = $daysInMonth;
            for ($d = $start; $d <= $end; $d++) {
                $events[$d][] = [
                    'type' => 'reservation',
                    'nom_du_logement' => $row['nom_du_logement'],
                    'prenom' => $row['prenom'],
                    'plateforme' => $row['plateforme'],
                    'is_checkin' => ($d === (int)date('j', strtotime($row['date_arrivee'])) && strtotime($row['date_arrivee']) >= strtotime($monthStart)),
                    'is_checkout' => ($d === (int)date('j', strtotime($row['date_depart'])) && strtotime($row['date_depart']) <= strtotime($monthEnd)),
                ];
            }
        }
    } catch (PDOException $e) {}
}

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier - Espace Proprietaire</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .calendar { width: 100%; border-collapse: collapse; }
        .calendar th { background: #F3F4F6; padding: 10px; text-align: center; font-size: 0.85rem; color: #6B7280; }
        .calendar td { border: 1px solid #E5E7EB; padding: 6px; vertical-align: top; height: 90px; width: 14.28%; }
        .calendar .day-num { font-weight: 700; font-size: 0.9rem; color: #374151; margin-bottom: 4px; }
        .calendar .today { background: #EFF6FF; }
        .calendar .other-month { background: #F9FAFB; color: #D1D5DB; }
        .cal-event { font-size: 0.72rem; padding: 2px 5px; border-radius: 4px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cal-event.tache { background: #FEF3C7; color: #92400E; }
        .cal-event.checkup { background: #D1FAE5; color: #065F46; }
        .cal-event.reservation { background: #DBEAFE; color: #1E40AF; }
        .cal-event.reservation.checkin { background: #3B82F6; color: white; }
        .cal-event.reservation.checkout { background: #93C5FD; color: #1E3A8A; }
        .cal-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .cal-nav a { text-decoration: none; color: #3B82F6; font-weight: 600; padding: 8px 16px; border-radius: 8px; transition: background 0.2s; }
        .cal-nav a:hover { background: #EFF6FF; }
        .cal-nav h2 { font-size: 1.3rem; color: #1F2937; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Calendrier</h1>
        </div>

        <div class="card">
            <div class="cal-nav">
                <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>">&larr; Precedent</a>
                <h2><?= $moisNoms[$month] ?> <?= $year ?></h2>
                <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>">Suivant &rarr;</a>
            </div>

            <table class="calendar">
                <thead>
                    <tr>
                        <th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $today = date('Y-m-d');
                $cell = 1;
                $day = 1;
                echo '<tr>';
                // Empty cells before first day
                for ($i = 1; $i < $startDow; $i++) {
                    echo '<td class="other-month"></td>';
                    $cell++;
                }
                while ($day <= $daysInMonth) {
                    if ($cell > 7) { echo '</tr><tr>'; $cell = 1; }
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($dateStr === $today) ? ' today' : '';
                    echo "<td class=\"$isToday\">";
                    echo "<div class=\"day-num\">$day</div>";
                    if (!empty($events[$day])) {
                        // Deduplicate reservations per logement for this day
                        $shown_resa = [];
                        foreach ($events[$day] as $evt) {
                            if ($evt['type'] === 'tache') {
                                echo '<div class="cal-event tache" title="' . e($evt['description']) . '">' . e($evt['nom_du_logement']) . ' - ' . e(mb_substr($evt['description'], 0, 20)) . '</div>';
                            } elseif ($evt['type'] === 'checkup') {
                                $label = $evt['nb_problemes'] > 0 ? $evt['nb_problemes'] . ' pb' : 'OK';
                                echo '<div class="cal-event checkup" title="Checkup ' . e($evt['nom_du_logement']) . '">Checkup ' . e($evt['nom_du_logement']) . ' (' . $label . ')</div>';
                            } elseif ($evt['type'] === 'reservation') {
                                $key = $evt['nom_du_logement'] . '|' . ($evt['prenom'] ?? '');
                                if (isset($shown_resa[$key])) continue;
                                $shown_resa[$key] = true;
                                $subCls = $evt['is_checkin'] ? ' checkin' : ($evt['is_checkout'] ? ' checkout' : '');
                                $icon = $evt['is_checkin'] ? '&#x2192; ' : ($evt['is_checkout'] ? '&#x2190; ' : '');
                                $guest = $evt['prenom'] ? e($evt['prenom']) : e($evt['plateforme'] ?? '');
                                echo '<div class="cal-event reservation' . $subCls . '" title="' . e($evt['nom_du_logement']) . ' - ' . $guest . '">' . $icon . e(mb_substr($evt['nom_du_logement'], 0, 12)) . ' ' . $guest . '</div>';
                            }
                        }
                    }
                    echo '</td>';
                    $day++;
                    $cell++;
                }
                // Remaining cells
                while ($cell <= 7) {
                    echo '<td class="other-month"></td>';
                    $cell++;
                }
                echo '</tr>';
                ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
