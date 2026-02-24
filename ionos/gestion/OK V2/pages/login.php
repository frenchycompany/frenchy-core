<?php
session_start();
include 'config.php'; // Connexion à la base de données

$erreur = "";
$max_attempts = 5; // Limite de tentatives autorisées
$lockout_duration = 15 * 60; // Durée de blocage (15 minutes)

function is_locked_out($ip, $conn) {
    global $max_attempts, $lockout_duration;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS attempts, 
               MAX(attempt_time) AS last_attempt 
        FROM login_attempts 
        WHERE ip_address = :ip 
          AND attempt_time > NOW() - INTERVAL :duration SECOND
    ");
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':duration', $lockout_duration, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    return $data['attempts'] >= $max_attempts && strtotime($data['last_attempt']) + $lockout_duration > time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_utilisateur = trim($_POST['nom_utilisateur']);
    $mot_de_passe = trim($_POST['mot_de_passe']);
    $ip_address = $_SERVER['REMOTE_ADDR']; // Adresse IP de l'utilisateur

    // Vérification des tentatives récentes
    if (is_locked_out($ip_address, $conn)) {
        $erreur = "Trop de tentatives de connexion. Réessayez plus tard.";
    } else {
        if (empty($nom_utilisateur) || empty($mot_de_passe)) {
            $erreur = "Veuillez remplir tous les champs.";
        } else {
            try {
                // Récupération des données utilisateur
                $stmt = $conn->prepare("SELECT id, nom_utilisateur, mot_de_passe, role, email FROM intervenant WHERE nom_utilisateur = :nom_utilisateur");
                $stmt->bindParam(':nom_utilisateur', $nom_utilisateur, PDO::PARAM_STR);
                $stmt->execute();
                $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
                    // Réinitialisation des tentatives en cas de succès
                    $conn->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $ip_address]);

                    // Enregistrement des informations utilisateur en session
                    $_SESSION['id_intervenant'] = $utilisateur['id'];
                    $_SESSION['nom_utilisateur'] = htmlspecialchars($utilisateur['nom_utilisateur']);
                    $_SESSION['role'] = htmlspecialchars($utilisateur['role']);

                    header('Location: index.php');
                    exit;
                } else {
                    // Ajout d'une tentative en cas d'échec
                    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, nom_utilisateur) VALUES (:ip, :nom_utilisateur)");
                    $stmt->execute([':ip' => $ip_address, ':nom_utilisateur' => $nom_utilisateur]);
                    $erreur = "Nom d'utilisateur ou mot de passe incorrect.";
                }
            } catch (PDOException $e) {
                $erreur = "Erreur interne, veuillez réessayer.";
                error_log("Erreur de connexion : " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" href="style.css"> <!-- Votre fichier CSS -->
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
        <?php if (!empty($erreur)): ?>
            <div class="error-message"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="nom_utilisateur">Nom d'utilisateur :</label>
                <input type="text" id="nom_utilisateur" name="nom_utilisateur" required>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe :</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            </div>
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        <p><a href="forgot_password.php">Mot de passe oublié ?</a></p>
    </div>
</body>
</html>
