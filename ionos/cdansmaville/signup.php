<?php
require 'db/connection.php';
 include 'menu.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = htmlspecialchars($_POST['role']); // 'visitor' ou 'merchant'
    $siret = isset($_POST['siret']) ? htmlspecialchars($_POST['siret']) : null;
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;

    try {
        // Préparer l'insertion
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role, siret, newsletter) 
            VALUES (:name, :email, :password, :role, :siret, :newsletter)"
        );
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':siret', $siret);
        $stmt->bindParam(':newsletter', $newsletter);
        $stmt->execute();

        echo "Inscription réussie !";
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        function toggleSiretField() {
            const role = document.getElementById('role').value;
            const siretField = document.getElementById('siret-field');
            if (role === 'merchant') {
                siretField.style.display = 'block';
            } else {
                siretField.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="signup-container">
        <h2>Inscription</h2>
        <form action="signup.php" method="POST">
            <label for="name">Nom :</label>
            <input type="text" name="name" id="name" required>
            <br>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email" required>
            <br>
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required>
            <br>
            <label for="role">Rôle :</label>
            <select name="role" id="role" required onchange="toggleSiretField()">
                <option value="visitor">Visiteur</option>
                <option value="merchant">Commerçant</option>
            </select>
            <br>
            <div id="siret-field" style="display: none;">
                <label for="siret">Numéro SIRET :</label>
                <input type="text" name="siret" id="siret" pattern="[0-9]{14}" placeholder="14 chiffres">
                <br>
            </div>
            <label>
                <input type="checkbox" name="newsletter" id="newsletter">
                Je souhaite recevoir les newsletters
            </label>
            <br>
            <button type="submit">S'inscrire</button>
        </form>
    </div>
</body>
</html>
