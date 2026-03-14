<?php
// Endpoint AJAX pour l'envoi de SMS depuis le modal
ini_set('display_errors', 0); // Ne pas afficher les erreurs dans la réponse JSON

require_once '../includes/db.php';

// Définir le header JSON
header('Content-Type: application/json');

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Récupérer les données du formulaire
$receiver = trim($_POST['receiver'] ?? '');
$message = trim($_POST['message'] ?? '');
$modem = trim($_POST['modem'] ?? '');

// Validation des champs
if (empty($receiver)) {
    echo json_encode([
        'success' => false,
        'message' => 'Le numéro du destinataire est obligatoire'
    ]);
    exit;
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Le message est obligatoire'
    ]);
    exit;
}

if (empty($modem)) {
    echo json_encode([
        'success' => false,
        'message' => 'Le modem est obligatoire'
    ]);
    exit;
}

// Validation du format du numéro de téléphone
$phoneRegex = '/^(\+33|0)[1-9]\d{8}$/';
$cleanReceiver = preg_replace('/\s/', '', $receiver);

if (!preg_match($phoneRegex, $cleanReceiver)) {
    echo json_encode([
        'success' => false,
        'message' => 'Format de numéro invalide. Utilisez 0612345678 ou +33612345678'
    ]);
    exit;
}

// Vérifier la longueur du message
if (strlen($message) > 160) {
    echo json_encode([
        'success' => false,
        'message' => 'Le message ne peut pas dépasser 160 caractères'
    ]);
    exit;
}

// Vérifier que la connexion PDO existe
if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

// Insérer le SMS dans la file d'attente
try {
    $stmt = $pdo->prepare("
        INSERT INTO sms_outbox (receiver, message, modem, status, created_at)
        VALUES (:receiver, :message, :modem, 'pending', NOW())
    ");

    $stmt->execute([
        ':receiver' => $cleanReceiver,
        ':message' => $message,
        ':modem' => $modem
    ]);

    echo json_encode([
        'success' => true,
        'message' => '✓ SMS mis en file d\'attente avec succès',
        'sms_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    // Logger l'erreur (en production, utiliser un système de logs approprié)
    error_log("Erreur SQL dans send_sms_ajax.php: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage()
    ]);
}
?>
