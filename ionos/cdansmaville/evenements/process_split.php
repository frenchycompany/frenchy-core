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
        // Récupérer le texte depuis la table image_process
        $stmt = $conn->prepare("SELECT image_content FROM image_process WHERE imageid = ?");
        $stmt->execute([$imageId]);
        $imageData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$imageData) {
            echo json_encode(['success' => false, 'message' => 'Image non trouvée.']);
            exit;
        }

        $textToSplit = $imageData['image_content'];

        // Appel à GPT pour diviser le texte en sections
        $response = file_get_contents("https://api.openai.com/v1/chat/completions", false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer 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\r\n",
                'content' => json_encode([
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => "Voici un texte complexe contenant plusieurs événements culturels ou associatifs. Sépare chaque événement distinct dans des sections claires et renvoie chaque section comme un texte autonome séparé par `---`. Voici le texte : $textToSplit"
                        ]
                    ],
                    'max_tokens' => 3000
                ]),
            ],
        ]));

        $result = json_decode($response, true);

        // Vérifier si GPT a bien renvoyé une réponse
        if (!isset($result['choices'][0]['message']['content'])) {
            echo json_encode(['success' => false, 'message' => 'Aucune réponse valide de GPT.']);
            exit;
        }

        // Récupérer le contenu généré
        $splitContent = $result['choices'][0]['message']['content'];

        // Diviser le texte en sections basées sur le séparateur `---`
        $sections = explode('---', $splitContent);
        $newRecords = 0;

        foreach ($sections as $section) {
            $section = trim($section);
            if (!empty($section)) {
                // Insérer chaque section comme nouvelle ligne dans la table image_process
                $stmt = $conn->prepare("INSERT INTO image_process (image_content, process_chatgpt) VALUES (?, 'pending')");
                $stmt->execute([$section]);
                $newRecords++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "$newRecords sections ajoutées comme nouvelles entrées dans la table."
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du traitement.', 'debug' => $e->getMessage()]);
    }
}
