<?php
include '../config.php'; // Connexion à la base de données

$logement_id = filter_input(INPUT_GET, 'logement_id', FILTER_VALIDATE_INT);
if (!$logement_id) {
    echo json_encode(['error' => 'ID du logement invalide']);
    exit;
}

try {
    // Récupérer les données du logement
    $stmt = $conn->prepare("SELECT * FROM description_logements WHERE logement_id = ?");
    $stmt->execute([$logement_id]);
    $logement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$logement) {
        echo json_encode(['error' => 'Aucune fiche descriptive trouvée.']);
        exit;
    }

    // Récupérer les critères, leurs poids, et leur temps estimé par unité (en secondes)
    $stmt = $conn->query("SELECT critere, valeur, temps_par_unite FROM poids_criteres");
    $criteres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialisations
    $poids_menage = 0; // En points
    $temps_estime = 0; // En secondes
    $temps_passe_moyen_valeur = 0; // Pour stocker la valeur associée au critère "temps_passe_moyen"
    $details_calcul_temps = []; // Tableau pour stocker les détails du calcul du temps

    // Calculs
    foreach ($criteres as $critere) {
        $key = $critere['critere']; // Nom du critère
        $poids_par_unite = $critere['valeur'];
        $temps_par_unite = $critere['temps_par_unite']; // En secondes

        // Identifier et stocker la valeur du critère "temps_passe_moyen"
        if ($key === 'temps_passe_moyen') {
            $temps_passe_moyen_valeur = $poids_par_unite; // Récupérer la valeur
            continue; // Ne pas inclure ce critère dans le calcul principal
        }

        // Vérifie si la clé existe dans la description du logement
        if (isset($logement[$key])) {
            $quantite = $logement[$key]; // Quantité associée au critère
            $poids_menage += $quantite * $poids_par_unite; // Ajouter au poids total
            $temps_critere = $quantite * $temps_par_unite; // Temps total pour ce critère en secondes
            $temps_estime += $temps_critere; // Ajouter au temps total estimé

            // Ajouter le détail au tableau
            $details_calcul_temps[] = [
                'critere' => $key,
                'quantite' => $quantite,
                'temps_par_unite' => $temps_par_unite,
                'temps_total_critere' => $temps_critere
            ];
        }
    }

    // Convertir le temps estimé en minutes
    $temps_estime_minutes = $temps_estime / 60;

    // Appliquer la soustraction de temps_passe_moyen
    $poids_menage -= $temps_passe_moyen_valeur;

    // Appliquer le multiplicateur (facteur de difficulté)
    $multiplicateur = $logement['multiplicateur'] ?? 1.0;
    $poids_menage *= $multiplicateur;

    // Comparaison avec le temps moyen passé
    $temps_passe_moyen = $logement['temps_passe_moyen'] ?? 0;

    $evaluation = '';
    if ($temps_passe_moyen > $temps_estime_minutes) {
        $evaluation = 'Temps moyen passé plus long que l’estimation.';
    } elseif ($temps_passe_moyen < $temps_estime_minutes) {
        $evaluation = 'Temps moyen passé plus court que l’estimation.';
    } else {
        $evaluation = 'Temps moyen passé équivalent à l’estimation.';
    }

    // Retourner les résultats au format JSON
    echo json_encode([
        'poids_menage' => round($poids_menage, 2), // Poids en points
        'temps_estime' => round($temps_estime_minutes, 2), // Temps total estimé en minutes
        'temps_passe_moyen' => round($temps_passe_moyen, 2), // Temps moyen passé pour comparaison
        'evaluation' => $evaluation,
        'details_temps' => array_map(function ($detail) {
            return [
                'critere' => $detail['critere'],
                'quantite' => $detail['quantite'],
                'temps_par_unite' => $detail['temps_par_unite'] . ' sec',
                'temps_total_critere' => $detail['temps_total_critere'] . ' sec'
            ];
        }, $details_calcul_temps) // Convertir les détails en secondes pour l'affichage
    ]);
} catch (PDOException $e) {
    error_log('calculate_poid_menage.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Une erreur interne est survenue.']);
}
