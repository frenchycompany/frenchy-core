<?php
include '../config.php'; // Connexion à la base de données
include '../pages/menu.php'; // Inclusion du menu

// Récupération de la liste des intervenants pour le <select>
$intervStmt = $conn->prepare("SELECT id, nom FROM intervenant ORDER BY nom");
$intervStmt->execute();
$intervenantsList = $intervStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des paramètres GET
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?? date('Y-m-d');
$selectedIntervenant = filter_input(INPUT_GET, 'intervenant', FILTER_VALIDATE_INT);
if ($selectedIntervenant === false || $selectedIntervenant === null) {
    $selectedIntervenant = 0; // 0 = Tout le monde
}

// Détermination du domaine pour générer les liens
$domain = env('APP_URL', 'https://gestion.frenchyconciergerie.fr');
$domain = rtrim($domain, '/');

try {
    // Construction dynamique de la requête pour filtrer par intervenant si besoin
    $sql = "
        SELECT 
            p.id AS intervention_id,
            l.nom_du_logement, 
            l.adresse, 
            l.code, 
            p.nombre_de_personnes,
            p.lit_bebe,
            p.nombre_lits_specifique,
            p.early_check_in,
            p.late_check_out,
            p.note,
            p.conducteur,
            p.femme_de_menage_1,
            p.femme_de_menage_2,
            p.laverie,
            c.nom AS conducteur_nom,
            f1.nom AS femme1_nom,
            f2.nom AS femme2_nom,
            lv.nom AS laverie_nom
        FROM planning p
        JOIN liste_logements l ON p.logement_id = l.id
        LEFT JOIN intervenant c  ON p.conducteur = c.id
        LEFT JOIN intervenant f1 ON p.femme_de_menage_1 = f1.id
        LEFT JOIN intervenant f2 ON p.femme_de_menage_2 = f2.id
        LEFT JOIN intervenant lv ON p.laverie = lv.id
        WHERE p.date = :date
    ";
    $params = [':date' => $date];

    if ($selectedIntervenant > 0) {
        // Filtrer si l'intervenant est dans l'une des colonnes de Planning
        $sql .= " AND (
            p.conducteur = :interv
            OR p.femme_de_menage_1 = :interv
            OR p.femme_de_menage_2 = :interv
            OR p.laverie = :interv
        )";
        $params[':interv'] = $selectedIntervenant;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Préparation de la requête pour récupérer le token de validation
    $tokenStmt = $conn->prepare("
        SELECT token
        FROM intervention_tokens
        WHERE intervention_id = ?
          AND used = 0
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 1
    ");

    // Génération du texte formaté pour WhatsApp (texte brut, pas de htmlspecialchars)
    $texte_combined = "📅 *Planning des interventions - " . date('d/m/Y', strtotime($date)) . "*\n\n";

    foreach ($interventions as $intervention) {
        $texte_combined .= "🏠 *Logement* : " . $intervention['nom_du_logement'] . "\n";
        $texte_combined .= "👥 *Personnes* : " . $intervention['nombre_de_personnes'] . "\n";
        $texte_combined .= "📍 *Adresse* : " . $intervention['adresse'] . "\n";
        $texte_combined .= "🔑 *Code* : " . $intervention['code'] . "\n";

        // Particularités
        $extras = [];
        if (!empty($intervention['lit_bebe'])) {
            $extras[] = "Lit bébé";
        }
        if (!empty($intervention['nombre_lits_specifique'])) {
            $extras[] = $intervention['nombre_lits_specifique'] . " lits";
        }
        if (!empty($intervention['early_check_in'])) {
            $extras[] = "Early Check-in";
        }
        if (!empty($intervention['late_check_out'])) {
            $extras[] = "Late Check-out";
        }
        if (!empty($extras)) {
            $texte_combined .= "✨ *Particularités* : " . implode(', ', $extras) . "\n";
        }

        if (!empty($intervention['note'])) {
            $texte_combined .= "📝 *Note* : " . $intervention['note'] . "\n";
        }

        // (La section Intervenants a été retirée)

        // Récupération du token et ajout du lien de validation
        $tokenStmt->execute([$intervention['intervention_id']]);
        $tokRow = $tokenStmt->fetch(PDO::FETCH_ASSOC);
        if ($tokRow) {
            $validationLink = $domain . '/pages/validate.php?token=' . $tokRow['token'];
            $texte_combined .= "🔗 *Valider* :\n" . $validationLink . "\n";
        } else {
            $texte_combined .= "🔗 *Valider* : (lien non généré)\n";
        }

        $texte_combined .= "--------------------------------------\n";
    }

    $texte_encoded = urlencode($texte_combined);

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des interventions : " . $e->getMessage());
    $interventions = [];
    $texte_combined = "Aucune intervention trouvée pour le " . htmlspecialchars(date('d/m/Y', strtotime($date))) . ".";
    $texte_encoded = urlencode($texte_combined);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planning – Utilisateur</title>

  <!-- Votre CSS perso -->
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">

  <!-- Bootstrap 5 -->
  <link
    rel="stylesheet"
    integrity="sha384-…"
    crossorigin="anonymous"
  >
</head>

<body>
    <div class="container mt-4">
        <h2 class="text-center">Éditer le Planning du <?= htmlspecialchars(date('d/m/Y', strtotime($date))) ?></h2>

        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="date">Sélectionner une date :</label>
                    <input type="date" id="date" name="date" class="form-control"
                           value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group col-md-4">
                    <label for="intervenant">Intervenant :</label>
                    <select id="intervenant" name="intervenant" class="form-control" onchange="this.form.submit()">
                        <option value="0" <?= $selectedIntervenant == 0 ? 'selected' : '' ?>>Tout le monde</option>
                        <?php foreach ($intervenantsList as $interv): ?>
                            <option value="<?= $interv['id'] ?>"
                                <?= $selectedIntervenant == $interv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($interv['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <table class="table table-striped mt-4">
            <thead class="thead-dark">
                <tr>
                    <th>Logement</th>
                    <th>Adresse/Code</th>
                    <th>Personnes</th>
                    <th>Intervenants</th>
                    <th>Particularités / Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interventions)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucune intervention pour la date sélectionnée.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($interventions as $intervention): ?>
                        <tr>
                            <td><?= htmlspecialchars($intervention['nom_du_logement']) ?></td>
                            <td>
                                <?= htmlspecialchars($intervention['adresse']) ?><br>
                                <strong>Code :</strong> <?= htmlspecialchars($intervention['code']) ?>
                            </td>
                            <td><?= htmlspecialchars($intervention['nombre_de_personnes']) ?></td>
                            <td>
                                <?php
                                $names = [];
                                if ($intervention['conducteur_nom']) {
                                    $names[] = $intervention['conducteur_nom'];
                                }
                                if ($intervention['femme1_nom']) {
                                    $names[] = $intervention['femme1_nom'];
                                }
                                if ($intervention['femme2_nom']) {
                                    $names[] = $intervention['femme2_nom'];
                                }
                                if ($intervention['laverie_nom']) {
                                    $names[] = $intervention['laverie_nom'];
                                }
                                echo !empty($names) ? implode(' — ', $names) : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $particul = [];
                                if (!empty($intervention['lit_bebe'])) {
                                    $particul[] = 'Lit bébé';
                                }
                                if (!empty($intervention['nombre_lits_specifique'])) {
                                    $particul[] = $intervention['nombre_lits_specifique'] . ' lits';
                                }
                                if (!empty($intervention['early_check_in'])) {
                                    $particul[] = 'Early Check-in';
                                }
                                if (!empty($intervention['late_check_out'])) {
                                    $particul[] = 'Late Check-out';
                                }
                                if (!empty($particul)) {
                                    echo '<strong>Particularités :</strong> ' . implode(', ', $particul) . '<br>';
                                }
                                if (!empty($intervention['note'])) {
                                    echo '<strong>Note :</strong> <em>' . htmlspecialchars($intervention['note']) . '</em>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <h3>Texte pour SMS / WhatsApp :</h3>
            <textarea rows="10" class="form-control" readonly><?= htmlspecialchars($texte_combined) ?></textarea>
        </div>

        <div class="text-center mt-4">
            <a href="https://wa.me/?text=<?= $texte_encoded ?>" target="_blank" class="btn btn-success">
                Envoyer sur WhatsApp
            </a>
        </div>
    </div>

 <script
    integrity="sha384-…"
    crossorigin="anonymous"
  ></script>

</body>
</html>
