<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Conversation</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
    </style>
</head>
<body>
    <h1>🔍 Test de Diagnostic SMS</h1>

    <div class="test-section">
        <h2>1. Test de la base de données</h2>
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        try {
            include '../includes/db.php';

            if ($conn) {
                echo '<p class="success">✓ Connexion à la base de données OK</p>';

                // Test table sms_in
                $test_in = $conn->query("SELECT COUNT(*) as count FROM sms_in");
                $count_in = $test_in->fetch(PDO::FETCH_ASSOC);
                echo '<p class="success">✓ Table sms_in existe (' . $count_in['count'] . ' messages)</p>';

                // Test table sms_outbox
                try {
                    $test_out = $conn->query("SELECT COUNT(*) as count FROM sms_outbox");
                    $count_out = $test_out->fetch(PDO::FETCH_ASSOC);
                    echo '<p class="success">✓ Table sms_outbox existe (' . $count_out['count'] . ' messages)</p>';
                } catch (PDOException $e) {
                    echo '<p class="error">✗ Table sms_outbox n\'existe pas : ' . $e->getMessage() . '</p>';
                    echo '<p>👉 <strong>ACTION REQUISE :</strong> Exécutez le fichier schema.sql pour créer la table</p>';
                }

                // Afficher quelques numéros de la table sms_in
                $stmt = $conn->query("SELECT DISTINCT sender FROM sms_in LIMIT 5");
                $senders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($senders) > 0) {
                    echo '<p><strong>Numéros dans sms_in :</strong></p><ul>';
                    foreach ($senders as $s) {
                        echo '<li>' . htmlspecialchars($s['sender']) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="error">✗ Aucun message dans sms_in</p>';
                }

            } else {
                echo '<p class="error">✗ Échec de connexion à la base de données</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">✗ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>2. Test de l'API get_conversation.php</h2>
        <p>Entrez un numéro de téléphone pour tester :</p>
        <input type="text" id="testPhone" placeholder="+33612345678" value="">
        <button onclick="testAPI()">Tester l'API</button>
        <div id="apiResult" style="margin-top: 15px;"></div>
    </div>

    <div class="test-section">
        <h2>3. Test des chemins de fichiers</h2>
        <?php
        $files_to_check = [
            'get_conversation.php',
            'send_sms.php',
            'recus.php',
            '../includes/db.php',
            '../includes/header.php'
        ];

        echo '<ul>';
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                echo '<li class="success">✓ ' . htmlspecialchars($file) . ' existe</li>';
            } else {
                echo '<li class="error">✗ ' . htmlspecialchars($file) . ' MANQUANT</li>';
            }
        }
        echo '</ul>';
        ?>
    </div>

    <div class="test-section">
        <h2>4. Logs PHP</h2>
        <p><strong>Chemin des logs PHP :</strong> <?= ini_get('error_log') ?: '/var/log/php_errors.log' ?></p>
        <p>Pour voir les logs en temps réel :</p>
        <pre>tail -f <?= ini_get('error_log') ?: '/var/log/php_errors.log' ?></pre>
    </div>

    <script>
        // Pré-remplir avec le premier numéro si disponible
        <?php
        if (isset($senders) && count($senders) > 0) {
            echo 'document.getElementById("testPhone").value = "' . addslashes($senders[0]['sender']) . '";';
        }
        ?>

        async function testAPI() {
            const phone = document.getElementById('testPhone').value;
            const resultDiv = document.getElementById('apiResult');

            if (!phone) {
                resultDiv.innerHTML = '<p class="error">Veuillez entrer un numéro de téléphone</p>';
                return;
            }

            resultDiv.innerHTML = '<p>⏳ Chargement...</p>';

            try {
                console.log('Appel API avec numéro:', phone);
                const response = await fetch('get_conversation.php?sender=' + encodeURIComponent(phone));
                console.log('Réponse reçue:', response);

                const text = await response.text();
                console.log('Texte brut:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Réponse non-JSON: ' + text.substring(0, 200));
                }

                console.log('Données JSON:', data);

                if (data.error) {
                    resultDiv.innerHTML = '<p class="error">✗ Erreur API : ' + data.error + '</p>';
                } else {
                    resultDiv.innerHTML =
                        '<p class="success">✓ API fonctionne !</p>' +
                        '<p><strong>Nombre de messages :</strong> ' + data.length + '</p>' +
                        '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                }
            } catch (error) {
                console.error('Erreur complète:', error);
                resultDiv.innerHTML = '<p class="error">✗ Erreur : ' + error.message + '</p>' +
                    '<p>Vérifiez la console du navigateur (F12) pour plus de détails</p>';
            }
        }
    </script>
</body>
</html>