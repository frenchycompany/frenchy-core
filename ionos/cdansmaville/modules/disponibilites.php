<?php
if (empty($client['ics_url'])) {
    echo "<p>⚠️ Aucune URL de calendrier fournie pour ce client.</p>";
    return;
}

$ics_url = $client['ics_url'];
$ics_data = @file_get_contents($ics_url);

if ($ics_data === false) {
    echo "<p>❌ Impossible de charger le calendrier.</p>";
    return;
}

preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics_data, $events);

$occupied_ranges = [];

foreach ($events[1] as $event) {
    if (preg_match('/DTSTART(?:;VALUE=DATE)?:(\d{8})/', $event, $start) &&
        preg_match('/DTEND(?:;VALUE=DATE)?:(\d{8})/', $event, $end)) {

        $start_date = DateTime::createFromFormat('Ymd', $start[1]);
        $end_date = DateTime::createFromFormat('Ymd', $end[1]);

        $occupied_ranges[] = [
            'start' => $start_date->format('Y-m-d'),
            'end' => $end_date->format('Y-m-d'),
            'display' => 'background',
            'color' => '#ff4d4d'
        ];
    }
}
?>

<!-- Titre au-dessus du calendrier -->
<h3 style="text-align: center; margin-top: 30px;">📅 Consulter le calendrier des disponibilités</h3>

<!-- Calendrier -->
<div id='calendar'></div>

<!-- Bouton CTA en dessous -->
<div style="text-align: center; margin-top: 20px;">
    <a href="sms:0647554678?body=Bonjour%20je%20souhaite%20réserver%20le%20logement%20vu%20sur%20votre%20site." 
       class="cta-button"
       style="display: inline-block; padding: 14px 28px; background-color: #28a745; color: white; font-size: 18px; font-weight: bold; border-radius: 6px; text-decoration: none; transition: background 0.3s;">
        📱 Réserver par SMS au 06 47 55 46 78
    </a>
</div>

<style>
.cta-button:hover {
    background-color: #1e7e34;
}
</style>

</div>

<!-- FullCalendar JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 450,
        locale: 'fr',
        validRange: {
            start: new Date()
        },
        events: <?php echo json_encode($occupied_ranges); ?>,
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: ''
        }
    });

    calendar.render();
});
</script>

<!-- FullCalendar CSS (à inclure UNIQUEMENT si pas déjà dans ton layout global) -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<style>
#calendar {
    max-width: 600px;
    margin: 30px auto;
    font-size: 0.85rem;
    border: 1px solid #ccc;
    padding: 10px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
}

.cta-button:hover {
    background-color: #0056b3;
}
</style>
