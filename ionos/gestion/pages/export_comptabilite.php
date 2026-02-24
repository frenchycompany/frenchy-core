<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periode = $_POST['periode'];

    // Récupération des données pour la période
    $stmt = $conn->prepare("
        SELECT * FROM comptabilite
        WHERE DATE_FORMAT(date_comptabilisation, '%Y-%m') = ?
    ");
    $stmt->execute([$periode]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$resultats) {
        echo "Aucune donnée trouvée pour la période sélectionnée.";
        exit;
    }

    // Génération du fichier CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=comptabilite_' . $periode . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, array_keys($resultats[0])); // Entêtes

    foreach ($resultats as $ligne) {
        fputcsv($output, $ligne);
    }

    fclose($output);
    exit;
}
?>
