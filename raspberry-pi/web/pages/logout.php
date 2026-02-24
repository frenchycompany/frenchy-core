<?php
require_once __DIR__ . '/../includes/auth.php';

logout();

header('Location: login.php?message=Vous avez été déconnecté avec succès');
exit;
