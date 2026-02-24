<?php
// /cdansmaville/admin/manage_global.php
ob_start();
session_start();
require_once 'config.php';
require_once '../db/connection.php';

$allowed_sections = [
    'dashboard',
    'clients',
    'edit_client',
    'modules',
    'texts',
    'content',
    'partners',
    'banner',
    'preview',
    'css'
];

$section = isset($_GET['section']) && in_array($_GET['section'], $allowed_sections) ? $_GET['section'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interface Globale - Administration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
        /* Styles pour le menu latéral et la zone de contenu */
        body { margin: 0; font-family: Arial, sans-serif; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 250px; height: 100%;
            background-color: #007bff; color: white; padding-top: 20px;
        }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a {
            display: block; padding: 10px 15px; color: white; text-decoration: none;
            transition: background 0.3s;
        }
        .sidebar a:hover { background-color: #0056b3; }
        .content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .content { margin-left: 200px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Menu Admin</h2>
        <a href="?section=dashboard">Dashboard</a>
        <a href="?section=clients">Gestion des Clients</a>
        <a href="?section=partners">Partenaires</a>
        <a href="?section=preview">Prévisualisation</a>
        <a href="?section=css">Modification CSS</a>
    </div>
    <div class="content">
        <?php
        // Message de debug pour vérifier la section
        echo "<p>DEBUG: section = " . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . "</p>";
        switch ($section) {
            case 'dashboard':
                include 'partials/dashboard.php';
                break;
            case 'clients':
                include 'partials/clients.php';
                break;
            case 'edit_client':
                include 'partials/edit_client.php';
                break;
            case 'modules':
                include 'partials/manage_modules.php';
                break;
            case 'texts':
                include 'partials/manage_texts.php';
                break;
            case 'content':
                include 'partials/manage_content.php';
                break;
            case 'partners':
                include 'partials/partners.php';
                break;
            case 'banner':
                include 'partials/manage_banner.php';
                break;
            case 'preview':
                include 'partials/preview_landing.php';
                break;
            case 'css':
                include 'partials/edit_css.php';
                break;
            default:
                include 'partials/dashboard.php';
                break;
        }
        ?>
    </div>
</body>
</html>
