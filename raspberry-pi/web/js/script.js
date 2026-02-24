/**
 * ============================================
 * GESTION SMS - JavaScript Moderne
 * Script principal pour l'interface web
 * ============================================
 */

// Initialisation au chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    console.log('=€ Interface de gestion SMS chargée');

    // Initialiser toutes les fonctionnalités
    initAnimations();
    initFormValidation();
    initTooltips();
    initAlerts();
    initNavigation();
    initCounters();
    initSearchFilters();
});

/* ============================================
   ANIMATIONS ET EFFETS VISUELS
   ============================================ */

/**
 * Initialiser les animations d'entrée
 */
function initAnimations() {
    // Animer les cartes au scroll
    const cards = document.querySelectorAll('.card');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    entry.target.style.transition = 'all 0.5s ease';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, 100);
            }
        });
    }, {
        threshold: 0.1
    });

    cards.forEach(card => {
        observer.observe(card);
    });

    // Animation au survol des boutons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/* ============================================
   VALIDATION DE FORMULAIRES
   ============================================ */

/**
 * Initialiser la validation des formulaires
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        // Validation en temps réel
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });

        // Validation à la soumission
        form.addEventListener('submit', function(e) {
            let isValid = true;

            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotification('Veuillez corriger les erreurs du formulaire', 'error');
            }
        });
    });
}

/**
 * Valider un champ spécifique
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');

    // Réinitialiser l'état
    field.classList.remove('is-valid', 'is-invalid');

    // Champ requis vide
    if (required && value === '') {
        field.classList.add('is-invalid');
        showFieldError(field, 'Ce champ est obligatoire');
        return false;
    }

    // Validation du numéro de téléphone
    if (field.name === 'receiver' || field.id === 'receiver') {
        const phoneRegex = /^(\+33|0)[1-9](\d{8})$/;
        if (value && !phoneRegex.test(value.replace(/\s/g, ''))) {
            field.classList.add('is-invalid');
            showFieldError(field, 'Numéro de téléphone invalide (ex: 0612345678 ou +33612345678)');
            return false;
        }
    }

    // Validation du message SMS (longueur)
    if (field.name === 'message' || field.id === 'message') {
        if (value.length > 160) {
            field.classList.add('is-invalid');
            showFieldError(field, `Message trop long (${value.length}/160 caractères)`);
            return false;
        }
        // Afficher le compteur de caractères
        updateCharCount(field, value.length);
    }

    // Validation de l'email
    if (type === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (value && !emailRegex.test(value)) {
            field.classList.add('is-invalid');
            showFieldError(field, 'Adresse email invalide');
            return false;
        }
    }

    // Champ valide
    if (value !== '') {
        field.classList.add('is-valid');
        removeFieldError(field);
    }

    return true;
}

/**
 * Afficher une erreur sur un champ
 */
function showFieldError(field, message) {
    removeFieldError(field);

    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback d-block';
    errorDiv.textContent = message;
    errorDiv.id = `${field.id || field.name}-error`;

    field.parentNode.appendChild(errorDiv);
}

/**
 * Supprimer l'erreur d'un champ
 */
function removeFieldError(field) {
    const errorId = `${field.id || field.name}-error`;
    const existingError = document.getElementById(errorId);
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Mettre à jour le compteur de caractères
 */
function updateCharCount(field, count) {
    let counter = document.getElementById(`${field.id}-counter`);

    if (!counter) {
        counter = document.createElement('small');
        counter.id = `${field.id}-counter`;
        counter.className = 'form-text';
        field.parentNode.appendChild(counter);
    }

    const remaining = 160 - count;
    counter.textContent = `${count}/160 caractères`;

    if (remaining < 20) {
        counter.style.color = '#FF6B6B';
    } else {
        counter.style.color = '#7F8C8D';
    }
}

/* ============================================
   TOOLTIPS ET POPOVERS
   ============================================ */

/**
 * Initialiser les tooltips Bootstrap
 */
function initTooltips() {
    // Activer les tooltips Bootstrap si jQuery est disponible
    if (typeof $ !== 'undefined' && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }
}

/* ============================================
   GESTION DES ALERTES
   ============================================ */

/**
 * Initialiser les alertes auto-fermantes
 */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(alert => {
        // Ajouter un bouton de fermeture si absent
        if (!alert.querySelector('.close')) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';
            closeBtn.onclick = () => dismissAlert(alert);
            alert.insertBefore(closeBtn, alert.firstChild);
        }

        // Auto-fermeture après 5 secondes
        setTimeout(() => {
            dismissAlert(alert);
        }, 5000);
    });
}

/**
 * Fermer une alerte avec animation
 */
function dismissAlert(alert) {
    alert.style.transition = 'opacity 0.3s, transform 0.3s';
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-20px)';

    setTimeout(() => {
        alert.remove();
    }, 300);
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    const alertTypes = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    };

    const icons = {
        'success': '<i class="fas fa-check-circle"></i>',
        'error': '<i class="fas fa-exclamation-circle"></i>',
        'warning': '<i class="fas fa-exclamation-triangle"></i>',
        'info': '<i class="fas fa-info-circle"></i>'
    };

    const alert = document.createElement('div');
    alert.className = `alert ${alertTypes[type]} alert-dismissible fade show`;
    alert.setAttribute('role', 'alert');
    alert.innerHTML = `
        ${icons[type]} ${message}
        <button type="button" class="close" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;

    // Ajouter au début du container
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alert, container.firstChild);

        // Événement de fermeture
        const closeBtn = alert.querySelector('.close');
        closeBtn.addEventListener('click', () => dismissAlert(alert));

        // Auto-fermeture
        if (duration > 0) {
            setTimeout(() => dismissAlert(alert), duration);
        }
    }
}

/* ============================================
   NAVIGATION ACTIVE
   ============================================ */

/**
 * Marquer l'élément de navigation actif
 */
function initNavigation() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
            link.style.background = 'rgba(255, 255, 255, 0.2)';
            link.style.borderRadius = '8px';
        }
    });
}

/* ============================================
   COMPTEURS ANIMÉS
   ============================================ */

/**
 * Animer les compteurs de statistiques
 */
function initCounters() {
    const counters = document.querySelectorAll('.stat-number, .counter');

    counters.forEach(counter => {
        const target = parseInt(counter.textContent) || 0;
        const duration = 2000; // 2 secondes
        const step = target / (duration / 16); // 60 FPS
        let current = 0;

        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };

        // Démarrer l'animation quand l'élément est visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });

        observer.observe(counter);
    });
}

/* ============================================
   RECHERCHE ET FILTRES
   ============================================ */

/**
 * Initialiser les fonctions de recherche
 */
function initSearchFilters() {
    const searchInputs = document.querySelectorAll('[data-search]');

    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetSelector = this.getAttribute('data-search');
            const items = document.querySelectorAll(targetSelector);

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = '';
                    item.style.animation = 'fadeIn 0.3s';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

/* ============================================
   CONFIRMATION D'ACTIONS
   ============================================ */

/**
 * Demander confirmation pour les actions importantes
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Attacher des confirmations aux liens/boutons de suppression
 */
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('btn-danger') ||
        e.target.classList.contains('delete-btn')) {
        e.preventDefault();

        confirmAction('Êtes-vous sûr de vouloir supprimer cet élément ?', () => {
            // Si c'est un lien, suivre le lien
            if (e.target.href) {
                window.location.href = e.target.href;
            }
            // Si c'est un bouton de formulaire, soumettre le formulaire
            else if (e.target.form) {
                e.target.form.submit();
            }
        });
    }
});

/* ============================================
   FORMATAGE AUTOMATIQUE
   ============================================ */

/**
 * Formater automatiquement les numéros de téléphone
 */
document.addEventListener('input', function(e) {
    if (e.target.name === 'receiver' || e.target.id === 'receiver') {
        let value = e.target.value.replace(/\s/g, '');

        // Formater le numéro en groupes de 2
        if (value.length > 0) {
            value = value.match(/.{1,2}/g).join(' ');
            e.target.value = value;
        }
    }
});

/* ============================================
   AMÉLIORATION DE L'EXPÉRIENCE UTILISATEUR
   ============================================ */

/**
 * Smooth scroll pour les ancres
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

/**
 * Chargement paresseux des images
 */
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });

    document.querySelectorAll('img.lazy').forEach(img => {
        imageObserver.observe(img);
    });
}

/**
 * Indicateur de chargement pour les formulaires
 */
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn && !form.hasAttribute('data-no-loader')) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
    }
});

/* ============================================
   UTILITAIRES
   ============================================ */

/**
 * Copier du texte dans le presse-papiers
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Texte copié dans le presse-papiers', 'success', 2000);
    }).catch(() => {
        showNotification('Erreur lors de la copie', 'error', 2000);
    });
}

/**
 * Formater une date en français
 */
function formatDateFR(dateString) {
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return date.toLocaleDateString('fr-FR', options);
}

/**
 * Tronquer un texte
 */
function truncate(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

/**
 * Debounce pour optimiser les événements fréquents
 */
function debounce(func, wait) {
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

// Log de fin de chargement
console.log(' Tous les scripts sont chargés et initialisés');
