(function (window) {
    'use strict';

    const api = window.FireballEditor2 = window.FireballEditor2 || {};
    const sanitizer = api.sanitizer;
    const blockSelector = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'section', 'article',
        'ul', 'ol', 'blockquote', 'figure', 'img', 'table', 'pre', 'hr',
        'video', 'audio', 'iframe'
    ].join(',');
    const inlineTags = new Set([
        'a', 'abbr', 'b', 'br', 'code', 'del', 'em', 'i', 'ins', 'kbd', 'mark',
        'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'u'
    ]);

    function blockId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return 'block_' + window.crypto.randomUUID().replace(/-/g, '');
        }
        return 'block_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    function base64Encode(value) {
        return window.btoa(unescape(encodeURIComponent(String(value || ''))));
    }

    function base64Decode(value) {
        return decodeURIComponent(escape(window.atob(String(value || ''))));
    }

    function normalizeSettings(settings) {
        const source = settings && typeof settings === 'object' ? settings : {};
        const hiddenOn = Array.isArray(source.hiddenOn) ? source.hiddenOn : [];
        return {
            width: ['default', 'wide', 'full'].indexOf(source.width) !== -1 ? source.width : 'default',
            marginTop: Math.max(0, Math.min(160, Number(source.marginTop || 0))),
            marginBottom: Math.max(0, Math.min(160, Number(source.marginBottom == null ? 16 : source.marginBottom))),
            align: ['left', 'center', 'right', 'justify'].indexOf(source.align) !== -1 ? source.align : 'left',
            indent: Math.max(0, Math.min(160, Number(source.indent || 0))),
            lineHeight: Math.max(0, Math.min(3, Number(source.lineHeight || 0))),
            indentFirstLine: Boolean(source.indentFirstLine),
            className: String(source.className || '').replace(/[^a-zA-Z0-9 _-]/g, '').trim(),
            anchor: String(source.anchor || '').replace(/[^a-zA-Z0-9_-]/g, '').trim(),
            hiddenOn: hiddenOn.filter(function (item) {
                return ['desktop', 'tablet', 'mobile'].indexOf(item) !== -1;
            })
        };
    }

    function defaultData(type, registry) {
        const definition = registry && typeof registry.getBlockType === 'function'
            ? registry.getBlockType(type)
            : null;
        return definition && definition.default_content
            ? JSON.parse(JSON.stringify(definition.default_content))
            : {};
    }

    function firstDataValue(data, keys) {
        const source = data && typeof data === 'object' ? data : {};
        for (let index = 0; index < keys.length; index += 1) {
            const value = String(source[keys[index]] == null ? '' : source[keys[index]]).trim();
            if (value !== '') {
                return value;
            }
        }
        return '';
    }

    function normalizeMediaData(type, data, registry) {
        const source = data && typeof data === 'object'
            ? data
            : (typeof data === 'string' ? { src: data } : {});
        const normalized = Object.assign(defaultData(type, registry), source);
        const sourceKeys = type === 'video'
            ? ['src', 'url', 'source', 'video', 'videoUrl', 'video_url', 'file', 'fileUrl', 'file_url', 'hlsSrc', 'hlsUrl', 'hls_url']
            : ['src', 'url', 'source', 'audio', 'audioUrl', 'audio_url', 'file', 'fileUrl', 'file_url'];

        normalized.src = firstDataValue(source, sourceKeys);
        normalized.caption = firstDataValue(source, ['caption', 'title', 'description']);

        if (type === 'video') {
            normalized.poster = firstDataValue(source, ['poster', 'posterUrl', 'poster_url', 'image', 'thumbnail']);
            if (!Object.prototype.hasOwnProperty.call(source, 'hls')) {
                normalized.hls = firstDataValue(source, ['hlsSrc', 'hlsUrl', 'hls_url']) !== ''
                    || /\.m3u8(?:$|[?#])/i.test(normalized.src);
            }
        }

        return normalized;
    }

    function normalizeBlock(block, registry) {
        const source = block && typeof block === 'object' ? block : {};
        const type = String(source.type || 'text');
        const rawData = source.data && typeof source.data === 'object' ? source.data : {};
        const mediaData = typeof source.data === 'string' ? source.data : rawData;
        return {
            id: String(source.id || blockId()),
            type: type,
            hidden: Boolean(source.hidden),
            data: type === 'video' || type === 'audio'
                ? normalizeMediaData(type, mediaData, registry)
                : Object.assign(defaultData(type, registry), rawData),
            settings: normalizeSettings(source.settings),
            meta: source.meta && typeof source.meta === 'object' ? source.meta : {}
        };
    }

    function normalizeState(value, registry) {
        const source = value && typeof value === 'object' ? value : {};
        return {
            version: 2,
            blocks: Array.isArray(source.blocks)
                ? source.blocks.map(function (block) { return normalizeBlock(block, registry); })
                : []
        };
    }

    function extractSnapshot(html, registry) {
        if (!String(html || '').trim()) {
            return null;
        }
        const parsed = new DOMParser().parseFromString(String(html), 'text/html');
        const snapshot = parsed.querySelector('template[data-fb-editor-state]');
        if (!snapshot) {
            return null;
        }
        const encoded = String(snapshot.content.textContent || snapshot.textContent || '').trim();
        if (!encoded) {
            return null;
        }
        try {
            const state = JSON.parse(base64Decode(encoded));
            return normalizeState(state, registry);
        } catch (error) {
            return null;
        }
    }

    function listBlock(element, type) {
        return {
            id: blockId(),
            type: type,
            data: {
                items: Array.from(element.children).filter(function (item) {
                    return item.tagName && item.tagName.toLowerCase() === 'li';
                }).map(function (item) {
                    return sanitizer.sanitizeHtml(item.innerHTML);
                })
            }
        };
    }

    function tableBlock(element) {
        const rows = Array.from(element.querySelectorAll('tr')).map(function (row) {
            return Array.from(row.children).filter(function (cell) {
                return /^(td|th)$/i.test(cell.tagName);
            }).map(function (cell) {
                return sanitizer.sanitizeHtml(cell.innerHTML);
            });
        }).filter(function (row) {
            return row.length > 0;
        });
        return {
            id: blockId(),
            type: 'table',
            data: {
                rows: rows.length ? rows : [['', ''], ['', '']],
                header: Boolean(element.querySelector('thead, tr:first-child th'))
            }
        };
    }

    function mediaBlock(element, type) {
        const hlsSource = type === 'video' ? element.getAttribute('data-hls-src') || '' : '';
        const source = element.getAttribute('src')
            || hlsSource
            || (element.querySelector('source') ? element.querySelector('source').getAttribute('src') : '');
        return {
            id: blockId(),
            type: type,
            data: {
                src: sanitizer.safeUrl(source || '', false),
                poster: sanitizer.safeUrl(element.getAttribute('poster') || '', true),
                caption: '',
                hls: Boolean(hlsSource) || /\.m3u8(?:$|[?#])/i.test(String(source || ''))
            }
        };
    }

    function nodeToBlocks(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = String(node.textContent || '').trim();
            return text ? [{ id: blockId(), type: 'text', data: { html: sanitizer.escapeHtml(text) } }] : [];
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return [];
        }

        const element = node;
        const tag = element.tagName.toLowerCase();
        if (element.matches('template[data-fb-editor-state], template[data-fb-hidden-block]')) {
            return [];
        }
        if (/^h[1-6]$/.test(tag)) {
            return [{
                id: blockId(),
                type: 'heading',
                data: { level: tag, html: sanitizer.sanitizeHtml(element.innerHTML) }
            }];
        }
        if (tag === 'ul') {
            const isChecklist = element.matches('[data-fb-checklist]') || Boolean(element.querySelector('input[type="checkbox"]'));
            if (isChecklist) {
                return [{
                    id: blockId(),
                    type: 'checklist',
                    data: {
                        items: Array.from(element.querySelectorAll(':scope > li')).map(function (item) {
                            const checkbox = item.querySelector('input[type="checkbox"]');
                            if (checkbox) {
                                checkbox.remove();
                            }
                            return { text: sanitizer.sanitizeHtml(item.innerHTML), checked: Boolean(checkbox && checkbox.checked) };
                        })
                    }
                }];
            }
            return [listBlock(element, 'bulletList')];
        }
        if (tag === 'ol') {
            return [listBlock(element, 'orderedList')];
        }
        if (tag === 'blockquote') {
            const cite = element.querySelector('cite');
            if (cite) {
                cite.remove();
            }
            return [{
                id: blockId(),
                type: 'quote',
                data: {
                    html: sanitizer.sanitizeHtml(element.innerHTML),
                    cite: cite ? String(cite.textContent || '').trim() : ''
                }
            }];
        }
        if (tag === 'figure' || tag === 'img') {
            const image = tag === 'img' ? element : element.querySelector('img');
            if (image) {
                const caption = tag === 'figure' ? element.querySelector('figcaption') : null;
                return [{
                    id: blockId(),
                    type: 'image',
                    data: {
                        src: sanitizer.safeUrl(image.getAttribute('src') || '', true),
                        alt: String(image.getAttribute('alt') || ''),
                        caption: caption ? String(caption.textContent || '').trim() : '',
                        link: ''
                    }
                }];
            }
            const media = tag === 'figure' ? element.querySelector('video, audio') : null;
            if (media) {
                const block = mediaBlock(media, media.tagName.toLowerCase());
                const caption = element.querySelector('figcaption');
                block.data.caption = caption ? String(caption.textContent || '').trim() : '';
                return [block];
            }
        }
        if (tag === 'table') {
            return [tableBlock(element)];
        }
        if (tag === 'pre') {
            const code = element.querySelector('code');
            return [{
                id: blockId(),
                type: 'code',
                data: {
                    language: code ? String(code.getAttribute('data-language') || '').replace(/^language-/, '') || 'text' : 'text',
                    code: String((code || element).textContent || '')
                }
            }];
        }
        if (tag === 'hr') {
            return [{ id: blockId(), type: 'divider', data: { style: 'solid' } }];
        }
        if (tag === 'video' || tag === 'audio') {
            return [mediaBlock(element, tag)];
        }
        if (tag === 'iframe') {
            return [{
                id: blockId(),
                type: 'embed',
                data: { url: sanitizer.safeUrl(element.getAttribute('src') || '', false), caption: element.getAttribute('title') || '' }
            }];
        }
        if (element.matches('[data-fb-alert-block]')) {
            return [{
                id: blockId(),
                type: 'alert',
                data: {
                    variant: element.getAttribute('data-alert-variant') || 'info',
                    title: String((element.querySelector('[data-fb-alert-title]') || {}).textContent || ''),
                    text: String((element.querySelector('[data-fb-alert-text]') || {}).textContent || ''),
                    icon: 'ci-info'
                }
            }];
        }
        if (element.matches('[data-fb-newsletter-block]')) {
            return [{
                id: blockId(),
                type: 'newsletter',
                data: {
                    title: String((element.querySelector('[data-fb-newsletter-title]') || {}).textContent || ''),
                    text: String((element.querySelector('[data-fb-newsletter-text]') || {}).textContent || ''),
                    buttonText: element.getAttribute('data-button-text') || '',
                    buttonUrl: element.getAttribute('data-button-url') || '',
                    buttonIcon: element.getAttribute('data-button-icon') || 'ci-mail'
                }
            }];
        }
        if (tag === 'p' || tag === 'div' || tag === 'section' || tag === 'article') {
            const hasBlockChildren = Array.from(element.children).some(function (child) {
                return /^(h[1-6]|p|div|section|article|ul|ol|blockquote|figure|table|pre|hr|video|audio)$/i.test(child.tagName);
            });
            if (hasBlockChildren) {
                return Array.from(element.childNodes).reduce(function (blocks, child) {
                    return blocks.concat(nodeToBlocks(child));
                }, []);
            }
            return [{
                id: blockId(),
                type: 'text',
                data: { html: sanitizer.sanitizeHtml(element.innerHTML) }
            }];
        }

        if (inlineTags.has(tag)) {
            return [{
                id: blockId(),
                type: 'text',
                data: { html: sanitizer.sanitizeHtml(element.outerHTML) }
            }];
        }

        return [{
            id: blockId(),
            type: 'html',
            data: { html: sanitizer.sanitizeHtml(element.outerHTML) }
        }];
    }

    function htmlToBlocks(html, registry) {
        const safe = sanitizer.sanitizeHtml(String(html || ''));
        if (!safe) {
            return [];
        }
        const parsed = new DOMParser().parseFromString('<div data-import-root>' + safe + '</div>', 'text/html');
        const root = parsed.querySelector('[data-import-root]');
        const blocks = [];
        let inlineNodes = [];

        function flushInlineNodes() {
            if (!inlineNodes.length) {
                return;
            }
            const container = parsed.createElement('div');
            inlineNodes.forEach(function (node) {
                container.appendChild(node.cloneNode(true));
            });
            inlineNodes = [];
            if (String(container.textContent || '').trim() === '' && !container.querySelector('img,br')) {
                return;
            }
            blocks.push({
                id: blockId(),
                type: 'text',
                data: { html: sanitizer.sanitizeHtml(container.innerHTML) }
            });
        }

        Array.from(root ? root.childNodes : []).forEach(function (node) {
            const isInlineNode = node.nodeType === Node.TEXT_NODE
                || (node.nodeType === Node.ELEMENT_NODE && inlineTags.has(node.tagName.toLowerCase()));
            if (isInlineNode) {
                inlineNodes.push(node);
                return;
            }
            flushInlineNodes();
            blocks.push.apply(blocks, nodeToBlocks(node));
        });
        flushInlineNodes();

        return blocks.map(function (block) {
            return normalizeBlock(block, registry);
        });
    }

    function inlineMarkdownToHtml(value) {
        let html = sanitizer.escapeHtml(String(value || ''));
        const codeTokens = [];
        html = html.replace(/`([^`\n]+)`/g, function (match, code) {
            const token = '\u0000CODE' + codeTokens.length + '\u0000';
            codeTokens.push('<code>' + code + '</code>');
            return token;
        });
        html = html.replace(/\[([^\]\n]+)\]\(([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\)/g, function (match, label, rawUrl) {
            const url = sanitizer.safeUrl(String(rawUrl || '').replace(/&amp;/g, '&'), false);
            return url
                ? '<a href="' + sanitizer.escapeHtml(url) + '" rel="noopener noreferrer">' + label + '</a>'
                : label;
        });
        html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
        html = html.replace(/(^|[\s(])\*([^*\n]+)\*(?=$|[\s).,!?:;])/g, '$1<em>$2</em>');
        html = html.replace(/(^|[\s(])_([^_\n]+)_(?=$|[\s).,!?:;])/g, '$1<em>$2</em>');
        codeTokens.forEach(function (token, index) {
            html = html.replace('\u0000CODE' + index + '\u0000', token);
        });
        return sanitizer.sanitizeHtml(html);
    }

    function markdownTableCells(line) {
        return String(line || '').trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (cell) {
            return inlineMarkdownToHtml(cell.trim());
        });
    }

    function isMarkdownTableSeparator(line) {
        const cells = String(line || '').trim().replace(/^\|/, '').replace(/\|$/, '').split('|');
        return cells.length > 1 && cells.every(function (cell) {
            return /^:?-{3,}:?$/.test(cell.trim());
        });
    }

    function markdownToBlocks(text, registry) {
        const lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        const blocks = [];
        let index = 0;
        let paragraph = [];

        function flushParagraph() {
            const value = paragraph.join('\n').trim();
            if (value) {
                blocks.push({ id: blockId(), type: 'text', data: { html: inlineMarkdownToHtml(value).replace(/\n/g, '<br>') } });
            }
            paragraph = [];
        }

        while (index < lines.length) {
            const line = lines[index];
            if (line.indexOf('|') !== -1 && index + 1 < lines.length && isMarkdownTableSeparator(lines[index + 1])) {
                flushParagraph();
                const rows = [markdownTableCells(line)];
                index += 2;
                while (index < lines.length && lines[index].trim() && lines[index].indexOf('|') !== -1) {
                    rows.push(markdownTableCells(lines[index]));
                    index += 1;
                }
                blocks.push({ id: blockId(), type: 'table', data: { rows: rows, header: true, bordered: true, responsive: true } });
                continue;
            }
            const heading = line.match(/^(#{1,6})\s+(.+)$/);
            if (heading) {
                flushParagraph();
                blocks.push({ id: blockId(), type: 'heading', data: { level: 'h' + heading[1].length, html: inlineMarkdownToHtml(heading[2]) } });
                index += 1;
                continue;
            }
            const image = line.match(/^!\[([^\]]*)\]\(([^)\s]+)\)\s*$/);
            if (image) {
                flushParagraph();
                blocks.push({ id: blockId(), type: 'image', data: { src: sanitizer.safeUrl(image[2], true), alt: image[1], caption: '', link: '' } });
                index += 1;
                continue;
            }
            if (/^```/.test(line)) {
                flushParagraph();
                const language = line.replace(/^```/, '').trim() || 'text';
                const code = [];
                index += 1;
                while (index < lines.length && !/^```/.test(lines[index])) {
                    code.push(lines[index]);
                    index += 1;
                }
                index += 1;
                blocks.push({ id: blockId(), type: 'code', data: { language: language, code: code.join('\n') } });
                continue;
            }
            if (/^\s*[-*]\s+\[[ xX]\]\s+/.test(line)) {
                flushParagraph();
                const items = [];
                while (index < lines.length && /^\s*[-*]\s+\[[ xX]\]\s+/.test(lines[index])) {
                    const match = lines[index].match(/^\s*[-*]\s+\[([ xX])\]\s+(.+)$/);
                    items.push({ text: inlineMarkdownToHtml(match[2]), checked: match[1].toLowerCase() === 'x' });
                    index += 1;
                }
                blocks.push({ id: blockId(), type: 'checklist', data: { items: items } });
                continue;
            }
            if (/^\s*[-*+•]\s+/.test(line)) {
                flushParagraph();
                const items = [];
                while (index < lines.length && /^\s*[-*+•]\s+/.test(lines[index])) {
                    items.push(inlineMarkdownToHtml(lines[index].replace(/^\s*[-*+•]\s+/, '')));
                    index += 1;
                }
                blocks.push({ id: blockId(), type: 'bulletList', data: { items: items } });
                continue;
            }
            if (/^\s*\d+[.)]\s+/.test(line)) {
                flushParagraph();
                const items = [];
                while (index < lines.length && /^\s*\d+[.)]\s+/.test(lines[index])) {
                    items.push(inlineMarkdownToHtml(lines[index].replace(/^\s*\d+[.)]\s+/, '')));
                    index += 1;
                }
                blocks.push({ id: blockId(), type: 'orderedList', data: { items: items } });
                continue;
            }
            if (/^>\s?/.test(line)) {
                flushParagraph();
                const quote = [];
                while (index < lines.length && /^>\s?/.test(lines[index])) {
                    quote.push(lines[index].replace(/^>\s?/, ''));
                    index += 1;
                }
                blocks.push({ id: blockId(), type: 'quote', data: { html: inlineMarkdownToHtml(quote.join('\n')).replace(/\n/g, '<br>'), cite: '' } });
                continue;
            }
            if (/^\s*(?:---+|\*\*\*+)\s*$/.test(line)) {
                flushParagraph();
                blocks.push({ id: blockId(), type: 'divider', data: { style: 'solid' } });
                index += 1;
                continue;
            }
            if (!line.trim()) {
                flushParagraph();
            } else {
                paragraph.push(line);
            }
            index += 1;
        }
        flushParagraph();

        return blocks.map(function (block) {
            return normalizeBlock(block, registry);
        });
    }

    function looksLikeMarkdown(text) {
        return /(^|\n)(#{1,6}\s|```|>\s|[-*+•]\s+|\d+[.)]\s+|[-*]\s+\[[ xX]\]\s+)|\|.+\|\s*\n\s*\|?\s*:?-{3,}/m.test(String(text || ''));
    }

    function looksLikeHtmlText(text) {
        const value = String(text || '').trim();
        return /^<(?:h[1-6]|p|ul|ol|blockquote|pre|table|figure|img|video|audio|hr|a|abbr|b|code|del|em|i|ins|kbd|mark|q|s|small|span|strong|sub|sup|u)\b[\s\S]*>/i.test(value)
            && /<\/(?:h[1-6]|p|ul|ol|blockquote|pre|table|figure|video|audio|a|abbr|b|code|del|em|i|ins|kbd|mark|q|s|small|span|strong|sub|sup|u)>|<hr\b|<img\b/i.test(value);
    }

    function isInlineHtmlFragment(html) {
        const safe = sanitizer.sanitizeHtml(String(html || ''));
        if (!safe) {
            return false;
        }
        const parsed = new DOMParser().parseFromString('<div data-inline-root>' + safe + '</div>', 'text/html');
        const root = parsed.querySelector('[data-inline-root]');
        return Boolean(root && !root.querySelector(blockSelector));
    }

    function clipboardToBlocks(clipboard, registry) {
        const internal = clipboard ? clipboard.getData(api.internalMime || 'application/x-fireball-blocks+json') : '';
        if (internal) {
            try {
                const parsed = JSON.parse(internal);
                if (Array.isArray(parsed.blocks)) {
                    return parsed.blocks.map(function (block) {
                        const copy = normalizeBlock(block, registry);
                        copy.id = blockId();
                        return copy;
                    });
                }
            } catch (error) {
                if (typeof api.doAction === 'function') {
                    api.doAction('import:error', { phase: 'internal-clipboard', message: String(error && error.message || error) });
                }
            }
        }
        const html = clipboard ? String(clipboard.getData('text/html') || '') : '';
        if (html) {
            const blocks = htmlToBlocks(html, registry);
            if (blocks.length) {
                return blocks;
            }
        }
        const plain = clipboard ? String(clipboard.getData('text/plain') || '') : '';
        if (looksLikeHtmlText(plain)) {
            return htmlToBlocks(plain, registry);
        }
        return looksLikeMarkdown(plain)
            ? markdownToBlocks(plain, registry)
            : markdownToBlocks(plain.replace(/\n{3,}/g, '\n\n'), registry);
    }

    api.importer = {
        blockId: blockId,
        base64Encode: base64Encode,
        base64Decode: base64Decode,
        normalizeBlock: normalizeBlock,
        normalizeState: normalizeState,
        extractSnapshot: extractSnapshot,
        htmlToBlocks: htmlToBlocks,
        markdownToBlocks: markdownToBlocks,
        looksLikeMarkdown: looksLikeMarkdown,
        looksLikeHtmlText: looksLikeHtmlText,
        isInlineHtmlFragment: isInlineHtmlFragment,
        inlineMarkdownToHtml: inlineMarkdownToHtml,
        clipboardToBlocks: clipboardToBlocks
    };
})(window);
