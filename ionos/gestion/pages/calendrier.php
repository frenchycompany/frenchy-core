<?php
/**
 * Calendrier des réservations — FrenchyConciergerie
 * Vue calendrier avec FullCalendar
 * Sources : table reservation (RPi) + ical_reservations (RPi)
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

// ============================================================
// Logements (VPS) pour le filtre et les couleurs
// ============================================================
$logements = [];
try {
    $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

// Palette de couleurs par logement
$couleurs = ['#3788d8', '#e6550d', '#31a354', '#756bb1', '#e7298a', '#66c2a4', '#fc8d59', '#8c6d31', '#843c39', '#7b4173'];
$logementColors = [];
foreach ($logements as $idx => $l) {
    $logementColors[$l['id']] = $couleurs[$idx % count($couleurs)];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <style>
        #calendar {
            max-width: 100%;
            margin: 0 auto;
        }
        .fc-event {
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.8rem;
            padding: 1px 4px;
        }
        .fc .fc-daygrid-day.fc-day-today {
            background-color: rgba(23, 162, 184, 0.1);
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            flex-shrink: 0;
        }
        .filter-bar {
            background: #fff;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .event-tooltip {
            position: absolute;
            z-index: 9999;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.85rem;
            max-width: 300px;
            pointer-events: none;
        }
        .event-tooltip strong { color: #343a40; }
        .event-tooltip .label { color: #6c757d; font-size: 0.8rem; }
        .stat-card { text-align: center; border-radius: 10px; padding: 0.75rem; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fas fa-calendar text-primary"></i> Calendrier des réservations</h2>
        </div>
        <div>
            <a href="reservations.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list"></i> Vue liste
            </a>
            <a href="sync_ical.php" class="btn btn-outline-info btn-sm">
                <i class="fas fa-sync-alt"></i> Sync iCal
            </a>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row g-3 mb-4" id="stats-row">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-primary bg-opacity-10">
                <div class="fs-4 fw-bold text-primary" id="stat-total">—</div>
                <small class="text-muted">Réservations affichées</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success bg-opacity-10">
                <div class="fs-4 fw-bold text-success" id="stat-current">—</div>
                <small class="text-muted">En cours</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-warning bg-opacity-10">
                <div class="fs-4 fw-bold text-warning" id="stat-upcoming">—</div>
                <small class="text-muted">À venir</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-info bg-opacity-10">
                <div class="fs-4 fw-bold text-info" id="stat-nights">—</div>
                <small class="text-muted">Nuitées (période)</small>
            </div>
        </div>
    </div>

    <!-- Filtre + Légende -->
    <div class="filter-bar">
        <div class="row align-items-center">
            <div class="col-md-4 mb-2 mb-md-0">
                <label class="form-label mb-1 fw-bold">Filtrer par logement</label>
                <select id="logement-filter" class="form-select form-select-sm">
                    <option value="">Tous les logements</option>
                    <?php foreach ($logements as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom_du_logement']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label mb-1 fw-bold">Légende</label>
                <div>
                    <?php foreach ($logements as $idx => $l): ?>
                        <span class="legend-item">
                            <span class="legend-dot" style="background:<?= $couleurs[$idx % count($couleurs)] ?>"></span>
                            <?= htmlspecialchars($l['nom_du_logement']) ?>
                        </span>
                    <?php endforeach; ?>
                    <span class="legend-item">
                        <span class="legend-dot" style="background:#95a5a6"></span>
                        Bloqué
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendrier -->
    <div id="calendar"></div>

    <!-- Tooltip flottant -->
    <div id="event-tooltip" class="event-tooltip" style="display:none"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/fr.global.min.js"></script>
<script>
(function() {
    // Couleurs par logement (depuis PHP)
    var logementColors = <?= json_encode($logementColors) ?>;
    var tooltip = document.getElementById('event-tooltip');
    var allEvents = [];
    var calendar;

    function updateStats(events) {
        var today = new Date().toISOString().slice(0, 10);
        var total = 0, current = 0, upcoming = 0, nights = 0;
        events.forEach(function(e) {
            if (e.extendedProps && e.extendedProps.is_blocked) return;
            total++;
            var start = (e.startStr || e.start || '').slice(0, 10);
            var end = (e.endStr || e.end || '').slice(0, 10);
            if (start <= today && end > today) current++;
            if (start > today) upcoming++;
            // Calculer nuitées
            var d1 = new Date(start), d2 = new Date(end);
            if (!isNaN(d1) && !isNaN(d2)) {
                nights += Math.max(0, Math.round((d2 - d1) / 86400000));
            }
        });
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-current').textContent = current;
        document.getElementById('stat-upcoming').textContent = upcoming;
        document.getElementById('stat-nights').textContent = nights;
    }

    function formatDate(d) {
        if (!d) return '—';
        var date = new Date(d);
        return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    var calendarEl = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'fr',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,listMonth'
        },
        buttonText: {
            today: "Aujourd'hui",
            month: 'Mois',
            week: 'Semaine',
            list: 'Liste'
        },
        height: 'auto',
        navLinks: true,
        editable: false,
        dayMaxEvents: 4,
        moreLinkText: function(n) { return '+' + n + ' résa.'; },

        events: function(info, successCallback, failureCallback) {
            var start = info.startStr.slice(0, 10);
            var end = info.endStr.slice(0, 10);
            var filterLogement = document.getElementById('logement-filter').value;

            fetch('calendrier_api.php?start=' + start + '&end=' + end +
                  (filterLogement ? '&logement_id=' + filterLogement : ''))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        failureCallback(new Error(data.error || 'Erreur'));
                        return;
                    }
                    allEvents = data.events.map(function(e) {
                        return {
                            id: e.id,
                            title: e.title,
                            start: e.start,
                            end: e.end,
                            color: e.is_blocked ? '#95a5a6' : (logementColors[e.logement_id] || '#667eea'),
                            extendedProps: {
                                guest_name: e.guest_name || '',
                                logement: e.logement_name || '',
                                logement_id: e.logement_id,
                                plateforme: e.plateforme || '',
                                telephone: e.telephone || '',
                                nb_adultes: e.nb_adultes || 0,
                                nb_enfants: e.nb_enfants || 0,
                                is_blocked: e.is_blocked || false,
                                source: e.source || '',
                                num_nights: e.num_nights || 0,
                                statut: e.statut || ''
                            }
                        };
                    });
                    successCallback(allEvents);
                    updateStats(allEvents);
                })
                .catch(failureCallback);
        },

        eventMouseEnter: function(info) {
            var p = info.event.extendedProps;
            var html = '<strong>' + (info.event.title || 'Réservation') + '</strong><br>';
            if (p.logement) html += '<span class="label">Logement :</span> ' + p.logement + '<br>';
            html += '<span class="label">Du</span> ' + formatDate(info.event.startStr) +
                    ' <span class="label">au</span> ' + formatDate(info.event.endStr);
            if (p.num_nights) html += ' <span class="label">(' + p.num_nights + ' nuit' + (p.num_nights > 1 ? 's' : '') + ')</span>';
            html += '<br>';
            if (p.guest_name) html += '<span class="label">Client :</span> ' + p.guest_name + '<br>';
            if (p.plateforme) html += '<span class="label">Plateforme :</span> ' + p.plateforme + '<br>';
            if (p.telephone) html += '<span class="label">Tél :</span> ' + p.telephone + '<br>';
            var voyageurs = (parseInt(p.nb_adultes) || 0) + (parseInt(p.nb_enfants) || 0);
            if (voyageurs > 0) html += '<span class="label">Voyageurs :</span> ' + voyageurs + '<br>';
            if (p.is_blocked) html += '<span class="badge bg-secondary mt-1">Période bloquée</span>';
            if (p.statut === 'annulée') html += '<span class="badge bg-danger mt-1">Annulée</span>';
            tooltip.innerHTML = html;
            tooltip.style.display = 'block';

            var rect = info.el.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            tooltip.style.left = Math.min(rect.left + window.scrollX, window.innerWidth - 320) + 'px';
        },

        eventMouseLeave: function() {
            tooltip.style.display = 'none';
        },

        eventClick: function(info) {
            var p = info.event.extendedProps;
            if (p.source === 'reservation' && p.logement_id) {
                // Ouvrir la page réservations filtrée
                window.location.href = 'reservations.php?logement=' + p.logement_id;
            }
        }
    });

    calendar.render();

    // Filtre logement → refetch
    document.getElementById('logement-filter').addEventListener('change', function() {
        calendar.refetchEvents();
    });
})();
</script>
</body>
</html>
