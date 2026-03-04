<?php
/**
 * Script de migration : hasher les mots de passe stockés en clair
 * Ne re-hashe PAS les mots de passe déjà hashés (commençant par $2y$)
 * À exécuter une seule fois après la correction de intervenants.php
 */
include 'config.php';

$stmt = $conn->query("SELECT id, nom_utilisateur, mot_de_passe FROM intervenant");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$skipped = 0;

foreach ($utilisateurs as $utilisateur) {
    $mdp = $utilisateur['mot_de_passe'];

    // Déjà hashé (bcrypt commence par $2y$ ou $2a$)
    if (empty($mdp) || preg_match('/^\$2[yab]\$/', $mdp)) {
        $skipped++;
        continue;
    }

    // Mot de passe en clair → hasher
    $hash = password_hash($mdp, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE intervenant SET mot_de_passe = ? WHERE id = ?");
    $stmt->execute([$hash, $utilisateur['id']]);
    $updated++;
    echo "Hashé : " . htmlspecialchars($utilisateur['nom_utilisateur']) . "\n";
}

echo "\nTerminé. {$updated} mot(s) de passe hashé(s), {$skipped} déjà OK.\n";
