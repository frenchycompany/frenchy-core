<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db/connection.php';
require_once '../lib/fpdf/fpdf.php';

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function txt($str) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
}

if (!isset($_POST['objets']) || !is_array($_POST['objets']) || empty($_POST['objets'])) {
    exit("Aucun objet sélectionné.");
}

$objets_id = array_map('intval', $_POST['objets']);
$placeholders = implode(',', array_fill(0, count($objets_id), '?'));

$stmt = $conn->prepare("
    SELECT o.*, l.nom_du_logement 
    FROM inventaire_objets o
    JOIN liste_logements l ON o.logement_id = l.id
    WHERE o.id IN ($placeholders)
");
$stmt->execute($objets_id);
$etiquettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF init
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);
$pdf->SetFont('Arial', '', 10);

// Dimensions
$etiquette_largeur = 105;
$etiquette_hauteur = 70;
$logo_taille = 22;
$qr_taille = 25;

$logo_y = 8;
$text_y = 35;
$qr_y = 54;

$logo_path = __DIR__ . '/../images/logo.png';

$col = 0;
$row = 0;

foreach ($etiquettes as $obj) {
    if ($col === 0 && $row === 0) $pdf->AddPage();

    $x = $col * $etiquette_largeur;
    $y = $row * $etiquette_hauteur;

// Cadre (facultatif)
$pdf->SetDrawColor(200, 200, 200);
$pdf->Rect($x, $y, $etiquette_largeur, $etiquette_hauteur);

// LOGO à gauche
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, $x + 20, $y + 20, 25);
}

// QR CODE à droite
$qr_path = __DIR__ . '/../uploads/qrcodes/' . basename($obj['qr_code_path']);
if (file_exists($qr_path)) {
    $pdf->Image($qr_path, $x + 70, $y + 15, 30);
} else {
    $pdf->SetXY($x + 70, $y + 48);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(255, 0, 0);
    $pdf->Cell(0, 5, txt("QR manquant"), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
}

// TEXTE centré
$pdf->SetXY($x + 5, $y + 45);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 6, txt($obj['nom_objet']), 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($x + 5);
$pdf->Cell(95, 5, txt($obj['nom_du_logement']), 0, 1, 'C');

$pdf->SetFont('Arial', '', 15);
$pdf->SetX($x + 5);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(95, 5, txt("Propriété de Frenchy Conciergerie"), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
    // Suivant
    $col++;
    if ($col >= 2) {
        $col = 0;
        $row++;
        if ($row >= 4) $row = 0;
    }
}

$pdf->Output('I', 'etiquettes_frenchy_final.pdf');
exit;
