<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

// Chemin où sauvegarder les fichiers uploadés
$uploadDir = 'uploads/';

// Assurez-vous que le dossier d'upload existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

try {
    // Vérifiez si le fichier a été envoyé correctement
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur lors de l\'envoi du fichier.');
    }

    // Récupérer les informations sur le fichier
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $fileSize = $_FILES['file']['size'];
    $fileType = $_FILES['file']['type'];

    // Vérifiez le type de fichier (seuls les images sont autorisées)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Seuls les fichiers JPG, PNG et GIF sont autorisés.');
    }

    // Générer un nom unique pour éviter les conflits
    $newFileName = uniqid('image_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    $destPath = $uploadDir . $newFileName;

    // Déplacer le fichier vers le dossier d'upload
    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        throw new Exception('Impossible de sauvegarder le fichier uploadé.');
    }

    // Réponse réussie
    echo json_encode([
        'success' => true,
        'message' => 'Fichier uploadé avec succès.',
        'file_path' => $destPath
    ]);
} catch (Exception $e) {
    // En cas d'erreur
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
