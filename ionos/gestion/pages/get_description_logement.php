<?php
header('Content-Type: application/json; charset=utf-8');
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);

if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // S'assurer que les colonnes NOT NULL problématiques ont un DEFAULT
    try {
        $cols = $conn->query("SHOW COLUMNS FROM description_logements")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if ($col['Null'] === 'NO' && $col['Default'] === null && $col['Key'] !== 'PRI' && $col['Field'] !== 'logement_id') {
                $type = $col['Type'];
                $default = (stripos($type, 'int') !== false || stripos($type, 'float') !== false || stripos($type, 'decimal') !== false)
                    ? 'DEFAULT 0' : 'DEFAULT NULL';
                $conn->exec("ALTER TABLE description_logements MODIFY `{$col['Field']}` {$type} NULL {$default}");
            }
        }
    } catch (PDOException $e) { /* table n'existe peut-être pas */ }

    // Créer la table si elle n'existe pas
    $conn->exec("CREATE TABLE IF NOT EXISTS `description_logements` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `logement_id` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_logement` (`logement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Vérifier si une fiche descriptive existe
    $stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si aucune fiche n'existe, créer une entrée par défaut
    if (!$logement_data) {
        $stmt = $conn->prepare("
            INSERT INTO description_logements (logement_id)
            VALUES (?)
        ");
        $stmt->execute([$logement_id]);

        // Recharger les données après création
        $stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
        $stmt->execute([$logement_id]);
        $logement_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode($logement_data);
} catch (PDOException $e) {
    error_log('get_description_logement.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Erreur DB: ' . $e->getMessage()]);
}
