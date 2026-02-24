<?php
// Script pour vérifier la structure de la table liste_logements
require_once __DIR__ . '/../includes/db.php';

echo "<h3>Structure de la table liste_logements:</h3>";
echo "<pre>";

try {
    $stmt = $pdo->query("DESCRIBE liste_logements");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

echo "</pre>";

echo "<h3>Structure de la table reservation:</h3>";
echo "<pre>";

try {
    $stmt = $pdo->query("DESCRIBE reservation");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}

echo "</pre>";
?>
