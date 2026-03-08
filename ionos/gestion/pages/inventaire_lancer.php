<?php
/**
 * Lancer un inventaire — Choix du logement
 */
include '../config.php';
include '../pages/menu.php';

// Auto-migration : ajouter intervenant_id si absent
try { $conn->exec("ALTER TABLE sessions_inventaire ADD COLUMN intervenant_id INT DEFAULT NULL AFTER logement_id"); } catch (PDOException $e) { error_log('inventaire_lancer.php: ' . $e->getMessage()); }

$intervenants = $conn->query("SELECT id, nom FROM intervenant WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$logements = $conn->query("
    SELECT l.id, l.nom_du_logement,
           (SELECT COUNT(*) FROM sessions_inventaire s WHERE s.logement_id = l.id AND s.statut = 'en_cours') AS sessions_en_cours
    FROM liste_logements l
    WHERE l.actif = 1
    ORDER BY l.nom_du_logement
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lancer un inventaire</title>
    <style>
        .launch-container {
            max-width: 520px;
            margin: 0 auto;
            padding: 0 12px 30px;
        }
        .launch-header {
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            text-align: center;
            padding: 25px 15px;
            border-radius: 15px;
            margin: 15px 0 20px;
        }
        .launch-header h2 { margin: 0 0 5px; font-size: 1.3em; }
        .launch-header p { margin: 0; opacity: 0.85; font-size: 0.92em; }
        .launch-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .launch-card label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
            font-size: 1.05em;
        }
        .launch-card select {
            width: 100%;
            padding: 14px 12px;
            font-size: 1.1em;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #fafafa;
            margin-bottom: 18px;
            appearance: auto;
        }
        .launch-card select:focus { border-color: #43a047; outline: none; }
        .warning-encours {
            background: #fff3e0;
            border-left: 3px solid #ff9800;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.88em;
            color: #e65100;
            margin-bottom: 15px;
            display: none;
        }
        .btn-launch {
            width: 100%;
            padding: 16px;
            font-size: 1.15em;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #43a047, #388e3c);
            color: #fff;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .btn-launch:active { transform: scale(0.97); }
        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #1976d2;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="launch-container">
    <div class="launch-header">
        <h2><i class="fas fa-plus-circle"></i> Nouvel inventaire</h2>
        <p>Selectionnez un logement pour commencer</p>
    </div>

    <div class="launch-card">
        <form action="inventaire_creer_session.php" method="POST">
            <label for="logement_id"><i class="fas fa-home"></i> Logement</label>
            <select name="logement_id" id="logement_id" required onchange="checkEnCours()">
                <option value="">-- Selectionnez --</option>
                <?php foreach ($logements as $l): ?>
                <option value="<?= $l['id'] ?>" data-encours="<?= $l['sessions_en_cours'] ?>">
                    <?= htmlspecialchars($l['nom_du_logement']) ?>
                    <?= $l['sessions_en_cours'] > 0 ? ' (session en cours)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>

            <div class="warning-encours" id="warningEnCours">
                <i class="fas fa-exclamation-triangle"></i>
                Ce logement a deja une session d'inventaire en cours.
                Vous pouvez quand meme en lancer une nouvelle.
            </div>

            <label for="intervenant_id"><i class="fas fa-user"></i> Attribuer a (optionnel)</label>
            <select name="intervenant_id" id="intervenant_id" style="width:100%;padding:14px 12px;font-size:1.1em;border:2px solid #e0e0e0;border-radius:10px;background:#fafafa;margin-bottom:18px;appearance:auto;">
                <option value="">-- Moi-meme --</option>
                <?php foreach ($intervenants as $int): ?>
                    <option value="<?= $int['id'] ?>"><?= htmlspecialchars($int['nom']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-launch">
                <i class="fas fa-play-circle"></i> Lancer l'inventaire
            </button>
        </form>
    </div>
    <a href="inventaire.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<script>
function checkEnCours() {
    var select = document.getElementById('logement_id');
    var option = select.options[select.selectedIndex];
    var warning = document.getElementById('warningEnCours');
    warning.style.display = (option && option.dataset.encours > 0) ? 'block' : 'none';
}
</script>
</body>
</html>
