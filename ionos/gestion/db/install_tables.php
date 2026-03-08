<?php
/**
 * Script d'installation des tables — A executer une seule fois lors du deploiement
 * Usage : php ionos/gestion/db/install_tables.php
 */
require_once __DIR__ . '/../config.php';

$tables = [
    // Contrats de location
    "CREATE TABLE IF NOT EXISTS location_contract_templates (
        id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, content TEXT NOT NULL,
        placeholders TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS location_contract_fields (
        id INT AUTO_INCREMENT PRIMARY KEY, field_name VARCHAR(255) NOT NULL UNIQUE,
        description VARCHAR(255) NOT NULL, input_type ENUM('text','number','textarea','date','select') DEFAULT 'text',
        options TEXT DEFAULT NULL, field_group ENUM('voyageur','reservation','logement','autre') DEFAULT 'autre',
        sort_order INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS location_contract_logement_details (
        id INT AUTO_INCREMENT PRIMARY KEY, logement_id INT NOT NULL,
        description_logement TEXT, equipements TEXT, regles_maison TEXT,
        heure_arrivee VARCHAR(10) DEFAULT '16:00', heure_depart VARCHAR(10) DEFAULT '10:00',
        depot_garantie DECIMAL(10,2), taxe_sejour_par_nuit DECIMAL(10,2),
        conditions_annulation TEXT, informations_supplementaires TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_logement (logement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS generated_location_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT NULL, logement_id INT NOT NULL,
        template_title VARCHAR(255), logement_nom VARCHAR(255), voyageur_nom VARCHAR(255),
        date_arrivee DATE, date_depart DATE, prix_total DECIMAL(10,2),
        file_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Rate limiting
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        nom_utilisateur VARCHAR(255) DEFAULT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$ok = 0;
$fail = 0;
foreach ($tables as $sql) {
    try {
        $conn->exec($sql);
        $ok++;
        // Extraire le nom de la table
        preg_match('/CREATE TABLE IF NOT EXISTS (\S+)/', $sql, $m);
        echo "OK: {$m[1]}\n";
    } catch (PDOException $e) {
        $fail++;
        preg_match('/CREATE TABLE IF NOT EXISTS (\S+)/', $sql, $m);
        echo "ERREUR ({$m[1]}): " . $e->getMessage() . "\n";
    }
}

echo "\nTermine: {$ok} tables OK, {$fail} erreurs.\n";
