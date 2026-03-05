<?php
/**
 * Traductions francaises (langue par defaut)
 */
return [
    // General
    'app.name' => 'Frenchy Conciergerie',
    'btn.back' => 'Retour',
    'btn.save' => 'Sauvegarder',
    'btn.delete' => 'Supprimer',
    'btn.add' => 'Ajouter',
    'btn.filter' => 'Filtrer',
    'btn.reset' => 'Reset',
    'btn.print' => 'Imprimer',
    'btn.download' => 'Telecharger',
    'btn.close' => 'Fermer',
    'none' => 'Aucun',
    'all' => 'Tous',
    'yes' => 'Oui',
    'no' => 'Non',
    'date' => 'Date',
    'status' => 'Statut',

    // Checkup
    'checkup.title' => 'Checkup Logement',
    'checkup.subtitle' => 'Equipements + Inventaire + Taches + Etat general',
    'checkup.choose_property' => 'Choisir un logement',
    'checkup.launch' => 'Lancer le checkup',
    'checkup.finish' => 'Terminer le checkup',
    'checkup.recent' => 'Checkups recents',
    'checkup.pending_tasks' => 'Taches en attente',
    'checkup.last_inventory' => 'Dernier inventaire',
    'checkup.equipment' => 'Equipements',
    'checkup.in_progress' => 'En cours',
    'checkup.completed' => 'Termine',
    'checkup.verified' => 'verifies',
    'checkup.general_comment' => 'Commentaire general',
    'checkup.general_comment_placeholder' => 'Remarques generales sur l\'etat du logement...',
    'checkup.signature' => 'Signature de l\'intervenant',
    'checkup.clear' => 'Effacer',

    // Checkup statuts
    'status.ok' => 'OK',
    'status.problem' => 'Probleme',
    'status.absent' => 'Absent',
    'status.not_checked' => 'Non verifie',

    // Rapport
    'report.title' => 'Rapport de Checkup',
    'report.score' => 'Score',
    'report.problems' => 'Problemes signales',
    'report.missing' => 'Elements absents',
    'report.tasks_done' => 'Taches realisees',
    'report.tasks_remaining' => 'Taches non realisees',
    'report.all_good' => 'Tout est en ordre !',
    'report.all_good_detail' => 'Aucun probleme ni element absent n\'a ete signale. Le logement est pret.',
    'report.detail' => 'Detail complet',
    'report.pdf' => 'PDF',
    'report.modify' => 'Modifier',
    'report.new' => 'Nouveau',

    // Historique
    'history.title' => 'Historique des Checkups',
    'history.total' => 'Total',
    'history.finished' => 'Termines',
    'history.in_progress' => 'En cours',
    'history.avg_score' => 'Score moyen',
    'history.no_results' => 'Aucun checkup trouve avec ces filtres.',

    // Dashboard
    'dashboard.title' => 'Dashboard de Suivi',
    'dashboard.properties' => 'Logements',
    'dashboard.with_checkup' => 'Avec checkup',
    'dashboard.pending_tasks' => 'Taches en attente',
    'dashboard.alerts' => 'Alertes',
    'dashboard.never' => 'Jamais',
    'dashboard.no_issues' => 'RAS',

    // Inventaire
    'inventory.title' => 'Inventaire',
    'inventory.add_object' => 'Ajouter un objet',
    'inventory.object_name' => 'Nom de l\'objet',
    'inventory.quantity' => 'Quantite',
    'inventory.room' => 'Piece',
    'inventory.condition' => 'Etat',
    'inventory.take_photo' => 'Prendre une photo',
    'inventory.photo_taken' => 'Photo prise !',
    'inventory.more_details' => 'Plus de details',
    'inventory.validate' => 'Valider l\'inventaire',
    'inventory.compare' => 'Comparer deux inventaires',
    'inventory.added' => 'Ajoutes',
    'inventory.removed' => 'Supprimes',
    'inventory.modified' => 'Modifies',
    'inventory.identical' => 'Identiques',

    // Statistiques
    'stats.title' => 'Statistiques Checkup',
    'stats.score_evolution' => 'Evolution du score',
    'stats.issues_chart' => 'Problemes et absents par checkup',
    'stats.ranking' => 'Classement par logement',
    'stats.top_problems' => 'Top problemes',
    'stats.top_missing' => 'Top absents',
    'stats.no_data' => 'Aucun checkup termine pour generer des statistiques.',

    // Templates
    'templates.title' => 'Templates de Checkup',
    'templates.subtitle' => 'Items personnalises ajoutes aux checkups (piscine, jardin...)',
    'templates.global' => 'Global (tous les logements)',
    'templates.specific' => 'Specifique logement',
    'templates.category' => 'Categorie',
    'templates.item_name' => 'Nom de l\'item',

    // QR Code
    'qr.title' => 'QR Codes Checkup',
    'qr.subtitle' => 'Scannez pour lancer un checkup directement',
    'qr.generate' => 'Generer le QR Code',
    'qr.generate_all' => 'Generer tous les QR codes',

    // Notifications
    'notif.checkup_problem' => 'Checkup {:property} : {:nb_problems} probleme(s), {:nb_missing} absent(s)',

    // Offline
    'offline.banner' => 'Mode hors-ligne — Les modifications seront synchronisees au retour',
    'offline.title' => 'Mode hors-ligne',
    'offline.message' => 'Cette page n\'est pas disponible hors-ligne.',

    // Filtres
    'filter.property' => 'Logement',
    'filter.status' => 'Statut',
    'filter.worker' => 'Intervenant',
    'filter.from' => 'Du',
    'filter.to' => 'Au',
];
