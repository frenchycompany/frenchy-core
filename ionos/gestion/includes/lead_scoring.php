<?php
/**
 * Systeme de scoring automatique des leads
 * Score de 0 a 100 base sur la source, les donnees fournies et l'activite
 */

/**
 * Calcule le score d'un lead
 * @param array $lead Donnees du lead (source, email, telephone, surface, revenu, etc.)
 * @param int $nbInteractions Nombre d'interactions enregistrees
 * @return int Score de 0 a 100
 */
function calculateLeadScore(array $lead, int $nbInteractions = 0): int
{
    $score = 0;

    // --- Score par source ---
    $sourceScores = [
        'rdv_site'           => 60,
        'recommandation'     => 50,
        'simulateur'         => 40,
        'formulaire_contact' => 30,
        'landing_page'       => 25,
        'concurrence'        => 20,
        'demarchage'         => 10,
        'autre'              => 5,
    ];
    $score += $sourceScores[$lead['source'] ?? 'autre'] ?? 5;

    // --- Score par donnees fournies ---
    if (!empty($lead['email'])) $score += 5;
    if (!empty($lead['telephone'])) $score += 15;
    if (!empty($lead['nom'])) $score += 5;

    // Donnees bien (simulateur)
    if (($lead['surface'] ?? 0) > 50) $score += 5;
    if (($lead['capacite'] ?? 0) > 4) $score += 3;
    if (($lead['revenu_mensuel_estime'] ?? 0) > 2000) $score += 7;

    // --- Score par activite ---
    if ($nbInteractions > 0) $score += min($nbInteractions * 5, 20);

    // RDV planifie = fort signal
    if (!empty($lead['date_rdv'])) $score += 15;

    // --- Degradation par inactivite ---
    if (!empty($lead['date_derniere_interaction'])) {
        $daysSince = (time() - strtotime($lead['date_derniere_interaction'])) / 86400;
        $weeksInactive = floor($daysSince / 7);
        if ($weeksInactive > 0) {
            $score -= min($weeksInactive * 3, 30);
        }
    } elseif (!empty($lead['created_at'])) {
        $daysSince = (time() - strtotime($lead['created_at'])) / 86400;
        $weeksInactive = floor($daysSince / 7);
        if ($weeksInactive > 1) {
            $score -= min($weeksInactive * 3, 30);
        }
    }

    return max(0, min(100, $score));
}

/**
 * Retourne la classe CSS et le label pour un score donne
 * @param int $score
 * @return array ['class' => string, 'label' => string, 'color' => string]
 */
function getScoreBadge(int $score): array
{
    if ($score >= 70) return ['class' => 'bg-danger',  'label' => 'Chaud',  'color' => '#dc3545'];
    if ($score >= 45) return ['class' => 'bg-warning text-dark', 'label' => 'Tiede',  'color' => '#ffc107'];
    return ['class' => 'bg-secondary', 'label' => 'Froid',  'color' => '#6c757d'];
}

/**
 * Met a jour le score d'un lead en base
 * @param PDO $conn
 * @param int $leadId
 * @return int Nouveau score
 */
function updateLeadScore(PDO $conn, int $leadId): int
{
    $stmt = $conn->prepare("SELECT * FROM prospection_leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) return 0;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM prospection_interactions WHERE lead_id = ?");
    $stmt->execute([$leadId]);
    $nbInteractions = (int)$stmt->fetchColumn();

    $score = calculateLeadScore($lead, $nbInteractions);

    $conn->prepare("UPDATE prospection_leads SET score = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$score, $leadId]);

    return $score;
}

/**
 * Cree un lead dans prospection_leads et calcule son score
 * @param PDO $conn
 * @param array $data Donnees du lead
 * @return int|false ID du lead cree, ou false en cas d'erreur
 */
function createLead(PDO $conn, array $data)
{
    $fields = ['nom','prenom','email','telephone','ville','source','surface','capacite',
               'tarif_nuit_estime','revenu_mensuel_estime','equipements','statut','priorite',
               'date_rdv','type_rdv','message_rdv','notes','host_profile_id','nb_annonces',
               'note_moyenne','legacy_simulation_id','legacy_prospect_id'];

    $insert = [];
    $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $data) && $data[$f] !== null && $data[$f] !== '') {
            $insert[] = $f;
            $params[":$f"] = $data[$f];
        }
    }

    if (empty($insert)) return false;

    // Calcul du score initial
    $score = calculateLeadScore($data);
    $insert[] = 'score';
    $params[':score'] = $score;

    $cols = implode(', ', $insert);
    $placeholders = implode(', ', array_map(fn($f) => ":$f", $insert));

    $stmt = $conn->prepare("INSERT INTO prospection_leads ($cols) VALUES ($placeholders)");
    $stmt->execute($params);

    return $conn->lastInsertId() ?: false;
}

/**
 * Retourne les leads qui necessitent une relance
 * @param PDO $conn
 * @return array
 */
function getLeadsNeedingFollowUp(PDO $conn): array
{
    return $conn->query("
        SELECT l.*,
            DATEDIFF(NOW(), COALESCE(l.date_derniere_interaction, l.created_at)) as jours_sans_contact
        FROM prospection_leads l
        WHERE l.statut NOT IN ('converti', 'perdu')
        AND (
            (l.score >= 50 AND DATEDIFF(NOW(), COALESCE(l.date_derniere_interaction, l.created_at)) >= 2)
            OR (l.date_rdv IS NOT NULL AND l.date_rdv BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY))
            OR (l.statut = 'proposition' AND DATEDIFF(NOW(), l.updated_at) >= 7)
            OR (l.score >= 60 AND l.statut = 'nouveau')
        )
        ORDER BY l.score DESC, l.date_rdv ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
