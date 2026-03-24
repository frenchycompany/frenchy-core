<?php
// pages/sync_reservations_today.php
// Wrapper : synchronise pour aujourd'hui en déléguant à sync_reservations_by_date.php
date_default_timezone_set('Europe/Paris');
$_GET['date'] = date('Y-m-d');
require __DIR__ . '/sync_reservations_by_date.php';
