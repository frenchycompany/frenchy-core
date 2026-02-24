/**
 * Bookmarklet pour capturer les donnees d'une annonce Airbnb
 * Ce script est charge dynamiquement quand l'utilisateur clique sur le bookmarklet
 */

(function() {
    // Configuration - MODIFIER CETTE URL selon votre serveur
    const API_URL = window.BOOKMARKLET_API_URL || (window.location.protocol + '//' + window.location.host + '/api/market_capture.php');

    // Verifier qu'on est sur Airbnb
    if (!window.location.hostname.includes('airbnb')) {
        alert('Ce bookmarklet fonctionne uniquement sur les pages Airbnb !');
        return;
    }

    // Creer l'overlay de chargement
    const overlay = document.createElement('div');
    overlay.id = 'sms-capture-overlay';
    overlay.innerHTML = `
        <style>
            #sms-capture-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #sms-capture-box {
                background: white;
                padding: 30px;
                border-radius: 10px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            #sms-capture-box h2 {
                margin: 0 0 20px 0;
                color: #FF5A5F;
            }
            #sms-capture-box .field {
                margin-bottom: 15px;
            }
            #sms-capture-box label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
                color: #333;
            }
            #sms-capture-box input, #sms-capture-box select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
            }
            #sms-capture-box .btn-row {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }
            #sms-capture-box button {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
            }
            #sms-capture-box .btn-primary {
                background: #FF5A5F;
                color: white;
            }
            #sms-capture-box .btn-secondary {
                background: #eee;
                color: #333;
            }
            #sms-capture-box .status {
                margin-top: 15px;
                padding: 10px;
                border-radius: 5px;
                display: none;
            }
            #sms-capture-box .status.success {
                background: #d4edda;
                color: #155724;
                display: block;
            }
            #sms-capture-box .status.error {
                background: #f8d7da;
                color: #721c24;
                display: block;
            }
        </style>
        <div id="sms-capture-box">
            <h2>Capture Airbnb</h2>
            <div id="sms-capture-fields"></div>
            <div class="btn-row">
                <button class="btn-secondary" onclick="document.getElementById('sms-capture-overlay').remove()">Annuler</button>
                <button class="btn-primary" id="sms-capture-save">Enregistrer</button>
            </div>
            <div id="sms-capture-status" class="status"></div>
        </div>
    `;
    document.body.appendChild(overlay);

    // Fonctions d'extraction des donnees
    function extractData() {
        const data = {
            url: window.location.href,
            nom: '',
            ville: '',
            quartier: '',
            type_logement: 'appartement',
            capacite: null,
            chambres: null,
            lits: null,
            salles_bain: null,
            note_moyenne: null,
            nb_avis: null,
            superhost: false,
            photo_url: '',
            prix_nuit: null,
            date_sejour: new Date().toISOString().split('T')[0]
        };

        // Titre de l'annonce
        const titleEl = document.querySelector('h1') ||
                       document.querySelector('[data-section-id="TITLE_DEFAULT"] h1') ||
                       document.querySelector('[data-testid="listing-title"]');
        if (titleEl) {
            data.nom = titleEl.textContent.trim();
        }

        // Photo principale
        const photoEl = document.querySelector('img[data-original-uri]') ||
                       document.querySelector('[data-testid="photo-viewer-section"] img') ||
                       document.querySelector('picture img');
        if (photoEl) {
            data.photo_url = photoEl.src || photoEl.getAttribute('data-original-uri');
        }

        // Superhost
        data.superhost = !!document.body.innerHTML.match(/superhost|superhôte/i);

        // Note et avis
        const ratingEl = document.querySelector('[data-testid="pdp-reviews-highlight-banner-host-rating"]') ||
                        document.body.innerHTML.match(/(\d+[.,]\d+)\s*·\s*(\d+)\s*(?:avis|reviews)/i);
        if (ratingEl) {
            if (typeof ratingEl === 'object' && ratingEl.textContent) {
                const match = ratingEl.textContent.match(/(\d+[.,]\d+)/);
                if (match) data.note_moyenne = parseFloat(match[1].replace(',', '.'));
            } else if (ratingEl) {
                data.note_moyenne = parseFloat(ratingEl[1].replace(',', '.'));
                data.nb_avis = parseInt(ratingEl[2]);
            }
        }

        // Chercher le nombre d'avis separement
        const avisMatch = document.body.innerHTML.match(/(\d+)\s*(?:avis|reviews|commentaires)/i);
        if (avisMatch && !data.nb_avis) {
            data.nb_avis = parseInt(avisMatch[1]);
        }

        // Details du logement (voyageurs, chambres, lits, sdb)
        const detailsText = document.body.innerText;

        const capaciteMatch = detailsText.match(/(\d+)\s*(?:voyageur|guest|personne)/i);
        if (capaciteMatch) data.capacite = parseInt(capaciteMatch[1]);

        const chambresMatch = detailsText.match(/(\d+)\s*(?:chambre|bedroom)/i);
        if (chambresMatch) data.chambres = parseInt(chambresMatch[1]);

        const litsMatch = detailsText.match(/(\d+)\s*(?:lit|bed)/i);
        if (litsMatch) data.lits = parseInt(litsMatch[1]);

        const sdbMatch = detailsText.match(/(\d+(?:[.,]\d+)?)\s*(?:salle|bathroom)/i);
        if (sdbMatch) data.salles_bain = parseFloat(sdbMatch[1].replace(',', '.'));

        // Prix - chercher dans differents endroits
        const priceEl = document.querySelector('[data-testid="price-summary"] span') ||
                       document.querySelector('._tyxjp1') ||
                       document.querySelector('[data-testid="book-it-default"] span');
        if (priceEl) {
            const priceMatch = priceEl.textContent.match(/(\d+)/);
            if (priceMatch) data.prix_nuit = parseInt(priceMatch[1]);
        }

        // Chercher le prix dans le texte
        if (!data.prix_nuit) {
            const priceTextMatch = detailsText.match(/(\d+)\s*(?:€|EUR)\s*(?:par nuit|\/\s*nuit|per night)/i);
            if (priceTextMatch) data.prix_nuit = parseInt(priceTextMatch[1]);
        }

        // Localisation
        const locationEl = document.querySelector('[data-section-id="LOCATION_DEFAULT"]') ||
                          document.querySelector('[data-testid="listing-location-and-nearby"]');
        if (locationEl) {
            const locationText = locationEl.textContent;
            const locationParts = locationText.split(',');
            if (locationParts.length >= 1) data.quartier = locationParts[0].trim();
            if (locationParts.length >= 2) data.ville = locationParts[locationParts.length - 1].trim();
        }

        // Date de sejour depuis l'URL
        const checkInMatch = window.location.search.match(/check_in=(\d{4}-\d{2}-\d{2})/);
        if (checkInMatch) {
            data.date_sejour = checkInMatch[1];
        }

        return data;
    }

    // Extraire les donnees
    const data = extractData();

    // Creer le formulaire
    const fieldsContainer = document.getElementById('sms-capture-fields');
    fieldsContainer.innerHTML = `
        <div class="field">
            <label>Nom de l'annonce</label>
            <input type="text" id="cap-nom" value="${data.nom.replace(/"/g, '&quot;')}">
        </div>
        <div class="field">
            <label>Prix par nuit (EUR)</label>
            <input type="number" id="cap-prix" value="${data.prix_nuit || ''}">
        </div>
        <div class="field">
            <label>Date du sejour</label>
            <input type="date" id="cap-date" value="${data.date_sejour}">
        </div>
        <div class="field">
            <label>Ville</label>
            <input type="text" id="cap-ville" value="${data.ville}">
        </div>
        <div class="field">
            <label>Capacite (voyageurs)</label>
            <input type="number" id="cap-capacite" value="${data.capacite || ''}">
        </div>
        <div class="field">
            <label>Chambres</label>
            <input type="number" id="cap-chambres" value="${data.chambres || ''}">
        </div>
    `;

    // Gerer l'enregistrement
    document.getElementById('sms-capture-save').addEventListener('click', function() {
        const statusEl = document.getElementById('sms-capture-status');
        statusEl.className = 'status';
        statusEl.textContent = 'Envoi en cours...';
        statusEl.style.display = 'block';
        statusEl.style.background = '#fff3cd';
        statusEl.style.color = '#856404';

        // Mettre a jour les donnees avec les valeurs du formulaire
        data.nom = document.getElementById('cap-nom').value;
        data.prix_nuit = document.getElementById('cap-prix').value ? parseFloat(document.getElementById('cap-prix').value) : null;
        data.date_sejour = document.getElementById('cap-date').value;
        data.ville = document.getElementById('cap-ville').value;
        data.capacite = document.getElementById('cap-capacite').value ? parseInt(document.getElementById('cap-capacite').value) : null;
        data.chambres = document.getElementById('cap-chambres').value ? parseInt(document.getElementById('cap-chambres').value) : null;

        // Envoyer au serveur
        fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                statusEl.className = 'status success';
                statusEl.textContent = result.is_new ?
                    'Nouveau concurrent ajoute !' :
                    'Concurrent mis a jour !';
                setTimeout(() => {
                    overlay.remove();
                }, 2000);
            } else {
                statusEl.className = 'status error';
                statusEl.textContent = 'Erreur: ' + (result.error || 'Erreur inconnue');
            }
        })
        .catch(error => {
            statusEl.className = 'status error';
            statusEl.textContent = 'Erreur de connexion: ' + error.message;
        });
    });
})();
