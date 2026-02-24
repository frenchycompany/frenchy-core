<?php
// Ce module affiche un bloc iframe si fourni
if (!empty($client['airbnb_embed'])) {
    echo '<div class="airbnb-embed-container">';
    echo $client['airbnb_embed']; // Pas de htmlspecialchars ici pour garder l’iframe fonctionnel
    echo '</div>';
}
?>
