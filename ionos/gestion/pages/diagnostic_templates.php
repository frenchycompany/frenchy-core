<?php
// Script de diagnostic pour vérifier les templates
// DB loaded via config.php
include '../config.php';
include '../pages/menu.php';
require_once __DIR__ . '/../includes/rpi_db.php';

$pdo = getRpiPdo();

echo "=== DIAGNOSTIC TEMPLATES SMS ===\n\n";

if (!($pdo instanceof PDO)) {
    die("ERREUR: PDO non disponible\n");
}

// Tables requises : voir db/install_tables.php

// Vérifier si la table existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'sms_templates'");
    $exists = $stmt->fetch();
    if (!$exists) {
        echo "❌ La table sms_templates n'existe PAS\n";
        echo "→ Exécutez db/install_tables.php pour la créer\n\n";
    } else {
        echo "✓ La table sms_templates existe\n\n";
    }
} catch (PDOException $e) {
    error_log('diagnostic_templates.php: ' . $e->getMessage());
    die("ERREUR: Une erreur interne est survenue.\n");
}

// Vérifier les templates
try {
    $stmt = $pdo->query("SELECT name, template FROM sms_templates");
    $templates = $stmt->fetchAll();

    if (empty($templates)) {
        echo "❌ AUCUN template trouvé dans la base\n";
        echo "→ Insertion des templates par défaut...\n\n";

        $default_templates = [
            [
                'name' => 'checkout',
                'template' => "Bonjour {prenom},\nMerci pour votre séjour! Nous espérons vous revoir bientôt.",
                'description' => 'Message envoyé le jour du départ'
            ],
            [
                'name' => 'accueil',
                'template' => "Bonjour {prenom},\nBienvenue! N'hésitez pas à nous contacter si vous avez besoin de quoi que ce soit.",
                'description' => 'Message envoyé le jour de l\'arrivée'
            ],
            [
                'name' => 'preparation',
                'template' => "Bonjour {prenom},\nVotre arrivée approche! Nous préparons tout pour vous accueillir dans les meilleures conditions.",
                'description' => 'Message envoyé 4 jours avant l\'arrivée'
            ],
            [
                'name' => 'relance',
                'template' => "Bonjour {prenom},\nNous espérons que vous avez passé un excellent séjour! N'hésitez pas à revenir nous voir.",
                'description' => 'Message de relance pour campagnes'
            ]
        ];

        $stmt_insert = $pdo->prepare("INSERT INTO sms_templates (name, template, description) VALUES (:name, :template, :description)");

        foreach ($default_templates as $template) {
            $stmt_insert->execute($template);
            echo "  ✓ Template '{$template['name']}' créé\n";
        }
        echo "\n";
    } else {
        echo "✓ Templates trouvés:\n";
        foreach ($templates as $t) {
            $preview = substr($t['template'], 0, 50);
            echo "  - {$t['name']}: {$preview}...\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    error_log('diagnostic_templates.php: ' . $e->getMessage());
    echo "ERREUR: Une erreur interne est survenue.\n";
}

echo "=== FIN DU DIAGNOSTIC ===\n";
?>
