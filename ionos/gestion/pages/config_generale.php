<?php
/**
 * Configuration Générale — FrenchyConciergerie
 * Redirige vers admin.php qui gère désormais la configuration directement.
 */
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/csrf.php';

// Vérification admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../error.php?message=' . urlencode('Accès réservé aux administrateurs.'));
    exit;
}

// Si POST reçu ici (ancien formulaire), traiter puis rediriger
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();

    $fields = [
        'nom_site'          => trim($_POST['nom_site'] ?? ''),
        'nom_conciergerie'  => trim($_POST['nom_conciergerie'] ?? ''),
        'email_contact'     => trim($_POST['email_contact'] ?? ''),
        'telephone'         => trim($_POST['telephone'] ?? ''),
        'adresse'           => trim($_POST['adresse'] ?? ''),
        'siret'             => trim($_POST['siret'] ?? ''),
        'site_web'          => trim($_POST['site_web'] ?? ''),
        'footer_text'       => trim($_POST['footer_text'] ?? ''),
        'mode_maintenance'  => isset($_POST['mode_maintenance']) ? 1 : 0,
    ];

    try {
        // Ne mettre à jour que les champs qui existent en BDD
        $existantes = array_column($conn->query("SHOW COLUMNS FROM configuration")->fetchAll(), 'Field');
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $existantes)) {
                $sets[] = "`$k` = ?";
                $vals[] = $v;
            }
        }
        if (!empty($sets)) {
            $stmt = $conn->prepare("UPDATE configuration SET " . implode(', ', $sets) . " WHERE id = 1");
            $stmt->execute($vals);
        }
    } catch (PDOException $e) {
        error_log('config_generale.php erreur : ' . $e->getMessage());
    }

    header('Location: admin.php?updated=1');
    exit;
}

// GET → rediriger vers admin.php
header('Location: admin.php');
exit;
