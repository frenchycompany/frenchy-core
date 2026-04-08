<?php
/**
 * Script pour créer/réinitialiser l'utilisateur admin
 * Exécuter ce script une fois pour créer le compte admin
 */

require_once __DIR__ . '/web/includes/db.php';

echo "=== Création/Réinitialisation de l'utilisateur admin ===\n\n";

// Vérifier la connexion PDO
if (!($pdo instanceof PDO)) {
    die("❌ Erreur: PDO non disponible. Vérifiez la connexion à la base de données.\n");
}

try {
    // 1. Créer la table users si elle n'existe pas
    echo "1. Création de la table users si nécessaire...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `nom` varchar(100) DEFAULT NULL,
          `prenom` varchar(100) DEFAULT NULL,
          `active` tinyint(1) DEFAULT 1,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `last_login` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "✅ Table users créée ou déjà existante\n\n";

    // 2. Générer un mot de passe aléatoire sécurisé
    $email = 'admin@sms.local';
    $password = bin2hex(random_bytes(12)); // 24 caractères aléatoires
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    echo "2. Génération du hash du mot de passe...\n";
    echo "   Email: $email\n";
    echo "   Mot de passe: $password\n";
    echo "   Hash: $password_hash\n\n";

    // 3. Vérifier si l'utilisateur existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Mettre à jour le mot de passe existant
        echo "3. Mise à jour de l'utilisateur existant...\n";
        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?, nom = 'Admin', prenom = 'Système', active = 1
            WHERE email = ?
        ");
        $stmt->execute([$password_hash, $email]);
        echo "✅ Mot de passe mis à jour pour $email\n\n";
    } else {
        // Créer un nouvel utilisateur
        echo "3. Création d'un nouvel utilisateur admin...\n";
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, nom, prenom, active)
            VALUES (?, ?, 'Admin', 'Système', 1)
        ");
        $stmt->execute([$email, $password_hash]);
        echo "✅ Utilisateur créé: $email\n\n";
    }

    // 4. Vérifier que le mot de passe fonctionne
    echo "4. Vérification du mot de passe...\n";
    $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        echo "✅ Le mot de passe est correct et fonctionne!\n\n";
    } else {
        echo "❌ Erreur: Le mot de passe ne fonctionne pas!\n\n";
        exit(1);
    }

    echo "=== SUCCÈS ===\n";
    echo "Vous pouvez maintenant vous connecter avec:\n";
    echo "  Email: $email\n";
    echo "  Mot de passe: $password\n\n";
    echo "⚠️  IMPORTANT: Changez ce mot de passe après votre première connexion!\n";

} catch (PDOException $e) {
    echo "❌ Erreur PDO: " . $e->getMessage() . "\n";
    exit(1);
}
