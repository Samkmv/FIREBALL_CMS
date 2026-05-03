$(function () {
    const fileSelectionStorageKey = 'fireball:file:selected';
    const publishedAtField = document.querySelector('[data-post-datepicker]');
    if (publishedAtField && typeof flatpickr !== 'undefined') {
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

    const editorField = document.querySelector('[data-post-editor]');
    const editorApp = document.querySelector('[data-post-editor-app]');
    if (!editorField || !editorApp) {
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
        config = Object.assign(config, JSON.parse(String(editorApp.getAttribute('data-post-editor-config') || '{}')));
    } catch (error) {
        config = Object.assign({}, config);
    }

    const labels = Object.assign({
        builderHint: 'Build the post from blocks and reorder them.',
        addText: 'Text',
        addHeading: 'Heading',
        addImage: 'Image',
        addVideo: 'Video',
        addHtml: 'HTML',
        addSocial: 'Social buttons',
        addCode: 'Code',
        textBlock: 'Text block',
        headingBlock: 'Heading block',
        imageBlock: 'Image block',
        videoBlock: 'Video block',
        htmlBlock: 'HTML block',
        socialBlock: 'Social buttons block',
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
        linkPrompt: 'Enter a link URL',
        empty: 'Add the first block to start building the post.',
        bulletList: 'Bullet list',
        quote: 'Quote',
        inserter: 'Block inserter',
        inspector: 'Block settings',
        canvasTitle: 'Post content',
        addBlock: 'Add block',
        outline: 'Outline',
        selectBlock: 'Select a block in the canvas to open its settings.',
        blockSettings: 'Block options',
        contentSettings: 'Content editing',
        mediaSettings: 'Media settings',
        blockCount: 'Blocks'
    }, config.labels || {});

    const fonts = Array.isArray(config.fonts) ? config.fonts : [];
    const sizes = Array.isArray(config.sizes) ? config.sizes : [];
    const popupName = 'fireball_file_manager';
    const blockTypeIcons = {
        text: 'T',
        heading: 'H',
        image: 'I',
        video: 'V',
        html: '<>',
        social: '@',
        code: '</>'
    };
    const htmlBlockStartMarker = 'fb-html-block:start';
    const htmlBlockEndMarker = 'fb-html-block:end';
    const socialBlockStartMarker = 'fb-social-block:start';
    const socialBlockEndMarker = 'fb-social-block:end';
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
        { value: 'custom', label: 'Custom', icon: 'ci-share-2' }
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
        { value: 'ci-globe', label: 'Globe' },
        { value: 'ci-share-2', label: 'Share' },
        { value: 'ci-external-link', label: 'External link' },
        { value: 'ci-phone', label: 'Phone' },
        { value: 'ci-message-circle', label: 'Message' }
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

    const state = {
        blocks: [],
        selectedId: null,
        draggedId: null,
        picker: null,
        selection: null
    };

    function makeId() {
        return 'block_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function cloneData(data) {
        return JSON.parse(JSON.stringify(data || {}));
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value == null ? '' : value)).html();
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

    function getSocialNetworkMeta(network) {
        const networkValue = String(network || '').trim().toLowerCase();
        for (let index = 0; index < socialNetworkOptions.length; index += 1) {
            if (socialNetworkOptions[index].value === networkValue) {
                return socialNetworkOptions[index];
            }
        }

        return socialNetworkOptions[socialNetworkOptions.length - 1];
    }

    function getSocialIconMeta(icon) {
        const iconValue = String(icon || '').trim().toLowerCase();
        for (let index = 0; index < socialIconOptions.length; index += 1) {
            if (socialIconOptions[index].value === iconValue) {
                return socialIconOptions[index];
            }
        }

        return {
            value: iconValue || 'ci-share-2',
            label: iconValue || 'Icon'
        };
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

    function normalizeSocialItems(items) {
        if (!Array.isArray(items) || !items.length) {
            return [getDefaultSocialItem('telegram')];
        }

        const normalized = items.map(function (item) {
            const source = item && typeof item === 'object' ? item : {};
            const meta = getSocialNetworkMeta(source.network || 'custom');
            const icon = String(source.icon || '').trim();
            const label = String(source.label || '').trim();

            return {
                network: meta.value,
                icon: icon !== '' ? icon : meta.icon,
                label: label !== '' ? label : meta.label,
                url: String(source.url || '').trim()
            };
        }).filter(function (item) {
            return item.label !== '' || item.url !== '' || item.icon !== '';
        });

        return normalized.length ? normalized : [getDefaultSocialItem('telegram')];
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
        if (type === 'code') {
            return { language: 'html', code: '' };
        }

        return { html: '' };
    }

    function createBlock(type, data) {
        return {
            id: makeId(),
            type: type,
            data: Object.assign(defaultBlockData(type), cloneData(data))
        };
    }

    function textBlockFromHtml(html) {
        return createBlock('text', { html: sanitizeHtml(html) });
    }

    function parseImageBlock(node) {
        const image = node.tagName && node.tagName.toLowerCase() === 'img'
            ? node
            : node.querySelector('img');
        const link = node.querySelector('a');
        const caption = node.querySelector('figcaption');

        return createBlock('image', {
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
            source = sourceNode
                ? String(sourceNode.getAttribute('src') || '')
                : (video ? String(video.getAttribute('src') || '') : '');
            poster = video ? String(video.getAttribute('poster') || '') : '';
        }

        return createBlock('video', {
            src: source,
            poster: poster,
            caption: ''
        });
    }

    function parseCodeBlock(node) {
        const codeNode = node.querySelector('code');
        let language = String(node.getAttribute('data-language') || '');

        if (!language && codeNode) {
            const className = String(codeNode.getAttribute('class') || '');
            const match = className.match(/language-([a-z0-9_-]+)/i);
            language = match ? match[1] : '';
        }

        return createBlock('code', {
            language: language || 'html',
            code: codeNode ? String(codeNode.textContent || '') : String(node.textContent || '')
        });
    }

    function parseHtmlBlock(html) {
        return createBlock('html', { html: sanitizeHtml(html) });
    }

    function parseSocialBlock(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-social-root="1">' + String(html || '') + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-social-root="1"]');
        const wrapper = root
            ? (root.querySelector('[data-fb-social-buttons="1"]') || root.querySelector('.fb-social-buttons'))
            : null;

        if (!wrapper) {
            return createBlock('social');
        }

        const items = Array.from(wrapper.querySelectorAll('a')).map(function (linkNode) {
            const meta = getSocialNetworkMeta(linkNode.getAttribute('data-network') || 'custom');
            const iconNode = linkNode.querySelector('i[class*="ci-"]');
            const iconClass = iconNode ? String(iconNode.className || '').match(/ci-[a-z0-9-]+/i) : null;
            const labelNode = linkNode.querySelector('.fb-social-buttons__label');
            const label = labelNode
                ? String(labelNode.textContent || '').trim()
                : String(linkNode.textContent || '').trim();

            return {
                network: meta.value,
                icon: iconClass ? iconClass[0] : meta.icon,
                label: label,
                url: String(linkNode.getAttribute('href') || '').trim()
            };
        });

        return createBlock('social', { items: normalizeSocialItems(items) });
    }

    function nodeToHtml(node) {
        if (!node) {
            return '';
        }

        if (node.nodeType === Node.TEXT_NODE) {
            return escapeHtml(String(node.textContent || ''));
        }

        if (node.nodeType === Node.COMMENT_NODE) {
            return '<!--' + String(node.nodeValue || '') + '-->';
        }

        return String(node.outerHTML || '');
    }

    function importBlocks(html) {
        const cleanHtml = String(html || '').trim();
        if (!cleanHtml) {
            return [createBlock('text')];
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString('<div data-import-root="1">' + cleanHtml + '</div>', 'text/html');
        const root = doc.body.querySelector('[data-import-root="1"]');
        if (!root) {
            return [createBlock('text')];
        }

        const blocks = [];
        let richBuffer = [];
        let htmlBuffer = null;
        let socialBuffer = null;

        function flushRichBuffer() {
            if (!richBuffer.length) {
                return;
            }

            blocks.push(textBlockFromHtml(richBuffer.join('')));
            richBuffer = [];
        }

        function flushHtmlBuffer() {
            if (htmlBuffer === null) {
                return;
            }

            blocks.push(parseHtmlBlock(htmlBuffer.join('')));
            htmlBuffer = null;
        }

        function flushSocialBuffer() {
            if (socialBuffer === null) {
                return;
            }

            blocks.push(parseSocialBlock(socialBuffer.join('')));
            socialBuffer = null;
        }

        Array.from(root.childNodes).forEach(function (node) {
            if (node.nodeType === Node.COMMENT_NODE) {
                const commentValue = String(node.nodeValue || '').trim();

                if (commentValue === socialBlockStartMarker) {
                    flushRichBuffer();
                    flushHtmlBuffer();
                    socialBuffer = [];
                    return;
                }

                if (commentValue === socialBlockEndMarker) {
                    flushSocialBuffer();
                    return;
                }

                if (commentValue === htmlBlockStartMarker) {
                    flushRichBuffer();
                    flushSocialBuffer();
                    htmlBuffer = [];
                    return;
                }

                if (commentValue === htmlBlockEndMarker) {
                    flushHtmlBuffer();
                    return;
                }
            }

            if (socialBuffer !== null) {
                socialBuffer.push(nodeToHtml(node));
                return;
            }

            if (htmlBuffer !== null) {
                htmlBuffer.push(nodeToHtml(node));
                return;
            }

            if (node.nodeType === Node.TEXT_NODE) {
                const text = String(node.textContent || '').trim();
                if (text !== '') {
                    richBuffer.push('<p>' + escapeHtml(text) + '</p>');
                }
                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            const tag = node.tagName.toLowerCase();

            if (/^h[1-6]$/.test(tag)) {
                flushRichBuffer();
                blocks.push(createBlock('heading', {
                    level: tag,
                    html: sanitizeHtml(node.innerHTML)
                }));
                return;
            }

            if (tag === 'pre') {
                flushRichBuffer();
                blocks.push(parseCodeBlock(node));
                return;
            }

            if (tag === 'img' || tag === 'figure') {
                flushRichBuffer();
                blocks.push(parseImageBlock(node));
                return;
            }

            if (tag === 'iframe' || tag === 'video' || node.matches('[data-plyr-player-wrap]')) {
                flushRichBuffer();
                blocks.push(parseVideoBlock(node));
                return;
            }

            richBuffer.push(node.outerHTML);
        });

        flushSocialBuffer();
        flushHtmlBuffer();
        flushRichBuffer();

        return blocks.length ? blocks : [createBlock('text')];
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

    function getBlockIndex(blockId) {
        return state.blocks.findIndex(function (block) {
            return block.id === blockId;
        });
    }

    function getBlock(blockId) {
        const index = getBlockIndex(blockId);
        return index >= 0 ? state.blocks[index] : null;
    }

    function saveSelection(editor) {
        if (!editor) {
            return;
        }

        const selection = window.getSelection ? window.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);
        const commonAncestor = range.commonAncestorContainer;
        if (!editor.contains(commonAncestor) && commonAncestor !== editor) {
            return;
        }

        state.selection = {
            blockId: String(editor.getAttribute('data-block-id') || ''),
            range: range.cloneRange()
        };
    }

    function restoreSelection(editor) {
        if (!editor || !state.selection) {
            return false;
        }

        const blockId = String(editor.getAttribute('data-block-id') || '');
        if (state.selection.blockId !== blockId || !state.selection.range) {
            return false;
        }

        const selection = window.getSelection ? window.getSelection() : null;
        if (!selection) {
            return false;
        }

        try {
            selection.removeAllRanges();
            selection.addRange(state.selection.range.cloneRange());
            return true;
        } catch (error) {
            return false;
        }
    }

    function ensureSelection() {
        if (!state.blocks.length) {
            state.selectedId = null;
            return;
        }

        if (!state.selectedId || getBlockIndex(state.selectedId) === -1) {
            state.selectedId = state.blocks[0].id;
        }
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

    function serializeBlocks() {
        const parts = state.blocks.map(function (block) {
            if (block.type === 'heading') {
                const level = /^h[1-6]$/.test(String(block.data.level || '')) ? String(block.data.level) : 'h2';
                const html = sanitizeHtml(block.data.html || '');
                return html ? '<' + level + '>' + html + '</' + level + '>' : '';
            }

            if (block.type === 'image') {
                const src = String(block.data.src || '').trim();
                if (src === '') {
                    return '';
                }

                const alt = escapeAttr(block.data.alt || '');
                const caption = String(block.data.caption || '').trim();
                const link = String(block.data.link || '').trim();
                let imageTag = '<img src="' + escapeAttr(src) + '" alt="' + alt + '">';

                if (link !== '') {
                    imageTag = '<a href="' + escapeAttr(link) + '" target="_blank" rel="noopener noreferrer">' + imageTag + '</a>';
                }

                if (caption !== '') {
                    return '<figure>' + imageTag + '<figcaption>' + escapeHtml(caption) + '</figcaption></figure>';
                }

                return imageTag;
            }

            if (block.type === 'video') {
                const src = String(block.data.src || '').trim();
                if (src === '') {
                    return '';
                }

                const embedUrl = getVideoEmbedUrl(src);
                const caption = String(block.data.caption || '').trim();

                if (embedUrl !== '') {
                    return '<div class="ratio ratio-16x9"><iframe src="' + escapeAttr(embedUrl) + '" title="' + escapeAttr(caption || 'Video') + '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>' +
                        (caption !== '' ? '<p>' + escapeHtml(caption) + '</p>' : '');
                }

                const poster = String(block.data.poster || '').trim();
                const posterAttribute = poster !== '' ? ' poster="' + escapeAttr(poster) + '"' : '';
                const mimeType = getVideoMimeType(src);

                return '<div data-plyr-player-wrap=""><video controls playsinline data-plyr-player=""' + posterAttribute + '><source src="' + escapeAttr(src) + '" type="' + escapeAttr(mimeType) + '"></video></div>' +
                    (caption !== '' ? '<p>' + escapeHtml(caption) + '</p>' : '');
            }

            if (block.type === 'html') {
                const html = sanitizeHtml(block.data.html || '');
                return html ? '<!--' + htmlBlockStartMarker + '-->\n' + html + '\n<!--' + htmlBlockEndMarker + '-->' : '';
            }

            if (block.type === 'social') {
                const items = normalizeSocialItems(block.data.items).filter(function (item) {
                    return String(item.url || '').trim() !== '';
                });

                if (!items.length) {
                    return '';
                }

                const buttonsHtml = items.map(function (item) {
                    return '' +
                        '<a class="fb-social-buttons__item" href="' + escapeAttr(item.url) + '" target="_blank" rel="noopener noreferrer" data-network="' + escapeAttr(item.network) + '" data-icon="' + escapeAttr(item.icon) + '">' +
                            '<i class="fb-social-buttons__icon ' + escapeAttr(item.icon) + '" aria-hidden="true"></i>' +
                            '<span class="fb-social-buttons__label">' + escapeHtml(item.label) + '</span>' +
                        '</a>';
                }).join('');

                return '<!--' + socialBlockStartMarker + '-->\n<div class="fb-social-buttons" data-fb-social-buttons="1">' + buttonsHtml + '</div>\n<!--' + socialBlockEndMarker + '-->';
            }

            if (block.type === 'code') {
                const code = String(block.data.code || '');
                if (code.trim() === '') {
                    return '';
                }

                const language = String(block.data.language || 'html').trim() || 'html';
                return '<pre class="fb-code-block" data-language="' + escapeAttr(language) + '"><code class="language-' + escapeAttr(language) + '">' + escapeHtml(code) + '</code></pre>';
            }

            const html = sanitizeHtml(block.data.html || '');
            return html;
        }).filter(function (item) {
            return String(item || '').trim() !== '';
        });

        return parts.join('\n');
    }

    function syncTextarea() {
        editorField.value = serializeBlocks();
    }

    function renderOptions(items, selectedValue) {
        return items.map(function (item) {
            const value = String(item.value || '');
            const label = String(item.label || value);
            return '<option value="' + escapeAttr(value) + '"' + (value === selectedValue ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
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

        return '<div class="fb-post-editor__preview-text">' + escapeHtml(labels.sourceLink) + '</div>';
    }

    function renderTextCanvas(block) {
        return '' +
            '<div class="fb-post-editor__formatbar">' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="italic" title="Italic" aria-label="Italic"><em>I</em></button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="underline" title="Underline" aria-label="Underline"><u>U</u></button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="insertUnorderedList" title="' + escapeAttr(labels.bulletList) + '" aria-label="' + escapeAttr(labels.bulletList) + '"><i class="ci-list"></i></button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="formatBlock" data-editor-value="blockquote" title="' + escapeAttr(labels.quote) + '" aria-label="' + escapeAttr(labels.quote) + '">&ldquo;</button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="createLink" title="Link" aria-label="Link"><i class="ci-link"></i></button>' +
                '<button class="btn btn-outline-secondary btn-sm" type="button" data-editor-command="unlink" title="Unlink" aria-label="Unlink">/</button>' +
                '<select class="form-select form-select-sm" data-editor-command="fontName" aria-label="' + escapeAttr(labels.font) + '">' +
                    '<option value="">' + escapeHtml(labels.font) + '</option>' +
                    renderOptions(fonts, '') +
                '</select>' +
                '<select class="form-select form-select-sm" data-editor-font-size aria-label="' + escapeAttr(labels.size) + '">' +
                    '<option value="">' + escapeHtml(labels.size) + '</option>' +
                    renderOptions(sizes, '') +
                '</select>' +
                '<label class="fb-post-editor__color-control">' +
                    '<span class="fb-post-editor__color-label">' + escapeHtml(labels.textColor) + '</span>' +
                    '<input class="form-control form-control-color" type="color" value="#111827" data-editor-command="foreColor" aria-label="' + escapeAttr(labels.textColor) + '">' +
                '</label>' +
                '<label class="fb-post-editor__color-control">' +
                    '<span class="fb-post-editor__color-label">' + escapeHtml(labels.background) + '</span>' +
                    '<input class="form-control form-control-color" type="color" value="#fff1a8" data-editor-command="hiliteColor" aria-label="' + escapeAttr(labels.background) + '">' +
                '</label>' +
            '</div>' +
            '<div class="fb-post-editor__rich" contenteditable="true" data-block-rich data-block-id="' + escapeAttr(block.id) + '" data-placeholder="' + escapeAttr(labels.textPlaceholder) + '">' + sanitizeHtml(block.data.html || '') + '</div>';
    }

    function renderHeadingCanvas(block) {
        return '' +
            '<div class="fb-post-editor__meta"><span class="fb-post-editor__chip">' + escapeHtml(String(block.data.level || 'h2').toUpperCase()) + '</span></div>' +
            '<div class="fb-post-editor__heading-input mt-3" contenteditable="true" data-block-heading data-block-id="' + escapeAttr(block.id) + '" data-placeholder="' + escapeAttr(labels.headingPlaceholder) + '">' + sanitizeHtml(block.data.html || '') + '</div>';
    }

    function renderImageCanvas(block) {
        const source = String(block.data.src || '').trim();
        const caption = String(block.data.caption || '').trim();

        return '' +
            '<div class="fb-post-editor__media-summary">' +
                '<div class="fb-post-editor__preview">' + renderMediaPreview(block) + '</div>' +
                '<div class="fb-post-editor__meta">' +
                    (source ? '<span class="fb-post-editor__chip">' + escapeHtml(source.split('/').pop()) + '</span>' : '') +
                    (caption ? '<span class="fb-post-editor__chip">' + escapeHtml(caption) + '</span>' : '') +
                '</div>' +
            '</div>';
    }

    function renderVideoCanvas(block) {
        const source = String(block.data.src || '').trim();
        const caption = String(block.data.caption || '').trim();

        return '' +
            '<div class="fb-post-editor__media-summary">' +
                '<div class="fb-post-editor__preview">' + renderMediaPreview(block) + '</div>' +
                '<div class="fb-post-editor__meta">' +
                    (source ? '<span class="fb-post-editor__chip">' + escapeHtml(source.split('/').pop()) + '</span>' : '') +
                    (caption ? '<span class="fb-post-editor__chip">' + escapeHtml(caption) + '</span>' : '') +
                '</div>' +
            '</div>';
    }

    function renderHtmlCanvas(block) {
        const htmlPreview = buildAdaptivePreviewHtml(block.data.html || '');

        return '' +
            '<div class="fb-post-editor__meta"><span class="fb-post-editor__chip">HTML</span></div>' +
            '<textarea class="form-control fb-post-editor__code fb-post-editor__code--html mt-3" data-block-field="html" data-block-id="' + escapeAttr(block.id) + '" spellcheck="false" placeholder="' + escapeAttr(labels.htmlPlaceholder) + '">' + escapeHtml(block.data.html || '') + '</textarea>' +
            '<div class="fb-post-editor__meta mt-3"><span class="fb-post-editor__chip">' + escapeHtml(labels.htmlPreview) + '</span></div>' +
            '<div class="fb-post-editor__rich fb-post-editor__html-preview mt-3" data-block-html-preview>' + htmlPreview + '</div>';
    }

    function renderSocialCanvas(block) {
        const items = normalizeSocialItems(block.data.items);
        const previewHtml = items.map(function (item) {
            return '' +
                '<div class="fb-social-buttons__item' + (String(item.url || '').trim() === '' ? ' is-disabled' : '') + '">' +
                    '<i class="fb-social-buttons__icon ' + escapeAttr(item.icon) + '" aria-hidden="true"></i>' +
                    '<span class="fb-social-buttons__label">' + escapeHtml(item.label) + '</span>' +
                '</div>';
        }).join('');

        return '' +
            '<div class="fb-post-editor__meta"><span class="fb-post-editor__chip">' + escapeHtml(String(items.length)) + '</span></div>' +
            '<div class="fb-social-buttons fb-social-buttons--editor mt-3">' + previewHtml + '</div>';
    }

    function renderCodeCanvas(block) {
        return '' +
            '<div class="fb-post-editor__meta"><span class="fb-post-editor__chip">' + escapeHtml(String(block.data.language || 'html')) + '</span></div>' +
            '<textarea class="form-control fb-post-editor__code mt-3" data-block-field="code" data-block-id="' + escapeAttr(block.id) + '" spellcheck="false" placeholder="' + escapeAttr(labels.codePlaceholder) + '">' + escapeHtml(block.data.code || '') + '</textarea>';
    }

    function renderCanvasBlockBody(block) {
        if (block.type === 'heading') {
            return renderHeadingCanvas(block);
        }
        if (block.type === 'image') {
            return renderImageCanvas(block);
        }
        if (block.type === 'video') {
            return renderVideoCanvas(block);
        }
        if (block.type === 'html') {
            return renderHtmlCanvas(block);
        }
        if (block.type === 'social') {
            return renderSocialCanvas(block);
        }
        if (block.type === 'code') {
            return renderCodeCanvas(block);
        }
        return renderTextCanvas(block);
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
        if (type === 'code') {
            return labels.codeBlock;
        }
        return labels.textBlock;
    }

    function blockIcon(type) {
        return blockTypeIcons[type] || '?';
    }

    function renderBlockIcon(type) {
        if (type === 'social') {
            return '<i class="ci-share-2" aria-hidden="true"></i>';
        }

        return escapeHtml(blockIcon(type));
    }

    function renderInspector(selectedBlock) {
        if (!selectedBlock) {
            return '' +
                '<h3 class="fb-post-editor__panel-title">' + escapeHtml(labels.inspector) + '</h3>' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<p class="fb-post-editor__inspector-note">' + escapeHtml(labels.selectBlock) + '</p>' +
                '</div>';
        }

        let settingsHtml = '' +
            '<div class="fb-post-editor__inspector-card">' +
                '<div class="fb-post-editor__block-label"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon(selectedBlock.type) + '</span><span>' + escapeHtml(blockTitle(selectedBlock.type)) + '</span></div>' +
            '</div>';

        if (selectedBlock.type === 'heading') {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.blockSettings) + '</h4>' +
                    '<label class="form-label">' + escapeHtml(labels.headingLevel) + '</label>' +
                    '<select class="form-select" data-block-field="level" data-block-id="' + escapeAttr(selectedBlock.id) + '">' +
                        renderOptions([
                            { value: 'h1', label: 'H1' },
                            { value: 'h2', label: 'H2' },
                            { value: 'h3', label: 'H3' },
                            { value: 'h4', label: 'H4' }
                        ], String(selectedBlock.data.level || 'h2')) +
                    '</select>' +
                '</div>';
        } else if (selectedBlock.type === 'image') {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.mediaSettings) + '</h4>' +
                    '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.sourceLink) + '</label><div class="input-group"><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.src || '') + '" data-block-field="src" data-block-id="' + escapeAttr(selectedBlock.id) + '"><button class="btn btn-outline-secondary" type="button" data-block-picker="src" data-block-id="' + escapeAttr(selectedBlock.id) + '">' + escapeHtml(labels.chooseFile) + '</button></div></div>' +
                    '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.imageAlt) + '</label><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.alt || '') + '" data-block-field="alt" data-block-id="' + escapeAttr(selectedBlock.id) + '"></div>' +
                    '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.imageCaption) + '</label><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.caption || '') + '" data-block-field="caption" data-block-id="' + escapeAttr(selectedBlock.id) + '"></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.imageLink) + '</label><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.link || '') + '" data-block-field="link" data-block-id="' + escapeAttr(selectedBlock.id) + '"></div>' +
                '</div>';
        } else if (selectedBlock.type === 'video') {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.mediaSettings) + '</h4>' +
                    '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.sourceLink) + '</label><div class="input-group"><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.src || '') + '" data-block-field="src" data-block-id="' + escapeAttr(selectedBlock.id) + '"><button class="btn btn-outline-secondary" type="button" data-block-picker="src" data-block-id="' + escapeAttr(selectedBlock.id) + '">' + escapeHtml(labels.chooseFile) + '</button></div></div>' +
                    '<div class="mb-3"><label class="form-label">' + escapeHtml(labels.videoPoster) + '</label><div class="input-group"><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.poster || '') + '" data-block-field="poster" data-block-id="' + escapeAttr(selectedBlock.id) + '"><button class="btn btn-outline-secondary" type="button" data-block-picker="poster" data-block-id="' + escapeAttr(selectedBlock.id) + '">' + escapeHtml(labels.chooseFile) + '</button></div></div>' +
                    '<div><label class="form-label">' + escapeHtml(labels.videoCaption) + '</label><input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.caption || '') + '" data-block-field="caption" data-block-id="' + escapeAttr(selectedBlock.id) + '"></div>' +
                '</div>';
        } else if (selectedBlock.type === 'html') {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.blockSettings) + '</h4>' +
                    '<p class="fb-post-editor__inspector-note">' + escapeHtml(labels.htmlPlaceholder) + '</p>' +
                '</div>';
        } else if (selectedBlock.type === 'social') {
            const items = normalizeSocialItems(selectedBlock.data.items);
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.blockSettings) + '</h4>' +
                    '<p class="fb-post-editor__inspector-note mb-3">' + escapeHtml(labels.socialItemsHint) + '</p>' +
                    '<div class="fb-post-editor__grid">';

            items.forEach(function (item, index) {
                const networkMeta = getSocialNetworkMeta(item.network);
                const iconMeta = getSocialIconMeta(item.icon);
                settingsHtml += '' +
                    '<div class="fb-post-editor__social-item">' +
                        '<div class="fb-post-editor__social-item-head">' +
                            '<div class="fb-post-editor__social-item-preview"><i class="' + escapeAttr(item.icon) + '" aria-hidden="true"></i><span>' + escapeHtml(item.label) + '</span></div>' +
                            '<button class="btn btn-outline-danger btn-sm" type="button" data-social-remove-item data-block-id="' + escapeAttr(selectedBlock.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + escapeHtml(labels.socialRemoveItem) + '</button>' +
                        '</div>' +
                        '<div class="fb-post-editor__social-fields">' +
                            '<div><label class="form-label fb-post-editor__field-label" for="social-network-' + escapeAttr(selectedBlock.id + '-' + index) + '"><span>' + escapeHtml(labels.socialNetwork) + '</span><span class="fb-post-editor__social-meta"><i class="' + escapeAttr(networkMeta.icon) + '" aria-hidden="true"></i><span>' + escapeHtml(networkMeta.label) + '</span></span></label><select class="form-select" id="social-network-' + escapeAttr(selectedBlock.id + '-' + index) + '" data-social-field="network" data-block-id="' + escapeAttr(selectedBlock.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + renderOptions(socialNetworkOptions.map(function (networkOption) { return { value: networkOption.value, label: networkOption.label }; }), item.network) + '</select></div>' +
                            '<div><label class="form-label fb-post-editor__field-label" for="social-icon-' + escapeAttr(selectedBlock.id + '-' + index) + '"><span>' + escapeHtml(labels.socialIcon) + '</span><span class="fb-post-editor__social-meta"><i class="' + escapeAttr(item.icon) + '" aria-hidden="true"></i><span>' + escapeHtml(iconMeta.label) + '</span></span></label><select class="form-select" id="social-icon-' + escapeAttr(selectedBlock.id + '-' + index) + '" data-social-field="icon" data-block-id="' + escapeAttr(selectedBlock.id) + '" data-item-index="' + escapeAttr(String(index)) + '">' + renderOptions(socialIconOptions.map(function (iconOption) { return { value: iconOption.value, label: iconOption.label + ' (' + iconOption.value + ')' }; }), item.icon) + '</select></div>' +
                            '<div><label class="form-label">' + escapeHtml(labels.socialLabel) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.label) + '" data-social-field="label" data-block-id="' + escapeAttr(selectedBlock.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                            '<div><label class="form-label">' + escapeHtml(labels.socialUrl) + '</label><input class="form-control" type="text" value="' + escapeAttr(item.url) + '" data-social-field="url" data-block-id="' + escapeAttr(selectedBlock.id) + '" data-item-index="' + escapeAttr(String(index)) + '"></div>' +
                        '</div>' +
                    '</div>';
            });

            settingsHtml += '' +
                    '</div>' +
                    '<button class="btn btn-outline-secondary rounded-pill mt-3" type="button" data-social-add-item data-block-id="' + escapeAttr(selectedBlock.id) + '">' + escapeHtml(labels.socialAddItem) + '</button>' +
                '</div>';
        } else if (selectedBlock.type === 'code') {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.blockSettings) + '</h4>' +
                    '<label class="form-label">' + escapeHtml(labels.codeLanguage) + '</label>' +
                    '<input class="form-control" type="text" value="' + escapeAttr(selectedBlock.data.language || '') + '" data-block-field="language" data-block-id="' + escapeAttr(selectedBlock.id) + '">' +
                '</div>';
        } else {
            settingsHtml += '' +
                '<div class="fb-post-editor__inspector-card">' +
                    '<h4 class="fb-post-editor__panel-title">' + escapeHtml(labels.contentSettings) + '</h4>' +
                    '<p class="fb-post-editor__inspector-note">' + escapeHtml(labels.textPlaceholder) + '</p>' +
                '</div>';
        }

        return '<h3 class="fb-post-editor__panel-title">' + escapeHtml(labels.inspector) + '</h3>' + settingsHtml;
    }

    function renderOutline() {
        return state.blocks.map(function (block, index) {
            const isActive = block.id === state.selectedId;
            return '' +
                '<button class="fb-post-editor__outline-item' + (isActive ? ' is-active' : '') + '" type="button" data-select-block="' + escapeAttr(block.id) + '">' +
                    '<span class="d-flex align-items-center gap-2"><strong>' + escapeHtml(String(index + 1)) + '.</strong><span>' + escapeHtml(blockTitle(block.type)) + '</span></span>' +
                '</button>';
        }).join('');
    }

    function renderEditor() {
        ensureSelection();
        const selectedBlock = getBlock(state.selectedId);
        const blocksHtml = state.blocks.map(function (block, index) {
            const isSelected = block.id === state.selectedId;
            return '' +
                '<article class="fb-post-editor__block' + (isSelected ? ' is-selected' : '') + '" data-block-card data-block-id="' + escapeAttr(block.id) + '">' +
                    '<div class="fb-post-editor__block-header">' +
                        '<div class="fb-post-editor__block-title">' +
                            '<button class="fb-post-editor__drag" type="button" draggable="true" data-block-drag-handle data-block-id="' + escapeAttr(block.id) + '" title="' + escapeAttr(labels.drag) + '">⋮⋮</button>' +
                            '<span class="fb-post-editor__inserter-icon">' + renderBlockIcon(block.type) + '</span>' +
                            '<span>' + escapeHtml(blockTitle(block.type)) + ' #' + escapeHtml(String(index + 1)) + '</span>' +
                        '</div>' +
                        '<div class="fb-post-editor__block-actions">' +
                            '<button class="btn btn-outline-secondary btn-sm btn-icon rounded-circle" type="button" data-block-action="move-up" data-block-id="' + escapeAttr(block.id) + '" title="' + escapeAttr(labels.moveUp) + '" aria-label="' + escapeAttr(labels.moveUp) + '"><i class="ci-arrow-up"></i></button>' +
                            '<button class="btn btn-outline-secondary btn-sm btn-icon rounded-circle" type="button" data-block-action="move-down" data-block-id="' + escapeAttr(block.id) + '" title="' + escapeAttr(labels.moveDown) + '" aria-label="' + escapeAttr(labels.moveDown) + '"><i class="ci-arrow-down"></i></button>' +
                            '<button class="btn btn-outline-secondary btn-sm btn-icon rounded-circle" type="button" data-block-action="duplicate" data-block-id="' + escapeAttr(block.id) + '" title="' + escapeAttr(labels.duplicate) + '" aria-label="' + escapeAttr(labels.duplicate) + '"><i class="ci-copy"></i></button>' +
                            '<button class="btn btn-outline-danger btn-sm btn-icon rounded-circle" type="button" data-block-action="remove" data-block-id="' + escapeAttr(block.id) + '" title="' + escapeAttr(labels.remove) + '" aria-label="' + escapeAttr(labels.remove) + '"><i class="ci-trash"></i></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="fb-post-editor__body" data-block-selectable data-block-id="' + escapeAttr(block.id) + '">' + renderCanvasBlockBody(block) + '</div>' +
                '</article>';
        }).join('');

        editorApp.innerHTML = '' +
            '<div class="fb-post-editor__topbar">' +
                '<p class="fb-post-editor__hint">' + escapeHtml(labels.builderHint) + '</p>' +
                '<div class="fb-post-editor__topbar-meta"><span>' + escapeHtml(labels.blockCount) + ': ' + escapeHtml(String(state.blocks.length)) + '</span></div>' +
            '</div>' +
            '<div class="fb-post-editor__workspace">' +
                '<aside class="fb-post-editor__sidebar">' +
                    '<h3 class="fb-post-editor__panel-title">' + escapeHtml(labels.inserter) + '</h3>' +
                    '<div class="fb-post-editor__inserter-buttons">' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="text"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('text') + '</span><span>' + escapeHtml(labels.addText) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="heading"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('heading') + '</span><span>' + escapeHtml(labels.addHeading) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="image"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('image') + '</span><span>' + escapeHtml(labels.addImage) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="video"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('video') + '</span><span>' + escapeHtml(labels.addVideo) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="html"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('html') + '</span><span>' + escapeHtml(labels.addHtml) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="social"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('social') + '</span><span>' + escapeHtml(labels.addSocial) + '</span></button>' +
                        '<button class="fb-post-editor__inserter-btn" type="button" data-editor-add="code"><span class="fb-post-editor__inserter-icon">' + renderBlockIcon('code') + '</span><span>' + escapeHtml(labels.addCode) + '</span></button>' +
                    '</div>' +
                    '<h3 class="fb-post-editor__panel-title">' + escapeHtml(labels.outline) + '</h3>' +
                    '<div class="fb-post-editor__outline">' + renderOutline() + '</div>' +
                '</aside>' +
                '<div class="fb-post-editor__canvas">' +
                    '<div class="fb-post-editor__canvas-header">' +
                        '<h3 class="fb-post-editor__canvas-title">' + escapeHtml(labels.canvasTitle) + '</h3>' +
                        '<button class="btn btn-outline-secondary btn-sm rounded-pill d-inline-flex align-items-center gap-2" type="button" data-editor-add="text"><i class="ci-plus"></i>' + escapeHtml(labels.addBlock) + '</button>' +
                    '</div>' +
                    '<div class="fb-post-editor__list">' + (blocksHtml || ('<div class="fb-post-editor__empty">' + escapeHtml(labels.empty) + '</div>')) + '</div>' +
                '</div>' +
                '<aside class="fb-post-editor__inspector">' + renderInspector(selectedBlock) + '</aside>' +
            '</div>';
    }

    function renderAndSync() {
        renderEditor();
        syncTextarea();
    }

    function selectBlock(blockId) {
        if (getBlockIndex(blockId) === -1) {
            return;
        }
        if (state.selectedId === blockId) {
            return;
        }
        state.selectedId = blockId;
        renderEditor();
    }

    function addBlock(type) {
        const block = createBlock(type);
        state.blocks.push(block);
        state.selectedId = block.id;
        renderAndSync();
    }

    function removeBlock(blockId) {
        const index = getBlockIndex(blockId);
        if (index === -1) {
            return;
        }

        state.blocks = state.blocks.filter(function (block) {
            return block.id !== blockId;
        });

        if (!state.blocks.length) {
            const fallback = createBlock('text');
            state.blocks.push(fallback);
            state.selectedId = fallback.id;
        } else if (state.selectedId === blockId) {
            const nextIndex = Math.min(index, state.blocks.length - 1);
            state.selectedId = state.blocks[nextIndex].id;
        }

        renderAndSync();
    }

    function duplicateBlock(blockId) {
        const index = getBlockIndex(blockId);
        if (index < 0) {
            return;
        }

        const source = state.blocks[index];
        const clone = createBlock(source.type, source.data);
        state.blocks.splice(index + 1, 0, clone);
        state.selectedId = clone.id;
        renderAndSync();
    }

    function moveBlock(blockId, direction) {
        const index = getBlockIndex(blockId);
        const targetIndex = direction === 'up' ? index - 1 : index + 1;

        if (index < 0 || targetIndex < 0 || targetIndex >= state.blocks.length) {
            return;
        }

        const moved = state.blocks.splice(index, 1)[0];
        state.blocks.splice(targetIndex, 0, moved);
        state.selectedId = moved.id;
        renderAndSync();
    }

    function reorderBlocks(sourceId, targetId) {
        const sourceIndex = getBlockIndex(sourceId);
        const targetIndex = getBlockIndex(targetId);
        if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
            return;
        }

        const moved = state.blocks.splice(sourceIndex, 1)[0];
        state.blocks.splice(targetIndex, 0, moved);
        state.selectedId = moved.id;
        renderAndSync();
    }

    function updateBlockField(blockId, field, value) {
        const block = getBlock(blockId);
        if (!block) {
            return;
        }

        block.data[field] = value;
        syncTextarea();
    }

    function updateSocialItemField(blockId, itemIndex, field, value) {
        const block = getBlock(blockId);
        const index = Number(itemIndex);
        if (!block || block.type !== 'social' || Number.isNaN(index)) {
            return;
        }

        const items = normalizeSocialItems(block.data.items);
        if (index < 0 || index >= items.length) {
            return;
        }

        const item = items[index];
        if (field === 'network') {
            const oldMeta = getSocialNetworkMeta(item.network);
            const newMeta = getSocialNetworkMeta(value);
            item.network = newMeta.value;

            if (!item.label || item.label === oldMeta.label) {
                item.label = newMeta.label;
            }

            if (!item.icon || item.icon === oldMeta.icon) {
                item.icon = newMeta.icon;
            }
        } else if (field === 'icon') {
            item.icon = String(value || '').trim() || getSocialNetworkMeta(item.network).icon;
        } else if (field === 'label' || field === 'url') {
            item[field] = String(value || '');
        }

        block.data.items = items;
        syncTextarea();
    }

    function addSocialItem(blockId) {
        const block = getBlock(blockId);
        if (!block || block.type !== 'social') {
            return;
        }

        const items = normalizeSocialItems(block.data.items);
        items.push(getDefaultSocialItem('custom'));
        block.data.items = items;
        renderAndSync();
    }

    function removeSocialItem(blockId, itemIndex) {
        const block = getBlock(blockId);
        const index = Number(itemIndex);
        if (!block || block.type !== 'social' || Number.isNaN(index)) {
            return;
        }

        let items = normalizeSocialItems(block.data.items);
        if (items.length <= 1) {
            items = [getDefaultSocialItem('telegram')];
        } else {
            items.splice(index, 1);
        }

        block.data.items = items;
        renderAndSync();
    }

    function syncRichEditor(editor) {
        const blockId = String(editor.getAttribute('data-block-id') || '');
        const block = getBlock(blockId);
        if (!block) {
            return;
        }

        normalizeFontTags(editor);
        block.data.html = sanitizeHtml(editor.innerHTML);
        syncTextarea();
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

    function openPicker(blockId, field) {
        if (!config.fileManagerUrl) {
            return;
        }

        const token = 'post_builder_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
        const url = new URL(config.fileManagerUrl, window.location.origin);
        url.searchParams.set('picker', '1');
        url.searchParams.set('field', token);
        url.searchParams.set('dir', String(config.defaultDirectory || 'posts'));

        state.picker = {
            token: token,
            blockId: blockId,
            field: field
        };

        window.open(url.toString(), popupName, 'width=1280,height=860,resizable=yes,scrollbars=yes');
    }

    editorApp.addEventListener('click', function (event) {
        const addButton = event.target.closest('[data-editor-add]');
        if (addButton) {
            addBlock(String(addButton.getAttribute('data-editor-add') || 'text'));
            return;
        }

        const selectButton = event.target.closest('[data-select-block]');
        if (selectButton) {
            selectBlock(String(selectButton.getAttribute('data-select-block') || ''));
            return;
        }

        const blockAction = event.target.closest('[data-block-action]');
        if (blockAction) {
            const blockId = String(blockAction.getAttribute('data-block-id') || '');
            const action = String(blockAction.getAttribute('data-block-action') || '');

            if (action === 'remove') {
                removeBlock(blockId);
                return;
            }
            if (action === 'duplicate') {
                duplicateBlock(blockId);
                return;
            }
            if (action === 'move-up') {
                moveBlock(blockId, 'up');
                return;
            }
            if (action === 'move-down') {
                moveBlock(blockId, 'down');
            }
            return;
        }

        const pickerButton = event.target.closest('[data-block-picker]');
        if (pickerButton) {
            openPicker(
                String(pickerButton.getAttribute('data-block-id') || ''),
                String(pickerButton.getAttribute('data-block-picker') || 'src')
            );
            return;
        }

        const addSocialButton = event.target.closest('[data-social-add-item]');
        if (addSocialButton) {
            addSocialItem(String(addSocialButton.getAttribute('data-block-id') || ''));
            return;
        }

        const removeSocialButton = event.target.closest('[data-social-remove-item]');
        if (removeSocialButton) {
            removeSocialItem(
                String(removeSocialButton.getAttribute('data-block-id') || ''),
                String(removeSocialButton.getAttribute('data-item-index') || '-1')
            );
            return;
        }

        const commandButton = event.target.closest('[data-editor-command]');
        if (commandButton && commandButton.tagName === 'BUTTON') {
            const blockCard = commandButton.closest('[data-block-card]');
            const editor = blockCard ? blockCard.querySelector('[data-block-rich]') : null;
            const command = String(commandButton.getAttribute('data-editor-command') || '');
            const value = String(commandButton.getAttribute('data-editor-value') || '');
            applyCommand(editor, command, value);
            return;
        }

        const selectable = event.target.closest('[data-block-selectable]');
        if (selectable) {
            selectBlock(String(selectable.getAttribute('data-block-id') || ''));
        }
    });

    editorApp.addEventListener('mousedown', function (event) {
        const commandButton = event.target.closest('button[data-editor-command]');
        if (commandButton) {
            event.preventDefault();
        }
    });

    editorApp.addEventListener('pointerdown', function (event) {
        const toolbarControl = event.target.closest('[data-editor-command], [data-editor-font-size]');
        if (!toolbarControl) {
            return;
        }

        const blockCard = toolbarControl.closest('[data-block-card]');
        const editor = blockCard ? blockCard.querySelector('[data-block-rich]') : null;
        saveSelection(editor);

        if (toolbarControl.tagName === 'BUTTON') {
            event.preventDefault();
        }
    });

    editorApp.addEventListener('input', function (event) {
        const richEditor = event.target.closest('[data-block-rich]');
        if (richEditor) {
            syncRichEditor(richEditor);
            return;
        }

        const headingInput = event.target.closest('[data-block-heading]');
        if (headingInput) {
            updateBlockField(
                String(headingInput.getAttribute('data-block-id') || ''),
                'html',
                sanitizeHtml(headingInput.innerHTML)
            );
            return;
        }

        const socialField = event.target.closest('[data-social-field]');
        if (socialField) {
            updateSocialItemField(
                String(socialField.getAttribute('data-block-id') || ''),
                String(socialField.getAttribute('data-item-index') || '-1'),
                String(socialField.getAttribute('data-social-field') || ''),
                socialField.value
            );
            return;
        }

        const field = event.target.closest('[data-block-field]');
        if (field) {
            const fieldName = String(field.getAttribute('data-block-field') || '');
            updateBlockField(
                String(field.getAttribute('data-block-id') || ''),
                fieldName,
                field.value
            );

            if (fieldName === 'html') {
                const blockCard = field.closest('[data-block-card]');
                const preview = blockCard ? blockCard.querySelector('[data-block-html-preview]') : null;
                if (preview) {
                    preview.innerHTML = buildAdaptivePreviewHtml(field.value);
                }
            }
            return;
        }

        const colorInput = event.target.closest('input[type="color"][data-editor-command]');
        if (colorInput) {
            const blockCard = colorInput.closest('[data-block-card]');
            const editor = blockCard ? blockCard.querySelector('[data-block-rich]') : null;
            const command = String(colorInput.getAttribute('data-editor-command') || '');
            applyCommand(editor, command, String(colorInput.value || ''));
        }
    });

    editorApp.addEventListener('change', function (event) {
        const socialField = event.target.closest('[data-social-field]');
        if (socialField) {
            updateSocialItemField(
                String(socialField.getAttribute('data-block-id') || ''),
                String(socialField.getAttribute('data-item-index') || '-1'),
                String(socialField.getAttribute('data-social-field') || ''),
                socialField.value
            );
            renderAndSync();
            return;
        }

        const field = event.target.closest('[data-block-field]');
        if (field) {
            updateBlockField(
                String(field.getAttribute('data-block-id') || ''),
                String(field.getAttribute('data-block-field') || ''),
                field.value
            );
            renderAndSync();
            return;
        }

        const commandSelect = event.target.closest('select[data-editor-command]');
        if (commandSelect) {
            const blockCard = commandSelect.closest('[data-block-card]');
            const editor = blockCard ? blockCard.querySelector('[data-block-rich]') : null;
            const command = String(commandSelect.getAttribute('data-editor-command') || '');
            const value = String(commandSelect.value || '');
            if (value !== '') {
                applyCommand(editor, command, value);
            }
            commandSelect.value = '';
            return;
        }

        const sizeSelect = event.target.closest('[data-editor-font-size]');
        if (sizeSelect) {
            const blockCard = sizeSelect.closest('[data-block-card]');
            const editor = blockCard ? blockCard.querySelector('[data-block-rich]') : null;
            const value = String(sizeSelect.value || '');
            if (value !== '') {
                applyCommand(editor, 'fontSize', value);
            }
            sizeSelect.value = '';
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
        if (!anchorElement || !editorApp.contains(anchorElement)) {
            return;
        }

        const richEditor = anchorElement.closest('[data-block-rich]');
        if (richEditor) {
            saveSelection(richEditor);
        }
    });

    editorApp.addEventListener('keydown', function (event) {
        const headingInput = event.target.closest('[data-block-heading]');
        if (headingInput && event.key === 'Enter') {
            event.preventDefault();
            return;
        }

        const richEditor = event.target.closest('[data-block-rich]');
        if (richEditor) {
            saveSelection(richEditor);
        }
    });

    editorApp.addEventListener('keyup', function (event) {
        const richEditor = event.target.closest('[data-block-rich]');
        if (richEditor) {
            saveSelection(richEditor);
        }
    });

    editorApp.addEventListener('mouseup', function (event) {
        const richEditor = event.target.closest('[data-block-rich]');
        if (richEditor) {
            saveSelection(richEditor);
        }
    });

    editorApp.addEventListener('dragstart', function (event) {
        const handle = event.target.closest('[data-block-drag-handle]');
        if (!handle) {
            return;
        }

        state.draggedId = String(handle.getAttribute('data-block-id') || '');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', state.draggedId);
        }
    });

    editorApp.addEventListener('dragover', function (event) {
        const card = event.target.closest('[data-block-card]');
        if (!card || !state.draggedId) {
            return;
        }

        event.preventDefault();
        editorApp.querySelectorAll('[data-block-card]').forEach(function (item) {
            item.classList.remove('is-drop-target');
        });
        card.classList.add('is-drop-target');
    });

    editorApp.addEventListener('drop', function (event) {
        const card = event.target.closest('[data-block-card]');
        if (!card || !state.draggedId) {
            return;
        }

        event.preventDefault();
        reorderBlocks(state.draggedId, String(card.getAttribute('data-block-id') || ''));
        state.draggedId = null;
    });

    editorApp.addEventListener('dragend', function () {
        state.draggedId = null;
        editorApp.querySelectorAll('[data-block-card]').forEach(function (item) {
            item.classList.remove('is-drop-target');
        });
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected' || !state.picker) {
            return;
        }

        if (String(event.data.field || '') !== state.picker.token) {
            return;
        }

        updateBlockField(state.picker.blockId, state.picker.field, String(event.data.value || ''));
        state.selectedId = state.picker.blockId;
        state.picker = null;
        renderAndSync();
    });

    window.addEventListener('storage', function (event) {
        if (event.key !== fileSelectionStorageKey || !event.newValue || !state.picker) {
            return;
        }

        let payload = null;

        try {
            payload = JSON.parse(String(event.newValue || ''));
        } catch (error) {
            payload = null;
        }

        if (!payload || payload.type !== 'fireball:file:selected') {
            return;
        }

        if (String(payload.field || '') !== state.picker.token) {
            return;
        }

        updateBlockField(state.picker.blockId, state.picker.field, String(payload.value || ''));
        state.selectedId = state.picker.blockId;
        state.picker = null;
        renderAndSync();

        try {
            localStorage.removeItem(fileSelectionStorageKey);
        } catch (error) {
        }
    });

    window.addEventListener('focus', function () {
        if (!state.picker) {
            return;
        }

        let payload = null;

        try {
            payload = JSON.parse(String(localStorage.getItem(fileSelectionStorageKey) || ''));
        } catch (error) {
            payload = null;
        }

        if (!payload || payload.type !== 'fireball:file:selected') {
            return;
        }

        if (String(payload.field || '') !== state.picker.token) {
            return;
        }

        updateBlockField(state.picker.blockId, state.picker.field, String(payload.value || ''));
        state.selectedId = state.picker.blockId;
        state.picker = null;
        renderAndSync();

        try {
            localStorage.removeItem(fileSelectionStorageKey);
        } catch (error) {
        }
    });

    const form = editorField.form;
    if (form) {
        form.addEventListener('submit', function () {
            syncTextarea();
        });
    }

    state.blocks = importBlocks(editorField.value);
    ensureSelection();
    renderAndSync();
});
