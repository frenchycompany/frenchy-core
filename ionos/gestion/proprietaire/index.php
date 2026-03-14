<?php
/**
 * Espace Propriétaire - Dashboard
 * Frenchy Conciergerie
 */

require_once __DIR__ . '/auth.php';

// Mise à jour de la dernière connexion
try {
    $stmt = $conn->prepare("UPDATE FC_proprietaires SET derniere_connexion = NOW() WHERE id = ?");
    $stmt->execute([$proprietaire_id]);
} catch (PDOException $e) {}

// Statistiques globales
$stats = [
    'total_logements' => count($logements),
    'taches_en_attente' => 0,
    'checkups_recents' => 0,
    'inventaires' => 0
];

if (!empty($logement_ids)) {
    // Tâches en attente
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM todo_list WHERE logement_id IN ($placeholders) AND statut != 'terminee'");
        $stmt->execute($logement_ids);
        $stats['taches_en_attente'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}

    // Checkups récents (30 derniers jours)
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM checkup_sessions WHERE logement_id IN ($placeholders) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute($logement_ids);
        $stats['checkups_recents'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}

    // Sessions d'inventaire
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM sessions_inventaire WHERE logement_id IN ($placeholders)");
        $stmt->execute($logement_ids);
        $stats['inventaires'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {}
}

// Taux d'occupation du mois en cours
$taux_occupation = 0;
$jours_occupes = 0;
$nb_jours_mois = (int)date('t');
if (!empty($logement_ids)) {
    try {
        $mois_debut = date('Y-m-01');
        $mois_fin = date('Y-m-t');
        $stmt = $conn->prepare("SELECT r.logement_id, r.date_arrivee, r.date_depart
            FROM reservation r
            WHERE r.logement_id IN ($placeholders) AND r.date_arrivee <= ? AND r.date_depart >= ? AND r.statut != 'annulée'");
        $stmt->execute(array_merge($logement_ids, [$mois_fin, $mois_debut]));
        $resa_par_logement = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resa_par_logement[$r['logement_id']][] = $r;
        }
        foreach ($logement_ids as $lid) {
            foreach (($resa_par_logement[$lid] ?? []) as $r) {
                $start = max(strtotime($r['date_arrivee']), strtotime($mois_debut));
                $end = min(strtotime($r['date_depart']), strtotime($mois_fin) + 86400);
                $jours_occupes += max(0, ($end - $start) / 86400);
            }
        }
        $total_possible = count($logement_ids) * $nb_jours_mois;
        $taux_occupation = $total_possible > 0 ? round(($jours_occupes / $total_possible) * 100) : 0;
    } catch (PDOException $e) {}
}

// Sites vitrine liés aux logements
$sites_vitrine = [];
if (!empty($logement_ids)) {
    try {
        $stmt = $conn->prepare("SELECT fi.*, l.nom_du_logement FROM frenchysite_instances fi JOIN liste_logements l ON fi.logement_id = l.id WHERE fi.logement_id IN ($placeholders) AND fi.actif = 1");
        $stmt->execute($logement_ids);
        $sites_vitrine = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Propriétaire - Frenchy Conciergerie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="proprio.css">
    <style>
        .date-info { color: #6B7280; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }

        .stat-card {
            background: white; padding: 1.5rem; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 1rem;
        }

        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }

        .stat-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }
        .stat-icon.green { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .stat-icon.orange { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
        .stat-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }

        .stat-content h3 { font-size: 1.8rem; color: var(--gris-fonce); margin-bottom: 0.2rem; }
        .stat-content p { color: #6B7280; font-size: 0.9rem; }

        .content-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;
        }

        @media (max-width: 1200px) { .content-grid { grid-template-columns: 1fr; } }

        .site-link {
            display: flex; align-items: center; gap: 0.8rem;
            padding: 0.8rem; border: 1px solid #E5E7EB; border-radius: 10px;
            text-decoration: none; color: var(--gris-fonce); margin-bottom: 0.5rem;
            transition: box-shadow 0.2s;
        }
        .site-link:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .site-link i { font-size: 1.2rem; color: var(--bleu-clair); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php proprioSidebar($proprietaire, $currentPage, $has_sites); ?>

        <!-- Main content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Bonjour, <?= e($proprietaire['prenom'] ?? $proprietaire['nom']) ?> !</h1>
                <span class="date-info"><?= date('d/m/Y') ?></span>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-home"></i></div>
                    <div class="stat-content">
                        <h3><?= $stats['total_logements'] ?></h3>
                        <p>Logements</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-tasks"></i></div>
                    <div class="stat-content">
                        <h3><?= $stats['taches_en_attente'] ?></h3>
                        <p>Taches en attente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-content">
                        <h3><?= $stats['checkups_recents'] ?></h3>
                        <p>Checkups (30j)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-boxes-stacked"></i></div>
                    <div class="stat-content">
                        <h3><?= $stats['inventaires'] ?></h3>
                        <p>Inventaires</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(99,102,241,0.1);color:#6366F1;"><i class="fas fa-chart-pie"></i></div>
                    <div class="stat-content">
                        <h3><?= $taux_occupation ?>%</h3>
                        <?php $moisFr = ['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre']; ?>
                        <p>Occupation <?= $moisFr[(int)date('m')] ?></p>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Tâches récentes -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-tasks"></i> Taches en cours</h2>
                        <a href="taches.php">Voir tout &rarr;</a>
                    </div>
                    <?php
                    $taches_recentes = [];
                    if (!empty($logement_ids)) {
                        try {
                            $stmt = $conn->prepare("SELECT t.*, l.nom_du_logement FROM todo_list t JOIN liste_logements l ON t.logement_id = l.id WHERE t.logement_id IN ($placeholders) AND t.statut != 'terminee' ORDER BY t.date_limite ASC LIMIT 5");
                            $stmt->execute($logement_ids);
                            $taches_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {}
                    }
                    ?>
                    <?php if (empty($taches_recentes)): ?>
                        <p class="empty-state">Aucune tache en attente</p>
                    <?php else: ?>
                        <?php foreach ($taches_recentes as $t): ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e($t['description']) ?></h4>
                                <small><?= e($t['nom_du_logement']) ?><?= $t['date_limite'] ? ' &middot; ' . date('d/m/Y', strtotime($t['date_limite'])) : '' ?></small>
                            </div>
                            <span class="badge <?= $t['statut'] === 'en cours' ? 'badge-info' : 'badge-warning' ?>">
                                <?= e($t['statut']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Derniers checkups -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-clipboard-check"></i> Derniers checkups</h2>
                        <a href="checkups.php">Voir tout &rarr;</a>
                    </div>
                    <?php
                    $checkups_recents = [];
                    if (!empty($logement_ids)) {
                        try {
                            $stmt = $conn->prepare("SELECT cs.*, l.nom_du_logement FROM checkup_sessions cs JOIN liste_logements l ON cs.logement_id = l.id WHERE cs.logement_id IN ($placeholders) ORDER BY cs.created_at DESC LIMIT 5");
                            $stmt->execute($logement_ids);
                            $checkups_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {}
                    }
                    ?>
                    <?php if (empty($checkups_recents)): ?>
                        <p class="empty-state">Aucun checkup enregistre</p>
                    <?php else: ?>
                        <?php foreach ($checkups_recents as $c): ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e($c['nom_du_logement']) ?></h4>
                                <small><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small>
                            </div>
                            <div>
                                <?php if ($c['nb_problemes'] > 0): ?>
                                    <span class="badge badge-danger"><?= (int)$c['nb_problemes'] ?> pb</span>
                                <?php endif; ?>
                                <span class="badge badge-success"><?= (int)$c['nb_ok'] ?> OK</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dernières interventions -->
            <div class="content-grid" style="margin-top: 1.5rem;">
                <div class="card" style="grid-column: 1 / -1;">
                    <div class="card-header">
                        <h2><i class="fas fa-broom"></i> Dernieres interventions</h2>
                        <a href="interventions.php">Voir tout &rarr;</a>
                    </div>
                    <?php
                    $interventions_recentes = [];
                    if (!empty($logement_ids)) {
                        try {
                            $stmt = $conn->prepare("SELECT p.id, p.date, p.statut, l.nom_du_logement FROM planning p JOIN liste_logements l ON p.logement_id = l.id WHERE p.logement_id IN ($placeholders) AND p.date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY) ORDER BY p.date DESC LIMIT 5");
                            $stmt->execute($logement_ids);
                            $interventions_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {}
                    }
                    ?>
                    <?php if (empty($interventions_recentes)): ?>
                        <p class="empty-state">Aucune intervention recente</p>
                    <?php else: ?>
                        <?php foreach ($interventions_recentes as $ir):
                            $sc = 'badge-info';
                            $s = $ir['statut'] ?? '';
                            if (in_array($s, ['termine', 'terminé', 'validé', 'valide'])) $sc = 'badge-success';
                            elseif (in_array($s, ['en_cours', 'en cours', 'planifié', 'planifie'])) $sc = 'badge-warning';
                            elseif (in_array($s, ['annulé', 'annule', 'probleme'])) $sc = 'badge-danger';
                        ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e($ir['nom_du_logement']) ?></h4>
                                <small><?= date('d/m/Y', strtotime($ir['date'])) ?></small>
                            </div>
                            <span class="badge <?= $sc ?>"><?= e($s ?: 'inconnu') ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Logements + Sites vitrine -->
            <div class="content-grid" style="margin-top: 1.5rem;">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-home"></i> Mes logements</h2>
                    </div>
                    <?php if (empty($logements)): ?>
                        <p class="empty-state">Aucun logement associe a votre compte</p>
                    <?php else: ?>
                        <?php foreach ($logements as $logement): ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e($logement['nom_du_logement']) ?></h4>
                                <small><?= e($logement['adresse'] ?? '') ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sites_vitrine)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-globe"></i> Sites vitrine</h2>
                    </div>
                    <?php foreach ($sites_vitrine as $site): ?>
                    <a class="site-link" href="<?= e($site['site_url'] ?: '#') ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i>
                        <div>
                            <h4><?= e($site['site_name']) ?></h4>
                            <small><?= e($site['nom_du_logement']) ?></small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
