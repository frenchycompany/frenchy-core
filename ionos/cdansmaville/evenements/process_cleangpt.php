<?php
ini_set('display_errors', 1);
header('Content-Type: application/json');
require 'db/connection.php'; // Connexion à la base de données

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageId = $_POST['imageid'] ?? null;

    if (!$imageId) {
        echo json_encode(['success' => false, 'message' => 'ID de l\'image non fourni.']);
        exit;
    }

    try {
        // Récupérer le texte brut depuis la table `image_process`
        $stmt = $conn->prepare("SELECT image_content FROM image_process WHERE imageid = ?");
        $stmt->execute([$imageId]);
        $imageData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$imageData) {
            echo json_encode(['success' => false, 'message' => 'Image non trouvée.']);
            exit;
        }

        $textToClean = $imageData['image_content'];

        // Appel à GPT pour nettoyer le texte
        $cleaningPrompt = "Le texte suivant provient d'un OCR et peut contenir des erreurs typiques. Nettoyez-le et corrigez les fautes d'orthographe, simplifiez la structure, et préparez-le pour une utilisation future.\n\nVoici le texte brut :\n$textToClean";

        $cleaningResponse = file_get_contents("https://api.openai.com/v1/chat/completions", false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer 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\r\n",
                'content' => json_encode([
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => $cleaningPrompt]],
                    'max_tokens' => 1000
                ]),
            ],
        ]));

        $cleaningResult = json_decode($cleaningResponse, true);

        if (!isset($cleaningResult['choices'][0]['message']['content'])) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du nettoyage du texte par GPT.']);
            exit;
        }

        $cleanedText = $cleaningResult['choices'][0]['message']['content'];

        // Mettre à jour le texte nettoyé dans la table `image_process`
        $updateStmt = $conn->prepare("UPDATE image_process SET image_content = ?, process_chatgpt = 'cleaned' WHERE imageid = ?");
        $updateStmt->execute([$cleanedText, $imageId]);

        echo json_encode(['success' => true, 'message' => 'Texte nettoyé et mis à jour avec succès.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du nettoyage.', 'debug' => $e->getMessage()]);
    }
}
?>
