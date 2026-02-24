
<?php
require_once '../includes/db.php';

echo "<h1>Test Simple iCal</h1>";
echo "<pre>";

// Test 1 : Vérifier que la colonne existe
echo "=== TEST 1 : Vérifier la colonne ===\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM travel_account_connections WHERE Field = 'ical_url'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        echo "✓ La colonne ical_url existe\n";
        echo "  Type: " . $col['Type'] . "\n";
    } else {
        echo "✗ La colonne ical_url N'EXISTE PAS\n";
        echo "  → Exécutez migrate_ical.php d'abord\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    exit;
}

// Test 2 : Insérer directement une connexion avec ical_url
echo "\n=== TEST 2 : Insertion directe ===\n";
try {
    $testUrl = "https://www.airbnb.fr/calendar/ical/TEST123.ics";

    $stmt = $pdo->prepare("
        INSERT INTO travel_account_connections
        (platform_id, account_name, ical_url, connection_status)
        VALUES (1, 'TEST ICAL DIRECT', ?, 'pending')
    ");

    $result = $stmt->execute([$testUrl]);
    $insertId = $pdo->lastInsertId();

    echo "✓ Insertion réussie\n";
    echo "  ID: $insertId\n";

    // Vérifier que c'est bien enregistré
    $stmt = $pdo->prepare("SELECT id, account_name, ical_url FROM travel_account_connections WHERE id = ?");
    $stmt->execute([$insertId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n=== Vérification dans la base ===\n";
    echo "ID: " . $row['id'] . "\n";
    echo "Nom: " . $row['account_name'] . "\n";
    echo "iCal URL: " . ($row['ical_url'] ?? 'NULL') . "\n";

    if ($row['ical_url'] === $testUrl) {
        echo "\n✓✓✓ SUCCÈS : ical_url est bien enregistré dans la base !\n";
        echo "\nCela signifie que :\n";
        echo "- La colonne existe\n";
        echo "- L'insertion SQL fonctionne\n";
        echo "- Le problème vient du formulaire web ou de l'API\n\n";
        echo "Solutions possibles :\n";
        echo "1. Les fichiers travel_accounts.php et travel_accounts_api.php ne sont pas à jour sur votre serveur\n";
        echo "2. Il y a un cache dans votre navigateur (Ctrl+F5 pour forcer le rechargement)\n";

        // Nettoyer
        $pdo->prepare("DELETE FROM travel_account_connections WHERE id = ?")->execute([$insertId]);
        echo "\n(Connexion de test supprimée)\n";
    } else {
        echo "\n✗✗✗ ÉCHEC : ical_url est NULL dans la base\n";
        echo "Valeur attendue: $testUrl\n";
        echo "Valeur reçue: " . ($row['ical_url'] ?? 'NULL') . "\n";
    }

} catch (Exception $e) {
    echo "✗ Erreur lors de l'insertion: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>

<hr>
<h2>Test manuel via formulaire</h2>
<form method="POST" action="">
    <input type="hidden" name="test_form" value="1">
    <label>URL iCal à tester:</label><br>
    <input type="text" name="test_ical_url" value="https://www.airbnb.fr/calendar/ical/MANUAL_TEST.ics" style="width: 500px"><br><br>
    <button type="submit">Tester l'insertion</button>
</form>

<?php
if (isset($_POST['test_form'])) {
    echo "<pre>";
    echo "\n=== TEST FORMULAIRE POST ===\n";
    $testUrl = $_POST['test_ical_url'] ?? '';
    echo "URL reçue: $testUrl\n";

    try {
        $stmt = $pdo->prepare("
            INSERT INTO travel_account_connections
            (platform_id, account_name, ical_url, connection_status)
            VALUES (1, 'TEST FORMULAIRE', ?, 'pending')
        ");

        $stmt->execute([$testUrl]);
        $insertId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT ical_url FROM travel_account_connections WHERE id = ?");
        $stmt->execute([$insertId]);
        $saved = $stmt->fetchColumn();

        if ($saved === $testUrl) {
            echo "✓ SUCCÈS : URL bien enregistrée via formulaire POST\n";
            echo "URL dans la base: $saved\n";
        } else {
            echo "✗ ÉCHEC : URL=$saved (attendu: $testUrl)\n";
        }

        $pdo->prepare("DELETE FROM travel_account_connections WHERE id = ?")->execute([$insertId]);

    } catch (Exception $e) {
        echo "✗ Erreur: " . $e->getMessage() . "\n";
    }
    echo "</pre>";
}
?>
