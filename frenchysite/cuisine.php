<?php
// Redirect vers le système de guides dynamique
$slug = basename(__FILE__, '.php');
header('Location: guide.php?slug=' . $slug, true, 301);
exit;
