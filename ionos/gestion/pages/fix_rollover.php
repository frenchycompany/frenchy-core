<?php
/**
 * Script de réparation : remet les interventions à leur date d'origine
 * après le rollover qui les a toutes déplacées au 25/03/2026.
 *
 * Lit la note "[Reporté du XX/XX]" pour retrouver la date d'origine.
 */
include '../config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Réparation des interventions déplacées par le rollover</h2>";

try {
    // Trouver toutes les interventions avec "[Reporté du"
    $stmt = $conn->query("SELECT id, date, note FROM planning WHERE note LIKE '%[Reporté du %'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<p>Aucune intervention à réparer.</p>";
        exit;
    }

    echo "<p><strong>" . count($rows) . " intervention(s) à réparer.</strong></p>";

    $fixed = 0;
    $errors = [];
    $currentYear = date('Y');

    foreach ($rows as $row) {
        // Extraire la date d'origine depuis "[Reporté du DD/MM]"
        if (preg_match('/\[Reporté du (\d{2})\/(\d{2})\]/', $row['note'], $m)) {
            $day = $m[1];
            $month = $m[2];

            // Reconstruire la date (même année que la date actuelle de l'intervention)
            $originalDate = $currentYear . '-' . $month . '-' . $day;

            // Nettoyer la note (retirer le tag "[Reporté du XX/XX]")
            $cleanNote = trim(preg_replace('/\s*\[Reporté du \d{2}\/\d{2}\]/', '', $row['note']));

            // Remettre à la date d'origine
            $update = $conn->prepare("UPDATE planning SET date = ?, note = ? WHERE id = ?");
            $update->execute([$originalDate, $cleanNote, $row['id']]);

            echo "<p>ID #{$row['id']} : {$row['date']} → {$originalDate}</p>";
            $fixed++;
        } else {
            $errors[] = "ID #{$row['id']} : pattern non trouvé dans la note";
        }
    }

    echo "<hr>";
    echo "<p><strong>{$fixed} intervention(s) réparée(s).</strong></p>";
    if ($errors) {
        echo "<p>Erreurs :</p><ul>";
        foreach ($errors as $e) echo "<li>{$e}</li>";
        echo "</ul>";
    }
    echo "<p><a href='planning.php'>Retour au planning</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
