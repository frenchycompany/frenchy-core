<?php
/**
 * ComptabiliteService - Logique métier pour la comptabilité
 */

class ComptabiliteService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère les écritures comptables pour une période
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @param string $type (CA, Charge, ou null pour tous)
     * @return array
     */
    public function getEcritures($dateDebut, $dateFin, $type = null)
    {
        $query = "
            SELECT c.*,
                   i.nom as intervenant_nom,
                   l.nom as logement_nom
            FROM comptabilite c
            LEFT JOIN intervenant i ON c.intervenant_id = i.id
            LEFT JOIN liste_logements l ON c.logement_id = l.id
            WHERE c.date BETWEEN ? AND ?
        ";

        $params = [$dateDebut, $dateFin];

        if ($type !== null) {
            $query .= " AND c.type = ?";
            $params[] = $type;
        }

        $query .= " ORDER BY c.date DESC";

        return $this->db->query($query, $params);
    }

    /**
     * Crée une écriture comptable
     *
     * @param array $data
     * @return int|false
     */
    public function creerEcriture($data)
    {
        $ecriture = [
            'date' => $data['date'],
            'type' => $data['type'], // CA ou Charge
            'categorie' => $data['categorie'] ?? null,
            'montant' => $data['montant'],
            'description' => $data['description'] ?? null,
            'intervenant_id' => $data['intervenant_id'] ?? null,
            'logement_id' => $data['logement_id'] ?? null,
            'planning_id' => $data['planning_id'] ?? null,
        ];

        return $this->db->insert('comptabilite', $ecriture);
    }

    /**
     * Calcule le bilan pour une période
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @return array
     */
    public function getBilan($dateDebut, $dateFin)
    {
        $bilan = [];

        // Chiffre d'affaires
        $bilan['ca'] = (float) $this->db->queryValue(
            "SELECT COALESCE(SUM(montant), 0) FROM comptabilite
             WHERE type = 'CA' AND date BETWEEN ? AND ?",
            [$dateDebut, $dateFin]
        );

        // Charges
        $bilan['charges'] = (float) $this->db->queryValue(
            "SELECT COALESCE(SUM(montant), 0) FROM comptabilite
             WHERE type = 'Charge' AND date BETWEEN ? AND ?",
            [$dateDebut, $dateFin]
        );

        // Résultat
        $bilan['resultat'] = $bilan['ca'] - $bilan['charges'];

        // Charges par catégorie
        $bilan['charges_par_categorie'] = $this->db->query(
            "SELECT categorie, SUM(montant) as total
             FROM comptabilite
             WHERE type = 'Charge' AND date BETWEEN ? AND ?
             GROUP BY categorie
             ORDER BY total DESC",
            [$dateDebut, $dateFin]
        );

        // CA par logement
        $bilan['ca_par_logement'] = $this->db->query(
            "SELECT l.nom, SUM(c.montant) as total
             FROM comptabilite c
             LEFT JOIN liste_logements l ON c.logement_id = l.id
             WHERE c.type = 'CA' AND c.date BETWEEN ? AND ?
             GROUP BY l.nom
             ORDER BY total DESC",
            [$dateDebut, $dateFin]
        );

        return $bilan;
    }

    /**
     * Calcule la rémunération d'un intervenant pour une période
     *
     * @param int $intervenantId
     * @param string $dateDebut
     * @param string $dateFin
     * @return array
     */
    public function getRemunerationIntervenant($intervenantId, $dateDebut, $dateFin)
    {
        $remuneration = [];

        // Montant total
        $remuneration['total'] = (float) $this->db->queryValue(
            "SELECT COALESCE(SUM(montant), 0) FROM comptabilite
             WHERE intervenant_id = ? AND date BETWEEN ? AND ?",
            [$intervenantId, $dateDebut, $dateFin]
        );

        // Détail par catégorie/type de prestation
        $remuneration['detail'] = $this->db->query(
            "SELECT categorie, SUM(montant) as total, COUNT(*) as nombre
             FROM comptabilite
             WHERE intervenant_id = ? AND date BETWEEN ? AND ?
             GROUP BY categorie",
            [$intervenantId, $dateDebut, $dateFin]
        );

        // Liste des écritures
        $remuneration['ecritures'] = $this->getEcritures($dateDebut, $dateFin, null);
        $remuneration['ecritures'] = array_filter($remuneration['ecritures'], function($e) use ($intervenantId) {
            return $e['intervenant_id'] == $intervenantId;
        });

        return $remuneration;
    }

    /**
     * Génère les écritures comptables depuis le planning
     *
     * @param string $date Date du planning
     * @return int Nombre d'écritures créées
     */
    public function genererDepuisPlanning($date)
    {
        $planningService = new PlanningService();
        $interventions = $planningService->getPlanningJour($date);

        $count = 0;

        foreach ($interventions as $intervention) {
            // Ne générer que pour les interventions terminées
            if ($intervention['statut'] !== 'Fait') {
                continue;
            }

            // Vérifier si déjà généré
            $existe = $this->db->count('comptabilite', ['planning_id' => $intervention['id']]);
            if ($existe > 0) {
                continue;
            }

            // Récupérer les tarifs
            $remuneration = $planningService->calculerRemuneration($intervention);

            // Créer les écritures pour chaque intervenant
            $intervenants = [
                'conducteur' => $intervention['conducteur_id'],
                'femme_menage_1' => $intervention['femme_menage_1_id'],
                'femme_menage_2' => $intervention['femme_menage_2_id'],
                'laverie' => $intervention['laverie_id'],
            ];

            foreach ($intervenants as $role => $intervenantId) {
                if ($intervenantId && isset($remuneration[$role]) && $remuneration[$role] > 0) {
                    $this->creerEcriture([
                        'date' => $intervention['date_intervention'],
                        'type' => 'Charge',
                        'categorie' => ucfirst(str_replace('_', ' ', $role)),
                        'montant' => $remuneration[$role],
                        'description' => "Intervention - {$intervention['logement_nom']}",
                        'intervenant_id' => $intervenantId,
                        'logement_id' => $intervention['logement_id'],
                        'planning_id' => $intervention['id'],
                    ]);
                    $count++;
                }
            }

            // Ajouter les bonus
            if ($remuneration['bonus_par_personne'] > 0) {
                if ($intervention['femme_menage_1_id']) {
                    $this->creerEcriture([
                        'date' => $intervention['date_intervention'],
                        'type' => 'Charge',
                        'categorie' => 'Bonus',
                        'montant' => $remuneration['bonus_par_personne'],
                        'description' => "Bonus - {$intervention['logement_nom']}",
                        'intervenant_id' => $intervention['femme_menage_1_id'],
                        'logement_id' => $intervention['logement_id'],
                        'planning_id' => $intervention['id'],
                    ]);
                    $count++;
                }

                if ($intervention['femme_menage_2_id']) {
                    $this->creerEcriture([
                        'date' => $intervention['date_intervention'],
                        'type' => 'Charge',
                        'categorie' => 'Bonus',
                        'montant' => $remuneration['bonus_par_personne'],
                        'description' => "Bonus - {$intervention['logement_nom']}",
                        'intervenant_id' => $intervention['femme_menage_2_id'],
                        'logement_id' => $intervention['logement_id'],
                        'planning_id' => $intervention['id'],
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Exporte les écritures au format CSV
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @return string Chemin du fichier CSV
     */
    public function exporterCSV($dateDebut, $dateFin)
    {
        $ecritures = $this->getEcritures($dateDebut, $dateFin);

        $filename = "comptabilite_{$dateDebut}_{$dateFin}.csv";
        $filepath = BASE_PATH . "/uploads/" . $filename;

        $fp = fopen($filepath, 'w');

        // En-têtes
        fputcsv($fp, ['Date', 'Type', 'Catégorie', 'Montant', 'Description', 'Intervenant', 'Logement'], ';');

        // Données
        foreach ($ecritures as $ecriture) {
            fputcsv($fp, [
                $ecriture['date'],
                $ecriture['type'],
                $ecriture['categorie'],
                $ecriture['montant'],
                $ecriture['description'],
                $ecriture['intervenant_nom'] ?? '',
                $ecriture['logement_nom'] ?? '',
            ], ';');
        }

        fclose($fp);

        return $filepath;
    }
}
