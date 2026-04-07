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

    /* ── Gallery Lightbox ── */
    (function () {
        var lightbox  = document.getElementById('vf-lightbox');
        var lbImg     = document.getElementById('vf-lightbox-img');
        var lbCaption = document.getElementById('vf-lightbox-caption');
        var lbCounter = document.getElementById('vf-lightbox-counter');
        var gallery   = document.getElementById('vf-gallery');
        if (!lightbox || !gallery) return;

        var items = [];
        var current = 0;

        gallery.querySelectorAll('.vf-gallery-item').forEach(function (fig) {
            var img = fig.querySelector('img');
            if (!img) return;
            items.push({
                src: img.src,
                alt: img.alt || '',
            });
        });

        if (items.length === 0) return;

        function show(index) {
            if (index < 0) index = items.length - 1;
            if (index >= items.length) index = 0;
            current = index;
            lbImg.src = items[index].src;
            lbImg.alt = items[index].alt;
            lbCaption.textContent = items[index].alt;
            lbCaption.hidden = !items[index].alt;
            lbCounter.textContent = (index + 1) + ' / ' + items.length;
        }

        function open(index) {
            show(index);
            lightbox.hidden = false;
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function close() {
            lightbox.hidden = true;
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        gallery.addEventListener('click', function (e) {
            var fig = e.target.closest('.vf-gallery-item');
            if (!fig) return;
            var idx = parseInt(fig.dataset.index, 10);
            if (!isNaN(idx)) open(idx);
        });

        lightbox.querySelector('.vf-lightbox-close').addEventListener('click', close);
        lightbox.querySelector('.vf-lightbox-backdrop').addEventListener('click', close);
        lightbox.querySelector('.vf-lightbox-prev').addEventListener('click', function () { show(current - 1); });
        lightbox.querySelector('.vf-lightbox-next').addEventListener('click', function () { show(current + 1); });

        document.addEventListener('keydown', function (e) {
            if (lightbox.hidden) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') show(current - 1);
            if (e.key === 'ArrowRight') show(current + 1);
        });

        // Swipe support
        var touchStartX = 0;
        lightbox.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        lightbox.addEventListener('touchend', function (e) {
            var diff = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) show(current - 1);
                else show(current + 1);
            }
        }, { passive: true });
    })();

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

    /* ── Site Search ── */
    (function () {
        var overlay  = document.getElementById('vf-search-overlay');
        var input    = document.getElementById('vf-search-input');
        var results  = document.getElementById('vf-search-results');
        var btnOpen  = document.getElementById('vf-search-toggle');
        var btnClose = document.getElementById('vf-search-close');
        var backdrop = document.getElementById('vf-search-backdrop');

        if (!overlay || !input || !results) return;

        // Build search index from page sections
        var index = [];
        document.querySelectorAll('main .vf-section, main .vf-hero, main .vf-band').forEach(function (el) {
            var id = el.id || el.closest('[id]')?.id || '';
            // Find the section label from nav or heading
            var heading = el.querySelector('h2, h1, .vf-section-title');
            var label = heading ? heading.textContent.trim() : (id || 'Section');
            // Collect all visible text
            var text = el.innerText || el.textContent || '';
            if (text.trim().length > 0) {
                index.push({
                    id: id,
                    label: label,
                    text: text.trim(),
                    el: el
                });
            }
        });

        // Also index footer content
        var footer = document.querySelector('.vf-footer');
        if (footer) {
            index.push({
                id: 'footer',
                label: 'Pied de page',
                text: (footer.innerText || footer.textContent || '').trim(),
                el: footer
            });
        }

        function open() {
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            input.value = '';
            results.innerHTML = '<p class="vf-search-hint">Tapez pour rechercher dans toutes les sections du site.</p>' +
                '<p class="vf-search-shortcut"><kbd>Esc</kbd> pour fermer</p>';
            setTimeout(function () { input.focus(); }, 50);
        }

        function close() {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function highlightMatch(text, query) {
            var lower = text.toLowerCase();
            var qi = lower.indexOf(query.toLowerCase());
            if (qi === -1) return escapeHtml(text);
            var before = text.substring(0, qi);
            var match  = text.substring(qi, qi + query.length);
            var after  = text.substring(qi + query.length);
            return escapeHtml(before) + '<mark>' + escapeHtml(match) + '</mark>' + escapeHtml(after);
        }

        function getExcerpt(fullText, query, contextLen) {
            contextLen = contextLen || 80;
            var lower = fullText.toLowerCase();
            var qi = lower.indexOf(query.toLowerCase());
            if (qi === -1) return '';
            var start = Math.max(0, qi - contextLen);
            var end   = Math.min(fullText.length, qi + query.length + contextLen);
            var excerpt = (start > 0 ? '...' : '') +
                fullText.substring(start, end).replace(/\s+/g, ' ') +
                (end < fullText.length ? '...' : '');
            return excerpt;
        }

        function search(query) {
            query = query.trim();
            if (query.length < 2) {
                results.innerHTML = '<p class="vf-search-hint">Tapez au moins 2 caractères.</p>' +
                    '<p class="vf-search-shortcut"><kbd>Esc</kbd> pour fermer</p>';
                return;
            }

            var matches = [];
            var qLower = query.toLowerCase();

            for (var i = 0; i < index.length; i++) {
                var item = index[i];
                if (item.text.toLowerCase().indexOf(qLower) !== -1) {
                    matches.push(item);
                }
            }

            if (matches.length === 0) {
                results.innerHTML = '<p class="vf-search-no-results">Aucun résultat pour « ' + escapeHtml(query) + ' »</p>';
                return;
            }

            var html = '';
            for (var j = 0; j < matches.length; j++) {
                var m = matches[j];
                var excerpt = getExcerpt(m.text, query);
                var href = m.id ? '#' + m.id : '#';
                html += '<a class="vf-search-result" href="' + href + '" data-idx="' + j + '">' +
                    '<div class="vf-search-result-section">' + escapeHtml(m.label) + '</div>' +
                    '<div class="vf-search-result-excerpt">' + highlightMatch(excerpt, query) + '</div>' +
                    '</a>';
            }
            results.innerHTML = html;
        }

        // Events
        if (btnOpen) btnOpen.addEventListener('click', open);
        if (btnClose) btnClose.addEventListener('click', close);
        if (backdrop) backdrop.addEventListener('click', close);

        input.addEventListener('input', function () {
            search(input.value);
        });

        // Click on result → scroll to section & close
        results.addEventListener('click', function (e) {
            var link = e.target.closest('.vf-search-result');
            if (!link) return;
            close();
            // Let the default anchor navigation happen
        });

        // Keyboard: Escape to close, Ctrl+K / Cmd+K to open
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (overlay.hidden) open(); else close();
            }
            if (e.key === 'Escape' && !overlay.hidden) {
                close();
            }
        });

        // Arrow keys navigation in results
        input.addEventListener('keydown', function (e) {
            var items = results.querySelectorAll('.vf-search-result');
            if (items.length === 0) return;
            var active = results.querySelector('.vf-search-result.is-active');
            var idx = -1;
            if (active) {
                idx = parseInt(active.dataset.idx, 10);
                active.classList.remove('is-active');
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = (idx + 1) % items.length;
                items[idx].classList.add('is-active');
                items[idx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = idx <= 0 ? items.length - 1 : idx - 1;
                items[idx].classList.add('is-active');
                items[idx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (active) {
                    e.preventDefault();
                    active.click();
                }
            }
        });
    })();

})();
