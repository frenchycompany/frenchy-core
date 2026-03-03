<?php
/**
 * Interface for viewing all reservations imported via iCal
 */

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }

        .view-tabs {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .view-tab {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .view-tab:hover {
            background: rgba(255,255,255,0.3);
        }

        .view-tab.active {
            background: white;
            color: #667eea;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon-blue {
            background: #e3f2fd;
            color: #1976d2;
        }

        .stat-icon-green {
            background: #e8f5e9;
            color: #388e3c;
        }

        .stat-icon-orange {
            background: #fff3e0;
            color: #f57c00;
        }

        .stat-icon-purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #2d3748;
        }

        .stat-info p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }

        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .view-content {
            display: none;
        }

        .view-content.active {
            display: block;
        }

        .reservation-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #667eea;
        }

        .reservation-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .reservation-card.blocked {
            border-left-color: #95a5a6;
            opacity: 0.7;
        }

        .reservation-card.past {
            opacity: 0.6;
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .reservation-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .reservation-dates {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4a5568;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .reservation-guest {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #718096;
            margin-bottom: 8px;
        }

        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .platform-airbnb { background: #ffe8f0; color: #ff385c; }
        .platform-booking { background: #e3f2fd; color: #003b95; }
        .platform-direct { background: #e8f5e9; color: #2e7d32; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .calendar-view {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        #calendar {
            max-width: 100%;
        }

        .fc-event {
            cursor: pointer;
        }

        .reservation-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-sm-icon {
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Mes Réservations</h1>
            <div class="view-tabs">
                <button class="view-tab active" onclick="switchView('list')">
                    <i class="fas fa-list"></i> Liste
                </button>
                <button class="view-tab" onclick="switchView('calendar')">
                    <i class="fas fa-calendar"></i> Calendrier
                </button>
            </div>
        </div>

        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3 id="total-reservations">0</h3>
                    <p>Réservations totales</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-info">
                    <h3 id="upcoming-reservations">0</h3>
                    <p>À venir</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3 id="current-reservations">0</h3>
                    <p>En cours</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3 id="total-nights">0</h3>
                    <p>Nuits réservées</p>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div id="list-view" class="view-content active">
            <div class="filters-bar">
                <div class="row">
                    <div class="col-md-3">
                        <label for="platform-filter">Plateforme</label>
                        <select id="platform-filter" class="form-control">
                            <option value="">Toutes</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="period-filter">Période</label>
                        <select id="period-filter" class="form-control">
                            <option value="">Toutes</option>
                            <option value="upcoming">À venir</option>
                            <option value="current">En cours</option>
                            <option value="past">Passées</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter">Statut</label>
                        <select id="status-filter" class="form-control">
                            <option value="">Tous</option>
                            <option value="confirmed">Confirmé</option>
                            <option value="pending">En attente</option>
                            <option value="cancelled">Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search-filter">Recherche</label>
                        <input type="text" id="search-filter" class="form-control" placeholder="Nom du client...">
                    </div>
                </div>
            </div>

            <div id="reservations-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div id="calendar-view" class="view-content">
            <div class="calendar-view">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js"></script>

    <script>
        let allReservations = [];
        let filteredReservations = [];
        let calendar = null;

        $(document).ready(function() {
            loadReservations();
            initFilters();
        });

        function loadReservations() {
            fetch('ical_sync_api.php?action=get_reservations')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        allReservations = data.reservations || [];
                        filteredReservations = allReservations;
                        updateStats();
                        populateFilters();
                        renderReservationsList();
                        initCalendar();
                    }
                })
                .catch(error => {
                    console.error(error);
                    $('#reservations-list').html('<div class="alert alert-danger">Erreur lors du chargement des réservations</div>');
                });
        }

        function updateStats() {
            const total = allReservations.length;
            const upcoming = allReservations.filter(r => r.reservation_period === 'upcoming').length;
            const current = allReservations.filter(r => r.reservation_period === 'current').length;
            const totalNights = allReservations.reduce((sum, r) => sum + (parseInt(r.num_nights) || 0), 0);

            $('#total-reservations').text(total);
            $('#upcoming-reservations').text(upcoming);
            $('#current-reservations').text(current);
            $('#total-nights').text(totalNights);
        }

        function populateFilters() {
            const platforms = [...new Set(allReservations.map(r => ({
                code: r.platform_code,
                name: r.platform_name
            })).map(p => JSON.stringify(p)))].map(p => JSON.parse(p));

            platforms.forEach(platform => {
                $('#platform-filter').append(
                    $('<option>').val(platform.code).text(platform.name)
                );
            });
        }

        function initFilters() {
            $('#platform-filter, #period-filter, #status-filter').change(applyFilters);
            $('#search-filter').on('input', applyFilters);
        }

        function applyFilters() {
            const platformFilter = $('#platform-filter').val();
            const periodFilter = $('#period-filter').val();
            const statusFilter = $('#status-filter').val();
            const searchFilter = $('#search-filter').val().toLowerCase();

            filteredReservations = allReservations.filter(res => {
                if (platformFilter && res.platform_code !== platformFilter) return false;
                if (periodFilter && res.reservation_period !== periodFilter) return false;
                if (statusFilter && res.status !== statusFilter) return false;
                if (searchFilter) {
                    const searchText = `${res.guest_name} ${res.summary} ${res.listing_title}`.toLowerCase();
                    if (!searchText.includes(searchFilter)) return false;
                }
                return true;
            });

            renderReservationsList();
        }

        function renderReservationsList() {
            const container = $('#reservations-list');
            container.empty();

            if (filteredReservations.length === 0) {
                container.html('<div class="alert alert-info text-center">Aucune réservation trouvée</div>');
                return;
            }

            filteredReservations.forEach(res => {
                const card = createReservationCard(res);
                container.append(card);
            });
        }

        function createReservationCard(res) {
            const isBlocked = res.is_blocked == 1;
            const isPast = res.reservation_period === 'past';
            const cardClass = `reservation-card ${isBlocked ? 'blocked' : ''} ${isPast ? 'past' : ''}`;

            return $(`
                <div class="${cardClass}">
                    <div class="reservation-header">
                        <div style="flex: 1;">
                            <h3 class="reservation-title">
                                ${isBlocked ? '<i class="fas fa-ban"></i> ' : ''}
                                ${res.summary || 'Réservation'}
                            </h3>
                            <div class="reservation-dates">
                                <i class="fas fa-calendar"></i>
                                <strong>${formatDate(res.start_date)}</strong>
                                <i class="fas fa-arrow-right"></i>
                                <strong>${formatDate(res.end_date)}</strong>
                                <span class="badge text-bg-secondary">${res.num_nights} nuit${res.num_nights > 1 ? 's' : ''}</span>
                            </div>
                            ${res.guest_name ?
                                `<div class="reservation-guest">
                                    <i class="fas fa-user"></i>
                                    ${res.guest_name}
                                </div>` : ''
                            }
                            ${res.guest_email ?
                                `<div class="reservation-guest">
                                    <i class="fas fa-envelope"></i>
                                    ${res.guest_email}
                                </div>` : ''
                            }
                            ${res.guest_phone ?
                                `<div class="reservation-guest">
                                    <i class="fas fa-phone"></i>
                                    ${res.guest_phone}
                                </div>` : ''
                            }
                            ${res.listing_title ?
                                `<div class="reservation-guest">
                                    <i class="fas fa-home"></i>
                                    ${res.listing_title} - ${res.listing_city || ''}
                                </div>` : ''
                            }
                        </div>
                        <div class="reservation-actions">
                            <span class="platform-badge platform-${res.platform_code}">
                                ${res.platform_name}
                            </span>
                            <span class="status-badge status-${res.status}">
                                ${getStatusText(res.status)}
                            </span>
                        </div>
                    </div>
                </div>
            `);
        }

        function switchView(view) {
            $('.view-tab').removeClass('active');
            $(`.view-tab:contains('${view === 'list' ? 'Liste' : 'Calendrier'}')`).addClass('active');

            $('.view-content').removeClass('active');
            $(`#${view}-view`).addClass('active');

            if (view === 'calendar' && !calendar) {
                initCalendar();
            }
        }

        function initCalendar() {
            if (calendar) {
                calendar.destroy();
            }

            fetch('ical_sync_api.php?action=get_calendar_events')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const calendarEl = document.getElementById('calendar');
                        calendar = new FullCalendar.Calendar(calendarEl, {
                            initialView: 'dayGridMonth',
                            locale: 'fr',
                            headerToolbar: {
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,timeGridWeek,listWeek'
                            },
                            events: data.events,
                            eventClick: function(info) {
                                alert('Réservation: ' + info.event.title);
                            },
                            height: 'auto'
                        });
                        calendar.render();
                    }
                });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });
        }

        function getStatusText(status) {
            const texts = {
                'confirmed': 'Confirmé',
                'pending': 'En attente',
                'cancelled': 'Annulé',
                'blocked': 'Bloqué'
            };
            return texts[status] || status;
        }
    </script>
</body>
</html>