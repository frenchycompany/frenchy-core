/**
 * Fonctions utilitaires pour FC-gestion
 */

// ============================================
// LOADING OVERLAY
// ============================================

/**
 * Affiche un overlay de chargement
 */
function showLoading(message = 'Chargement en cours...') {
    // Supprimer l'overlay existant
    hideLoading();

    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div style="text-align: center; color: white;">
            <div class="spinner" style="
                width: 60px;
                height: 60px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spinner-rotate 0.8s linear infinite;
                margin: 0 auto 20px;
            "></div>
            <p style="font-size: 18px; margin: 0;">${message}</p>
        </div>
    `;

    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

/**
 * Cache l'overlay de chargement
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.remove();
        document.body.style.overflow = '';
    }
}

// ============================================
// CONFIRMATION DIALOG
// ============================================

/**
 * Affiche une boîte de dialogue de confirmation
 * @param {string} message
 * @param {string} confirmText
 * @param {string} cancelText
 * @returns {Promise<boolean>}
 */
function confirm(message, confirmText = 'Confirmer', cancelText = 'Annuler') {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.2s ease;
        `;

        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 12px;
                padding: 24px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                animation: slideUp 0.3s ease;
            ">
                <h3 style="margin: 0 0 16px; font-size: 20px; color: #333;">Confirmation</h3>
                <p style="margin: 0 0 24px; color: #666; line-height: 1.5;">${message}</p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button class="btn-cancel" style="
                        padding: 10px 20px;
                        border: 1px solid #ddd;
                        background: white;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;
                        transition: all 0.2s;
                    ">${cancelText}</button>
                    <button class="btn-confirm" style="
                        padding: 10px 20px;
                        border: none;
                        background: #dc3545;
                        color: white;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        font-weight: 500;
                        transition: all 0.2s;
                    ">${confirmText}</button>
                </div>
            </div>
        `;

        // Ajouter animations
        if (!document.getElementById('modal-animations')) {
            const style = document.createElement('style');
            style.id = 'modal-animations';
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideUp {
                    from {
                        transform: translateY(30px);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                .btn-cancel:hover {
                    background: #f8f9fa !important;
                }
                .btn-confirm:hover {
                    background: #c82333 !important;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(modal);

        const btnConfirm = modal.querySelector('.btn-confirm');
        const btnCancel = modal.querySelector('.btn-cancel');

        btnConfirm.addEventListener('click', () => {
            modal.remove();
            resolve(true);
        });

        btnCancel.addEventListener('click', () => {
            modal.remove();
            resolve(false);
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                resolve(false);
            }
        });
    });
}

// ============================================
// AJAX HELPERS
// ============================================

/**
 * Effectue une requête AJAX avec gestion d'erreurs
 * @param {string} url
 * @param {object} options
 * @returns {Promise}
 */
async function ajax(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = { ...defaults, ...options };

    try {
        const response = await fetch(url, config);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erreur AJAX:', error);
        toast.error('Une erreur est survenue lors de la communication avec le serveur');
        throw error;
    }
}

/**
 * Effectue une requête POST avec des données de formulaire
 * @param {string} url
 * @param {FormData|object} data
 * @returns {Promise}
 */
async function postForm(url, data) {
    const formData = data instanceof FormData ? data : new FormData();

    if (!(data instanceof FormData)) {
        for (const key in data) {
            formData.append(key, data[key]);
        }
    }

    return ajax(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
}

// ============================================
// DEBOUNCE
// ============================================

/**
 * Crée une fonction debounced
 * @param {Function} func
 * @param {number} wait
 * @returns {Function}
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// FORMAT HELPERS
// ============================================

/**
 * Formate un nombre en euros
 * @param {number} amount
 * @returns {string}
 */
function formatEuro(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Formate une date
 * @param {string|Date} date
 * @returns {string}
 */
function formatDate(date) {
    const d = typeof date === 'string' ? new Date(date) : date;
    return new Intl.DateTimeFormat('fr-FR').format(d);
}

/**
 * Formate une date et heure
 * @param {string|Date} date
 * @returns {string}
 */
function formatDateTime(date) {
    const d = typeof date === 'string' ? new Date(date) : date;
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short'
    }).format(d);
}

// ============================================
// VALIDATION
// ============================================

/**
 * Valide un email
 * @param {string} email
 * @returns {boolean}
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Valide un numéro de téléphone français
 * @param {string} phone
 * @returns {boolean}
 */
function isValidPhone(phone) {
    const re = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
    return re.test(phone);
}

// ============================================
// DOM READY
// ============================================

/**
 * Exécute une fonction quand le DOM est prêt
 * @param {Function} fn
 */
function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

// ============================================
// EXPORT GLOBAL
// ============================================

window.utils = {
    showLoading,
    hideLoading,
    confirm,
    ajax,
    postForm,
    debounce,
    formatEuro,
    formatDate,
    formatDateTime,
    isValidEmail,
    isValidPhone,
    ready
};
