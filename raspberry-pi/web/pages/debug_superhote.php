<?php
/**
 * Diagnostic Superhote - Groupes et execution
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic Superhote</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #eee; }
        .section { background: #16213e; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .section h3 { color: #e94560; margin-top: 0; }
        .ok { color: #4ecca3; }
        .error { color: #ff6b6b; }
        .warn { color: #feca57; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #333; }
        th { color: #e94560; }
        pre { background: #0f0f23; padding: 10px; overflow-x: auto; }
        button { padding: 10px 20px; background: #e94560; color: white; border: none; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <h1>🔧 Diagnostic Superhote</h1>

    <div class="section">
        <h3>1. Groupes configures</h3>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM superhote_groups ORDER BY nom");
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($groups)) {
                echo '<p class="warn">⚠️ Aucun groupe configure</p>';
            } else {
                echo '<table><tr><th>ID</th><th>Nom</th><th>Description</th><th>Ref ID</th><th>Prix Plancher</th><th>Prix Standard</th></tr>';
                foreach ($groups as $g) {
                    echo '<tr>';
                    echo '<td>'.$g['id'].'</td>';
                    echo '<td><strong>'.$g['nom'].'</strong></td>';
                    echo '<td>'.$g['description'].'</td>';
                    echo '<td>'.$g['logement_reference_id'].'</td>';
                    echo '<td>'.$g['prix_plancher'].'</td>';
                    echo '<td>'.$g['prix_standard'].'</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Erreur: '.$e->getMessage().'</p>';
        }
        ?>
    </div>

    <div class="section">
        <h3>2. Logements et leur groupe</h3>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT ll.nom_du_logement, sc.superhote_property_id, sc.groupe, sc.is_active,
                       sc.prix_plancher, sc.prix_standard
                FROM liste_logements ll
                LEFT JOIN superhote_config sc ON ll.id = sc.logement_id
                ORDER BY sc.groupe, ll.nom_du_logement
            ");
            $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo '<table><tr><th>Logement</th><th>Superhote ID</th><th>Groupe</th><th>Actif</th><th>Prix Plancher</th><th>Prix Standard</th></tr>';
            foreach ($logements as $l) {
                $groupClass = $l['groupe'] ? 'ok' : 'warn';
                echo '<tr>';
                echo '<td>'.$l['nom_du_logement'].'</td>';
                echo '<td>'.($l['superhote_property_id'] ?: '<span class="error">Non config</span>').'</td>';
                echo '<td class="'.$groupClass.'">'.($l['groupe'] ?: '-').'</td>';
                echo '<td>'.($l['is_active'] ? '✅' : '❌').'</td>';
                echo '<td>'.$l['prix_plancher'].'</td>';
                echo '<td>'.$l['prix_standard'].'</td>';
                echo '</tr>';
            }
            echo '</table>';
        } catch (PDOException $e) {
            echo '<p class="error">Erreur: '.$e->getMessage().'</p>';
        }
        ?>
    </div>

    <div class="section">
        <h3>3. Test d'execution Python</h3>
        <?php
        $scriptDir = dirname(dirname(__DIR__)) . '/scripts/selenium';
        $scriptPath = $scriptDir . '/run_scheduled_update.py';
        $logPath = dirname(dirname(__DIR__)) . '/logs';

        echo '<p>Script: <code>'.$scriptPath.'</code></p>';
        echo '<p>Existe: '.( file_exists($scriptPath) ? '<span class="ok">✅ Oui</span>' : '<span class="error">❌ Non</span>').'</p>';
        echo '<p>Logs dir: <code>'.$logPath.'</code></p>';
        echo '<p>Existe: '.( is_dir($logPath) ? '<span class="ok">✅ Oui</span>' : '<span class="error">❌ Non</span>').'</p>';
        echo '<p>Writable: '.( is_writable($logPath) ? '<span class="ok">✅ Oui</span>' : '<span class="error">❌ Non</span>').'</p>';

        // Test Python
        echo '<h4>Test Python:</h4>';
        $pythonPath = '/usr/bin/python3';
        echo '<p>Python path: <code>'.$pythonPath.'</code></p>';

        $output = [];
        $returnCode = 0;
        exec($pythonPath . ' --version 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo '<p class="ok">✅ Python fonctionne: ' . implode(' ', $output) . '</p>';
        } else {
            echo '<p class="error">❌ Python error: ' . implode(' ', $output) . '</p>';
        }

        // Test whoami
        $output = [];
        exec('whoami', $output);
        echo '<p>Utilisateur web: <code>' . implode('', $output) . '</code></p>';

        // Test if we can write to log
        $testLog = $logPath . '/test_write.log';
        if (file_put_contents($testLog, date('Y-m-d H:i:s') . " - Test write\n", FILE_APPEND)) {
            echo '<p class="ok">✅ Ecriture logs OK</p>';
        } else {
            echo '<p class="error">❌ Impossible d\'ecrire dans les logs</p>';
        }
        ?>

        <h4>Test lancement manuel:</h4>
        <form method="post">
            <input type="hidden" name="test_run" value="1">
            <button type="submit">🚀 Tester le lancement</button>
        </form>

        <?php
        if (isset($_POST['test_run'])) {
            echo '<h4>Resultat du test:</h4>';

            $logFile = "$logPath/test_manual_run.log";
            $cmd = "cd $scriptDir && $pythonPath run_scheduled_update.py --dry-run 2>&1";

            echo '<p>Commande: <code>'.$cmd.'</code></p>';

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            echo '<p>Return code: <code>'.$returnCode.'</code></p>';
            echo '<pre>'.htmlspecialchars(implode("\n", $output)).'</pre>';
        }
        ?>
    </div>

    <div class="section">
        <h3>4. Derniers logs</h3>
        <?php
        $manualLog = $logPath . '/manual_run.log';
        if (file_exists($manualLog)) {
            $content = file_get_contents($manualLog);
            $lines = explode("\n", $content);
            $lastLines = array_slice($lines, -50);
            echo '<p>Fichier: <code>'.$manualLog.'</code></p>';
            echo '<pre>'.htmlspecialchars(implode("\n", $lastLines)).'</pre>';
        } else {
            echo '<p class="warn">Pas de log manuel trouve</p>';
        }
        ?>
    </div>

</body>
</html>
