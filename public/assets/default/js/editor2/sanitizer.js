(function (window) {
    'use strict';

    const api = window.FireballEditor2 = window.FireballEditor2 || {};
    const allowedTags = new Set([
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup',
        'del', 'div', 'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5',
        'h6', 'hr', 'i', 'iframe', 'img', 'ins', 'kbd', 'li', 'mark', 'ol', 'p', 'pre',
        'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul', 'video', 'audio', 'source'
    ]);
    const dropWithContent = new Set([
        'script', 'style', 'object', 'embed', 'form', 'input', 'textarea', 'select',
        'option', 'button', 'base', 'meta', 'link', 'svg', 'math', 'canvas'
    ]);
    const allowedAttributes = new Set([
        'allow', 'allowfullscreen', 'alt', 'aria-label', 'cite', 'colspan', 'controls', 'datetime', 'height',
        'data-hls-src', 'href', 'lang', 'loading', 'open', 'poster', 'preload', 'rel', 'rowspan',
        'scope', 'src', 'start', 'style', 'target', 'title', 'type', 'width'
    ]);
    const allowedStyles = new Set([
        'background-color', 'color', 'font-family', 'font-size', 'font-style',
        'font-weight', 'letter-spacing', 'line-height', 'margin-left', 'text-align',
        'text-decoration', 'text-decoration-line', 'text-indent', 'vertical-align'
    ]);

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = String(value == null ? '' : value);
        return node.innerHTML;
    }

    function safeUrl(value, allowDataImage) {
        const candidate = typeof api.applyFilters === 'function'
            ? api.applyFilters('sanitize:url', value, { allowDataImage: Boolean(allowDataImage) })
            : value;
        const url = String(candidate || '').replace(/[\u0000-\u0020\u007f]+/g, '').trim();
        if (!url || url.charAt(0) === '#' || url.charAt(0) === '/') {
            return url;
        }
        if (allowDataImage && /^data:image\/(?:png|gif|jpe?g|webp);base64,/i.test(url)) {
            return url;
        }
        try {
            const parsed = new URL(url, window.location.origin);
            return ['http:', 'https:', 'mailto:', 'tel:'].indexOf(parsed.protocol) !== -1 ? url : '';
        } catch (error) {
            return '';
        }
    }

    function sanitizeStyle(styleText) {
        const clean = [];
        String(styleText || '').split(';').forEach(function (declaration) {
            const separator = declaration.indexOf(':');
            if (separator === -1) {
                return;
            }
            const property = declaration.slice(0, separator).trim().toLowerCase();
            const value = declaration.slice(separator + 1).trim();
            if (!allowedStyles.has(property) || !value) {
                return;
            }
            if (/(?:expression\s*\(|(?:java|vb)script\s*:|url\s*\()/i.test(value)) {
                return;
            }
            clean.push(property + ': ' + value);
        });
        return clean.join('; ');
    }

    function normalizeOfficeMarkup(root) {
        root.querySelectorAll('[class]').forEach(function (element) {
            const className = String(element.getAttribute('class') || '');
            if (/\bMso|docs-internal-guid|Apple-converted-space/i.test(className)) {
                element.removeAttribute('class');
            }
        });
        root.querySelectorAll('o\\:p, w\\:sdt, xml').forEach(function (element) {
            element.replaceWith.apply(element, Array.from(element.childNodes));
        });
        const iterator = document.createNodeIterator(root, NodeFilter.SHOW_COMMENT);
        const comments = [];
        let current;
        while ((current = iterator.nextNode())) {
            comments.push(current);
        }
        comments.forEach(function (comment) {
            comment.remove();
        });
    }

    function isTrustedIframeUrl(value) {
        const safe = safeUrl(value, false);
        if (!safe) {
            return false;
        }
        try {
            const host = new URL(safe, window.location.origin).hostname.toLowerCase().replace(/^www\./, '');
            const trusted = host === 'youtube.com'
                || host === 'm.youtube.com'
                || host === 'youtube-nocookie.com'
                || host === 'youtu.be'
                || host === 'vimeo.com'
                || host === 'player.vimeo.com';
            return typeof api.applyFilters === 'function'
                ? Boolean(api.applyFilters('sanitize:iframe', trusted, { url: safe, host: host }))
                : trusted;
        } catch (error) {
            return false;
        }
    }

    function sanitizeHtml(html, skipFilters) {
        const parser = new DOMParser();
        const parsed = parser.parseFromString('<div data-sanitize-root>' + String(html || '') + '</div>', 'text/html');
        const root = parsed.querySelector('[data-sanitize-root]');
        if (!root) {
            return '';
        }

        normalizeOfficeMarkup(root);
        Array.from(root.querySelectorAll('*')).forEach(function (element) {
            const tag = element.tagName.toLowerCase();
            if (dropWithContent.has(tag)) {
                element.remove();
                return;
            }
            if (!allowedTags.has(tag)) {
                element.replaceWith.apply(element, Array.from(element.childNodes));
                return;
            }
            const rawStyle = String(element.getAttribute('style') || '');
            if (element.hasAttribute('hidden') || element.getAttribute('aria-hidden') === 'true' || /(?:display\s*:\s*none|visibility\s*:\s*hidden)/i.test(rawStyle)) {
                element.remove();
                return;
            }
            if (tag === 'img') {
                const width = Number(element.getAttribute('width') || 0);
                const height = Number(element.getAttribute('height') || 0);
                if ((width > 0 && width <= 1) || (height > 0 && height <= 1)) {
                    element.remove();
                    return;
                }
            }
            if (tag === 'iframe' && !isTrustedIframeUrl(element.getAttribute('src') || '')) {
                element.remove();
                return;
            }

            Array.from(element.attributes).forEach(function (attribute) {
                const name = attribute.name.toLowerCase();
                let value = String(attribute.value || '');
                if (name.indexOf('on') === 0 || !allowedAttributes.has(name)) {
                    element.removeAttribute(attribute.name);
                    return;
                }
                if (name === 'href' || name === 'src' || name === 'poster' || name === 'data-hls-src') {
                    value = safeUrl(value, (name === 'src' || name === 'poster') && tag === 'img');
                    if (!value) {
                        element.removeAttribute(attribute.name);
                        return;
                    }
                    element.setAttribute(name, value);
                }
                if (name === 'style') {
                    value = sanitizeStyle(value);
                    if (value) {
                        element.setAttribute('style', value);
                    } else {
                        element.removeAttribute('style');
                    }
                }
                if (name === 'target' && value === '_blank') {
                    element.setAttribute('rel', 'noopener noreferrer');
                }
            });
        });

        const output = root.innerHTML.trim();
        if (!skipFilters && typeof api.applyFilters === 'function') {
            const filtered = String(api.applyFilters('sanitize:html', output, { original: html }) || '');
            return filtered === output ? output : sanitizeHtml(filtered, true);
        }
        return output;
    }

    api.sanitizer = {
        escapeHtml: escapeHtml,
        safeUrl: safeUrl,
        sanitizeStyle: sanitizeStyle,
        sanitizeHtml: sanitizeHtml
    };
})(window);
