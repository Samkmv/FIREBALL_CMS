function initPostDatepicker() {
    const publishedAtField = document.querySelector('[data-post-datepicker]');
    if (!publishedAtField || typeof flatpickr === 'undefined') {
        return;
    }

    flatpickr(publishedAtField, {
        enableTime: true,
        time_24hr: true,
        allowInput: true,
        minuteIncrement: 1,
        dateFormat: 'Y-m-d H:i:S',
        altInput: true,
        altFormat: 'd.m.Y H:i',
        disableMobile: true
    });
}

function initPostEditor() {
    const fileSelectionStorageKey = 'fireball:file:selected';
    const textarea = document.querySelector('[data-post-editor]');
    const app = document.querySelector('[data-post-editor-app]');

    if (!textarea || !app) {
        console.warn('fb-post-editor: required elements not found', {
            textareaFound: !!textarea,
            appFound: !!app
        });
        return;
    }

    let config = {
        fileManagerUrl: '',
        defaultDirectory: 'posts',
        fonts: [],
        sizes: [],
        labels: {}
    };

    try {
        config = Object.assign(config, JSON.parse(String(app.getAttribute('data-post-editor-config') || '{}')));
    } catch (error) {
        console.warn('fb-post-editor: invalid config JSON', error);
    }

    const labels = Object.assign({
        addText: 'Text',
        addHeading: 'Heading',
        addImage: 'Image',
        addVideo: 'Video',
        addHtml: 'HTML',
        addSocial: 'Social buttons',
        addSlider: 'Slider',
        addCode: 'Code',
        textBlock: 'Text block',
        headingBlock: 'Heading block',
        imageBlock: 'Image block',
        videoBlock: 'Video block',
        htmlBlock: 'HTML block',
        socialBlock: 'Social buttons block',
        sliderBlock: 'Slider block',
        codeBlock: 'Code block',
        moveUp: 'Move up',
        moveDown: 'Move down',
        remove: 'Remove',
        duplicate: 'Duplicate',
        drag: 'Drag block',
        chooseFile: 'Choose file',
        sourceLink: 'Source link',
        imageAlt: 'Alt text',
        imageCaption: 'Caption',
        imageLink: 'Image link',
        videoPoster: 'Poster',
        videoCaption: 'Caption',
        headingLevel: 'Level',
        codeLanguage: 'Language',
        htmlPlaceholder: 'Paste HTML markup. Scripts and unsafe event handlers will be removed.',
        htmlPreview: 'HTML preview',
        socialNetwork: 'Network',
        socialIcon: 'Icon',
        socialCustom: 'Custom',
        socialGlobe: 'Globe',
        socialShare: 'Share',
        socialExternalLink: 'External link',
        socialPhone: 'Phone',
        socialMessage: 'Message',
        socialLabel: 'Label',
        socialUrl: 'Link',
        socialAddItem: 'Add button',
        socialRemoveItem: 'Remove button',
        socialItemsHint: 'Add buttons, choose a network and icon, then set the target URL.',
        codePlaceholder: 'Paste your code here',
        textPlaceholder: 'Write text here. Use the toolbar for font, size, colors and lists.',
        headingPlaceholder: 'Heading text',
        font: 'Font',
        size: 'Size',
        textColor: 'Text color',
        background: 'Background',
        bold: 'Bold',
        italic: 'Italic',
        underline: 'Underline',
        alignLeft: 'Align left',
        alignCenter: 'Align center',
        alignRight: 'Align right',
        link: 'Link',
        unlink: 'Unlink',
        linkPrompt: 'Enter a link URL',
        empty: 'Add the first block to start building the post.',
        quote: 'Quote',
        bulletList: 'Bullet list',
        addBlock: 'Add block',
        blockSettings: 'Block settings',
        mediaSettings: 'Media settings',
        contentSettings: 'Content editing',
        sliderItems: 'Slides',
        sliderAddItem: 'Add slide',
        sliderRemoveItem: 'Remove slide',
        sliderImage: 'Slide image',
        sliderAspectRatio: 'Aspect ratio',
        sliderShowBullets: 'Show bullets',
        sliderShowArrows: 'Show arrows',
        sliderTitle: 'Slide title',
        sliderText: 'Slide text',
        sliderAlt: 'Image alt text',
        sliderSlide: 'Slide',
        sliderPrev: 'Previous slide',
        sliderNext: 'Next slide',
        blockCount: 'Blocks',
        canvasTitle: 'Post content',
        style: 'Style',
        close: 'Close',
        settings: 'Settings',
        inlineSettingsHint: 'This block is edited directly in the content area.',
        moreStyles: 'Font, size and colors',
        htmlHint: 'Unsafe tags and inline handlers will be removed on save.',
        deleteModalTitle: 'Remove block?',
        deleteModalText: 'This action cannot be undone. The block will be removed from the post content.'
    }, config.labels || {});

    const blockTypeIcons = {
        text: 'ci-type',
        heading: 'ci-hash',
        image: 'ci-image',
        video: 'ci-video',
        html: 'ci-layout',
        social: 'ci-share-2',
        slider: 'ci-sliders',
        code: 'ci-code'
    };
    const fonts = Array.isArray(config.fonts) ? config.fonts : [];
    const sizes = Array.isArray(config.sizes) ? config.sizes : [];
    const popupName = 'fireball_file_manager';
    const sliderAspectRatioOptions = [
        { value: '16x9', label: '16:9' },
        { value: '4x3', label: '4:3' },
        { value: '1x1', label: '1:1' }
    ];
    const socialNetworkOptions = [
        { value: 'telegram', label: 'Telegram', icon: 'ci-telegram' },
        { value: 'instagram', label: 'Instagram', icon: 'ci-instagram' },
        { value: 'facebook', label: 'Facebook', icon: 'ci-facebook' },
        { value: 'youtube', label: 'YouTube', icon: 'ci-youtube' },
        { value: 'whatsapp', label: 'WhatsApp', icon: 'ci-whatsapp' },
        { value: 'vk', label: 'VK', icon: 'ci-vk' },
        { value: 'linkedin', label: 'LinkedIn', icon: 'ci-linkedin' },
        { value: 'x', label: 'X', icon: 'ci-x' },
        { value: 'tiktok', label: 'TikTok', icon: 'ci-tiktok' },
        { value: 'discord', label: 'Discord', icon: 'ci-discord' },
        { value: 'github', label: 'GitHub', icon: 'ci-github' },
        { value: 'viber', label: 'Viber', icon: 'ci-viber' },
        { value: 'messenger', label: 'Messenger', icon: 'ci-messenger' },
        { value: 'custom', label: labels.socialCustom, icon: 'ci-share-2' }
    ];
    const socialIconOptions = [
        { value: 'ci-telegram', label: 'Telegram' },
        { value: 'ci-instagram', label: 'Instagram' },
        { value: 'ci-facebook', label: 'Facebook' },
        { value: 'ci-youtube', label: 'YouTube' },
        { value: 'ci-whatsapp', label: 'WhatsApp' },
        { value: 'ci-vk', label: 'VK' },
        { value: 'ci-linkedin', label: 'LinkedIn' },
        { value: 'ci-x', label: 'X' },
        { value: 'ci-tiktok', label: 'TikTok' },
        { value: 'ci-discord', label: 'Discord' },
        { value: 'ci-github', label: 'GitHub' },
        { value: 'ci-viber', label: 'Viber' },
        { value: 'ci-messenger', label: 'Messenger' },
        { value: 'ci-globe', label: labels.socialGlobe },
        { value: 'ci-share-2', label: labels.socialShare },
        { value: 'ci-external-link', label: labels.socialExternalLink },
        { value: 'ci-phone', label: labels.socialPhone },
        { value: 'ci-message-circle', label: labels.socialMessage }
    ];
    const fontSizeCommandMap = {
        '10px': '1',
        '12px': '2',
        '14px': '3',
        '16px': '4',
        '18px': '5',
        '24px': '6',
        '32px': '7'
    };
    const fontSizeCssMap = {
        '1': '10px',
        '2': '12px',
        '3': '14px',
        '4': '16px',
        '5': '18px',
        '6': '24px',
        '7': '32px'
    };

    let state = parseInitialState();
    const ui = {
        activeBlockId: state.blocks[0] ? state.blocks[0].id : null,
        addMenuAnchor: null,
        addMenuPosition: state.blocks.length,
        pendingRemoveBlockId: null,
        settingsBlockId: null,
        draggedBlockId: null,
        picker: null,
        selection: null,
        deleteModalInstance: null
    };

    const deleteModalElement = document.getElementById('postEditorDeleteModal');
    const deleteModalTitleElement = deleteModalElement
        ? deleteModalElement.querySelector('[data-editor-delete-title]')
        : null;
    const deleteModalTextElement = deleteModalElement
        ? deleteModalElement.querySelector('[data-editor-delete-text]')
        : null;
    const deleteModalConfirmButton = deleteModalElement
        ? deleteModalElement.querySelector('[data-editor-confirm-remove]')
        : null;
    const settingsModalElement = document.getElementById('postEditorSettingsModal');
    const settingsModalTitleElement = settingsModalElement
        ? settingsModalElement.querySelector('[data-editor-settings-title]')
        : null;
    const settingsModalBodyElement = settingsModalElement
        ? settingsModalElement.querySelector('[data-editor-settings-body]')
        : null;
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);

    if (deleteModalElement && bootstrapApi && bootstrapApi.Modal) {
        ui.deleteModalInstance = bootstrapApi.Modal.getOrCreateInstance(deleteModalElement);
    }

    if (settingsModalElement && bootstrapApi && bootstrapApi.Modal) {
        ui.settingsModalInstance = bootstrapApi.Modal.getOrCreateInstance(settingsModalElement);
    }

    if (deleteModalTitleElement) {
        deleteModalTitleElement.textContent = labels.deleteModalTitle;
    }

    if (deleteModalTextElement) {
        deleteModalTextElement.textContent = labels.deleteModalText;
    }

    app.classList.add('fb-post-editor--linear');
    bindEvents();
    render();
    sync();

    function parseInitialState() {
        const initialValue = String(textarea.value || '').trim();
        const emptyState = {
            version: 2,
            blocks: []
        };

        if (initialValue === '') {
            return emptyState;
        }

        if (initialValue.charAt(0) === '{') {
            try {
                const parsed = JSON.parse(initialValue);
                if (parsed && typeof parsed === 'object' && Array.isArray(parsed.blocks)) {
                    return {
                        version: 2,
                        blocks: parsed.blocks.map(normalizeBlock).filter(Boolean)
                    };
                }
            } catch (error) {
                console.warn('fb-post-editor: invalid initial JSON, fallback to HTML parser', error);
            }
        }

        return {
            version: 2,
            blocks: importBlocksFromHtml(initialValue)
        };
    }

    function normalizeBlock(block) {
        if (!block || typeof block !== 'object') {
            return null;
        }

        const type = getAllowedBlockType(block.type);
        return {
            id: String(block.id || makeId()),
            type: type,
            data: Object.assign(defaultBlockData(type), cloneData(block.data))
        };
    }

    function render() {
        ensureAppStructure();
        ensureActiveBlock();

        const blockCountElement = app.querySelector('[data-editor-block-count]');
        const blocksContainer = getBlocksContainer();

        if (blockCountElement) {
            blockCountElement.textContent = String(state.blocks.length);
        }

        blocksContainer.innerHTML = '';

        if (!state.blocks.length) {
            blocksContainer.innerHTML = '<div class="fb-post-editor__empty">' + escapeHtml(labels.empty) + '</div>';
        } else {
            state.blocks.forEach(function (block, index) {
                blocksContainer.insertAdjacentHTML('beforeend', renderBlockCard(block, index));
                if (index < state.blocks.length - 1) {
                    blocksContainer.insertAdjacentHTML('beforeend', renderInsertControl('between-' + String(index), index + 1));
                }
            });
        }

        blocksContainer.insertAdjacentHTML('beforeend', '<div class="fb-post-editor__insert fb-post-editor__insert--tail">' + renderInsertControl('bottom', state.blocks.length) + '</div>');
        renderSettingsModal();
        closeStylePanels();
    }

    function sync() {
        textarea.value = JSON.stringify(state, null, 2);
    }

    function ensureAppStructure() {
        const topMenu = renderInsertControl('top', state.blocks.length);
        app.innerHTML = '' +
            '<div class="fb-post-editor__bar">' +
                '<div class="fb-post-editor__bar-copy">' +
                    '<strong>' + escapeHtml(labels.canvasTitle) + '</strong>' +
                    '<span>' + escapeHtml(labels.blockCount) + ': <span data-editor-block-count>' + escapeHtml(String(state.blocks.length)) + '</span></span>' +
                '</div>' +
                topMenu +
            '</div>' +
            '<div class="fb-post-editor__blocks" data-editor-blocks></div>' +
            '<div class="fb-post-editor__settings-root" data-editor-settings-root></div>';
    }

    function getBlocksContainer() {
        let blocksContainer = app.querySelector('[data-editor-blocks]');
        if (!blocksContainer) {
            blocksContainer = document.createElement('div');
            blocksContainer.className = 'fb-post-editor__blocks';
            blocksContainer.setAttribute('data-editor-blocks', '');
            app.appendChild(blocksContainer);
        }
        return blocksContainer;
    }

    function addBlock(type, position) {
        const insertPosition = typeof position === 'number'
            ? Math.max(0, Math.min(position, state.blocks.length))
            : state.blocks.length;
        const block = createBlock(type);
        state.blocks.splice(insertPosition, 0, block);
        ui.activeBlockId = block.id;
        ui.settingsBlockId = null;
        closeAddMenu();
        render();
        sync();
        focusBlockEditor(block.id);
    }

    function createBlock(type) {
        const blockType = getAllowedBlockType(type);
        return {
            id: makeId(),
            type: blockType,
            data: defaultBlockData(blockType)
        };
    }

    function removeBlock(id) {
        const index = findBlockIndex(id);
        if (index === -1) {
            return;
        }

        state.blocks.splice(index, 1);

        if (ui.activeBlockId === id) {
            ui.activeBlockId = state.blocks[index] ? state.blocks[index].id : (state.blocks[index - 1] ? state.blocks[index - 1].id : null);
        }

        if (ui.settingsBlockId === id) {
            ui.settingsBlockId = null;
        }

        render();
        sync();
    }

    function requestRemoveBlock(id) {
        if (!findBlock(id)) {
            return;
        }

        ui.pendingRemoveBlockId = id;
        openDeleteModal();
    }

    function confirmRemoveBlock() {
        if (!ui.pendingRemoveBlockId) {
            closeDeleteModal();
            return;
        }

        const blockId = ui.pendingRemoveBlockId;
        ui.pendingRemoveBlockId = null;
        closeDeleteModal();
        removeBlock(blockId);
    }

    function duplicateBlock(id) {
        const index = findBlockIndex(id);
        const block = findBlock(id);
        if (index === -1 || !block) {
            return;
        }

        const duplicate = {
            id: makeId(),
            type: block.type,
            data: cloneData(block.data)
        };

        state.blocks.splice(index + 1, 0, duplicate);
        ui.activeBlockId = duplicate.id;
        render();
        sync();
        focusBlockEditor(duplicate.id);
    }

    function moveBlock(id, direction) {
        const index = findBlockIndex(id);
        if (index === -1) {
            return;
        }

        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= state.blocks.length) {
            return;
        }

        const moved = state.blocks.splice(index, 1)[0];
        state.blocks.splice(targetIndex, 0, moved);
        ui.activeBlockId = moved.id;
        render();
        sync();
    }

    function updateBlock(id, patch) {
        const block = findBlock(id);
        if (!block || !patch || typeof patch !== 'object') {
            return;
        }

        block.data = Object.assign({}, block.data, patch);
        sync();
    }

    function setActiveBlock(id) {
        if (!findBlock(id)) {
            return;
        }

        if (ui.activeBlockId === id) {
            return;
        }

        ui.activeBlockId = id;
        app.querySelectorAll('.fb-post-block[data-block-id]').forEach(function (blockElement) {
            blockElement.classList.toggle('is-active', blockElement.getAttribute('data-block-id') === id);
        });
        closeStylePanels();
    }

    function openAddMenu(button) {
        const anchor = String(button.getAttribute('data-menu-anchor') || '');
        const position = Number(button.getAttribute('data-insert-position') || state.blocks.length);

        if (ui.addMenuAnchor === anchor) {
            closeAddMenu();
            return;
        }

        ui.addMenuAnchor = anchor;
        ui.addMenuPosition = Number.isNaN(position) ? state.blocks.length : position;
        render();
    }

    function closeAddMenu() {
        ui.addMenuAnchor = null;
        ui.addMenuPosition = state.blocks.length;
    }

    function bindEvents() {
        app.addEventListener('click', function (event) {
            const openAddButton = event.target.closest('[data-editor-open-add]');
            const addTypeButton = event.target.closest('[data-editor-add]');
            const blockActionButton = event.target.closest('[data-block-action]');
            const styleToggleButton = event.target.closest('[data-style-toggle]');
            const closeSettingsButton = event.target.closest('[data-editor-close-settings]');
            const blockSelectable = event.target.closest('[data-block-selectable]');
            const pickerButton = event.target.closest('[data-block-picker]');
            const sliderPickerButton = event.target.closest('[data-slider-picker]');
            const socialAddButton = event.target.closest('[data-social-add-item]');
            const socialRemoveButton = event.target.closest('[data-social-remove-item]');
            const sliderAddButton = event.target.closest('[data-slider-add-item]');
            const sliderRemoveButton = event.target.closest('[data-slider-remove-item]');
            const editorCommandButton = event.target.closest('button[data-editor-command]');

            if (openAddButton) {
                openAddMenu(openAddButton);
                return;
            }

            if (addTypeButton) {
                addBlock(
                    String(addTypeButton.getAttribute('data-editor-add') || 'text'),
                    Number(addTypeButton.getAttribute('data-insert-position') || state.blocks.length)
                );
                return;
            }

            if (blockActionButton) {
                const blockElement = blockActionButton.closest('[data-block-id]');
                const blockId = blockElement ? String(blockElement.dataset.blockId || '') : '';
                const action = String(blockActionButton.getAttribute('data-block-action') || '');

                if (!blockId) {
                    return;
                }

                if (action === 'move-up') {
                    moveBlock(blockId, 'up');
                    return;
                }

                if (action === 'move-down') {
                    moveBlock(blockId, 'down');
                    return;
                }

                if (action === 'duplicate') {
                    duplicateBlock(blockId);
                    return;
                }

                if (action === 'settings') {
                    ui.settingsBlockId = blockId;
                    setActiveBlock(blockId);
                    openSettingsModal();
                    return;
                }

                if (action === 'remove') {
                    requestRemoveBlock(blockId);
                    return;
                }
            }

            if (styleToggleButton) {
                toggleStylePanel(styleToggleButton);
                return;
            }

            if (event.target.closest('[data-style-wrap]')) {
                return;
            }

            if (closeSettingsButton) {
                ui.settingsBlockId = null;
                closeSettingsModal();
                return;
            }

            if (pickerButton) {
                const blockElement = pickerButton.closest('[data-block-id]');
                openPicker(
                    blockElement ? String(blockElement.dataset.blockId || '') : '',
                    String(pickerButton.getAttribute('data-block-picker') || 'src')
                );
                return;
            }

            if (sliderPickerButton) {
                const blockElement = sliderPickerButton.closest('[data-block-id]');
                openPicker(
                    blockElement ? String(blockElement.dataset.blockId || '') : '',
                    String(sliderPickerButton.getAttribute('data-slider-picker') || 'image'),
                    Number(sliderPickerButton.getAttribute('data-item-index') || '-1')
                );
                return;
            }

            if (socialAddButton) {
                const blockElement = socialAddButton.closest('[data-block-id]');
                addSocialItem(blockElement ? String(blockElement.dataset.blockId || '') : '');
                return;
            }

            if (socialRemoveButton) {
                const blockElement = socialRemoveButton.closest('[data-block-id]');
                removeSocialItem(
                    blockElement ? String(blockElement.dataset.blockId || '') : '',
                    Number(socialRemoveButton.getAttribute('data-item-index') || '-1')
                );
                return;
            }

            if (sliderAddButton) {
                const blockElement = sliderAddButton.closest('[data-block-id]');
                addSliderItem(blockElement ? String(blockElement.dataset.blockId || '') : '');
                return;
            }

            if (sliderRemoveButton) {
                const blockElement = sliderRemoveButton.closest('[data-block-id]');
                removeSliderItem(
                    blockElement ? String(blockElement.dataset.blockId || '') : '',
                    Number(sliderRemoveButton.getAttribute('data-item-index') || '-1')
                );
                return;
            }

            if (editorCommandButton) {
                const blockElement = editorCommandButton.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                applyCommand(
                    editor,
                    String(editorCommandButton.getAttribute('data-editor-command') || ''),
                    String(editorCommandButton.getAttribute('data-editor-value') || '')
                );
                return;
            }

            if (blockSelectable) {
                setActiveBlock(String(blockSelectable.getAttribute('data-block-id') || ''));
            }
        });

        app.addEventListener('mousedown', function (event) {
            const commandButton = event.target.closest('button[data-editor-command], button[data-style-toggle]');
            if (commandButton) {
                event.preventDefault();
            }
        });

        app.addEventListener('pointerdown', function (event) {
            const toolbarControl = event.target.closest('[data-editor-command], [data-editor-font-size], [data-style-toggle]');
            if (!toolbarControl) {
                return;
            }

            const blockElement = toolbarControl.closest('[data-block-id]');
            const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
            saveSelection(editor);

            if (toolbarControl.tagName === 'BUTTON') {
                event.preventDefault();
            }
        });

        app.addEventListener('focusin', function (event) {
            const blockElement = event.target.closest('[data-block-id]');
            if (blockElement) {
                setActiveBlock(String(blockElement.dataset.blockId || ''));
            }
        });

        app.addEventListener('input', function (event) {
            const richEditor = event.target.closest('[data-block-rich]');
            const headingEditor = event.target.closest('[data-block-heading]');
            const blockField = event.target.closest('[data-block-field]');
            const socialField = event.target.closest('[data-social-field]');
            const sliderField = event.target.closest('[data-slider-field]');
            const colorField = event.target.closest('input[type="color"][data-editor-command]');

            if (richEditor) {
                syncRichEditor(richEditor);
                return;
            }

            if (headingEditor) {
                updateBlock(
                    String(headingEditor.getAttribute('data-block-id') || ''),
                    { html: sanitizeHtml(headingEditor.innerHTML) }
                );
                return;
            }

            if (socialField) {
                updateSocialItemField(
                    String(socialField.getAttribute('data-block-id') || ''),
                    Number(socialField.getAttribute('data-item-index') || '-1'),
                    String(socialField.getAttribute('data-social-field') || ''),
                    socialField.value
                );
                sync();
                return;
            }

            if (sliderField) {
                updateSliderItemField(
                    String(sliderField.getAttribute('data-block-id') || ''),
                    Number(sliderField.getAttribute('data-item-index') || '-1'),
                    String(sliderField.getAttribute('data-slider-field') || ''),
                    sliderField.value
                );
                sync();
                return;
            }

            if (blockField) {
                const blockId = String(blockField.getAttribute('data-block-id') || '');
                const fieldName = String(blockField.getAttribute('data-block-field') || '');
                const patch = {};
                patch[fieldName] = blockField.value;
                updateBlock(blockId, patch);

                if (fieldName === 'html') {
                    const blockElement = blockField.closest('[data-block-id]');
                    const preview = blockElement ? blockElement.querySelector('[data-block-html-preview]') : null;
                    if (preview) {
                        preview.innerHTML = buildAdaptivePreviewHtml(blockField.value);
                    }
                }
                return;
            }

            if (colorField) {
                const blockElement = colorField.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                applyCommand(editor, String(colorField.getAttribute('data-editor-command') || ''), String(colorField.value || ''));
            }
        });

        app.addEventListener('change', function (event) {
            const commandSelect = event.target.closest('select[data-editor-command]');
            const fontSizeSelect = event.target.closest('[data-editor-font-size]');
            const toggleField = event.target.closest('[data-block-toggle]');
            const blockField = event.target.closest('[data-block-field]');
            const socialField = event.target.closest('[data-social-field]');
            const sliderField = event.target.closest('[data-slider-field]');

            if (commandSelect) {
                const blockElement = commandSelect.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                if (commandSelect.value !== '') {
                    applyCommand(editor, String(commandSelect.getAttribute('data-editor-command') || ''), String(commandSelect.value || ''));
                }
                commandSelect.value = '';
                return;
            }

            if (fontSizeSelect) {
                const blockElement = fontSizeSelect.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                if (fontSizeSelect.value !== '') {
                    applyCommand(editor, 'fontSize', String(fontSizeSelect.value || ''));
                }
                fontSizeSelect.value = '';
                return;
            }

            if (toggleField && toggleField.type === 'checkbox') {
                const patch = {};
                patch[String(toggleField.getAttribute('data-block-toggle') || '')] = !!toggleField.checked;
                updateBlock(String(toggleField.getAttribute('data-block-id') || ''), patch);
                render();
                sync();
                return;
            }

            if (blockField && blockField.tagName === 'SELECT') {
                const patch = {};
                patch[String(blockField.getAttribute('data-block-field') || '')] = blockField.value;
                updateBlock(String(blockField.getAttribute('data-block-id') || ''), patch);
                render();
                sync();
                return;
            }

            if (socialField) {
                updateSocialItemField(
                    String(socialField.getAttribute('data-block-id') || ''),
                    Number(socialField.getAttribute('data-item-index') || '-1'),
                    String(socialField.getAttribute('data-social-field') || ''),
                    socialField.value
                );
                render();
                sync();
                return;
            }

            if (sliderField) {
                updateSliderItemField(
                    String(sliderField.getAttribute('data-block-id') || ''),
                    Number(sliderField.getAttribute('data-item-index') || '-1'),
                    String(sliderField.getAttribute('data-slider-field') || ''),
                    sliderField.value
                );
                render();
                sync();
            }
        });

        app.addEventListener('keydown', function (event) {
            const headingEditor = event.target.closest('[data-block-heading]');
            const richEditor = event.target.closest('[data-block-rich]');

            if (headingEditor && event.key === 'Enter') {
                event.preventDefault();
                return;
            }

            if (richEditor) {
                saveSelection(richEditor);
            }
        });

        app.addEventListener('keyup', function (event) {
            const richEditor = event.target.closest('[data-block-rich]');
            if (richEditor) {
                saveSelection(richEditor);
            }
        });

        app.addEventListener('mouseup', function (event) {
            const richEditor = event.target.closest('[data-block-rich]');
            if (richEditor) {
                saveSelection(richEditor);
            }
        });

        app.addEventListener('dragstart', function (event) {
            const handle = event.target.closest('[data-block-drag-handle]');
            if (!handle) {
                return;
            }

            const blockElement = handle.closest('[data-block-id]');
            ui.draggedBlockId = blockElement ? String(blockElement.dataset.blockId || '') : null;
            if (event.dataTransfer && ui.draggedBlockId) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', ui.draggedBlockId);
            }
        });

        app.addEventListener('dragover', function (event) {
            const blockElement = event.target.closest('[data-block-id]');
            if (!blockElement || !ui.draggedBlockId) {
                return;
            }

            event.preventDefault();
            app.querySelectorAll('[data-block-id]').forEach(function (item) {
                item.classList.remove('is-drop-target');
            });
            blockElement.classList.add('is-drop-target');
        });

        app.addEventListener('drop', function (event) {
            const blockElement = event.target.closest('[data-block-id]');
            if (!blockElement || !ui.draggedBlockId) {
                return;
            }

            event.preventDefault();
            reorderBlocks(ui.draggedBlockId, String(blockElement.dataset.blockId || ''));
            ui.draggedBlockId = null;
        });

        app.addEventListener('dragend', function () {
            ui.draggedBlockId = null;
            app.querySelectorAll('[data-block-id]').forEach(function (item) {
                item.classList.remove('is-drop-target');
            });
        });

        document.addEventListener('selectionchange', function () {
            const selection = window.getSelection ? window.getSelection() : null;
            if (!selection || selection.rangeCount === 0) {
                return;
            }

            const anchorNode = selection.anchorNode;
            if (!anchorNode) {
                return;
            }

            const anchorElement = anchorNode.nodeType === Node.ELEMENT_NODE ? anchorNode : anchorNode.parentElement;
            if (!anchorElement || !app.contains(anchorElement)) {
                return;
            }

            const richEditor = anchorElement.closest('[data-block-rich]');
            if (richEditor) {
                saveSelection(richEditor);
            }
        });

        document.addEventListener('pointerdown', function (event) {
            const target = event.target;
            const clickedInsideApp = app.contains(target);
            const clickedInsideAddMenu = !!target.closest('[data-add-wrap], .fb-post-add-menu');
            const clickedInsideStyleMenu = !!target.closest('[data-style-wrap]');

            if (!clickedInsideStyleMenu) {
                closeStylePanels();
            }

            if (ui.addMenuAnchor === null) {
                return;
            }

            if (!clickedInsideApp) {
                closeAddMenu();
                render();
                return;
            }

            if (!clickedInsideAddMenu) {
                closeAddMenu();
                render();
            }
        });

        if (deleteModalConfirmButton) {
            deleteModalConfirmButton.addEventListener('click', confirmRemoveBlock);
        }

        if (deleteModalElement) {
            deleteModalElement.addEventListener('click', function (event) {
                if (event.target === deleteModalElement && !ui.deleteModalInstance) {
                    closeDeleteModal();
                }
            });

            deleteModalElement.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!ui.deleteModalInstance) {
                        closeDeleteModal();
                    }
                });
            });

            deleteModalElement.addEventListener('hidden.bs.modal', function () {
                ui.pendingRemoveBlockId = null;
            });
        }

        if (settingsModalElement) {
            settingsModalElement.addEventListener('click', function (event) {
                const pickerButton = event.target.closest('[data-block-picker]');
                const sliderPickerButton = event.target.closest('[data-slider-picker]');
                const socialAddButton = event.target.closest('[data-social-add-item]');
                const socialRemoveButton = event.target.closest('[data-social-remove-item]');
                const sliderAddButton = event.target.closest('[data-slider-add-item]');
                const sliderRemoveButton = event.target.closest('[data-slider-remove-item]');
                const closeButton = event.target.closest('[data-bs-dismiss="modal"], [data-editor-close-settings]');

                if (pickerButton) {
                    const blockElement = pickerButton.closest('[data-block-id]');
                    openPicker(
                        blockElement ? String(blockElement.dataset.blockId || '') : '',
                        String(pickerButton.getAttribute('data-block-picker') || 'src')
                    );
                    return;
                }

                if (sliderPickerButton) {
                    const blockElement = sliderPickerButton.closest('[data-block-id]');
                    openPicker(
                        blockElement ? String(blockElement.dataset.blockId || '') : '',
                        String(sliderPickerButton.getAttribute('data-slider-picker') || 'image'),
                        Number(sliderPickerButton.getAttribute('data-item-index') || '-1')
                    );
                    return;
                }

                if (socialAddButton) {
                    const blockElement = socialAddButton.closest('[data-block-id]');
                    addSocialItem(blockElement ? String(blockElement.dataset.blockId || '') : '');
                    return;
                }

                if (socialRemoveButton) {
                    const blockElement = socialRemoveButton.closest('[data-block-id]');
                    removeSocialItem(
                        blockElement ? String(blockElement.dataset.blockId || '') : '',
                        Number(socialRemoveButton.getAttribute('data-item-index') || '-1')
                    );
                    return;
                }

                if (sliderAddButton) {
                    const blockElement = sliderAddButton.closest('[data-block-id]');
                    addSliderItem(blockElement ? String(blockElement.dataset.blockId || '') : '');
                    return;
                }

                if (sliderRemoveButton) {
                    const blockElement = sliderRemoveButton.closest('[data-block-id]');
                    removeSliderItem(
                        blockElement ? String(blockElement.dataset.blockId || '') : '',
                        Number(sliderRemoveButton.getAttribute('data-item-index') || '-1')
                    );
                    return;
                }

                if (closeButton && !ui.settingsModalInstance) {
                    ui.settingsBlockId = null;
                    closeSettingsModal();
                }
            });

            settingsModalElement.addEventListener('input', function (event) {
                const blockField = event.target.closest('[data-block-field]');
                const socialField = event.target.closest('[data-social-field]');
                const sliderField = event.target.closest('[data-slider-field]');

                if (socialField) {
                    updateSocialItemField(
                        String(socialField.getAttribute('data-block-id') || ''),
                        Number(socialField.getAttribute('data-item-index') || '-1'),
                        String(socialField.getAttribute('data-social-field') || ''),
                        socialField.value
                    );
                    sync();
                    return;
                }

                if (sliderField) {
                    updateSliderItemField(
                        String(sliderField.getAttribute('data-block-id') || ''),
                        Number(sliderField.getAttribute('data-item-index') || '-1'),
                        String(sliderField.getAttribute('data-slider-field') || ''),
                        sliderField.value
                    );
                    sync();
                    return;
                }

                if (blockField) {
                    const patch = {};
                    patch[String(blockField.getAttribute('data-block-field') || '')] = blockField.value;
                    updateBlock(String(blockField.getAttribute('data-block-id') || ''), patch);
                    sync();
                }
            });

            settingsModalElement.addEventListener('change', function (event) {
                const toggleField = event.target.closest('[data-block-toggle]');
                const blockField = event.target.closest('[data-block-field]');
                const socialField = event.target.closest('[data-social-field]');
                const sliderField = event.target.closest('[data-slider-field]');

                if (toggleField && toggleField.type === 'checkbox') {
                    const patch = {};
                    patch[String(toggleField.getAttribute('data-block-toggle') || '')] = !!toggleField.checked;
                    updateBlock(String(toggleField.getAttribute('data-block-id') || ''), patch);
                    render();
                    sync();
                    return;
                }

                if (blockField && blockField.tagName === 'SELECT') {
                    const patch = {};
                    patch[String(blockField.getAttribute('data-block-field') || '')] = blockField.value;
                    updateBlock(String(blockField.getAttribute('data-block-id') || ''), patch);
                    render();
                    sync();
                    return;
                }

                if (socialField) {
                    updateSocialItemField(
                        String(socialField.getAttribute('data-block-id') || ''),
                        Number(socialField.getAttribute('data-item-index') || '-1'),
                        String(socialField.getAttribute('data-social-field') || ''),
                        socialField.value
                    );
                    render();
                    sync();
                    return;
                }

                if (sliderField) {
                    updateSliderItemField(
                        String(sliderField.getAttribute('data-block-id') || ''),
                        Number(sliderField.getAttribute('data-item-index') || '-1'),
                        String(sliderField.getAttribute('data-slider-field') || ''),
                        sliderField.value
                    );
                    render();
                    sync();
                }
            });

            settingsModalElement.addEventListener('hidden.bs.modal', function () {
                ui.settingsBlockId = null;
                renderSettingsModal();
            });
        }

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected' || !ui.picker) {
                return;
            }

            if (String(event.data.field || '') !== ui.picker.token) {
                return;
            }

            applyPickerValue(event.data.value || '');
        });

        window.addEventListener('storage', function (event) {
            if (event.key !== fileSelectionStorageKey || !event.newValue || !ui.picker) {
                return;
            }

            let payload = null;
            try {
                payload = JSON.parse(String(event.newValue || ''));
            } catch (error) {
                payload = null;
            }

            if (!payload || payload.type !== 'fireball:file:selected' || String(payload.field || '') !== ui.picker.token) {
                return;
            }

            applyPickerValue(payload.value || '');

            try {
                localStorage.removeItem(fileSelectionStorageKey);
            } catch (error) {
            }
        });

        window.addEventListener('focus', function () {
            if (!ui.picker) {
                return;
            }

            let payload = null;
            try {
                payload = JSON.parse(String(localStorage.getItem(fileSelectionStorageKey) || ''));
            } catch (error) {
                payload = null;
            }

            if (!payload || payload.type !== 'fireball:file:selected' || String(payload.field || '') !== ui.picker.token) {
                return;
            }

            applyPickerValue(payload.value || '');

            try {
                localStorage.removeItem(fileSelectionStorageKey);
            } catch (error) {
            }
        });

        if (textarea.form) {
            textarea.form.addEventListener('submit', function () {
                textarea.value = serializeBlocksToHtml();
            });
        }
    }

    function ensureActiveBlock() {
        if (!state.blocks.length) {
            ui.activeBlockId = null;
            return;
        }

        if (!ui.activeBlockId || !findBlock(ui.activeBlockId)) {
            ui.activeBlockId = state.blocks[0].id;
        }
    }

    function findBlock(id) {
        return state.blocks.find(function (block) {
            return block.id === id;
        }) || null;
    }

    function findBlockIndex(id) {
        return state.blocks.findIndex(function (block) {
            return block.id === id;
        });
    }

    function reorderBlocks(sourceId, targetId) {
        const sourceIndex = findBlockIndex(sourceId);
        const targetIndex = findBlockIndex(targetId);
        if (sourceIndex === -1 || targetIndex === -1 || sourceIndex === targetIndex) {
            return;
        }

        const moved = state.blocks.splice(sourceIndex, 1)[0];
        state.blocks.splice(targetIndex, 0, moved);
        ui.activeBlockId = moved.id;
        render();
        sync();
    }

    function defaultBlockData(type) {
        if (type === 'heading') {
            return { level: 'h2', html: '' };
        }
        if (type === 'image') {
            return { src: '', alt: '', caption: '', link: '' };
        }
        if (type === 'video') {
            return { src: '', poster: '', caption: '' };
        }
        if (type === 'html') {
            return { html: '' };
        }
        if (type === 'social') {
            return { items: [getDefaultSocialItem('telegram')] };
        }
        if (type === 'slider') {
            return {
                aspectRatio: '16x9',
                showBullets: true,
                showArrows: true,
                items: [getDefaultSliderItem()]
            };
        }
        if (type === 'code') {
            return { language: 'html', code: '' };
        }

        return { html: '' };
    }

    function getAllowedBlockType(type) {
        const allowedTypes = ['text', 'heading', 'image', 'video', 'html', 'social', 'slider', 'code'];
        return allowedTypes.indexOf(String(type || '')) !== -1 ? String(type) : 'text';
    }

    function makeId() {
        return 'block_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function makeDomId(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '') || makeId();
    }

    function cloneData(data) {
        return JSON.parse(JSON.stringify(data || {}));
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function sanitizeHtml(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-root="1">' + String(html || '') + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-root="1"]');
        if (!root) {
            return '';
        }

        root.querySelectorAll('script,style,object,embed,form,input,button,textarea,select').forEach(function (node) {
            node.remove();
        });

        root.querySelectorAll('*').forEach(function (node) {
            Array.from(node.attributes).forEach(function (attribute) {
                const name = attribute.name.toLowerCase();
                const value = String(attribute.value || '');

                if (name.indexOf('on') === 0) {
                    node.removeAttribute(attribute.name);
                    return;
                }

                if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value)) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return root.innerHTML.trim();
    }

    function extractDimensionValue(value) {
        const match = String(value || '').match(/(\d+(?:\.\d+)?)/);
        return match ? parseFloat(match[1]) : 0;
    }

    function getNodeDimension(node, attributeName, styleName) {
        const attributeValue = extractDimensionValue(node.getAttribute(attributeName));
        if (attributeValue > 0) {
            return attributeValue;
        }

        return extractDimensionValue(node.style[styleName]);
    }

    function buildAdaptivePreviewHtml(html) {
        const cleanHtml = sanitizeHtml(html || '');
        if (cleanHtml === '') {
            return '';
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-preview-root="1">' + cleanHtml + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-preview-root="1"]');
        if (!root) {
            return cleanHtml;
        }

        root.querySelectorAll('*').forEach(function (node) {
            node.style.maxWidth = '100%';
            node.style.boxSizing = 'border-box';

            if (node.querySelector('iframe,video,embed,object')) {
                node.style.width = '100%';
                node.style.marginLeft = 'auto';
                node.style.marginRight = 'auto';
            }

            if (node.matches('img,video,canvas,svg')) {
                node.style.maxWidth = '100%';
                node.style.height = 'auto';
            }

            if (node.matches('iframe,embed,object')) {
                const width = getNodeDimension(node, 'width', 'width');
                const height = getNodeDimension(node, 'height', 'height');

                node.style.width = '100%';
                node.style.maxWidth = '100%';

                if (width > 0 && height > 0) {
                    node.style.aspectRatio = width + ' / ' + height;
                    node.style.height = 'auto';
                } else if (!node.style.height) {
                    node.style.minHeight = '320px';
                }
            }

            if (node.matches('table')) {
                node.style.width = '100%';
                node.style.display = 'block';
            }
        });

        return root.innerHTML.trim();
    }

    function normalizeFontTags(scope) {
        scope.querySelectorAll('font[size]').forEach(function (fontNode) {
            const size = String(fontNode.getAttribute('size') || '');
            const span = document.createElement('span');
            if (fontSizeCssMap[size]) {
                span.style.fontSize = fontSizeCssMap[size];
            }
            span.innerHTML = fontNode.innerHTML;
            fontNode.replaceWith(span);
        });
    }

    function saveSelection(editor) {
        if (!editor || typeof window.getSelection !== 'function') {
            return;
        }

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);
        const commonAncestor = range.commonAncestorContainer;
        if (!editor.contains(commonAncestor) && commonAncestor !== editor) {
            return;
        }

        ui.selection = {
            blockId: String(editor.getAttribute('data-block-id') || ''),
            range: range.cloneRange()
        };
    }

    function restoreSelection(editor) {
        if (!editor || !ui.selection || ui.selection.blockId !== String(editor.getAttribute('data-block-id') || '') || typeof window.getSelection !== 'function') {
            return false;
        }

        try {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(ui.selection.range.cloneRange());
            return true;
        } catch (error) {
            return false;
        }
    }

    function applyCommand(editor, command, value) {
        if (!editor) {
            return;
        }

        editor.focus();
        restoreSelection(editor);
        document.execCommand('styleWithCSS', false, true);

        if (command === 'createLink') {
            const url = window.prompt(labels.linkPrompt, 'https://');
            if (!url) {
                return;
            }
            document.execCommand('createLink', false, url);
        } else if (command === 'formatBlock') {
            document.execCommand('formatBlock', false, value);
        } else if (command === 'hiliteColor') {
            document.execCommand('hiliteColor', false, value);
            document.execCommand('backColor', false, value);
        } else if (command === 'fontSize') {
            document.execCommand('fontSize', false, fontSizeCommandMap[value] || '4');
        } else {
            document.execCommand(command, false, value);
        }

        syncRichEditor(editor);
    }

    function syncRichEditor(editor) {
        normalizeFontTags(editor);
        updateBlock(String(editor.getAttribute('data-block-id') || ''), {
            html: sanitizeHtml(editor.innerHTML)
        });
    }

    function focusBlockEditor(blockId) {
        window.requestAnimationFrame(function () {
            const richEditor = app.querySelector('[data-block-rich][data-block-id="' + blockId + '"]');
            if (richEditor) {
                richEditor.focus();
                placeCaretAtEnd(richEditor);
                return;
            }

            const headingEditor = app.querySelector('[data-block-heading][data-block-id="' + blockId + '"]');
            if (headingEditor) {
                headingEditor.focus();
                placeCaretAtEnd(headingEditor);
            }
        });
    }

    function placeCaretAtEnd(element) {
        if (!element || typeof window.getSelection !== 'function') {
            return;
        }

        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function openPicker(blockId, field, itemIndex) {
        if (!config.fileManagerUrl || !blockId) {
            return;
        }

        const token = 'post_builder_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
        const url = new URL(config.fileManagerUrl, window.location.origin);
        url.searchParams.set('picker', '1');
        url.searchParams.set('field', token);
        url.searchParams.set('dir', String(config.defaultDirectory || 'posts'));

        ui.picker = {
            token: token,
            blockId: blockId,
            field: field,
            itemIndex: typeof itemIndex === 'number' && !Number.isNaN(itemIndex) && itemIndex >= 0 ? itemIndex : null
        };

        window.open(url.toString(), popupName, 'width=1280,height=860,resizable=yes,scrollbars=yes');
    }

    function applyPickerValue(value) {
        if (!ui.picker) {
            return;
        }

        if (ui.picker.itemIndex !== null) {
            updateSliderItemField(ui.picker.blockId, ui.picker.itemIndex, ui.picker.field, String(value || ''));
        } else {
            const patch = {};
            patch[ui.picker.field] = String(value || '');
            updateBlock(ui.picker.blockId, patch);
        }

        ui.activeBlockId = ui.picker.blockId;
        ui.picker = null;
        render();
        sync();
    }

    function openDeleteModal() {
        if (!deleteModalElement) {
            return;
        }

        if (ui.deleteModalInstance) {
            ui.deleteModalInstance.show();
            return;
        }

        deleteModalElement.classList.add('is-open');
        deleteModalElement.style.display = 'block';
        deleteModalElement.removeAttribute('aria-hidden');
        document.body.classList.add('fb-modal-open');
    }

    function closeDeleteModal() {
        if (!deleteModalElement) {
            return;
        }

        if (ui.deleteModalInstance) {
            ui.deleteModalInstance.hide();
            return;
        }

        deleteModalElement.classList.remove('is-open');
        deleteModalElement.style.display = 'none';
        deleteModalElement.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fb-modal-open');
    }

    function toggleStylePanel(button) {
        const wrap = button.closest('[data-style-wrap]');
        if (!wrap) {
            return;
        }

        const willOpen = !wrap.classList.contains('is-open');
        closeStylePanels();
        wrap.classList.toggle('is-open', willOpen);
    }

    function closeStylePanels() {
        app.querySelectorAll('[data-style-wrap]').forEach(function (wrap) {
            wrap.classList.remove('is-open');
        });
    }

    function getDefaultSocialItem(network) {
        const meta = getSocialNetworkMeta(network || 'telegram');
        return {
            network: meta.value,
            icon: meta.icon,
            label: meta.label,
            url: ''
        };
    }

    function getDefaultSliderItem() {
        return {
            image: '',
            alt: '',
            title: '',
            text: ''
        };
    }

    function getSocialNetworkMeta(network) {
        const value = String(network || '').trim().toLowerCase();
        for (let index = 0; index < socialNetworkOptions.length; index += 1) {
            if (socialNetworkOptions[index].value === value) {
                return socialNetworkOptions[index];
            }
        }

        return socialNetworkOptions[socialNetworkOptions.length - 1];
    }

    function getSocialIconMeta(icon) {
        const value = String(icon || '').trim().toLowerCase();
        for (let index = 0; index < socialIconOptions.length; index += 1) {
            if (socialIconOptions[index].value === value) {
                return socialIconOptions[index];
            }
        }

        return {
            value: value || 'ci-share-2',
            label: value || labels.socialIcon
        };
    }

    function normalizeSocialItems(items) {
        if (!Array.isArray(items) || !items.length) {
            return [getDefaultSocialItem('telegram')];
        }

        return items.map(function (item) {
            const source = item && typeof item === 'object' ? item : {};
            const meta = getSocialNetworkMeta(source.network || 'custom');
            return {
                network: meta.value,
                icon: String(source.icon || '').trim() || meta.icon,
                label: String(source.label || '').trim() || meta.label,
                url: String(source.url || '').trim()
            };
        });
    }

    function normalizeSliderItems(items) {
        if (!Array.isArray(items) || !items.length) {
            return [getDefaultSliderItem()];
        }

        return items.map(function (item) {
            const source = item && typeof item === 'object' ? item : {};
            return {
                image: String(source.image || '').trim(),
                alt: String(source.alt || '').trim(),
                title: String(source.title || '').trim(),
                text: String(source.text || '').trim()
            };
        });
    }

    function updateSocialItemField(blockId, itemIndex, field, value) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'social' || itemIndex < 0) {
            return;
        }

        const items = normalizeSocialItems(block.data.items);
        if (!items[itemIndex]) {
            return;
        }

        if (field === 'network') {
            const oldMeta = getSocialNetworkMeta(items[itemIndex].network);
            const newMeta = getSocialNetworkMeta(value);
            items[itemIndex].network = newMeta.value;

            if (!items[itemIndex].label || items[itemIndex].label === oldMeta.label) {
                items[itemIndex].label = newMeta.label;
            }
            if (!items[itemIndex].icon || items[itemIndex].icon === oldMeta.icon) {
                items[itemIndex].icon = newMeta.icon;
            }
        } else {
            items[itemIndex][field] = String(value || '');
        }

        block.data.items = items;
    }

    function addSocialItem(blockId) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'social') {
            return;
        }

        const items = normalizeSocialItems(block.data.items);
        items.push(getDefaultSocialItem('custom'));
        block.data.items = items;
        render();
        sync();
    }

    function removeSocialItem(blockId, itemIndex) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'social' || itemIndex < 0) {
            return;
        }

        const items = normalizeSocialItems(block.data.items);
        if (items.length <= 1) {
            block.data.items = [getDefaultSocialItem('telegram')];
        } else {
            items.splice(itemIndex, 1);
            block.data.items = items;
        }

        render();
        sync();
    }

    function updateSliderItemField(blockId, itemIndex, field, value) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'slider' || itemIndex < 0) {
            return;
        }

        const items = normalizeSliderItems(block.data.items);
        if (!items[itemIndex]) {
            return;
        }

        items[itemIndex][field] = String(value || '');
        block.data.items = items;
    }

    function addSliderItem(blockId) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'slider') {
            return;
        }

        const items = normalizeSliderItems(block.data.items);
        items.push(getDefaultSliderItem());
        block.data.items = items;
        render();
        sync();
    }

    function removeSliderItem(blockId, itemIndex) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'slider' || itemIndex < 0) {
            return;
        }

        const items = normalizeSliderItems(block.data.items);
        if (items.length <= 1) {
            block.data.items = [getDefaultSliderItem()];
        } else {
            items.splice(itemIndex, 1);
            block.data.items = items;
        }

        render();
        sync();
    }

    function renderInsertControl(anchor, position) {
        const isOpen = ui.addMenuAnchor === anchor;
        return '' +
            '<div class="fb-post-editor__add-wrap' + (isOpen ? ' is-open' : '') + '" data-add-wrap="' + escapeAttr(anchor) + '">' +
                '<button class="btn btn-outline-secondary btn-sm fb-post-editor__add-btn" type="button" data-editor-open-add data-menu-anchor="' + escapeAttr(anchor) + '" data-insert-position="' + escapeAttr(String(position)) + '">' +
                    '<i class="ci-plus"></i><span>' + escapeHtml(labels.addBlock) + '</span>' +
                '</button>' +
                (isOpen
                    ? '<div class="fb-post-add-menu">' +
                        renderAddTypeButton('text', position, labels.addText) +
                        renderAddTypeButton('heading', position, labels.addHeading) +
                        renderAddTypeButton('image', position, labels.addImage) +
                        renderAddTypeButton('video', position, labels.addVideo) +
                        renderAddTypeButton('html', position, labels.addHtml) +
                        renderAddTypeButton('social', position, labels.addSocial) +
                        renderAddTypeButton('slider', position, labels.addSlider) +
                        renderAddTypeButton('code', position, labels.addCode) +
                    '</div>'
                    : '') +
            '</div>';
    }

    function renderAddTypeButton(type, position, label) {
        return '<button type="button" data-editor-add="' + escapeAttr(type) + '" data-insert-position="' + escapeAttr(String(position)) + '">' + escapeHtml(label) + '</button>';
    }

    function renderBlockCard(block, index) {
        const isActive = block.id === ui.activeBlockId;
        return '' +
            '<article class="fb-post-block' + (isActive ? ' is-active' : '') + '" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="fb-post-block__head">' +
                    '<div class="fb-post-block__title">' +
                        '<button class="fb-post-block__drag" type="button" draggable="true" data-block-drag-handle title="' + escapeAttr(labels.drag) + '" aria-label="' + escapeAttr(labels.drag) + '">⋮⋮</button>' +
                        '<span class="fb-post-block__icon">' + renderBlockIcon(block.type) + '</span>' +
                        '<span>' + escapeHtml(blockTitle(block.type)) + '</span>' +
                    '</div>' +
                    '<div class="fb-post-block__actions">' +
                        '<div class="fb-post-block__meta">' + renderBlockMeta(block, index) + '</div>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="move-up" title="' + escapeAttr(labels.moveUp) + '" aria-label="' + escapeAttr(labels.moveUp) + '"><i class="ci-arrow-up"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="move-down" title="' + escapeAttr(labels.moveDown) + '" aria-label="' + escapeAttr(labels.moveDown) + '"><i class="ci-arrow-down"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="duplicate" title="' + escapeAttr(labels.duplicate) + '" aria-label="' + escapeAttr(labels.duplicate) + '"><i class="ci-copy"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="settings" title="' + escapeAttr(labels.settings) + '" aria-label="' + escapeAttr(labels.settings) + '"><i class="ci-settings"></i></button>' +
                        '<button class="btn btn-outline-danger btn-sm" type="button" data-block-action="remove" title="' + escapeAttr(labels.remove) + '" aria-label="' + escapeAttr(labels.remove) + '"><i class="ci-trash"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="fb-post-block__content" data-block-selectable data-block-id="' + escapeAttr(block.id) + '">' + renderBlockContent(block) + '</div>' +
            '</article>';
    }

    function renderBlockIcon(type) {
        const icon = blockTypeIcons[type] || '?';
        return String(icon).indexOf('ci-') === 0
            ? '<i class="' + escapeAttr(icon) + '" aria-hidden="true"></i>'
            : escapeHtml(icon);
    }

    function blockTitle(type) {
        if (type === 'heading') {
            return labels.headingBlock;
        }
        if (type === 'image') {
            return labels.imageBlock;
        }
        if (type === 'video') {
            return labels.videoBlock;
        }
        if (type === 'html') {
            return labels.htmlBlock;
        }
        if (type === 'social') {
            return labels.socialBlock;
        }
        if (type === 'slider') {
            return labels.sliderBlock;
        }
        if (type === 'code') {
            return labels.codeBlock;
        }
        return labels.textBlock;
    }

    function renderBlockMeta(block, index) {
        let html = '<span class="fb-post-block__chip">#' + escapeHtml(String(index + 1)) + '</span>';

        if (block.type === 'heading') {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(block.data.level || 'h2').toUpperCase()) + '</span>';
        } else if (block.type === 'image' && block.data.src) {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(block.data.src).split('/').pop()) + '</span>';
        } else if (block.type === 'video' && block.data.src) {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(block.data.src).split('/').pop()) + '</span>';
        } else if (block.type === 'social') {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(normalizeSocialItems(block.data.items).length)) + '</span>';
        } else if (block.type === 'slider') {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(normalizeSliderItems(block.data.items).length)) + '</span>';
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(block.data.aspectRatio || '16x9')) + '</span>';
        } else if (block.type === 'code') {
            html += '<span class="fb-post-block__chip">' + escapeHtml(String(block.data.language || 'html')) + '</span>';
        } else if (block.type === 'html') {
            html += '<span class="fb-post-block__chip">HTML</span>';
        }

        return html;
    }

    function renderBlockContent(block) {
        if (block.type === 'heading') {
            return '' +
                '<div class="fb-post-block__heading" contenteditable="true" data-block-heading data-block-id="' + escapeAttr(block.id) + '" data-placeholder="' + escapeAttr(labels.headingPlaceholder) + '">' + sanitizeHtml(block.data.html || '') + '</div>';
        }

        if (block.type === 'image' || block.type === 'video') {
            return '' +
                '<div class="fb-post-block__media">' +
                    '<div class="fb-post-block__preview">' + renderMediaPreview(block) + '</div>' +
                '</div>';
        }

        if (block.type === 'html') {
            return '' +
                '<div class="fb-post-block__stack">' +
                    '<textarea class="form-control fb-post-block__code fb-post-block__code--html" data-block-field="html" data-block-id="' + escapeAttr(block.id) + '" spellcheck="false" placeholder="' + escapeAttr(labels.htmlPlaceholder) + '">' + escapeHtml(block.data.html || '') + '</textarea>' +
                    '<div class="fb-post-block__subhead">' + escapeHtml(labels.htmlPreview) + '</div>' +
                    '<div class="fb-post-block__html-preview" data-block-html-preview>' + buildAdaptivePreviewHtml(block.data.html || '') + '</div>' +
                '</div>';
        }

        if (block.type === 'social') {
            return renderSocialPreview(block);
        }

        if (block.type === 'slider') {
            return renderSliderPreview(block);
        }

        if (block.type === 'code') {
            return '<textarea class="form-control fb-post-block__code" data-block-field="code" data-block-id="' + escapeAttr(block.id) + '" spellcheck="false" placeholder="' + escapeAttr(labels.codePlaceholder) + '">' + escapeHtml(block.data.code || '') + '</textarea>';
        }

        return renderTextBlock(block);
    }

    function renderTextBlock(block) {
        return '' +
            '<div class="fb-post-block__toolbar" data-block-toolbar>' +
                '<div class="fb-post-block__toolbar-group">' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="bold" title="' + escapeAttr(labels.bold) + '" aria-label="' + escapeAttr(labels.bold) + '"><strong>B</strong></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="italic" title="' + escapeAttr(labels.italic) + '" aria-label="' + escapeAttr(labels.italic) + '"><em>I</em></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="underline" title="' + escapeAttr(labels.underline) + '" aria-label="' + escapeAttr(labels.underline) + '"><u>U</u></button>' +
                '</div>' +
                '<div class="fb-post-block__toolbar-group">' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="insertUnorderedList" title="' + escapeAttr(labels.bulletList) + '" aria-label="' + escapeAttr(labels.bulletList) + '"><i class="ci-list"></i></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="formatBlock" data-editor-value="blockquote" title="' + escapeAttr(labels.quote) + '" aria-label="' + escapeAttr(labels.quote) + '">&ldquo;</button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="createLink" title="' + escapeAttr(labels.link) + '" aria-label="' + escapeAttr(labels.link) + '"><i class="ci-link"></i></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="unlink" title="' + escapeAttr(labels.unlink) + '" aria-label="' + escapeAttr(labels.unlink) + '">/</button>' +
                '</div>' +
                '<div class="fb-post-block__toolbar-group">' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="justifyLeft" title="' + escapeAttr(labels.alignLeft) + '" aria-label="' + escapeAttr(labels.alignLeft) + '"><i class="ci-align-left"></i></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="justifyCenter" title="' + escapeAttr(labels.alignCenter) + '" aria-label="' + escapeAttr(labels.alignCenter) + '"><i class="ci-align-center"></i></button>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-editor-command="justifyRight" title="' + escapeAttr(labels.alignRight) + '" aria-label="' + escapeAttr(labels.alignRight) + '"><i class="ci-align-right"></i></button>' +
                '</div>' +
                '<div class="fb-post-block__style" data-style-wrap>' +
                    '<button class="btn btn-sm btn-outline-secondary" type="button" data-style-toggle>' + escapeHtml(labels.style) + '</button>' +
                    '<div class="fb-post-style-menu">' +
                        '<div class="fb-post-style-menu__title">' + escapeHtml(labels.moreStyles) + '</div>' +
                        '<label class="fb-post-style-menu__field">' +
                            '<span>' + escapeHtml(labels.font) + '</span>' +
                            '<select class="form-select form-select-sm" data-editor-command="fontName">' +
                                '<option value="">' + escapeHtml(labels.font) + '</option>' +
                                renderOptions(fonts, '') +
                            '</select>' +
                        '</label>' +
                        '<label class="fb-post-style-menu__field">' +
                            '<span>' + escapeHtml(labels.size) + '</span>' +
                            '<select class="form-select form-select-sm" data-editor-font-size>' +
                                '<option value="">' + escapeHtml(labels.size) + '</option>' +
                                renderOptions(sizes, '') +
                            '</select>' +
                        '</label>' +
                        '<label class="fb-post-style-menu__field">' +
                            '<span>' + escapeHtml(labels.textColor) + '</span>' +
                            '<input class="form-control form-control-color" type="color" value="#111827" data-editor-command="foreColor">' +
                        '</label>' +
                        '<label class="fb-post-style-menu__field">' +
                            '<span>' + escapeHtml(labels.background) + '</span>' +
                            '<input class="form-control form-control-color" type="color" value="#fff1a8" data-editor-command="hiliteColor">' +
                        '</label>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="fb-post-block__rich" contenteditable="true" data-block-rich data-block-id="' + escapeAttr(block.id) + '" data-placeholder="' + escapeAttr(labels.textPlaceholder) + '">' + sanitizeHtml(block.data.html || '') + '</div>';
    }

    function renderMediaPreview(block) {
        if (block.type === 'image' && block.data.src) {
            return '<img src="' + escapeAttr(block.data.src) + '" alt="' + escapeAttr(block.data.alt || '') + '">';
        }

        if (block.type === 'video' && block.data.src) {
            const embedUrl = getVideoEmbedUrl(block.data.src);
            if (embedUrl !== '') {
                return '<iframe src="' + escapeAttr(embedUrl) + '" allowfullscreen loading="lazy"></iframe>';
            }

            return '<video controls playsinline' + (block.data.poster ? ' poster="' + escapeAttr(block.data.poster) + '"' : '') + '><source src="' + escapeAttr(block.data.src) + '" type="' + escapeAttr(getVideoMimeType(block.data.src)) + '"></video>';
        }

        return '<div class="fb-post-block__placeholder-text">' + escapeHtml(labels.sourceLink) + '</div>';
    }

    function renderSocialPreview(block) {
        const items = normalizeSocialItems(block.data.items);
        return '<div class="fb-social-buttons fb-social-buttons--editor">' + items.map(function (item) {
            return '' +
                '<div class="fb-social-buttons__item' + (String(item.url || '').trim() === '' ? ' is-disabled' : '') + '">' +
                    '<i class="fb-social-buttons__icon ' + escapeAttr(item.icon) + '" aria-hidden="true"></i>' +
                    '<span class="fb-social-buttons__label">' + escapeHtml(item.label) + '</span>' +
                '</div>';
        }).join('') + '</div>';
    }

    function renderSliderPreview(block) {
        const items = normalizeSliderItems(block.data.items);
        const aspectRatio = sliderAspectRatioOptions.some(function (option) {
            return option.value === String(block.data.aspectRatio || '');
        }) ? String(block.data.aspectRatio) : '16x9';

        return '<div class="fb-post-block__slider-track">' + items.map(function (item, index) {
            return '' +
                '<div class="fb-post-block__slider-card">' +
                    '<div class="fb-post-block__slider-figure ratio ratio-' + escapeAttr(aspectRatio) + '">' +
                        (item.image
                            ? '<img src="' + escapeAttr(item.image) + '" alt="' + escapeAttr(item.alt) + '">'
                            : '<div class="fb-post-block__slider-placeholder">' + escapeHtml(String(index + 1)) + '</div>') +
                    '</div>' +
                    '<div class="fb-post-block__slider-copy">' +
                        '<strong>' + escapeHtml(item.title || (labels.sliderSlide + ' ' + String(index + 1))) + '</strong>' +
                        (item.text ? '<span>' + escapeHtml(item.text) + '</span>' : '') +
                    '</div>' +
                '</div>';
        }).join('') + '</div>';
    }

    function renderOptions(items, selectedValue) {
        return items.map(function (item) {
            const value = String(item.value || '');
            return '<option value="' + escapeAttr(value) + '"' + (value === selectedValue ? ' selected' : '') + '>' + escapeHtml(String(item.label || value)) + '</option>';
        }).join('');
    }

    function renderSettingsModal() {
        const block = ui.settingsBlockId ? findBlock(ui.settingsBlockId) : null;
        if (!settingsModalElement || !settingsModalTitleElement || !settingsModalBodyElement) {
            return;
        }

        if (!block) {
            settingsModalTitleElement.textContent = labels.blockSettings;
            settingsModalBodyElement.innerHTML = '';
            return;
        }

        settingsModalTitleElement.textContent = blockTitle(block.type);
        settingsModalBodyElement.innerHTML = renderSettingsFields(block);
    }

    function openSettingsModal() {
        renderSettingsModal();

        if (!settingsModalElement) {
            return;
        }

        if (ui.settingsModalInstance) {
            ui.settingsModalInstance.show();
            return;
        }

        settingsModalElement.classList.add('is-open');
        settingsModalElement.style.display = 'block';
        settingsModalElement.removeAttribute('aria-hidden');
        document.body.classList.add('fb-modal-open');
    }

    function closeSettingsModal() {
        if (!settingsModalElement) {
            return;
        }

        if (ui.settingsModalInstance) {
            ui.settingsModalInstance.hide();
            return;
        }

        settingsModalElement.classList.remove('is-open');
        settingsModalElement.style.display = 'none';
        settingsModalElement.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fb-modal-open');
    }

    function renderSettingsFields(block) {
        if (block.type === 'heading') {
            return '' +
                '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                    '<label class="form-label">' + escapeHtml(labels.headingLevel) + '</label>' +
                    '<select class="form-select" data-block-field="level" data-block-id="' + escapeAttr(block.id) + '">' +
                        renderOptions([
                            { value: 'h1', label: 'H1' },
                            { value: 'h2', label: 'H2' },
                            { value: 'h3', label: 'H3' },
                            { value: 'h4', label: 'H4' }
                        ], String(block.data.level || 'h2')) +
                    '</select>' +
                '</div>';
        }

        if (block.type === 'image') {
            return renderImageSettings(block);
        }

        if (block.type === 'video') {
            return renderVideoSettings(block);
        }

        if (block.type === 'social') {
            return renderSocialSettings(block);
        }

        if (block.type === 'slider') {
            return renderSliderSettings(block);
        }

        if (block.type === 'code') {
            return '' +
                '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                    '<label class="form-label">' + escapeHtml(labels.codeLanguage) + '</label>' +
                    '<input class="form-control" type="text" value="' + escapeAttr(block.data.language || '') + '" data-block-field="language" data-block-id="' + escapeAttr(block.id) + '">' +
                '</div>';
        }

        if (block.type === 'html') {
            return '<p class="fb-post-settings__note">' + escapeHtml(labels.htmlHint) + '</p>';
        }

        return '<p class="fb-post-settings__note">' + escapeHtml(labels.inlineSettingsHint) + '</p>';
    }

    function renderImageSettings(block) {
        return '' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="mb-3">' +
                    '<label class="form-label">' + escapeHtml(labels.sourceLink) + '</label>' +
                    '<div class="input-group">' +
                        '<input class="form-control" type="text" value="' + escapeAttr(block.data.src || '') + '" data-block-field="src" data-block-id="' + escapeAttr(block.id) + '">' +
                        '<button class="btn btn-outline-secondary" type="button" data-block-picker="src">' + escapeHtml(labels.chooseFile) + '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.imageAlt) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.alt || '') + '" data-block-field="alt" data-block-id="' + escapeAttr(block.id) + '"></div>' +
                '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.imageCaption) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.caption || '') + '" data-block-field="caption" data-block-id="' + escapeAttr(block.id) + '"></div>' +
                '<div><label class="form-label">' + escapeHtml(labels.imageLink) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.link || '') + '" data-block-field="link" data-block-id="' + escapeAttr(block.id) + '"></div>' +
            '</div>';
    }

    function renderVideoSettings(block) {
        return '' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="mb-3">' +
                    '<label class="form-label">' + escapeHtml(labels.sourceLink) + '</label>' +
                    '<div class="input-group">' +
                        '<input class="form-control" type="text" value="' + escapeAttr(block.data.src || '') + '" data-block-field="src" data-block-id="' + escapeAttr(block.id) + '">' +
                        '<button class="btn btn-outline-secondary" type="button" data-block-picker="src">' + escapeHtml(labels.chooseFile) + '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="mb-3">' +
                    '<label class="form-label">' + escapeHtml(labels.videoPoster) + '</label>' +
                    '<div class="input-group">' +
                        '<input class="form-control" type="text" value="' + escapeAttr(block.data.poster || '') + '" data-block-field="poster" data-block-id="' + escapeAttr(block.id) + '">' +
                        '<button class="btn btn-outline-secondary" type="button" data-block-picker="poster">' + escapeHtml(labels.chooseFile) + '</button>' +
                    '</div>' +
                '</div>' +
                '<div><label class="form-label">' + escapeHtml(labels.videoCaption) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.caption || '') + '" data-block-field="caption" data-block-id="' + escapeAttr(block.id) + '"></div>' +
            '</div>';
    }

    function renderSocialSettings(block) {
        const items = normalizeSocialItems(block.data.items);
        let html = '' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<p class="fb-post-settings__note">' + escapeHtml(labels.socialItemsHint) + '</p>' +
                '<div class="fb-post-settings__stack">';

        items.forEach(function (item, index) {
            const networkMeta = getSocialNetworkMeta(item.network);
            const iconMeta = getSocialIconMeta(item.icon);
            html += '' +
                '<div class="fb-post-settings__card" data-block-id="' + escapeAttr(block.id) + '">' +
                    '<div class="fb-post-settings__card-head">' +
                        '<div class="fb-post-settings__badge"><i class="' + escapeAttr(item.icon) + '" aria-hidden="true"></i><span>' + escapeHtml(item.label || networkMeta.label) + '</span></div>' +
                        '<button class="btn btn-outline-danger btn-sm" type="button" data-social-remove-item data-item-index="' + escapeAttr(String(index)) + '">' + escapeHtml(labels.socialRemoveItem) + '</button>' +
                    '</div>' +
                    '<div class="fb-post-settings__grid">' +
                        '<div><label class="form-label">' + escapeHtml(labels.socialNetwork) + '</label><select class="form-select" data-social-field="network" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + renderOptions(socialNetworkOptions.map(function (itemOption) { return { value: itemOption.value, label: itemOption.label }; }), item.network) + '</select></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.socialIcon) + '</label><select class="form-select" data-social-field="icon" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + renderOptions(socialIconOptions.map(function (itemOption) { return { value: itemOption.value, label: itemOption.label + ' (' + itemOption.value + ')' }; }), item.icon) + '</select><div class="form-text">' + escapeHtml(iconMeta.label) + '</div></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.socialLabel) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.label) + '" data-social-field="label" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.socialUrl) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.url) + '" data-social-field="url" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                    '</div>' +
                '</div>';
        });

        html += '' +
                '</div>' +
                '<button class="btn btn-outline-secondary rounded-pill mt-3" type="button" data-social-add-item>' + escapeHtml(labels.socialAddItem) + '</button>' +
            '</div>';

        return html;
    }

    function renderSliderSettings(block) {
        const items = normalizeSliderItems(block.data.items);
        let html = '' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.sliderAspectRatio) + '</label><select class="form-select" data-block-field="aspectRatio" data-block-id="' + escapeAttr(block.id) + '">' + renderOptions(sliderAspectRatioOptions, String(block.data.aspectRatio || '16x9')) + '</select></div>' +
                '<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="slider-bullets-' + escapeAttr(block.id) + '" data-block-toggle="showBullets" data-block-id="' + escapeAttr(block.id) + '"' + (block.data.showBullets !== false ? ' checked' : '') + '><label class="form-check-label" for="slider-bullets-' + escapeAttr(block.id) + '">' + escapeHtml(labels.sliderShowBullets) + '</label></div>' +
                '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="slider-arrows-' + escapeAttr(block.id) + '" data-block-toggle="showArrows" data-block-id="' + escapeAttr(block.id) + '"' + (block.data.showArrows !== false ? ' checked' : '') + '><label class="form-check-label" for="slider-arrows-' + escapeAttr(block.id) + '">' + escapeHtml(labels.sliderShowArrows) + '</label></div>' +
            '</div>' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="fb-post-settings__stack">';

        items.forEach(function (item, index) {
            html += '' +
                '<div class="fb-post-settings__card" data-block-id="' + escapeAttr(block.id) + '">' +
                    '<div class="fb-post-settings__card-head">' +
                        '<div class="fb-post-settings__badge"><i class="ci-sliders" aria-hidden="true"></i><span>' + escapeHtml(item.title || (labels.sliderSlide + ' ' + String(index + 1))) + '</span></div>' +
                        '<button class="btn btn-outline-danger btn-sm" type="button" data-slider-remove-item data-item-index="' + escapeAttr(String(index)) + '">' + escapeHtml(labels.sliderRemoveItem) + '</button>' +
                    '</div>' +
                    '<div class="fb-post-settings__grid">' +
                        '<div><label class="form-label">' + escapeHtml(labels.sliderImage) + '</label><div class="input-group"><input class="form-control" type="text" value="' + escapeAttr(item.image) + '" data-slider-field="image" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '"><button class="btn btn-outline-secondary" type="button" data-slider-picker="image" data-item-index="' + escapeAttr(String(index)) + '">' + escapeHtml(labels.chooseFile) + '</button></div></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.sliderAlt) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.alt) + '" data-slider-field="alt" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.sliderTitle) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.title) + '" data-slider-field="title" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                        '<div><label class="form-label">' + escapeHtml(labels.sliderText) + '</label><textarea class="form-control" rows="3" data-slider-field="text" data-block-id="' + escapeAttr(block.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + escapeHtml(item.text) + '</textarea></div>' +
                    '</div>' +
                '</div>';
        });

        html += '' +
                '</div>' +
                '<button class="btn btn-outline-secondary rounded-pill mt-3" type="button" data-slider-add-item>' + escapeHtml(labels.sliderAddItem) + '</button>' +
            '</div>';

        return html;
    }

    function getVideoEmbedUrl(source) {
        const url = String(source || '').trim();
        const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]+)/i);
        if (youtubeMatch) {
            return 'https://www.youtube.com/embed/' + youtubeMatch[1];
        }

        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/i);
        if (vimeoMatch) {
            return 'https://player.vimeo.com/video/' + vimeoMatch[1];
        }

        return '';
    }

    function getVideoMimeType(source) {
        const url = String(source || '').toLowerCase();
        if (/\.m3u8(?:$|\?)/.test(url)) {
            return 'application/vnd.apple.mpegurl';
        }
        if (/\.webm(?:$|\?)/.test(url)) {
            return 'video/webm';
        }
        if (/\.ogg(?:$|\?)/.test(url) || /\.ogv(?:$|\?)/.test(url)) {
            return 'video/ogg';
        }
        return 'video/mp4';
    }

    function serializeBlocksToHtml() {
        return state.blocks.map(function (block) {
            if (block.type === 'heading') {
                const level = /^h[1-6]$/.test(String(block.data.level || '')) ? String(block.data.level) : 'h2';
                const html = sanitizeHtml(block.data.html || '');
                return html ? '<' + level + '>' + html + '</' + level + '>' : '';
            }

            if (block.type === 'image') {
                const src = String(block.data.src || '').trim();
                if (!src) {
                    return '';
                }

                let imageTag = '<img src="' + escapeAttr(src) + '" alt="' + escapeAttr(block.data.alt || '') + '" class="rounded-4">';
                if (String(block.data.link || '').trim() !== '') {
                    imageTag = '<a href="' + escapeAttr(block.data.link) + '" target="_blank" rel="noopener noreferrer">' + imageTag + '</a>';
                }

                if (String(block.data.caption || '').trim() !== '') {
                    return '<figure>' + imageTag + '<figcaption>' + escapeHtml(block.data.caption) + '</figcaption></figure>';
                }

                return imageTag;
            }

            if (block.type === 'video') {
                const src = String(block.data.src || '').trim();
                if (!src) {
                    return '';
                }

                const embedUrl = getVideoEmbedUrl(src);
                const caption = String(block.data.caption || '').trim();
                if (embedUrl !== '') {
                    return '<div class="ratio ratio-16x9"><iframe src="' + escapeAttr(embedUrl) + '" title="' + escapeAttr(caption || labels.addVideo) + '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>' +
                        (caption ? '<p>' + escapeHtml(caption) + '</p>' : '');
                }

                const poster = String(block.data.poster || '').trim();
                return '<div data-plyr-player-wrap=""><video controls playsinline data-plyr-player=""' + (poster ? ' poster="' + escapeAttr(poster) + '"' : '') + '><source src="' + escapeAttr(src) + '" type="' + escapeAttr(getVideoMimeType(src)) + '"></video></div>' +
                    (caption ? '<p>' + escapeHtml(caption) + '</p>' : '');
            }

            if (block.type === 'html') {
                return sanitizeHtml(block.data.html || '');
            }

            if (block.type === 'social') {
                const items = normalizeSocialItems(block.data.items).filter(function (item) {
                    return String(item.url || '').trim() !== '';
                });

                if (!items.length) {
                    return '';
                }

                return '<div class="fb-social-buttons" data-fb-social-buttons="1">' + items.map(function (item) {
                    return '<a class="fb-social-buttons__item" href="' + escapeAttr(item.url) + '" target="_blank" rel="noopener noreferrer" data-network="' + escapeAttr(item.network) + '" data-icon="' + escapeAttr(item.icon) + '"><i class="fb-social-buttons__icon ' + escapeAttr(item.icon) + '" aria-hidden="true"></i><span class="fb-social-buttons__label">' + escapeHtml(item.label) + '</span></a>';
                }).join('') + '</div>';
            }

            if (block.type === 'slider') {
                const items = normalizeSliderItems(block.data.items);
                if (!items.length) {
                    return '';
                }

                const aspectRatio = sliderAspectRatioOptions.some(function (option) {
                    return option.value === String(block.data.aspectRatio || '');
                }) ? String(block.data.aspectRatio) : '16x9';
                const aspectRatioStyleMap = {
                    '16x9': 'calc(9 / 16 * 100%)',
                    '4x3': 'calc(3 / 4 * 100%)',
                    '1x1': 'calc(1 / 1 * 100%)'
                };
                const showBullets = block.data.showBullets !== false;
                const showArrows = block.data.showArrows !== false;
                const sliderDomId = makeDomId(block.id || makeId());
                const prevButtonId = 'prev-' + sliderDomId;
                const nextButtonId = 'next-' + sliderDomId;
                const wrapperId = 'swiper-wrapper-' + sliderDomId;
                const aspectRatioStyle = aspectRatioStyleMap[aspectRatio] || aspectRatioStyleMap['16x9'];
                const swiperConfig = { effect: 'fade', loop: items.length > 1 };

                if (showBullets) {
                    swiperConfig.pagination = { el: '.swiper-pagination', clickable: true };
                }
                if (showArrows) {
                    swiperConfig.navigation = { prevEl: '#' + prevButtonId, nextEl: '#' + nextButtonId };
                }

                return '' +
                    '<div class="position-relative py-3 py-md-4 px-5 mb-3" data-fb-slider-shell="1">' +
                        (showArrows
                            ? '<button type="button" id="' + escapeAttr(prevButtonId) + '" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-start position-absolute top-50 start-0 translate-middle-y mt-n3 z-2" aria-label="' + escapeAttr(labels.sliderPrev) + '" aria-controls="' + escapeAttr(wrapperId) + '" data-fb-slider-prev="1"><i class="ci-chevron-left fs-lg animate-target"></i></button><button type="button" id="' + escapeAttr(nextButtonId) + '" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-end position-absolute top-50 end-0 translate-middle-y mt-n3 z-2" aria-label="' + escapeAttr(labels.sliderNext) + '" aria-controls="' + escapeAttr(wrapperId) + '" data-fb-slider-next="1"><i class="ci-chevron-right fs-lg animate-target"></i></button>'
                            : '') +
                        '<div class="swiper px-3 swiper-fade" data-fb-slider="1" data-aspect-ratio="' + escapeAttr(aspectRatio) + '" data-swiper=\'' + escapeAttr(JSON.stringify(swiperConfig)) + '\'>' +
                        '<div class="swiper-wrapper" id="' + escapeAttr(wrapperId) + '">' +
                            items.map(function (item, index) {
                                const copyHtml = item.title || item.text
                                    ? '<div class="position-absolute start-0 end-0 bottom-0 p-3 p-md-4 text-white" data-fb-slider-copy="1"><div class="d-grid gap-2"><h3 class="h5 mb-0" data-fb-slider-title="1">' + escapeHtml(item.title) + '</h3>' + (item.text ? '<p class="mb-0 opacity-75" data-fb-slider-text="1">' + escapeHtml(item.text) + '</p>' : '') + '</div></div>'
                                    : '';
                                const imageHtml = item.image
                                    ? '<img src="' + escapeAttr(item.image) + '" alt="' + escapeAttr(item.alt) + '" class="rounded-4 w-100 h-100 object-fit-cover">' + (copyHtml ? '<div class="position-absolute top-0 start-0 w-100 h-100 rounded-4" style="background: linear-gradient(180deg, rgba(17, 24, 39, 0.08) 0%, rgba(17, 24, 39, 0.6) 100%);"></div>' : '')
                                    : '<div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center display-4">' + escapeHtml(item.title || (labels.sliderSlide + ' ' + String(index + 1))) + '</div>';
                                return '<div class="swiper-slide"><div class="ratio bg-body-tertiary position-relative overflow-hidden" style="--cz-aspect-ratio: ' + escapeAttr(aspectRatioStyle) + '">' + imageHtml + copyHtml + '</div></div>';
                            }).join('') +
                        '</div>' +
                        (showBullets ? '<div class="swiper-pagination position-static mt-3"></div>' : '') +
                    '</div></div>';
            }

            if (block.type === 'code') {
                if (String(block.data.code || '').trim() === '') {
                    return '';
                }

                const language = String(block.data.language || 'html').trim() || 'html';
                return '<pre class="fb-code-block" data-language="' + escapeAttr(language) + '"><code class="language-' + escapeAttr(language) + '">' + escapeHtml(block.data.code || '') + '</code></pre>';
            }

            return sanitizeHtml(block.data.html || '');
        }).filter(function (item) {
            return String(item || '').trim() !== '';
        }).join('\n');
    }

    function importBlocksFromHtml(html) {
        const cleanHtml = String(html || '').trim();
        if (!cleanHtml) {
            return [];
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-import-root="1">' + cleanHtml + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-import-root="1"]');
        if (!root) {
            return [];
        }

        const blocks = [];
        const buffer = [];

        function flushTextBuffer() {
            if (!buffer.length) {
                return;
            }
            blocks.push(createImportedBlock('text', { html: sanitizeHtml(buffer.join('')) }));
            buffer.length = 0;
        }

        Array.from(root.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = String(node.textContent || '').trim();
                if (text) {
                    buffer.push('<p>' + escapeHtml(text) + '</p>');
                }
                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            const tag = node.tagName.toLowerCase();

            if (/^h[1-6]$/.test(tag)) {
                flushTextBuffer();
                blocks.push(createImportedBlock('heading', {
                    level: tag,
                    html: sanitizeHtml(node.innerHTML)
                }));
                return;
            }

            if (tag === 'pre') {
                flushTextBuffer();
                blocks.push(parseCodeBlock(node));
                return;
            }

            if (tag === 'img' || tag === 'figure') {
                flushTextBuffer();
                blocks.push(parseImageBlock(node));
                return;
            }

            if (tag === 'iframe' || tag === 'video' || node.matches('[data-plyr-player-wrap]')) {
                flushTextBuffer();
                blocks.push(parseVideoBlock(node));
                return;
            }

            if (node.matches('.swiper') && node.querySelector('.swiper-wrapper')) {
                flushTextBuffer();
                blocks.push(parseSliderBlock(node));
                return;
            }

            if (node.matches('[data-fb-social-buttons], .fb-social-buttons')) {
                flushTextBuffer();
                blocks.push(parseSocialBlock(node.outerHTML));
                return;
            }

            buffer.push(node.outerHTML);
        });

        flushTextBuffer();
        return blocks;
    }

    function createImportedBlock(type, data) {
        return {
            id: makeId(),
            type: type,
            data: Object.assign(defaultBlockData(type), cloneData(data))
        };
    }

    function parseImageBlock(node) {
        const image = node.tagName && node.tagName.toLowerCase() === 'img' ? node : node.querySelector('img');
        const link = node.querySelector('a');
        const caption = node.querySelector('figcaption');

        return createImportedBlock('image', {
            src: image ? String(image.getAttribute('src') || '') : '',
            alt: image ? String(image.getAttribute('alt') || '') : '',
            caption: caption ? String(caption.textContent || '') : '',
            link: link ? String(link.getAttribute('href') || '') : ''
        });
    }

    function parseVideoBlock(node) {
        let source = '';
        let poster = '';

        if (node.matches('iframe')) {
            source = String(node.getAttribute('src') || '');
        } else {
            const video = node.matches('video') ? node : node.querySelector('video');
            const sourceNode = video ? video.querySelector('source') : null;
            source = sourceNode ? String(sourceNode.getAttribute('src') || '') : (video ? String(video.getAttribute('src') || '') : '');
            poster = video ? String(video.getAttribute('poster') || '') : '';
        }

        return createImportedBlock('video', {
            src: source,
            poster: poster,
            caption: ''
        });
    }

    function parseCodeBlock(node) {
        const codeNode = node.querySelector('code');
        let language = String(node.getAttribute('data-language') || '');

        if (!language && codeNode) {
            const match = String(codeNode.getAttribute('class') || '').match(/language-([a-z0-9_-]+)/i);
            language = match ? match[1] : '';
        }

        return createImportedBlock('code', {
            language: language || 'html',
            code: codeNode ? String(codeNode.textContent || '') : String(node.textContent || '')
        });
    }

    function parseSocialBlock(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-social-root="1">' + String(html || '') + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-social-root="1"]');
        const wrapper = root ? (root.querySelector('[data-fb-social-buttons="1"]') || root.querySelector('.fb-social-buttons')) : null;

        if (!wrapper) {
            return createImportedBlock('social', { items: [getDefaultSocialItem('telegram')] });
        }

        const items = Array.from(wrapper.querySelectorAll('a')).map(function (linkNode) {
            const meta = getSocialNetworkMeta(linkNode.getAttribute('data-network') || 'custom');
            const iconNode = linkNode.querySelector('i[class*="ci-"]');
            const iconClass = iconNode ? String(iconNode.className || '').match(/ci-[a-z0-9-]+/i) : null;
            const labelNode = linkNode.querySelector('.fb-social-buttons__label');

            return {
                network: meta.value,
                icon: iconClass ? iconClass[0] : meta.icon,
                label: labelNode ? String(labelNode.textContent || '').trim() : String(linkNode.textContent || '').trim(),
                url: String(linkNode.getAttribute('href') || '').trim()
            };
        });

        return createImportedBlock('social', {
            items: normalizeSocialItems(items)
        });
    }

    function parseSliderBlock(node) {
        const aspectRatio = String(node.getAttribute('data-aspect-ratio') || '').trim();
        const items = Array.from(node.querySelectorAll('.swiper-wrapper > .swiper-slide')).map(function (slideNode) {
            const imageNode = slideNode.querySelector('img');
            const copyRoot = slideNode.querySelector('[data-fb-slider-copy]');
            const titleNode = copyRoot ? copyRoot.querySelector('[data-fb-slider-title]') : slideNode.querySelector('[data-fb-slider-title], h1, h2, h3, h4, h5, h6');
            const textNode = copyRoot ? copyRoot.querySelector('[data-fb-slider-text]') : slideNode.querySelector('[data-fb-slider-text], p');
            return {
                image: imageNode ? String(imageNode.getAttribute('src') || '').trim() : '',
                alt: imageNode ? String(imageNode.getAttribute('alt') || '').trim() : '',
                title: titleNode ? String(titleNode.textContent || '').trim() : '',
                text: textNode ? String(textNode.textContent || '').trim() : ''
            };
        });

        return createImportedBlock('slider', {
            aspectRatio: sliderAspectRatioOptions.some(function (option) {
                return option.value === aspectRatio;
            }) ? aspectRatio : '16x9',
            showBullets: !!node.querySelector('.swiper-pagination'),
            showArrows: !!(node.querySelector('[data-fb-slider-prev]') || node.querySelector('[data-fb-slider-next]') || node.querySelector('.btn-prev') || node.querySelector('.btn-next')),
            items: normalizeSliderItems(items)
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initPostDatepicker();
    initPostEditor();
});
