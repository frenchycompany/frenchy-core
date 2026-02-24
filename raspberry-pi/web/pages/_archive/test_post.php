<?php
// Test simple pour voir si le POST fonctionne
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test POST simple</h1>";

echo "<h2>Données POST reçues:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<p style='color: green; font-weight: bold;'>✓ POST détecté !</p>";

    if (!empty($_POST['receiver'])) {
        echo "<p>✓ receiver: " . htmlspecialchars($_POST['receiver']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ receiver manquant</p>";
    }

    if (!empty($_POST['prenom'])) {
        echo "<p>✓ prenom: " . htmlspecialchars($_POST['prenom']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ prenom manquant</p>";
    }

    if (!empty($_POST['res_id'])) {
        echo "<p>✓ res_id: " . htmlspecialchars($_POST['res_id']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ res_id manquant</p>";
    }

    // Vérifier quel bouton a été cliqué
    if (isset($_POST['send_sms_checkout'])) {
        echo "<p style='background: yellow; padding: 10px;'><strong>✓ BOUTON CHECKOUT DÉTECTÉ !</strong></p>";
    } elseif (isset($_POST['send_sms_accueil'])) {
        echo "<p style='background: yellow; padding: 10px;'><strong>✓ BOUTON ACCUEIL DÉTECTÉ !</strong></p>";
    } elseif (isset($_POST['send_sms_preparation'])) {
        echo "<p style='background: yellow; padding: 10px;'><strong>✓ BOUTON PREPARATION DÉTECTÉ !</strong></p>";
    } else {
        echo "<p style='color: red; background: pink; padding: 10px;'><strong>❌ AUCUN BOUTON DÉTECTÉ !</strong></p>";
        echo "<p>Boutons présents dans POST:</p>";
        echo "<ul>";
        foreach ($_POST as $key => $value) {
            echo "<li>" . htmlspecialchars($key) . " = " . htmlspecialchars($value) . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p>Aucune requête POST - utilisez le formulaire ci-dessous</p>";
}
?>

<hr>
<h2>Formulaire de test (identique à reservation_list.php)</h2>

<form method="POST" class="sms-send-form">
    <input type="hidden" name="res_id" value="123">
    <input type="hidden" name="receiver" value="+33612345678">
    <input type="hidden" name="prenom" value="Jean">
    <input type="hidden" name="nom" value="Dupont">

    <p><button type="submit" name="send_sms_checkout" value="1" class="sms-send-btn">Test Checkout</button></p>
    <p><button type="submit" name="send_sms_accueil" value="1" class="sms-send-btn">Test Accueil</button></p>
    <p><button type="submit" name="send_sms_preparation" value="1" class="sms-send-btn">Test Préparation</button></p>
</form>

<hr>
<h2>Test AVEC le JavaScript de reservation_list.php</h2>
<p>Cliquez sur un bouton ci-dessous pour tester avec le même JavaScript que reservation_list.php:</p>

<form method="POST" class="sms-send-form">
    <input type="hidden" name="res_id" value="456">
    <input type="hidden" name="receiver" value="+33687654321">
    <input type="hidden" name="prenom" value="Marie">
    <input type="hidden" name="nom" value="Martin">

    <p><button type="submit" name="send_sms_checkout" value="1" class="btn btn-warning sms-send-btn">Test avec JS - Checkout</button></p>
</form>

<script>
// COPIE EXACTE du JavaScript de reservation_list.php
document.addEventListener('DOMContentLoaded', function() {
    const smsForms = document.querySelectorAll('.sms-send-form');

    smsForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.sms-send-btn');

            // Vérifier si le bouton est déjà désactivé
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }

            // Désactiver le bouton
            submitBtn.disabled = true;

            // Changer l'apparence et le texte
            submitBtn.classList.remove('btn-warning');
            submitBtn.classList.add('btn-secondary');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

            // Optionnel : réactiver après 10 secondes si la page ne se recharge pas
            setTimeout(() => {
                if (!submitBtn.disabled) return;
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-secondary');
                submitBtn.classList.add('btn-warning');
                submitBtn.innerHTML = 'Envoyer';
            }, 10000);
        });
    });
});
</script>

<style>
.btn {
    padding: 8px 15px;
    cursor: pointer;
    border: none;
    border-radius: 4px;
}
.btn-warning {
    background-color: #ffc107;
    color: #000;
}
.btn-secondary {
    background-color: #6c757d;
    color: #fff;
}
</style>
