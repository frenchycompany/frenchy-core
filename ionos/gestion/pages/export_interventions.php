<?php
include '../config.php';

// Récupération des filtres via GET
$mois = filter_input(INPUT_GET, 'mois', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
$annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
$intervenant = filter_input(INPUT_GET, 'intervenant', FILTER_VALIDATE_INT);

$conditions = [];
$params = [];

if ($mois) {
    $conditions[] = "MONTH(date_comptabilisation) = :mois";
    $params[':mois'] = $mois;
}
if ($annee) {
    $conditions[] = "YEAR(date_comptabilisation) = :annee";
    $params[':annee'] = $annee;
}
if ($intervenant) {
    $conditions[] = "intervenant_id = :intervenant";
    $params[':intervenant'] = $intervenant;
}
$whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

$query = "
    SELECT date_comptabilisation, type, montant, description,
           (SELECT nom FROM intervenant WHERE id = comptabilite.intervenant_id) AS intervenant_nom,
           (SELECT nom_du_logement FROM liste_logements WHERE id = source_id) AS nom_du_logement
    FROM comptabilite
    " . $whereClause . "
    ORDER BY date_comptabilisation ASC
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=interventions_export.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Type', 'Montant', 'Description', 'Intervenant', 'Logement']);
foreach ($data as $row) {
    fputcsv($output, [
        $row['date_comptabilisation'] ?? '',
        $row['type'] ?? '',
        $row['montant'] ?? '',
        $row['description'] ?? '',
        $row['intervenant_nom'] ?? '',
        $row['nom_du_logement'] ?? ''
    ]);
}
fclose($output);
exit;
?>
