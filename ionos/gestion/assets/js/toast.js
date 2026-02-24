/**
 * Toast Notifications System
 * Système de notifications toast moderne pour FC-gestion
 */

class Toast {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Créer le conteneur de toasts s'il n'existe pas
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
            `;
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    /**
     * Affiche une notification toast
     * @param {string} message - Le message à afficher
     * @param {string} type - Type de notification (success, error, warning, info)
     * @param {number} duration - Durée d'affichage en ms (0 = infini)
     */
    show(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        // Icônes selon le type
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        // Couleurs selon le type
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };

        toast.style.cssText = `
            background: white;
            border-left: 4px solid ${colors[type] || colors.info};
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
            transition: all 0.3s ease;
            cursor: pointer;
        `;

        toast.innerHTML = `
            <div style="
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: ${colors[type] || colors.info};
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                font-weight: bold;
                flex-shrink: 0;
            ">
                ${icons[type] || icons.info}
            </div>
            <div style="flex: 1; color: #333; font-size: 14px; line-height: 1.4;">
                ${message}
            </div>
            <button style="
                background: none;
                border: none;
                color: #999;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            " onclick="this.parentElement.remove()">×</button>
        `;

        // Ajouter les animations CSS si elles n'existent pas
        if (!document.getElementById('toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
                .toast:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
                }
            `;
            document.head.appendChild(style);
        }

        // Ajouter au conteneur
        this.container.appendChild(toast);

        // Retirer automatiquement après la durée spécifiée
        if (duration > 0) {
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // Permettre de fermer en cliquant
        toast.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }
        });

        return toast;
    }

    success(message, duration = 3000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 3000) {
        return this.show(message, 'info', duration);
    }

    /**
     * Affiche un toast de chargement
     * @param {string} message
     * @returns {HTMLElement} Element à passer à hideLoading()
     */
    loading(message = 'Chargement en cours...') {
        const toast = document.createElement('div');
        toast.className = 'toast toast-loading';

        toast.style.cssText = `
            background: white;
            border-left: 4px solid #17a2b8;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
        `;

        toast.innerHTML = `
            <div class="spinner" style="
                width: 24px;
                height: 24px;
                border: 3px solid #e9ecef;
                border-top-color: #17a2b8;
                border-radius: 50%;
                animation: spinner-rotate 0.8s linear infinite;
            "></div>
            <div style="flex: 1; color: #333; font-size: 14px;">
                ${message}
            </div>
        `;

        this.container.appendChild(toast);
        return toast;
    }

    /**
     * Cache un toast de chargement
     * @param {HTMLElement} toast
     */
    hideLoading(toast) {
        if (toast && toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }

    /**
     * Efface tous les toasts
     */
    clear() {
        while (this.container.firstChild) {
            this.container.removeChild(this.container.firstChild);
        }
    }
}

// Instance globale
const toast = new Toast();

// Exposer globalement
window.toast = toast;
