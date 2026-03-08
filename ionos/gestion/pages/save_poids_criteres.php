<?php
include '../config.php'; // Connexion à la base de données

try {
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_valeur') !== false) {
            $critere = str_replace('_valeur', '', $key);
            $valeur = floatval($value);
            $temps_key = $critere . '_temps';
            $temps = isset($_POST[$temps_key]) ? floatval($_POST[$temps_key]) : null;

            $stmt = $conn->prepare("UPDATE poids_criteres SET valeur = ?, temps_par_unite = ? WHERE critere = ?");
            $stmt->execute([$valeur, $temps, $critere]);
        }
    }
    echo "Les poids et temps ont été mis à jour avec succès.";
} catch (PDOException $e) {
    error_log('save_poids_criteres.php: ' . $e->getMessage());
    echo "Une erreur interne est survenue.";
}
