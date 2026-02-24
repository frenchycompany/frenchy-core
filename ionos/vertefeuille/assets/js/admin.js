/**
 * Admin JS — Tabs, AJAX save, photo upload, color preview, toast.
 */
(function () {
    'use strict';

    /* ── CSRF helper ── */
    function csrfAppend(fd) {
        fd.append('_csrf', window.VF_CSRF || '');
        return fd;
    }

    /* ── Toast ── */
    function toast(message, type) {
        var el = document.getElementById('adm-toast');
        if (!el) return;
        el.textContent = message;
        el.className = 'adm-toast is-visible is-' + (type || 'success');
        clearTimeout(el._timer);
        el._timer = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 3000);
    }

    /* ── Tabs ── */
    var tabs = document.querySelectorAll('.adm-tab');
    var panels = document.querySelectorAll('.adm-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            panels.forEach(function (p) { p.classList.remove('is-active'); });
            tab.classList.add('is-active');
            var target = document.getElementById('panel-' + tab.dataset.tab);
            if (target) target.classList.add('is-active');
        });
    });

    /* ── Save Modules (sections & guides toggles) ── */
    var formModules = document.getElementById('form-modules');
    if (formModules) {
        formModules.addEventListener('submit', function (e) {
            e.preventDefault();
            var sections = [];
            formModules.querySelectorAll('input[name="sections[]"]:checked').forEach(function (cb) {
                sections.push(cb.value);
            });
            var guides = [];
            formModules.querySelectorAll('input[name="guides[]"]:checked').forEach(function (cb) {
                guides.push(cb.value);
            });

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_modules');
            fd.append('sections', JSON.stringify(sections));
            fd.append('guides', JSON.stringify(guides));
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Modules enregistrés');
                        setTimeout(function () { window.location.reload(); }, 800);
                    } else {
                        toast(r.error || 'Erreur', 'error');
                    }
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });
    }

    /* ── Save Settings (identity + integrations) ── */
    var formSettings = document.getElementById('form-settings');
    if (formSettings) {
        formSettings.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = {};
            formSettings.querySelectorAll('input[name]').forEach(function (input) {
                data[input.name] = input.value;
            });

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_settings');
            fd.append('data', JSON.stringify(data));
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Paramètres enregistrés');
                    else toast(r.error || 'Erreur', 'error');
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });
    }

    /* ── Save Colors + Typography ── */
    var formColors = document.getElementById('form-colors');
    if (formColors) {
        formColors.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = {};
            formColors.querySelectorAll('input[name]').forEach(function (input) {
                if (input.type === 'color' || input.type === 'text') {
                    // Only get named inputs (not .adm-color-hex which has no name)
                    if (input.name) data[input.name] = input.value;
                }
            });

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_settings');
            fd.append('data', JSON.stringify(data));
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Couleurs & typographie enregistrées');
                    else toast(r.error || 'Erreur', 'error');
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });

        /* Sync color picker ↔ hex input */
        formColors.querySelectorAll('.adm-color-picker').forEach(function (picker) {
            picker.addEventListener('input', function () {
                var hex = formColors.querySelector('.adm-color-hex[data-for="' + picker.id + '"]');
                if (hex) hex.value = picker.value;
                updatePreview();
            });
        });

        formColors.querySelectorAll('.adm-color-hex').forEach(function (hex) {
            hex.addEventListener('input', function () {
                var picker = document.getElementById(hex.dataset.for);
                if (picker && /^#[0-9a-fA-F]{6}$/.test(hex.value)) {
                    picker.value = hex.value;
                    updatePreview();
                }
            });
        });

        /* Preview update */
        function updatePreview() {
            var get = function (key) {
                var el = document.getElementById('s-' + key);
                return el ? el.value : '';
            };

            var green    = get('color_green');
            var greenDk  = get('color_green_dk');
            var beige    = get('color_beige');
            var offwhite = get('color_offwhite');
            var brown    = get('color_brown');
            var dark     = get('color_dark');

            var bar     = document.getElementById('prev-bar');
            var barText = document.getElementById('prev-bar-text');
            var card    = document.getElementById('prev-card');
            var heading = document.getElementById('prev-heading');
            var body    = document.getElementById('prev-body');
            var btn     = document.getElementById('prev-btn');
            var btnO    = document.getElementById('prev-btn-outline');

            if (bar)     bar.style.background = green;
            if (barText) barText.style.color = offwhite;
            if (card)    card.style.background = beige;
            if (heading) heading.style.color = green;
            if (body)    { body.style.color = dark; }
            if (btn)     { btn.style.background = green; btn.style.color = offwhite; btn.style.borderColor = green; }
            if (btnO)    { btnO.style.background = 'transparent'; btnO.style.color = green; btnO.style.borderColor = green; }
        }

        updatePreview();
    }

    /* ── Save Texts ── */
    var formTexts = document.getElementById('form-texts');
    if (formTexts) {
        formTexts.addEventListener('submit', function (e) {
            e.preventDefault();
            var items = [];
            formTexts.querySelectorAll('input[data-section], textarea[data-section]').forEach(function (el) {
                items.push({
                    section: el.dataset.section,
                    field: el.dataset.field,
                    value: el.value
                });
            });

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_texts');
            fd.append('data', JSON.stringify(items));
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Textes enregistrés');
                    else toast(r.error || 'Erreur', 'error');
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });
    }

    /* ── Save Guides (same as texts — uses save_texts action) ── */
    var formGuides = document.getElementById('form-guides');
    if (formGuides) {
        formGuides.addEventListener('submit', function (e) {
            e.preventDefault();
            var items = [];
            formGuides.querySelectorAll('input[data-section], textarea[data-section]').forEach(function (el) {
                items.push({
                    section: el.dataset.section,
                    field: el.dataset.field,
                    value: el.value
                });
            });

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_texts');
            fd.append('data', JSON.stringify(items));
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Guides enregistrés');
                    else toast(r.error || 'Erreur', 'error');
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });
    }

    /* ── Photo Upload ── */
    document.querySelectorAll('.adm-upload-form').forEach(function (form) {
        var fileInput = form.querySelector('input[type="file"]');
        var submitBtn = form.querySelector('button[type="submit"]');
        var preview   = form.querySelector('.adm-upload-preview');
        var prevImg   = preview ? preview.querySelector('img') : null;
        var prevName  = preview ? preview.querySelector('.adm-upload-filename') : null;
        var mode      = form.dataset.mode || 'multi'; // 'single' or 'multi'
        var btnLabel  = mode === 'single' ? 'Envoyer' : 'Ajouter';

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                submitBtn.disabled = false;
                if (preview && prevImg) {
                    preview.hidden = false;
                    prevImg.src = URL.createObjectURL(fileInput.files[0]);
                    if (prevName) prevName.textContent = fileInput.files[0].name;
                }
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!fileInput.files.length) return;

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'upload_photo');
            fd.append('photo_group', form.dataset.group);
            fd.append('photo_key', form.querySelector('input[name="photo_key"]').value);
            fd.append('alt_text', form.querySelector('input[name="alt_text"]').value);
            fd.append('photo', fileInput.files[0]);

            var wideCheck = form.querySelector('input[name="is_wide"]');
            fd.append('is_wide', wideCheck && wideCheck.checked ? '1' : '0');
            csrfAppend(fd);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Photo enregistrée');

                        // Find the target grid/container
                        var targetId = form.dataset.target || ('photos-' + form.dataset.group);
                        var grid = document.getElementById(targetId);

                        if (grid) {
                            // Remove "empty" message
                            var empty = grid.querySelector('.adm-photo-empty');
                            if (empty) empty.remove();

                            if (mode === 'single') {
                                // Replace: remove existing card, add new one
                                var oldCard = grid.querySelector('.adm-photo-card');
                                if (oldCard) oldCard.remove();
                            }

                            var altText = form.querySelector('input[name="alt_text"]').value;
                            var card = document.createElement('div');
                            card.className = 'adm-photo-card' + (mode === 'single' && form.dataset.group === 'hero' ? ' adm-photo-card--large' : '');
                            card.dataset.id = r.id;
                            card.innerHTML =
                                '<img src="' + r.path + '" alt="' + (altText || '') + '">' +
                                (mode === 'multi' ? '<div class="adm-photo-info"><span class="adm-photo-name">' + (altText || 'Photo') + '</span></div>' : '') +
                                '<button type="button" class="adm-photo-delete" data-id="' + r.id + '" title="Supprimer">' +
                                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                                '</button>';
                            grid.appendChild(card);
                        }

                        // Reset form
                        form.reset();
                        if (preview) preview.hidden = true;
                    } else {
                        toast(r.error || 'Erreur upload', 'error');
                    }
                    submitBtn.disabled = true;
                    submitBtn.textContent = btnLabel;
                })
                .catch(function () {
                    toast('Erreur réseau', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = btnLabel;
                });
        });
    });

    /* ── Delete Photo (event delegation) ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.adm-photo-delete');
        if (!btn) return;

        if (!confirm('Supprimer cette photo ?')) return;

        var id = btn.dataset.id;
        var fd = new FormData();
        fd.append('ajax', '1');
        fd.append('action', 'delete_photo');
        fd.append('photo_id', id);
        csrfAppend(fd);

        fetch('admin.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    toast('Photo supprimée');
                    var card = btn.closest('.adm-photo-card');
                    if (card) card.remove();
                } else {
                    toast(r.error || 'Erreur', 'error');
                }
            })
            .catch(function () { toast('Erreur réseau', 'error'); });
    });

})();
