<?php
/**
 * API JSON pour le calendrier des réservations
 * Combine : table reservation (RPi) + ical_reservations (RPi)
 * Enrichit avec les noms de logements (VPS)
 */
header('Content-Type: application/json; charset=utf-8');

include '../config.php';
require_once __DIR__ . '/../includes/rpi_db.php';

// Vérifier la session
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['id_intervenant'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$start       = $_GET['start'] ?? date('Y-m-01');
$end         = $_GET['end']   ?? date('Y-m-t', strtotime('+3 months'));
$logement_id = isset($_GET['logement_id']) && $_GET['logement_id'] !== '' ? (int) $_GET['logement_id'] : null;

try {
    // ── 1. Noms de logements (VPS) ──
    $logementNames = [];
    $rows = $conn->query("SELECT id, nom_du_logement FROM liste_logements")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $logementNames[$r['id']] = $r['nom_du_logement'];
    }

    $pdoRpi = getRpiPdo();
    $events = [];

    // ── 2. Table reservation (réservations manuelles / SMS) ──
    $sql = "
        SELECT id, reference, logement_id, date_arrivee, date_depart,
               prenom, nom, plateforme, telephone, email,
               nb_adultes, nb_enfants, nb_bebes, statut,
               DATEDIFF(date_depart, date_arrivee) AS num_nights
        FROM reservation
        WHERE date_arrivee <= :end AND date_depart >= :start
    ";
    $params = [':start' => $start, ':end' => $end];

    if ($logement_id) {
        $sql .= " AND logement_id = :lid";
        $params[':lid'] = $logement_id;
    }
    $sql .= " ORDER BY date_arrivee";

    $stmt = $pdoRpi->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservations as $r) {
        $guestName = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''));
        $events[] = [
            'id'            => 'resa_' . $r['id'],
            'title'         => $guestName ?: ($r['reference'] ?: 'Réservation'),
            'start'         => $r['date_arrivee'],
            'end'           => $r['date_depart'],
            'logement_id'   => (int) $r['logement_id'],
            'logement_name' => $logementNames[$r['logement_id']] ?? '',
            'guest_name'    => $guestName,
            'plateforme'    => $r['plateforme'] ?? '',
            'telephone'     => $r['telephone'] ?? '',
            'nb_adultes'    => (int) ($r['nb_adultes'] ?? 0),
            'nb_enfants'    => (int) ($r['nb_enfants'] ?? 0),
            'num_nights'    => (int) ($r['num_nights'] ?? 0),
            'statut'        => $r['statut'] ?? '',
            'is_blocked'    => false,
            'source'        => 'reservation',
        ];
    }

    // ── 3. Table ical_reservations (sync iCal) ──
    // On a besoin du mapping connection_id → logement_id
    // via travel_account_connections → travel_listings → logement_id
    $icalSql = "
        SELECT ir.id, ir.summary, ir.start_date, ir.end_date,
               ir.guest_name, ir.guest_email, ir.guest_phone,
               ir.status, ir.is_blocked, ir.num_nights,
               ir.connection_id,
               tl.external_listing_id,
               tac.account_name
        FROM ical_reservations ir
        LEFT JOIN travel_account_connections tac ON ir.connection_id = tac.id
        LEFT JOIN travel_listings tl ON tac.id = tl.connection_id
        WHERE ir.start_date <= :end AND ir.end_date >= :start
    ";
    $icalParams = [':start' => $start, ':end' => $end];

    try {
        $stmt = $pdoRpi->prepare($icalSql);
        $stmt->execute($icalParams);
        $icalReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tenter de mapper les réservations iCal aux logements via ics_url dans liste_logements
        $icsToLogement = [];
        try {
            $icsRows = $conn->query("SELECT id, ics_url, ics_url_2 FROM liste_logements WHERE ics_url IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
            // On ne peut pas faire un mapping exact sans l'URL, mais on peut utiliser le connection_id
        } catch (PDOException $e) { /* ignore */ }

        foreach ($icalReservations as $ir) {
            // Filtrer par logement si nécessaire
            // Pour les ical_reservations, le mapping logement est plus complexe
            // On les affiche tous sauf si un filtre logement est actif et qu'on ne peut pas matcher
            $mappedLogementId = null;

            // Essayer de matcher via account_name contenant le nom du logement
            if (!empty($ir['account_name'])) {
                foreach ($logementNames as $lid => $lname) {
                    if (stripos($ir['account_name'], $lname) !== false || stripos($lname, $ir['account_name']) !== false) {
                        $mappedLogementId = $lid;
                        break;
                    }
                }
            }

            if ($logement_id && $mappedLogementId !== $logement_id) {
                continue;
            }

            // Éviter les doublons avec les réservations manuelles
            // (vérifier si même dates + même logement)
            $isDuplicate = false;
            foreach ($events as $existing) {
                if ($existing['start'] === $ir['start_date'] &&
                    $existing['end'] === $ir['end_date'] &&
                    $existing['logement_id'] === $mappedLogementId) {
                    $isDuplicate = true;
                    break;
                }
            }
            if ($isDuplicate) continue;

            $events[] = [
                'id'            => 'ical_' . $ir['id'],
                'title'         => $ir['guest_name'] ?: ($ir['summary'] ?: 'Réservation iCal'),
                'start'         => $ir['start_date'],
                'end'           => $ir['end_date'],
                'logement_id'   => $mappedLogementId,
                'logement_name' => $mappedLogementId ? ($logementNames[$mappedLogementId] ?? '') : ($ir['account_name'] ?? ''),
                'guest_name'    => $ir['guest_name'] ?? '',
                'plateforme'    => $ir['account_name'] ?? 'iCal',
                'telephone'     => $ir['guest_phone'] ?? '',
                'nb_adultes'    => 0,
                'nb_enfants'    => 0,
                'num_nights'    => (int) ($ir['num_nights'] ?? 0),
                'statut'        => $ir['status'] ?? '',
                'is_blocked'    => (bool) ($ir['is_blocked'] ?? false),
                'source'        => 'ical',
            ];
        }
    } catch (PDOException $e) {
        // ical_reservations table might not exist yet — that's OK
        error_log('calendrier_api ical: ' . $e->getMessage());
    }

    // Trier par date
    usort($events, function($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    echo json_encode([
        'success' => true,
        'events'  => $events,
        'count'   => count($events),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur base de données : ' . $e->getMessage(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
