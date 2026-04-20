<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/invoice_generator.php';

$ref = $_GET['ref'] ?? '';
$email = $_GET['email'] ?? '';

if (!$ref || !$email) {
    http_response_code(400);
    die('Paramètres manquants (ref et email requis)');
}

try {
    $pdo = getBookingPdo();

    $stmt = $pdo->prepare("SELECT * FROM direct_bookings WHERE booking_ref = ? AND guest_email = ?");
    $stmt->execute([$ref, $email]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        die('Réservation introuvable');
    }

    if ($booking['invoice_pdf_path'] && file_exists($booking['invoice_pdf_path'])) {
        $filepath = $booking['invoice_pdf_path'];
    } else {
        $generator = new InvoiceGenerator($pdo);
        $filepath = $generator->generate($booking);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="facture_' . $booking['invoice_number'] . '.pdf"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);

} catch (Exception $e) {
    http_response_code(500);
    die('Erreur lors de la génération de la facture');
}
