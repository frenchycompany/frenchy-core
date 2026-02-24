<?php
/**
 * PlanningService - Logique métier pour la gestion du planning
 */

class PlanningService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère le planning pour une période donnée
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @return array
     */
    public function getPlanningPeriode($dateDebut, $dateFin)
    {
        $query = "
            SELECT p.*,
                   l.nom as logement_nom,
                   l.adresse as logement_adresse,
                   c.nom as conducteur_nom,
                   fm1.nom as femme_menage_1_nom,
                   fm2.nom as femme_menage_2_nom,
                   lav.nom as laverie_nom
            FROM planning p
            LEFT JOIN liste_logements l ON p.logement_id = l.id
            LEFT JOIN intervenant c ON p.conducteur_id = c.id
            LEFT JOIN intervenant fm1 ON p.femme_menage_1_id = fm1.id
            LEFT JOIN intervenant fm2 ON p.femme_menage_2_id = fm2.id
            LEFT JOIN intervenant lav ON p.laverie_id = lav.id
            WHERE p.date_intervention BETWEEN ? AND ?
            ORDER BY p.date_intervention ASC, p.heure_intervention ASC
        ";

        return $this->db->query($query, [$dateDebut, $dateFin]);
    }

    /**
     * Récupère le planning du jour
     *
     * @return array
     */
    public function getPlanningJour($date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        return $this->getPlanningPeriode($date, $date);
    }

    /**
     * Crée une intervention dans le planning
     *
     * @param array $data
     * @return int|false
     */
    public function creerIntervention($data)
    {
        $intervention = [
            'logement_id' => $data['logement_id'],
            'date_intervention' => $data['date_intervention'],
            'heure_intervention' => $data['heure_intervention'] ?? null,
            'conducteur_id' => $data['conducteur_id'] ?? null,
            'femme_menage_1_id' => $data['femme_menage_1_id'] ?? null,
            'femme_menage_2_id' => $data['femme_menage_2_id'] ?? null,
            'laverie_id' => $data['laverie_id'] ?? null,
            'statut' => $data['statut'] ?? 'A Faire',
            'early_checkin' => $data['early_checkin'] ?? 0,
            'late_checkout' => $data['late_checkout'] ?? 0,
            'baby_bed' => $data['baby_bed'] ?? 0,
            'bonus' => $data['bonus'] ?? 0,
            'remarques' => $data['remarques'] ?? null,
        ];

        return $this->db->insert('planning', $intervention);
    }

    /**
     * Met à jour une intervention
     *
     * @param int $id
     * @param array $data
     * @return int
     */
    public function mettreAJourIntervention($id, $data)
    {
        return $this->db->update('planning', $data, ['id' => $id]);
    }

    /**
     * Affecte un intervenant à une intervention
     *
     * @param int $interventionId
     * @param string $role (conducteur, femme_menage_1, femme_menage_2, laverie)
     * @param int $intervenantId
     * @return int
     */
    public function affecterIntervenant($interventionId, $role, $intervenantId)
    {
        $roleMap = [
            'conducteur' => 'conducteur_id',
            'femme_menage_1' => 'femme_menage_1_id',
            'femme_menage_2' => 'femme_menage_2_id',
            'laverie' => 'laverie_id',
        ];

        if (!isset($roleMap[$role])) {
            return false;
        }

        return $this->db->update('planning', [
            $roleMap[$role] => $intervenantId
        ], ['id' => $interventionId]);
    }

    /**
     * Change le statut d'une intervention
     *
     * @param int $id
     * @param string $statut (A Faire, En Cours, Fait)
     * @return int
     */
    public function changerStatut($id, $statut)
    {
        $statutsValides = ['A Faire', 'En Cours', 'Fait'];

        if (!in_array($statut, $statutsValides)) {
            return false;
        }

        return $this->db->update('planning', ['statut' => $statut], ['id' => $id]);
    }

    /**
     * Récupère les interventions d'un intervenant pour une période
     *
     * @param int $intervenantId
     * @param string $dateDebut
     * @param string $dateFin
     * @return array
     */
    public function getInterventionsIntervenant($intervenantId, $dateDebut, $dateFin)
    {
        $query = "
            SELECT p.*, l.nom as logement_nom
            FROM planning p
            LEFT JOIN liste_logements l ON p.logement_id = l.id
            WHERE (p.conducteur_id = ?
                   OR p.femme_menage_1_id = ?
                   OR p.femme_menage_2_id = ?
                   OR p.laverie_id = ?)
            AND p.date_intervention BETWEEN ? AND ?
            ORDER BY p.date_intervention ASC
        ";

        return $this->db->query($query, [
            $intervenantId, $intervenantId, $intervenantId, $intervenantId,
            $dateDebut, $dateFin
        ]);
    }

    /**
     * Calcule la rémunération pour une intervention
     *
     * @param array $intervention
     * @return array
     */
    public function calculerRemuneration($intervention)
    {
        // Récupérer les tarifs depuis la table role
        $roles = $this->db->query("SELECT * FROM role");
        $tarifs = [];

        foreach ($roles as $role) {
            $tarifs[$role['id']] = $role['prix'];
        }

        $remuneration = [
            'conducteur' => 0,
            'femme_menage_1' => 0,
            'femme_menage_2' => 0,
            'laverie' => 0,
            'bonus_par_personne' => 0,
        ];

        // Bonus de 10€ à répartir entre les femmes de ménage
        if ($intervention['bonus'] == 1) {
            $nbFemmesMenage = 0;
            if ($intervention['femme_menage_1_id']) $nbFemmesMenage++;
            if ($intervention['femme_menage_2_id']) $nbFemmesMenage++;

            if ($nbFemmesMenage > 0) {
                $remuneration['bonus_par_personne'] = 10 / $nbFemmesMenage;
            }
        }

        return $remuneration;
    }

    /**
     * Supprime une intervention
     *
     * @param int $id
     * @return int
     */
    public function supprimerIntervention($id)
    {
        return $this->db->delete('planning', ['id' => $id]);
    }

    /**
     * Récupère les statistiques du planning
     *
     * @param string $dateDebut
     * @param string $dateFin
     * @return array
     */
    public function getStatistiques($dateDebut, $dateFin)
    {
        $stats = [];

        // Nombre total d'interventions
        $stats['total'] = $this->db->queryValue(
            "SELECT COUNT(*) FROM planning WHERE date_intervention BETWEEN ? AND ?",
            [$dateDebut, $dateFin]
        );

        // Par statut
        $stats['par_statut'] = $this->db->query(
            "SELECT statut, COUNT(*) as nombre
             FROM planning
             WHERE date_intervention BETWEEN ? AND ?
             GROUP BY statut",
            [$dateDebut, $dateFin]
        );

        // Par logement
        $stats['par_logement'] = $this->db->query(
            "SELECT l.nom, COUNT(*) as nombre
             FROM planning p
             LEFT JOIN liste_logements l ON p.logement_id = l.id
             WHERE p.date_intervention BETWEEN ? AND ?
             GROUP BY l.nom
             ORDER BY nombre DESC
             LIMIT 10",
            [$dateDebut, $dateFin]
        );

        return $stats;
    }
}
