
<?php
session_start();

// Détruire la session et rediriger vers la page de connexion
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
