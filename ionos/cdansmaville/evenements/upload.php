<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db/connection.php'; // Database connection

use Google\Cloud\Vision\VisionClient;


// Replace with your actual API Key. Protect it as much as possible.  A service account is much more secure.
$apiKey = 'AIzaSyAfKPSV_rFROLxFtWHyV-bLDNoyie2z52E';


// Get the base64 image data
$data = json_decode(file_get_contents('php://input'), true);

// Error handling if no 'image' key is present or JSON decoding fails
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['image'])) {
    echo json_encode(['error' => 'Invalid request.']); 
    exit;
}

$imageBase64 = $data['image'];

try {

    $vision = new VisionClient(['key' => $apiKey]);  // Initialize with API key
    $image = $vision->image(base64_decode($imageBase64), ['TEXT_DETECTION']);
    $annotation = $vision->annotate($image);

    $extractedText = "";
    foreach ($annotation->textAnnotations() as $text) { 
        $extractedText .= $text->description() . "\n";
    }



    try {
        $stmt = $conn->prepare("INSERT INTO events (description, image_source) VALUES (?, ?)");
        $stmt->execute([$extractedText, $imageBase64]);
        echo json_encode(['extractedText' => $extractedText]); // Successful response

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]); 
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Vision API error: ' . $e->getMessage()]); 
}

?>
