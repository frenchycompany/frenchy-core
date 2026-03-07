<?php
/**
 * Script de migration — Système d'auth unifié
 *
 * Ce script :
 * 1. Crée les tables users, user_permissions, auth_rate_limit
 * 2. Migre les intervenants (staff) vers users
 * 3. Migre les FC_proprietaires vers users
 * 4. Migre les permissions (intervenants_pages → user_permissions)
 * 5. Crée un super_admin si aucun n'existe
 *
 * SAFE : peut être relancé plusieurs fois (idempotent)
 */

// Empêcher l'exécution via le web
if (php_sapi_name() !== 'cli' && !defined('MIGRATION_ALLOWED')) {
    die("Ce script doit être exécuté en ligne de commande.\n");
}

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../../includes/Auth.php';

echo "=== Migration vers le système d'auth unifié ===\n\n";

$auth = new Auth($conn);

// ============================================================
// ÉTAPE 1 : Créer les tables (ou mettre à jour si elles existent)
// ============================================================
echo "[1/5] Création des tables...\n";

$sql = file_get_contents(__DIR__ . '/001_unified_auth.sql');

// Exécuter chaque instruction séparément
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with($s, '--')
);

foreach ($statements as $statement) {
    try {
        $conn->exec($statement);
    } catch (PDOException $e) {
        // Ignorer les erreurs "already exists"
        if (strpos($e->getMessage(), 'already exists') === false
            && strpos($e->getMessage(), '1050') === false) {
            echo "  ATTENTION: " . $e->getMessage() . "\n";
        }
    }
}

// Si la table users existait déjà, ajouter les colonnes manquantes
$columnsToAdd = [
    'password_hash'          => "VARCHAR(255) NOT NULL DEFAULT ''",
    'nom'                    => "VARCHAR(100) NOT NULL DEFAULT 'Sans nom'",
    'prenom'                 => 'VARCHAR(100) DEFAULT NULL',
    'telephone'              => 'VARCHAR(30) DEFAULT NULL',
    'adresse'                => 'TEXT DEFAULT NULL',
    'photo'                  => 'VARCHAR(255) DEFAULT NULL',
    'role'                   => "ENUM('super_admin', 'admin', 'staff', 'proprietaire_full', 'proprietaire_opti') NOT NULL DEFAULT 'staff'",
    'numero'                 => 'VARCHAR(50) DEFAULT NULL',
    'role1'                  => 'VARCHAR(255) DEFAULT NULL',
    'role2'                  => 'VARCHAR(255) DEFAULT NULL',
    'role3'                  => 'VARCHAR(255) DEFAULT NULL',
    'societe'                => 'VARCHAR(255) DEFAULT NULL',
    'siret'                  => 'VARCHAR(20) DEFAULT NULL',
    'rib_iban'               => 'VARCHAR(40) DEFAULT NULL',
    'rib_bic'                => 'VARCHAR(15) DEFAULT NULL',
    'rib_banque'             => 'VARCHAR(100) DEFAULT NULL',
    'commission'             => 'DECIMAL(5,2) DEFAULT NULL',
    'notes_admin'            => 'TEXT DEFAULT NULL',
    'actif'                  => 'TINYINT(1) NOT NULL DEFAULT 1',
    'derniere_connexion'     => 'DATETIME DEFAULT NULL',
    'token_reset'            => 'VARCHAR(255) DEFAULT NULL',
    'token_reset_expire'     => 'DATETIME DEFAULT NULL',
    'legacy_intervenant_id'  => 'INT DEFAULT NULL',
    'legacy_proprietaire_id' => 'INT DEFAULT NULL',
    'created_at'             => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at'             => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];

// Récupérer les colonnes existantes de la table users
$existingCols = [];
try {
    $colsResult = $conn->query("SHOW COLUMNS FROM users");
    foreach ($colsResult as $col) {
        $existingCols[] = $col['Field'];
    }
} catch (PDOException $e) {
    // Table n'existe pas encore — pas de colonnes à ajouter
}

$addedCols = 0;
foreach ($columnsToAdd as $colName => $colDef) {
    if (!in_array($colName, $existingCols)) {
        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `$colName` $colDef");
            $addedCols++;
            echo "  Colonne ajoutée : $colName\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "  ATTENTION colonne $colName : " . $e->getMessage() . "\n";
            }
        }
    }
}

// Ajouter les index manquants
$indexesToAdd = [
    'idx_role'                => 'ALTER TABLE `users` ADD KEY `idx_role` (`role`)',
    'idx_actif'               => 'ALTER TABLE `users` ADD KEY `idx_actif` (`actif`)',
    'idx_legacy_intervenant'  => 'ALTER TABLE `users` ADD KEY `idx_legacy_intervenant` (`legacy_intervenant_id`)',
    'idx_legacy_proprietaire' => 'ALTER TABLE `users` ADD KEY `idx_legacy_proprietaire` (`legacy_proprietaire_id`)',
];

foreach ($indexesToAdd as $idxName => $idxSql) {
    try {
        $conn->exec($idxSql);
    } catch (PDOException $e) {
        // Ignorer si l'index existe déjà
    }
}

if ($addedCols > 0) {
    echo "  $addedCols colonnes ajoutées à la table users existante.\n";
}
echo "  Tables OK.\n";

// ============================================================
// ÉTAPE 2 : Migrer les intervenants (staff)
// ============================================================
echo "\n[2/5] Migration des intervenants...\n";

try {
    $intervenants = $conn->query("SELECT * FROM intervenant")->fetchAll(PDO::FETCH_ASSOC);
    $migrated_staff = 0;
    $skipped_staff = 0;

    foreach ($intervenants as $i) {
        // Vérifier si déjà migré
        $check = $conn->prepare("SELECT id FROM users WHERE legacy_intervenant_id = ?");
        $check->execute([$i['id']]);
        if ($check->fetch()) {
            $skipped_staff++;
            continue;
        }

        // Déterminer l'email : utiliser nom_utilisateur@frenchy.local si pas d'email
        $email = '';
        if (!empty($i['email'])) {
            $email = strtolower(trim($i['email']));
        } else {
            // Générer un email à partir du nom d'utilisateur
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $i['nom_utilisateur'] ?? $i['nom'] ?? 'user'));
            $email = $username . '@frenchy.local';
        }

        // Vérifier unicité de l'email
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetch()) {
            $email = $email . '.' . $i['id']; // Ajouter l'ID pour rendre unique
        }

        // Déterminer le rôle
        $role = 'staff';
        if (($i['role'] ?? '') === 'admin') {
            $role = 'admin';
        }

        // Le mot de passe existant est déjà hashé (bcrypt), on le garde tel quel
        // Il sera rehashé en argon2id au prochain login
        $passwordHash = $i['mot_de_passe'] ?? '';

        $stmt = $conn->prepare(
            "INSERT INTO users (email, password_hash, nom, prenom, telephone, role,
                                numero, role1, role2, role3,
                                actif, legacy_intervenant_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $email,
            $passwordHash,
            $i['nom'] ?? 'Sans nom',
            null,
            $i['numero'] ?? null,
            $role,
            $i['numero'] ?? null,
            $i['role1'] ?? null,
            $i['role2'] ?? null,
            $i['role3'] ?? null,
            $i['actif'] ?? 1,
            $i['id'],
        ]);

        $migrated_staff++;
    }

    echo "  $migrated_staff intervenants migrés, $skipped_staff déjà existants.\n";

} catch (PDOException $e) {
    echo "  ERREUR intervenants: " . $e->getMessage() . "\n";
}

// ============================================================
// ÉTAPE 3 : Migrer les propriétaires
// ============================================================
echo "\n[3/5] Migration des propriétaires...\n";

try {
    // Vérifier si la table FC_proprietaires existe
    $tables = $conn->query("SHOW TABLES LIKE 'FC_proprietaires'")->fetchAll();
    if (empty($tables)) {
        echo "  Table FC_proprietaires n'existe pas, skip.\n";
    } else {
        $proprietaires = $conn->query("SELECT * FROM FC_proprietaires")->fetchAll(PDO::FETCH_ASSOC);
        $migrated_prop = 0;
        $skipped_prop = 0;

        foreach ($proprietaires as $p) {
            // Vérifier si déjà migré
            $check = $conn->prepare("SELECT id FROM users WHERE legacy_proprietaire_id = ?");
            $check->execute([$p['id']]);
            if ($check->fetch()) {
                $skipped_prop++;
                continue;
            }

            $email = strtolower(trim($p['email']));

            // Vérifier unicité — un proprio pourrait aussi être staff
            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                // L'email existe déjà (par ex. un staff qui est aussi proprio)
                // On ajoute le legacy_proprietaire_id à l'user existant
                $conn->prepare("UPDATE users SET legacy_proprietaire_id = ?, role = 'proprietaire_full' WHERE email = ?")
                    ->execute([$p['id'], $email]);
                $skipped_prop++;
                echo "  Email dupliqué $email → lié au user existant.\n";
                continue;
            }

            // Déterminer le rôle proprio (par défaut: full, à ajuster manuellement pour opti)
            $role = 'proprietaire_full';

            // Le password_hash existant est déjà hashé, on le garde
            $stmt = $conn->prepare(
                "INSERT INTO users (email, password_hash, nom, prenom, telephone, adresse, photo, role,
                                    societe, siret, rib_iban, rib_bic, rib_banque,
                                    commission, notes_admin,
                                    actif, derniere_connexion,
                                    token_reset, token_reset_expire,
                                    legacy_proprietaire_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $email,
                $p['password_hash'] ?? '',
                $p['nom'] ?? 'Sans nom',
                $p['prenom'] ?? null,
                $p['telephone'] ?? null,
                $p['adresse'] ?? null,
                $p['photo'] ?? null,
                $role,
                $p['societe'] ?? null,
                $p['siret'] ?? null,
                $p['rib_iban'] ?? null,
                $p['rib_bic'] ?? null,
                $p['rib_banque'] ?? null,
                $p['commission'] ?? null,
                $p['notes'] ?? null,
                $p['actif'] ?? 1,
                $p['derniere_connexion'] ?? null,
                $p['token_reset'] ?? null,
                $p['token_reset_expire'] ?? null,
                $p['id'],
            ]);

            $migrated_prop++;
        }

        echo "  $migrated_prop propriétaires migrés, $skipped_prop déjà existants.\n";
    }

} catch (PDOException $e) {
    echo "  ERREUR propriétaires: " . $e->getMessage() . "\n";
}

// ============================================================
// ÉTAPE 4 : Migrer les permissions de pages
// ============================================================
echo "\n[4/5] Migration des permissions de pages...\n";

try {
    // Vérifier si les tables intervenants_pages et user_permissions existent
    $tables = $conn->query("SHOW TABLES LIKE 'intervenants_pages'")->fetchAll();
    $hasUserPerms = !empty($conn->query("SHOW TABLES LIKE 'user_permissions'")->fetchAll());
    if (empty($tables)) {
        echo "  Table intervenants_pages n'existe pas, skip.\n";
    } elseif (!$hasUserPerms) {
        echo "  Table user_permissions n'existe pas (table pages manquante ?), skip.\n";
    } else {
        $perms = $conn->query("SELECT * FROM intervenants_pages")->fetchAll(PDO::FETCH_ASSOC);
        $migrated_perms = 0;

        foreach ($perms as $perm) {
            // Trouver le user correspondant à l'ancien intervenant_id
            $stmt = $conn->prepare("SELECT id FROM users WHERE legacy_intervenant_id = ?");
            $stmt->execute([$perm['intervenant_id']]);
            $user = $stmt->fetch();

            if (!$user) continue;

            // Insérer la permission si elle n'existe pas déjà
            try {
                $conn->prepare(
                    "INSERT IGNORE INTO user_permissions (user_id, page_id) VALUES (?, ?)"
                )->execute([$user['id'], $perm['page_id']]);
                $migrated_perms++;
            } catch (PDOException $e) {
                // Ignorer les doublons
            }
        }

        echo "  $migrated_perms permissions migrées.\n";
    }

} catch (PDOException $e) {
    echo "  ERREUR permissions: " . $e->getMessage() . "\n";
}

// ============================================================
// ÉTAPE 5 : Créer un super_admin par défaut si aucun n'existe
// ============================================================
echo "\n[5/5] Vérification super_admin...\n";

try {
    $check = $conn->query("SELECT id FROM users WHERE role = 'super_admin'")->fetch();
    if (!$check) {
        $defaultPassword = bin2hex(random_bytes(8)); // 16 caractères aléatoires
        $hash = $auth->hashPassword($defaultPassword);

        $conn->prepare(
            "INSERT INTO users (email, password_hash, nom, role, actif)
             VALUES ('admin@frenchy.local', ?, 'Super Admin', 'super_admin', 1)"
        )->execute([$hash]);

        echo "  Super admin créé !\n";
        echo "  Email: admin@frenchy.local\n";
        echo "  Mot de passe: $defaultPassword\n";
        echo "  >>> CHANGEZ CE MOT DE PASSE IMMÉDIATEMENT <<<\n";
    } else {
        echo "  Super admin existe déjà.\n";
    }
} catch (PDOException $e) {
    echo "  ERREUR super_admin: " . $e->getMessage() . "\n";
}

// ============================================================
// RÉSUMÉ
// ============================================================
echo "\n=== Migration terminée ===\n";

$counts = $conn->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
echo "\nUtilisateurs par rôle :\n";
foreach ($counts as $c) {
    echo "  {$c['role']}: {$c['cnt']}\n";
}

try {
    $totalPerms = $conn->query("SELECT COUNT(*) FROM user_permissions")->fetchColumn();
    echo "\nPermissions de pages : $totalPerms\n";
} catch (PDOException $e) {
    echo "\nPermissions de pages : table non créée (table pages manquante ?)\n";
}

echo "\nProchaines étapes :\n";
echo "  1. Testez le login unifié : login_unified.php\n";
echo "  2. Une fois validé, renommez login_unified.php → login.php\n";
echo "  3. Mettez à jour les redirections des anciennes pages de login\n";
echo "  4. Les propriétaires 'optimisation' doivent être basculés manuellement\n";
echo "     UPDATE users SET role = 'proprietaire_opti', commission = 6.00 WHERE id = X;\n";
