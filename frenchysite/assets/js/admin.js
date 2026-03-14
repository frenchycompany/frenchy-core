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

    /* ── Save Settings (tous les paramètres dans un seul formulaire) ── */
    var formSettings = document.getElementById('form-settings');
    if (formSettings) {
        formSettings.addEventListener('submit', function (e) {
            e.preventDefault();
            var data = {};
            // Collecter inputs (text, color) et textareas avec un name
            formSettings.querySelectorAll('input[name], textarea[name]').forEach(function (el) {
                if (el.name) data[el.name] = el.value;
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

        /* Sync color picker ↔ hex input */
        formSettings.querySelectorAll('.adm-color-picker').forEach(function (picker) {
            picker.addEventListener('input', function () {
                var hex = formSettings.querySelector('.adm-color-hex[data-for="' + picker.id + '"]');
                if (hex) hex.value = picker.value;
                updatePreview();
            });
        });

        formSettings.querySelectorAll('.adm-color-hex').forEach(function (hex) {
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
            var beige    = get('color_beige');
            var offwhite = get('color_offwhite');
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

    /* ── Guide CRUD (dynamic guides + blocks) ── */
    (function () {
        var createForm = document.getElementById('create-guide-form');
        var btnCreate  = document.getElementById('btn-create-guide');
        var btnCancel  = document.getElementById('btn-cancel-new-guide');
        var btnSave    = document.getElementById('btn-save-new-guide');

        if (btnCreate) {
            btnCreate.addEventListener('click', function () {
                createForm.hidden = false;
                btnCreate.hidden = true;
                document.getElementById('new-guide-slug').focus();
            });
        }
        if (btnCancel) {
            btnCancel.addEventListener('click', function () {
                createForm.hidden = true;
                btnCreate.hidden = false;
            });
        }
        if (btnSave) {
            btnSave.addEventListener('click', function () {
                var slug     = document.getElementById('new-guide-slug').value.trim();
                var title    = document.getElementById('new-guide-title').value.trim();
                var subtitle = document.getElementById('new-guide-subtitle').value.trim();
                var icon     = document.getElementById('new-guide-icon').value.trim();

                if (!slug || !title) { toast('Slug et titre obligatoires', 'error'); return; }

                var fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'create_guide');
                fd.append('slug', slug);
                fd.append('title', title);
                fd.append('subtitle', subtitle);
                fd.append('icon_svg', icon);
                csrfAppend(fd);

                btnSave.disabled = true;
                fetch('admin.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        if (r.success) {
                            toast('Guide créé');
                            setTimeout(function () { window.location.reload(); }, 600);
                        } else {
                            toast(r.error || 'Erreur', 'error');
                            btnSave.disabled = false;
                        }
                    })
                    .catch(function () { toast('Erreur réseau', 'error'); btnSave.disabled = false; });
            });
        }

        /* Save guide metadata */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-save-guide');
            if (!btn) return;
            var item = btn.closest('.adm-guide-item');
            if (!item) return;

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'update_guide');
            fd.append('guide_id', item.dataset.id);
            fd.append('title', item.querySelector('.guide-title').value.trim());
            fd.append('subtitle', item.querySelector('.guide-subtitle').value.trim());
            fd.append('icon_svg', item.querySelector('.guide-icon').value.trim());
            fd.append('is_active', item.querySelector('.guide-active').checked ? '1' : '0');
            csrfAppend(fd);

            btn.disabled = true;
            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Guide enregistré');
                    else toast(r.error || 'Erreur', 'error');
                    btn.disabled = false;
                })
                .catch(function () { toast('Erreur réseau', 'error'); btn.disabled = false; });
        });

        /* Delete guide */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-guide');
            if (!btn) return;
            var item = btn.closest('.adm-guide-item');
            if (!item) return;

            if (!confirm('Supprimer ce guide et tous ses blocs ? Cette action est irréversible.')) return;

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'delete_guide');
            fd.append('guide_id', item.dataset.id);
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Guide supprimé');
                        item.remove();
                    } else {
                        toast(r.error || 'Erreur', 'error');
                    }
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });

        /* Toggle block edit panel */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-edit-block');
            if (!btn) return;
            var blockItem = btn.closest('.adm-block-item');
            if (!blockItem) return;
            var editPanel = blockItem.querySelector('.adm-block-edit');
            if (editPanel) editPanel.hidden = !editPanel.hidden;
        });

        /* Save block (create or update) */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-save-block');
            if (!btn) return;
            var blockItem = btn.closest('.adm-block-item');
            var guideItem = btn.closest('.adm-guide-item');
            if (!blockItem || !guideItem) return;

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'save_guide_block');
            fd.append('block_id', blockItem.dataset.blockId || '0');
            fd.append('guide_slug', guideItem.dataset.slug);
            fd.append('block_type', blockItem.querySelector('.block-type').value);
            fd.append('block_title', blockItem.querySelector('.block-title').value);
            fd.append('block_content', blockItem.querySelector('.block-content').value);
            csrfAppend(fd);

            btn.disabled = true;
            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Bloc enregistré');
                        if (!blockItem.dataset.blockId || blockItem.dataset.blockId === '0') {
                            blockItem.dataset.blockId = r.id;
                        }
                        // Update title preview
                        var preview = blockItem.querySelector('.adm-block-title-preview');
                        if (preview) preview.textContent = blockItem.querySelector('.block-title').value.substring(0, 60);
                        var badge = blockItem.querySelector('.adm-block-type-badge');
                        if (badge) badge.textContent = blockItem.querySelector('.block-type').value;
                    } else {
                        toast(r.error || 'Erreur', 'error');
                    }
                    btn.disabled = false;
                })
                .catch(function () { toast('Erreur réseau', 'error'); btn.disabled = false; });
        });

        /* Delete block */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete-block');
            if (!btn) return;
            var blockItem = btn.closest('.adm-block-item');
            if (!blockItem) return;
            var blockId = blockItem.dataset.blockId;

            if (!blockId || blockId === '0') {
                blockItem.remove();
                return;
            }

            if (!confirm('Supprimer ce bloc ?')) return;

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'delete_guide_block');
            fd.append('block_id', blockId);
            csrfAppend(fd);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Bloc supprimé');
                        blockItem.remove();
                    } else {
                        toast(r.error || 'Erreur', 'error');
                    }
                })
                .catch(function () { toast('Erreur réseau', 'error'); });
        });

        /* Add new block */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-add-block');
            if (!btn) return;
            var guideItem = btn.closest('.adm-guide-item');
            if (!guideItem) return;
            var list = guideItem.querySelector('.adm-blocks-list');
            if (!list) return;

            var html =
                '<div class="adm-block-item" data-block-id="0" draggable="true">' +
                    '<div class="adm-block-header">' +
                        '<span class="adm-block-drag" title="Glisser pour réordonner">&#x2630;</span>' +
                        '<span class="adm-block-type-badge">text</span>' +
                        '<span class="adm-block-title-preview">(nouveau bloc)</span>' +
                        '<button type="button" class="adm-btn adm-btn-sm adm-btn-outline btn-edit-block">Modifier</button>' +
                        '<button type="button" class="adm-btn adm-btn-sm adm-btn-danger btn-delete-block">&#x2715;</button>' +
                    '</div>' +
                    '<div class="adm-block-edit">' +
                        '<div class="adm-field">' +
                            '<label>Type</label>' +
                            '<select class="adm-input block-type">' +
                                '<option value="text" selected>Texte</option>' +
                                '<option value="highlight">Highlight (mise en avant)</option>' +
                                '<option value="steps">Étapes (numérotées)</option>' +
                                '<option value="list">Liste à puces</option>' +
                                '<option value="alert">Alerte (warning/info/danger)</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="adm-field">' +
                            '<label>Titre du bloc</label>' +
                            '<input type="text" class="adm-input block-title" value="">' +
                        '</div>' +
                        '<div class="adm-field">' +
                            '<label>Contenu <small style="color:var(--adm-grey)">— pour listes/étapes : 1 élément par ligne</small></label>' +
                            '<textarea class="adm-textarea block-content" rows="4"></textarea>' +
                        '</div>' +
                        '<div class="adm-form-actions" style="justify-content:flex-start">' +
                            '<button type="button" class="adm-btn adm-btn-primary adm-btn-sm btn-save-block">Enregistrer le bloc</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            list.insertAdjacentHTML('beforeend', html);
            var newBlock = list.lastElementChild;
            newBlock.querySelector('.block-title').focus();
        });

        /* Drag & drop reorder blocks */
        var dragSrc = null;

        document.addEventListener('dragstart', function (e) {
            var item = e.target.closest('.adm-block-item');
            if (!item) return;
            dragSrc = item;
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        document.addEventListener('dragover', function (e) {
            var item = e.target.closest('.adm-block-item');
            if (!item || item === dragSrc) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            item.classList.add('drag-over');
        });

        document.addEventListener('dragleave', function (e) {
            var item = e.target.closest('.adm-block-item');
            if (item) item.classList.remove('drag-over');
        });

        document.addEventListener('drop', function (e) {
            var target = e.target.closest('.adm-block-item');
            if (!target || !dragSrc || target === dragSrc) return;
            e.preventDefault();
            target.classList.remove('drag-over');

            var list = target.closest('.adm-blocks-list');
            if (!list) return;

            // Insert dragSrc before or after target based on position
            var items = Array.from(list.querySelectorAll('.adm-block-item'));
            var srcIdx = items.indexOf(dragSrc);
            var tgtIdx = items.indexOf(target);
            if (srcIdx < tgtIdx) {
                list.insertBefore(dragSrc, target.nextSibling);
            } else {
                list.insertBefore(dragSrc, target);
            }

            // Save new order
            var order = [];
            list.querySelectorAll('.adm-block-item').forEach(function (bi) {
                var bid = bi.dataset.blockId;
                if (bid && bid !== '0') order.push(bid);
            });

            if (order.length > 0) {
                var fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'reorder_blocks');
                fd.append('order', JSON.stringify(order));
                csrfAppend(fd);
                fetch('admin.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        if (r.success) toast('Ordre mis à jour');
                    })
                    .catch(function () {});
            }
        });

        document.addEventListener('dragend', function (e) {
            var item = e.target.closest('.adm-block-item');
            if (item) item.classList.remove('is-dragging');
            dragSrc = null;
        });
    })();

    /* ── Photo Upload (single-mode forms: hero, logo, experience) ── */
    document.querySelectorAll('.adm-upload-form[data-mode="single"]').forEach(function (form) {
        var fileInput = form.querySelector('input[type="file"]');
        var submitBtn = form.querySelector('button[type="submit"]');
        var preview   = form.querySelector('.adm-upload-preview');
        var prevImg   = preview ? preview.querySelector('img') : null;
        var prevName  = preview ? preview.querySelector('.adm-upload-filename') : null;

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
            csrfAppend(fd);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi...';

            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Photo enregistrée');
                        var targetId = form.dataset.target || ('photos-' + form.dataset.group);
                        var grid = document.getElementById(targetId);

                        if (grid) {
                            var empty = grid.querySelector('.adm-photo-empty');
                            if (empty) empty.remove();

                            var oldCard = grid.querySelector('.adm-photo-card');
                            if (oldCard) oldCard.remove();

                            var altText = form.querySelector('input[name="alt_text"]').value;
                            var card = document.createElement('div');
                            card.className = 'adm-photo-card' + (form.dataset.group === 'hero' ? ' adm-photo-card--large' : '');
                            card.dataset.id = r.id;
                            card.innerHTML =
                                '<img src="' + r.path + '" alt="' + (altText || '') + '">' +
                                '<button type="button" class="adm-photo-delete" data-id="' + r.id + '" title="Supprimer">' +
                                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                                '</button>';
                            grid.appendChild(card);
                        }

                        form.reset();
                        if (preview) preview.hidden = true;
                    } else {
                        toast(r.error || 'Erreur upload', 'error');
                    }
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Envoyer';
                })
                .catch(function () {
                    toast('Erreur réseau', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Envoyer';
                });
        });
    });

    /* ── Gallery: Multi-file upload with dropzone ── */
    (function () {
        var dropzone = document.getElementById('dropzone-galerie');
        var fileInput = document.getElementById('galerie-file-input');
        var grid = document.getElementById('photos-galerie');
        var queue = document.getElementById('upload-queue-galerie');
        if (!dropzone || !fileInput || !grid) return;

        function uploadFile(file) {
            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'upload_photo');
            fd.append('photo_group', 'galerie');
            fd.append('photo_key', '');
            fd.append('alt_text', '');
            fd.append('is_wide', '0');
            fd.append('photo', file);
            csrfAppend(fd);

            // Add queue item
            var queueItem = document.createElement('div');
            queueItem.className = 'adm-queue-item';
            queueItem.innerHTML =
                '<img src="' + URL.createObjectURL(file) + '" alt="">' +
                '<span class="adm-queue-name">' + file.name + '</span>' +
                '<span class="adm-queue-status">Envoi...</span>';
            queue.hidden = false;
            queue.appendChild(queueItem);

            return fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        queueItem.querySelector('.adm-queue-status').textContent = 'OK';
                        queueItem.classList.add('is-done');

                        var empty = grid.querySelector('.adm-photo-empty');
                        if (empty) empty.remove();

                        var card = document.createElement('div');
                        card.className = 'adm-photo-card';
                        card.dataset.id = r.id;
                        card.draggable = true;
                        card.innerHTML =
                            '<img src="' + r.path + '" alt="">' +
                            '<div class="adm-photo-info"><span class="adm-photo-name">Photo</span></div>' +
                            '<div class="adm-photo-actions">' +
                                '<button type="button" class="adm-photo-edit" data-id="' + r.id + '" data-alt="" data-wide="0" title="Modifier">' +
                                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                                '</button>' +
                                '<button type="button" class="adm-photo-delete" data-id="' + r.id + '" title="Supprimer">' +
                                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                                '</button>' +
                            '</div>' +
                            '<span class="adm-photo-drag" title="Glisser pour réordonner">&#x2630;</span>';
                        grid.appendChild(card);
                    } else {
                        queueItem.querySelector('.adm-queue-status').textContent = r.error || 'Erreur';
                        queueItem.classList.add('is-error');
                    }
                })
                .catch(function () {
                    queueItem.querySelector('.adm-queue-status').textContent = 'Erreur réseau';
                    queueItem.classList.add('is-error');
                });
        }

        function handleFiles(files) {
            var uploads = [];
            for (var i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    uploads.push(uploadFile(files[i]));
                }
            }
            if (uploads.length === 0) {
                toast('Aucune image valide sélectionnée', 'error');
                return;
            }
            Promise.all(uploads).then(function () {
                toast(uploads.length + ' photo(s) ajoutée(s)');
                setTimeout(function () {
                    queue.innerHTML = '';
                    queue.hidden = true;
                }, 2000);
            });
        }

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) handleFiles(fileInput.files);
            fileInput.value = '';
        });

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            dropzone.classList.add('is-dragover');
        });
        dropzone.addEventListener('dragleave', function (e) {
            if (!dropzone.contains(e.relatedTarget)) {
                dropzone.classList.remove('is-dragover');
            }
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
            if (e.dataTransfer.files.length > 0) handleFiles(e.dataTransfer.files);
        });
    })();

    /* ── Gallery: Drag & drop reorder photos ── */
    (function () {
        var grid = document.getElementById('photos-galerie');
        if (!grid) return;
        var dragSrc = null;

        grid.addEventListener('dragstart', function (e) {
            var card = e.target.closest('.adm-photo-card');
            if (!card) return;
            dragSrc = card;
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.id);
        });

        grid.addEventListener('dragover', function (e) {
            var card = e.target.closest('.adm-photo-card');
            if (!card || card === dragSrc) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            card.classList.add('drag-over');
        });

        grid.addEventListener('dragleave', function (e) {
            var card = e.target.closest('.adm-photo-card');
            if (card) card.classList.remove('drag-over');
        });

        grid.addEventListener('drop', function (e) {
            var target = e.target.closest('.adm-photo-card');
            if (!target || !dragSrc || target === dragSrc) return;
            e.preventDefault();
            target.classList.remove('drag-over');

            var cards = Array.from(grid.querySelectorAll('.adm-photo-card'));
            var srcIdx = cards.indexOf(dragSrc);
            var tgtIdx = cards.indexOf(target);
            if (srcIdx < tgtIdx) {
                grid.insertBefore(dragSrc, target.nextSibling);
            } else {
                grid.insertBefore(dragSrc, target);
            }

            // Save new order
            var order = [];
            grid.querySelectorAll('.adm-photo-card').forEach(function (c) {
                if (c.dataset.id) order.push(c.dataset.id);
            });
            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'reorder_photos');
            fd.append('order', JSON.stringify(order));
            csrfAppend(fd);
            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) toast('Ordre mis à jour');
                })
                .catch(function () {});
        });

        grid.addEventListener('dragend', function (e) {
            var card = e.target.closest('.adm-photo-card');
            if (card) card.classList.remove('is-dragging');
            dragSrc = null;
        });
    })();

    /* ── Edit photo modal ── */
    (function () {
        var modal     = document.getElementById('modal-edit-photo');
        var modalImg  = document.getElementById('modal-edit-img');
        var modalAlt  = document.getElementById('modal-edit-alt');
        var modalWide = document.getElementById('modal-edit-wide');
        var modalId   = document.getElementById('modal-edit-id');
        var btnSave   = document.getElementById('modal-edit-save');
        var btnCancel = document.getElementById('modal-edit-cancel');
        var backdrop  = modal ? modal.querySelector('.adm-modal-backdrop') : null;
        if (!modal) return;

        function openModal(btn) {
            var card = btn.closest('.adm-photo-card');
            modalId.value = btn.dataset.id;
            modalAlt.value = btn.dataset.alt || '';
            modalWide.checked = btn.dataset.wide === '1';
            modalImg.src = card ? card.querySelector('img').src : '';
            modal.hidden = false;
            modalAlt.focus();
        }

        function closeModal() {
            modal.hidden = true;
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.adm-photo-edit');
            if (btn) openModal(btn);
        });

        btnCancel.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);

        btnSave.addEventListener('click', function () {
            var id = modalId.value;
            var alt = modalAlt.value;
            var wide = modalWide.checked ? '1' : '0';

            var fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'update_photo');
            fd.append('photo_id', id);
            fd.append('alt_text', alt);
            fd.append('is_wide', wide);
            csrfAppend(fd);

            btnSave.disabled = true;
            fetch('admin.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        toast('Photo mise à jour');
                        // Update card in DOM
                        var card = document.querySelector('.adm-photo-card[data-id="' + id + '"]');
                        if (card) {
                            var nameEl = card.querySelector('.adm-photo-name');
                            if (nameEl) nameEl.textContent = alt || 'Photo';
                            var editBtn = card.querySelector('.adm-photo-edit');
                            if (editBtn) {
                                editBtn.dataset.alt = alt;
                                editBtn.dataset.wide = wide;
                            }
                            // Update badge
                            var badge = card.querySelector('.adm-badge');
                            if (wide === '1' && !badge) {
                                var info = card.querySelector('.adm-photo-info');
                                if (info) {
                                    var b = document.createElement('span');
                                    b.className = 'adm-badge';
                                    b.textContent = 'Grande';
                                    info.appendChild(b);
                                }
                            } else if (wide === '0' && badge) {
                                badge.remove();
                            }
                        }
                        closeModal();
                    } else {
                        toast(r.error || 'Erreur', 'error');
                    }
                    btnSave.disabled = false;
                })
                .catch(function () {
                    toast('Erreur réseau', 'error');
                    btnSave.disabled = false;
                });
        });
    })();

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
