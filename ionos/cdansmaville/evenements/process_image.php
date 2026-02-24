<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');
header('Content-Type: application/json');

require 'db/connection.php'; // Inclure la connexion à la base de données

$apiKey = 'AIzaSyBBJ9jLaQe9_c7mf_3EiiZ5-ql1i6fBl80';

if (isset($_FILES['image'])) {
    try {
        // 1. Sauvegarder l'image sur le serveur
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $imageName = uniqid() . '_' . $_FILES['image']['name'];
        $imagePath = $uploadDir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);

        // 2. Encoder l'image en base64
        $imageData = file_get_contents($imagePath);
        $imageBase64 = base64_encode($imageData);

        // 3. Requête Vision API
        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey;

        $requestBody = [
            'requests' => [
                [
                    'image' => ['content' => $imageBase64],
                    'features' => [['type' => 'TEXT_DETECTION']],
                ],
            ],
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($requestBody),
            ],
        ];

        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Failed to contact Vision API.');
        }

        $responseData = json_decode($response, true);

        // 4. Extraire le texte et insérer dans la base de données
        if (isset($responseData['responses'][0]['textAnnotations'])) {
            $extractedText = $responseData['responses'][0]['textAnnotations'][0]['description'];

            $stmt = $conn->prepare("INSERT INTO image_process (image_content, image_source) VALUES (?, ?)");
            $stmt->execute([$extractedText, $imagePath]);

            $imageId = $conn->lastInsertId(); // Récupérer l'ID de l'insertion

            echo json_encode([
                'message' => 'Image processed successfully.',
                'extractedText' => $extractedText,
                'imageId' => $imageId // Retourner l'imageid
            ]);
        } else {
            throw new Exception('No text found in the image.');
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No image received.']);
}
