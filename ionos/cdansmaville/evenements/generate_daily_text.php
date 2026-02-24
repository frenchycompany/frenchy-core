<?php
ini_set('display_errors', 1);
header('Content-Type: application/json');
require 'db/connection.php'; // Connexion à la base de données

$date = date('Y-m-d'); // Date du jour

try {
    // Vérifier si un résumé existe déjà pour aujourd'hui
    $stmt = $conn->prepare("SELECT summary FROM daily_summary WHERE date = ?");
    $stmt->execute([$date]);
    $existingSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSummary) {
        echo json_encode(['success' => true, 'summary' => $existingSummary['summary']]);
        exit;
    }

    // Récupérer les événements du jour ou en cours
    $stmt = $conn->prepare("
        SELECT titre, date_debut, date_fin, heure_debut, heure_fin, nom_lieu, ville
        FROM structured_events
        WHERE ? BETWEEN date_debut AND IFNULL(date_fin, date_debut)
    ");
    $stmt->execute([$date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($events) === 0) {
        echo json_encode(['success' => false, 'message' => 'Aucun événement pour aujourd\'hui.']);
        exit;
    }

    // Préparer les données pour GPT
    $eventList = "";
    foreach ($events as $event) {
        $eventList .= "- {$event['titre']} " .
            (!empty($event['heure_debut']) ? "à {$event['heure_debut']}" : "") .
            " au {$event['nom_lieu']} ({$event['ville']})" .
            (isset($event['date_fin']) && $event['date_fin'] !== $event['date_debut'] ? " (En cours jusqu'au {$event['date_fin']})" : "") .
            "\n";
    }

    $prompt = "Génère un résumé convivial et prêt à être partagé par SMS ou WhatsApp pour les événements suivants du jour (ajoute des smileys) :\n\n" . $eventList;

    // Appel à GPT (remplacez par votre API)
    $response = file_get_contents("https://api.openai.com/v1/chat/completions", false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer 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\r\n",
            'content' => json_encode([
                'model' => 'gpt-4o',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 300
            ]),
        ],
    ]));

    $result = json_decode($response, true);
    $generatedText = $result['choices'][0]['message']['content'] ?? 'Erreur de génération du texte.';

    // Stocker le texte généré
    $insertStmt = $conn->prepare("INSERT INTO daily_summary (date, summary) VALUES (?, ?)");
    $insertStmt->execute([$date, $generatedText]);

    echo json_encode(['success' => true, 'summary' => $generatedText]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
