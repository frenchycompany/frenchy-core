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
