<?php
// web/pages/import_ics.php

// Afficher toutes les erreurs pour debug

// 1) Autoload Composer (Sabre/VObject)
require __DIR__ . '/../../vendor/autoload.php';
// 2) Connexion BDD
require __DIR__ . '/../includes/db.php';

use Sabre\VObject\Reader;

// 3) URL du flux ICS
$icsUrl = 'https://app.superhote.com/export-ics/MpQTb3gYD3';

// 4) Charger et parser le ICS
$icsContent = @file_get_contents($icsUrl);
if ($icsContent === false) {
    die("❌ Impossible de récupérer le flux ICS\n");
}
$vcalendar = Reader::read($icsContent);
$events    = $vcalendar->select('VEVENT');

echo "Total d'événements : " . count($events) . "\n\n";

$imported = 0;
$updated  = 0;

foreach ($events as $vevent) {
    $summary     = trim((string)$vevent->SUMMARY);
    $description = (string)$vevent->DESCRIPTION;

    // Ignorer les "Blocked dates"
    if (stripos($summary, 'Blocked dates') === 0) {
        continue;
    }

    // Format attendu : "Prénom - Airbnb.com - 5519509"
    if (!preg_match('/^(?<prenom>[^-]+)\s*-\s*Airbnb\.com\s*-\s*(?<ref>\d+)$/i', $summary, $m)) {
        echo "Ignoré (format inattendu) : “$summary”\n";
        continue;
    }

    $prenom     = trim($m['prenom']);
    $nom        = '';  // nom non fourni dans SUMMARY
    $reference  = trim($m['ref']);
    $dateIn     = $vevent->DTSTART->getDateTime()->format('Y-m-d');
    $dateOut    = $vevent->DTEND->getDateTime()->format('Y-m-d');
    $plateforme = 'Airbnb.com';

    // Extraire téléphone depuis DESCRIPTION
    $telephone = '';
    if (preg_match('/(\+?\d[\d\-\s\(\)]{7,}\d)/', $description, $pm)) {
        $telephone = preg_replace('/[^\d+]/', '', $pm[1]);
    }

    // Extraire ville depuis DESCRIPTION (ligne "City: Ville")
    $ville = '';
    if (preg_match('/\bCity\s*[:\-]\s*(?<city>[^\r\n]+)/i', $description, $cm)) {
        $ville = trim($cm['city']);
    }

    // S'assurer que nom, telephone, ville sont au moins des chaînes
    $nom       = $nom ?: '';
    $telephone = $telephone ?: '';
    $ville     = $ville ?: '';

    // Vérifier existence de la résa
    $chk = $conn->prepare("SELECT id FROM reservation WHERE reference = ?");
    $chk->bind_param('s', $reference);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
        // Mise à jour
        $chk->bind_result($resId);
        $chk->fetch();
        $upd = $conn->prepare("
            UPDATE reservation
               SET date_arrivee = ?,
                   date_depart   = ?,
                   nom           = ?,
                   plateforme    = ?,
                   telephone     = ?,
                   ville         = ?
             WHERE id = ?
        ");
        $upd->bind_param(
            'ssssssi',
            $dateIn,
            $dateOut,
            $nom,
            $plateforme,
            $telephone,
            $ville,
            $resId
        );
        $upd->execute();
        $updated++;
        echo "↺ Mise à jour ref#$reference (ID $resId) – tel:$telephone – ville:$ville\n";
    } else {
        // Insertion
        $ins = $conn->prepare("
            INSERT INTO reservation (
                reference,
                date_reservation,
                date_arrivee,
                date_depart,
                prenom,
                nom,
                plateforme,
                telephone,
                ville
            ) VALUES (
                ?, NOW(), ?, ?, ?, ?, ?, ?, ?
            )
        ");
        $ins->bind_param(
            'ssssssss',
            $reference,
            $dateIn,
            $dateOut,
            $prenom,
            $nom,
            $plateforme,
            $telephone,
            $ville
        );
        $ins->execute();
        $newId = $ins->insert_id;
        $imported++;
        echo "✓ Inséré ref#$reference (ID $newId) – $prenom – tel:$telephone – ville:$ville\n";
    }
}

echo "\n=== Résumé ===\n";
echo "Importées : $imported\n";
echo "Mises à jour : $updated\n";
