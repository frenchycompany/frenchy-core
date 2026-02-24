/**
 * Frenchy Conciergerie - JavaScript principal v2.0
 * Dark Mode, Lightbox, Carousel, Scroll Reveal, Lazy Loading
 */

document.addEventListener('DOMContentLoaded', function() {

    // ========================================
    // DARK MODE
    // ========================================
    const themeToggle = document.querySelector('.theme-toggle');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    }

    function getTheme() {
        return localStorage.getItem('theme') || (prefersDark.matches ? 'dark' : 'light');
    }

    // Initialize theme
    setTheme(getTheme());

    // Toggle theme on click
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            setTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    // ========================================
    // SCROLL REVEAL
    // ========================================
    const revealElements = document.querySelectorAll('.reveal');

    function checkReveal() {
        const windowHeight = window.innerHeight;
        const revealPoint = 150;

        revealElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;

            if (elementTop < windowHeight - revealPoint) {
                element.classList.add('visible');
            }
        });
    }

    window.addEventListener('scroll', checkReveal);
    checkReveal(); // Check on load

    // ========================================
    // LAZY LOADING IMAGES
    // ========================================
    const lazyImages = document.querySelectorAll('img[data-src]');

    const lazyLoad = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px 0px'
    });

    lazyImages.forEach(img => {
        img.classList.add('lazy-image');
        lazyLoad.observe(img);
    });

    // ========================================
    // LIGHTBOX
    // ========================================
    class Lightbox {
        constructor() {
            this.images = [];
            this.currentIndex = 0;
            this.overlay = null;
            this.init();
        }

        init() {
            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'lightbox-overlay';
            this.overlay.innerHTML = `
                <div class="lightbox-content">
                    <button class="lightbox-close">&times;</button>
                    <button class="lightbox-nav lightbox-prev">&#10094;</button>
                    <button class="lightbox-nav lightbox-next">&#10095;</button>
                    <img src="" alt="">
                    <div class="lightbox-caption"></div>
                    <div class="lightbox-counter"></div>
                </div>
            `;
            document.body.appendChild(this.overlay);

            // Bind events
            this.overlay.querySelector('.lightbox-close').addEventListener('click', () => this.close());
            this.overlay.querySelector('.lightbox-prev').addEventListener('click', () => this.prev());
            this.overlay.querySelector('.lightbox-next').addEventListener('click', () => this.next());
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) this.close();
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (!this.overlay.classList.contains('active')) return;
                if (e.key === 'Escape') this.close();
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
            });

            // Init gallery items
            document.querySelectorAll('.gallery-clickable, [data-lightbox]').forEach((item, index) => {
                const img = item.querySelector('img') || item;
                const src = img.dataset.full || img.src;
                const caption = img.alt || item.dataset.caption || '';

                this.images.push({ src, caption });

                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open(index);
                });
            });
        }

        open(index) {
            this.currentIndex = index;
            this.showImage();
            this.overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        showImage() {
            const image = this.images[this.currentIndex];
            const img = this.overlay.querySelector('img');
            const caption = this.overlay.querySelector('.lightbox-caption');
            const counter = this.overlay.querySelector('.lightbox-counter');

            img.src = image.src;
            img.alt = image.caption;
            caption.textContent = image.caption;
            counter.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
        }

        prev() {
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
            this.showImage();
        }

        next() {
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
            this.showImage();
        }
    }

    // Initialize lightbox if gallery exists
    if (document.querySelector('.gallery-clickable, [data-lightbox]')) {
        new Lightbox();
    }

    // ========================================
    // CAROUSEL / SLIDER
    // ========================================
    class Carousel {
        constructor(element, options = {}) {
            this.element = element;
            this.track = element.querySelector('.carousel-track');
            this.slides = element.querySelectorAll('.carousel-slide');
            this.currentIndex = 0;
            this.autoplay = options.autoplay !== false;
            this.interval = options.interval || 5000;
            this.autoplayTimer = null;

            if (this.slides.length > 1) {
                this.init();
            }
        }

        init() {
            this.createNavigation();
            this.createDots();

            if (this.autoplay) {
                this.startAutoplay();
                this.element.addEventListener('mouseenter', () => this.stopAutoplay());
                this.element.addEventListener('mouseleave', () => this.startAutoplay());
            }

            // Touch/swipe support
            let touchStartX = 0;
            let touchEndX = 0;

            this.element.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            this.element.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                if (touchStartX - touchEndX > 50) this.next();
                if (touchEndX - touchStartX > 50) this.prev();
            }, { passive: true });
        }

        createNavigation() {
            const prevBtn = document.createElement('button');
            prevBtn.className = 'carousel-nav carousel-prev';
            prevBtn.innerHTML = '&#10094;';
            prevBtn.addEventListener('click', () => this.prev());

            const nextBtn = document.createElement('button');
            nextBtn.className = 'carousel-nav carousel-next';
            nextBtn.innerHTML = '&#10095;';
            nextBtn.addEventListener('click', () => this.next());

            this.element.appendChild(prevBtn);
            this.element.appendChild(nextBtn);
        }

        createDots() {
            const dotsContainer = document.createElement('div');
            dotsContainer.className = 'carousel-dots';

            this.slides.forEach((_, index) => {
                const dot = document.createElement('button');
                dot.className = 'carousel-dot' + (index === 0 ? ' active' : '');
                dot.addEventListener('click', () => this.goTo(index));
                dotsContainer.appendChild(dot);
            });

            this.element.appendChild(dotsContainer);
            this.dots = dotsContainer.querySelectorAll('.carousel-dot');
        }

        updateDots() {
            this.dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === this.currentIndex);
            });
        }

        goTo(index) {
            this.currentIndex = index;
            this.track.style.transform = `translateX(-${this.currentIndex * 100}%)`;
            this.updateDots();
        }

        prev() {
            this.currentIndex = (this.currentIndex - 1 + this.slides.length) % this.slides.length;
            this.goTo(this.currentIndex);
        }

        next() {
            this.currentIndex = (this.currentIndex + 1) % this.slides.length;
            this.goTo(this.currentIndex);
        }

        startAutoplay() {
            this.stopAutoplay();
            this.autoplayTimer = setInterval(() => this.next(), this.interval);
            this.element.classList.add('playing');
        }

        stopAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            }
            this.element.classList.remove('playing');
        }
    }

    // Initialize carousels
    document.querySelectorAll('.carousel').forEach(carousel => {
        new Carousel(carousel, {
            autoplay: carousel.dataset.autoplay !== 'false',
            interval: parseInt(carousel.dataset.interval) || 5000
        });
    });

    // ========================================
    // SMOOTH SCROLL
    // ========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ========================================
    // FORM VALIDATION
    // ========================================
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;

            this.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            this.querySelectorAll('[type="email"]').forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    valid = false;
                    field.classList.add('is-invalid');
                }
            });

            if (!valid) {
                e.preventDefault();
                this.querySelector('.is-invalid')?.focus();
            }
        });
    });

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ========================================
    // NEWSLETTER AJAX
    // ========================================
    document.querySelectorAll('.newsletter-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', '1');

            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Envoi...';

            try {
                const response = await fetch('newsletter.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.reset();
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Une erreur est survenue', 'error');
            }

            btn.disabled = false;
            btn.textContent = originalText;
        });
    });

    // ========================================
    // NOTIFICATIONS
    // ========================================
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
            animation: fadeInRight 0.3s ease;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeInRight 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // Expose globally
    window.showNotification = showNotification;

    // ========================================
    // BACK TO TOP
    // ========================================
    const backToTop = document.createElement('button');
    backToTop.className = 'back-to-top';
    backToTop.innerHTML = '↑';
    backToTop.style.cssText = `
        position: fixed;
        bottom: 80px;
        right: 20px;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--bleu-frenchy);
        color: white;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 999;
    `;

    document.body.appendChild(backToTop);

    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            backToTop.style.opacity = '1';
            backToTop.style.visibility = 'visible';
        } else {
            backToTop.style.opacity = '0';
            backToTop.style.visibility = 'hidden';
        }
    });

    // ========================================
    // MOBILE MENU
    // ========================================
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });
    }

});

// ========================================
// UTILITY FUNCTIONS
// ========================================

// Format number with spaces
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Format currency
function formatCurrency(amount, currency = 'EUR') {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 0
    }).format(amount);
}

// Debounce function
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

// Throttle function
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}
