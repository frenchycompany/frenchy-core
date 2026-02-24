<?php
include 'config.php';

$stmt = $conn->query("SELECT id, mot_de_passe FROM intervenant");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($utilisateurs as $utilisateur) {
    $id = $utilisateur['id'];
    $mot_de_passe_hash = password_hash($utilisateur['mot_de_passe'], PASSWORD_DEFAULT);

    $updateStmt = $conn->prepare("UPDATE intervenant SET mot_de_passe = :mot_de_passe_hash WHERE id = :id");
    $updateStmt->execute([':mot_de_passe_hash' => $mot_de_passe_hash, ':id' => $id]);
}
echo "Mots de passe mis à jour avec succès.";
?>