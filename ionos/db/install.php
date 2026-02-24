<?php
/**
 * Script d'installation de la base de données Frenchy Conciergerie
 * Crée les tables FC_* et insère les données initiales
 */

require_once __DIR__ . '/connection.php';

echo "<h1>Installation de la base de données Frenchy Conciergerie</h1>";

try {
    // Lecture et exécution du schema SQL
    $schema = file_get_contents(__DIR__ . '/schema.sql');

    // Séparer les requêtes
    $queries = array_filter(array_map('trim', explode(';', $schema)));

    foreach ($queries as $query) {
        if (!empty($query)) {
            $conn->exec($query);
        }
    }
    echo "<p style='color: green;'>✓ Tables créées avec succès</p>";

    // Création des index d'optimisation (seulement s'ils n'existent pas)
    $indexes = [
        ['FC_services', 'idx_services_ordre', 'ordre, actif'],
        ['FC_tarifs', 'idx_tarifs_ordre', 'ordre, actif'],
        ['FC_logements', 'idx_logements_ordre', 'ordre, actif'],
        ['FC_avis', 'idx_avis_actif', 'actif'],
        ['FC_distinctions', 'idx_distinctions_ordre', 'ordre, actif'],
        ['FC_articles', 'idx_articles_date', 'date_publication, actif'],
        ['FC_contacts', 'idx_contacts_lu', 'lu, traite'],
        ['FC_newsletter', 'idx_newsletter_email', 'email, actif'],
        ['FC_reservations', 'idx_reservations_dates', 'logement_id, date_debut, date_fin'],
        ['FC_revenus', 'idx_revenus_logement', 'logement_id, mois'],
        ['FC_csrf_tokens', 'idx_csrf_expires', 'expires_at'],
        ['FC_rate_limit', 'idx_rate_limit_expires', 'blocked_until'],
        ['FC_menages', 'idx_menages_date', 'date_intervention, statut'],
        ['FC_menages', 'idx_menages_prestataire', 'prestataire_id, date_intervention'],
    ];

    foreach ($indexes as [$table, $indexName, $columns]) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $checkStmt->execute([$table, $indexName]);
        if ($checkStmt->fetchColumn() == 0) {
            try {
                $conn->exec("CREATE INDEX $indexName ON $table($columns)");
            } catch (PDOException $e) {
                // Index might already exist or table doesn't exist yet - ignore
            }
        }
    }
    echo "<p style='color: green;'>✓ Index créés</p>";

    // Vérifier si les données existent déjà
    $checkServices = $conn->query("SELECT COUNT(*) FROM FC_services")->fetchColumn();
    $checkTarifs = $conn->query("SELECT COUNT(*) FROM FC_tarifs")->fetchColumn();
    $checkLogements = $conn->query("SELECT COUNT(*) FROM FC_logements")->fetchColumn();
    $checkAvis = $conn->query("SELECT COUNT(*) FROM FC_avis")->fetchColumn();
    $checkDistinctions = $conn->query("SELECT COUNT(*) FROM FC_distinctions")->fetchColumn();
    $checkLegal = $conn->query("SELECT COUNT(*) FROM FC_legal")->fetchColumn();

    // Insertion des paramètres par défaut (toujours, car INSERT IGNORE gère les doublons via la clé unique)
    $settings = [
        ['site_nom', 'Frenchy Conciergerie'],
        ['site_slogan', 'Conciergerie Airbnb & Gestion Locative Saisonnière'],
        ['site_description', 'Nous gérons votre bien de A à Z pour optimiser vos revenus'],
        ['adresse', '718 rue de la Louvière, 60126 LONGUEIL-SAINTE-MARIE'],
        ['telephone', '06 47 55 46 78'],
        ['email', 'raphael@frenchycompany.fr'],
        ['email_legal', 'laetitia@frenchycompany.fr'],
        ['telephone_legal', '07 64 23 86 17'],
        ['horaires', 'Du lundi au samedi : 9h - 19h | Dimanche : sur rendez-vous'],
        ['siret', '944 992 528 00017'],
        ['rcs', 'Beauvais 944 992 528'],
        ['tva_intra', 'FR15 944992528'],
        ['capital', '100 euros'],
        ['forme_juridique', 'Société par Actions Simplifiée (SAS)'],
        ['presidente', 'Madame Laëtitia PIERIN'],
        ['carte_transaction', 'CPI 6002 2025 001 000 003'],
        ['carte_gestion', 'CPI 6002 2025 001 000 003'],
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO FC_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "<p style='color: green;'>✓ Paramètres insérés</p>";

    // Insertion des services (seulement si la table est vide)
    if ($checkServices == 0) {
    $services = [
        [
            'Gestion Locative Complète',
            '🏠',
            'Activité exercée sous carte Gestion n° CPI 6002 2025 001 000 003',
            'Gestion complète de votre bien locatif saisonnier',
            json_encode([
                'Gestion des réservations Airbnb',
                'Collecte des loyers',
                'Accueil et remise des clés',
                'Communication avec les voyageurs',
                'Coordination ménage et maintenance',
                'Gestion des stocks (linge, produits)',
                'Reporting mensuel détaillé'
            ]),
            1
        ],
        [
            'Publication et Diffusion',
            '📢',
            'Activité exercée sous carte Transaction n° CPI 6002 2025 001 000 003',
            'Visibilité maximale pour votre bien',
            json_encode([
                'Création et optimisation des annonces',
                'Photos professionnelles',
                'Diffusion multi-plateformes',
                'Stratégie tarifaire dynamique',
                'Publicité ciblée',
                'Mise en relation avec professionnels'
            ]),
            2
        ],
        [
            'Accompagnement Investisseurs',
            '🎯',
            'Conseil personnalisé pour votre projet',
            'Un accompagnement sur mesure pour réussir votre investissement',
            json_encode([
                'Étude de rentabilité',
                'Sélection du bien idéal',
                'Aménagement et décoration',
                'Mise en conformité réglementaire',
                'Formation à la gestion Airbnb',
                'Support juridique et fiscal'
            ]),
            3
        ]
    ];

    $stmt = $conn->prepare("INSERT INTO FC_services (titre, icone, carte_info, description, liste_items, ordre) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($services as $service) {
        $stmt->execute($service);
    }
    echo "<p style='color: green;'>✓ Services insérés</p>";
    } else {
        echo "<p style='color: blue;'>→ Services déjà présents (ignorés)</p>";
    }

    // Insertion des tarifs (seulement si la table est vide)
    if ($checkTarifs == 0) {
    $tarifs = [
        ['Logement Équipé', 24.00, 'pourcentage', 'TTC des revenus locatifs', 'Logement meublé et équipé, prêt à accueillir les voyageurs', 1],
        ['Logement Vide', 36.00, 'pourcentage', 'TTC des revenus locatifs', 'Nous prenons en charge l\'aménagement et l\'équipement complet', 2]
    ];

    $stmt = $conn->prepare("INSERT INTO FC_tarifs (titre, montant, type_tarif, description, details, ordre) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($tarifs as $tarif) {
        $stmt->execute($tarif);
    }
    echo "<p style='color: green;'>✓ Tarifs insérés</p>";
    } else {
        echo "<p style='color: blue;'>→ Tarifs déjà présents (ignorés)</p>";
    }

    // Configuration du simulateur (seulement si vide)
    $checkSimConfig = $conn->query("SELECT COUNT(*) FROM FC_simulateur_config")->fetchColumn();
    if ($checkSimConfig == 0) {
        $simConfigs = [
            ['tarif_base_couchage', '15', 'Tarif de base par couchage (€)', 'number', 1],
            ['majoration_m2', '0.5', 'Majoration par m² (€)', 'number', 2],
            ['majoration_centre_ville', '10', 'Majoration centre-ville (%)', 'percent', 3],
            ['majoration_fibre', '5', 'Majoration fibre (%)', 'percent', 4],
            ['majoration_equipements_speciaux', '15', 'Majoration équipements spéciaux (%)', 'percent', 5],
            ['majoration_machine_cafe', '3', 'Majoration machine café (%)', 'percent', 6],
            ['majoration_machine_laver', '5', 'Majoration machine à laver (%)', 'percent', 7],
            ['taux_occupation', '70', 'Taux d\'occupation moyen (%)', 'percent', 8],
            ['commission', '24', 'Commission Frenchy (%)', 'percent', 9],
            ['cout_menage_m2', '1', 'Coût ménage par m² (€)', 'number', 10],
            ['rotations_mois', '12', 'Nombre de rotations/mois (locations)', 'number', 11],
        ];
        $stmt = $conn->prepare("INSERT INTO FC_simulateur_config (config_key, config_value, config_label, config_type, ordre) VALUES (?, ?, ?, ?, ?)");
        foreach ($simConfigs as $config) {
            $stmt->execute($config);
        }
        echo "<p style='color: green;'>✓ Configuration simulateur insérée</p>";
    } else {
        echo "<p style='color: blue;'>→ Configuration simulateur déjà présente (ignorée)</p>";
    }

    // Villes pour le simulateur (seulement si vide)
    $checkVilles = $conn->query("SELECT COUNT(*) FROM FC_simulateur_villes")->fetchColumn();
    if ($checkVilles == 0) {
        $villes = [
            ['Compiègne', 0, 1],
            ['Paris', 80, 2],
            ['Margny-lès-Compiègne', -5, 3],
            ['Venette', -5, 4],
            ['Lacroix-Saint-Ouen', -10, 5],
            ['Jaux', -5, 6],
            ['Choisy-au-Bac', -10, 7],
        ];
        $stmt = $conn->prepare("INSERT INTO FC_simulateur_villes (ville, majoration_percent, ordre) VALUES (?, ?, ?)");
        foreach ($villes as $ville) {
            $stmt->execute($ville);
        }
        echo "<p style='color: green;'>✓ Villes simulateur insérées</p>";
    } else {
        echo "<p style='color: blue;'>→ Villes simulateur déjà présentes (ignorées)</p>";
    }

    // Insertion des logements (seulement si la table est vide)
    if ($checkLogements == 0) {
    $logements = [
        ['Appartement T2 - Centre Compiègne', 'Logement moderne et chaleureux', 'logement-1.jpg', 'Compiègne', 'Appartement', 1],
        ['Studio Cosy - Proche Château', 'Idéal pour voyageurs d\'affaires', 'logement-2.jpg', 'Compiègne', 'Studio', 2],
        ['Maison T3 - Avec Jardin', 'Parfait pour familles', 'logement-3.jpg', 'Compiègne', 'Maison', 3],
        ['Appartement T4 - Vue Dégagée', 'Spacieux et lumineux', 'logement-4.jpg', 'Compiègne', 'Appartement', 4],
        ['Loft Industriel - Compiègne', 'Design contemporain', 'logement-5.jpg', 'Compiègne', 'Loft', 5],
        ['Duplex Premium - Centre-Ville', 'Standing élevé', 'logement-6.jpg', 'Compiègne', 'Duplex', 6]
    ];

    $stmt = $conn->prepare("INSERT INTO FC_logements (titre, description, image, localisation, type_bien, ordre) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($logements as $logement) {
        $stmt->execute($logement);
    }
    echo "<p style='color: green;'>✓ Logements insérés</p>";
    } else {
        echo "<p style='color: blue;'>→ Logements déjà présents (ignorés)</p>";
    }

    // Insertion des avis (seulement si la table est vide)
    if ($checkAvis == 0) {
    $avis = [
        ['Sophie M.', 'Propriétaire', '2026-02-01', 5, 'Depuis que j\'ai confié mon appartement à Frenchy Conciergerie, je n\'ai plus à me soucier de rien. L\'équipe est ultra professionnelle et mes revenus ont considérablement augmenté. Je recommande vivement !'],
        ['Marc D.', 'Propriétaire', '2026-01-15', 5, 'Excellent service de gestion locative. Communication irréprochable, reporting détaillé chaque mois. Mon bien est entre de bonnes mains et les résultats sont au rendez-vous.'],
        ['Catherine L.', 'Propriétaire', '2026-02-01', 5, 'Je suis propriétaire de 2 appartements gérés par Frenchy Conciergerie. Leur accompagnement a été précieux dès le début. Résultats excellents et tranquillité d\'esprit garantie !']
    ];

    $stmt = $conn->prepare("INSERT INTO FC_avis (nom, role, date_avis, note, commentaire) VALUES (?, ?, ?, ?, ?)");
    foreach ($avis as $avi) {
        $stmt->execute($avi);
    }
    echo "<p style='color: green;'>✓ Avis insérés</p>";
    } else {
        echo "<p style='color: blue;'>→ Avis déjà présents (ignorés)</p>";
    }

    // Insertion des distinctions (seulement si la table est vide)
    if ($checkDistinctions == 0) {
    $distinctions = [
        ['Traveller Review Awards 2026', '🏆', 'Distinction Booking.com décernée pour l\'excellence de nos services et la satisfaction de nos voyageurs sur l\'ensemble de nos logements.', 'booking-award.png', 1],
        ['Co-hôte Expérimenté Airbnb', '⭐', 'Statut officiel Airbnb reconnaissant notre expertise dans la gestion de locations saisonnières et notre professionnalisme.', '', 2],
        ['Contrôle Qualité Réglementaire', '✓', 'Notre activité a fait l\'objet d\'un contrôle qualité approfondi par les autorités compétentes, confirmant notre conformité totale aux réglementations en vigueur.', '', 3]
    ];

    $stmt = $conn->prepare("INSERT INTO FC_distinctions (titre, icone, description, image, ordre) VALUES (?, ?, ?, ?, ?)");
    foreach ($distinctions as $distinction) {
        $stmt->execute($distinction);
    }
    echo "<p style='color: green;'>✓ Distinctions insérées</p>";
    } else {
        echo "<p style='color: blue;'>→ Distinctions déjà présentes (ignorées)</p>";
    }

    // Insertion des sections légales (seulement si la table est vide)
    if ($checkLegal == 0) {
    $legals = [
        ['identite', 'Identité de l\'Entreprise', '📋', json_encode([
            'Raison sociale' => 'SAS FRENCHY CONCIERGERIE',
            'Forme juridique' => 'Société par Actions Simplifiée (SAS)',
            'Capital social' => '100 euros',
            'Siège social' => '718 rue de la Louvière, 60126 LONGUEIL-SAINTE-MARIE',
            'SIRET' => '944 992 528 00017',
            'RCS' => 'Beauvais 944 992 528',
            'TVA intracommunautaire' => 'FR15 944992528',
            'Présidente' => 'Madame Laëtitia PIERIN',
            'Email' => 'laetitia@frenchycompany.fr',
            'Téléphone' => '07 64 23 86 17'
        ]), 1],
        ['cartes', 'Cartes Professionnelles', '🏛️', json_encode([
            'transaction' => [
                'numero' => 'CPI 6002 2025 001 000 003',
                'validite' => '23 janvier 2028',
                'delivree' => 'CCI de l\'Oise',
                'activite' => 'Transactions sur immeubles et fonds de commerce'
            ],
            'gestion' => [
                'numero' => 'CPI 6002 2025 001 000 003',
                'validite' => '22 septembre 2028',
                'delivree' => 'CCI de l\'Oise',
                'activite' => 'Gestion immobilière - Prestations touristiques'
            ]
        ]), 2],
        ['garanties', 'Garanties Professionnelles', '🛡️', json_encode([
            'assurance' => 'GENERALI IARD',
            'siege' => '2 Rue Pillet-Will, 75009 PARIS',
            'contrat' => 'AL591311 / 31705',
            'prise_effet' => '01/07/2025',
            'validite' => 'du 01/01/2026 au 31/12/2026',
            'activites' => ['Gestion immobilière', 'Prestations touristiques', 'Transaction sur immeubles et fonds de commerce']
        ]), 3],
        ['mediateur', 'Médiation de la Consommation', '⚖️', json_encode([
            'nom' => 'GIE IMMOMEDIATEURS',
            'adresse' => '55 Avenue Marceau, 75116 Paris',
            'site' => 'www.immomediateurs.fr',
            'email' => 'contact@immomediateurs.fr'
        ]), 4]
    ];

    $stmt = $conn->prepare("INSERT INTO FC_legal (section, titre, icone, contenu, ordre) VALUES (?, ?, ?, ?, ?)");
    foreach ($legals as $legal) {
        $stmt->execute($legal);
    }
    echo "<p style='color: green;'>✓ Informations légales insérées</p>";
    } else {
        echo "<p style='color: blue;'>→ Informations légales déjà présentes (ignorées)</p>";
    }

    echo "<h2 style='color: green;'>✓ Installation terminée avec succès !</h2>";
    echo "<p><a href='../index.php'>Aller sur le site</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
