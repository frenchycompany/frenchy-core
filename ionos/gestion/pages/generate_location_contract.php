<?php
// Systeme unifie — redirige le POST vers generate_contract.php avec type=location
$_POST['contract_type'] = 'location';
$_GET['type'] = 'location';
include __DIR__ . '/generate_contract.php';
