<?php
/**
 * Calendrier des réservations — FrenchyConciergerie
 * Vue moderne : Timeline multi-logements (Gantt) + Calendrier mensuel
 * Sources : table reservation (RPi) + ical_reservations (RPi)
 */
include '../config.php';
include '../pages/menu.php';

$logements = [];
try {
    $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements WHERE actif = 1 ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $logements = $conn->query("SELECT id, nom_du_logement FROM liste_logements ORDER BY nom_du_logement")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { /* ignore */ }
}

// Palette moderne
$couleurs = [
    '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6',
    '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#06b6d4'
];
$logementColors = [];
$logementData = [];
foreach ($logements as $idx => $l) {
    $c = $couleurs[$idx % count($couleurs)];
    $logementColors[$l['id']] = $c;
    $logementData[] = [
        'id'    => $l['id'],
        'nom'   => $l['nom_du_logement'],
        'color' => $c
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier — FrenchyConciergerie</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
    :root {
        --fc-bg: #f8fafc;
        --fc-card: #ffffff;
        --fc-border: #e2e8f0;
        --fc-text: #1e293b;
        --fc-muted: #94a3b8;
        --fc-accent: #6366f1;
        --fc-radius: 16px;
        --fc-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
        --fc-shadow-lg: 0 8px 32px rgba(0,0,0,0.08);
    }

    body { background: var(--fc-bg); }

    /* ─── Header ─── */
    .cal-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
    }
    .cal-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--fc-text); margin: 0; }
    .cal-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

    /* ─── View Toggle ─── */
    .view-toggle {
        display: inline-flex; background: var(--fc-card); border-radius: 12px;
        padding: 4px; box-shadow: var(--fc-shadow); border: 1px solid var(--fc-border);
    }
    .view-toggle button {
        border: none; background: transparent; padding: 8px 18px;
        border-radius: 10px; font-size: 0.85rem; font-weight: 600;
        color: var(--fc-muted); cursor: pointer; transition: all 0.25s ease;
        display: flex; align-items: center; gap: 6px;
    }
    .view-toggle button.active {
        background: var(--fc-accent); color: #fff;
        box-shadow: 0 2px 8px rgba(99,102,241,0.35);
    }
    .view-toggle button:hover:not(.active) { color: var(--fc-text); background: #f1f5f9; }

    /* ─── Stat Cards ─── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem; margin-bottom: 1.5rem;
    }
    .stat-card {
        background: var(--fc-card); border-radius: 14px; padding: 1rem 1.25rem;
        box-shadow: var(--fc-shadow); border: 1px solid var(--fc-border);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: var(--fc-shadow-lg); }
    .stat-value {
        font-size: 1.75rem; font-weight: 800; line-height: 1;
        background: linear-gradient(135deg, var(--accent-from), var(--accent-to));
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stat-label { font-size: 0.78rem; color: var(--fc-muted); margin-top: 4px; font-weight: 500; }
    .stat-icon {
        width: 36px; height: 36px; border-radius: 10px; display: flex;
        align-items: center; justify-content: center; font-size: 1rem;
        margin-bottom: 0.5rem; color: #fff;
    }

    /* ─── Timeline ─── */
    .timeline-wrap {
        background: var(--fc-card); border-radius: var(--fc-radius);
        box-shadow: var(--fc-shadow); border: 1px solid var(--fc-border);
        overflow: hidden;
    }
    .timeline-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--fc-border);
        flex-wrap: wrap; gap: 0.5rem;
    }
    .timeline-nav { display: flex; align-items: center; gap: 0.5rem; }
    .timeline-nav button {
        border: 1px solid var(--fc-border); background: var(--fc-card);
        width: 34px; height: 34px; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--fc-text); transition: all 0.2s;
    }
    .timeline-nav button:hover { background: #f1f5f9; border-color: var(--fc-accent); color: var(--fc-accent); }
    .timeline-period {
        font-weight: 700; font-size: 1.05rem; color: var(--fc-text); min-width: 200px; text-align: center;
    }
    .timeline-zoom { display: flex; gap: 4px; }
    .timeline-zoom button {
        border: 1px solid var(--fc-border); background: var(--fc-card);
        padding: 4px 14px; border-radius: 8px; font-size: 0.8rem;
        font-weight: 600; cursor: pointer; color: var(--fc-muted); transition: all 0.2s;
    }
    .timeline-zoom button.active { background: var(--fc-accent); color: #fff; border-color: var(--fc-accent); }

    .timeline-container {
        overflow-x: auto; overflow-y: visible; position: relative;
        scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;
    }
    .timeline-container::-webkit-scrollbar { height: 8px; }
    .timeline-container::-webkit-scrollbar-track { background: transparent; }
    .timeline-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    .timeline-grid {
        display: grid; position: relative; min-width: max-content;
    }
    .timeline-header-row {
        display: contents;
    }
    .timeline-month-label {
        position: sticky; top: 0; z-index: 3;
        font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--fc-accent); padding: 6px 0;
        text-align: center; background: #f8fafc; border-bottom: 2px solid var(--fc-accent);
    }
    .timeline-day-header {
        position: sticky; top: 0; z-index: 2;
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 4px 0;
        font-size: 0.7rem; border-right: 1px solid #f1f5f9;
        background: #fafbfc; user-select: none;
    }
    .timeline-day-header .day-num { font-weight: 700; font-size: 0.82rem; color: var(--fc-text); }
    .timeline-day-header .day-name { font-weight: 500; color: var(--fc-muted); font-size: 0.65rem; text-transform: uppercase; }
    .timeline-day-header.today { background: rgba(99,102,241,0.08); }
    .timeline-day-header.today .day-num { color: var(--fc-accent); }
    .timeline-day-header.weekend { background: #fef9f0; }

    .timeline-row {
        display: contents;
    }
    .timeline-label {
        position: sticky; left: 0; z-index: 4;
        background: var(--fc-card); border-right: 1px solid var(--fc-border);
        border-bottom: 1px solid #f1f5f9; padding: 0 1rem;
        display: flex; align-items: center; gap: 0.6rem;
        min-height: 52px; white-space: nowrap;
    }
    .timeline-label-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }
    .timeline-label-text {
        font-weight: 600; font-size: 0.85rem; color: var(--fc-text);
        overflow: hidden; text-overflow: ellipsis; max-width: 140px;
    }
    .timeline-cell {
        border-right: 1px solid #f8f9fa; border-bottom: 1px solid #f1f5f9;
        min-height: 52px; position: relative;
    }
    .timeline-cell.today { background: rgba(99,102,241,0.04); }
    .timeline-cell.weekend { background: rgba(251,191,36,0.03); }

    .timeline-event {
        position: absolute; top: 8px; height: 36px; border-radius: 8px;
        display: flex; align-items: center; padding: 0 10px;
        font-size: 0.78rem; font-weight: 600; color: #fff;
        cursor: pointer; z-index: 1; overflow: hidden;
        white-space: nowrap; text-overflow: ellipsis;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    .timeline-event:hover {
        transform: translateY(-1px); z-index: 5;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    .timeline-event.blocked {
        background: repeating-linear-gradient(
            -45deg, #cbd5e1, #cbd5e1 4px, #e2e8f0 4px, #e2e8f0 8px
        ) !important;
        color: #64748b;
    }
    .timeline-event-icon { margin-right: 6px; font-size: 0.72rem; opacity: 0.85; }

    /* Today marker */
    .today-marker {
        position: absolute; top: 0; bottom: 0; width: 2px;
        background: var(--fc-accent); z-index: 3;
        box-shadow: 0 0 8px rgba(99,102,241,0.4);
    }
    .today-marker::before {
        content: ''; position: absolute; top: -3px; left: -4px;
        width: 10px; height: 10px; border-radius: 50%;
        background: var(--fc-accent);
    }

    /* ─── Calendar View (FullCalendar overrides) ─── */
    #fc-calendar-wrap {
        background: var(--fc-card); border-radius: var(--fc-radius);
        box-shadow: var(--fc-shadow); border: 1px solid var(--fc-border);
        padding: 1rem;
    }
    .fc .fc-toolbar { margin-bottom: 1rem; }
    .fc .fc-button-primary {
        background: var(--fc-card) !important; color: var(--fc-text) !important;
        border: 1px solid var(--fc-border) !important; border-radius: 10px !important;
        font-weight: 600 !important; font-size: 0.85rem !important;
        box-shadow: none !important; padding: 6px 16px !important;
    }
    .fc .fc-button-primary:hover { background: #f1f5f9 !important; }
    .fc .fc-button-primary.fc-button-active {
        background: var(--fc-accent) !important; color: #fff !important;
        border-color: var(--fc-accent) !important;
    }
    .fc .fc-toolbar-title { font-weight: 700; font-size: 1.15rem; }
    .fc .fc-daygrid-day.fc-day-today { background: rgba(99,102,241,0.06); }
    .fc .fc-event {
        border-radius: 6px; border: none; font-size: 0.78rem;
        font-weight: 600; padding: 2px 6px;
    }
    .fc .fc-daygrid-event-dot { display: none; }
    .fc .fc-list-event-dot { border-color: var(--fc-accent); }

    /* ─── Detail Modal ─── */
    .modal-backdrop-custom {
        position: fixed; inset: 0; background: rgba(15,23,42,0.5);
        backdrop-filter: blur(4px); z-index: 9998;
        opacity: 0; transition: opacity 0.25s ease;
    }
    .modal-backdrop-custom.show { opacity: 1; }
    .detail-modal {
        position: fixed; right: -440px; top: 0; bottom: 0;
        width: 420px; max-width: 90vw; background: var(--fc-card);
        z-index: 9999; box-shadow: -8px 0 40px rgba(0,0,0,0.12);
        transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; flex-direction: column;
    }
    .detail-modal.show { right: 0; }
    .detail-modal-header {
        padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--fc-border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .detail-modal-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
    .detail-modal-close {
        width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--fc-border);
        background: var(--fc-card); cursor: pointer; display: flex;
        align-items: center; justify-content: center; transition: all 0.2s;
    }
    .detail-modal-close:hover { background: #fef2f2; border-color: #fca5a5; color: #dc2626; }
    .detail-modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
    .detail-row {
        display: flex; align-items: flex-start; gap: 0.75rem;
        padding: 0.6rem 0; border-bottom: 1px solid #f1f5f9;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row-icon {
        width: 32px; height: 32px; border-radius: 8px; display: flex;
        align-items: center; justify-content: center; flex-shrink: 0;
        font-size: 0.85rem; background: #f1f5f9; color: var(--fc-muted);
    }
    .detail-row-content { flex: 1; }
    .detail-row-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--fc-muted); font-weight: 600; }
    .detail-row-value { font-weight: 600; color: var(--fc-text); font-size: 0.92rem; }
    .detail-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;
    }
    .detail-color-bar {
        height: 4px; border-radius: 2px; margin-bottom: 1rem;
    }

    /* ─── Responsive ─── */
    @media (max-width: 768px) {
        .cal-header { flex-direction: column; align-items: flex-start; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .timeline-label-text { max-width: 90px; font-size: 0.78rem; }
        .detail-modal { width: 100%; }
    }

    /* ─── Price overlay in timeline ─── */
    .timeline-price {
        position: absolute; bottom: 2px; left: 0; right: 0;
        text-align: center; font-size: 0.62rem; font-weight: 700;
        color: var(--fc-muted); pointer-events: none; line-height: 1;
    }
    .timeline-price.has-event { color: rgba(255,255,255,0.7); }
    .timeline-label-meta {
        display: flex; flex-direction: column; gap: 1px;
    }
    .timeline-label-pricing {
        font-size: 0.65rem; color: var(--fc-muted); font-weight: 500;
        display: flex; gap: 6px; align-items: center;
    }
    .timeline-label-pricing .tag {
        display: inline-flex; align-items: center; gap: 2px;
        background: #f1f5f9; padding: 1px 5px; border-radius: 4px;
    }
    .timeline-label-pricing .tag i { font-size: 0.55rem; }

    /* ─── Price toggle ─── */
    .price-toggle {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 8px; font-size: 0.78rem;
        font-weight: 600; background: #f0fdf4; border: 1px solid #bbf7d0;
        cursor: pointer; transition: all 0.2s; user-select: none; color: #166534;
    }
    .price-toggle:hover { background: #dcfce7; }
    .price-toggle.inactive { background: #f8fafc; border-color: #e2e8f0; color: var(--fc-muted); }

    /* ─── Gap indicator in timeline ─── */
    .timeline-gap {
        position: absolute; top: 14px; height: 24px; border-radius: 6px;
        background: repeating-linear-gradient(
            90deg, #fef3c7 0px, #fef3c7 4px, transparent 4px, transparent 8px
        );
        border: 1px dashed #f59e0b; z-index: 0; display: flex;
        align-items: center; justify-content: center;
        font-size: 0.6rem; font-weight: 700; color: #92400e;
        cursor: default; pointer-events: none;
    }

    /* ─── Checkin/Checkout markers ─── */
    .timeline-event .ev-checkin {
        position: absolute; left: -1px; top: -1px; bottom: -1px; width: 4px;
        border-radius: 8px 0 0 8px; background: rgba(255,255,255,0.5);
    }
    .timeline-event .ev-checkout {
        position: absolute; right: -1px; top: -1px; bottom: -1px; width: 4px;
        border-radius: 0 8px 8px 0; background: rgba(0,0,0,0.15);
    }

    /* ─── Platform badges ─── */
    .platform-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 18px; height: 18px; border-radius: 4px; font-size: 0.55rem;
        font-weight: 900; color: #fff; margin-right: 4px; flex-shrink: 0;
        letter-spacing: -0.5px;
    }
    .platform-icon.airbnb { background: #ff385c; }
    .platform-icon.booking { background: #003b95; }
    .platform-icon.direct { background: #10b981; }
    .platform-icon.other { background: #94a3b8; }

    /* ─── Sync button ─── */
    .sync-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 8px; font-size: 0.78rem;
        font-weight: 600; background: #eff6ff; border: 1px solid #bfdbfe;
        cursor: pointer; transition: all 0.2s; user-select: none; color: #1e40af;
    }
    .sync-btn:hover { background: #dbeafe; }
    .sync-btn.syncing { opacity: 0.7; pointer-events: none; }
    .sync-btn .fa-spin { display: none; }
    .sync-btn.syncing .fa-spin { display: inline-block; }
    .sync-btn.syncing .fa-sync-alt { display: none; }

    /* ─── Toast notification ─── */
    .cal-toast {
        position: fixed; bottom: 2rem; right: 2rem; z-index: 10000;
        background: var(--fc-card); border-radius: 12px; padding: 0.75rem 1.25rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15); border: 1px solid var(--fc-border);
        font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px;
        transform: translateY(100px); opacity: 0; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .cal-toast.show { transform: translateY(0); opacity: 1; }

    /* ─── Animations ─── */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-in { animation: fadeInUp 0.4s ease forwards; }
    .view-container { display: none; }
    .view-container.active { display: block; animation: fadeInUp 0.3s ease; }

    /* ─── Legend ─── */
    .legend-bar {
        display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
        padding: 0.75rem 1.25rem; background: var(--fc-card);
        border-radius: 12px; box-shadow: var(--fc-shadow);
        border: 1px solid var(--fc-border); margin-bottom: 1.25rem;
    }
    .legend-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 8px; font-size: 0.78rem;
        font-weight: 600; background: #f8fafc; border: 1px solid #e2e8f0;
        cursor: pointer; transition: all 0.2s; user-select: none;
    }
    .legend-chip:hover { border-color: var(--chip-color, #6366f1); background: #f1f5f9; }
    .legend-chip.active { background: var(--chip-color, #6366f1); color: #fff; border-color: transparent; }
    .legend-chip .chip-dot {
        width: 8px; height: 8px; border-radius: 50%;
    }
    </style>
</head>
<body>
<div class="container-fluid mt-3 mb-5">

    <!-- Header -->
    <div class="cal-header animate-in">
        <div>
            <h2><i class="fas fa-calendar-alt" style="color:var(--fc-accent)"></i> Calendrier</h2>
        </div>
        <div class="cal-actions">
            <div class="view-toggle" id="viewToggle">
                <button class="active" data-view="timeline">
                    <i class="fas fa-grip-lines"></i> Timeline
                </button>
                <button data-view="calendar">
                    <i class="fas fa-calendar"></i> Mois
                </button>
                <button data-view="list">
                    <i class="fas fa-list-ul"></i> Liste
                </button>
            </div>
            <a href="reservations.php" class="btn btn-sm" style="background:#f1f5f9;border:1px solid var(--fc-border);font-weight:600;border-radius:10px">
                <i class="fas fa-table-list"></i> Listing
            </a>
            <a href="sync_ical.php" class="btn btn-sm" style="background:#f1f5f9;border:1px solid var(--fc-border);font-weight:600;border-radius:10px">
                <i class="fas fa-sync-alt"></i> Sync
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid animate-in" style="animation-delay:0.05s">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8)"><i class="fas fa-bookmark"></i></div>
            <div class="stat-value" style="--accent-from:#6366f1;--accent-to:#818cf8" id="stat-total">0</div>
            <div class="stat-label">Réservations</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#34d399)"><i class="fas fa-door-open"></i></div>
            <div class="stat-value" style="--accent-from:#10b981;--accent-to:#34d399" id="stat-current">0</div>
            <div class="stat-label">En cours</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="fas fa-clock"></i></div>
            <div class="stat-value" style="--accent-from:#f59e0b;--accent-to:#fbbf24" id="stat-upcoming">0</div>
            <div class="stat-label">A venir</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#60a5fa)"><i class="fas fa-moon"></i></div>
            <div class="stat-value" style="--accent-from:#3b82f6;--accent-to:#60a5fa" id="stat-nights">0</div>
            <div class="stat-label">Nuitées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#ec4899,#f472b6)"><i class="fas fa-percent"></i></div>
            <div class="stat-value" style="--accent-from:#ec4899;--accent-to:#f472b6" id="stat-occupancy">0%</div>
            <div class="stat-label">Taux d'occupation</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#059669,#34d399)"><i class="fas fa-coins"></i></div>
            <div class="stat-value" style="--accent-from:#059669;--accent-to:#34d399" id="stat-revenue">0€</div>
            <div class="stat-label">Revenu estimé</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-value" style="--accent-from:#dc2626;--accent-to:#f87171" id="stat-gaps">0</div>
            <div class="stat-label">Trous détectés</div>
        </div>
    </div>

    <!-- Legend (clickable filter) -->
    <div class="legend-bar animate-in" style="animation-delay:0.1s">
        <span style="font-size:0.78rem;font-weight:600;color:var(--fc-muted);margin-right:0.5rem">Logements :</span>
        <?php foreach ($logementData as $ld): ?>
            <span class="legend-chip active" data-logement="<?= $ld['id'] ?>" style="--chip-color:<?= $ld['color'] ?>">
                <span class="chip-dot" style="background:<?= $ld['color'] ?>"></span>
                <?= htmlspecialchars($ld['nom']) ?>
            </span>
        <?php endforeach; ?>
        <span class="legend-chip" style="--chip-color:#94a3b8" id="toggleBlocked">
            <span class="chip-dot" style="background:#94a3b8"></span>
            Bloqués
        </span>
        <span class="price-toggle" style="margin-left:auto" id="togglePrices">
            <i class="fas fa-euro-sign"></i> Prix/nuit
        </span>
        <span class="sync-btn" id="syncBtn" title="Synchroniser tous les calendriers iCal">
            <i class="fas fa-sync-alt"></i><i class="fas fa-spinner fa-spin"></i> Sync iCal
        </span>
    </div>

    <!-- ════════ TIMELINE VIEW ════════ -->
    <div class="view-container active" id="view-timeline">
        <div class="timeline-wrap">
            <div class="timeline-toolbar">
                <div class="timeline-nav">
                    <button id="tl-prev" title="Précédent"><i class="fas fa-chevron-left"></i></button>
                    <button id="tl-today" title="Aujourd'hui" style="width:auto;padding:0 12px;font-size:0.8rem;font-weight:600">Aujourd'hui</button>
                    <button id="tl-next" title="Suivant"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="timeline-period" id="tl-period"></div>
                <div class="timeline-zoom">
                    <button data-days="14">2 sem</button>
                    <button data-days="30" class="active">1 mois</button>
                    <button data-days="60">2 mois</button>
                    <button data-days="90">3 mois</button>
                </div>
            </div>
            <div class="timeline-container" id="tl-container">
                <div class="timeline-grid" id="tl-grid"></div>
            </div>
        </div>
    </div>

    <!-- ════════ CALENDAR VIEW (FullCalendar) ════════ -->
    <div class="view-container" id="view-calendar">
        <div id="fc-calendar-wrap">
            <div id="fc-calendar"></div>
        </div>
    </div>

    <!-- ════════ LIST VIEW ════════ -->
    <div class="view-container" id="view-list">
        <div style="background:var(--fc-card);border-radius:var(--fc-radius);box-shadow:var(--fc-shadow);border:1px solid var(--fc-border);overflow:hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="list-table">
                    <thead>
                        <tr style="background:#fafbfc">
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none;padding:0.75rem 1rem">Logement</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Client</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Arrivée</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Départ</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Nuits</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Plateforme</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Prix/nuit</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Est. total</th>
                            <th style="font-size:0.78rem;font-weight:700;color:var(--fc-muted);border:none">Statut</th>
                        </tr>
                    </thead>
                    <tbody id="list-body"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Detail Slide-in Modal -->
<div class="modal-backdrop-custom" id="detailBackdrop"></div>
<div class="detail-modal" id="detailModal">
    <div class="detail-modal-header">
        <h3 id="detail-title">Détail</h3>
        <button class="detail-modal-close" id="detailClose"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="detail-modal-body" id="detail-body"></div>
</div>

<div class="cal-toast" id="calToast"></div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
(function() {
    // ═══════════════════════════════════════
    // DATA
    // ═══════════════════════════════════════
    var logements = <?= json_encode($logementData) ?>;
    var logementColors = <?= json_encode($logementColors) ?>;
    var allEvents = [];
    var pricing = {};      // logement_id => { prix_plancher, prix_standard, nuits_minimum, ... }
    var dailyPrices = {};  // logement_id => { date => price }
    var activeLogements = new Set(logements.map(function(l) { return l.id; }));
    var showBlocked = false;
    var showPrices = true;

    // Timeline state
    var tlDays = 30;
    var tlStart = new Date();
    tlStart.setDate(tlStart.getDate() - 3); // start 3 days before today

    var fcCalendar = null;

    // ═══════════════════════════════════════
    // FETCH DATA
    // ═══════════════════════════════════════
    function fetchEvents(start, end, callback) {
        var s = fmt(start), e = fmt(end);
        fetch('calendrier_api.php?start=' + s + '&end=' + e)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    allEvents = data.events;
                    if (data.pricing) pricing = data.pricing;
                    if (data.daily_prices) dailyPrices = data.daily_prices;
                    if (callback) callback();
                }
            })
            .catch(function(err) { console.error(err); });
    }

    function filteredEvents() {
        return allEvents.filter(function(e) {
            if (e.is_blocked && !showBlocked) return false;
            if (e.logement_id && !activeLogements.has(e.logement_id)) return false;
            return true;
        });
    }

    // ═══════════════════════════════════════
    // STATS
    // ═══════════════════════════════════════
    function updateStats() {
        var today = fmt(new Date());
        var events = filteredEvents().filter(function(e) { return !e.is_blocked; });
        var total = events.length, current = 0, upcoming = 0, nights = 0;
        events.forEach(function(e) {
            if (e.start <= today && e.end > today) current++;
            if (e.start > today) upcoming++;
            var d1 = new Date(e.start), d2 = new Date(e.end);
            if (!isNaN(d1) && !isNaN(d2)) nights += Math.max(0, Math.round((d2 - d1) / 86400000));
        });
        // Occupancy: nuitées / (nb logements actifs * nb jours affichés)
        var nbLogements = activeLogements.size || 1;
        var occ = nbLogements > 0 && tlDays > 0 ? Math.round((nights / (nbLogements * tlDays)) * 100) : 0;

        // Revenue estimation
        var revenue = 0;
        events.forEach(function(e) {
            if (e.logement_id && dailyPrices[e.logement_id]) {
                var dp = dailyPrices[e.logement_id];
                var cursor = new Date(e.start);
                var endD = new Date(e.end);
                while (cursor < endD) {
                    var ds = fmt(cursor);
                    if (dp[ds]) revenue += dp[ds];
                    else if (pricing[e.logement_id]) revenue += pricing[e.logement_id].prix_standard || 0;
                    cursor.setDate(cursor.getDate() + 1);
                }
            } else if (e.logement_id && pricing[e.logement_id]) {
                var n = e.num_nights || Math.round((new Date(e.end) - new Date(e.start)) / 86400000);
                revenue += n * (pricing[e.logement_id].prix_standard || 0);
            }
        });

        // Gap detection
        var totalGaps = detectGaps(filteredEvents()).length;

        animateValue('stat-total', total);
        animateValue('stat-current', current);
        animateValue('stat-upcoming', upcoming);
        animateValue('stat-nights', nights);
        document.getElementById('stat-occupancy').textContent = Math.min(occ, 100) + '%';
        document.getElementById('stat-revenue').textContent = formatRevenue(Math.round(revenue));
        document.getElementById('stat-gaps').textContent = totalGaps;
    }

    function formatRevenue(amount) {
        if (amount >= 1000) return Math.round(amount / 100) / 10 + 'k€';
        return amount + '€';
    }

    function detectGaps(events) {
        var gaps = [];
        var today = fmt(new Date());
        logements.forEach(function(logement) {
            if (!activeLogements.has(logement.id)) return;
            var minNights = (pricing[logement.id] || {}).nuits_minimum || 1;
            var logEvents = events.filter(function(e) {
                return e.logement_id === logement.id && !e.is_blocked;
            }).sort(function(a, b) { return a.start > b.start ? 1 : -1; });

            for (var i = 0; i < logEvents.length - 1; i++) {
                var gapStart = logEvents[i].end;
                var gapEnd = logEvents[i + 1].start;
                if (gapStart >= gapEnd) continue;
                var gapDays = Math.round((new Date(gapEnd) - new Date(gapStart)) / 86400000);
                // A gap that's too short for min nights and in the future
                if (gapDays > 0 && gapDays < minNights && gapStart >= today) {
                    gaps.push({
                        logement_id: logement.id,
                        start: gapStart,
                        end: gapEnd,
                        days: gapDays,
                        minNights: minNights
                    });
                }
            }
        });
        return gaps;
    }

    function animateValue(id, target) {
        var el = document.getElementById(id);
        var current = parseInt(el.textContent) || 0;
        if (current === target) return;
        var step = target > current ? 1 : -1;
        var diff = Math.abs(target - current);
        var delay = Math.max(20, Math.min(80, 400 / diff));
        var timer = setInterval(function() {
            current += step;
            el.textContent = current;
            if (current === target) clearInterval(timer);
        }, delay);
    }

    // ═══════════════════════════════════════
    // TIMELINE VIEW
    // ═══════════════════════════════════════
    function renderTimeline() {
        var container = document.getElementById('tl-container');
        var grid = document.getElementById('tl-grid');
        grid.innerHTML = '';

        var days = [];
        var d = new Date(tlStart);
        for (var i = 0; i < tlDays; i++) {
            days.push(new Date(d));
            d.setDate(d.getDate() + 1);
        }
        var endDate = new Date(d);

        // Period label
        var months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        var firstDay = days[0], lastDay = days[days.length - 1];
        var periodText = '';
        if (firstDay.getMonth() === lastDay.getMonth()) {
            periodText = months[firstDay.getMonth()] + ' ' + firstDay.getFullYear();
        } else if (firstDay.getFullYear() === lastDay.getFullYear()) {
            periodText = months[firstDay.getMonth()] + ' — ' + months[lastDay.getMonth()] + ' ' + firstDay.getFullYear();
        } else {
            periodText = months[firstDay.getMonth()] + ' ' + firstDay.getFullYear() + ' — ' + months[lastDay.getMonth()] + ' ' + lastDay.getFullYear();
        }
        document.getElementById('tl-period').textContent = periodText;

        var visibleLogements = logements.filter(function(l) { return activeLogements.has(l.id); });
        var cellW = Math.max(40, tlDays <= 30 ? 52 : (tlDays <= 60 ? 38 : 30));
        var labelW = 170;

        // Grid setup
        var cols = labelW + 'px ' + ('1fr '.repeat(tlDays));
        grid.style.gridTemplateColumns = cols;
        grid.style.width = (labelW + cellW * tlDays) + 'px';

        // Day names
        var dayNames = ['dim','lun','mar','mer','jeu','ven','sam'];
        var todayStr = fmt(new Date());

        // ─── Header row: label corner + day headers ───
        var corner = document.createElement('div');
        corner.className = 'timeline-label';
        corner.style.borderBottom = '2px solid var(--fc-border)';
        corner.style.zIndex = '5';
        corner.innerHTML = '<span style="font-size:0.75rem;font-weight:700;color:var(--fc-muted)">' + visibleLogements.length + ' logement' + (visibleLogements.length > 1 ? 's' : '') + '</span>';
        grid.appendChild(corner);

        days.forEach(function(day) {
            var dStr = fmt(day);
            var isToday = dStr === todayStr;
            var isWeekend = day.getDay() === 0 || day.getDay() === 6;
            var hdr = document.createElement('div');
            hdr.className = 'timeline-day-header' + (isToday ? ' today' : '') + (isWeekend ? ' weekend' : '');
            hdr.innerHTML = '<span class="day-name">' + dayNames[day.getDay()] + '</span><span class="day-num">' + day.getDate() + '</span>';
            grid.appendChild(hdr);
        });

        // ─── Logement rows ───
        var events = filteredEvents();

        visibleLogements.forEach(function(logement) {
            // Label with pricing info
            var label = document.createElement('div');
            label.className = 'timeline-label';
            var pr = pricing[logement.id] || {};
            var minN = pr.nuits_minimum || 1;
            var prixStd = pr.prix_standard || 0;
            var labelHtml = '<span class="timeline-label-dot" style="background:' + logement.color + '"></span>' +
                '<div class="timeline-label-meta">' +
                '<span class="timeline-label-text" title="' + logement.nom + '">' + logement.nom + '</span>' +
                '<span class="timeline-label-pricing">';
            if (prixStd > 0) labelHtml += '<span class="tag"><i class="fas fa-euro-sign"></i>' + Math.round(prixStd) + '</span>';
            if (minN > 1) labelHtml += '<span class="tag"><i class="fas fa-moon"></i>min ' + minN + '</span>';
            labelHtml += '</span></div>';
            label.innerHTML = labelHtml;
            grid.appendChild(label);

            // Cells
            var cellContainer = document.createElement('div');
            cellContainer.style.gridColumn = '2 / -1';
            cellContainer.style.position = 'relative';
            cellContainer.style.minHeight = '52px';
            cellContainer.style.borderBottom = '1px solid #f1f5f9';

            // Day grid lines + price labels
            var logDailyPrices = dailyPrices[logement.id] || {};
            days.forEach(function(day, idx) {
                var dStr = fmt(day);
                var isToday = dStr === todayStr;
                var isWeekend = day.getDay() === 0 || day.getDay() === 6;
                var line = document.createElement('div');
                line.style.cssText = 'position:absolute;top:0;bottom:0;left:' + (idx * cellW) + 'px;width:' + cellW + 'px;border-right:1px solid #f8f9fa;';
                if (isToday) line.style.background = 'rgba(99,102,241,0.04)';
                else if (isWeekend) line.style.background = 'rgba(251,191,36,0.03)';

                // Price label
                if (showPrices && logDailyPrices[dStr]) {
                    var priceEl = document.createElement('div');
                    priceEl.className = 'timeline-price';
                    priceEl.textContent = Math.round(logDailyPrices[dStr]) + '€';
                    line.appendChild(priceEl);
                }

                cellContainer.appendChild(line);
            });

            // Events for this logement
            var logEvents = events.filter(function(e) { return e.logement_id === logement.id; });
            logEvents.sort(function(a, b) { return a.start > b.start ? 1 : -1; });
            logEvents.forEach(function(evt) {
                var evStart = new Date(evt.start);
                var evEnd = new Date(evt.end);
                var startOffset = Math.max(0, Math.round((evStart - days[0]) / 86400000));
                var endOffset = Math.min(tlDays, Math.round((evEnd - days[0]) / 86400000));
                if (endOffset <= 0 || startOffset >= tlDays) return;
                startOffset = Math.max(0, startOffset);

                var bar = document.createElement('div');
                bar.className = 'timeline-event' + (evt.is_blocked ? ' blocked' : '');
                bar.style.left = (startOffset * cellW + 2) + 'px';
                bar.style.width = Math.max(cellW - 4, (endOffset - startOffset) * cellW - 4) + 'px';
                if (!evt.is_blocked) bar.style.background = 'linear-gradient(135deg, ' + logement.color + ', ' + logement.color + 'cc)';

                // Platform icon
                var platformHtml = '';
                if (!evt.is_blocked) {
                    var pf = (evt.plateforme || '').toLowerCase();
                    if (pf.includes('airbnb')) platformHtml = '<span class="platform-icon airbnb">A</span>';
                    else if (pf.includes('booking')) platformHtml = '<span class="platform-icon booking">B</span>';
                    else if (pf.includes('direct')) platformHtml = '<span class="platform-icon direct">D</span>';
                    else platformHtml = '<span class="platform-icon other"><i class="fas fa-user" style="font-size:0.5rem"></i></span>';
                } else {
                    platformHtml = '<i class="fas fa-lock timeline-event-icon"></i>';
                }

                var barWidth = Math.max(cellW - 4, (endOffset - startOffset) * cellW - 4);
                bar.innerHTML = platformHtml + (barWidth > 80 ? (evt.guest_name || evt.title || '') : '') +
                    '<span class="ev-checkin"></span><span class="ev-checkout"></span>';

                bar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showDetail(evt, logement);
                });

                cellContainer.appendChild(bar);
            });

            // Gap indicators
            var allGaps = detectGaps(events);
            var logGaps = allGaps.filter(function(g) { return g.logement_id === logement.id; });
            logGaps.forEach(function(gap) {
                var gStart = Math.max(0, Math.round((new Date(gap.start) - days[0]) / 86400000));
                var gEnd = Math.min(tlDays, Math.round((new Date(gap.end) - days[0]) / 86400000));
                if (gEnd <= 0 || gStart >= tlDays) return;
                var gapEl = document.createElement('div');
                gapEl.className = 'timeline-gap';
                gapEl.style.left = (gStart * cellW + 2) + 'px';
                gapEl.style.width = Math.max(cellW - 4, (gEnd - gStart) * cellW - 4) + 'px';
                gapEl.textContent = gap.days + 'j';
                gapEl.title = 'Trou de ' + gap.days + ' jour' + (gap.days > 1 ? 's' : '') + ' (min ' + gap.minNights + ' nuits)';
                cellContainer.appendChild(gapEl);
            });

            // Today marker
            var todayIdx = Math.round((new Date(todayStr) - days[0]) / 86400000);
            if (todayIdx >= 0 && todayIdx < tlDays) {
                var marker = document.createElement('div');
                marker.className = 'today-marker';
                marker.style.left = (todayIdx * cellW + cellW / 2) + 'px';
                cellContainer.appendChild(marker);
            }

            grid.appendChild(cellContainer);
        });

        // Scroll to today
        var todayOffset = Math.round((new Date(todayStr) - days[0]) / 86400000);
        if (todayOffset > 3) {
            container.scrollLeft = Math.max(0, (todayOffset - 2) * cellW);
        }
    }

    // ═══════════════════════════════════════
    // FULLCALENDAR VIEW
    // ═══════════════════════════════════════
    function initFullCalendar() {
        if (fcCalendar) return;
        var el = document.getElementById('fc-calendar');
        fcCalendar = new FullCalendar.Calendar(el, {
            locale: 'fr',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            height: 'auto',
            dayMaxEvents: 4,
            moreLinkText: function(n) { return '+' + n; },
            events: function(info, ok, fail) {
                var s = info.startStr.slice(0, 10), e = info.endStr.slice(0, 10);
                fetch('calendrier_api.php?start=' + s + '&end=' + e)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success) { fail(); return; }
                        var mapped = data.events.filter(function(ev) {
                            if (ev.is_blocked && !showBlocked) return false;
                            if (ev.logement_id && !activeLogements.has(ev.logement_id)) return false;
                            return true;
                        }).map(function(ev) {
                            return {
                                id: ev.id, title: (ev.logement_name ? ev.logement_name + ' — ' : '') + (ev.guest_name || ev.title),
                                start: ev.start, end: ev.end,
                                color: ev.is_blocked ? '#94a3b8' : (logementColors[ev.logement_id] || '#6366f1'),
                                extendedProps: ev
                            };
                        });
                        ok(mapped);
                    }).catch(fail);
            },
            eventClick: function(info) {
                var ev = info.event.extendedProps;
                var logement = logements.find(function(l) { return l.id === ev.logement_id; });
                showDetail(ev, logement);
            }
        });
        fcCalendar.render();
    }

    // ═══════════════════════════════════════
    // LIST VIEW
    // ═══════════════════════════════════════
    function renderList() {
        var tbody = document.getElementById('list-body');
        tbody.innerHTML = '';
        var events = filteredEvents().filter(function(e) { return !e.is_blocked; });
        events.sort(function(a, b) { return a.start > b.start ? 1 : -1; });
        var today = fmt(new Date());

        if (!events.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--fc-muted)">Aucune réservation</td></tr>';
            return;
        }

        events.forEach(function(ev) {
            var color = logementColors[ev.logement_id] || '#6366f1';
            var isCurrent = ev.start <= today && ev.end > today;
            var isPast = ev.end <= today;
            var tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            if (isPast) tr.style.opacity = '0.5';
            tr.innerHTML =
                '<td style="padding:0.75rem 1rem;border-color:#f1f5f9"><span style="display:inline-flex;align-items:center;gap:6px"><span style="width:8px;height:8px;border-radius:50%;background:' + color + '"></span><strong style="font-size:0.85rem">' + (ev.logement_name || '—') + '</strong></span></td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem;font-weight:600">' + (ev.guest_name || ev.title || '—') + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem">' + fmtFR(ev.start) + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem">' + fmtFR(ev.end) + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem;font-weight:700">' + (ev.num_nights || '—') + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem">' + (ev.plateforme || '—') + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem;font-weight:700;color:#10b981">' + (pricing[ev.logement_id] && pricing[ev.logement_id].prix_standard ? Math.round(pricing[ev.logement_id].prix_standard) + '€' : '—') + '</td>' +
                '<td style="border-color:#f1f5f9;font-size:0.85rem;font-weight:700;color:#6366f1">' + estimateRevenue(ev) + '</td>' +
                '<td style="border-color:#f1f5f9">' + (isCurrent ? '<span class="detail-badge" style="background:#dcfce7;color:#166534">En cours</span>' : (isPast ? '<span class="detail-badge" style="background:#f1f5f9;color:#64748b">Passée</span>' : '<span class="detail-badge" style="background:#fef3c7;color:#92400e">A venir</span>')) + '</td>';
            tr.addEventListener('click', function() {
                var logement = logements.find(function(l) { return l.id === ev.logement_id; });
                showDetail(ev, logement);
            });
            tbody.appendChild(tr);
        });
    }

    // ═══════════════════════════════════════
    // DETAIL MODAL
    // ═══════════════════════════════════════
    function showDetail(evt, logement) {
        var modal = document.getElementById('detailModal');
        var backdrop = document.getElementById('detailBackdrop');
        var body = document.getElementById('detail-body');
        var title = document.getElementById('detail-title');

        var color = logement ? logement.color : '#6366f1';
        title.textContent = evt.guest_name || evt.title || 'Réservation';

        var nights = evt.num_nights || 0;
        var today = fmt(new Date());
        var statusHtml = '';
        if (evt.is_blocked) {
            statusHtml = '<span class="detail-badge" style="background:#f1f5f9;color:#64748b"><i class="fas fa-lock" style="margin-right:4px"></i>Période bloquée</span>';
        } else if (evt.start <= today && evt.end > today) {
            statusHtml = '<span class="detail-badge" style="background:#dcfce7;color:#166534"><i class="fas fa-circle" style="margin-right:4px;font-size:0.5rem;vertical-align:middle"></i>En cours</span>';
        } else if (evt.end <= today) {
            statusHtml = '<span class="detail-badge" style="background:#f1f5f9;color:#64748b">Terminée</span>';
        } else {
            statusHtml = '<span class="detail-badge" style="background:#fef3c7;color:#92400e">A venir</span>';
        }

        var html = '<div class="detail-color-bar" style="background:linear-gradient(90deg,' + color + ',' + color + '88)"></div>';
        html += '<div style="margin-bottom:1rem">' + statusHtml + '</div>';

        if (logement) {
            html += detailRow('fa-home', '#6366f1', 'Logement', logement.nom);
        }
        html += detailRow('fa-calendar-day', '#10b981', 'Arrivée', fmtFR(evt.start));
        html += detailRow('fa-calendar-check', '#ef4444', 'Départ', fmtFR(evt.end));
        if (nights) html += detailRow('fa-moon', '#3b82f6', 'Nuitées', nights + ' nuit' + (nights > 1 ? 's' : ''));
        if (evt.guest_name) html += detailRow('fa-user', '#8b5cf6', 'Client', evt.guest_name);
        if (evt.plateforme) html += detailRow('fa-globe', '#f59e0b', 'Plateforme', evt.plateforme);
        if (evt.telephone) html += detailRow('fa-phone', '#14b8a6', 'Téléphone', '<a href="tel:' + evt.telephone + '">' + evt.telephone + '</a>');
        var voyageurs = (parseInt(evt.nb_adultes) || 0) + (parseInt(evt.nb_enfants) || 0);
        if (voyageurs > 0) {
            var detail = evt.nb_adultes + ' adulte' + (evt.nb_adultes > 1 ? 's' : '');
            if (evt.nb_enfants > 0) detail += ', ' + evt.nb_enfants + ' enfant' + (evt.nb_enfants > 1 ? 's' : '');
            html += detailRow('fa-users', '#ec4899', 'Voyageurs', detail);
        }
        // Pricing info
        if (evt.logement_id && pricing[evt.logement_id]) {
            var pr = pricing[evt.logement_id];
            if (pr.prix_standard > 0) {
                // Calculer prix moyen du séjour à partir des daily prices
                var dp = dailyPrices[evt.logement_id] || {};
                var totalPrice = 0, pricedDays = 0;
                var cursor = new Date(evt.start);
                var endD = new Date(evt.end);
                while (cursor < endD) {
                    var ds = fmt(cursor);
                    if (dp[ds]) { totalPrice += dp[ds]; pricedDays++; }
                    cursor.setDate(cursor.getDate() + 1);
                }
                var avgPrice = pricedDays > 0 ? Math.round(totalPrice / pricedDays) : Math.round(pr.prix_standard);
                var estTotal = pricedDays > 0 ? Math.round(totalPrice) : (nights * Math.round(pr.prix_standard));

                html += '<hr style="border-color:#f1f5f9;margin:0.5rem 0">';
                html += detailRow('fa-euro-sign', '#10b981', 'Prix/nuit moyen', avgPrice + '€/nuit');
                if (nights > 0) html += detailRow('fa-calculator', '#6366f1', 'Estimation total', '<strong>' + estTotal + '€</strong> (' + nights + ' nuit' + (nights > 1 ? 's' : '') + ')');
                html += detailRow('fa-tag', '#f59e0b', 'Fourchette', pr.prix_plancher + '€ — ' + pr.prix_standard + '€');
            }
            if (pr.nuits_minimum > 1) {
                var minOk = nights >= pr.nuits_minimum;
                html += detailRow('fa-moon', '#8b5cf6', 'Nuits minimum', pr.nuits_minimum + ' nuit' + (pr.nuits_minimum > 1 ? 's' : '') +
                    (minOk ? ' <span class="detail-badge" style="background:#dcfce7;color:#166534">OK</span>' : ' <span class="detail-badge" style="background:#fef2f2;color:#991b1b">Inférieur</span>'));
            }
        }
        if (evt.source) html += detailRow('fa-database', '#94a3b8', 'Source', evt.source === 'ical' ? 'Sync iCal' : 'Réservation directe');

        body.innerHTML = html;
        backdrop.style.display = 'block';
        requestAnimationFrame(function() {
            backdrop.classList.add('show');
            modal.classList.add('show');
        });
    }

    function detailRow(icon, iconColor, label, value) {
        return '<div class="detail-row">' +
            '<div class="detail-row-icon" style="background:' + iconColor + '15;color:' + iconColor + '"><i class="fas ' + icon + '"></i></div>' +
            '<div class="detail-row-content"><div class="detail-row-label">' + label + '</div><div class="detail-row-value">' + value + '</div></div></div>';
    }

    function closeDetail() {
        document.getElementById('detailModal').classList.remove('show');
        var backdrop = document.getElementById('detailBackdrop');
        backdrop.classList.remove('show');
        setTimeout(function() { backdrop.style.display = 'none'; }, 300);
    }
    document.getElementById('detailClose').addEventListener('click', closeDetail);
    document.getElementById('detailBackdrop').addEventListener('click', closeDetail);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDetail(); });

    // ═══════════════════════════════════════
    // VIEW SWITCHING
    // ═══════════════════════════════════════
    document.querySelectorAll('#viewToggle button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#viewToggle button').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var view = btn.dataset.view;
            document.querySelectorAll('.view-container').forEach(function(v) { v.classList.remove('active'); });
            document.getElementById('view-' + view).classList.add('active');
            if (view === 'calendar') { initFullCalendar(); }
            if (view === 'list') { renderList(); }
        });
    });

    // ═══════════════════════════════════════
    // LEGEND FILTER (toggle logements)
    // ═══════════════════════════════════════
    document.querySelectorAll('.legend-chip[data-logement]').forEach(function(chip) {
        chip.addEventListener('click', function() {
            var lid = parseInt(chip.dataset.logement);
            if (activeLogements.has(lid)) {
                activeLogements.delete(lid);
                chip.classList.remove('active');
            } else {
                activeLogements.add(lid);
                chip.classList.add('active');
            }
            refresh();
        });
    });

    document.getElementById('toggleBlocked').addEventListener('click', function() {
        showBlocked = !showBlocked;
        this.classList.toggle('active');
        refresh();
    });

    document.getElementById('togglePrices').addEventListener('click', function() {
        showPrices = !showPrices;
        this.classList.toggle('inactive');
        refresh();
    });

    // Sync iCal button
    document.getElementById('syncBtn').addEventListener('click', function() {
        var btn = this;
        if (btn.classList.contains('syncing')) return;
        btn.classList.add('syncing');
        fetch('ical_sync_api.php?action=sync_all_icals', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.classList.remove('syncing');
            if (data.success) {
                showToast('<i class="fas fa-check-circle" style="color:#10b981"></i> ' + (data.message || 'Synchronisation terminée'), 'success');
                loadAndRender();
            } else {
                showToast('<i class="fas fa-exclamation-circle" style="color:#ef4444"></i> ' + (data.error || 'Erreur'), 'error');
            }
        })
        .catch(function() {
            btn.classList.remove('syncing');
            showToast('<i class="fas fa-exclamation-circle" style="color:#ef4444"></i> Erreur réseau', 'error');
        });
    });

    function showToast(html, type) {
        var toast = document.getElementById('calToast');
        toast.innerHTML = html;
        toast.style.borderColor = type === 'success' ? '#bbf7d0' : '#fecaca';
        toast.classList.add('show');
        setTimeout(function() { toast.classList.remove('show'); }, 4000);
    }

    // ═══════════════════════════════════════
    // TIMELINE NAVIGATION
    // ═══════════════════════════════════════
    document.getElementById('tl-prev').addEventListener('click', function() {
        tlStart.setDate(tlStart.getDate() - Math.round(tlDays / 2));
        loadAndRender();
    });
    document.getElementById('tl-next').addEventListener('click', function() {
        tlStart.setDate(tlStart.getDate() + Math.round(tlDays / 2));
        loadAndRender();
    });
    document.getElementById('tl-today').addEventListener('click', function() {
        tlStart = new Date();
        tlStart.setDate(tlStart.getDate() - 3);
        loadAndRender();
    });
    document.querySelectorAll('.timeline-zoom button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.timeline-zoom button').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            tlDays = parseInt(btn.dataset.days);
            loadAndRender();
        });
    });

    // ═══════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════
    function fmt(d) {
        if (typeof d === 'string') return d.slice(0, 10);
        var mm = d.getMonth() + 1, dd = d.getDate();
        return d.getFullYear() + '-' + (mm < 10 ? '0' : '') + mm + '-' + (dd < 10 ? '0' : '') + dd;
    }

    function estimateRevenue(ev) {
        if (!ev.logement_id) return '—';
        var dp = dailyPrices[ev.logement_id] || {};
        var pr = pricing[ev.logement_id] || {};
        var total = 0, counted = false;
        var cursor = new Date(ev.start);
        var endD = new Date(ev.end);
        while (cursor < endD) {
            var ds = fmt(cursor);
            if (dp[ds]) { total += dp[ds]; counted = true; }
            else if (pr.prix_standard) { total += pr.prix_standard; counted = true; }
            cursor.setDate(cursor.getDate() + 1);
        }
        return counted ? Math.round(total) + '€' : '—';
    }

    function fmtFR(d) {
        if (!d) return '—';
        var parts = d.slice(0, 10).split('-');
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function refresh() {
        renderTimeline();
        updateStats();
        if (fcCalendar) fcCalendar.refetchEvents();
        var activeView = document.querySelector('.view-container.active');
        if (activeView && activeView.id === 'view-list') renderList();
    }

    function loadAndRender() {
        var end = new Date(tlStart);
        end.setDate(end.getDate() + tlDays + 5);
        fetchEvents(tlStart, end, function() {
            renderTimeline();
            updateStats();
        });
    }

    // ═══════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════
    loadAndRender();

})();
</script>
</body>
</html>
