<?php
// Script de diagnostic pour reservation_list.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/template_helper.php';

echo "<h1>Diagnostic envoi SMS - reservation_list.php</h1>";

echo "<h2>1. Vérification de la requête</h2>";
echo "<p>Méthode: " . $_SERVER['REQUEST_METHOD'] . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Données POST reçues:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    echo "<h3>Vérification des champs obligatoires:</h3>";
    echo "<ul>";
    echo "<li>receiver: " . (!empty($_POST['receiver']) ? '✓ ' . htmlspecialchars($_POST['receiver']) : '❌ MANQUANT') . "</li>";
    echo "<li>prenom: " . (!empty($_POST['prenom']) ? '✓ ' . htmlspecialchars($_POST['prenom']) : '❌ MANQUANT') . "</li>";
    echo "<li>res_id: " . (!empty($_POST['res_id']) ? '✓ ' . htmlspecialchars($_POST['res_id']) : '❌ MANQUANT') . "</li>";
    echo "</ul>";

    echo "<h3>Détection du type de SMS:</h3>";
    echo "<ul>";
    echo "<li>send_sms_checkout: " . (isset($_POST['send_sms_checkout']) ? '✓ DÉTECTÉ' : '❌') . "</li>";
    echo "<li>send_sms_accueil: " . (isset($_POST['send_sms_accueil']) ? '✓ DÉTECTÉ' : '❌') . "</li>";
    echo "<li>send_sms_preparation: " . (isset($_POST['send_sms_preparation']) ? '✓ DÉTECTÉ' : '❌') . "</li>";
    echo "</ul>";

    // Déterminer le template
    $tplName = '';
    if (isset($_POST['send_sms_checkout'])) {
        $tplName = 'checkout';
    } elseif (isset($_POST['send_sms_accueil'])) {
        $tplName = 'accueil';
    } elseif (isset($_POST['send_sms_preparation'])) {
        $tplName = 'preparation';
    }

    echo "<p><strong>Template détecté: " . ($tplName ? htmlspecialchars($tplName) : "❌ AUCUN") . "</strong></p>";

    if ($tplName) {
        echo "<h3>Vérification du template en base:</h3>";
        $logement_id = !empty($_POST['logement_id']) ? (int)$_POST['logement_id'] : null;

        $message = get_personalized_sms($pdo, $tplName, [
            'prenom' => $_POST['prenom'] ?? 'Test',
            'nom' => $_POST['nom'] ?? ''
        ], $logement_id);

        if ($message !== null) {
            echo "<p>✓ Template trouvé et message généré:</p>";
            echo "<pre>" . htmlspecialchars($message) . "</pre>";
        } else {
            echo "<p>❌ Template NON trouvé ou erreur</p>";

            // Vérifier si le template existe en base
            echo "<h4>Vérification dans la base:</h4>";
            try {
                $stmt = $pdo->prepare("SELECT * FROM sms_templates WHERE name = :name");
                $stmt->execute([':name' => $tplName]);
                $template = $stmt->fetch();

                if ($template) {
                    echo "<p>✓ Template existe dans sms_templates:</p>";
                    echo "<pre>";
                    print_r($template);
                    echo "</pre>";
                } else {
                    echo "<p>❌ Template N'EXISTE PAS dans sms_templates</p>";
                }
            } catch (PDOException $e) {
                echo "<p>❌ ERREUR SQL: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }

} else {
    echo "<p>Aucune requête POST - Formulaire de test ci-dessous</p>";
}

echo "<h2>2. Vérification des templates dans la base</h2>";
try {
    $stmt = $pdo->query("SELECT name, LEFT(template, 80) as preview FROM sms_templates");
    $templates = $stmt->fetchAll();

    if (empty($templates)) {
        echo "<p>❌ AUCUN template dans la base de données</p>";
    } else {
        echo "<ul>";
        foreach ($templates as $t) {
            echo "<li><strong>{$t['name']}</strong>: " . htmlspecialchars($t['preview']) . "...</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p>❌ ERREUR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>3. Formulaire de test</h2>";
?>

<form method="POST">
    <p><label>Téléphone: <input type="text" name="receiver" value="+33612345678"></label></p>
    <p><label>Prénom: <input type="text" name="prenom" value="Jean"></label></p>
    <p><label>Nom: <input type="text" name="nom" value="Dupont"></label></p>
    <p><label>ID Réservation: <input type="text" name="res_id" value="1"></label></p>

    <h4>Choisir le type de SMS:</h4>
    <p>
        <button type="submit" name="send_sms_checkout" value="1">Test Checkout</button>
        <button type="submit" name="send_sms_accueil" value="1">Test Accueil</button>
        <button type="submit" name="send_sms_preparation" value="1">Test Préparation</button>
    </p>
</form>

<hr>
<p><a href="reservation_list.php">← Retour à reservation_list.php</a></p>
