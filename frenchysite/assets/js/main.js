/**
 * Location premium — Main JS
 * Minimal, dependency-free.
 */

(function () {
    'use strict';

    /* ── Mobile menu ── */
    var toggle = document.getElementById('vf-menu-toggle');
    var nav    = document.getElementById('vf-nav');

    if (toggle && nav) {
        function toggleMenu() {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
        }
        toggle.addEventListener('click', toggleMenu);
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMenu();
            }
        });
    }

    /* ── Close mobile nav on anchor click ── */
    document.querySelectorAll('.vf-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (nav && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Ouvrir le menu');
                }
            }
        });
    });

    /* ── Smooth scroll for anchor links ── */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var href = this.getAttribute('href');
            if (href === '#') return;
            var target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                var headerH = document.querySelector('.vf-header')
                    ? document.querySelector('.vf-header').offsetHeight
                    : 0;
                var top = target.getBoundingClientRect().top + window.pageYOffset - headerH;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
        });
    });

    /* ── Header shadow on scroll ── */
    var header = document.querySelector('.vf-header');
    if (header) {
        var onScroll = function () {
            if (window.scrollY > 10) {
                header.classList.add('is-scrolled');
            } else {
                header.classList.remove('is-scrolled');
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ── Contact form (AJAX) ── */
    var contactForm = document.getElementById('vf-contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn    = document.getElementById('vf-contact-submit');
            var status = document.getElementById('vf-contact-status');
            var fd     = new FormData(contactForm);

            btn.disabled = true;
            btn.textContent = 'Envoi...';
            status.hidden = true;

            fetch('contact-send.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    status.hidden = false;
                    status.className = 'vf-contact-form-status is-success';
                    status.textContent = 'Message envoyé avec succès. Nous vous répondrons rapidement.';
                    contactForm.reset();
                } else {
                    status.hidden = false;
                    status.className = 'vf-contact-form-status is-error';
                    status.textContent = r.error || 'Erreur lors de l\'envoi.';
                }
                btn.disabled = false;
                btn.textContent = 'Envoyer le message';
            })
            .catch(function () {
                status.hidden = false;
                status.className = 'vf-contact-form-status is-error';
                status.textContent = 'Erreur réseau. Veuillez réessayer.';
                btn.disabled = false;
                btn.textContent = 'Envoyer le message';
            });
        });
    }

    /* ── Reveal-on-scroll (subtle fade-in) ── */
    if ('IntersectionObserver' in window) {
        var sections = document.querySelectorAll('.vf-section, .vf-band');
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        sections.forEach(function (section) {
            section.classList.add('vf-reveal');
            observer.observe(section);
        });
    }

})();
