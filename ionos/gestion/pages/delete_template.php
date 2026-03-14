<?php
include '../config.php';
require_once __DIR__ . '/../includes/contract_config.php';

$type = detectContractType();
$config = getContractConfig($type);
$table = $config['table_templates'];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        header("Location: list_templates.php?type=$type&deleted=1");
        exit;
    } catch (PDOException $e) {
        error_log('delete_template.php: ' . $e->getMessage());
        echo "Une erreur interne est survenue.";
    }
} else {
    header("Location: list_templates.php?type=$type");
    exit;
}
