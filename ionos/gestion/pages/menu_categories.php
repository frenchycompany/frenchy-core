<?php
/**
 * Définition centralisée des catégories du menu.
 * Inclus par menu.php et gestion_pages.php pour garantir la synchronisation.
 */

if (!isset($menu_categories)) {
    $menu_categories = [
        'Logements' => [
            'icon' => 'fa-home',
            'items' => [
                ['nom' => 'Logements',     'chemin' => 'pages/logements.php',             'icon' => 'fa-building'],
                ['nom' => 'Propriétaires','chemin' => 'pages/proprietaires.php',        'icon' => 'fa-user-tie'],
                ['nom' => 'Équipements',   'chemin' => 'pages/logement_equipements.php',  'icon' => 'fa-couch'],
                ['nom' => 'Descriptions',  'chemin' => 'pages/description_logements.php', 'icon' => 'fa-file-alt'],
                ['nom' => 'Sites vitrine', 'chemin' => 'pages/sites.php',                 'icon' => 'fa-globe'],
            ]
        ],
        'Ménage / Interventions' => [
            'icon' => 'fa-broom',
            'items' => [
                ['nom' => 'Planning',             'chemin' => 'pages/planning.php',              'icon' => 'fa-calendar-alt'],
                ['nom' => 'Éditer planning',      'chemin' => 'pages/editer_planning.php',       'icon' => 'fa-edit'],
                ['nom' => 'Intervenants',         'chemin' => 'pages/intervenants.php',          'icon' => 'fa-users'],
                ['nom' => 'Machines',             'chemin' => 'pages/machines.php',              'icon' => 'fa-cogs'],
                ['nom' => 'Stat. ménage',         'chemin' => 'pages/statistiques_menage.php',   'icon' => 'fa-chart-bar'],
            ]
        ],
        'Checkups' => [
            'icon' => 'fa-clipboard-check',
            'items' => [
                ['nom' => 'Checkup',      'chemin' => 'pages/checkup_logement.php',      'icon' => 'fa-clipboard-check'],
                ['nom' => 'Historique',   'chemin' => 'pages/checkup_historique.php',     'icon' => 'fa-history'],
                ['nom' => 'Dashboard',    'chemin' => 'pages/checkup_dashboard.php',      'icon' => 'fa-tachometer-alt'],
                ['nom' => 'Statistiques', 'chemin' => 'pages/checkup_statistiques.php',   'icon' => 'fa-chart-line'],
            ]
        ],
        'Réservations' => [
            'icon' => 'fa-calendar-check',
            'items' => [
                ['nom' => 'Calendrier',         'chemin' => 'pages/calendrier.php',          'icon' => 'fa-calendar'],
                ['nom' => 'Listing complet',    'chemin' => 'pages/reservations.php',        'icon' => 'fa-list'],
                ['nom' => 'Sync iCal',          'chemin' => 'pages/sync_ical.php',           'icon' => 'fa-sync-alt'],
                ['nom' => 'Taux d\'occupation', 'chemin' => 'pages/occupation.php',          'icon' => 'fa-chart-pie'],
                ['nom' => 'Import CSV',         'chemin' => 'pages/import_reservations.php', 'icon' => 'fa-file-import'],
            ]
        ],
        'Communication' => [
            'icon' => 'fa-comments',
            'items' => [
                ['nom' => 'SMS reçus',       'chemin' => 'pages/sms_recus.php',       'icon' => 'fa-inbox'],
                ['nom' => 'Envoyer SMS',     'chemin' => 'pages/sms_envoyer.php',     'icon' => 'fa-paper-plane'],
                ['nom' => 'Templates',       'chemin' => 'pages/sms_templates.php',   'icon' => 'fa-file-alt'],
                ['nom' => 'Automatisations', 'chemin' => 'pages/sms_automations.php', 'icon' => 'fa-robot'],
                ['nom' => 'Campagnes',       'chemin' => 'pages/sms_campagnes.php',   'icon' => 'fa-bullhorn'],
            ]
        ],
        'Inventaire' => [
            'icon' => 'fa-boxes-stacked',
            'items' => [
                ['nom' => 'Accueil inventaire', 'chemin' => 'pages/inventaire.php',          'icon' => 'fa-warehouse'],
                ['nom' => 'Lancer inventaire',  'chemin' => 'pages/inventaire_lancer.php',   'icon' => 'fa-plus-circle'],
                ['nom' => 'Sessions',           'chemin' => 'pages/liste_sessions.php',      'icon' => 'fa-clipboard-list'],
                ['nom' => 'Liste objets',       'chemin' => 'pages/liste_objets.php',        'icon' => 'fa-list'],
                ['nom' => 'Étiquettes',         'chemin' => 'pages/impression_etiquettes.php','icon' => 'fa-tags'],
                ['nom' => 'Comparer',           'chemin' => 'pages/inventaire_comparer.php',  'icon' => 'fa-code-compare'],
            ]
        ],
        'Finance' => [
            'icon' => 'fa-euro-sign',
            'items' => [
                ['nom' => 'Comptabilité',       'chemin' => 'pages/comptabilite.php',  'icon' => 'fa-calculator'],
                ['nom' => 'Facturation',        'chemin' => 'pages/facturation.php',   'icon' => 'fa-file-invoice'],
                ['nom' => 'Superhôte / Tarifs', 'chemin' => 'pages/superhote.php',     'icon' => 'fa-star'],
                ['nom' => 'Statistiques',       'chemin' => 'pages/statistiques.php',  'icon' => 'fa-chart-bar'],
            ]
        ],
        'Contrats Conciergerie' => [
            'icon' => 'fa-file-contract',
            'items' => [
                ['nom' => 'Créer contrat',        'chemin' => 'pages/create_contract.php',              'icon' => 'fa-file-contract'],
                ['nom' => 'Contrats générés',     'chemin' => 'pages/contrats_generes.php',             'icon' => 'fa-file-signature'],
                ['nom' => 'Modèles',              'chemin' => 'pages/list_templates.php',               'icon' => 'fa-file-alt'],
            ]
        ],
        'Contrats Location' => [
            'icon' => 'fa-house-user',
            'items' => [
                ['nom' => 'Créer contrat location',    'chemin' => 'pages/create_location_contract.php',           'icon' => 'fa-house-user'],
                ['nom' => 'Contrats générés',          'chemin' => 'pages/location_contrats_generes.php',          'icon' => 'fa-file-signature'],
                ['nom' => 'Modèles location',          'chemin' => 'pages/list_location_templates.php',            'icon' => 'fa-file-alt'],
                ['nom' => 'Détails logements',         'chemin' => 'pages/location_logement_details.php',          'icon' => 'fa-house-circle-check'],
            ]
        ],
        'Commercial' => [
            'icon' => 'fa-handshake',
            'items' => [
                ['nom' => 'CRM / Leads',       'chemin' => 'pages/prospection_proprietaires.php',  'icon' => 'fa-funnel-dollar'],
                ['nom' => 'Simulations',       'chemin' => 'pages/simulations.php',                'icon' => 'fa-calculator'],
                ['nom' => 'Agenda RDV',        'chemin' => 'pages/rdv_agenda.php',                 'icon' => 'fa-calendar-check'],
                ['nom' => 'Analyse de marché', 'chemin' => 'pages/analyse_marche.php',             'icon' => 'fa-chart-line'],
                ['nom' => 'Audit LCD',         'chemin' => 'pages/audit_lcd.php',                  'icon' => 'fa-file-circle-check'],
                ['nom' => 'Concurrence',       'chemin' => 'pages/analyse_concurrence.php',        'icon' => 'fa-chart-area'],
                ['nom' => 'Relances voyageurs','chemin' => 'pages/relances_voyageurs.php',         'icon' => 'fa-bullhorn'],
                ['nom' => 'Carnet clients',    'chemin' => 'pages/clients.php',                    'icon' => 'fa-address-book'],
            ]
        ],
        'Outils' => [
            'icon' => 'fa-tools',
            'items' => [
                ['nom' => 'Villes',           'chemin' => 'pages/villes.php',                      'icon' => 'fa-city'],
                ['nom' => 'Recommandations',  'chemin' => 'pages/recommandations_logement.php',    'icon' => 'fa-map-marked-alt'],
                ['nom' => 'Photos',           'chemin' => 'pages/import_photos_airbnb.php',        'icon' => 'fa-images'],
                ['nom' => 'Agent dashboard',  'chemin' => 'pages/agent_dashboard.php',             'icon' => 'fa-headset'],
                ['nom' => 'Todo',             'chemin' => 'pages/todo.php',                        'icon' => 'fa-tasks'],
                ['nom' => 'Rental United',    'chemin' => 'pages/rental_united.php',               'icon' => 'fa-plug'],
                ['nom' => 'Coffre-fort',      'chemin' => 'pages/coffre_fort.php',                 'icon' => 'fa-vault'],
            ]
        ],
    ];
}
