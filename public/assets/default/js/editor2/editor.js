(function (window, document) {
    'use strict';

    const API = window.FireballEditor2;
    if (!API || !API.importer || !API.sanitizer || !API.EditorHistory) {
        return;
    }

    const importer = API.importer;
    const sanitizer = API.sanitizer;
    const INTERNAL_MIME = API.internalMime;
    const TEXT_TYPES = new Set(['text', 'heading', 'quote']);
    const FILE_SELECTION_KEY = 'fireball:file:selected';

    function escapeAttr(value) {
        return sanitizer.escapeHtml(String(value == null ? '' : value)).replace(/`/g, '&#96;');
    }

    function stripHtml(value) {
        const node = document.createElement('div');
        node.innerHTML = String(value || '');
        return String(node.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function iconClass(value) {
        const icon = String(value || '');
        return /^ci-[a-z0-9-]+$/i.test(icon) ? icon : 'ci-square';
    }

    const socialIconSupport = new Map();

    function hasThemeSocialIcon(value) {
        const icon = String(value || '').trim();
        if (!/^ci-[a-z0-9-]+$/i.test(icon)) {
            return false;
        }
        if (socialIconSupport.has(icon)) {
            return socialIconSupport.get(icon);
        }
        const probe = document.createElement('i');
        probe.className = icon;
        probe.setAttribute('aria-hidden', 'true');
        probe.style.cssText = 'position:absolute;left:-9999px;visibility:hidden';
        (document.body || document.documentElement).appendChild(probe);
        const content = window.getComputedStyle(probe, '::before').content;
        probe.remove();
        const supported = Boolean(content && content !== 'none' && content !== 'normal' && content !== '""');
        socialIconSupport.set(icon, supported);
        return supported;
    }

    function socialIconMarkup(value) {
        const icon = String(value || '').trim();
        return hasThemeSocialIcon(icon)
            ? '<i class="fb-social-buttons__icon ' + escapeAttr(icon) + '" aria-hidden="true"></i>'
            : '<span class="fb-social-buttons__icon fb-social-buttons__icon--svg" aria-hidden="true"></span>';
    }

    function socialIconValue(value) {
        const icon = String(value || '').trim();
        return hasThemeSocialIcon(icon) ? icon : 'fb-social-vector';
    }

    function debounce(callback, delay) {
        let timer = 0;
        return function () {
            const args = arguments;
            const context = this;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                callback.apply(context, args);
            }, delay);
        };
    }

    function setPath(target, path, value) {
        const parts = String(path || '').split('.').filter(Boolean);
        let current = target;
        parts.forEach(function (part, index) {
            if (index === parts.length - 1) {
                current[part] = value;
                return;
            }
            if (!current[part] || typeof current[part] !== 'object') {
                current[part] = {};
            }
            current = current[part];
        });
    }

    function getPath(target, path) {
        return String(path || '').split('.').filter(Boolean).reduce(function (current, part) {
            return current && typeof current === 'object' ? current[part] : undefined;
        }, target);
    }

    class FireballEditor {
        constructor(root) {
            this.root = root;
            this.textarea = root.previousElementSibling && root.previousElementSibling.matches('[data-block-editor-source]')
                ? root.previousElementSibling
                : root.parentElement.querySelector('[data-block-editor-source]');
            this.form = this.textarea ? this.textarea.form : null;
            this.workspace = root.closest('[data-editor-workspace]');
            this.config = this.readConfig();
            this.labels = this.config.labels || {};
            this.registerConfiguredTypes();
            this.state = this.readInitialState();
            if (!this.state.blocks.length) {
                this.state.blocks.push(importer.normalizeBlock({
                    type: 'text',
                    data: { html: '' }
                }, API));
            }
            this.mode = 'document';
            try {
                const storedMode = window.localStorage.getItem('fireball:editor2:mode');
                if (storedMode === 'document' || storedMode === 'structure') {
                    this.mode = storedMode;
                }
            } catch (error) {
            }
            this.activeId = this.state.blocks[0].id;
            this.selectedIds = new Set([this.activeId]);
            this.selectionAnchorId = this.activeId;
            this.lastSelectAllAt = 0;
            this.dirty = false;
            this.destroyed = false;
            this.savedRange = null;
            this.draggedId = '';
            this.pointerDrag = null;
            this.insertionAfter = '';
            this.slashBlockId = '';
            this.slashIndex = -1;
            this.searchMatches = [];
            this.searchIndex = -1;
            this.outlineQuery = '';
            this.lastSavedAt = '';
            this.autosaveTimer = 0;
            this.localSaveTimer = 0;
            this.abortController = null;
            this.lastSerialized = '';
            this.savedSerialized = '';
            this.filePickerTarget = null;
            this.contextMenuAnchor = null;
            this.pendingDeleteIds = [];
            this.floatingUiFrame = 0;
            this.history = new API.EditorHistory(this.historyState(), { limit: 120, coalesceMs: 700 });
            this.ui = this.collectUi();
            this.refreshOutlineSoon = debounce(this.refreshOutline.bind(this), 160);
            this.refreshStatusSoon = debounce(this.refreshStatus.bind(this), 120);
            this.refreshSplitPreviewSoon = debounce(this.refreshSplitPreview.bind(this), 250);
            this.bind();
            this.renderAll();
            this.syncTextarea(false);
            this.savedSerialized = this.lastSerialized;
            this.restoreLocalDraft();
            API.attachEditor(this);
        }

        label(name, fallback) {
            const value = String(this.labels[name] || '');
            return value && value !== name ? value : fallback;
        }

        readConfig() {
            try {
                return JSON.parse(this.root.getAttribute('data-post-editor-config') || '{}');
            } catch (error) {
                return {};
            }
        }

        registerConfiguredTypes() {
            (this.config.blockTypes || []).forEach(function (definition) {
                if (definition && definition.machine_name) {
                    API.registerBlockType(definition.machine_name, definition);
                }
            });
        }

        readInitialState() {
            const content = String(this.textarea ? this.textarea.value : '').trim();
            const snapshot = importer.extractSnapshot(content, API);
            if (snapshot) {
                return snapshot;
            }
            if (content.charAt(0) === '{') {
                try {
                    const decoded = JSON.parse(content);
                    if (decoded && Array.isArray(decoded.blocks)) {
                        return importer.normalizeState(decoded, API);
                    }
                } catch (error) {
                }
            }
            return importer.normalizeState({
                blocks: importer.htmlToBlocks(content, API)
            }, API);
        }

        collectUi() {
            const workspace = this.workspace || document;
            return {
                canvas: this.root.querySelector('[data-editor-canvas]'),
                selectionToolbar: this.root.querySelector('[data-editor-selection-toolbar]'),
                slashMenu: this.root.querySelector('[data-editor-slash-menu]'),
                slashSearch: this.root.querySelector('[data-editor-slash-search]'),
                slashList: this.root.querySelector('[data-editor-slash-list]'),
                contextMenu: this.root.querySelector('[data-editor-context-menu]'),
                commandDialog: this.root.querySelector('[data-editor-command-dialog]'),
                commandSearch: this.root.querySelector('[data-editor-command-search]'),
                commandList: this.root.querySelector('[data-editor-command-list]'),
                previewDialog: this.root.querySelector('[data-editor-preview-dialog]'),
                previewFrame: this.root.querySelector('[data-editor-preview-frame]'),
                previewStage: this.root.querySelector('[data-preview-stage]'),
                splitPreview: workspace.querySelector('[data-editor-split-preview]'),
                splitPreviewFrame: workspace.querySelector('[data-editor-split-preview-frame]'),
                recoveryDialog: this.root.querySelector('[data-editor-recovery-dialog]'),
                deleteDialog: this.root.querySelector('[data-editor-delete-dialog]'),
                searchPanel: this.root.querySelector('[data-editor-search-panel]'),
                searchInput: this.root.querySelector('[data-editor-search-input]'),
                replaceInput: this.root.querySelector('[data-editor-replace-input]'),
                searchCase: this.root.querySelector('[data-editor-search-case]'),
                outline: workspace.querySelector('[data-editor-outline]'),
                outlineFilter: workspace.querySelector('[data-editor-outline-filter]'),
                inspector: workspace.querySelector('[data-editor-inspector]'),
                saveState: workspace.querySelector('[data-editor-save-state]'),
                saveStateLabel: workspace.querySelector('[data-editor-save-state-label]'),
                undo: workspace.querySelector('[data-editor-undo]'),
                redo: workspace.querySelector('[data-editor-redo]'),
                statusBlocks: workspace.querySelector('[data-editor-status-blocks]'),
                statusWords: workspace.querySelector('[data-editor-status-words]'),
                statusCharacters: workspace.querySelector('[data-editor-status-characters]'),
                statusReading: workspace.querySelector('[data-editor-status-reading]'),
                statusSavedAt: workspace.querySelector('[data-editor-status-saved-at]'),
                statusCurrent: workspace.querySelector('[data-editor-status-current]'),
                publishedInput: workspace.querySelector('[data-editor-published-input]'),
                entityIdInput: workspace.querySelector('[data-editor-entity-id]')
            };
        }

        historyState() {
            return { version: 2, blocks: API.clone(this.state.blocks) };
        }

        activeBlock() {
            return this.state.blocks.find(function (block) {
                return block.id === this.activeId;
            }, this) || null;
        }

        blockIndex(id) {
            return this.state.blocks.findIndex(function (block) {
                return block.id === id;
            });
        }

        refreshRegistry() {
            this.renderAll({ preserveFocus: true });
        }

        bind() {
            this.onRootClick = this.handleRootClick.bind(this);
            this.onRootInput = this.handleRootInput.bind(this);
            this.onRootChange = this.handleRootChange.bind(this);
            this.onRootKeydown = this.handleRootKeydown.bind(this);
            this.onRootPaste = this.handlePaste.bind(this);
            this.onRootCopy = this.handleCopy.bind(this);
            this.onRootCut = this.handleCut.bind(this);
            this.onRootDragStart = this.handleDragStart.bind(this);
            this.onRootDragOver = this.handleDragOver.bind(this);
            this.onRootDrop = this.handleDrop.bind(this);
            this.onRootDragEnd = this.handleDragEnd.bind(this);
            this.onRootPointerDown = this.handlePointerDown.bind(this);
            this.onSelectionChange = debounce(this.handleSelectionChange.bind(this), 30);
            this.onWorkspaceClick = this.handleWorkspaceClick.bind(this);
            this.onWorkspaceInput = this.handleWorkspaceInput.bind(this);
            this.onBeforeUnload = this.handleBeforeUnload.bind(this);
            this.onMessage = this.handleFileSelectionMessage.bind(this);
            this.onStorage = this.handleStorage.bind(this);
            this.onGlobalKeydown = this.handleGlobalKeydown.bind(this);
            this.onDocumentClick = this.handleDocumentClick.bind(this);
            this.onFloatingUiViewportChange = this.handleFloatingUiViewportChange.bind(this);
            this.onFormSubmit = this.handleSubmit.bind(this);
            this.onFormInvalid = this.handleFormInvalid.bind(this);
            this.onExternalSerialize = this.handleExternalSerialize.bind(this);

            this.root.addEventListener('click', this.onRootClick);
            this.root.addEventListener('input', this.onRootInput);
            this.root.addEventListener('change', this.onRootChange);
            this.root.addEventListener('keydown', this.onRootKeydown);
            this.root.addEventListener('paste', this.onRootPaste);
            this.root.addEventListener('copy', this.onRootCopy);
            this.root.addEventListener('cut', this.onRootCut);
            this.root.addEventListener('dragstart', this.onRootDragStart);
            this.root.addEventListener('dragover', this.onRootDragOver);
            this.root.addEventListener('drop', this.onRootDrop);
            this.root.addEventListener('dragend', this.onRootDragEnd);
            this.root.addEventListener('pointerdown', this.onRootPointerDown);
            document.addEventListener('selectionchange', this.onSelectionChange);
            document.addEventListener('keydown', this.onGlobalKeydown, true);
            document.addEventListener('click', this.onDocumentClick);
            document.addEventListener('scroll', this.onFloatingUiViewportChange, true);
            window.addEventListener('resize', this.onFloatingUiViewportChange);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', this.onFloatingUiViewportChange);
                window.visualViewport.addEventListener('scroll', this.onFloatingUiViewportChange);
            }

            if (this.workspace) {
                this.workspace.addEventListener('click', this.onWorkspaceClick);
                this.workspace.addEventListener('input', this.onWorkspaceInput);
                this.workspace.addEventListener('change', this.onWorkspaceInput);
            }
            if (this.form) {
                this.form.addEventListener('submit', this.onFormSubmit);
                this.form.addEventListener('invalid', this.onFormInvalid, true);
                this.form.addEventListener('fireball:post-editor-serialize', this.onExternalSerialize);
            }
            window.addEventListener('beforeunload', this.onBeforeUnload);
            window.addEventListener('message', this.onMessage);
            window.addEventListener('storage', this.onStorage);
        }

        renderAll(options) {
            const settings = options || {};
            const focusId = settings.preserveFocus ? this.activeId : '';
            this.root.setAttribute('data-editor-mode', this.mode);
            if (this.workspace) {
                this.workspace.setAttribute('data-editor-mode', this.mode);
                this.workspace.querySelectorAll('button[data-editor-mode]').forEach(function (button) {
                    button.classList.toggle('is-active', button.getAttribute('data-editor-mode') === this.mode);
                }, this);
            }
            this.renderCanvas();
            this.refreshOutline();
            this.renderInspector();
            this.refreshStatus();
            this.updateHistoryControls();
            if (focusId) {
                this.focusBlock(focusId, settings.caret || 'end');
            }
        }

        renderCanvas() {
            if (!this.ui.canvas) {
                return;
            }
            const blocks = this.state.blocks.map(function (block, index) {
                return this.renderBlock(block, index);
            }, this).join('');
            this.ui.canvas.innerHTML = blocks || '<div class="fb-editor2__empty">' + escapeAttr(this.label('noBlocks', 'Start writing or press / to add a block.')) + '</div>';
        }

        refreshBlock(blockId) {
            if (!this.ui.canvas || !blockId) {
                return;
            }
            const index = this.blockIndex(blockId);
            const current = this.ui.canvas.querySelector('[data-block-id="' + blockId + '"]');
            if (index < 0 || !current) {
                return;
            }
            const template = document.createElement('template');
            template.innerHTML = this.renderBlock(this.state.blocks[index], index).trim();
            const next = template.content.firstElementChild;
            if (next) {
                current.replaceWith(next);
            }
        }

        renderBlock(block, index) {
            const definition = API.getBlockType(block.type) || {};
            const title = definition.title || block.type;
            const icon = iconClass(definition.icon);
            const selected = this.selectedIds.has(block.id);
            const active = this.activeId === block.id;
            const settings = block.settings || {};
            const classes = [
                'fb-editor2-block',
                selected ? 'is-selected' : '',
                active ? 'is-active' : '',
                block.hidden ? 'is-hidden' : '',
                'is-width-' + (settings.width || 'default')
            ].filter(Boolean).join(' ');
            const style = [
                '--fb-block-margin-top:' + Number(settings.marginTop || 0) + 'px',
                '--fb-block-margin-bottom:' + Number(settings.marginBottom == null ? 16 : settings.marginBottom) + 'px',
                '--fb-block-indent:' + Number(settings.indent || 0) + 'px',
                '--fb-block-align:' + String(settings.align || 'left'),
                settings.lineHeight ? '--fb-block-line-height:' + Number(settings.lineHeight) : ''
            ].filter(Boolean).join(';');

            return '<article class="' + classes + '" data-editor-block data-block-id="' + escapeAttr(block.id) + '" data-block-type="' + escapeAttr(block.type) + '" style="' + escapeAttr(style) + '" tabindex="-1" aria-label="' + escapeAttr(title) + '">' +
                '<div class="fb-editor2-block__rail">' +
                    '<button type="button" class="fb-editor2-block__drag" draggable="true" data-editor-drag-handle aria-label="' + escapeAttr(this.label('drag', 'Move block')) + '"><i class="ci-grip-vertical"></i></button>' +
                    '<button type="button" class="fb-editor2-block__select" data-editor-select-block aria-label="' + escapeAttr(this.label('selectBlock', 'Select block')) + '"><i class="' + icon + '"></i></button>' +
                '</div>' +
                '<div class="fb-editor2-block__surface">' +
                    '<div class="fb-editor2-block__meta"><i class="' + icon + '"></i><span>' + escapeAttr(title) + '</span><small>' + (index + 1) + '</small></div>' +
                    '<div class="fb-editor2-block__content">' + this.renderBlockContent(block) + '</div>' +
                    '<div class="fb-editor2-block__toolbar" role="toolbar">' +
                        '<button type="button" data-block-action="moveUp" aria-label="' + escapeAttr(this.label('moveUp', 'Move up')) + '"><i class="ci-arrow-up"></i></button>' +
                        '<button type="button" data-block-action="moveDown" aria-label="' + escapeAttr(this.label('moveDown', 'Move down')) + '"><i class="ci-arrow-down"></i></button>' +
                        '<button type="button" data-block-action="copy" aria-label="' + escapeAttr(this.label('copy', 'Copy')) + '"><i class="ci-clipboard"></i></button>' +
                        '<button type="button" data-block-action="duplicate" aria-label="' + escapeAttr(this.label('duplicate', 'Duplicate')) + '"><i class="ci-copy"></i></button>' +
                        '<button type="button" data-block-action="hide" aria-label="' + escapeAttr(block.hidden ? this.label('show', 'Show') : this.label('hide', 'Hide')) + '"><i class="' + (block.hidden ? 'ci-eye' : 'ci-eye-off') + '"></i></button>' +
                        '<button type="button" data-block-action="remove" aria-label="' + escapeAttr(this.label('remove', 'Remove')) + '"><i class="ci-trash"></i></button>' +
                        '<button type="button" data-block-action="more" aria-label="' + escapeAttr(this.label('more', 'More')) + '"><i class="ci-more-horizontal"></i></button>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="fb-editor2-block__insert" data-editor-insert-after="' + escapeAttr(block.id) + '" aria-label="' + escapeAttr(this.label('addBlock', 'Add block')) + '"><span><i class="ci-plus"></i></span><small>' + escapeAttr(this.label('addBlock', 'Add block')) + '</small></button>' +
            '</article>';
        }

        renderBlockContent(block) {
            const data = block.data || {};
            const custom = API.applyFilters('block:renderEditor', null, block, this);
            if (typeof custom === 'string') {
                return custom;
            }
            const definition = API.getBlockType(block.type);
            if (definition && typeof definition.renderEditor === 'function') {
                return String(definition.renderEditor(block, this) || '');
            }

            if (block.type === 'heading') {
                const level = /^h[1-6]$/.test(data.level) ? data.level : 'h2';
                return '<' + level + ' contenteditable="true" spellcheck="true" data-editor-rich data-editor-field="data.html" data-placeholder="' + escapeAttr(this.label('headingPlaceholder', 'Heading')) + '">' + sanitizer.sanitizeHtml(data.html || '') + '</' + level + '>';
            }
            if (block.type === 'text') {
                return '<div class="fb-editor2-rich" contenteditable="true" spellcheck="true" data-editor-rich data-editor-field="data.html" data-placeholder="' + escapeAttr(this.label('textPlaceholder', this.label('slashHint', 'Type / for commands'))) + '">' + sanitizer.sanitizeHtml(data.html || '') + '</div>';
            }
            if (block.type === 'quote') {
                return '<blockquote><div contenteditable="true" data-editor-rich data-editor-field="data.html" data-placeholder="' + escapeAttr(this.label('quotePlaceholder', 'Quote')) + '">' + sanitizer.sanitizeHtml(data.html || '') + '</div>' +
                    '<cite contenteditable="true" data-editor-plain data-editor-field="data.cite" data-placeholder="' + escapeAttr(this.label('authorPlaceholder', 'Author')) + '">' + escapeAttr(data.cite || '') + '</cite></blockquote>';
            }
            if (block.type === 'bulletList' || block.type === 'orderedList') {
                const tag = block.type === 'orderedList' ? 'ol' : 'ul';
                const items = Array.isArray(data.items) && data.items.length ? data.items : [''];
                return '<' + tag + ' class="fb-editor2-list">' + items.map(function (item, itemIndex) {
                    return '<li contenteditable="true" data-editor-list-item="' + itemIndex + '">' + sanitizer.sanitizeHtml(item || '') + '</li>';
                }).join('') + '</' + tag + '>';
            }
            if (block.type === 'checklist') {
                const items = Array.isArray(data.items) && data.items.length ? data.items : [{ text: '', checked: false }];
                return '<ul class="fb-editor2-checklist">' + items.map(function (item, itemIndex) {
                    return '<li><input type="checkbox" data-editor-check-item="' + itemIndex + '" ' + (item.checked ? 'checked' : '') + '><span contenteditable="true" data-editor-check-text="' + itemIndex + '">' + sanitizer.sanitizeHtml(item.text || '') + '</span></li>';
                }).join('') + '</ul>';
            }
            if (block.type === 'image') {
                const imageSize = (Number(data.width) > 0 ? ' width="' + Math.round(Number(data.width)) + '"' : '') +
                    (Number(data.height) > 0 ? ' height="' + Math.round(Number(data.height)) + '"' : '');
                const cropStyle = ['cover', 'contain'].indexOf(data.crop) !== -1 ? ' style="object-fit:' + data.crop + '"' : '';
                return '<figure class="fb-editor2-media">' +
                    (data.src
                        ? '<img src="' + escapeAttr(sanitizer.safeUrl(data.src, true)) + '" alt="' + escapeAttr(data.alt || '') + '" title="' + escapeAttr(data.title || '') + '" loading="' + (data.lazy === false ? 'eager' : 'lazy') + '"' + imageSize + cropStyle + '>'
                        : '<button type="button" class="fb-editor2-media__empty" data-editor-pick-block-media><i class="ci-image"></i><span>' + escapeAttr(this.label('chooseFile', 'Choose image')) + '</span></button>') +
                    '<figcaption contenteditable="true" data-editor-plain data-editor-field="data.caption" data-placeholder="' + escapeAttr(this.label('imageCaption', 'Caption')) + '">' + escapeAttr(data.caption || '') + '</figcaption>' +
                '</figure>';
            }
            if (block.type === 'gallery' || block.type === 'slider') {
                const items = Array.isArray(data.items) ? data.items : [];
                return '<div class="fb-editor2-gallery">' +
                    items.map(function (item) {
                        const src = sanitizer.safeUrl(item.src || item.image || '', true);
                        return src ? '<img src="' + escapeAttr(src) + '" alt="' + escapeAttr(item.alt || '') + '" loading="lazy">' : '';
                    }).join('') +
                    '<button type="button" data-editor-pick-block-media><i class="ci-plus"></i><span>' + escapeAttr(this.label('chooseFile', 'Add media')) + '</span></button>' +
                '</div>';
            }
            if (block.type === 'video') {
                const src = sanitizer.safeUrl(data.src || '', false);
                const videoAttributes = (data.controls === false ? '' : ' controls') +
                    (data.autoplay ? ' autoplay' : '') +
                    (data.muted || data.autoplay ? ' muted' : '') +
                    (data.loop ? ' loop' : '');
                return src
                    ? '<div class="fb-editor2-video" data-aspect-ratio="' + escapeAttr(data.aspectRatio || '16:9') + '"><video' + videoAttributes + ' preload="metadata" src="' + escapeAttr(src) + '" poster="' + escapeAttr(sanitizer.safeUrl(data.poster || '', true)) + '"></video><p contenteditable="true" data-editor-plain data-editor-field="data.caption">' + escapeAttr(data.caption || '') + '</p></div>'
                    : '<button type="button" class="fb-editor2-media__empty" data-editor-pick-block-media><i class="ci-video"></i><span>' + escapeAttr(this.label('chooseFile', 'Choose video')) + '</span></button>';
            }
            if (block.type === 'audio') {
                const src = sanitizer.safeUrl(data.src || '', false);
                return src
                    ? '<div class="fb-editor2-audio"><audio controls preload="metadata" src="' + escapeAttr(src) + '"></audio><p contenteditable="true" data-editor-plain data-editor-field="data.caption">' + escapeAttr(data.caption || '') + '</p></div>'
                    : '<button type="button" class="fb-editor2-media__empty" data-editor-pick-block-media><i class="ci-music"></i><span>' + escapeAttr(this.label('chooseFile', 'Choose audio')) + '</span></button>';
            }
            if (block.type === 'table') {
                return this.renderTableEditor(block);
            }
            if (block.type === 'code') {
                return '<div class="fb-editor2-code"><span>' + escapeAttr(data.language || 'text') + '</span><textarea spellcheck="false" data-editor-field="data.code">' + escapeAttr(data.code || '') + '</textarea></div>';
            }
            if (block.type === 'html') {
                return '<div class="fb-editor2-html"><textarea spellcheck="false" data-editor-field="data.html" placeholder="<div>…</div>">' + escapeAttr(data.html || '') + '</textarea><div class="fb-editor2-html__preview">' + sanitizer.sanitizeHtmlBlock(data.html || '') + '</div></div>';
            }
            if (block.type === 'divider') {
                return '<div class="fb-editor2-divider"><span></span><small>***</small><span></span></div>';
            }
            if (block.type === 'alert') {
                const variant = /^(primary|secondary|success|danger|warning|info|light|dark)$/.test(data.variant) ? data.variant : 'info';
                return '<div class="fb-editor2-alert is-' + variant + '"><i class="' + iconClass(data.icon || 'ci-info') + '"></i><div><strong contenteditable="true" data-editor-plain data-editor-field="data.title">' + escapeAttr(data.title || '') + '</strong><p contenteditable="true" data-editor-plain data-editor-field="data.text">' + escapeAttr(data.text || '') + '</p></div></div>';
            }
            if (block.type === 'faq') {
                const items = Array.isArray(data.items) && data.items.length ? data.items : [{ question: '', answer: '' }];
                return '<div class="fb-editor2-faq">' + items.map(function (item, itemIndex) {
                    return '<details open><summary contenteditable="true" data-editor-faq-question="' + itemIndex + '">' + escapeAttr(item.question || '') + '</summary><div contenteditable="true" data-editor-faq-answer="' + itemIndex + '">' + sanitizer.sanitizeHtml(item.answer || '') + '</div></details>';
                }).join('') + '</div>';
            }
            if (block.type === 'button') {
                return '<div class="fb-editor2-button is-' + escapeAttr(data.align || 'left') + '"><span class="btn btn-' + escapeAttr(data.style || 'primary') + '">' + escapeAttr(data.text || 'Button') + '</span></div>';
            }
            if (block.type === 'embed') {
                const embed = this.embedUrl(data.url || '');
                return embed
                    ? '<div class="fb-editor2-embed"><iframe src="' + escapeAttr(embed) + '" title="' + escapeAttr(data.caption || this.label('embedTitle', 'Embedded content')) + '" loading="lazy"></iframe></div>'
                    : '<div class="fb-editor2-embed-empty"><i class="ci-external-link"></i><span>' + escapeAttr(data.url || this.label('embedUrlHint', 'Paste a supported URL in block settings')) + '</span></div>';
            }
            if (block.type === 'newsletter') {
                return this.renderNewsletterBlock(block, true);
            }
            if (block.type === 'social') {
                const items = Array.isArray(data.items) ? data.items : [];
                return '<div class="fb-editor2-social fb-social-buttons">' + items.map(function (item) {
                    return '<span class="fb-social-buttons__item">' + socialIconMarkup(item.icon) + '<span class="fb-social-buttons__label">' + escapeAttr(item.label || item.url || '') + '</span></span>';
                }).join('') + '</div>';
            }
            return '<div class="fb-editor2-unknown"><i class="ci-box"></i><div><strong>' + escapeAttr(block.type) + '</strong><p>Plugin block data is preserved.</p></div></div>';
        }

        renderNewsletterBlock(block, editable) {
            const data = block && block.data ? block.data : {};
            const title = String(data.title || '').trim() || this.label('newsletterDefaultTitle', 'Sign up to our newsletter');
            const text = String(data.text || '').trim() || this.label('newsletterDefaultText', 'Receive our latest updates about our products & promotions');
            const buttonText = String(data.buttonText || '').trim() || this.label('newsletterDefaultButton', 'Subscribe');
            const buttonUrl = sanitizer.safeUrl(data.buttonUrl || '', false);
            const requestedIcon = String(data.buttonIcon || 'ci-mail').trim();
            const buttonIcon = /^ci-[a-z0-9-]+$/i.test(requestedIcon) ? requestedIcon : 'ci-mail';
            const titleAttributes = editable
                ? ' contenteditable="true" spellcheck="true" data-editor-plain data-editor-field="data.title"'
                : '';
            const textAttributes = editable
                ? ' contenteditable="true" spellcheck="true" data-editor-plain data-editor-field="data.text"'
                : '';
            const buttonLabel = editable
                ? '<span contenteditable="true" spellcheck="true" data-editor-plain data-editor-field="data.buttonText">' + escapeAttr(buttonText) + '</span>'
                : escapeAttr(buttonText);
            const buttonInner = '<i class="' + escapeAttr(buttonIcon) + ' fs-base ms-n1 me-2" aria-hidden="true"></i>' + buttonLabel;
            const button = !editable && buttonUrl
                ? '<a class="btn btn-dark" href="' + escapeAttr(buttonUrl) + '" target="_blank" rel="noopener noreferrer" data-fb-newsletter-button="1">' + buttonInner + '</a>'
                : '<button type="button" class="btn btn-dark" data-fb-newsletter-button="1">' + buttonInner + '</button>';

            return '<div class="' + (editable ? 'fb-editor2-newsletter-live ' : '') + 'd-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5" data-fb-newsletter-block="1" data-button-text="' + escapeAttr(buttonText) + '" data-button-url="' + escapeAttr(buttonUrl) + '" data-button-icon="' + escapeAttr(buttonIcon) + '">' +
                '<div class="mb-4 mb-sm-0 me-sm-4">' +
                    '<h3 class="h5 mb-2" data-fb-newsletter-title="1"' + titleAttributes + '>' + escapeAttr(title) + '</h3>' +
                    '<p class="fs-sm mb-0" data-fb-newsletter-text="1"' + textAttributes + '>' + escapeAttr(text) + '</p>' +
                '</div>' +
                button +
            '</div>';
        }

        renderTableEditor(block) {
            const data = block.data || {};
            const rows = Array.isArray(data.rows) && data.rows.length ? data.rows : [['', ''], ['', '']];
            return '<div class="fb-editor2-table-wrap"><table><tbody>' + rows.map(function (row, rowIndex) {
                const tag = data.header && rowIndex === 0 ? 'th' : 'td';
                return '<tr>' + (Array.isArray(row) ? row : ['']).map(function (cell, columnIndex) {
                    return '<' + tag + ' contenteditable="true" data-editor-table-cell="' + rowIndex + ':' + columnIndex + '">' + sanitizer.sanitizeHtml(cell || '') + '</' + tag + '>';
                }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
        }

        refreshOutline() {
            if (!this.ui.outline) {
                return;
            }
            const needle = String(this.outlineQuery || '').toLocaleLowerCase();
            this.ui.outline.innerHTML = this.state.blocks.map(function (block, index) {
                const definition = API.getBlockType(block.type) || {};
                const text = this.blockSummary(block) || definition.title || block.type;
                const searchable = (text + ' ' + (definition.title || '') + ' ' + block.type).toLocaleLowerCase();
                if (needle && searchable.indexOf(needle) === -1) {
                    return '';
                }
                return '<button type="button" class="' + (block.id === this.activeId ? 'is-active' : '') + '" data-editor-outline-block="' + escapeAttr(block.id) + '">' +
                    '<i class="' + iconClass(definition.icon) + '"></i><span><strong>' + escapeAttr(text) + '</strong><small>' + escapeAttr(definition.title || block.type) + '</small></span><em>' + (index + 1) + '</em>' +
                '</button>';
            }, this).join('');
        }

        blockSummary(block) {
            const data = block.data || {};
            if (block.type === 'heading' || block.type === 'text' || block.type === 'quote') {
                return stripHtml(data.html || '').slice(0, 54);
            }
            if (block.type === 'code') {
                return String(data.code || '').split('\n')[0].slice(0, 54);
            }
            if (block.type === 'image' || block.type === 'video' || block.type === 'audio') {
                return String(data.caption || data.alt || data.src || '').slice(0, 54);
            }
            if (block.type === 'alert' || block.type === 'newsletter') {
                return String(data.title || data.text || '').slice(0, 54);
            }
            return '';
        }

        blockSearchText(block) {
            const values = [];
            const collect = function (value) {
                if (typeof value === 'string') {
                    values.push(stripHtml(value));
                    return;
                }
                if (Array.isArray(value)) {
                    value.forEach(collect);
                    return;
                }
                if (value && typeof value === 'object') {
                    Object.keys(value).forEach(function (key) {
                        if (key !== 'src' && key !== 'url' && key !== 'link') {
                            collect(value[key]);
                        }
                    });
                }
            };
            collect(block && block.data ? block.data : {});
            return values.join(' ').replace(/\s+/g, ' ').trim();
        }

        renderInspector() {
            if (!this.ui.inspector) {
                return;
            }
            const block = this.activeBlock();
            if (!block) {
                this.ui.inspector.innerHTML = '<div class="fb-editor2-inspector-empty"><i class="ci-cursor"></i><p>' + escapeAttr(this.label('selectBlock', 'Select a block to edit its settings.')) + '</p></div>';
                return;
            }
            const definition = API.getBlockType(block.type) || {};
            const custom = API.applyFilters('block:renderInspector', null, block, this);
            const typeFields = typeof custom === 'string'
                ? custom
                : (definition && typeof definition.renderInspector === 'function'
                    ? String(definition.renderInspector(block, this) || '')
                    : this.renderTypeInspector(block));
            const settings = block.settings || {};
            const hiddenOn = settings.hiddenOn || [];
            this.ui.inspector.innerHTML =
                '<section class="fb-editor2-inspector-section fb-editor2-inspector-type">' +
                    '<div class="fb-editor2-inspector-heading"><span><i class="' + iconClass(definition.icon) + '"></i></span><div><small>' + escapeAttr(this.label('blockSettings', 'Block type')) + '</small><strong>' + escapeAttr(definition.title || block.type) + '</strong></div></div>' +
                    typeFields +
                '</section>' +
                '<section class="fb-editor2-inspector-section"><h3>' + escapeAttr(this.label('settings', 'Settings')) + '</h3>' +
                    this.selectField('settings.width', this.label('contentWidth', 'Content width'), settings.width || 'default', [
                        ['default', this.label('widthDefault', 'Default')],
                        ['wide', this.label('widthWide', 'Wide')],
                        ['full', this.label('widthFull', 'Full width')]
                    ]) +
                    this.numberField('settings.marginTop', this.label('topSpacing', 'Top spacing'), settings.marginTop || 0, 0, 160) +
                    this.numberField('settings.marginBottom', this.label('bottomSpacing', 'Bottom spacing'), settings.marginBottom == null ? 16 : settings.marginBottom, 0, 160) +
                    this.selectField('settings.align', this.label('alignment', 'Alignment'), settings.align || 'left', [
                        ['left', this.label('alignLeft', 'Left')],
                        ['center', this.label('alignCenter', 'Center')],
                        ['right', this.label('alignRight', 'Right')],
                        ['justify', this.label('alignJustify', 'Justify')]
                    ]) +
                    this.numberField('settings.lineHeight', this.label('lineHeight', 'Line height'), settings.lineHeight || 0, 0, 3, '0.1') +
                    this.checkField('settings.indentFirstLine', this.label('firstLineIndent', 'First line indent'), Boolean(settings.indentFirstLine)) +
                '</section>' +
                '<section class="fb-editor2-inspector-section"><h3>' + escapeAttr(this.label('advanced', 'Advanced')) + '</h3>' +
                    this.textField('settings.className', this.label('cssClass', 'CSS class'), settings.className || '', 'my-paragraph') +
                    this.textField('settings.anchor', this.label('anchor', 'Anchor'), settings.anchor || '', 'paragraph-1') +
                    '<fieldset class="fb-editor2-inspector-devices"><legend>' + escapeAttr(this.label('hideOnDevices', 'Hide on devices')) + '</legend>' +
                        this.checkField('settings.hiddenOn.desktop', this.label('desktop', 'Desktop'), hiddenOn.indexOf('desktop') !== -1, 'desktop') +
                        this.checkField('settings.hiddenOn.tablet', this.label('tablet', 'Tablet'), hiddenOn.indexOf('tablet') !== -1, 'tablet') +
                        this.checkField('settings.hiddenOn.mobile', this.label('mobile', 'Mobile'), hiddenOn.indexOf('mobile') !== -1, 'mobile') +
                    '</fieldset>' +
                '</section>' +
                '<section class="fb-editor2-inspector-section fb-editor2-inspector-actions">' +
                    '<button type="button" data-editor-remove-active><i class="ci-trash"></i>' + escapeAttr(this.label('remove', 'Remove block')) + '</button>' +
                '</section>';
        }

        renderTypeInspector(block) {
            const data = block.data || {};
            if (block.type === 'heading') {
                return this.selectField('data.level', 'Heading level', data.level || 'h2', [
                    ['h1', 'Heading 1'], ['h2', 'Heading 2'], ['h3', 'Heading 3'],
                    ['h4', 'Heading 4'], ['h5', 'Heading 5'], ['h6', 'Heading 6']
                ]);
            }
            if (block.type === 'image') {
                return this.textField('data.src', this.label('sourceLink', 'Image URL'), data.src || '', 'https://…') +
                    '<button type="button" class="btn btn-outline-secondary w-100 mb-3" data-editor-pick-media-path="data.src"><i class="ci-folder me-2"></i>' + escapeAttr(this.label('chooseFile', 'Media library')) + '</button>' +
                    this.textField('data.alt', this.label('imageAlt', 'Alternative text'), data.alt || '', '') +
                    this.textField('data.title', this.label('imageTitle', 'Image title'), data.title || '', '') +
                    this.textField('data.caption', this.label('imageCaption', 'Caption'), data.caption || '', '') +
                    this.textField('data.link', this.label('imageLink', 'Link'), data.link || '', 'https://…') +
                    this.checkField('data.lazy', this.label('lazyLoading', 'Lazy loading'), data.lazy !== false) +
                    this.numberField('data.width', this.label('imageWidth', 'Width'), Number(data.width || 0), 0, 8000) +
                    this.numberField('data.height', this.label('imageHeight', 'Height'), Number(data.height || 0), 0, 8000) +
                    this.selectField('data.crop', this.label('imageCrop', 'Crop'), data.crop || 'none', [
                        ['none', this.label('widthDefault', 'Default')],
                        ['cover', 'Cover'],
                        ['contain', 'Contain']
                    ]);
            }
            if (block.type === 'video' || block.type === 'audio') {
                const subscriptionAccess = block.type === 'video' ? this.renderVideoSubscriptionAccess(data) : '';
                return this.textField('data.src', this.label('sourceLink', 'Media URL'), data.src || '', 'https://…') +
                    '<button type="button" class="btn btn-outline-secondary w-100 mb-3" data-editor-pick-media-path="data.src"><i class="ci-folder me-2"></i>' + escapeAttr(this.label('chooseFile', 'Media library')) + '</button>' +
                    (block.type === 'video' ? this.textField('data.poster', this.label('videoPoster', 'Poster'), data.poster || '', 'https://…') : '') +
                    this.textField('data.caption', this.label('caption', 'Caption'), data.caption || '', '') +
                    (block.type === 'video'
                        ? this.checkField('data.autoplay', this.label('videoAutoplay', 'Autoplay'), Boolean(data.autoplay)) +
                            this.checkField('data.muted', this.label('videoMuted', 'Muted'), Boolean(data.muted)) +
                            this.checkField('data.controls', this.label('videoControls', 'Show controls'), data.controls !== false) +
                            this.checkField('data.loop', this.label('videoLoop', 'Loop'), Boolean(data.loop)) +
                            this.checkField('data.hls', this.label('videoHls', 'HLS stream'), Boolean(data.hls)) +
                            this.selectField('data.aspectRatio', this.label('aspectRatio', 'Aspect ratio'), data.aspectRatio || '16:9', [
                                ['16:9', '16:9'], ['4:3', '4:3'], ['1:1', '1:1'], ['9:16', '9:16']
                            ]) + subscriptionAccess
                        : '');
            }
            if (block.type === 'embed') {
                return this.textField('data.url', 'URL', data.url || '', 'https://youtube.com/…') +
                    this.textField('data.caption', this.label('caption', 'Caption'), data.caption || '', '');
            }
            if (block.type === 'code') {
                return this.textField('data.language', this.label('codeLanguage', 'Language'), data.language || 'text', 'javascript');
            }
            if (block.type === 'table') {
                const rows = Array.isArray(data.rows) ? data.rows.length : 2;
                const columns = rows && Array.isArray(data.rows[0]) ? data.rows[0].length : 2;
                return this.numberField('table.rows', this.label('rows', 'Rows'), rows, 1, 50) +
                    this.numberField('table.columns', this.label('columns', 'Columns'), columns, 1, 20) +
                    this.checkField('data.header', this.label('headerRow', 'Header row'), Boolean(data.header)) +
                    this.checkField('data.bordered', this.label('tableBordered', 'Borders'), data.bordered !== false) +
                    this.checkField('data.responsive', this.label('tableResponsive', 'Responsive'), data.responsive !== false);
            }
            if (block.type === 'alert') {
                return this.selectField('data.variant', this.label('variant', 'Variant'), data.variant || 'info', [
                    ['primary', 'Primary'], ['success', 'Success'], ['info', 'Info'],
                    ['warning', 'Warning'], ['danger', 'Danger'], ['dark', 'Dark']
                ]);
            }
            if (block.type === 'button') {
                return this.textField('data.text', this.label('buttonText', 'Button text'), data.text || '', '') +
                    this.textField('data.url', 'URL', data.url || '', 'https://…') +
                    this.selectField('data.style', 'Style', data.style || 'primary', [
                        ['primary', 'Primary'], ['dark', 'Dark'], ['outline-secondary', 'Outline']
                    ]) +
                    this.selectField('data.align', 'Alignment', data.align || 'left', [
                        ['left', 'Left'], ['center', 'Center'], ['right', 'Right']
                    ]);
            }
            if (block.type === 'gallery' || block.type === 'slider') {
                return '<button type="button" class="btn btn-outline-secondary w-100 mb-3" data-editor-pick-media-path="data.items"><i class="ci-plus me-2"></i>' + escapeAttr(this.label('chooseFile', 'Add media')) + '</button>';
            }
            if (block.type === 'newsletter') {
                return this.textField('data.title', this.label('newsletterTitle', 'Title'), data.title || '', '') +
                    this.textField('data.text', this.label('newsletterText', 'Text'), data.text || '', '') +
                    this.textField('data.buttonText', this.label('newsletterButton', 'Button text'), data.buttonText || '', '') +
                    this.textField('data.buttonUrl', this.label('newsletterUrl', 'Button link'), data.buttonUrl || '', 'https://…') +
                    this.textField('data.buttonIcon', this.label('newsletterIcon', 'Button icon'), data.buttonIcon || 'ci-mail', 'ci-mail');
            }
            if (block.type === 'social') {
                return this.renderSocialInspector(data);
            }
            return '<p class="fb-editor2-inspector-hint">' + escapeAttr(this.label('inlineSettingsHint', 'Edit this block directly in the document.')) + '</p>';
        }

        renderSocialInspector(data) {
            const networks = Array.isArray(this.config.socialNetworks) && this.config.socialNetworks.length
                ? this.config.socialNetworks
                : [{ value: 'website', label: this.label('socialExternalLink', 'Website'), icon: 'ci-globe', placeholder: 'https://…' }];
            const items = Array.isArray(data.items) ? data.items : [];
            const fields = items.map(function (item, index) {
                const selected = networks.find(function (network) {
                    return String(network.value) === String(item.network || '');
                }) || networks[0];
                const options = networks.map(function (network) {
                    return '<option value="' + escapeAttr(network.value) + '" ' + (String(selected.value) === String(network.value) ? 'selected' : '') + '>' + escapeAttr(network.label) + '</option>';
                }).join('');

                return '<fieldset class="fb-editor2-social-item">' +
                    '<legend>' + escapeAttr(this.label('socialNetwork', 'Social network')) + ' ' + (index + 1) + '</legend>' +
                    '<label class="fb-editor2-inspector-field"><span>' + escapeAttr(this.label('socialNetwork', 'Social network')) + '</span><select data-editor-setting="data.items.' + index + '.network" data-editor-social-network="' + index + '">' + options + '</select></label>' +
                    this.textField('data.items.' + index + '.icon', this.label('socialIcon', 'Icon'), item.icon || selected.icon || 'ci-globe', 'ci-globe') +
                    this.textField('data.items.' + index + '.label', this.label('socialLabel', 'Label'), item.label || selected.label || '', '') +
                    this.textField('data.items.' + index + '.url', selected.value === 'phone' ? this.label('socialPhone', 'Phone') : this.label('socialUrl', 'Link'), item.url || '', selected.placeholder || 'https://…') +
                    '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-editor-social-remove="' + index + '"><i class="ci-trash me-2"></i>' + escapeAttr(this.label('socialRemoveItem', 'Remove')) + '</button>' +
                '</fieldset>';
            }, this).join('');

            return '<p class="fb-editor2-inspector-hint">' + escapeAttr(this.label('socialItemsHint', 'Add links and choose their icons.')) + '</p>' +
                fields +
                '<button type="button" class="btn btn-outline-secondary w-100" data-editor-social-add><i class="ci-plus me-2"></i>' + escapeAttr(this.label('socialAddItem', 'Add link')) + '</button>';
        }

        renderVideoSubscriptionAccess(data) {
            const config = this.config.subscriptionVideoAccess;
            if (!config || !Array.isArray(config.plans)) {
                return '';
            }
            const labels = config.labels || {};
            const selectedPlans = Array.isArray(data.subscriptionPlanIds) ? data.subscriptionPlanIds.map(Number) : [];
            const accessMode = data.subscriptionAccessMode || 'public';
            const planFields = config.plans.map(function (plan) {
                return '<label class="fb-editor2-inspector-check"><input type="checkbox" value="' + Number(plan.id) + '" data-editor-video-plan ' + (selectedPlans.indexOf(Number(plan.id)) !== -1 ? 'checked' : '') + '><span>' + escapeAttr(plan.name) + '</span></label>';
            }).join('');

            return '<fieldset class="fb-editor2-social-item"><legend>' + escapeAttr(labels.title || 'Video access') + '</legend>' +
                this.selectField('data.subscriptionAccessMode', labels.title || 'Video access', accessMode, [
                    ['public', labels.public || 'Everyone'],
                    ['subscribers', labels.subscribers || 'Subscribers'],
                    ['plans', labels.plans || 'Selected plans']
                ]) +
                (planFields ? '<div class="fb-editor2-inspector-field" data-editor-video-plans ' + (accessMode === 'plans' ? '' : 'hidden') + '><span>' + escapeAttr(labels.allowedPlans || 'Allowed plans') + '</span>' + planFields + '</div>' : '') +
            '</fieldset>';
        }

        textField(path, label, value, placeholder) {
            return '<label class="fb-editor2-inspector-field"><span>' + escapeAttr(label) + '</span><input type="text" value="' + escapeAttr(value) + '" placeholder="' + escapeAttr(placeholder || '') + '" data-editor-setting="' + escapeAttr(path) + '"></label>';
        }

        numberField(path, label, value, min, max, step) {
            return '<label class="fb-editor2-inspector-field"><span>' + escapeAttr(label) + '</span><input type="number" value="' + escapeAttr(value) + '" min="' + Number(min) + '" max="' + Number(max) + '" step="' + escapeAttr(step || '1') + '" data-editor-setting="' + escapeAttr(path) + '"></label>';
        }

        selectField(path, label, value, options) {
            return '<label class="fb-editor2-inspector-field"><span>' + escapeAttr(label) + '</span><select data-editor-setting="' + escapeAttr(path) + '">' +
                options.map(function (option) {
                    return '<option value="' + escapeAttr(option[0]) + '" ' + (String(value) === String(option[0]) ? 'selected' : '') + '>' + escapeAttr(option[1]) + '</option>';
                }).join('') +
            '</select></label>';
        }

        checkField(path, label, checked, device) {
            return '<label class="fb-editor2-inspector-check"><input type="checkbox" ' + (checked ? 'checked' : '') + ' data-editor-setting="' + escapeAttr(path) + '"' + (device ? ' data-editor-device="' + escapeAttr(device) + '"' : '') + '><span>' + escapeAttr(label) + '</span></label>';
        }

        refreshStatus() {
            const visible = this.state.blocks.filter(function (block) { return !block.hidden; });
            const contentText = visible.map(this.blockSearchText.bind(this)).join(' ').replace(/\s+/g, ' ').trim();
            const words = contentText ? contentText.split(/\s+/).filter(Boolean).length : 0;
            const characters = contentText.length;
            const readingMinutes = words ? Math.max(1, Math.ceil(words / 200)) : 0;
            if (this.ui.statusBlocks) {
                this.ui.statusBlocks.textContent = visible.length + ' ' + this.label('blockCountLabel', 'blocks');
            }
            if (this.ui.statusWords) {
                this.ui.statusWords.textContent = words + ' ' + this.label('wordCount', 'words');
            }
            if (this.ui.statusCharacters) {
                this.ui.statusCharacters.textContent = characters + ' ' + this.label('characterCount', 'characters');
            }
            if (this.ui.statusReading) {
                this.ui.statusReading.textContent = readingMinutes + ' ' + this.label('readingMinutes', 'min read');
            }
            if (this.ui.statusSavedAt) {
                this.ui.statusSavedAt.textContent = this.label('lastSaved', 'Last saved') + ': ' + (this.lastSavedAt || '—');
            }
            if (this.ui.statusCurrent) {
                const block = this.activeBlock();
                const definition = block ? API.getBlockType(block.type) : null;
                this.ui.statusCurrent.textContent = definition ? definition.title : (block ? block.type : '—');
            }
        }

        updateHistoryControls() {
            if (this.ui.undo) {
                this.ui.undo.disabled = !this.history.canUndo();
            }
            if (this.ui.redo) {
                this.ui.redo.disabled = !this.history.canRedo();
            }
        }

        setSaveState(state, label) {
            if (this.ui.saveState) {
                this.ui.saveState.setAttribute('data-state', state);
            }
            if (this.ui.saveStateLabel) {
                this.ui.saveStateLabel.textContent = label || this.label(state, state);
            }
        }

        handleRootClick(event) {
            const target = event.target;
            const blockElement = target.closest('[data-editor-block]');
            const blockId = blockElement ? blockElement.getAttribute('data-block-id') : '';

            if (target.closest('[data-editor-command-close]')) {
                event.preventDefault();
                this.closeCommandPalette();
                return;
            }
            if (target.closest('[data-editor-inline]')) {
                event.preventDefault();
                this.applyInline(target.closest('[data-editor-inline]').getAttribute('data-editor-inline'));
                return;
            }
            if (target.closest('[data-editor-command]')) {
                event.preventDefault();
                this.runToolbarCommand(target.closest('[data-editor-command]').getAttribute('data-editor-command'));
                return;
            }
            if (target.closest('[data-editor-align]')) {
                event.preventDefault();
                const block = this.activeBlock();
                if (block) {
                    block.settings.align = target.closest('[data-editor-align]').getAttribute('data-editor-align');
                    this.commit('align', true, true);
                }
                return;
            }
            if (target.closest('[data-editor-convert]')) {
                event.preventDefault();
                this.convertActiveBlock(target.closest('[data-editor-convert]').getAttribute('data-editor-convert'));
                return;
            }
            if (target.closest('[data-editor-insert-after]')) {
                event.preventDefault();
                this.openBlockMenu(target.closest('[data-editor-insert-after]').getAttribute('data-editor-insert-after'), target.closest('[data-editor-insert-after]'));
                return;
            }
            if (target.closest('[data-block-action]')) {
                event.preventDefault();
                this.handleBlockAction(blockId, target.closest('[data-block-action]').getAttribute('data-block-action'), target.closest('[data-block-action]'));
                return;
            }
            if (target.closest('[data-editor-pick-block-media]')) {
                event.preventDefault();
                this.openMediaPicker(blockId, blockElement, blockElement && blockElement.getAttribute('data-block-type') === 'gallery' ? 'data.items' : 'data.src');
                return;
            }
            if (target.closest('[data-editor-select-block]')) {
                event.preventDefault();
                this.selectBlock(blockId, event);
                return;
            }
            if (blockId) {
                this.selectBlock(blockId, event, target.closest('[contenteditable], textarea, input, select') !== null);
            }
        }

        handleWorkspaceClick(event) {
            const target = event.target;
            const modeButton = target.closest('button[data-editor-mode]');
            if (modeButton) {
                this.setMode(modeButton.getAttribute('data-editor-mode'));
                return;
            }
            if (target.closest('[data-editor-undo]')) {
                this.undo();
                return;
            }
            if (target.closest('[data-editor-redo]')) {
                this.redo();
                return;
            }
            if (target.closest('[data-editor-preview]')) {
                this.openPreview();
                return;
            }
            if (target.closest('[data-editor-preview-right]')) {
                this.toggleSplitPreview(true);
                return;
            }
            if (target.closest('[data-editor-preview-right-close]')) {
                this.toggleSplitPreview(false);
                return;
            }
            if (target.closest('[data-editor-preview-new]')) {
                this.openPreviewInNewTab();
                return;
            }
            if (target.closest('[data-editor-focus]')) {
                this.toggleFocusMode();
                return;
            }
            if (target.closest('[data-editor-fullscreen]')) {
                this.toggleFullscreen();
                return;
            }
            if (target.closest('[data-editor-command-palette]')) {
                this.openCommandPalette();
                return;
            }
            if (target.closest('[data-editor-document-settings]')) {
                this.activateInspectorTab('document');
                this.openMobilePanels();
                return;
            }
            if (target.closest('[data-editor-mobile-panels]')) {
                this.openMobilePanels();
                return;
            }
            if (target.closest('[data-editor-panel-close]')) {
                this.closeMobilePanels();
                return;
            }
            if (target.closest('[data-editor-add-block]')) {
                this.openBlockMenu(this.state.blocks.length ? this.state.blocks[this.state.blocks.length - 1].id : '', target.closest('[data-editor-add-block]'));
                return;
            }
            if (target.closest('[data-editor-remove-active]')) {
                this.confirmRemoveBlocks(this.activeId ? [this.activeId] : []);
                return;
            }
            if (target.closest('[data-editor-delete-cancel]')) {
                this.pendingDeleteIds = [];
                if (this.ui.deleteDialog) {
                    this.ui.deleteDialog.close();
                }
                return;
            }
            if (target.closest('[data-editor-delete-confirm]')) {
                const ids = this.pendingDeleteIds.slice();
                this.pendingDeleteIds = [];
                if (this.ui.deleteDialog) {
                    this.ui.deleteDialog.close();
                }
                this.removeBlocks(ids);
                return;
            }
            if (target.closest('[data-editor-social-add]')) {
                const block = this.activeBlock();
                if (block && block.type === 'social') {
                    const network = (this.config.socialNetworks || [])[0] || { value: 'website', label: '', icon: 'ci-globe' };
                    block.data.items = Array.isArray(block.data.items) ? block.data.items : [];
                    block.data.items.push({ network: network.value, icon: network.icon, label: network.label, url: '' });
                    this.commit('social-add', true, true);
                    const index = block.data.items.length - 1;
                    window.requestAnimationFrame(function () {
                        const urlInput = this.ui.inspector.querySelector('[data-editor-setting="data.items.' + index + '.url"]');
                        if (urlInput) {
                            urlInput.scrollIntoView({ block: 'center' });
                            urlInput.focus({ preventScroll: true });
                        }
                    }.bind(this));
                }
                return;
            }
            const socialRemove = target.closest('[data-editor-social-remove]');
            if (socialRemove) {
                const block = this.activeBlock();
                const index = Number(socialRemove.getAttribute('data-editor-social-remove'));
                if (block && block.type === 'social' && Array.isArray(block.data.items) && index >= 0) {
                    block.data.items.splice(index, 1);
                    this.commit('social-remove', true, true);
                }
                return;
            }
            const outlineButton = target.closest('[data-editor-outline-block]');
            if (outlineButton) {
                const id = outlineButton.getAttribute('data-editor-outline-block');
                this.selectBlock(id, event);
                this.scrollToBlock(id);
                return;
            }
            const inspectorTab = target.closest('[data-editor-inspector-tab]');
            if (inspectorTab) {
                this.activateInspectorTab(inspectorTab.getAttribute('data-editor-inspector-tab'));
                return;
            }
            const settingButton = target.closest('[data-editor-pick-media-path]');
            if (settingButton) {
                this.openMediaPicker(this.activeId, settingButton, settingButton.getAttribute('data-editor-pick-media-path'));
                return;
            }
            const submitButton = target.closest('[data-editor-submit]');
            if (submitButton && this.ui.publishedInput) {
                this.ui.publishedInput.value = submitButton.getAttribute('data-editor-submit') === 'publish' ? '1' : '0';
            }
            const back = target.closest('[data-editor-back]');
            if (back && this.dirty && !window.confirm(this.workspace.getAttribute('data-editor-unsaved-confirm') || this.label('unsavedConfirm', 'There are unsaved changes. Leave the editor?'))) {
                event.preventDefault();
                return;
            }
            const fileButton = target.closest('[data-file-manager-open]');
            if (fileButton) {
                event.preventDefault();
                this.openDocumentFilePicker(fileButton);
            }
        }

        handleRootInput(event) {
            const target = event.target;
            const blockElement = target.closest('[data-editor-block]');
            if (!blockElement) {
                if (target === this.ui.slashSearch) {
                    this.renderSlashMenu(target.value);
                }
                if (target === this.ui.commandSearch) {
                    this.commandPaletteIndex = 0;
                    this.renderCommandPalette(target.value);
                }
                if (target === this.ui.searchInput) {
                    this.performSearch(0);
                }
                return;
            }
            const block = this.state.blocks[this.blockIndex(blockElement.getAttribute('data-block-id'))];
            if (!block) {
                return;
            }
            const field = target.getAttribute('data-editor-field');
            if (field) {
                setPath(block, field, target.matches('[contenteditable]') ? target.innerHTML : target.value);
            }
            if (block.type === 'html' && target.matches('textarea[data-editor-field="data.html"]')) {
                const preview = blockElement.querySelector('.fb-editor2-html__preview');
                if (preview) {
                    preview.innerHTML = sanitizer.sanitizeHtmlBlock(target.value);
                }
            }
            if (target.hasAttribute('data-editor-list-item')) {
                const index = Number(target.getAttribute('data-editor-list-item'));
                block.data.items[index] = target.innerHTML;
            }
            if (target.hasAttribute('data-editor-check-text')) {
                const index = Number(target.getAttribute('data-editor-check-text'));
                block.data.items[index].text = target.innerHTML;
            }
            if (target.hasAttribute('data-editor-table-cell')) {
                const parts = target.getAttribute('data-editor-table-cell').split(':').map(Number);
                block.data.rows[parts[0]][parts[1]] = target.innerHTML;
            }
            if (target.hasAttribute('data-editor-faq-question')) {
                block.data.items[Number(target.getAttribute('data-editor-faq-question'))].question = target.textContent;
            }
            if (target.hasAttribute('data-editor-faq-answer')) {
                block.data.items[Number(target.getAttribute('data-editor-faq-answer'))].answer = target.innerHTML;
            }

            this.activeId = block.id;
            this.selectedIds = new Set([block.id]);
            this.commit('typing', false, false);
            this.refreshOutlineSoon();
            this.refreshStatusSoon();
            if (target.matches('[data-editor-rich]')) {
                const text = String(target.textContent || '');
                if (/^\/[^/\n]*$/.test(text)) {
                    this.openSlashForBlock(block.id, target, text.slice(1));
                } else {
                    this.closeSlashMenu();
                }
            }
        }

        handleRootChange(event) {
            const target = event.target;
            if (target === this.ui.searchCase) {
                this.searchIndex = -1;
                this.performSearch(0);
                return;
            }
            if (target.matches('[data-editor-block-style]')) {
                this.convertActiveBlock(target.value);
                return;
            }
            if (target.matches('[data-editor-format-value]')) {
                this.applyInlineStyle(target.getAttribute('data-editor-format-value'), target.value);
                return;
            }
            if (target.matches('[data-editor-color]')) {
                this.applyInlineStyle(target.getAttribute('data-editor-color'), target.value);
                return;
            }
            const blockElement = target.closest('[data-editor-block]');
            if (!blockElement) {
                return;
            }
            const block = this.state.blocks[this.blockIndex(blockElement.getAttribute('data-block-id'))];
            if (!block) {
                return;
            }
            if (target.hasAttribute('data-editor-check-item')) {
                const index = Number(target.getAttribute('data-editor-check-item'));
                block.data.items[index].checked = target.checked;
                this.commit('checklist', true, false);
            }
        }

        handleWorkspaceInput(event) {
            const target = event.target;
            if (target === this.ui.outlineFilter) {
                this.outlineQuery = String(target.value || '').trim();
                this.refreshOutline();
                return;
            }
            if (target.matches('[data-editor-document-title], [name="slug"], [name="excerpt"], [name="meta_title"], [name="meta_description"], [name="seo_title"], [name="seo_description"], [name="seo_keywords"]')) {
                this.markDirty();
                this.scheduleAutosave();
                return;
            }
            if (target.matches('[data-editor-cover-url]')) {
                const value = String(target.value || '').trim();
                const preview = this.workspace.querySelector('[data-editor-cover-preview]');
                const empty = this.workspace.querySelector('[data-editor-cover-empty]');
                if (preview) {
                    preview.src = value;
                    preview.hidden = value === '';
                }
                if (empty) {
                    empty.hidden = value !== '';
                }
                this.markDirty();
                this.scheduleAutosave();
                return;
            }
            if (target.matches('[data-editor-cover-file]')) {
                const file = target.files && target.files[0] ? target.files[0] : null;
                const urlInput = this.workspace.querySelector('[data-editor-cover-url]');
                const preview = this.workspace.querySelector('[data-editor-cover-preview]');
                const empty = this.workspace.querySelector('[data-editor-cover-empty]');
                if (file && urlInput) {
                    urlInput.value = '';
                }
                if (file && preview) {
                    preview.src = URL.createObjectURL(file);
                    preview.hidden = false;
                }
                if (empty) {
                    empty.hidden = Boolean(file);
                }
                this.markDirty();
                this.saveLocalDraftSoon();
                return;
            }
            if (target.hasAttribute('data-editor-video-plan')) {
                const block = this.activeBlock();
                if (!block) {
                    return;
                }
                const planId = Number(target.value);
                const values = new Set((block.data.subscriptionPlanIds || []).map(Number));
                target.checked ? values.add(planId) : values.delete(planId);
                block.data.subscriptionPlanIds = Array.from(values);
                this.commit('video-access', event.type === 'change', false);
                this.refreshBlock(block.id);
                return;
            }
            const setting = target.getAttribute('data-editor-setting');
            if (!setting) {
                if (this.form && this.form.contains(target) && target.name && target.type !== 'hidden') {
                    this.markDirty();
                    this.scheduleAutosave();
                }
                return;
            }
            const block = this.activeBlock();
            if (!block) {
                return;
            }
            if (target.hasAttribute('data-editor-device')) {
                const device = target.getAttribute('data-editor-device');
                const values = new Set(block.settings.hiddenOn || []);
                target.checked ? values.add(device) : values.delete(device);
                block.settings.hiddenOn = Array.from(values);
            } else if (setting === 'table.rows' || setting === 'table.columns') {
                this.resizeTable(block, setting === 'table.rows' ? Number(target.value) : null, setting === 'table.columns' ? Number(target.value) : null);
            } else {
                let value = target.type === 'checkbox' ? target.checked : target.value;
                if (target.type === 'number') {
                    value = Number(value);
                }
                setPath(block, setting, value);
                if (target.hasAttribute('data-editor-social-network')) {
                    const index = Number(target.getAttribute('data-editor-social-network'));
                    const network = (this.config.socialNetworks || []).find(function (option) {
                        return String(option.value) === String(value);
                    });
                    if (network && block.data.items && block.data.items[index]) {
                        block.data.items[index].icon = network.icon;
                        block.data.items[index].label = network.label;
                        const iconInput = this.ui.inspector.querySelector('[data-editor-setting="data.items.' + index + '.icon"]');
                        const labelInput = this.ui.inspector.querySelector('[data-editor-setting="data.items.' + index + '.label"]');
                        const urlInput = this.ui.inspector.querySelector('[data-editor-setting="data.items.' + index + '.url"]');
                        if (iconInput) {
                            iconInput.value = network.icon;
                        }
                        if (labelInput) {
                            labelInput.value = network.label;
                        }
                        if (urlInput) {
                            urlInput.placeholder = network.placeholder || 'https://…';
                            const urlLabel = urlInput.closest('label');
                            const urlLabelText = urlLabel ? urlLabel.querySelector('span') : null;
                            if (urlLabelText) {
                                urlLabelText.textContent = network.value === 'phone'
                                    ? this.label('socialPhone', 'Phone')
                                    : this.label('socialUrl', 'Link');
                            }
                        }
                    }
                }
            }
            if (setting === 'data.subscriptionAccessMode') {
                const plans = this.ui.inspector.querySelector('[data-editor-video-plans]');
                if (plans) {
                    plans.hidden = String(target.value) !== 'plans';
                }
            }
            this.commit('settings', event.type === 'change', false);
            this.refreshBlock(block.id);
            this.refreshOutlineSoon();
        }

        resizeTable(block, requestedRows, requestedColumns) {
            const rows = Math.max(1, Math.min(50, requestedRows || block.data.rows.length || 2));
            const columns = Math.max(1, Math.min(20, requestedColumns || (block.data.rows[0] || []).length || 2));
            const next = [];
            for (let row = 0; row < rows; row += 1) {
                next[row] = [];
                for (let column = 0; column < columns; column += 1) {
                    next[row][column] = block.data.rows[row] && typeof block.data.rows[row][column] !== 'undefined'
                        ? block.data.rows[row][column]
                        : '';
                }
            }
            block.data.rows = next;
        }

        selectBlock(id, event, preserveCaret) {
            if (!id) {
                return;
            }
            const previousActiveId = this.activeId;
            if (event && event.shiftKey && this.selectionAnchorId) {
                const anchorIndex = this.blockIndex(this.selectionAnchorId);
                const targetIndex = this.blockIndex(id);
                if (anchorIndex >= 0 && targetIndex >= 0) {
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    this.selectedIds = new Set(this.state.blocks.slice(start, end + 1).map(function (block) {
                        return block.id;
                    }));
                }
            } else if (event && (event.metaKey || event.ctrlKey)) {
                if (this.selectedIds.has(id) && this.selectedIds.size > 1) {
                    this.selectedIds.delete(id);
                } else {
                    this.selectedIds.add(id);
                }
            } else {
                this.selectedIds = new Set([id]);
                this.selectionAnchorId = id;
            }
            this.activeId = id;
            this.ui.canvas.querySelectorAll('[data-editor-block]').forEach(function (element) {
                const selected = this.selectedIds.has(element.getAttribute('data-block-id'));
                element.classList.toggle('is-selected', selected);
                element.classList.toggle('is-active', element.getAttribute('data-block-id') === id);
            }, this);
            this.refreshOutline();
            this.renderInspector();
            if (previousActiveId !== id) {
                this.activateInspectorTab('block');
                const inspectorPanel = this.ui.inspector
                    ? this.ui.inspector.closest('.fb-editor-workspace__inspector')
                    : null;
                if (inspectorPanel) {
                    inspectorPanel.scrollTop = 0;
                }
            }
            this.refreshStatus();
            if (!preserveCaret) {
                const blockElement = this.ui.canvas.querySelector('[data-block-id="' + id + '"]');
                if (blockElement) {
                    blockElement.focus({ preventScroll: true });
                }
            }
            API.doAction('selection:change', Array.from(this.selectedIds), this);
        }

        handleBlockAction(id, action, anchor) {
            if (!id) {
                return;
            }
            if (action === 'moveUp') {
                this.moveBlock(id, -1);
            } else if (action === 'moveDown') {
                this.moveBlock(id, 1);
            } else if (action === 'duplicate') {
                this.duplicateBlocks([id]);
            } else if (action === 'copy') {
                this.selectBlock(id, null, true);
                this.writeBlocksToClipboard(false);
            } else if (action === 'remove') {
                this.confirmRemoveBlocks([id]);
            } else if (action === 'hide') {
                const block = this.state.blocks[this.blockIndex(id)];
                block.hidden = !block.hidden;
                this.commit('visibility', true, true);
            } else if (action === 'more') {
                this.openContextMenu(id, anchor);
            }
        }

        moveBlock(id, direction) {
            const index = this.blockIndex(id);
            const target = index + direction;
            if (index < 0 || target < 0 || target >= this.state.blocks.length) {
                return;
            }
            const block = this.state.blocks.splice(index, 1)[0];
            this.state.blocks.splice(target, 0, block);
            this.commit('reorder', true, true);
            this.scrollToBlock(id);
        }

        moveSelected(direction) {
            const selected = new Set(this.selectedIds);
            if (!selected.size) {
                return;
            }
            if (direction < 0) {
                for (let index = 1; index < this.state.blocks.length; index += 1) {
                    if (selected.has(this.state.blocks[index].id) && !selected.has(this.state.blocks[index - 1].id)) {
                        const block = this.state.blocks.splice(index, 1)[0];
                        this.state.blocks.splice(index - 1, 0, block);
                    }
                }
            } else {
                for (let index = this.state.blocks.length - 2; index >= 0; index -= 1) {
                    if (selected.has(this.state.blocks[index].id) && !selected.has(this.state.blocks[index + 1].id)) {
                        const block = this.state.blocks.splice(index, 1)[0];
                        this.state.blocks.splice(index + 1, 0, block);
                    }
                }
            }
            this.commit('reorder', true, true);
            this.scrollToBlock(this.activeId);
        }

        duplicateBlocks(ids) {
            const selected = this.state.blocks.filter(function (block) {
                return ids.indexOf(block.id) !== -1;
            });
            if (!selected.length) {
                return;
            }
            const lastIndex = Math.max.apply(null, ids.map(this.blockIndex.bind(this)));
            const copies = selected.map(function (block) {
                const copy = API.clone(block);
                copy.id = importer.blockId();
                return copy;
            });
            this.state.blocks.splice.apply(this.state.blocks, [lastIndex + 1, 0].concat(copies));
            this.activeId = copies[0].id;
            this.selectedIds = new Set(copies.map(function (block) { return block.id; }));
            this.commit('duplicate', true, true);
        }

        removeBlocks(ids) {
            const indexes = ids.map(this.blockIndex.bind(this)).filter(function (index) { return index >= 0; });
            if (!indexes.length) {
                return;
            }
            const fallbackIndex = Math.max(0, Math.min.apply(null, indexes) - 1);
            this.state.blocks = this.state.blocks.filter(function (block) {
                return ids.indexOf(block.id) === -1;
            });
            if (!this.state.blocks.length) {
                this.state.blocks.push(importer.normalizeBlock({ type: 'text', data: { html: '' } }, API));
            }
            this.activeId = this.state.blocks[Math.min(fallbackIndex, this.state.blocks.length - 1)].id;
            this.selectedIds = new Set([this.activeId]);
            this.commit('delete', true, true);
        }

        confirmRemoveBlocks(ids) {
            const blockIds = Array.from(new Set(ids || [])).filter(function (id) {
                return this.blockIndex(id) >= 0;
            }, this);
            if (!blockIds.length) {
                return;
            }
            if (!this.ui.deleteDialog || typeof this.ui.deleteDialog.showModal !== 'function') {
                if (window.confirm(this.label('deleteConfirm', 'Delete selected blocks?'))) {
                    this.removeBlocks(blockIds);
                }
                return;
            }
            this.pendingDeleteIds = blockIds;
            this.ui.deleteDialog.showModal();
        }

        addBlock(type, afterId, data) {
            const blockType = API.getBlockType(type) ? type : 'text';
            const block = importer.normalizeBlock({ type: blockType, data: data || {} }, API);
            const index = afterId ? this.blockIndex(afterId) + 1 : this.state.blocks.length;
            this.state.blocks.splice(Math.max(0, index), 0, block);
            this.activeId = block.id;
            this.selectedIds = new Set([block.id]);
            this.closeSlashMenu();
            this.commit('insert', true, true);
            this.activateInspectorTab('block');
            const inspectorPanel = this.ui.inspector
                ? this.ui.inspector.closest('.fb-editor-workspace__inspector')
                : null;
            if (inspectorPanel) {
                inspectorPanel.scrollTop = 0;
            }
            window.requestAnimationFrame(function () {
                this.scrollToBlock(block.id);
                window.requestAnimationFrame(function () {
                    this.focusBlock(block.id, 'start');
                }.bind(this));
            }.bind(this));
            return block;
        }

        convertActiveBlock(type) {
            const block = this.activeBlock();
            if (!block) {
                return;
            }
            const text = this.blockSummary(block);
            if (type === 'quote') {
                block.type = 'quote';
                block.data = { html: block.data.html || sanitizer.escapeHtml(text), cite: '' };
            } else if (type === 'bulletList' || type === 'orderedList') {
                block.type = type;
                block.data = { items: [block.data.html || sanitizer.escapeHtml(text)] };
            } else if (type === 'checklist') {
                block.type = type;
                block.data = { items: [{ text: block.data.html || sanitizer.escapeHtml(text), checked: false }] };
            } else if (/^h[1-6]$/.test(type)) {
                block.type = 'heading';
                block.data = { level: type, html: block.data.html || sanitizer.escapeHtml(text) };
            } else {
                block.type = 'text';
                block.data = { html: block.data.html || sanitizer.escapeHtml(text) };
            }
            this.commit('convert', true, true);
        }

        setMode(mode) {
            if (['document', 'structure'].indexOf(mode) === -1 || mode === this.mode) {
                return;
            }
            this.mode = mode;
            this.workspace.querySelectorAll('button[data-editor-mode]').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-editor-mode') === mode);
            });
            this.root.setAttribute('data-editor-mode', mode);
            this.workspace.setAttribute('data-editor-mode', mode);
            try {
                window.localStorage.setItem('fireball:editor2:mode', mode);
            } catch (error) {
            }
            API.doAction('mode:change', mode, this);
        }

        openContextMenu(id, anchor) {
            if (!this.ui.contextMenu) {
                return;
            }
            this.activeId = id;
            const block = this.activeBlock();
            this.ui.contextMenu.innerHTML =
                this.contextItem('addAbove', 'ci-plus', this.label('addAbove', 'Add block above'), '') +
                this.contextItem('addBelow', 'ci-plus', this.label('addBelow', 'Add block below'), '') +
                '<hr>' +
                this.contextItem('duplicate', 'ci-copy', this.label('duplicate', 'Duplicate'), '⌘D') +
                this.contextItem('copy', 'ci-clipboard', this.label('copy', 'Copy'), '⌘C') +
                this.contextItem('cut', 'ci-scissors', this.label('cut', 'Cut'), '⌘X') +
                this.contextItem('paste', 'ci-clipboard', this.label('paste', 'Paste'), '⌘V') +
                '<hr>' +
                this.contextItem('moveUp', 'ci-arrow-up', this.label('moveUp', 'Move up'), '⇧↑') +
                this.contextItem('moveDown', 'ci-arrow-down', this.label('moveDown', 'Move down'), '⇧↓') +
                this.contextItem('hide', block && block.hidden ? 'ci-eye' : 'ci-eye-off', block && block.hidden ? this.label('show', 'Show') : this.label('hide', 'Hide'), '') +
                this.contextItem('saveTemplate', 'ci-save', this.label('saveTemplate', 'Save as template'), '') +
                '<hr>' +
                this.contextItem('remove', 'ci-trash', this.label('remove', 'Remove'), '⌫', true);
            this.ui.contextMenu.querySelectorAll('[data-context-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const action = button.getAttribute('data-context-action');
                    this.closeContextMenu();
                    if (action === 'copy' || action === 'cut') {
                        this.writeBlocksToClipboard(action === 'cut');
                    } else if (action === 'paste') {
                        this.pasteBlocksFromClipboard(id);
                    } else if (action === 'addAbove' || action === 'addBelow') {
                        const index = this.blockIndex(id);
                        const afterId = action === 'addBelow'
                            ? id
                            : (index > 0 ? this.state.blocks[index - 1].id : '');
                        const anchor = this.ui.canvas.querySelector('[data-block-id="' + id + '"]');
                        this.openBlockMenu(afterId, anchor || this.ui.canvas);
                    } else if (action === 'saveTemplate') {
                        this.saveSelectionAsTemplate();
                    } else if (action === 'remove') {
                        this.confirmRemoveBlocks(Array.from(this.selectedIds));
                    } else {
                        this.handleBlockAction(id, action, button);
                    }
                }.bind(this));
            }, this);
            this.contextMenuAnchor = anchor;
            if (this.ui.contextMenu.parentElement !== document.body) {
                document.body.appendChild(this.ui.contextMenu);
            }
            this.ui.contextMenu.style.visibility = 'hidden';
            this.ui.contextMenu.hidden = false;
            this.positionContextMenu();
        }

        contextItem(action, icon, label, shortcut, danger) {
            return '<button type="button" class="' + (danger ? 'is-danger' : '') + '" data-context-action="' + escapeAttr(action) + '"><i class="' + iconClass(icon) + '"></i><span>' + escapeAttr(label) + '</span>' + (shortcut ? '<kbd>' + escapeAttr(shortcut) + '</kbd>' : '') + '</button>';
        }

        closeContextMenu() {
            if (this.ui.contextMenu) {
                this.ui.contextMenu.hidden = true;
                this.ui.contextMenu.style.visibility = '';
            }
            this.contextMenuAnchor = null;
        }

        viewportMetrics() {
            const viewport = window.visualViewport;
            return {
                left: viewport ? viewport.offsetLeft : 0,
                top: viewport ? viewport.offsetTop : 0,
                width: viewport ? viewport.width : window.innerWidth,
                height: viewport ? viewport.height : window.innerHeight
            };
        }

        positionContextMenu() {
            const menu = this.ui.contextMenu;
            const anchor = this.contextMenuAnchor;
            if (!menu || menu.hidden || !anchor || !anchor.isConnected) {
                this.closeContextMenu();
                return;
            }

            const viewport = this.viewportMetrics();
            const anchorRect = anchor.getBoundingClientRect();
            if (anchorRect.bottom < viewport.top || anchorRect.top > viewport.top + viewport.height ||
                anchorRect.right < viewport.left || anchorRect.left > viewport.left + viewport.width) {
                this.closeContextMenu();
                return;
            }
            menu.style.maxHeight = Math.max(120, viewport.height - 24) + 'px';
            menu.style.overflowY = 'auto';
            const menuRect = menu.getBoundingClientRect();
            const margin = 12;
            const gap = 6;
            const minLeft = viewport.left + margin;
            const maxLeft = viewport.left + viewport.width - menuRect.width - margin;
            const left = Math.max(minLeft, Math.min(anchorRect.right - menuRect.width, maxLeft));
            const availableBelow = viewport.top + viewport.height - anchorRect.bottom - margin;
            const availableAbove = anchorRect.top - viewport.top - margin;
            const openAbove = menuRect.height > availableBelow && availableAbove > availableBelow;
            const preferredTop = openAbove
                ? anchorRect.top - menuRect.height - gap
                : anchorRect.bottom + gap;
            const minTop = viewport.top + margin;
            const maxTop = viewport.top + viewport.height - menuRect.height - margin;

            menu.style.left = Math.round(left) + 'px';
            menu.style.top = Math.round(Math.max(minTop, Math.min(preferredTop, maxTop))) + 'px';
            menu.style.visibility = '';
            menu.classList.toggle('is-open-up', openAbove);
        }

        handleFloatingUiViewportChange() {
            if (!this.ui.contextMenu || this.ui.contextMenu.hidden || !this.contextMenuAnchor) {
                return;
            }
            window.cancelAnimationFrame(this.floatingUiFrame);
            this.floatingUiFrame = window.requestAnimationFrame(function () {
                this.positionContextMenu();
            }.bind(this));
        }

        openBlockMenu(afterId, anchor) {
            this.insertionAfter = afterId || '';
            this.slashBlockId = '';
            this.ui.slashMenu.classList.add('is-centered-picker');
            this.ui.slashMenu.setAttribute('role', 'dialog');
            this.ui.slashMenu.setAttribute('aria-modal', 'true');
            this.ui.slashMenu.style.left = '';
            this.ui.slashMenu.style.top = '';
            this.renderSlashMenu('');
            this.ui.slashMenu.hidden = false;
            if (this.ui.slashSearch) {
                this.ui.slashSearch.value = '';
                window.requestAnimationFrame(function () { this.ui.slashSearch.focus(); }.bind(this));
            }
        }

        openSlashForBlock(blockId, editable, query) {
            this.insertionAfter = blockId;
            this.slashBlockId = blockId;
            this.ui.slashMenu.classList.remove('is-centered-picker');
            this.ui.slashMenu.removeAttribute('role');
            this.ui.slashMenu.removeAttribute('aria-modal');
            this.renderSlashMenu(query || '');
            this.ui.slashMenu.hidden = false;
            const selection = window.getSelection();
            let rect = editable.getBoundingClientRect();
            if (selection && selection.rangeCount) {
                const selectionRect = selection.getRangeAt(0).getBoundingClientRect();
                if (selectionRect.width || selectionRect.height) {
                    rect = selectionRect;
                }
            }
            this.ui.slashMenu.style.left = Math.max(12, Math.min(window.innerWidth - 340, rect.left)) + 'px';
            this.ui.slashMenu.style.top = Math.max(12, Math.min(window.innerHeight - 440, rect.bottom + 8)) + 'px';
        }

        renderSlashMenu(query) {
            if (!this.ui.slashList) {
                return;
            }
            const needle = String(query || '').toLowerCase().trim();
            const types = API.getBlockTypes().filter(function (definition) {
                return !needle || String(definition.title || definition.machine_name).toLowerCase().indexOf(needle) !== -1;
            });
            this.slashIndex = types.length ? Math.max(0, Math.min(this.slashIndex, types.length - 1)) : -1;
            this.ui.slashList.innerHTML = types.map(function (definition, index) {
                return '<button type="button" class="' + (index === this.slashIndex ? 'is-active' : '') + '" data-slash-type="' + escapeAttr(definition.machine_name) + '"><span><i class="' + iconClass(definition.icon) + '"></i></span><div><strong>' + escapeAttr(definition.title || definition.machine_name) + '</strong><small>' + escapeAttr(this.blockDescription(definition.machine_name)) + '</small></div></button>';
            }, this).join('') || '<p class="fb-editor2__menu-empty">' + escapeAttr(this.label('noCommands', 'No commands found')) + '</p>';
            this.ui.slashList.querySelectorAll('[data-slash-type]').forEach(function (button) {
                button.addEventListener('click', function () {
                    this.chooseSlashType(button.getAttribute('data-slash-type'));
                }.bind(this));
            }, this);
        }

        blockDescription(type) {
            const definition = API.getBlockType(type) || {};
            return definition.description || this.label('contentBlock', 'Content block');
        }

        chooseSlashType(type) {
            let after = this.insertionAfter;
            if (this.slashBlockId) {
                const slashBlock = this.state.blocks[this.blockIndex(this.slashBlockId)];
                if (slashBlock && stripHtml(slashBlock.data.html || '').charAt(0) === '/') {
                    const index = this.blockIndex(slashBlock.id);
                    this.state.blocks.splice(index, 1);
                    after = index > 0 ? this.state.blocks[index - 1].id : '';
                }
            }
            this.addBlock(type, after);
        }

        closeSlashMenu() {
            if (this.ui.slashMenu) {
                this.ui.slashMenu.hidden = true;
                this.ui.slashMenu.classList.remove('is-centered-picker');
                this.ui.slashMenu.removeAttribute('role');
                this.ui.slashMenu.removeAttribute('aria-modal');
            }
            this.slashBlockId = '';
        }

        positionMenu(menu, anchor) {
            if (!menu || !anchor) {
                return;
            }
            const rect = anchor.getBoundingClientRect();
            menu.style.left = Math.max(12, Math.min(window.innerWidth - 340, rect.left)) + 'px';
            menu.style.top = Math.max(12, Math.min(window.innerHeight - 440, rect.bottom + 8)) + 'px';
        }

        handleSelectionChange() {
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || selection.isCollapsed) {
                if (this.ui.selectionToolbar) {
                    this.ui.selectionToolbar.hidden = true;
                }
                return;
            }
            const range = selection.getRangeAt(0);
            const common = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
                ? range.commonAncestorContainer
                : range.commonAncestorContainer.parentElement;
            const editable = common && common.closest ? common.closest('[data-editor-rich], [data-editor-list-item], [data-editor-check-text], [data-editor-table-cell], [data-editor-faq-answer]') : null;
            if (!editable || !this.root.contains(editable)) {
                if (this.ui.selectionToolbar) {
                    this.ui.selectionToolbar.hidden = true;
                }
                return;
            }
            this.savedRange = range.cloneRange();
            const blockElement = editable.closest('[data-editor-block]');
            if (blockElement) {
                this.selectBlock(blockElement.getAttribute('data-block-id'), null, true);
            }
            if (this.ui.selectionToolbar) {
                const rect = range.getBoundingClientRect();
                this.ui.selectionToolbar.style.left = Math.max(8, Math.min(window.innerWidth - 250, rect.left + rect.width / 2 - 110)) + 'px';
                this.ui.selectionToolbar.style.top = Math.max(8, rect.top - 48) + 'px';
                this.ui.selectionToolbar.hidden = false;
            }
        }

        restoreSelection() {
            if (!this.savedRange) {
                return false;
            }
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(this.savedRange);
            return true;
        }

        currentEditable() {
            const selection = window.getSelection();
            if (selection && selection.rangeCount) {
                let node = selection.anchorNode;
                if (node && node.nodeType !== Node.ELEMENT_NODE) {
                    node = node.parentElement;
                }
                const editable = node && node.closest ? node.closest('[contenteditable="true"]') : null;
                if (editable && this.root.contains(editable)) {
                    return editable;
                }
            }
            const active = document.activeElement;
            return active && active.matches && active.matches('[contenteditable="true"]') && this.root.contains(active) ? active : null;
        }

        applyInline(tagName) {
            this.restoreSelection();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || selection.isCollapsed) {
                return;
            }
            const range = selection.getRangeAt(0);
            const editable = this.currentEditable();
            if (!editable) {
                return;
            }
            const wrapper = document.createElement(tagName);
            try {
                wrapper.appendChild(range.extractContents());
                range.insertNode(wrapper);
                selection.removeAllRanges();
                const next = document.createRange();
                next.selectNodeContents(wrapper);
                selection.addRange(next);
                this.savedRange = next.cloneRange();
                this.syncEditableToBlock(editable);
                this.commit('format', true, false);
            } catch (error) {
            }
        }

        applyInlineStyle(property, value) {
            this.restoreSelection();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || selection.isCollapsed) {
                return;
            }
            const range = selection.getRangeAt(0);
            const editable = this.currentEditable();
            if (!editable) {
                return;
            }
            const wrapper = document.createElement('span');
            wrapper.style[property] = String(value || '');
            wrapper.appendChild(range.extractContents());
            range.insertNode(wrapper);
            const next = document.createRange();
            next.selectNodeContents(wrapper);
            selection.removeAllRanges();
            selection.addRange(next);
            this.savedRange = next.cloneRange();
            this.syncEditableToBlock(editable);
            this.commit('format', true, false);
        }

        runToolbarCommand(command) {
            if (command === 'link') {
                this.restoreSelection();
                const url = window.prompt(this.label('linkPrompt', 'Enter URL'), 'https://');
                if (!url || !sanitizer.safeUrl(url, false)) {
                    return;
                }
                const selection = window.getSelection();
                if (!selection || !selection.rangeCount || selection.isCollapsed) {
                    return;
                }
                const range = selection.getRangeAt(0);
                const link = document.createElement('a');
                link.href = sanitizer.safeUrl(url, false);
                link.rel = 'noopener noreferrer';
                link.appendChild(range.extractContents());
                range.insertNode(link);
                const editable = link.closest('[contenteditable="true"]');
                this.syncEditableToBlock(editable);
                this.commit('link', true, false);
                return;
            }
            if (command === 'unlink') {
                this.restoreSelection();
                const selection = window.getSelection();
                let node = selection && selection.anchorNode;
                if (node && node.nodeType !== Node.ELEMENT_NODE) {
                    node = node.parentElement;
                }
                const link = node && node.closest ? node.closest('a') : null;
                if (link && this.root.contains(link)) {
                    const editable = link.closest('[contenteditable="true"]');
                    link.replaceWith.apply(link, Array.from(link.childNodes));
                    this.syncEditableToBlock(editable);
                    this.commit('unlink', true, false);
                }
                return;
            }
            if (command === 'clear') {
                const editable = this.currentEditable();
                if (editable) {
                    editable.innerHTML = sanitizer.escapeHtml(editable.textContent || '');
                    this.syncEditableToBlock(editable);
                    this.commit('clear-format', true, false);
                }
                return;
            }
            if (command === 'indent' || command === 'outdent') {
                const block = this.activeBlock();
                if (block) {
                    block.settings.indent = Math.max(0, Math.min(160, Number(block.settings.indent || 0) + (command === 'indent' ? 24 : -24)));
                    this.commit('indent', true, true);
                }
                return;
            }
            if (command === 'search') {
                this.toggleSearchPanel(true);
                return;
            }
            if (command === 'commandPalette') {
                this.openCommandPalette();
            }
        }

        syncEditableToBlock(editable) {
            if (!editable) {
                return;
            }
            const blockElement = editable.closest('[data-editor-block]');
            const block = blockElement ? this.state.blocks[this.blockIndex(blockElement.getAttribute('data-block-id'))] : null;
            if (!block) {
                return;
            }
            const field = editable.getAttribute('data-editor-field');
            if (field) {
                setPath(block, field, editable.innerHTML);
            }
            if (editable.hasAttribute('data-editor-list-item')) {
                block.data.items[Number(editable.getAttribute('data-editor-list-item'))] = editable.innerHTML;
            }
            if (editable.hasAttribute('data-editor-check-text')) {
                block.data.items[Number(editable.getAttribute('data-editor-check-text'))].text = editable.innerHTML;
            }
        }

        handleRootKeydown(event) {
            const commandDialogOpen = this.ui.commandDialog
                && (this.ui.commandDialog.open || this.ui.commandDialog.hasAttribute('open'));
            if (commandDialogOpen && event.target.closest('[data-editor-command-dialog]')) {
                const commandButtons = Array.from(this.ui.commandList.querySelectorAll('[data-editor-run-command]'));
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const delta = event.key === 'ArrowDown' ? 1 : -1;
                    this.commandPaletteIndex = commandButtons.length
                        ? (Number(this.commandPaletteIndex || 0) + delta + commandButtons.length) % commandButtons.length
                        : -1;
                    this.syncCommandPaletteSelection();
                    return;
                }
                if (event.key === 'Enter' && event.target === this.ui.commandSearch) {
                    event.preventDefault();
                    this.activateCommandPaletteSelection();
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeCommandPalette();
                    return;
                }
            }

            if (this.ui.slashMenu && !this.ui.slashMenu.hidden) {
                const buttons = Array.from(this.ui.slashList.querySelectorAll('[data-slash-type]'));
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const delta = event.key === 'ArrowDown' ? 1 : -1;
                    this.slashIndex = buttons.length ? (this.slashIndex + delta + buttons.length) % buttons.length : -1;
                    buttons.forEach(function (button, index) {
                        button.classList.toggle('is-active', index === this.slashIndex);
                    }, this);
                    if (buttons[this.slashIndex]) {
                        buttons[this.slashIndex].scrollIntoView({ block: 'nearest' });
                    }
                    return;
                }
                if (event.key === 'Enter' && this.slashIndex >= 0 && buttons[this.slashIndex]) {
                    event.preventDefault();
                    this.chooseSlashType(buttons[this.slashIndex].getAttribute('data-slash-type'));
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeSlashMenu();
                    return;
                }
            }

            const editable = event.target.closest('[contenteditable="true"]');
            const blockElement = event.target.closest('[data-editor-block]');
            const block = blockElement ? this.state.blocks[this.blockIndex(blockElement.getAttribute('data-block-id'))] : null;
            if (!editable || !block) {
                return;
            }

            if (event.key === 'Enter' && !event.shiftKey) {
                if (editable.hasAttribute('data-editor-list-item')) {
                    event.preventDefault();
                    const index = Number(editable.getAttribute('data-editor-list-item'));
                    if (stripHtml(editable.innerHTML) === '') {
                        this.exitListAt(block, index);
                        return;
                    }
                    const split = this.splitEditableHtmlAtSelection(editable);
                    block.data.items[index] = split ? split.beforeHtml : sanitizer.sanitizeHtml(editable.innerHTML);
                    block.data.items.splice(index + 1, 0, split ? split.afterHtml : '');
                    this.commit('list-item', true, true);
                    this.focusBlock(block.id, 'list:' + (index + 1));
                    return;
                }
                if (editable.hasAttribute('data-editor-check-text')) {
                    event.preventDefault();
                    const index = Number(editable.getAttribute('data-editor-check-text'));
                    if (stripHtml(editable.innerHTML) === '') {
                        this.exitListAt(block, index);
                        return;
                    }
                    const split = this.splitEditableHtmlAtSelection(editable);
                    block.data.items[index].text = split ? split.beforeHtml : sanitizer.sanitizeHtml(editable.innerHTML);
                    block.data.items.splice(index + 1, 0, { text: split ? split.afterHtml : '', checked: false });
                    this.commit('check-item', true, true);
                    this.focusBlock(block.id, 'check:' + (index + 1));
                    return;
                }
                if (TEXT_TYPES.has(block.type) && editable.hasAttribute('data-editor-rich')) {
                    event.preventDefault();
                    this.splitRichBlock(block, editable);
                    return;
                }
            }
            if (event.key === 'Backspace' && stripHtml(editable.innerHTML) === '') {
                if (editable.hasAttribute('data-editor-list-item') || editable.hasAttribute('data-editor-check-text')) {
                    event.preventDefault();
                    const itemIndex = Number(editable.getAttribute('data-editor-list-item') || editable.getAttribute('data-editor-check-text'));
                    this.backspaceEmptyListItem(block, itemIndex);
                    return;
                }
                const index = this.blockIndex(block.id);
                if (index > 0) {
                    event.preventDefault();
                    const previous = this.state.blocks[index - 1];
                    this.state.blocks.splice(index, 1);
                    this.activeId = previous.id;
                    this.selectedIds = new Set([previous.id]);
                    this.commit('merge', true, true);
                    this.focusBlock(previous.id, 'end');
                }
            }
            if (event.key === 'Tab') {
                event.preventDefault();
                block.settings.indent = Math.max(0, Math.min(160, Number(block.settings.indent || 0) + (event.shiftKey ? -24 : 24)));
                this.commit('indent', true, true);
            }
        }

        exitListAt(block, itemIndex) {
            const blockIndex = this.blockIndex(block.id);
            const listType = block.type;
            const items = Array.isArray(block.data.items) ? block.data.items.slice() : [];
            const beforeItems = items.slice(0, itemIndex);
            const afterItems = items.slice(itemIndex + 1);
            const textBlock = importer.normalizeBlock({ type: 'text', data: { html: '' } }, API);
            let textIndex = blockIndex;

            if (beforeItems.length) {
                block.data.items = beforeItems;
                textIndex = blockIndex + 1;
                this.state.blocks.splice(textIndex, 0, textBlock);
            } else {
                textBlock.id = block.id;
                textBlock.settings = API.clone(block.settings || {});
                this.state.blocks.splice(blockIndex, 1, textBlock);
            }

            if (afterItems.length) {
                const trailingList = importer.normalizeBlock({
                    type: listType,
                    data: Object.assign({}, block.data || {}, { items: afterItems })
                }, API);
                this.state.blocks.splice(textIndex + 1, 0, trailingList);
            }

            this.activeId = textBlock.id;
            this.selectedIds = new Set([textBlock.id]);
            this.selectionAnchorId = textBlock.id;
            this.commit('list-exit', true, true);
            this.focusBlock(textBlock.id, 'start');
        }

        backspaceEmptyListItem(block, itemIndex) {
            const items = Array.isArray(block.data.items) ? block.data.items : [];
            const caretPrefix = block.type === 'checklist' ? 'check:' : 'list:';
            if (items.length > 1) {
                items.splice(itemIndex, 1);
                const nextIndex = Math.max(0, itemIndex - 1);
                this.commit('list-remove-empty', true, true);
                this.focusBlock(block.id, caretPrefix + nextIndex);
                return;
            }

            block.type = 'text';
            block.data = { html: '' };
            this.activeId = block.id;
            this.selectedIds = new Set([block.id]);
            this.selectionAnchorId = block.id;
            this.commit('list-to-text', true, true);
            this.focusBlock(block.id, 'start');
        }

        splitRichBlock(block, editable) {
            const selection = window.getSelection();
            let afterHtml = '';
            if (selection && selection.rangeCount && editable.contains(selection.anchorNode)) {
                const range = selection.getRangeAt(0);
                const after = range.cloneRange();
                after.selectNodeContents(editable);
                after.setStart(range.endContainer, range.endOffset);
                const container = document.createElement('div');
                container.appendChild(after.extractContents());
                afterHtml = sanitizer.sanitizeHtml(container.innerHTML);
            }
            block.data.html = sanitizer.sanitizeHtml(editable.innerHTML);
            const next = importer.normalizeBlock({
                type: 'text',
                data: { html: afterHtml }
            }, API);
            const index = this.blockIndex(block.id);
            this.state.blocks.splice(index + 1, 0, next);
            this.activeId = next.id;
            this.selectedIds = new Set([next.id]);
            this.commit('split', true, true);
            this.focusBlock(next.id, 'start');
        }

        focusBlock(id, caret) {
            const element = this.ui.canvas.querySelector('[data-block-id="' + id + '"]');
            if (!element) {
                return;
            }
            let editable = element.querySelector('[contenteditable="true"], textarea, input');
            if (String(caret).indexOf('list:') === 0) {
                editable = element.querySelector('[data-editor-list-item="' + String(caret).split(':')[1] + '"]');
            }
            if (String(caret).indexOf('check:') === 0) {
                editable = element.querySelector('[data-editor-check-text="' + String(caret).split(':')[1] + '"]');
            }
            if (!editable) {
                element.focus({ preventScroll: true });
                return;
            }
            editable.focus({ preventScroll: true });
            if (editable.matches('[contenteditable="true"]')) {
                const range = document.createRange();
                range.selectNodeContents(editable);
                range.collapse(caret === 'start');
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                this.savedRange = range.cloneRange();
            } else if (typeof editable.setSelectionRange === 'function') {
                const position = caret === 'start' ? 0 : editable.value.length;
                editable.setSelectionRange(position, position);
            }
        }

        scrollToBlock(id) {
            const element = this.ui.canvas.querySelector('[data-block-id="' + id + '"]');
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        handleGlobalKeydown(event) {
            if (this.destroyed || !this.workspace || (!this.workspace.contains(event.target) && !this.root.contains(document.activeElement))) {
                return;
            }
            const modifier = event.metaKey || event.ctrlKey;
            const key = event.key.toLowerCase();
            const editableTarget = event.target.closest('input, textarea, [contenteditable="true"]');
            if (modifier && event.shiftKey && key === 'p') {
                event.preventDefault();
                this.openCommandPalette();
                return;
            }
            if (modifier && ['b', 'i', 'u'].indexOf(key) !== -1 && event.target.closest('[contenteditable="true"]')) {
                event.preventDefault();
                this.applyInline({ b: 'strong', i: 'em', u: 'u' }[key]);
                return;
            }
            if (modifier && key === 'z') {
                event.preventDefault();
                event.shiftKey ? this.redo() : this.undo();
                return;
            }
            if (modifier && key === 'y') {
                event.preventDefault();
                this.redo();
                return;
            }
            if (modifier && key === 'k') {
                event.preventDefault();
                this.runToolbarCommand('link');
                return;
            }
            if (modifier && key === 'f') {
                event.preventDefault();
                this.toggleSearchPanel(true);
                if (this.ui.searchInput) {
                    this.ui.searchInput.focus();
                }
                return;
            }
            if (modifier && key === 'h') {
                event.preventDefault();
                this.toggleSearchPanel(true);
                if (this.ui.replaceInput) {
                    this.ui.replaceInput.focus();
                }
                return;
            }
            if (modifier && key === '/') {
                event.preventDefault();
                const activeElement = this.ui.canvas.querySelector('[data-block-id="' + this.activeId + '"]');
                this.openBlockMenu(this.activeId, activeElement || this.ui.canvas);
                return;
            }
            if (modifier && key === 's') {
                event.preventDefault();
                this.autosave(true);
                return;
            }
            if (modifier && key === 'enter') {
                event.preventDefault();
                this.autosave(true);
                return;
            }
            if (modifier && key === 'a') {
                const now = Date.now();
                if (!editableTarget || now - this.lastSelectAllAt < 900) {
                    event.preventDefault();
                    this.selectAllBlocks();
                }
                this.lastSelectAllAt = now;
                return;
            }
            if (modifier && key === 'd' && !editableTarget) {
                event.preventDefault();
                this.duplicateBlocks(Array.from(this.selectedIds));
                return;
            }
            if ((event.altKey || event.shiftKey) && event.key === 'ArrowUp' && !editableTarget) {
                event.preventDefault();
                this.moveSelected(-1);
                return;
            }
            if ((event.altKey || event.shiftKey) && event.key === 'ArrowDown' && !editableTarget) {
                event.preventDefault();
                this.moveSelected(1);
                return;
            }
            if ((event.key === 'Delete' || event.key === 'Backspace') && !editableTarget) {
                event.preventDefault();
                this.confirmRemoveBlocks(Array.from(this.selectedIds));
            }
            if (event.key === 'Escape') {
                this.closeSlashMenu();
                this.closeContextMenu();
                this.toggleSearchPanel(false);
                this.selectedIds = new Set(this.activeId ? [this.activeId] : []);
                this.selectBlock(this.activeId, null, true);
            }
        }

        selectAllBlocks() {
            this.selectedIds = new Set(this.state.blocks.map(function (block) {
                return block.id;
            }));
            this.ui.canvas.querySelectorAll('[data-editor-block]').forEach(function (element) {
                element.classList.add('is-selected');
                element.classList.toggle('is-active', element.getAttribute('data-block-id') === this.activeId);
            }, this);
            this.refreshOutline();
            this.refreshStatus();
            API.doAction('selection:change', Array.from(this.selectedIds), this);
        }

        undo() {
            const previous = this.history.undo();
            if (!previous) {
                return;
            }
            this.state = importer.normalizeState(previous, API);
            this.activeId = this.state.blocks.some(function (block) { return block.id === this.activeId; }, this)
                ? this.activeId
                : (this.state.blocks[0] ? this.state.blocks[0].id : '');
            this.selectedIds = new Set(this.activeId ? [this.activeId] : []);
            this.markDirty();
            this.syncTextarea();
            this.renderAll({ preserveFocus: true });
            this.scheduleAutosave();
        }

        redo() {
            const next = this.history.redo();
            if (!next) {
                return;
            }
            this.state = importer.normalizeState(next, API);
            this.activeId = this.state.blocks.some(function (block) { return block.id === this.activeId; }, this)
                ? this.activeId
                : (this.state.blocks[0] ? this.state.blocks[0].id : '');
            this.selectedIds = new Set(this.activeId ? [this.activeId] : []);
            this.markDirty();
            this.syncTextarea();
            this.renderAll({ preserveFocus: true });
            this.scheduleAutosave();
        }

        commit(label, forceHistory, rerender) {
            this.history.push(this.historyState(), label || 'change', Boolean(forceHistory));
            this.markDirty();
            this.syncTextarea();
            this.saveLocalDraftSoon();
            this.scheduleAutosave();
            if (rerender) {
                this.renderAll({ preserveFocus: true });
            } else {
                this.updateHistoryControls();
            }
            this.refreshStatusSoon();
            this.refreshSplitPreviewSoon();
            API.doAction('document:change', this.historyState(), this);
        }

        markDirty() {
            this.dirty = true;
            if (this.workspace) {
                this.workspace.classList.add('is-dirty');
            }
            this.setSaveState('saving', this.label('saving', 'Saving…'));
        }

        handlePaste(event) {
            const blockElement = event.target.closest('[data-editor-block]');
            if (!blockElement || !event.clipboardData) {
                return;
            }
            const block = this.state.blocks[this.blockIndex(blockElement.getAttribute('data-block-id'))];
            if (!block) {
                return;
            }
            if ((block.type === 'html' || block.type === 'code') && event.target.matches('textarea[data-editor-field]')) {
                return;
            }
            const internal = event.clipboardData.getData(INTERNAL_MIME);
            const html = String(event.clipboardData.getData('text/html') || '');
            const plain = String(event.clipboardData.getData('text/plain') || '');
            const editable = event.target.closest('[contenteditable="true"]');
            const isStructuredPlain = importer.looksLikeHtmlText(plain)
                || importer.looksLikeMarkdown(plain);
            const isSimpleInline = !internal
                && !html
                && plain.indexOf('\n') === -1
                && !isStructuredPlain
                && Boolean(editable);
            const isRichInline = !internal
                && Boolean(html)
                && Boolean(editable)
                && importer.isInlineHtmlFragment(html);

            event.preventDefault();
            if (isSimpleInline) {
                this.insertInlineText(plain);
                this.syncEditableToBlock(editable);
                this.commit('paste-inline', true, false);
                return;
            }
            if (isRichInline && this.insertInlineHtml(html, editable)) {
                this.syncEditableToBlock(editable);
                this.commit('paste-inline-rich', true, false);
                return;
            }

            const blocks = importer.clipboardToBlocks(event.clipboardData, API);
            if (!blocks.length) {
                return;
            }
            const blockIndex = this.blockIndex(block.id);
            let insertionIndex = blockIndex + 1;
            let trailingBlock = null;
            const split = TEXT_TYPES.has(block.type) && editable && editable.hasAttribute('data-editor-rich')
                ? this.splitEditableHtmlAtSelection(editable)
                : null;

            if (split) {
                const hasBefore = stripHtml(split.beforeHtml) !== ''
                    || (block.type === 'quote' && String(block.data.cite || '').trim() !== '');
                const hasAfter = stripHtml(split.afterHtml) !== '';
                if (hasBefore) {
                    block.data.html = split.beforeHtml;
                } else {
                    this.state.blocks.splice(blockIndex, 1);
                    insertionIndex = blockIndex;
                }
                if (hasAfter) {
                    trailingBlock = importer.normalizeBlock({
                        type: 'text',
                        data: { html: split.afterHtml }
                    }, API);
                }
            } else if (TEXT_TYPES.has(block.type) && stripHtml(block.data.html || '') === '') {
                insertionIndex = blockIndex;
                this.state.blocks.splice(blockIndex, 1);
            }

            const insertedBlocks = trailingBlock ? blocks.concat([trailingBlock]) : blocks;
            this.state.blocks.splice.apply(this.state.blocks, [insertionIndex, 0].concat(insertedBlocks));
            this.activeId = blocks[0].id;
            this.selectedIds = new Set(blocks.map(function (item) { return item.id; }));
            this.commit('smart-paste', true, true);
            this.scrollToBlock(blocks[0].id);
            API.doAction('paste:blocks', blocks, this);
        }

        splitEditableHtmlAtSelection(editable) {
            const selection = window.getSelection();
            if (!editable || !selection || !selection.rangeCount) {
                return null;
            }
            const range = selection.getRangeAt(0);
            if (!editable.contains(range.startContainer) || !editable.contains(range.endContainer)) {
                return null;
            }

            const beforeRange = range.cloneRange();
            beforeRange.selectNodeContents(editable);
            beforeRange.setEnd(range.startContainer, range.startOffset);
            const beforeContainer = document.createElement('div');
            beforeContainer.appendChild(beforeRange.cloneContents());

            const afterRange = range.cloneRange();
            afterRange.selectNodeContents(editable);
            afterRange.setStart(range.endContainer, range.endOffset);
            const afterContainer = document.createElement('div');
            afterContainer.appendChild(afterRange.cloneContents());

            return {
                beforeHtml: sanitizer.sanitizeHtml(beforeContainer.innerHTML),
                afterHtml: sanitizer.sanitizeHtml(afterContainer.innerHTML)
            };
        }

        insertInlineHtml(html, editable) {
            const safeHtml = sanitizer.sanitizeHtml(String(html || ''));
            const selection = window.getSelection();
            if (!safeHtml || !editable || !selection || !selection.rangeCount) {
                return false;
            }
            const range = selection.getRangeAt(0);
            if (!editable.contains(range.startContainer) || !editable.contains(range.endContainer)) {
                return false;
            }

            range.deleteContents();
            const fragment = range.createContextualFragment(safeHtml);
            const lastNode = fragment.lastChild;
            range.insertNode(fragment);
            if (lastNode) {
                range.setStartAfter(lastNode);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
                this.savedRange = range.cloneRange();
            }
            return true;
        }

        insertInlineText(text) {
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) {
                return;
            }
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const node = document.createTextNode(String(text || ''));
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            this.savedRange = range.cloneRange();
        }

        selectedBlocks() {
            return this.state.blocks.filter(function (block) {
                return this.selectedIds.has(block.id);
            }, this);
        }

        handleCopy(event) {
            const blocks = this.selectedBlocks();
            const targetIsEditing = Boolean(event.target.closest('input, textarea, [contenteditable="true"]'));
            const selection = window.getSelection();
            if (!blocks.length || (targetIsEditing && blocks.length === 1 && selection && !selection.isCollapsed)) {
                return;
            }
            event.preventDefault();
            this.populateClipboard(event.clipboardData, blocks);
        }

        handleCut(event) {
            const blocks = this.selectedBlocks();
            const targetIsEditing = Boolean(event.target.closest('input, textarea, [contenteditable="true"]'));
            const selection = window.getSelection();
            if (!blocks.length || (targetIsEditing && blocks.length === 1 && selection && !selection.isCollapsed)) {
                return;
            }
            event.preventDefault();
            this.populateClipboard(event.clipboardData, blocks);
            this.removeBlocks(blocks.map(function (block) { return block.id; }));
        }

        populateClipboard(clipboard, blocks) {
            if (!clipboard) {
                return;
            }
            const payload = JSON.stringify({ version: 2, blocks: blocks });
            clipboard.setData(INTERNAL_MIME, payload);
            clipboard.setData('text/html', blocks.map(this.serializePublicBlock.bind(this)).join(''));
            clipboard.setData('text/plain', blocks.map(this.blockSummary.bind(this)).join('\n\n'));
        }

        writeBlocksToClipboard(cut) {
            const blocks = this.selectedBlocks();
            if (!blocks.length) {
                return;
            }
            const payload = JSON.stringify({ version: 2, blocks: blocks });
            const html = blocks.map(this.serializePublicBlock.bind(this)).join('');
            const plain = blocks.map(this.blockSummary.bind(this)).join('\n\n');
            const complete = function () {
                if (cut) {
                    this.removeBlocks(blocks.map(function (block) { return block.id; }));
                }
            }.bind(this);

            if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
                const item = new ClipboardItem({
                    [INTERNAL_MIME]: new Blob([payload], { type: INTERNAL_MIME }),
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' })
                });
                navigator.clipboard.write([item]).then(complete).catch(function () {
                    navigator.clipboard.writeText(plain).then(complete).catch(function () {});
                });
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(plain).then(complete).catch(function () {});
            }
        }

        async pasteBlocksFromClipboard(afterId) {
            if (!navigator.clipboard) {
                return;
            }
            let blocks = [];
            try {
                if (typeof navigator.clipboard.read === 'function') {
                    const items = await navigator.clipboard.read();
                    let internal = '';
                    let html = '';
                    let plain = '';
                    for (const item of items) {
                        if (!internal && item.types.indexOf(INTERNAL_MIME) !== -1) {
                            internal = await (await item.getType(INTERNAL_MIME)).text();
                        }
                        if (!html && item.types.indexOf('text/html') !== -1) {
                            html = await (await item.getType('text/html')).text();
                        }
                        if (!plain && item.types.indexOf('text/plain') !== -1) {
                            plain = await (await item.getType('text/plain')).text();
                        }
                    }
                    blocks = importer.clipboardToBlocks({
                        getData: function (type) {
                            return type === INTERNAL_MIME ? internal : (type === 'text/html' ? html : plain);
                        }
                    }, API);
                } else if (typeof navigator.clipboard.readText === 'function') {
                    blocks = importer.markdownToBlocks(await navigator.clipboard.readText(), API);
                }
            } catch (error) {
                API.doAction('editor:error', { phase: 'clipboard-read', message: String(error && error.message || error) }, this);
                return;
            }
            if (!blocks.length) {
                return;
            }
            const index = afterId ? this.blockIndex(afterId) + 1 : this.state.blocks.length;
            this.state.blocks.splice.apply(this.state.blocks, [Math.max(0, index), 0].concat(blocks));
            this.activeId = blocks[0].id;
            this.selectedIds = new Set(blocks.map(function (block) { return block.id; }));
            this.commit('paste-blocks', true, true);
        }

        saveSelectionAsTemplate() {
            const blocks = this.selectedBlocks();
            if (!blocks.length) {
                return;
            }
            const name = window.prompt(this.label('templateName', 'Template name'));
            if (!name) {
                return;
            }
            try {
                const key = 'fireball:editor2:templates';
                const templates = JSON.parse(window.localStorage.getItem(key) || '[]');
                templates.unshift({
                    id: 'template_' + Date.now().toString(36),
                    name: String(name).slice(0, 120),
                    createdAt: Date.now(),
                    blocks: API.clone(blocks)
                });
                window.localStorage.setItem(key, JSON.stringify(templates.slice(0, 50)));
                API.doAction('template:saved', templates[0], this);
            } catch (error) {
                API.doAction('editor:error', { phase: 'template-save', message: String(error && error.message || error) }, this);
            }
        }

        handleDragStart(event) {
            const handle = event.target.closest('[data-editor-drag-handle]');
            const block = handle ? handle.closest('[data-editor-block]') : null;
            if (!block || !event.dataTransfer) {
                event.preventDefault();
                return;
            }
            this.draggedId = block.getAttribute('data-block-id');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/x-fireball-block-id', this.draggedId);
            block.classList.add('is-dragging');
        }

        handleDragOver(event) {
            if (!this.draggedId) {
                return;
            }
            this.autoScrollWhileDragging(event.clientY);
            const target = event.target.closest('[data-editor-block]');
            if (!target || target.getAttribute('data-block-id') === this.draggedId) {
                return;
            }
            event.preventDefault();
            this.ui.canvas.querySelectorAll('.is-drag-target-before, .is-drag-target-after').forEach(function (element) {
                element.classList.remove('is-drag-target-before', 'is-drag-target-after');
            });
            const rect = target.getBoundingClientRect();
            target.classList.add(event.clientY < rect.top + rect.height / 2 ? 'is-drag-target-before' : 'is-drag-target-after');
        }

        handleDrop(event) {
            if (!this.draggedId) {
                return;
            }
            const target = event.target.closest('[data-editor-block]');
            if (!target) {
                return;
            }
            event.preventDefault();
            const targetId = target.getAttribute('data-block-id');
            if (targetId === this.draggedId) {
                this.handleDragEnd();
                return;
            }
            const sourceIndex = this.blockIndex(this.draggedId);
            let targetIndex = this.blockIndex(targetId);
            const rect = target.getBoundingClientRect();
            const after = event.clientY >= rect.top + rect.height / 2;
            const block = this.state.blocks.splice(sourceIndex, 1)[0];
            targetIndex = this.blockIndex(targetId) + (after ? 1 : 0);
            this.state.blocks.splice(targetIndex, 0, block);
            this.activeId = block.id;
            this.selectedIds = new Set([block.id]);
            this.handleDragEnd();
            this.commit('drag', true, true);
        }

        handleDragEnd() {
            this.draggedId = '';
            this.ui.canvas.querySelectorAll('.is-dragging, .is-drag-target-before, .is-drag-target-after').forEach(function (element) {
                element.classList.remove('is-dragging', 'is-drag-target-before', 'is-drag-target-after');
            });
        }

        handlePointerDown(event) {
            const handle = event.target.closest('[data-editor-drag-handle]');
            if (!handle || event.pointerType === 'mouse') {
                return;
            }
            const block = handle.closest('[data-editor-block]');
            if (!block) {
                return;
            }
            event.preventDefault();
            const ghost = block.cloneNode(true);
            ghost.className = 'fb-editor2-block fb-editor2-block--ghost';
            ghost.style.width = block.getBoundingClientRect().width + 'px';
            document.body.appendChild(ghost);
            this.pointerDrag = {
                id: block.getAttribute('data-block-id'),
                pointerId: event.pointerId,
                ghost: ghost,
                targetId: '',
                after: false
            };
            block.classList.add('is-dragging');
            handle.setPointerCapture(event.pointerId);
            const move = this.handlePointerMove.bind(this);
            const up = this.handlePointerUp.bind(this);
            handle.addEventListener('pointermove', move);
            handle.addEventListener('pointerup', up, { once: true });
            handle.addEventListener('pointercancel', up, { once: true });
            this.pointerDrag.cleanup = function () {
                handle.removeEventListener('pointermove', move);
            };
            this.positionPointerGhost(event.clientX, event.clientY);
        }

        handlePointerMove(event) {
            if (!this.pointerDrag) {
                return;
            }
            event.preventDefault();
            this.autoScrollWhileDragging(event.clientY);
            this.positionPointerGhost(event.clientX, event.clientY);
            this.pointerDrag.ghost.hidden = true;
            const under = document.elementFromPoint(event.clientX, event.clientY);
            this.pointerDrag.ghost.hidden = false;
            const target = under && under.closest ? under.closest('[data-editor-block]') : null;
            this.ui.canvas.querySelectorAll('.is-drag-target-before, .is-drag-target-after').forEach(function (element) {
                element.classList.remove('is-drag-target-before', 'is-drag-target-after');
            });
            if (!target || target.getAttribute('data-block-id') === this.pointerDrag.id) {
                return;
            }
            const rect = target.getBoundingClientRect();
            this.pointerDrag.targetId = target.getAttribute('data-block-id');
            this.pointerDrag.after = event.clientY >= rect.top + rect.height / 2;
            target.classList.add(this.pointerDrag.after ? 'is-drag-target-after' : 'is-drag-target-before');
        }

        autoScrollWhileDragging(clientY) {
            const scroller = this.workspace ? this.workspace.querySelector('.fb-editor-workspace__document') : null;
            if (!scroller) {
                return;
            }
            const rect = scroller.getBoundingClientRect();
            const edge = Math.min(90, rect.height * 0.18);
            if (clientY < rect.top + edge) {
                scroller.scrollBy({ top: -18, behavior: 'auto' });
            } else if (clientY > rect.bottom - edge) {
                scroller.scrollBy({ top: 18, behavior: 'auto' });
            }
        }

        positionPointerGhost(x, y) {
            if (this.pointerDrag) {
                this.pointerDrag.ghost.style.transform = 'translate3d(' + Math.max(8, x - 24) + 'px,' + Math.max(8, y - 24) + 'px,0)';
            }
        }

        handlePointerUp() {
            if (!this.pointerDrag) {
                return;
            }
            const drag = this.pointerDrag;
            if (drag.cleanup) {
                drag.cleanup();
            }
            drag.ghost.remove();
            this.pointerDrag = null;
            this.handleDragEnd();
            if (!drag.targetId) {
                return;
            }
            const sourceIndex = this.blockIndex(drag.id);
            const block = this.state.blocks.splice(sourceIndex, 1)[0];
            const targetIndex = this.blockIndex(drag.targetId) + (drag.after ? 1 : 0);
            this.state.blocks.splice(targetIndex, 0, block);
            this.activeId = block.id;
            this.selectedIds = new Set([block.id]);
            this.commit('touch-drag', true, true);
        }

        serializeState() {
            const state = API.applyFilters('document:serializeState', this.historyState(), this);
            const html = this.state.blocks.map(this.serializePublicBlock.bind(this)).join('');
            const snapshot = importer.base64Encode(JSON.stringify(state));
            return html + '<template data-fb-editor-state="2">' + snapshot + '</template>';
        }

        serializePublicBlock(block) {
            if (!block || block.hidden) {
                return '';
            }
            const filtered = API.applyFilters('block:serialize', null, block, this);
            if (typeof filtered === 'string') {
                return this.wrapPublicBlock(block, filtered);
            }
            const definition = API.getBlockType(block.type);
            if (definition && typeof definition.serialize === 'function') {
                return this.wrapPublicBlock(block, String(definition.serialize(block, this) || ''));
            }

            const data = block.data || {};
            let content = '';
            if (block.type === 'text') {
                content = '<p>' + sanitizer.sanitizeHtml(data.html || '') + '</p>';
            } else if (block.type === 'heading') {
                const level = /^h[1-6]$/.test(data.level) ? data.level : 'h2';
                content = '<' + level + '>' + sanitizer.sanitizeHtml(data.html || '') + '</' + level + '>';
            } else if (block.type === 'bulletList' || block.type === 'orderedList') {
                const tag = block.type === 'orderedList' ? 'ol' : 'ul';
                content = '<' + tag + '>' + (data.items || []).map(function (item) {
                    return '<li>' + sanitizer.sanitizeHtml(item || '') + '</li>';
                }).join('') + '</' + tag + '>';
            } else if (block.type === 'checklist') {
                content = '<ul data-fb-checklist="1">' + (data.items || []).map(function (item) {
                    return '<li data-checked="' + (item.checked ? '1' : '0') + '">' + (item.checked ? '☑ ' : '☐ ') + sanitizer.sanitizeHtml(item.text || '') + '</li>';
                }).join('') + '</ul>';
            } else if (block.type === 'quote') {
                content = '<blockquote>' + sanitizer.sanitizeHtml(data.html || '') + (data.cite ? '<cite>' + escapeAttr(data.cite) + '</cite>' : '') + '</blockquote>';
            } else if (block.type === 'image') {
                const src = sanitizer.safeUrl(data.src || '', true);
                if (src) {
                    const image = '<img src="' + escapeAttr(src) + '" alt="' + escapeAttr(data.alt || '') + '"' +
                        (data.title ? ' title="' + escapeAttr(data.title) + '"' : '') +
                        ' loading="' + (data.lazy === false ? 'eager' : 'lazy') + '"' +
                        (Number(data.width) > 0 ? ' width="' + Math.round(Number(data.width)) + '"' : '') +
                        (Number(data.height) > 0 ? ' height="' + Math.round(Number(data.height)) + '"' : '') +
                        (['cover', 'contain'].indexOf(data.crop) !== -1 ? ' style="object-fit:' + data.crop + '"' : '') + '>';
                    content = '<figure>' + (sanitizer.safeUrl(data.link || '', false) ? '<a href="' + escapeAttr(data.link) + '" rel="noopener noreferrer">' + image + '</a>' : image) +
                        (data.caption ? '<figcaption>' + escapeAttr(data.caption) + '</figcaption>' : '') + '</figure>';
                }
            } else if (block.type === 'gallery' || block.type === 'slider') {
                const items = Array.isArray(data.items) ? data.items : [];
                content = '<div data-fb-' + block.type + '="1">' + items.map(function (item) {
                    const src = sanitizer.safeUrl(item.src || item.image || '', true);
                    return src ? '<figure><img src="' + escapeAttr(src) + '" alt="' + escapeAttr(item.alt || '') + '" loading="lazy">' + (item.caption ? '<figcaption>' + escapeAttr(item.caption) + '</figcaption>' : '') + '</figure>' : '';
                }).join('') + '</div>';
            } else if (block.type === 'video') {
                const src = sanitizer.safeUrl(data.src || '', false);
                const embed = this.embedUrl(src);
                const videoAttributes = (data.controls === false ? '' : ' controls') +
                    (data.autoplay ? ' autoplay' : '') +
                    (data.muted || data.autoplay ? ' muted' : '') +
                    (data.loop ? ' loop' : '');
                content = embed
                    ? '<div class="ratio ratio-16x9"><iframe src="' + escapeAttr(embed) + '" title="' + escapeAttr(data.caption || 'Video') + '" loading="lazy" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>'
                    : (src ? '<figure data-aspect-ratio="' + escapeAttr(data.aspectRatio || '16:9') + '"><video' + videoAttributes + ' preload="metadata" src="' + escapeAttr(src) + '"' + (data.poster ? ' poster="' + escapeAttr(sanitizer.safeUrl(data.poster, true)) + '"' : '') + '></video>' + (data.caption ? '<figcaption>' + escapeAttr(data.caption) + '</figcaption>' : '') + '</figure>' : '');
            } else if (block.type === 'audio') {
                const src = sanitizer.safeUrl(data.src || '', false);
                content = src ? '<figure><audio controls preload="metadata" src="' + escapeAttr(src) + '"></audio>' + (data.caption ? '<figcaption>' + escapeAttr(data.caption) + '</figcaption>' : '') + '</figure>' : '';
            } else if (block.type === 'table') {
                const rows = Array.isArray(data.rows) ? data.rows : [];
                const body = rows.map(function (row, rowIndex) {
                    const tag = data.header && rowIndex === 0 ? 'th' : 'td';
                    return '<tr>' + (row || []).map(function (cell) {
                        return '<' + tag + '>' + sanitizer.sanitizeHtml(cell || '') + '</' + tag + '>';
                    }).join('') + '</tr>';
                }).join('');
                const table = '<table class="table' + (data.bordered === false ? '' : ' table-bordered') + '">' + (data.header && rows.length ? '<thead>' + body.slice(0, body.indexOf('</tr>') + 5) + '</thead><tbody>' + body.slice(body.indexOf('</tr>') + 5) + '</tbody>' : '<tbody>' + body + '</tbody>') + '</table>';
                content = data.responsive === false ? table : '<div class="table-responsive">' + table + '</div>';
            } else if (block.type === 'code') {
                content = '<pre><code data-language="' + escapeAttr(data.language || 'text') + '">' + escapeAttr(data.code || '') + '</code></pre>';
            } else if (block.type === 'html') {
                content = sanitizer.sanitizeHtmlBlock(data.html || '');
            } else if (block.type === 'divider') {
                content = '<hr>';
            } else if (block.type === 'alert') {
                const variant = /^(primary|secondary|success|danger|warning|info|light|dark)$/.test(data.variant) ? data.variant : 'info';
                content = '<div class="alert alert-' + variant + '" role="alert" data-fb-alert-block="1" data-alert-variant="' + variant + '">' +
                    '<i class="' + iconClass(data.icon || 'ci-info') + '" aria-hidden="true"></i><div>' +
                    (data.title ? '<div data-fb-alert-title="1"><strong>' + escapeAttr(data.title) + '</strong></div>' : '') +
                    (data.text ? '<div data-fb-alert-text="1">' + escapeAttr(data.text).replace(/\n/g, '<br>') + '</div>' : '') +
                '</div></div>';
            } else if (block.type === 'faq') {
                content = '<div data-fb-faq="1">' + (data.items || []).map(function (item) {
                    return '<details><summary>' + escapeAttr(item.question || '') + '</summary><div>' + sanitizer.sanitizeHtml(item.answer || '') + '</div></details>';
                }).join('') + '</div>';
            } else if (block.type === 'button') {
                const url = sanitizer.safeUrl(data.url || '', false);
                const label = escapeAttr(data.text || 'Button');
                content = '<div class="text-' + escapeAttr(data.align || 'left') + '">' + (url
                    ? '<a class="btn btn-' + escapeAttr(data.style || 'primary') + '" href="' + escapeAttr(url) + '" rel="noopener noreferrer">' + label + '</a>'
                    : '<span class="btn btn-' + escapeAttr(data.style || 'primary') + '">' + label + '</span>') + '</div>';
            } else if (block.type === 'embed') {
                const embed = this.embedUrl(data.url || '');
                content = embed ? '<figure><div class="ratio ratio-16x9"><iframe src="' + escapeAttr(embed) + '" title="' + escapeAttr(data.caption || this.label('embedTitle', 'Embedded content')) + '" loading="lazy" allowfullscreen></iframe></div>' + (data.caption ? '<figcaption>' + escapeAttr(data.caption) + '</figcaption>' : '') + '</figure>' : '';
            } else if (block.type === 'newsletter') {
                content = this.renderNewsletterBlock(block, false);
            } else if (block.type === 'social') {
                content = '<div class="fb-social-buttons" data-fb-social="1" data-fb-social-buttons="1">' + (data.items || []).map(function (item) {
                    const rawUrl = item.network === 'phone' && item.url && !/^tel:/i.test(item.url)
                        ? 'tel:' + String(item.url).replace(/[^\d+]/g, '')
                        : item.url;
                    const url = sanitizer.safeUrl(rawUrl || '', false);
                    const external = item.network === 'phone' ? '' : ' target="_blank" rel="noopener noreferrer"';
                    return url ? '<a class="fb-social-buttons__item" href="' + escapeAttr(url) + '"' + external + ' data-network="' + escapeAttr(item.network || 'custom') + '" data-icon="' + escapeAttr(socialIconValue(item.icon)) + '">' + socialIconMarkup(item.icon) + '<span class="fb-social-buttons__label">' + escapeAttr(item.label || '') + '</span></a>' : '';
                }).join('') + '</div>';
            } else {
                content = sanitizer.sanitizeHtml(data.html || '');
            }
            return this.wrapPublicBlock(block, content);
        }

        wrapPublicBlock(block, content) {
            if (!content) {
                return '';
            }
            const settings = block.settings || {};
            const width = ['default', 'wide', 'full'].indexOf(settings.width) !== -1 ? settings.width : 'default';
            const classes = ['fb-content-block', 'fb-content-block--' + block.type, 'fb-content-block--width-' + width];
            if (settings.className) {
                classes.push(settings.className);
            }
            (settings.hiddenOn || []).forEach(function (device) {
                classes.push('fb-hide-' + device);
            });
            const styles = [];
            if (settings.marginTop) { styles.push('margin-top:' + Number(settings.marginTop) + 'px'); }
            if (settings.marginBottom != null) { styles.push('margin-bottom:' + Number(settings.marginBottom) + 'px'); }
            if (settings.align && settings.align !== 'left') { styles.push('text-align:' + settings.align); }
            if (settings.indent) { styles.push('margin-left:' + Number(settings.indent) + 'px'); }
            if (settings.lineHeight) { styles.push('line-height:' + Number(settings.lineHeight)); }
            if (settings.indentFirstLine) { styles.push('text-indent:2em'); }
            return '<div class="' + escapeAttr(classes.join(' ')) + '" data-fb-block="' + escapeAttr(block.type) + '" data-fb-block-id="' + escapeAttr(block.id) + '"' +
                (settings.anchor ? ' id="' + escapeAttr(settings.anchor) + '"' : '') +
                (styles.length ? ' style="' + escapeAttr(styles.join(';')) + '"' : '') + '>' + content + '</div>';
        }

        embedUrl(value) {
            const safe = sanitizer.safeUrl(value || '', false);
            if (!safe) {
                return '';
            }
            try {
                const url = new URL(safe, window.location.origin);
                const host = url.hostname.toLowerCase().replace(/^www\./, '');
                if (host === 'youtu.be') {
                    return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(url.pathname.replace(/^\/+/, ''));
                }
                if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtube-nocookie.com') {
                    const videoId = url.searchParams.get('v') || (url.pathname.match(/\/(?:embed|shorts)\/([^/?]+)/) || [])[1];
                    return videoId ? 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(videoId) : '';
                }
                if (host === 'vimeo.com' || host === 'player.vimeo.com') {
                    const videoId = (url.pathname.match(/(\d+)/) || [])[1];
                    return videoId ? 'https://player.vimeo.com/video/' + encodeURIComponent(videoId) : '';
                }
            } catch (error) {
            }
            return '';
        }

        syncTextarea(dispatchEvent) {
            if (!this.textarea) {
                return;
            }
            const serialized = this.serializeState();
            this.textarea.value = serialized;
            this.lastSerialized = serialized;
            if (dispatchEvent !== false) {
                this.textarea.dispatchEvent(new CustomEvent('fireball:post-editor-sync', {
                    bubbles: true,
                    detail: { version: 2, blocks: this.state.blocks.length }
                }));
            }
        }

        handleExternalSerialize() {
            this.syncTextarea(false);
        }

        handleSubmit() {
            this.syncTextarea(false);
            this.dirty = false;
            this.clearLocalDraft();
        }

        handleFormInvalid(event) {
            const field = event && event.target;
            if (!field || !field.closest || !field.closest('[data-editor-inspector-tab-panel="document"]')) {
                return;
            }
            this.activateInspectorTab('document');
            this.openMobilePanels();
            window.requestAnimationFrame(function () {
                if (typeof field.focus === 'function') {
                    field.focus({ preventScroll: false });
                }
            });
        }

        scheduleAutosave(immediate) {
            if (!this.form || !this.form.hasAttribute('data-post-autosave')) {
                return;
            }
            window.clearTimeout(this.autosaveTimer);
            this.autosaveTimer = window.setTimeout(function () {
                this.autosave(false);
            }.bind(this), immediate ? 0 : 1800);
        }

        async autosave(userInitiated) {
            if (!this.form || !this.dirty) {
                if (userInitiated) {
                    this.setSaveState('saved', this.label('saved', 'Saved'));
                }
                return;
            }
            const url = this.form.getAttribute('data-autosave-url');
            if (!url) {
                return;
            }
            this.syncTextarea(false);
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();
            this.setSaveState('saving', this.form.getAttribute('data-autosave-saving') || this.label('saving', 'Saving…'));
            try {
                const previousStorageKey = this.localStorageKey();
                const response = await window.fetch(url, {
                    method: 'POST',
                    body: new FormData(this.form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: this.abortController.signal
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Autosave failed');
                }
                if (result.id) {
                    this.root.setAttribute('data-entity-id', String(result.id));
                    if (this.ui.entityIdInput) {
                        this.ui.entityIdInput.value = String(result.id);
                    }
                    if (previousStorageKey !== this.localStorageKey()) {
                        try {
                            window.localStorage.removeItem(previousStorageKey);
                        } catch (error) {
                        }
                    }
                }
                if (result.edit_url) {
                    this.form.action = result.edit_url;
                    this.form.setAttribute('data-editor-edit-url', result.edit_url);
                }
                if (result.preview_url) {
                    this.form.setAttribute('data-preview-url', result.preview_url);
                }
                this.savedSerialized = this.lastSerialized;
                this.dirty = false;
                if (this.workspace) {
                    this.workspace.classList.remove('is-dirty');
                }
                this.lastSavedAt = String(result.saved_at || new Intl.DateTimeFormat(undefined, {
                    hour: '2-digit',
                    minute: '2-digit'
                }).format(new Date()));
                this.setSaveState('saved', (result.message || this.label('saved', 'Saved')) + (result.saved_at ? ' · ' + result.saved_at : ''));
                this.refreshStatus();
                this.clearLocalDraft();
                API.doAction('autosave:success', result, this);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                this.setSaveState('error', this.form.getAttribute('data-autosave-error') || this.label('saveError', 'Could not save'));
                this.saveLocalDraft();
                API.doAction('autosave:error', error, this);
                API.doAction('editor:error', { phase: 'autosave', message: String(error && error.message || error) }, this);
            }
        }

        localStorageKey() {
            const type = this.root.getAttribute('data-entity-type') || 'post';
            const id = this.root.getAttribute('data-entity-id') || 'new';
            const user = String(this.config.userId || 'anonymous');
            return 'fireball:editor2:recovery:' + user + ':' + type + ':' + id;
        }

        saveLocalDraftSoon() {
            window.clearTimeout(this.localSaveTimer);
            this.localSaveTimer = window.setTimeout(this.saveLocalDraft.bind(this), 500);
        }

        saveLocalDraft() {
            try {
                window.localStorage.setItem(this.localStorageKey(), JSON.stringify({
                    timestamp: Date.now(),
                    state: this.historyState(),
                    title: this.workspace.querySelector('[data-editor-document-title]')?.value || '',
                    serialized: this.lastSerialized
                }));
            } catch (error) {
            }
        }

        restoreLocalDraft() {
            let recovery = null;
            try {
                recovery = JSON.parse(window.localStorage.getItem(this.localStorageKey()) || 'null');
            } catch (error) {
                recovery = null;
            }
            if (!recovery || !recovery.state || recovery.serialized === this.savedSerialized) {
                return;
            }
            this.pendingRecovery = recovery;
            if (this.ui.recoveryDialog && typeof this.ui.recoveryDialog.showModal === 'function') {
                this.ui.recoveryDialog.showModal();
                const restore = this.ui.recoveryDialog.querySelector('[data-editor-recovery-restore]');
                const discard = this.ui.recoveryDialog.querySelector('[data-editor-recovery-discard]');
                restore.addEventListener('click', function () {
                    this.state = importer.normalizeState(recovery.state, API);
                    this.history = new API.EditorHistory(this.historyState(), { limit: 120, coalesceMs: 700 });
                    this.activeId = this.state.blocks[0] ? this.state.blocks[0].id : '';
                    this.selectedIds = new Set(this.activeId ? [this.activeId] : []);
                    const title = this.workspace.querySelector('[data-editor-document-title]');
                    if (title && recovery.title) {
                        title.value = recovery.title;
                    }
                    this.markDirty();
                    this.syncTextarea();
                    this.renderAll();
                    this.ui.recoveryDialog.close();
                }.bind(this), { once: true });
                discard.addEventListener('click', function () {
                    this.clearLocalDraft();
                    this.ui.recoveryDialog.close();
                }.bind(this), { once: true });
            }
        }

        clearLocalDraft() {
            try {
                window.localStorage.removeItem(this.localStorageKey());
            } catch (error) {
            }
        }

        handleBeforeUnload(event) {
            if (!this.dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        }

        openCommandPalette() {
            if (!this.ui.commandDialog) {
                return;
            }
            this.commandReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            this.commandPaletteIndex = 0;
            this.renderCommandPalette('');
            if (typeof this.ui.commandDialog.showModal === 'function') {
                this.ui.commandDialog.showModal();
            } else {
                this.ui.commandDialog.setAttribute('open', '');
            }
            if (this.ui.commandSearch) {
                this.ui.commandSearch.value = '';
                window.requestAnimationFrame(function () {
                    this.ui.commandSearch.focus();
                }.bind(this));
            }
        }

        closeCommandPalette() {
            if (!this.ui.commandDialog) {
                return;
            }
            if (typeof this.ui.commandDialog.close === 'function' && this.ui.commandDialog.open) {
                this.ui.commandDialog.close();
            } else {
                this.ui.commandDialog.removeAttribute('open');
            }
            if (this.commandReturnFocus instanceof HTMLElement && document.contains(this.commandReturnFocus)) {
                this.commandReturnFocus.focus({ preventScroll: true });
            }
            this.commandReturnFocus = null;
        }

        importMarkdown() {
            const source = window.prompt(this.label('importMarkdownPrompt', 'Paste Markdown'));
            if (!source) {
                return;
            }
            const blocks = importer.markdownToBlocks(source, API);
            if (!blocks.length) {
                return;
            }
            const index = this.activeId ? this.blockIndex(this.activeId) + 1 : this.state.blocks.length;
            this.state.blocks.splice.apply(this.state.blocks, [Math.max(0, index), 0].concat(blocks));
            this.activeId = blocks[0].id;
            this.selectionAnchorId = this.activeId;
            this.selectedIds = new Set(blocks.map(function (block) { return block.id; }));
            this.commit('import-markdown', true, true);
        }

        serializeMarkdown() {
            return this.state.blocks.filter(function (block) {
                return !block.hidden;
            }).map(function (block) {
                const data = block.data || {};
                const text = function (html) {
                    return stripHtml(html || '');
                };
                if (block.type === 'heading') {
                    const level = Math.max(1, Math.min(6, Number(String(data.level || 'h2').replace('h', '')) || 2));
                    return '#'.repeat(level) + ' ' + text(data.html);
                }
                if (block.type === 'bulletList') {
                    return (data.items || []).map(function (item) { return '- ' + text(item); }).join('\n');
                }
                if (block.type === 'orderedList') {
                    return (data.items || []).map(function (item, index) { return (index + 1) + '. ' + text(item); }).join('\n');
                }
                if (block.type === 'checklist') {
                    return (data.items || []).map(function (item) {
                        return '- [' + (item.checked ? 'x' : ' ') + '] ' + text(item.text);
                    }).join('\n');
                }
                if (block.type === 'quote') {
                    return text(data.html).split('\n').map(function (line) { return '> ' + line; }).join('\n');
                }
                if (block.type === 'code') {
                    return '```' + String(data.language || '') + '\n' + String(data.code || '') + '\n```';
                }
                if (block.type === 'divider') {
                    return '---';
                }
                if (block.type === 'image') {
                    return '![' + String(data.alt || '') + '](' + String(data.src || '') + ')';
                }
                if (block.type === 'table') {
                    const rows = Array.isArray(data.rows) ? data.rows : [];
                    if (!rows.length) {
                        return '';
                    }
                    const output = rows.map(function (row) {
                        return '| ' + row.map(text).join(' | ') + ' |';
                    });
                    output.splice(1, 0, '| ' + rows[0].map(function () { return '---'; }).join(' | ') + ' |');
                    return output.join('\n');
                }
                return text(data.html || data.text || data.title || data.caption || '');
            }).filter(Boolean).join('\n\n');
        }

        exportMarkdown() {
            const markdown = this.serializeMarkdown();
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(markdown).then(function () {
                    this.setSaveState(this.dirty ? 'saving' : 'saved', this.label('markdownCopied', 'Markdown copied'));
                }.bind(this)).catch(function (error) {
                    API.doAction('editor:error', { phase: 'markdown-export', message: String(error && error.message || error) }, this);
                }.bind(this));
            }
            API.doAction('markdown:export', markdown, this);
        }

        editorCommands() {
            const blockCommands = API.getBlockTypes().map(function (definition) {
                return {
                    id: 'insert:' + definition.machine_name,
                    label: this.label('insertBlock', 'Insert') + ': ' + (definition.title || definition.machine_name),
                    icon: iconClass(definition.icon),
                    keywords: 'add block ' + definition.machine_name,
                    run: function () { this.addBlock(definition.machine_name, this.activeId); }.bind(this)
                };
            }, this);
            const navigationCommands = this.state.blocks.slice(0, 200).map(function (block, index) {
                return {
                    id: 'goto:' + block.id,
                    label: this.label('goToBlock', 'Go to block') + ' ' + (index + 1) + ': ' + (this.blockSummary(block) || (API.getBlockType(block.type) || {}).title || block.type),
                    icon: iconClass((API.getBlockType(block.type) || {}).icon),
                    keywords: 'navigate outline ' + block.type,
                    run: function () {
                        this.selectBlock(block.id, null, true);
                        this.scrollToBlock(block.id);
                    }.bind(this)
                };
            }, this);
            const pluginCommands = (typeof API.getCommands === 'function' ? API.getCommands() : []).map(function (command) {
                return {
                    id: 'plugin:' + command.id,
                    label: this.label(command.labelKey || '', command.label || command.id),
                    icon: typeof API.getIcon === 'function' ? API.getIcon(command.icon, iconClass(command.icon)) : iconClass(command.icon),
                    keywords: command.keywords || 'plugin',
                    run: function () {
                        try {
                            command.run(this, command);
                        } catch (error) {
                            API.doAction('editor:error', { phase: 'plugin-command', command: command.id, message: String(error && error.message || error) }, this);
                        }
                    }.bind(this)
                };
            }, this);
            return [
                { id: 'undo', label: this.label('undo', 'Undo'), icon: 'ci-undo', keywords: 'history back', run: this.undo.bind(this) },
                { id: 'redo', label: this.label('redo', 'Redo'), icon: 'ci-redo', keywords: 'history forward', run: this.redo.bind(this) },
                { id: 'insert', label: this.label('addBlock', 'Add block'), icon: 'ci-plus', keywords: 'insert add', run: function () {
                    const active = this.ui.canvas.querySelector('[data-block-id="' + this.activeId + '"]');
                    this.openBlockMenu(this.activeId, active || this.ui.canvas);
                }.bind(this) },
                { id: 'import-markdown', label: this.label('importMarkdown', 'Import Markdown'), icon: 'ci-download', keywords: 'paste markdown', run: this.importMarkdown.bind(this) },
                { id: 'export-markdown', label: this.label('exportMarkdown', 'Export Markdown'), icon: 'ci-upload', keywords: 'copy markdown', run: this.exportMarkdown.bind(this) },
                { id: 'preview', label: this.label('preview', 'Preview'), icon: 'ci-eye', keywords: 'view', run: this.openPreview.bind(this) },
                { id: 'preview-split', label: this.label('previewRight', 'Preview on the right'), icon: 'ci-sidebar', keywords: 'view split', run: function () { this.toggleSplitPreview(true); }.bind(this) },
                { id: 'preview-new', label: this.label('previewNew', 'Preview in a new tab'), icon: 'ci-external-link', keywords: 'view window', run: this.openPreviewInNewTab.bind(this) },
                { id: 'focus', label: this.label('focusMode', 'Focus mode'), icon: 'ci-maximize-2', keywords: 'distraction free', run: this.toggleFocusMode.bind(this) },
                { id: 'structure', label: this.label('structureMode', 'Structure mode'), icon: 'ci-grid', keywords: 'blocks cards', run: function () { this.setMode('structure'); }.bind(this) },
                { id: 'document', label: this.label('documentMode', 'Document mode'), icon: 'ci-file-text', keywords: 'writing', run: function () { this.setMode('document'); }.bind(this) },
                { id: 'search', label: this.label('searchReplace', 'Search and replace'), icon: 'ci-search', keywords: 'find', run: function () { this.toggleSearchPanel(true); }.bind(this) },
                { id: 'clear-formatting', label: this.label('clearFormatting', 'Clear formatting'), icon: 'ci-eraser', keywords: 'format reset', run: function () { this.runToolbarCommand('clear'); }.bind(this) },
                { id: 'document-settings', label: this.label('documentSettings', 'Document settings'), icon: 'ci-settings', keywords: 'seo slug metadata', run: function () {
                    this.activateInspectorTab('document');
                    this.openMobilePanels();
                }.bind(this) },
                { id: 'save', label: this.label('saveDraft', 'Save draft'), icon: 'ci-save', keywords: 'autosave', run: function () { this.autosave(true); }.bind(this) },
                { id: 'publish', label: this.label('publish', 'Publish'), icon: 'ci-send', keywords: 'submit publish', run: function () {
                    const publish = this.workspace.querySelector('[data-editor-submit="publish"]');
                    if (publish) {
                        publish.click();
                    }
                }.bind(this) }
            ].concat(pluginCommands, navigationCommands, blockCommands);
        }

        renderCommandPalette(query) {
            if (!this.ui.commandList) {
                return;
            }
            const needle = String(query || '').toLowerCase().trim();
            const commands = this.editorCommands().filter(function (command) {
                return !needle || (command.label + ' ' + command.keywords).toLowerCase().indexOf(needle) !== -1;
            });
            this.commandPaletteCommands = commands;
            this.commandPaletteIndex = commands.length
                ? Math.max(0, Math.min(Number(this.commandPaletteIndex || 0), commands.length - 1))
                : -1;
            this.ui.commandList.innerHTML = commands.map(function (command, index) {
                const active = index === this.commandPaletteIndex;
                return '<button type="button" role="option" aria-selected="' + (active ? 'true' : 'false') + '" class="' + (active ? 'is-active' : '') + '" data-editor-run-command="' + escapeAttr(command.id) + '" data-editor-command-index="' + index + '"><span><i class="' + iconClass(command.icon) + '"></i></span><div><strong>' + escapeAttr(command.label) + '</strong></div></button>';
            }, this).join('') || '<p class="fb-editor2__menu-empty">' + escapeAttr(this.label('noCommands', 'No commands found')) + '</p>';
            this.ui.commandList.querySelectorAll('[data-editor-run-command]').forEach(function (button) {
                button.addEventListener('mouseenter', function () {
                    this.commandPaletteIndex = Number(button.getAttribute('data-editor-command-index'));
                    this.syncCommandPaletteSelection();
                }.bind(this));
                button.addEventListener('click', function () {
                    this.commandPaletteIndex = Number(button.getAttribute('data-editor-command-index'));
                    this.activateCommandPaletteSelection();
                }.bind(this));
            }, this);
        }

        syncCommandPaletteSelection() {
            if (!this.ui.commandList) {
                return;
            }
            this.ui.commandList.querySelectorAll('[data-editor-command-index]').forEach(function (button) {
                const active = Number(button.getAttribute('data-editor-command-index')) === this.commandPaletteIndex;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) {
                    button.scrollIntoView({ block: 'nearest' });
                }
            }, this);
        }

        activateCommandPaletteSelection() {
            const commands = Array.isArray(this.commandPaletteCommands) ? this.commandPaletteCommands : [];
            const command = commands[Number(this.commandPaletteIndex)];
            if (!command) {
                return;
            }
            this.closeCommandPalette();
            command.run();
        }

        previewDocumentHtml() {
            const content = this.state.blocks.map(this.serializePublicBlock.bind(this)).join('');
            const theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            const styles = Array.isArray(this.config.previewStyleAssets) ? this.config.previewStyleAssets : [];
            const styleLinks = styles.map(function (href) {
                return '<link rel="stylesheet" href="' + escapeAttr(href) + '">';
            }).join('');
            return '<!doctype html><html lang="' + escapeAttr(document.documentElement.lang || 'en') + '" data-bs-theme="' + theme + '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
                styleLinks +
                '<style>body{max-width:850px;margin:0 auto;padding:40px 24px}img,video{max-width:100%;height:auto}.fb-preview-document>.fb-content-block:last-child{margin-bottom:0!important}@media(max-width:575.98px){body{padding:24px 16px}}</style></head><body><main class="fb-preview-document">' +
                content + '</main></body></html>';
        }

        refreshSplitPreview() {
            if (this.ui.splitPreview && !this.ui.splitPreview.hidden && this.ui.splitPreviewFrame) {
                this.ui.splitPreviewFrame.srcdoc = this.previewDocumentHtml();
            }
        }

        toggleSplitPreview(open) {
            if (!this.workspace || !this.ui.splitPreview) {
                return;
            }
            const shouldOpen = typeof open === 'boolean' ? open : this.ui.splitPreview.hidden;
            this.ui.splitPreview.hidden = !shouldOpen;
            this.workspace.classList.toggle('is-split-preview', shouldOpen);
            if (shouldOpen) {
                this.refreshSplitPreview();
            }
            API.doAction('preview:split', shouldOpen, this);
        }

        openPreviewInNewTab() {
            const previewWindow = window.open('', '_blank');
            if (!previewWindow) {
                API.doAction('editor:error', { phase: 'preview-window', message: 'The preview window was blocked.' }, this);
                return;
            }
            previewWindow.opener = null;
            previewWindow.document.open();
            previewWindow.document.write(this.previewDocumentHtml());
            previewWindow.document.close();
            API.doAction('preview:new-window', this);
        }

        openPreview() {
            if (!this.ui.previewDialog || !this.ui.previewFrame) {
                return;
            }
            const content = this.state.blocks.map(this.serializePublicBlock.bind(this)).join('');
            this.ui.previewFrame.srcdoc = this.previewDocumentHtml();
            if (typeof this.ui.previewDialog.showModal === 'function') {
                this.ui.previewDialog.showModal();
            } else {
                this.ui.previewDialog.setAttribute('open', '');
            }
            API.doAction('preview:open', content, this);
        }

        closePreview() {
            if (this.ui.previewDialog && this.ui.previewDialog.open) {
                this.ui.previewDialog.close();
            }
        }

        setPreviewDevice(device) {
            if (!this.ui.previewStage) {
                return;
            }
            this.ui.previewStage.setAttribute('data-preview-device', device);
            this.root.querySelectorAll('[data-preview-device]').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-preview-device') === device);
            });
        }

        toggleFocusMode() {
            if (this.workspace) {
                this.workspace.classList.toggle('is-focus-mode');
            }
        }

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                const target = this.workspace || this.root;
                if (target.requestFullscreen) {
                    target.requestFullscreen().catch(function () {});
                }
            } else if (document.exitFullscreen) {
                document.exitFullscreen().catch(function () {});
            }
        }

        activateInspectorTab(tab) {
            if (!this.workspace) {
                return;
            }
            this.workspace.querySelectorAll('[data-editor-inspector-tab]').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-editor-inspector-tab') === tab);
            });
            this.workspace.querySelectorAll('[data-editor-inspector-tab-panel]').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-editor-inspector-tab-panel') !== tab;
            });
        }

        openMobilePanels() {
            if (this.workspace) {
                this.workspace.classList.add('is-mobile-panel-open');
            }
        }

        closeMobilePanels() {
            if (this.workspace) {
                this.workspace.classList.remove('is-mobile-panel-open');
            }
        }

        toggleSearchPanel(open) {
            if (!this.ui.searchPanel) {
                return;
            }
            this.ui.searchPanel.hidden = !open;
            if (open && this.ui.searchInput) {
                window.requestAnimationFrame(function () { this.ui.searchInput.focus(); }.bind(this));
            } else {
                this.clearSearchHighlights();
            }
        }

        performSearch(delta) {
            this.clearSearchHighlights();
            const query = String(this.ui.searchInput ? this.ui.searchInput.value : '').trim();
            if (!query) {
                this.searchMatches = [];
                this.searchIndex = -1;
                return;
            }
            const matchCase = Boolean(this.ui.searchCase && this.ui.searchCase.checked);
            const needle = matchCase ? query : query.toLocaleLowerCase();
            this.searchMatches = this.state.blocks.filter(function (block) {
                const haystack = this.blockSearchText(block);
                return (matchCase ? haystack : haystack.toLocaleLowerCase()).indexOf(needle) !== -1;
            }, this).map(function (block) {
                return block.id;
            });
            if (!this.searchMatches.length) {
                this.searchIndex = -1;
                return;
            }
            this.searchIndex = (this.searchIndex + Number(delta || 0) + this.searchMatches.length) % this.searchMatches.length;
            const matchId = this.searchMatches[this.searchIndex];
            const match = this.ui.canvas.querySelector('[data-block-id="' + matchId + '"]');
            if (match) {
                match.classList.add('is-search-match');
                match.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        clearSearchHighlights() {
            this.ui.canvas.querySelectorAll('.is-search-match').forEach(function (element) {
                element.classList.remove('is-search-match');
            });
        }

        replaceSearch(all) {
            const query = String(this.ui.searchInput ? this.ui.searchInput.value : '');
            const replacement = String(this.ui.replaceInput ? this.ui.replaceInput.value : '');
            if (!query) {
                return;
            }
            const matchCase = Boolean(this.ui.searchCase && this.ui.searchCase.checked);
            const expression = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), (all ? 'g' : '') + (matchCase ? '' : 'i'));
            let changed = false;
            const targetIds = all
                ? new Set(this.searchMatches)
                : new Set(this.searchIndex >= 0 && this.searchMatches[this.searchIndex] ? [this.searchMatches[this.searchIndex]] : []);
            const replaceNested = function (value) {
                if (typeof value === 'string') {
                    const next = value.replace(expression, replacement);
                    changed = changed || next !== value;
                    return next;
                }
                if (Array.isArray(value)) {
                    return value.map(replaceNested);
                }
                if (value && typeof value === 'object') {
                    Object.keys(value).forEach(function (key) {
                        if (key !== 'src' && key !== 'url' && key !== 'link') {
                            value[key] = replaceNested(value[key]);
                        }
                    });
                    return value;
                }
                return value;
            };
            this.state.blocks.forEach(function (block) {
                if (targetIds.has(block.id)) {
                    block.data = replaceNested(block.data || {});
                    if (!all && changed) {
                        targetIds.clear();
                    }
                }
            });
            if (changed) {
                this.commit('replace', true, true);
                this.performSearch(0);
            }
        }

        openMediaPicker(blockId, anchor, path) {
            const token = 'fireball_editor2_' + blockId + '_' + Date.now();
            this.filePickerTarget = { blockId: blockId, path: path || 'data.src', token: token };
            const url = new URL(this.config.fileManagerUrl || '/admin/files', window.location.origin);
            url.searchParams.set('picker', '1');
            url.searchParams.set('field', token);
            url.searchParams.set('dir', this.config.defaultDirectory || 'posts');
            window.open(url.toString(), 'fireball_editor_media', 'width=1280,height=860,resizable=yes,scrollbars=yes');
        }

        openDocumentFilePicker(button) {
            const inputId = button.getAttribute('data-file-manager-input');
            const token = inputId || ('fireball_document_' + Date.now());
            this.filePickerTarget = { documentInput: inputId, token: token };
            const url = new URL(button.getAttribute('data-file-manager-url') || '/admin/files', window.location.origin);
            url.searchParams.set('picker', '1');
            url.searchParams.set('field', token);
            if (button.getAttribute('data-file-manager-dir')) {
                url.searchParams.set('dir', button.getAttribute('data-file-manager-dir'));
            }
            window.open(url.toString(), 'fireball_editor_media', 'width=1280,height=860,resizable=yes,scrollbars=yes');
        }

        handleFileSelectionMessage(event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected') {
                return;
            }
            this.applyFileSelection(event.data);
        }

        handleStorage(event) {
            if (event.key !== FILE_SELECTION_KEY || !event.newValue) {
                return;
            }
            try {
                this.applyFileSelection(JSON.parse(event.newValue));
            } catch (error) {
            }
        }

        applyFileSelection(payload) {
            if (!this.filePickerTarget || String(payload.field || '') !== String(this.filePickerTarget.token || '')) {
                return;
            }
            const value = String(payload.value || '');
            if (this.filePickerTarget.documentInput) {
                const input = document.getElementById(this.filePickerTarget.documentInput);
                if (input) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    const urlInput = this.workspace.querySelector('[data-editor-cover-url]');
                    if (urlInput && !/^https?:\/\//i.test(value)) {
                        urlInput.value = '';
                    }
                    const preview = this.workspace.querySelector('[data-editor-cover-preview]');
                    const empty = this.workspace.querySelector('[data-editor-cover-empty]');
                    if (preview) {
                        preview.src = value;
                        preview.hidden = !value;
                    }
                    if (empty) {
                        empty.hidden = Boolean(value);
                    }
                }
            } else {
                const block = this.state.blocks[this.blockIndex(this.filePickerTarget.blockId)];
                if (block) {
                    if (this.filePickerTarget.path === 'data.items') {
                        if (!Array.isArray(block.data.items)) {
                            block.data.items = [];
                        }
                        block.data.items.push({ src: value, alt: '', caption: '' });
                    } else {
                        setPath(block, this.filePickerTarget.path, value);
                    }
                    this.activeId = block.id;
                    this.selectedIds = new Set([block.id]);
                    this.commit('media', true, true);
                }
            }
            this.filePickerTarget = null;
            try {
                window.localStorage.removeItem(FILE_SELECTION_KEY);
            } catch (error) {
            }
        }

        handleDocumentClick(event) {
            if (this.ui.contextMenu && !this.ui.contextMenu.hidden && !event.target.closest('[data-editor-context-menu], [data-block-action="more"]')) {
                this.closeContextMenu();
            }
            if (this.ui.slashMenu && !this.ui.slashMenu.hidden && !event.target.closest('[data-editor-slash-menu], [data-editor-insert-after], [data-editor-add-block]')) {
                const editable = event.target.closest('[data-editor-rich]');
                if (!editable || String(editable.textContent || '').charAt(0) !== '/') {
                    this.closeSlashMenu();
                }
            }
        }

        destroy() {
            this.destroyed = true;
            window.clearTimeout(this.autosaveTimer);
            window.clearTimeout(this.localSaveTimer);
            if (this.abortController) {
                this.abortController.abort();
            }
            this.root.removeEventListener('click', this.onRootClick);
            this.root.removeEventListener('input', this.onRootInput);
            this.root.removeEventListener('change', this.onRootChange);
            this.root.removeEventListener('keydown', this.onRootKeydown);
            this.root.removeEventListener('paste', this.onRootPaste);
            this.root.removeEventListener('copy', this.onRootCopy);
            this.root.removeEventListener('cut', this.onRootCut);
            this.root.removeEventListener('dragstart', this.onRootDragStart);
            this.root.removeEventListener('dragover', this.onRootDragOver);
            this.root.removeEventListener('drop', this.onRootDrop);
            this.root.removeEventListener('dragend', this.onRootDragEnd);
            this.root.removeEventListener('pointerdown', this.onRootPointerDown);
            document.removeEventListener('selectionchange', this.onSelectionChange);
            document.removeEventListener('keydown', this.onGlobalKeydown, true);
            document.removeEventListener('click', this.onDocumentClick);
            document.removeEventListener('scroll', this.onFloatingUiViewportChange, true);
            window.removeEventListener('resize', this.onFloatingUiViewportChange);
            if (window.visualViewport) {
                window.visualViewport.removeEventListener('resize', this.onFloatingUiViewportChange);
                window.visualViewport.removeEventListener('scroll', this.onFloatingUiViewportChange);
            }
            window.cancelAnimationFrame(this.floatingUiFrame);
            this.closeContextMenu();
            if (this.ui.contextMenu && !this.root.contains(this.ui.contextMenu)) {
                this.root.appendChild(this.ui.contextMenu);
            }
            if (this.workspace) {
                this.workspace.removeEventListener('click', this.onWorkspaceClick);
                this.workspace.removeEventListener('input', this.onWorkspaceInput);
                this.workspace.removeEventListener('change', this.onWorkspaceInput);
            }
            if (this.form) {
                this.form.removeEventListener('submit', this.onFormSubmit);
                this.form.removeEventListener('invalid', this.onFormInvalid, true);
                this.form.removeEventListener('fireball:post-editor-serialize', this.onExternalSerialize);
            }
            window.removeEventListener('beforeunload', this.onBeforeUnload);
            window.removeEventListener('message', this.onMessage);
            window.removeEventListener('storage', this.onStorage);
            API.detachEditor(this);
        }
    }

    const originalRootClick = FireballEditor.prototype.handleRootClick;
    FireballEditor.prototype.handleRootClick = function (event) {
        const previewClose = event.target.closest('[data-editor-preview-close]');
        if (previewClose) {
            this.closePreview();
            return;
        }
        const previewDevice = event.target.closest('[data-preview-device]');
        if (previewDevice) {
            this.setPreviewDevice(previewDevice.getAttribute('data-preview-device'));
            return;
        }
        if (event.target.closest('[data-editor-search-close]')) {
            this.toggleSearchPanel(false);
            return;
        }
        if (event.target.closest('[data-editor-search-prev]')) {
            this.performSearch(-1);
            return;
        }
        if (event.target.closest('[data-editor-search-next]')) {
            this.performSearch(1);
            return;
        }
        if (event.target.closest('[data-editor-replace-one]')) {
            this.replaceSearch(false);
            return;
        }
        if (event.target.closest('[data-editor-replace-all]')) {
            this.replaceSearch(true);
            return;
        }
        originalRootClick.call(this, event);
    };

    API.Editor = FireballEditor;

    function initializeEditors(scope) {
        (scope || document).querySelectorAll('[data-editor2]').forEach(function (root) {
            if (root.__fireballEditor2) {
                return;
            }
            try {
                root.__fireballEditor2 = new FireballEditor(root);
            } catch (error) {
                let labels = {};
                try {
                    labels = (JSON.parse(root.getAttribute('data-post-editor-config') || '{}').labels || {});
                } catch (configError) {
                }
                const source = root.previousElementSibling && root.previousElementSibling.matches('[data-block-editor-source]')
                    ? root.previousElementSibling
                    : null;
                const warning = document.createElement('div');
                warning.className = 'alert alert-danger m-3';
                warning.setAttribute('role', 'alert');
                warning.textContent = String(labels.editorLoadError || 'The editor could not open this document. The original content is preserved below.');
                root.replaceChildren(warning);
                if (source) {
                    source.classList.remove('d-none');
                    source.setAttribute('readonly', '');
                }
                API.doAction('editor:error', { phase: 'initialization', message: String(error && error.message || error) }, null);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeEditors(document);
        }, { once: true });
    } else {
        initializeEditors(document);
    }

    document.addEventListener('fireball:editor2:init', function (event) {
        initializeEditors(event.detail && event.detail.scope ? event.detail.scope : document);
    });
})(window, document);
