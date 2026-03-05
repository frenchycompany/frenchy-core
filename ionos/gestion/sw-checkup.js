/**
 * Service Worker pour le mode hors-ligne du checkup
 * Cache les pages essentielles et les donnees du checkup en cours
 * Synchronise les modifications au retour en ligne
 */

const CACHE_NAME = 'frenchy-checkup-v1';
const URLS_TO_CACHE = [
    '/pages/checkup_faire.php',
    '/pages/checkup_logement.php',
    '/pages/checkup_rapport.php',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
];

// Installation : pre-cache des ressources statiques
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(URLS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

// Activation : nettoyage des anciens caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.filter(function(name) {
                    return name !== CACHE_NAME;
                }).map(function(name) {
                    return caches.delete(name);
                })
            );
        })
    );
    self.clients.claim();
});

// Strategie : Network First, fallback sur cache
self.addEventListener('fetch', function(event) {
    // Ne pas intercepter les requetes POST (sauvegardes AJAX)
    if (event.request.method === 'POST') {
        event.respondWith(
            fetch(event.request.clone()).catch(function() {
                // Stocker la requete POST pour la rejouer plus tard
                return saveOfflineRequest(event.request.clone()).then(function() {
                    return new Response(JSON.stringify({
                        success: true,
                        offline: true,
                        message: 'Sauvegarde hors-ligne, synchronisation au retour en ligne.'
                    }), {
                        headers: { 'Content-Type': 'application/json' }
                    });
                });
            })
        );
        return;
    }

    // GET : Network first, fallback cache
    event.respondWith(
        fetch(event.request).then(function(response) {
            // Mettre en cache la reponse
            if (response.status === 200) {
                var responseClone = response.clone();
                caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(event.request, responseClone);
                });
            }
            return response;
        }).catch(function() {
            return caches.match(event.request).then(function(response) {
                if (response) return response;
                // Page offline par defaut
                return new Response(
                    '<html><body style="font-family:Arial;text-align:center;padding:40px;">' +
                    '<h2>Mode hors-ligne</h2>' +
                    '<p>Cette page n\'est pas disponible hors-ligne.</p>' +
                    '<p>Vos modifications seront synchronisees au retour en ligne.</p>' +
                    '</body></html>',
                    { headers: { 'Content-Type': 'text/html' } }
                );
            });
        })
    );
});

// Stocker les requetes POST hors-ligne dans IndexedDB
function saveOfflineRequest(request) {
    return request.text().then(function(body) {
        return openOfflineDB().then(function(db) {
            return new Promise(function(resolve, reject) {
                var tx = db.transaction('requests', 'readwrite');
                var store = tx.objectStore('requests');
                store.add({
                    url: request.url,
                    method: request.method,
                    body: body,
                    headers: Object.fromEntries(request.headers.entries()),
                    timestamp: Date.now()
                });
                tx.oncomplete = resolve;
                tx.onerror = reject;
            });
        });
    });
}

function openOfflineDB() {
    return new Promise(function(resolve, reject) {
        var request = indexedDB.open('frenchy-offline', 1);
        request.onupgradeneeded = function(event) {
            var db = event.target.result;
            if (!db.objectStoreNames.contains('requests')) {
                db.createObjectStore('requests', { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = function(event) { resolve(event.target.result); };
        request.onerror = function(event) { reject(event.target.error); };
    });
}

// Synchroniser les requetes en attente quand on revient en ligne
self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-checkup') {
        event.waitUntil(replayOfflineRequests());
    }
});

function replayOfflineRequests() {
    return openOfflineDB().then(function(db) {
        return new Promise(function(resolve, reject) {
            var tx = db.transaction('requests', 'readwrite');
            var store = tx.objectStore('requests');
            var getAll = store.getAll();
            getAll.onsuccess = function() {
                var requests = getAll.result;
                var promises = requests.map(function(req) {
                    return fetch(req.url, {
                        method: req.method,
                        body: req.body,
                        headers: req.headers
                    }).then(function() {
                        // Supprimer apres succes
                        var delTx = db.transaction('requests', 'readwrite');
                        delTx.objectStore('requests').delete(req.id);
                    }).catch(function() {
                        // Garder pour reessayer
                    });
                });
                Promise.all(promises).then(resolve).catch(resolve);
            };
            getAll.onerror = reject;
        });
    });
}

// Ecouter le retour en ligne pour synchroniser
self.addEventListener('message', function(event) {
    if (event.data === 'sync-now') {
        replayOfflineRequests().then(function() {
            self.clients.matchAll().then(function(clients) {
                clients.forEach(function(client) {
                    client.postMessage('sync-complete');
                });
            });
        });
    }
});
