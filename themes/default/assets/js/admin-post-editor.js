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
        addNewsletter: 'Newsletter CTA',
        addCode: 'Code',
        textBlock: 'Text block',
        headingBlock: 'Heading block',
        imageBlock: 'Image block',
        videoBlock: 'Video block',
        htmlBlock: 'HTML block',
        socialBlock: 'Social buttons block',
        sliderBlock: 'Slider block',
        newsletterBlock: 'Newsletter block',
        codeBlock: 'Code block',
        moveUp: 'Move up',
        moveDown: 'Move down',
        remove: 'Remove',
        hide: 'Hide block',
        show: 'Show block',
        hidden: 'Hidden',
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
        formRequiredSummary: 'Fill in required fields:',
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
        newsletterTitle: 'Title',
        newsletterText: 'Text',
        newsletterButton: 'Button text',
        newsletterUrl: 'Button link',
        newsletterIcon: 'Button icon',
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
        newsletter: 'ci-mail',
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
        { value: 'phone', label: labels.socialPhone, icon: 'ci-phone' },
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
    let state = parseInitialState();
    const ui = {
        activeBlockId: state.blocks[0] ? state.blocks[0].id : null,
        addMenuAnchor: null,
        addMenuPosition: state.blocks.length,
        pendingRemoveBlockId: null,
        settingsBlockId: null,
        draggedBlockId: null,
        pointerDrag: null,
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
        const data = Object.assign(defaultBlockData(type), cloneData(block.data));
        if ((type === 'text' || type === 'heading') && typeof data.html === 'string') {
            data.html = sanitizeHtml(data.html);
        }

        return {
            id: String(block.id || makeId()),
            type: type,
            hidden: block.hidden === true,
            data: data
        };
    }

    function render() {
        ensureAppStructure();
        ensureActiveBlock();
        closeAllAddDropdowns();

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
        refreshAllCodeEditors();
        normalizeRenderedEditors();
    }

    function sync() {
        textarea.value = JSON.stringify(state, null, 2);
        textarea.dispatchEvent(new CustomEvent('fireball:post-editor-sync', { bubbles: true }));
    }

    function ensureAppStructure() {
        const topMenu = renderInsertControl('top', state.blocks.length);
        app.innerHTML = '' +
            '<div class="fb-post-editor__bar">' +
                '<div class="fb-post-editor__bar-copy">' +
                    '<strong>' + escapeHtml(labels.canvasTitle) + '</strong>' +
                    '<span>' + escapeHtml(labels.blockCount) + ': <span data-editor-block-count>' + escapeHtml(String(state.blocks.length)) + '</span></span>' +
                '</div>' +
                '<div class="fb-post-editor__insert fb-post-editor__insert--top">' + topMenu + '</div>' +
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
        revealBlockEditor(block.id);
    }

    function createBlock(type) {
        const blockType = getAllowedBlockType(type);
        return {
            id: makeId(),
            type: blockType,
            hidden: false,
            data: defaultBlockData(blockType)
        };
    }

    function toggleBlockVisibility(id) {
        const block = findBlock(id);
        if (!block) {
            return;
        }

        block.hidden = block.hidden !== true;
        render();
        sync();
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
            hidden: block.hidden === true,
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

            if (!openAddButton && !addTypeButton) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            }

            if (openAddButton) {
                toggleAddDropdown(openAddButton);
                return;
            }

            hideAddDropdown(getAddDropdownWrapFromElement(addTypeButton));
            addBlock(
                String(addTypeButton.getAttribute('data-editor-add') || 'text'),
                Number(addTypeButton.getAttribute('data-insert-position') || state.blocks.length)
            );
        }, true);

        document.addEventListener('click', function (event) {
            const addTypeButton = event.target.closest('.fb-post-add-menu--portal [data-editor-add]');
            const wrap = getAddDropdownWrapFromElement(addTypeButton);

            if (!addTypeButton || !wrap || !app.contains(wrap)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            }

            hideAddDropdown(wrap);
            addBlock(
                String(addTypeButton.getAttribute('data-editor-add') || 'text'),
                Number(addTypeButton.getAttribute('data-insert-position') || state.blocks.length)
            );
        }, true);

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
                event.preventDefault();
                event.stopPropagation();
                toggleAddDropdown(openAddButton);
                return;
            }

            if (addTypeButton) {
                hideAddDropdown(getAddDropdownWrapFromElement(addTypeButton));
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

                if (action === 'toggle-visibility') {
                    toggleBlockVisibility(blockId);
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
            if (event.target.closest('[data-style-wrap]')) {
                return;
            }

            const blockElement = event.target.closest('[data-block-id]');
            if (blockElement) {
                setActiveBlock(String(blockElement.dataset.blockId || ''));
            }
        });

        app.addEventListener('paste', function (event) {
            const editor = event.target.closest('[data-block-rich], [data-block-heading]');
            if (!editor) {
                return;
            }

            const clipboard = event.clipboardData || window.clipboardData;
            if (!clipboard) {
                return;
            }

            const cleanHtml = buildEditorPasteHtml(
                String(clipboard.getData('text/html') || ''),
                String(clipboard.getData('text/plain') || '')
            );
            if (cleanHtml === '') {
                return;
            }

            event.preventDefault();
            editor.focus();
            restoreSelection(editor);
            document.execCommand('insertHTML', false, cleanHtml);

            if (editor.matches('[data-block-heading]')) {
                updateBlock(
                    String(editor.getAttribute('data-block-id') || ''),
                    { html: sanitizeHtml(editor.innerHTML) }
                );
                return;
            }

            syncRichEditor(editor);
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
                refreshNewsletterBlockContent(blockId);

                if (fieldName === 'html') {
                    refreshHtmlBlockPreview(blockField);
                }
                if (fieldName === 'html' || fieldName === 'code' || fieldName === 'language') {
                    refreshCodeEditor(blockField.closest('[data-code-editor]'));
                    refreshCodeEditor(findRenderedBlockElement(blockId) ? findRenderedBlockElement(blockId).querySelector('[data-code-editor]') : null);
                }
                return;
            }

            if (colorField) {
                const blockElement = colorField.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                applyCommand(editor, String(colorField.getAttribute('data-editor-command') || ''), String(colorField.value || ''));
            }
        });

        app.addEventListener('scroll', function (event) {
            const codeInput = event.target.closest('[data-code-input]');
            if (!codeInput) {
                return;
            }

            syncCodeEditorScroll(codeInput);
        }, true);

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
                updateBlockStyle(
                    String(blockElement ? blockElement.dataset.blockId || '' : ''),
                    String(commandSelect.getAttribute('data-editor-command') || ''),
                    String(commandSelect.value || ''),
                    editor
                );
                if (commandSelect.value !== '') {
                    applyCommand(editor, String(commandSelect.getAttribute('data-editor-command') || ''), String(commandSelect.value || ''));
                }
                return;
            }

            if (fontSizeSelect) {
                const blockElement = fontSizeSelect.closest('[data-block-id]');
                const editor = blockElement ? blockElement.querySelector('[data-block-rich]') : null;
                updateBlockStyle(
                    String(blockElement ? blockElement.dataset.blockId || '' : ''),
                    'fontSize',
                    String(fontSizeSelect.value || ''),
                    editor
                );
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

        app.addEventListener('pointerdown', function (event) {
            const handle = event.target.closest('[data-block-drag-handle]');
            if (!handle) {
                return;
            }

            startPointerBlockDrag(event, handle);
        });

        app.addEventListener('dragstart', function (event) {
            const handle = event.target.closest('[data-block-drag-handle]');
            if (!handle) {
                return;
            }

            const blockElement = handle.closest('.fb-post-block[data-block-id]');
            ui.draggedBlockId = blockElement ? String(blockElement.dataset.blockId || '') : null;
            if (event.dataTransfer && ui.draggedBlockId) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', ui.draggedBlockId);
            }
        });

        app.addEventListener('dragover', function (event) {
            const blockElement = event.target.closest('.fb-post-block[data-block-id]');
            if (!blockElement || !ui.draggedBlockId) {
                return;
            }

            event.preventDefault();
            const rect = blockElement.getBoundingClientRect();
            const dropPosition = event.clientY > rect.top + (rect.height / 2) ? 'after' : 'before';
            applyBlockDropTarget({ blockElement: blockElement, position: dropPosition });
        });

        app.addEventListener('drop', function (event) {
            const blockElement = event.target.closest('.fb-post-block[data-block-id]');
            if (!blockElement || !ui.draggedBlockId) {
                return;
            }

            event.preventDefault();
            const rect = blockElement.getBoundingClientRect();
            const dropPosition = event.clientY > rect.top + (rect.height / 2) ? 'after' : 'before';
            reorderBlocks(ui.draggedBlockId, String(blockElement.dataset.blockId || ''), dropPosition);
            ui.draggedBlockId = null;
        });

        app.addEventListener('dragend', function () {
            ui.draggedBlockId = null;
            cleanupBlockDragState();
        });

        app.addEventListener('shown.bs.dropdown', function (event) {
            const wrap = event.target ? event.target.closest('[data-add-wrap]') : null;
            if (wrap) {
                wrap.classList.add('is-open');
            }
        });

        app.addEventListener('hidden.bs.dropdown', function (event) {
            const wrap = event.target ? event.target.closest('[data-add-wrap]') : null;
            if (wrap) {
                wrap.classList.remove('is-open');
            }
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
            const clickedInsideAddMenu = !!target.closest('[data-add-wrap], .fb-post-add-menu');
            const clickedInsideStyleMenu = !!target.closest('[data-style-wrap]');
            const hasOpenAddMenu = !!app.querySelector('[data-add-wrap].is-open');

            if (!clickedInsideStyleMenu) {
                closeStylePanels();
            }

            if (!hasOpenAddMenu || clickedInsideAddMenu) {
                return;
            }

            closeAllAddDropdowns();
        });

        window.addEventListener('resize', positionOpenAddDropdown, { passive: true });
        window.addEventListener('scroll', positionOpenAddDropdown, { passive: true });
        window.addEventListener('resize', positionOpenStylePanel, { passive: true });
        window.addEventListener('scroll', positionOpenStylePanel, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', positionOpenAddDropdown, { passive: true });
            window.visualViewport.addEventListener('scroll', positionOpenAddDropdown, { passive: true });
            window.visualViewport.addEventListener('resize', positionOpenStylePanel, { passive: true });
            window.visualViewport.addEventListener('scroll', positionOpenStylePanel, { passive: true });
        }

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
                    const blockId = String(blockField.getAttribute('data-block-id') || '');
                    const fieldName = String(blockField.getAttribute('data-block-field') || '');
                    const patch = {};
                    patch[fieldName] = blockField.value;
                    updateBlock(blockId, patch);
                    refreshMediaBlockContent(blockId, fieldName);
                    refreshNewsletterBlockContent(blockId);
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
            textarea.form.addEventListener('fireball:post-editor-serialize', function () {
                textarea.value = serializeBlocksToHtml();
            });

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

    function findRenderedBlockElement(id) {
        return Array.from(app.querySelectorAll('.fb-post-block[data-block-id]')).find(function (element) {
            return String(element.dataset.blockId || '') === String(id || '');
        }) || null;
    }

    function refreshMediaBlockContent(blockId, fieldName) {
        const block = findBlock(blockId);
        if (!block || (block.type !== 'image' && block.type !== 'video')) {
            return;
        }

        if (['src', 'poster'].indexOf(String(fieldName || '')) === -1) {
            return;
        }

        const blockElement = findRenderedBlockElement(blockId);
        const contentElement = blockElement ? blockElement.querySelector('.fb-post-block__content') : null;
        if (contentElement) {
            contentElement.innerHTML = renderBlockContent(block);
        }
    }

    function refreshNewsletterBlockContent(blockId) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'newsletter') {
            return;
        }

        const blockElement = findRenderedBlockElement(blockId);
        const contentElement = blockElement ? blockElement.querySelector('.fb-post-block__content') : null;
        if (contentElement) {
            contentElement.innerHTML = renderBlockContent(block);
        }
    }

    function refreshHtmlBlockPreview(field) {
        if (!field) {
            return;
        }

        const blockElement = field.closest('.fb-post-block');
        const preview = blockElement ? blockElement.querySelector('[data-block-html-preview]') : null;
        if (preview) {
            preview.innerHTML = buildAdaptivePreviewHtml(field.value);
        }
    }

    function cleanupBlockDropTargets() {
        app.querySelectorAll('.fb-post-block[data-block-id]').forEach(function (item) {
            item.classList.remove('is-drop-target', 'is-drop-before', 'is-drop-after');
        });
    }

    function cleanupBlockDragState() {
        cleanupBlockDropTargets();
        app.querySelectorAll('.fb-post-block.is-dragging').forEach(function (item) {
            item.classList.remove('is-dragging');
        });
        document.body.classList.remove('fb-post-editor-is-dragging');
    }

    function applyBlockDropTarget(candidate) {
        cleanupBlockDropTargets();
        if (!candidate || !candidate.blockElement) {
            return;
        }

        candidate.blockElement.classList.add('is-drop-target', candidate.position === 'after' ? 'is-drop-after' : 'is-drop-before');
    }

    function getBlockDropCandidate(clientX, clientY) {
        const element = document.elementFromPoint(clientX, clientY);
        const blockElement = element ? element.closest('.fb-post-block[data-block-id]') : null;

        if (!blockElement || !ui.draggedBlockId || String(blockElement.dataset.blockId || '') === ui.draggedBlockId) {
            return null;
        }

        const rect = blockElement.getBoundingClientRect();
        return {
            blockElement: blockElement,
            position: clientY > rect.top + (rect.height / 2) ? 'after' : 'before'
        };
    }

    function startPointerBlockDrag(event, handle) {
        if (event.button !== undefined && event.button !== 0) {
            return;
        }

        const blockElement = handle.closest('.fb-post-block[data-block-id]');
        if (!blockElement) {
            return;
        }

        event.preventDefault();
        closeAddMenu();

        ui.draggedBlockId = String(blockElement.dataset.blockId || '');
        ui.pointerDrag = {
            pointerId: event.pointerId,
            handle: handle,
            sourceElement: blockElement,
            targetId: null,
            position: null
        };

        blockElement.classList.add('is-dragging');
        document.body.classList.add('fb-post-editor-is-dragging');

        if (handle.setPointerCapture && event.pointerId !== undefined) {
            try {
                handle.setPointerCapture(event.pointerId);
            } catch (error) {
            }
        }

        document.addEventListener('pointermove', updatePointerBlockDrag, { passive: false });
        document.addEventListener('pointerup', finishPointerBlockDrag);
        document.addEventListener('pointercancel', cancelPointerBlockDrag);
    }

    function updatePointerBlockDrag(event) {
        if (!ui.pointerDrag || (event.pointerId !== undefined && event.pointerId !== ui.pointerDrag.pointerId)) {
            return;
        }

        event.preventDefault();
        const candidate = getBlockDropCandidate(event.clientX, event.clientY);
        ui.pointerDrag.targetId = candidate && candidate.blockElement ? String(candidate.blockElement.dataset.blockId || '') : null;
        ui.pointerDrag.position = candidate ? candidate.position : null;
        applyBlockDropTarget(candidate);
    }

    function finishPointerBlockDrag(event) {
        if (!ui.pointerDrag || (event.pointerId !== undefined && event.pointerId !== ui.pointerDrag.pointerId)) {
            return;
        }

        event.preventDefault();
        const targetId = ui.pointerDrag.targetId;
        const position = ui.pointerDrag.position;
        const sourceId = ui.draggedBlockId;
        const handle = ui.pointerDrag.handle;

        if (handle && handle.releasePointerCapture && event.pointerId !== undefined) {
            try {
                handle.releasePointerCapture(event.pointerId);
            } catch (error) {
            }
        }

        document.removeEventListener('pointermove', updatePointerBlockDrag);
        document.removeEventListener('pointerup', finishPointerBlockDrag);
        document.removeEventListener('pointercancel', cancelPointerBlockDrag);
        ui.pointerDrag = null;
        ui.draggedBlockId = null;
        cleanupBlockDragState();

        if (sourceId && targetId && position) {
            reorderBlocks(sourceId, targetId, position);
        }
    }

    function cancelPointerBlockDrag(event) {
        if (!ui.pointerDrag || (event.pointerId !== undefined && event.pointerId !== ui.pointerDrag.pointerId)) {
            return;
        }

        document.removeEventListener('pointermove', updatePointerBlockDrag);
        document.removeEventListener('pointerup', finishPointerBlockDrag);
        document.removeEventListener('pointercancel', cancelPointerBlockDrag);
        ui.pointerDrag = null;
        ui.draggedBlockId = null;
        cleanupBlockDragState();
    }

    function reorderBlocks(sourceId, targetId, position) {
        const sourceIndex = findBlockIndex(sourceId);
        const targetIndex = findBlockIndex(targetId);
        if (sourceIndex === -1 || targetIndex === -1 || sourceIndex === targetIndex) {
            return;
        }

        const moved = state.blocks.splice(sourceIndex, 1)[0];
        let insertIndex = position === 'after' ? targetIndex + 1 : targetIndex;

        if (sourceIndex < insertIndex) {
            insertIndex -= 1;
        }

        state.blocks.splice(Math.max(0, Math.min(insertIndex, state.blocks.length)), 0, moved);
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
        if (type === 'newsletter') {
            return {
                title: 'Sign up to our newsletter',
                text: 'Receive our latest updates about our products & promotions',
                buttonText: 'Subscribe',
                buttonUrl: '',
                buttonIcon: 'ci-mail'
            };
        }
        if (type === 'code') {
            return { language: 'html', code: '' };
        }

        return { html: '', fontName: '', fontSize: '' };
    }

    function getAllowedBlockType(type) {
        const allowedTypes = ['text', 'heading', 'image', 'video', 'html', 'social', 'slider', 'newsletter', 'code'];
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

    function sanitizeEditorStyle(styleValue) {
        const allowed = [
            'color',
            'background-color',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
            'text-decoration-line',
            'vertical-align'
        ];
        const declarations = String(styleValue || '').split(';');
        const clean = [];

        declarations.forEach(function (declaration) {
            const separator = declaration.indexOf(':');
            if (separator === -1) {
                return;
            }

            const property = declaration.slice(0, separator).trim().toLowerCase();
            const value = declaration.slice(separator + 1).trim();

            if (allowed.indexOf(property) === -1 || value === '' || /(?:expression\s*\(|javascript\s*:|url\s*\()/i.test(value)) {
                return;
            }

            clean.push(property + ': ' + value);
        });

        return clean.join('; ');
    }

    function buildEditorPasteHtml(html, plainText) {
        if (html !== '') {
            return sanitizeHtml(html);
        }

        const text = String(plainText || '').replace(/\r\n?/g, '\n').trim();
        if (text === '') {
            return '';
        }

        return text.split(/\n{2,}/).map(function (paragraph) {
            return '<p>' + escapeHtml(paragraph).replace(/\n/g, '<br>') + '</p>';
        }).join('');
    }

    function sanitizeHtml(html, options) {
        const settings = Object.assign({
            htmlBlock: false
        }, options || {});
        const template = document.createElement('template');
        template.innerHTML = String(html || '');
        const root = template.content;
        if (!root) {
            return '';
        }

        const blockedSelector = settings.htmlBlock
            ? 'script'
            : 'script,style,object,embed,form,input,button,textarea,select';

        root.querySelectorAll(blockedSelector).forEach(function (node) {
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

                if (name === 'srcdoc') {
                    node.removeAttribute(attribute.name);
                    return;
                }

                if (!settings.htmlBlock && (name === 'class' || name === 'id' || name.indexOf('data-') === 0 || name.indexOf('aria-') === 0)) {
                    node.removeAttribute(attribute.name);
                    return;
                }

                if ((name === 'href' || name === 'src' || name === 'action' || name === 'formaction' || name === 'xlink:href') && /^\s*javascript:/i.test(value)) {
                    node.removeAttribute(attribute.name);
                    return;
                }

                if (name === 'style') {
                    if (settings.htmlBlock) {
                        if (/(?:expression\s*\(|javascript\s*:)/i.test(value)) {
                            node.removeAttribute(attribute.name);
                        }
                        return;
                    }

                    const cleanStyle = sanitizeEditorStyle(value);
                    if (cleanStyle === '') {
                        node.removeAttribute(attribute.name);
                        return;
                    }

                    node.setAttribute(attribute.name, cleanStyle);
                }
            });
        });

        const output = document.createElement('div');
        output.appendChild(root.cloneNode(true));

        return output.innerHTML.trim();
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
        const cleanHtml = sanitizeHtml(html || '', { htmlBlock: true });
        if (cleanHtml === '') {
            return '';
        }

        const template = document.createElement('template');
        template.innerHTML = cleanHtml;
        const root = template.content;
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

        const output = document.createElement('div');
        output.appendChild(root.cloneNode(true));

        return output.innerHTML.trim();
    }

    function normalizeFontTags(scope) {
        scope.querySelectorAll('font[size]').forEach(function (fontNode) {
            const span = document.createElement('span');
            span.innerHTML = fontNode.innerHTML;
            fontNode.replaceWith(span);
        });
    }

    function getAllowedOptionValue(items, value) {
        const normalizedValue = String(value || '').trim();
        const found = items.find(function (item) {
            return String(item.value || '') === normalizedValue;
        });

        return found ? String(found.value || '') : '';
    }

    function updateBlockStyle(blockId, command, value, editor) {
        const block = findBlock(blockId);
        if (!block || block.type !== 'text') {
            return;
        }

        if (command === 'fontName') {
            const fontName = getAllowedOptionValue(fonts, value);
            block.data.fontName = fontName;
            if (editor) {
                editor.style.fontFamily = fontName;
            }
            sync();
            return;
        }

        if (command === 'fontSize') {
            const fontSize = getAllowedOptionValue(sizes, value);
            block.data.fontSize = fontSize;
            if (editor) {
                cleanEditorDom(editor);
                editor.style.fontSize = fontSize;
                syncRichEditor(editor);
                return;
            }
            sync();
        }
    }

    function cleanEditorDom(editor) {
        if (!editor) {
            return;
        }

        const cleanHtml = sanitizeHtml(editor.innerHTML);
        if (editor.innerHTML !== cleanHtml) {
            editor.innerHTML = cleanHtml;
        }
        normalizeEditorLayout(editor);
    }

    function normalizeRenderedEditors() {
        app.querySelectorAll('[data-block-rich]').forEach(function (editor) {
            normalizeEditorLayout(editor);
        });
    }

    function normalizeEditorLayout(editor) {
        if (!editor) {
            return;
        }

        editor.style.lineHeight = '1.6';
        editor.querySelectorAll('*').forEach(function (node) {
            if (!node.style) {
                return;
            }

            node.style.removeProperty('font-size');
            node.style.removeProperty('font-family');
            node.style.removeProperty('line-height');
            node.style.removeProperty('letter-spacing');
            node.style.removeProperty('margin-top');
            node.style.removeProperty('margin-bottom');
            node.style.removeProperty('padding-top');
            node.style.removeProperty('padding-bottom');

            if (node.matches('p,ul,ol,blockquote,pre,table,figure,h1,h2,h3,h4,h5,h6')) {
                node.style.lineHeight = node.matches('h1,h2,h3,h4,h5,h6') ? '1.25' : '1.5';
                node.style.marginTop = node.matches('h1,h2,h3,h4,h5,h6') ? '1.1em' : '0';
                node.style.marginBottom = node.matches('h1,h2,h3,h4,h5,h6') ? '.65em' : '1em';
                return;
            }

            if (node.matches('li,span,a,strong,em,b,i,u')) {
                node.style.lineHeight = 'inherit';
            }
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
            syncRichEditor(editor);
            return;
        } else {
            document.execCommand(command, false, value);
        }

        syncRichEditor(editor);
    }

    function syncRichEditor(editor) {
        normalizeFontTags(editor);
        normalizeEditorLayout(editor);
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
                return;
            }

            const codeEditor = app.querySelector('[data-code-input][data-block-id="' + blockId + '"]');
            if (codeEditor) {
                codeEditor.focus();
                codeEditor.setSelectionRange(codeEditor.value.length, codeEditor.value.length);
            }
        });
    }

    function revealBlockEditor(blockId) {
        window.requestAnimationFrame(function () {
            const blockElement = findRenderedBlockElement(blockId);
            if (blockElement && typeof blockElement.scrollIntoView === 'function') {
                blockElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            focusBlockEditor(blockId);
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

    function encodeStoredSource(value) {
        try {
            return encodeURIComponent(String(value || ''));
        } catch (error) {
            return '';
        }
    }

    function decodeStoredSource(value) {
        const encoded = String(value || '');
        if (encoded === '') {
            return '';
        }

        try {
            return decodeURIComponent(encoded);
        } catch (error) {
            return '';
        }
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
        closeAllAddDropdowns();
        closeStylePanels();
        wrap.classList.toggle('is-open', willOpen);

        if (willOpen) {
            positionStylePanel(wrap);
        }
    }

    function closeStylePanels() {
        app.querySelectorAll('[data-style-wrap]').forEach(function (wrap) {
            const menu = wrap.querySelector('.fb-post-style-menu');
            wrap.classList.remove('is-open');
            clearFloatingMenuStyles(menu);
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

    function normalizePhoneHref(value) {
        const rawValue = String(value || '').trim();
        if (rawValue === '') {
            return '';
        }

        if (/^tel:/i.test(rawValue)) {
            return rawValue.replace(/\s+/g, '');
        }

        const phone = rawValue
            .replace(/[()\s.-]+/g, '')
            .replace(/(?!^\+)[^\d+*#,;]/g, '');

        return phone !== '' ? 'tel:' + phone : '';
    }

    function getSocialHref(item) {
        if (String(item.network || '') === 'phone') {
            return normalizePhoneHref(item.url);
        }

        return String(item.url || '').trim();
    }

    function getSocialLinkAttributes(item) {
        const href = getSocialHref(item);
        if (href === '') {
            return '';
        }

        if (String(item.network || '') === 'phone') {
            return 'href="' + escapeAttr(href) + '"';
        }

        return 'href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer"';
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

    function getAddDropdownButton(wrap) {
        return wrap ? wrap.querySelector('[data-editor-open-add]') : null;
    }

    function getAddDropdownMenu(wrap) {
        if (!wrap) {
            return null;
        }

        const activeMenu = wrap.__fbAddMenu || null;
        if (activeMenu && document.body.contains(activeMenu)) {
            return activeMenu;
        }

        const menu = wrap.querySelector('.fb-post-add-menu');
        if (menu) {
            wrap.__fbAddMenu = menu;
        }

        return menu;
    }

    function getAddDropdownWrapFromMenu(menu) {
        if (!menu) {
            return null;
        }

        return menu.__fbAddWrap || menu.closest('[data-add-wrap]');
    }

    function getAddDropdownWrapFromElement(element) {
        if (!element) {
            return null;
        }

        return element.closest('[data-add-wrap]') || getAddDropdownWrapFromMenu(element.closest('.fb-post-add-menu'));
    }

    function attachAddDropdownToBody(wrap, menu) {
        if (!wrap || !menu || menu.parentNode === document.body) {
            return;
        }

        const placeholder = document.createComment('fb-post-add-menu');
        wrap.__fbAddMenu = menu;
        menu.__fbAddWrap = wrap;
        menu.__fbAddPlaceholder = placeholder;
        menu.setAttribute('data-add-anchor', wrap.getAttribute('data-add-wrap') || '');
        menu.parentNode.insertBefore(placeholder, menu);
        document.body.appendChild(menu);
        menu.classList.add('fb-post-add-menu--portal');
    }

    function restoreAddDropdown(menu) {
        if (!menu) {
            return;
        }

        const wrap = getAddDropdownWrapFromMenu(menu);
        const placeholder = menu.__fbAddPlaceholder || null;

        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(menu, placeholder);
            placeholder.parentNode.removeChild(placeholder);
        } else if (wrap && !wrap.contains(menu)) {
            wrap.appendChild(menu);
        }

        menu.classList.remove('fb-post-add-menu--portal', 'is-open-up');
        menu.removeAttribute('data-add-anchor');
        if (wrap && wrap.__fbAddMenu === menu) {
            delete wrap.__fbAddMenu;
        }
        delete menu.__fbAddWrap;
        delete menu.__fbAddPlaceholder;
    }

    function hideAddDropdown(wrap) {
        const button = getAddDropdownButton(wrap);
        const menu = getAddDropdownMenu(wrap);

        if (!button || !menu) {
            return;
        }

        button.classList.remove('show');
        button.setAttribute('aria-expanded', 'false');
        menu.classList.remove('show');
        clearFloatingMenuStyles(menu);
        restoreAddDropdown(menu);
        wrap.classList.remove('is-open');
        app.classList.remove('has-open-add-menu');
    }

    function closeAllAddDropdowns(exceptWrap) {
        app.querySelectorAll('[data-add-wrap]').forEach(function (item) {
            if (item !== exceptWrap) {
                hideAddDropdown(item);
            }
        });
    }

    function toggleAddDropdown(button) {
        const wrap = button ? button.closest('[data-add-wrap]') : null;
        const menu = getAddDropdownMenu(wrap);

        if (!wrap || !menu) {
            return;
        }

        const shouldOpen = !menu.classList.contains('show');
        closeAllAddDropdowns(wrap);

        button.classList.toggle('show', shouldOpen);
        button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        if (shouldOpen) {
            attachAddDropdownToBody(wrap, menu);
        }
        menu.classList.toggle('show', shouldOpen);
        wrap.classList.toggle('is-open', shouldOpen);
        app.classList.toggle('has-open-add-menu', shouldOpen);

        if (shouldOpen) {
            positionAddDropdown(wrap);
        }
    }

    function getVisibleFixedBottomInset(viewport) {
        const toggle = document.querySelector('.admin-shell-mobile-toggle');
        if (!toggle) {
            return 16;
        }

        const style = window.getComputedStyle(toggle);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return 16;
        }

        const rect = toggle.getBoundingClientRect();
        if (!rect.height) {
            return 16;
        }

        const viewportBottom = viewport
            ? viewport.top + viewport.height
            : (window.innerHeight || document.documentElement.clientHeight);

        return Math.max(16, viewportBottom - rect.top + 12);
    }

    function getViewportMetrics() {
        const visualViewport = window.visualViewport || null;
        return {
            width: visualViewport ? visualViewport.width : (window.innerWidth || document.documentElement.clientWidth),
            height: visualViewport ? visualViewport.height : (window.innerHeight || document.documentElement.clientHeight),
            left: visualViewport ? visualViewport.offsetLeft : 0,
            top: visualViewport ? visualViewport.offsetTop : 0
        };
    }

    function clearFloatingMenuStyles(menu) {
        if (!menu) {
            return;
        }

        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.transform = '';
        menu.style.zIndex = '';
        menu.style.maxHeight = '';
        menu.style.overflowY = '';
        menu.style.width = '';
        menu.classList.remove('is-open-up');
    }

    function positionFloatingMenu(anchor, menu, options) {
        if (!anchor || !menu) {
            return;
        }

        const settings = Object.assign({
            width: 352,
            minWidth: 220,
            zIndex: 1070,
            align: 'center',
            matchAnchor: false,
            forceBelow: false
        }, options || {});
        const rect = anchor.getBoundingClientRect();
        const viewport = getViewportMetrics();
        const margin = 12;
        const bottomInset = getVisibleFixedBottomInset(viewport);
        const availableWidth = Math.max(120, viewport.width - (margin * 2));
        const targetWidth = settings.matchAnchor ? rect.width : settings.width;
        const menuWidth = Math.min(Math.max(settings.minWidth, targetWidth), availableWidth);

        menu.style.position = 'fixed';
        menu.style.right = 'auto';
        menu.style.transform = 'none';
        menu.style.zIndex = String(settings.zIndex);
        menu.style.width = menuWidth + 'px';
        menu.style.maxHeight = '';
        menu.style.overflowY = '';

        const menuRect = menu.getBoundingClientRect();
        const availableBelow = viewport.top + viewport.height - rect.bottom - bottomInset - margin;
        const availableAbove = rect.top - viewport.top - margin;
        const openAbove = !settings.forceBelow && availableBelow < menuRect.height && availableAbove > availableBelow;
        const chosenAvailable = Math.max(0, openAbove ? availableAbove : availableBelow);
        const viewportAvailable = Math.max(0, viewport.height - bottomInset - (margin * 2));
        const maxHeight = Math.max(48, Math.min(chosenAvailable, viewportAvailable));
        const renderedHeight = Math.min(menuRect.height, maxHeight);
        const top = openAbove
            ? Math.max(viewport.top + margin, rect.top - renderedHeight - 8)
            : Math.min(rect.bottom + 8, viewport.top + viewport.height - bottomInset - renderedHeight - margin);
        let preferredLeft = rect.left + (rect.width / 2) - (menuRect.width / 2);

        if (settings.align === 'end') {
            preferredLeft = rect.right - menuRect.width;
        } else if (settings.align === 'start') {
            preferredLeft = rect.left;
        }

        const left = Math.max(viewport.left + margin, Math.min(preferredLeft, viewport.left + viewport.width - menuRect.width - margin));

        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        menu.style.maxHeight = maxHeight + 'px';
        menu.style.overflowY = menuRect.height > maxHeight ? 'auto' : 'visible';
        menu.classList.toggle('is-open-up', openAbove);
    }

    function positionAddDropdown(wrap) {
        const button = getAddDropdownButton(wrap);
        const menu = getAddDropdownMenu(wrap);

        if (!button || !menu || !menu.classList.contains('show')) {
            return;
        }

        const viewport = getViewportMetrics();
        const buttonRect = button.getBoundingClientRect();
        const matchButtonWidth = viewport.width < 768 || buttonRect.width >= 260;

        positionFloatingMenu(button, menu, {
            width: matchButtonWidth ? buttonRect.width : 352,
            minWidth: matchButtonWidth ? Math.min(buttonRect.width, 220) : 220,
            zIndex: 12000,
            align: matchButtonWidth ? 'start' : 'center',
            matchAnchor: matchButtonWidth
        });
    }

    function positionOpenAddDropdown() {
        const openWrap = app.querySelector('[data-add-wrap].is-open');
        if (openWrap) {
            positionAddDropdown(openWrap);
        }
    }

    function positionStylePanel(wrap) {
        const button = wrap ? wrap.querySelector('[data-style-toggle]') : null;
        const menu = wrap ? wrap.querySelector('.fb-post-style-menu') : null;

        if (!button || !menu || !wrap.classList.contains('is-open')) {
            return;
        }

        positionFloatingMenu(button, menu, {
            width: 288,
            minWidth: 220,
            zIndex: 1070,
            align: 'end'
        });
    }

    function positionOpenStylePanel() {
        const openWrap = app.querySelector('[data-style-wrap].is-open');
        if (openWrap) {
            positionStylePanel(openWrap);
        }
    }

    function renderInsertControl(anchor, position) {
        return '' +
            '<div class="dropdown dropdown-center fb-post-editor__add-wrap" data-add-wrap="' + escapeAttr(anchor) + '">' +
                '<button class="btn btn-outline-secondary btn-sm fb-post-editor__add-btn dropdown-toggle" type="button" aria-expanded="false" data-editor-open-add data-menu-anchor="' + escapeAttr(anchor) + '" data-insert-position="' + escapeAttr(String(position)) + '">' +
                    '<i class="ci-plus"></i><span>' + escapeHtml(labels.addBlock) + '</span>' +
                '</button>' +
                '<div class="dropdown-menu fb-post-add-menu" data-add-menu data-add-anchor="' + escapeAttr(anchor) + '">' +
                    renderAddTypeButton('text', position, labels.addText) +
                    renderAddTypeButton('heading', position, labels.addHeading) +
                    renderAddTypeButton('image', position, labels.addImage) +
                    renderAddTypeButton('video', position, labels.addVideo) +
                    renderAddTypeButton('html', position, labels.addHtml) +
                    renderAddTypeButton('social', position, labels.addSocial) +
                    renderAddTypeButton('slider', position, labels.addSlider) +
                    renderAddTypeButton('newsletter', position, labels.addNewsletter) +
                    renderAddTypeButton('code', position, labels.addCode) +
                '</div>' +
            '</div>';
    }

    function renderAddTypeButton(type, position, label) {
        return '<button class="dropdown-item" type="button" data-editor-add="' + escapeAttr(type) + '" data-insert-position="' + escapeAttr(String(position)) + '"><span class="fb-post-add-menu__icon">' + renderBlockIcon(type) + '</span><span>' + escapeHtml(label) + '</span></button>';
    }

    function renderBlockCard(block, index) {
        const isActive = block.id === ui.activeBlockId;
        const isHidden = block.hidden === true;
        const visibilityLabel = isHidden ? labels.show : labels.hide;
        const visibilityIcon = isHidden ? 'ci-eye' : 'ci-eye-off';
        return '' +
            '<article class="fb-post-block' + (isActive ? ' is-active' : '') + (isHidden ? ' is-hidden' : '') + '" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="fb-post-block__head">' +
                    '<div class="fb-post-block__title">' +
                        '<button class="fb-post-block__drag" type="button" data-block-drag-handle title="' + escapeAttr(labels.drag) + '" aria-label="' + escapeAttr(labels.drag) + '">⋮⋮</button>' +
                        '<span class="fb-post-block__icon">' + renderBlockIcon(block.type) + '</span>' +
                        '<span>' + escapeHtml(blockTitle(block.type)) + '</span>' +
                    '</div>' +
                    '<div class="fb-post-block__actions">' +
                        '<div class="fb-post-block__meta">' + renderBlockMeta(block, index) + '</div>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="move-up" title="' + escapeAttr(labels.moveUp) + '" aria-label="' + escapeAttr(labels.moveUp) + '"><i class="ci-arrow-up"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="move-down" title="' + escapeAttr(labels.moveDown) + '" aria-label="' + escapeAttr(labels.moveDown) + '"><i class="ci-arrow-down"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="duplicate" title="' + escapeAttr(labels.duplicate) + '" aria-label="' + escapeAttr(labels.duplicate) + '"><i class="ci-copy"></i></button>' +
                        '<button class="btn btn-outline-secondary btn-sm" type="button" data-block-action="toggle-visibility" title="' + escapeAttr(visibilityLabel) + '" aria-label="' + escapeAttr(visibilityLabel) + '"><i class="' + escapeAttr(visibilityIcon) + '"></i></button>' +
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
        if (type === 'newsletter') {
            return labels.newsletterBlock;
        }
        if (type === 'code') {
            return labels.codeBlock;
        }
        return labels.textBlock;
    }

    function renderBlockMeta(block, index) {
        let html = '<span class="fb-post-block__chip">#' + escapeHtml(String(index + 1)) + '</span>';

        if (block.hidden === true) {
            html += '<span class="fb-post-block__chip fb-post-block__chip--hidden"><i class="ci-eye-off" aria-hidden="true"></i>' + escapeHtml(labels.hidden) + '</span>';
        }

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
        } else if (block.type === 'newsletter') {
            html += '<span class="fb-post-block__chip">' + escapeHtml(block.data.buttonText || labels.newsletterButton) + '</span>';
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
                    renderCodeEditor(block, 'html', block.data.html || '', 'html', labels.htmlPlaceholder, 'fb-post-block__code--html') +
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

        if (block.type === 'newsletter') {
            return renderNewsletterPreview(block);
        }

        if (block.type === 'code') {
            return renderCodeEditor(block, 'code', block.data.code || '', block.data.language || 'html', labels.codePlaceholder, '');
        }

        return renderTextBlock(block);
    }

    function renderCodeEditor(block, fieldName, value, language, placeholder, extraClass) {
        const normalizedLanguage = normalizeCodeLanguage(language || 'html');
        const textareaClass = 'form-control fb-post-block__code' + (extraClass ? ' ' + extraClass : '');
        const editorClass = 'fb-post-block__code-editor' + (fieldName === 'html' ? ' fb-post-block__code-editor--html' : '');
        return '' +
            '<div class="fb-post-block__code-wrap">' +
                '<div class="' + escapeAttr(editorClass) + '" data-code-editor data-code-language="' + escapeAttr(normalizedLanguage) + '">' +
                    '<pre class="fb-post-block__code-highlight" aria-hidden="true"><code class="language-' + escapeAttr(normalizedLanguage) + '">' + escapeHtml(value || '') + '</code></pre>' +
                    '<textarea class="' + escapeAttr(textareaClass) + '" data-code-input data-block-field="' + escapeAttr(fieldName) + '" data-block-id="' + escapeAttr(block.id) + '" spellcheck="false" wrap="off" placeholder="' + escapeAttr(placeholder) + '" style="height: 420px;">' + escapeHtml(value || '') + '</textarea>' +
                '</div>' +
            '</div>';
    }

    function renderTextBlock(block) {
        const fontName = getAllowedOptionValue(fonts, block.data.fontName || '');
        const fontSize = getAllowedOptionValue(sizes, block.data.fontSize || '');
        const richStyle = buildTextBlockStyle(fontName, fontSize);

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
                                renderOptions(fonts, fontName) +
                            '</select>' +
                        '</label>' +
                        '<label class="fb-post-style-menu__field">' +
                            '<span>' + escapeHtml(labels.size) + '</span>' +
                            '<select class="form-select form-select-sm" data-editor-font-size>' +
                                '<option value="">' + escapeHtml(labels.size) + '</option>' +
                                renderOptions(sizes, fontSize) +
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
            '<div class="fb-post-block__rich" contenteditable="true" data-block-rich data-block-id="' + escapeAttr(block.id) + '" data-placeholder="' + escapeAttr(labels.textPlaceholder) + '"' + (richStyle ? ' style="' + escapeAttr(richStyle) + '"' : '') + '>' + sanitizeHtml(block.data.html || '') + '</div>';
    }

    function buildTextBlockStyle(fontName, fontSize) {
        const styles = [];
        if (fontName) {
            styles.push('font-family: ' + fontName);
        }
        if (fontSize) {
            styles.push('font-size: ' + fontSize);
            styles.push('line-height: 1.6');
        }

        return styles.join('; ');
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

    function renderNewsletterPreview(block) {
        const title = String(block.data.title || '').trim() || 'Sign up to our newsletter';
        const text = String(block.data.text || '').trim() || 'Receive our latest updates about our products & promotions';
        const buttonText = String(block.data.buttonText || '').trim() || 'Subscribe';
        const buttonIcon = getSafeIconClass(block.data.buttonIcon || 'ci-mail');

        return '' +
            '<div class="fb-post-block__newsletter d-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5">' +
                '<div class="mb-4 mb-sm-0 me-sm-4">' +
                    '<h3 class="h5 mb-2">' + escapeHtml(title) + '</h3>' +
                    '<p class="fs-sm mb-0">' + escapeHtml(text) + '</p>' +
                '</div>' +
                '<button type="button" class="btn btn-dark">' +
                    (buttonIcon ? '<i class="' + escapeAttr(buttonIcon) + ' fs-base ms-n1 me-2"></i>' : '') +
                    escapeHtml(buttonText) +
                '</button>' +
            '</div>';
    }

    function getSafeIconClass(value) {
        const icon = String(value || '').trim();
        return /^ci-[a-z0-9-]+$/i.test(icon) ? icon : '';
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

        if (block.type === 'newsletter') {
            return renderNewsletterSettings(block);
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

    function renderNewsletterSettings(block) {
        return '' +
            '<div class="fb-post-settings__section" data-block-id="' + escapeAttr(block.id) + '">' +
                '<div class="fb-post-settings__grid">' +
                    '<div><label class="form-label">' + escapeHtml(labels.newsletterTitle) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.title || '') + '" data-block-field="title" data-block-id="' + escapeAttr(block.id) + '"></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.newsletterText) + '</label><textarea class="form-control" rows="3" data-block-field="text" data-block-id="' + escapeAttr(block.id) + '">' + escapeHtml(block.data.text || '') + '</textarea></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.newsletterButton) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.buttonText || '') + '" data-block-field="buttonText" data-block-id="' + escapeAttr(block.id) + '"></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.newsletterUrl) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.buttonUrl || '') + '" data-block-field="buttonUrl" data-block-id="' + escapeAttr(block.id) + '" placeholder="https://example.com/subscribe"></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.newsletterIcon) + '</label><input class="form-control" type="text" value="' + escapeAttr(block.data.buttonIcon || 'ci-mail') + '" data-block-field="buttonIcon" data-block-id="' + escapeAttr(block.id) + '" placeholder="ci-mail"></div>' +
                '</div>' +
            '</div>';
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
        const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]+)/i);
        if (youtubeMatch) {
            return 'https://www.youtube.com/embed/' + youtubeMatch[1];
        }

        const vimeoMatch = url.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/i);
        if (vimeoMatch) {
            return 'https://player.vimeo.com/video/' + vimeoMatch[1];
        }

        return '';
    }

    function normalizeCodeLanguage(language) {
        return String(language || 'html')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_-]/g, '') || 'html';
    }

    function refreshAllCodeEditors() {
        app.querySelectorAll('[data-code-editor]').forEach(function (editor) {
            refreshCodeEditor(editor);
        });
    }

    function refreshCodeEditor(editor) {
        if (!editor) {
            return;
        }

        const input = editor.querySelector('[data-code-input]');
        const code = editor.querySelector('code');
        if (!input || !code) {
            return;
        }

        const block = findBlock(String(input.getAttribute('data-block-id') || ''));
        const language = normalizeCodeLanguage(block && block.type === 'code' ? block.data.language : editor.getAttribute('data-code-language'));
        const value = String(input.value || '');
        code.textContent = value === '' ? '\n' : value;
        code.className = 'language-' + language;
        delete code.dataset.highlighted;

        if (typeof hljs !== 'undefined' && hljs.highlightElement) {
            hljs.highlightElement(code);
        }

        syncCodeEditorScroll(input);
    }

    function syncCodeEditorScroll(input) {
        const editor = input ? input.closest('[data-code-editor]') : null;
        const highlight = editor ? editor.querySelector('[data-code-editor] .fb-post-block__code-highlight, .fb-post-block__code-highlight') : null;
        if (!highlight) {
            return;
        }

        highlight.scrollTop = input.scrollTop;
        highlight.scrollLeft = input.scrollLeft;
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
            if (block.hidden === true) {
                return serializeHiddenBlock(block);
            }

            if (block.type === 'text') {
                return serializeTextBlock(block);
            }

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
                const mimeType = getVideoMimeType(src);
                const isHls = mimeType === 'application/vnd.apple.mpegurl';
                return '<div data-plyr-player-wrap="" data-plyr-lazy="true"><video controls playsinline webkit-playsinline preload="metadata" data-plyr-player=""' + (isHls ? ' data-hls-src="' + escapeAttr(src) + '"' : '') + (poster ? ' poster="' + escapeAttr(poster) + '"' : '') + '>' + (isHls ? '' : '<source src="' + escapeAttr(src) + '" type="' + escapeAttr(mimeType) + '">') + '</video></div>' +
                    (caption ? '<p>' + escapeHtml(caption) + '</p>' : '');
            }

            if (block.type === 'html') {
                const sourceHtml = String(block.data.html || '');
                const html = sanitizeHtml(sourceHtml, { htmlBlock: true });
                return html ? '<div data-fb-html-block="1" data-fb-html-source="' + escapeAttr(encodeStoredSource(sourceHtml)) + '">' + html + '</div>' : '';
            }

            if (block.type === 'social') {
                const items = normalizeSocialItems(block.data.items).filter(function (item) {
                    return getSocialHref(item) !== '';
                });

                if (!items.length) {
                    return '';
                }

                return '<div class="fb-social-buttons" data-fb-social-buttons="1">' + items.map(function (item) {
                    return '<a class="fb-social-buttons__item" ' + getSocialLinkAttributes(item) + ' data-network="' + escapeAttr(item.network) + '" data-icon="' + escapeAttr(item.icon) + '"><i class="fb-social-buttons__icon ' + escapeAttr(item.icon) + '" aria-hidden="true"></i><span class="fb-social-buttons__label">' + escapeHtml(item.label) + '</span></a>';
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

            if (block.type === 'newsletter') {
                return serializeNewsletterBlock(block);
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

    function serializeHiddenBlock(block) {
        const payload = {
            id: block.id,
            type: block.type,
            hidden: true,
            data: cloneData(block.data)
        };

        return '<template data-fb-hidden-block="1" data-block-json="' + escapeAttr(encodeURIComponent(JSON.stringify(payload))) + '"></template>';
    }

    function serializeTextBlock(block) {
        const html = sanitizeHtml(block.data.html || '');
        if (html === '') {
            return '';
        }

        const fontName = getAllowedOptionValue(fonts, block.data.fontName || '');
        const fontSize = getAllowedOptionValue(sizes, block.data.fontSize || '');
        const style = buildTextBlockStyle(fontName, fontSize);

        if (!style) {
            return html;
        }

        return '<div data-fb-text-block="1" style="' + escapeAttr(style) + '">' + html + '</div>';
    }

    function serializeNewsletterBlock(block) {
        const title = String(block.data.title || '').trim();
        const text = String(block.data.text || '').trim();
        const buttonText = String(block.data.buttonText || '').trim();
        const buttonUrl = String(block.data.buttonUrl || '').trim();
        const buttonIcon = getSafeIconClass(block.data.buttonIcon || 'ci-mail');
        const resolvedTitle = title || 'Sign up to our newsletter';
        const resolvedText = text || 'Receive our latest updates about our products & promotions';
        const resolvedButtonText = buttonText || 'Subscribe';
        const iconHtml = buttonIcon ? '<i class="' + escapeAttr(buttonIcon) + ' fs-base ms-n1 me-2"></i>' : '';
        const buttonInner = iconHtml + escapeHtml(resolvedButtonText);
        const buttonHtml = buttonUrl
            ? '<a class="btn btn-dark" href="' + escapeAttr(buttonUrl) + '" target="_blank" rel="noopener noreferrer">' + buttonInner + '</a>'
            : '<button type="button" class="btn btn-dark">' + buttonInner + '</button>';

        return '' +
            '<div class="d-sm-flex align-items-center justify-content-between bg-body-tertiary rounded-4 py-5 px-4 px-md-5">' +
                '<div class="mb-4 mb-sm-0 me-sm-4">' +
                    '<h3 class="h5 mb-2">' + escapeHtml(resolvedTitle) + '</h3>' +
                    '<p class="fs-sm mb-0">' + escapeHtml(resolvedText) + '</p>' +
                '</div>' +
                buttonHtml +
            '</div>';
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
            blocks.push(parseTextBlock(buffer.join('')));
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

            if (tag === 'template' && node.matches('[data-fb-hidden-block]')) {
                flushTextBuffer();
                const hiddenBlock = parseHiddenBlock(node);
                if (hiddenBlock) {
                    blocks.push(hiddenBlock);
                }
                return;
            }

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

            if (node.matches('[data-fb-text-block]')) {
                flushTextBuffer();
                blocks.push(parseTextBlock(node.outerHTML));
                return;
            }

            if (node.matches('[data-fb-newsletter-block]') || isNewsletterBlockCandidate(node)) {
                flushTextBuffer();
                blocks.push(parseNewsletterBlock(node));
                return;
            }

            if (node.matches('[data-fb-html-block]')) {
                flushTextBuffer();
                const storedSource = decodeStoredSource(node.getAttribute('data-fb-html-source'));
                blocks.push(parseHtmlBlock(storedSource || node.innerHTML, storedSource !== ''));
                return;
            }

            if (isVideoBlockCandidate(node)) {
                flushTextBuffer();
                blocks.push(parseVideoBlock(node));
                return;
            }

            if (tag === 'iframe' || node.querySelector('iframe')) {
                flushTextBuffer();
                blocks.push(parseHtmlBlock(node.outerHTML));
                return;
            }

            if (
                (node.matches('.swiper, [data-fb-slider]') && node.querySelector('.swiper-wrapper'))
                || node.matches('[data-fb-slider-shell]')
                || node.querySelector('[data-fb-slider] .swiper-wrapper, .swiper .swiper-wrapper')
            ) {
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
            hidden: false,
            data: Object.assign(defaultBlockData(type), cloneData(data))
        };
    }

    function parseHiddenBlock(node) {
        let parsed = null;

        try {
            parsed = JSON.parse(decodeURIComponent(String(node.getAttribute('data-block-json') || '')));
        } catch (error) {
            parsed = null;
        }

        const block = normalizeBlock(parsed);
        if (!block) {
            return null;
        }

        block.hidden = true;
        return block;
    }

    function parseTextBlock(html) {
        const cleanHtml = sanitizeHtml(html);
        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-text-import-root="1">' + cleanHtml + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-text-import-root="1"]');
        const wrapper = root && root.children.length === 1 ? root.children[0] : null;

        if (wrapper && wrapper.matches('[data-fb-text-block]')) {
            return createImportedBlock('text', {
                html: sanitizeHtml(wrapper.innerHTML),
                fontName: getAllowedOptionValue(fonts, wrapper.style.fontFamily || ''),
                fontSize: getAllowedOptionValue(sizes, wrapper.style.fontSize || '')
            });
        }

        return createImportedBlock('text', { html: cleanHtml });
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

    function parseHtmlBlock(html, preserveSource) {
        return createImportedBlock('html', {
            html: preserveSource ? String(html || '') : sanitizeHtml(html, { htmlBlock: true })
        });
    }

    function parseVideoBlock(node) {
        let source = '';
        let poster = '';

        if (node.matches('iframe')) {
            source = String(node.getAttribute('src') || '');
        } else if (node.querySelector('iframe')) {
            source = String(node.querySelector('iframe').getAttribute('src') || '');
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

    function isVideoBlockCandidate(node) {
        const tag = node.tagName ? node.tagName.toLowerCase() : '';
        if (tag === 'video' || node.matches('[data-plyr-player-wrap]') || node.querySelector('video, [data-plyr-player-wrap]')) {
            return true;
        }

        const iframe = tag === 'iframe' ? node : node.querySelector('iframe');
        if (!iframe) {
            return false;
        }

        return getVideoEmbedUrl(String(iframe.getAttribute('src') || '')) !== '';
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
            const href = String(linkNode.getAttribute('href') || '').trim();
            const network = String(linkNode.getAttribute('data-network') || '').trim() || (/^tel:/i.test(href) ? 'phone' : 'custom');
            const meta = getSocialNetworkMeta(network);
            const iconNode = linkNode.querySelector('i[class*="ci-"]');
            const iconClass = iconNode ? String(iconNode.className || '').match(/ci-[a-z0-9-]+/i) : null;
            const labelNode = linkNode.querySelector('.fb-social-buttons__label');

            return {
                network: meta.value,
                icon: iconClass ? iconClass[0] : meta.icon,
                label: labelNode ? String(labelNode.textContent || '').trim() : String(linkNode.textContent || '').trim(),
                url: href
            };
        });

        return createImportedBlock('social', {
            items: normalizeSocialItems(items)
        });
    }

    function parseNewsletterBlock(node) {
        const titleNode = node.querySelector('[data-fb-newsletter-title], h1, h2, h3, h4, h5, h6');
        const textNode = node.querySelector('[data-fb-newsletter-text], p');
        const buttonRoot = node.querySelector('[data-fb-newsletter-button]');
        const buttonNode = buttonRoot
            ? (buttonRoot.querySelector('a, button') || buttonRoot)
            : node.querySelector('a.btn, button.btn');
        const iconNode = buttonNode ? buttonNode.querySelector('i[class*="ci-"]') : null;
        const iconClass = iconNode ? String(iconNode.className || '').match(/ci-[a-z0-9-]+/i) : null;
        const buttonUrl = buttonNode && buttonNode.matches('a')
            ? String(buttonNode.getAttribute('href') || '').trim()
            : String((buttonRoot ? buttonRoot.getAttribute('data-button-url') : '') || '').trim();
        const buttonText = buttonNode ? String(buttonNode.textContent || '').trim() : '';

        return createImportedBlock('newsletter', {
            title: titleNode ? String(titleNode.textContent || '').trim() : '',
            text: textNode ? String(textNode.textContent || '').trim() : '',
            buttonText: buttonText,
            buttonUrl: buttonUrl,
            buttonIcon: iconClass ? iconClass[0] : String(node.getAttribute('data-button-icon') || 'ci-mail')
        });
    }

    function isNewsletterBlockCandidate(node) {
        if (!node || !node.matches) {
            return false;
        }

        return node.matches('.d-sm-flex.align-items-center.justify-content-between.bg-body-tertiary.rounded-4')
            && !!node.querySelector('h1, h2, h3, h4, h5, h6')
            && !!node.querySelector('p')
            && !!node.querySelector('a.btn, button.btn, [class*="ci-mail"]');
    }

    function parseSliderBlock(node) {
        const sliderNode = node.matches('.swiper, [data-fb-slider]')
            ? node
            : (node.querySelector('[data-fb-slider], .swiper') || node);
        const aspectRatio = String(sliderNode.getAttribute('data-aspect-ratio') || '').trim();
        const items = Array.from(sliderNode.querySelectorAll('.swiper-wrapper > .swiper-slide')).map(function (slideNode) {
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
            showBullets: !!sliderNode.querySelector('.swiper-pagination'),
            showArrows: !!(node.querySelector('[data-fb-slider-prev]') || node.querySelector('[data-fb-slider-next]') || node.querySelector('.btn-prev') || node.querySelector('.btn-next')),
            items: normalizeSliderItems(items)
        });
    }
}

function initPostFormValidation() {
    function escapeFormHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    document.querySelectorAll('[data-post-form]').forEach(function (form) {
        const summary = form.querySelector('[data-post-form-errors]');
        const requiredSummary = String(form.getAttribute('data-required-summary') || 'Fill in required fields:');

        form.setAttribute('novalidate', 'novalidate');

        form.addEventListener('submit', function (event) {
            const invalidControls = Array.from(form.querySelectorAll('[required]')).filter(function (control) {
                return !control.checkValidity();
            });

            form.querySelectorAll('.is-invalid[data-client-invalid]').forEach(function (control) {
                control.classList.remove('is-invalid');
                control.removeAttribute('data-client-invalid');
            });

            if (!invalidControls.length) {
                if (summary) {
                    summary.hidden = true;
                    summary.classList.add('d-none');
                    summary.innerHTML = '';
                }
                return;
            }

            event.preventDefault();

            const labels = invalidControls.map(function (control) {
                control.classList.add('is-invalid');
                control.setAttribute('data-client-invalid', '1');

                const field = control.closest('.col-12, .col-md-6, .col-md-3') || control.parentElement;
                const label = field ? field.querySelector('.form-label, label') : null;
                return label ? String(label.textContent || '').trim() : String(control.getAttribute('name') || '');
            }).filter(Boolean);

            if (summary) {
                summary.hidden = false;
                summary.classList.remove('d-none');
                summary.innerHTML = '<strong>' + escapeFormHtml(requiredSummary) + '</strong><ul class="mb-0 mt-2">' + labels.map(function (label) {
                    return '<li>' + escapeFormHtml(label) + '</li>';
                }).join('') + '</ul>';
            }

            invalidControls[0].focus();
        });
    });
}

function initPostAutosave() {
    document.querySelectorAll('[data-post-autosave]').forEach(function (form) {
        const autosaveUrl = String(form.getAttribute('data-autosave-url') || '');
        const statusNode = form.querySelector('[data-post-autosave-status]');
        const statusCard = form.querySelector('[data-post-autosave-card]');
        const savingLabel = String(form.getAttribute('data-autosave-saving') || 'Autosaving...');
        const savedLabel = String(form.getAttribute('data-autosave-saved') || 'Draft saved');
        const errorLabel = String(form.getAttribute('data-autosave-error') || 'Autosave failed');
        let postId = Number(form.getAttribute('data-autosave-post-id') || '0');
        let timer = null;
        let dirty = false;
        let inFlight = false;
        let pendingSave = false;
        let isSubmitting = false;
        let lastSnapshot = '';

        if (!autosaveUrl) {
            return;
        }

        function setStatus(message, tone) {
            if (!statusNode) {
                return;
            }

            statusNode.textContent = message;
            statusNode.classList.remove('text-body-secondary', 'text-success', 'text-danger');
            statusNode.classList.add(tone || 'text-body-secondary');

            if (statusCard) {
                statusCard.classList.remove('d-none');
            }
        }

        function serializeEditor() {
            form.dispatchEvent(new CustomEvent('fireball:post-editor-serialize', { bubbles: true }));
        }

        function collectFormData() {
            serializeEditor();

            const formData = new FormData(form);
            formData.set('autosave_post_id', String(postId > 0 ? postId : 0));
            formData.set('is_published', '0');

            form.querySelectorAll('input[type="file"][name]').forEach(function (input) {
                formData.delete(input.name);
            });

            return formData;
        }

        function formDataHasContent(formData) {
            const keys = [
                'title',
                'excerpt',
                'content',
                'image',
                'image_url',
                'seo_title',
                'seo_description',
                'seo_keywords',
                'seo_image'
            ];

            return keys.some(function (key) {
                const rawValue = String(formData.get(key) || '').trim();
                if (key === 'content') {
                    return rawValue !== '';
                }

                const value = rawValue
                    .replace(/<[^>]*>/g, '')
                    .replace(/&nbsp;/gi, ' ')
                    .trim();
                return value !== '';
            });
        }

        function makeSnapshot(formData) {
            const pairs = [];
            formData.forEach(function (value, key) {
                if (value instanceof File) {
                    return;
                }

                pairs.push(String(key) + '=' + String(value));
            });

            return pairs.join('&');
        }

        function applyAutosaveResponse(payload) {
            const nextPostId = Number(payload && payload.id ? payload.id : 0);
            const editUrl = String(payload && payload.edit_url ? payload.edit_url : '');
            const savedAt = String(payload && payload.saved_at ? payload.saved_at : '');
            const wasNewPost = postId <= 0;

            if (nextPostId > 0) {
                postId = nextPostId;
                form.setAttribute('data-autosave-post-id', String(postId));
            }

            if (editUrl) {
                form.setAttribute('action', editUrl);
                if (wasNewPost && window.history && typeof window.history.replaceState === 'function') {
                    window.history.replaceState({}, document.title, editUrl);
                }
            }

            setStatus(savedAt ? savedLabel + ' ' + savedAt : savedLabel, 'text-success');
        }

        function runAutosave() {
            if (isSubmitting) {
                return;
            }

            if (inFlight) {
                pendingSave = true;
                return;
            }

            const formData = collectFormData();
            if (!formDataHasContent(formData)) {
                return;
            }

            const snapshot = makeSnapshot(formData);
            if (snapshot === lastSnapshot) {
                dirty = false;
                return;
            }

            inFlight = true;
            pendingSave = false;
            setStatus(savingLabel, 'text-body-secondary');

            fetch(autosaveUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json'
                }
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (payload) {
                        if (!response.ok || !payload || payload.status !== 'success') {
                            throw new Error(String(payload && payload.message ? payload.message : errorLabel));
                        }

                        return payload;
                    });
                })
                .then(function (payload) {
                    lastSnapshot = snapshot;
                    dirty = false;
                    applyAutosaveResponse(payload);
                })
                .catch(function () {
                    setStatus(errorLabel, 'text-danger');
                })
                .finally(function () {
                    inFlight = false;
                    if (pendingSave || dirty) {
                        scheduleAutosave();
                    }
                });
        }

        function scheduleAutosave() {
            dirty = true;
            window.clearTimeout(timer);
            timer = window.setTimeout(runAutosave, 1800);
        }

        function flushAutosave() {
            if (!dirty || isSubmitting) {
                return;
            }

            window.clearTimeout(timer);
            const formData = collectFormData();
            if (!formDataHasContent(formData)) {
                return;
            }

            if (navigator.sendBeacon && navigator.sendBeacon(autosaveUrl, formData)) {
                dirty = false;
                return;
            }

            fetch(autosaveUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    Accept: 'application/json'
                }
            }).catch(function () {
            });
        }

        form.addEventListener('input', function (event) {
            if (event.target && event.target.matches('input[type="file"]')) {
                return;
            }

            scheduleAutosave();
        });

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input[type="file"]')) {
                return;
            }

            scheduleAutosave();
        });

        form.addEventListener('fireball:post-editor-sync', scheduleAutosave);

        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) {
                isSubmitting = true;
                window.clearTimeout(timer);
            }
        });

        window.addEventListener('pagehide', flushAutosave);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initPostDatepicker();
    initPostEditor();
    initPostFormValidation();
    initPostAutosave();
});
