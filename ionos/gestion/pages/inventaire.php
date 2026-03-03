<?php
// inventaire.php
include '../config.php';
include '../pages/menu.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion des Inventaires</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f7fa;
    margin: 0;
    padding: 0;
}
.header {
    background: #1976d2;
    color: #fff;
    text-align: center;
    padding: 25px 10px 20px 10px;
    font-size: 1.5em;
    letter-spacing: 1px;
}
.menu {
    max-width: 480px;
    margin: 40px auto 0 auto;
    display: flex;
    flex-direction: column;
    gap: 22px;
}
.menu a {
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: left;
    padding: 22px 22px;
    border-radius: 15px;
    box-shadow: 0 2px 9px #e0e0e0;
    text-decoration: none;
    color: #1976d2;
    font-size: 1.20em;
    font-weight: 600;
    transition: box-shadow 0.13s, background 0.15s;
    border: 2px solid #f0f0f0;
}
.menu a:hover {
    box-shadow: 0 2px 18px #aac6e8;
    background: #e3f2fd;
}
.menu span.emoji {
    font-size: 1.45em;
    margin-right: 17px;
    width: 2.1em;
    text-align: center;
}
@media (max-width: 600px) {
    .menu { margin-top: 22px; }
    .menu a { font-size: 1.08em; padding: 15px 10px; }
    .header { font-size: 1.07em; padding: 18px 4px; }
}
</style>
</head>
<body>

<div class="header">
    Gestion des Inventaires
</div>

<div class="menu">
    <a href="inventaire_lancer.php"><span class="emoji">🆕</span>Lancer un inventaire</a>
    <a href="liste_sessions.php"><span class="emoji">📝</span>Voir les sessions d’inventaire en cours</a>
    <a href="liste_sessions.php?validation=1"><span class="emoji">✅</span>Valider un inventaire</a>
    <a href="impression_etiquettes.php"><span class="emoji">🏷️</span>Impression des étiquettes d’un logement</a>
</div>

</body>
</html>
