<?php
/**
 * Configuration de la propriété
 *
 * Ce fichier contient toute la configuration spécifique à ce logement.
 * Pour créer un site pour un autre logement :
 *   1. Copiez le dossier complet du site
 *   2. Modifiez CE fichier avec les infos du nouveau logement
 *   3. Changez le db_prefix (3 lettres + 2 chiffres, ex: ml02_)
 *   4. Créez un .env avec les credentials de la BDD (même BDD possible)
 *   5. Ouvrez /admin.php — le schéma s'installe automatiquement
 *   6. Personnalisez textes, photos et couleurs via l'admin
 */

return [

    // ══════════ Identité ══════════
    'name'      => 'Mon Logement',
    'monogram'  => 'ML',
    'tagline'   => 'Bienvenue chez vous',
    'location'  => 'Ville · Région',
    'phone'     => '+33 6 00 00 00 00',
    'phone_raw' => '+33600000000',
    'email'     => 'contact@example.com',
    'address'   => 'Ville, Région, France',

    // ══════════ Préfixe BDD (pour partager une base entre logements) ══════════
    // Format : 3 lettres + 2 chiffres + underscore (ex: cv01_, ml02_)
    'db_prefix' => 'ml01_',

    // ══════════ Intégrations ══════════
    'airbnb_id'     => '',
    'matterport_id' => '',

    // ══════════ Couleurs par défaut ══════════
    'colors' => [
        'green'    => '#1D5345',
        'green_dk' => '#153d33',
        'beige'    => '#CFCDB0',
        'grey'     => '#B2ACA9',
        'brown'    => '#6C5C4F',
        'offwhite' => '#E8E4D0',
        'dark'     => '#2B2924',
    ],

    // ══════════ Typographie ══════════
    'font_display' => 'Playfair Display',
    'font_body'    => 'Inter',

    // ══════════ Sections actives (dans l'ordre d'affichage) ══════════
    // Chaque clé = fichier dans sections/
    // 'nav' = texte dans le menu de navigation (absent = pas dans le menu)
    // 'id'  = ancre HTML (par défaut = la clé)
    'sections' => [
        'hero'        => ['label' => 'Hero (accueil)'],
        'band'        => ['label' => 'Bandeau chiffres clés'],
        'histoire'    => ['label' => 'Histoire',      'nav' => 'Histoire'],
        'experience'  => ['label' => 'L\'expérience', 'nav' => 'L\'expérience'],
        'galerie'     => ['label' => 'Galerie',       'nav' => 'Galerie'],
        'visite'      => ['label' => 'Visite 360°',   'nav' => 'Visite 360°'],
        'reservation' => ['label' => 'Réservation',   'nav' => 'Réserver', 'id' => 'reserver'],
        'contact'     => ['label' => 'Contact',       'nav' => 'Contact'],
    ],

    // ══════════ Guides (modes d'emploi QR codes) ══════════
    // Chaque clé = fichier .php à la racine (wifi.php, piscine.php...)
    // Pour désactiver un guide, commentez-le ou supprimez-le.
    'guides' => [
        'wifi' => [
            'label'       => 'WiFi',
            'admin_label' => 'WiFi',
            'icon'        => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>',
        ],
        'piscine' => [
            'label'       => 'Piscine',
            'admin_label' => 'Piscine',
            'icon'        => '<path d="M2 12h20"/><path d="M2 16c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/><path d="M2 20c1.5 1 3 1.5 4.5 1s3-1.5 4.5-1 3 .5 4.5 1 3 0 4.5-1"/>',
        ],
        'sauna' => [
            'label'       => 'Sauna',
            'admin_label' => 'Sauna',
            'icon'        => '<path d="M7 10v2"/><path d="M5 8.5c0 0 .5-2 2-2s2 2 2 2"/><path d="M2 18c0 0 2-2 5-2s5 2 5 2"/><path d="M2 22c0 0 2-2 5-2s5 2 5 2"/>',
        ],
        'sport' => [
            'label'       => 'Sport',
            'admin_label' => 'Salle de Sport',
            'icon'        => '<path d="M2 12h4m12 0h4"/><path d="M6 8v8"/><path d="M18 8v8"/><path d="M4 10v4"/><path d="M20 10v4"/>',
        ],
        'cinema' => [
            'label'       => 'Cinéma',
            'admin_label' => 'Cinéma',
            'icon'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 8h20"/><polygon points="10,11 10,17 15,14" fill="currentColor" stroke="none"/>',
        ],
        'cuisine' => [
            'label'       => 'Cuisine',
            'admin_label' => 'Cuisine',
            'icon'        => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>',
        ],
    ],

    // ══════════ Photos fallback (Unsplash) ══════════
    // Utilisées si aucune photo n'est uploadée dans l'admin
    'photo_fallbacks' => [
        'hero' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=2000&q=80',
        'galerie' => [
            ['url' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=1200&q=80', 'alt' => 'Vue extérieure',     'wide' => true],
            ['url' => 'https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=800&q=80',  'alt' => 'Grand salon',        'wide' => false],
            ['url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',  'alt' => 'Chambre principale', 'wide' => false],
            ['url' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',     'alt' => 'Jardin',             'wide' => false],
            ['url' => 'https://images.unsplash.com/photo-1609766975132-5c14c77e64da?w=800&q=80',  'alt' => 'Salle à manger',     'wide' => false],
            ['url' => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?w=1200&q=80', 'alt' => 'Bibliothèque',       'wide' => true],
        ],
        'experience' => [
            'confort' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
            'charme'  => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
            'accueil' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?w=800&q=80',
        ],
    ],
];
