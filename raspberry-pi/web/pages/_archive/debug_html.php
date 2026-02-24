<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';

// Récupérer les conversations comme dans recus.php
$conversations = [];

try {
    $stmt_conv = $conn->query("
        SELECT
            s.sender,
            s.message as last_message,
            s.received_at as last_date,
            stats.total_count
        FROM sms_in s
        INNER JOIN (
            SELECT MAX(id) as max_id
            FROM sms_in
            WHERE sender IS NOT NULL AND sender != ''
            GROUP BY sender
        ) as last_msg_ids ON s.id = last_msg_ids.max_id
        INNER JOIN (
            SELECT sender, COUNT(id) as total_count
            FROM sms_in
            WHERE sender IS NOT NULL AND sender != ''
            GROUP BY sender
        ) as stats ON s.sender = stats.sender
        ORDER BY s.received_at DESC
        LIMIT 5
    ");

    $raw_conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_conversations as $conv_data) {
        $sender = $conv_data['sender'];
        if (empty($sender)) continue;

        $last_message = $conv_data['last_message'] ?? '';
        $last_message = str_replace(["\r\n", "\n", "\r"], ' ', $last_message);

        $conversations[] = [
            'sender' => $sender,
            'last_message' => $last_message,
            'last_date' => $conv_data['last_date'] ?? '',
            'unread_count' => 0,
            'total_count' => $conv_data['total_count'] ?? 0,
            'reservation' => null
        ];
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug HTML/JavaScript</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; white-space: pre-wrap; }
        .test-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔍 Debug HTML/JavaScript - Ligne 3302</h1>

    <div class="test-box">
        <h2>Test 1: JSON direct (méthode actuelle)</h2>
        <p>C'est ce qui cause probablement le problème:</p>
        <pre><?php
$json = json_encode($conversations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
echo htmlspecialchars($json);
?></pre>

        <div id="test1-result"></div>
        <script>
        try {
            const conversationsData1 = <?= json_encode($conversations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            document.getElementById('test1-result').innerHTML = '<p class="success">✓ Test 1 réussi ! JSON valide.</p>';
            console.log('Test 1 OK:', conversationsData1);
        } catch (e) {
            document.getElementById('test1-result').innerHTML = '<p class="error">✗ Test 1 échoué : ' + e.message + '</p>';
            console.error('Test 1 ERREUR:', e);
        }
        </script>
    </div>

    <div class="test-box">
        <h2>Test 2: JSON via attribut data (solution recommandée)</h2>
        <div id="json-holder" data-conversations='<?= htmlspecialchars(json_encode($conversations), ENT_QUOTES, 'UTF-8') ?>'></div>

        <div id="test2-result"></div>
        <script>
        try {
            const holder = document.getElementById('json-holder');
            const conversationsData2 = JSON.parse(holder.dataset.conversations);
            document.getElementById('test2-result').innerHTML = '<p class="success">✓ Test 2 réussi ! JSON dans attribut data.</p>';
            console.log('Test 2 OK:', conversationsData2);
        } catch (e) {
            document.getElementById('test2-result').innerHTML = '<p class="error">✗ Test 2 échoué : ' + e.message + '</p>';
            console.error('Test 2 ERREUR:', e);
        }
        </script>
    </div>

    <div class="test-box">
        <h2>Test 3: Base64 (solution ultra-sécurisée)</h2>
        <?php $json_b64 = base64_encode(json_encode($conversations)); ?>
        <div id="json-holder-b64" data-conversations-b64="<?= $json_b64 ?>"></div>

        <div id="test3-result"></div>
        <script>
        try {
            const holderB64 = document.getElementById('json-holder-b64');
            const jsonStr = atob(holderB64.dataset.conversationsB64);
            const conversationsData3 = JSON.parse(jsonStr);
            document.getElementById('test3-result').innerHTML = '<p class="success">✓ Test 3 réussi ! JSON en base64.</p>';
            console.log('Test 3 OK:', conversationsData3);
        } catch (e) {
            document.getElementById('test3-result').innerHTML = '<p class="error">✗ Test 3 échoué : ' + e.message + '</p>';
            console.error('Test 3 ERREUR:', e);
        }
        </script>
    </div>

    <div class="test-box">
        <h2>Analyse des messages problématiques</h2>
        <p>Messages contenant des caractères spéciaux :</p>
        <ul>
        <?php
        foreach ($conversations as $conv) {
            $msg = $conv['last_message'];
            $hasSpecial = preg_match('/["\'\`\\\\]/', $msg);
            $hasNewline = preg_match('/[\r\n]/', $msg);
            $hasUnicode = preg_match('/[^\x20-\x7E]/', $msg);

            if ($hasSpecial || $hasNewline || $hasUnicode) {
                echo '<li>';
                echo '<strong>' . htmlspecialchars($conv['sender']) . '</strong>: ';
                echo '<code>' . htmlspecialchars(substr($msg, 0, 50)) . '...</code>';
                echo ' <span style="color:orange;">';
                if ($hasSpecial) echo '[Guillemets/Apostrophes] ';
                if ($hasNewline) echo '[Retours ligne] ';
                if ($hasUnicode) echo '[Unicode] ';
                echo '</span>';
                echo '</li>';
            }
        }
        ?>
        </ul>
    </div>

    <div class="test-box">
        <h2>Recommandation</h2>
        <p id="recommendation"></p>
        <script>
        const results = [
            document.getElementById('test1-result').innerHTML.includes('✓'),
            document.getElementById('test2-result').innerHTML.includes('✓'),
            document.getElementById('test3-result').innerHTML.includes('✓')
        ];

        let recommendation = '';
        if (!results[0] && results[1]) {
            recommendation = '<strong class="success">✓ Utilisez la méthode 2 (JSON dans attribut data)</strong>';
        } else if (!results[0] && !results[1] && results[2]) {
            recommendation = '<strong class="success">✓ Utilisez la méthode 3 (Base64)</strong>';
        } else if (results[0]) {
            recommendation = '<strong class="success">✓ La méthode actuelle fonctionne - le problème est ailleurs</strong>';
        } else {
            recommendation = '<strong class="error">✗ Problème critique - aucune méthode ne fonctionne</strong>';
        }

        document.getElementById('recommendation').innerHTML = recommendation;
        </script>
    </div>

    <div class="test-box">
        <h2>Instructions de correction</h2>
        <p>Si le Test 1 échoue mais que le Test 2 ou 3 réussit, modifiez recus.php :</p>

        <h3>Solution avec attribut data (Test 2) :</h3>
        <pre>
// Dans recus.php, remplacez:
conversationsData = &lt;?= json_encode(...) ?&gt;;

// Par:
&lt;div id="conversations-data"
     data-json='&lt;?= htmlspecialchars(json_encode($conversations), ENT_QUOTES, 'UTF-8') ?&gt;'
     style="display:none;"&gt;&lt;/div&gt;

&lt;script&gt;
const conversationsData = JSON.parse(
    document.getElementById('conversations-data').dataset.json
);
&lt;/script&gt;
        </pre>

        <h3>Solution avec base64 (Test 3) :</h3>
        <pre>
// Dans recus.php, remplacez:
conversationsData = &lt;?= json_encode(...) ?&gt;;

// Par:
&lt;div id="conversations-data"
     data-json-b64="&lt;?= base64_encode(json_encode($conversations)) ?&gt;"
     style="display:none;"&gt;&lt;/div&gt;

&lt;script&gt;
const conversationsData = JSON.parse(
    atob(document.getElementById('conversations-data').dataset.jsonB64)
);
&lt;/script&gt;
        </pre>
    </div>
</body>
</html>