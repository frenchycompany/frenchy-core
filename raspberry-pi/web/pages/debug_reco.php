<?php
/**
 * Script de diagnostic pour les recommandations
 * Affiche toutes les infos pour comprendre pourquoi les recos ne fonctionnent pas
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$phone = $_GET['phone'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic Recommandations</title>
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
        input { padding: 10px; width: 300px; }
        button { padding: 10px 20px; background: #e94560; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic Recommandations</h1>

    <form method="get">
        <input type="text" name="phone" placeholder="Numero de telephone (ex: +33612345678)" value="<?= htmlspecialchars($phone) ?>">
        <button type="submit">Diagnostiquer</button>
    </form>

<?php if ($phone): ?>
    <?php
    $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
    $phone_0 = preg_replace('/^\+33/', '0', $phone_clean);
    ?>

    <div class="section">
        <h3>1. Numero de telephone</h3>
        <p>Saisi: <strong><?= htmlspecialchars($phone) ?></strong></p>
        <p>Format E164: <strong><?= $phone_clean ?></strong></p>
        <p>Format 0x: <strong><?= $phone_0 ?></strong></p>
    </div>

    <div class="section">
        <h3>2. Recherche reservation</h3>
        <?php
        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.prenom, r.nom, r.telephone, r.date_arrivee, r.date_depart,
                       r.logement_id, r.statut,
                       ll.nom_du_logement, ll.ville, ll.ville_id
                FROM reservation r
                LEFT JOIN liste_logements ll ON r.logement_id = ll.id
                WHERE r.telephone LIKE ? OR r.telephone LIKE ?
                ORDER BY r.date_arrivee DESC
                LIMIT 5
            ");
            $stmt->execute(['%' . substr($phone_clean, -9) . '%', '%' . substr($phone_0, -9) . '%']);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($reservations)) {
                echo '<p class="error">❌ Aucune reservation trouvee pour ce numero</p>';

                // Montrer les dernieres reservations pour debug
                echo '<p class="warn">Dernieres reservations dans la base:</p>';
                $stmt = $pdo->query("SELECT id, prenom, telephone, date_arrivee, date_depart, logement_id FROM reservation ORDER BY id DESC LIMIT 10");
                $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo '<table><tr><th>ID</th><th>Prenom</th><th>Telephone</th><th>Arrivee</th><th>Depart</th><th>Logement ID</th></tr>';
                foreach ($recent as $r) {
                    echo '<tr><td>'.$r['id'].'</td><td>'.$r['prenom'].'</td><td>'.$r['telephone'].'</td><td>'.$r['date_arrivee'].'</td><td>'.$r['date_depart'].'</td><td>'.($r['logement_id'] ?: '<span class="error">NULL</span>').'</td></tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="ok">✅ '.count($reservations).' reservation(s) trouvee(s)</p>';
                echo '<table><tr><th>ID</th><th>Client</th><th>Tel</th><th>Arrivee</th><th>Depart</th><th>Logement ID</th><th>Logement</th><th>Ville</th><th>Ville ID</th></tr>';
                foreach ($reservations as $r) {
                    $logement_ok = $r['logement_id'] ? 'ok' : 'error';
                    $ville_ok = $r['ville_id'] ? 'ok' : 'warn';
                    echo '<tr>';
                    echo '<td>'.$r['id'].'</td>';
                    echo '<td>'.$r['prenom'].' '.$r['nom'].'</td>';
                    echo '<td>'.$r['telephone'].'</td>';
                    echo '<td>'.$r['date_arrivee'].'</td>';
                    echo '<td>'.$r['date_depart'].'</td>';
                    echo '<td class="'.$logement_ok.'">'.($r['logement_id'] ?: 'NULL').'</td>';
                    echo '<td>'.$r['nom_du_logement'].'</td>';
                    echo '<td>'.$r['ville'].'</td>';
                    echo '<td class="'.$ville_ok.'">'.($r['ville_id'] ?: 'NULL').'</td>';
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
        <h3>3. Table villes</h3>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM villes ORDER BY nom");
            $villes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($villes)) {
                echo '<p class="error">❌ Aucune ville configuree</p>';
            } else {
                echo '<p class="ok">✅ '.count($villes).' ville(s)</p>';
                echo '<table><tr><th>ID</th><th>Nom</th></tr>';
                foreach ($villes as $v) {
                    echo '<tr><td>'.$v['id'].'</td><td>'.$v['nom'].'</td></tr>';
                }
                echo '</table>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Table villes n\'existe pas: '.$e->getMessage().'</p>';
        }
        ?>
    </div>

    <div class="section">
        <h3>4. Recommandations par ville</h3>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT v.nom as ville, vr.categorie, COUNT(*) as nb
                FROM ville_recommandations vr
                JOIN villes v ON vr.ville_id = v.id
                WHERE vr.actif = 1
                GROUP BY v.id, vr.categorie
                ORDER BY v.nom, vr.categorie
            ");
            $recos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($recos)) {
                echo '<p class="error">❌ Aucune recommandation configuree</p>';
            } else {
                echo '<p class="ok">✅ Recommandations trouvees:</p>';
                echo '<table><tr><th>Ville</th><th>Categorie</th><th>Nombre</th></tr>';
                foreach ($recos as $r) {
                    echo '<tr><td>'.$r['ville'].'</td><td>'.$r['categorie'].'</td><td>'.$r['nb'].'</td></tr>';
                }
                echo '</table>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">Erreur: '.$e->getMessage().'</p>';
        }
        ?>
    </div>

    <div class="section">
        <h3>5. Logements et leur ville_id</h3>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT ll.id, ll.nom_du_logement, ll.ville, ll.ville_id, v.nom as ville_nom
                FROM liste_logements ll
                LEFT JOIN villes v ON ll.ville_id = v.id
                ORDER BY ll.nom_du_logement
            ");
            $logements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo '<table><tr><th>ID</th><th>Logement</th><th>Ville (texte)</th><th>Ville ID</th><th>Ville (liee)</th></tr>';
            foreach ($logements as $l) {
                $ville_ok = $l['ville_id'] ? 'ok' : 'warn';
                echo '<tr>';
                echo '<td>'.$l['id'].'</td>';
                echo '<td>'.$l['nom_du_logement'].'</td>';
                echo '<td>'.$l['ville'].'</td>';
                echo '<td class="'.$ville_ok.'">'.($l['ville_id'] ?: 'NULL').'</td>';
                echo '<td>'.($l['ville_nom'] ?: '-').'</td>';
                echo '</tr>';
            }
            echo '</table>';
        } catch (PDOException $e) {
            echo '<p class="error">Erreur: '.$e->getMessage().'</p>';
        }
        ?>
    </div>

<?php else: ?>
    <div class="section">
        <h3>Instructions</h3>
        <p>Entre le numero de telephone du sender SMS pour diagnostiquer pourquoi les recommandations ne fonctionnent pas.</p>
        <p>Le diagnostic va verifier:</p>
        <ul>
            <li>Si une reservation existe pour ce numero</li>
            <li>Si la reservation est liee a un logement</li>
            <li>Si le logement a une ville_id configuree</li>
            <li>Si des recommandations existent pour cette ville</li>
        </ul>
    </div>
<?php endif; ?>

</body>
</html>
