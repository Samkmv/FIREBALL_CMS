'use strict';

// These checks read the shipped stylesheet and template. They protect the
// viewport, touch/keyboard and theme contracts; real Safari painting is checked
// separately in the browser and on an installed iPhone PWA.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.join(__dirname, '../plugins/reel-player');
const css = fs.readFileSync(path.join(root, 'assets/player.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const template = fs.readFileSync(path.join(root, 'views/player.php'), 'utf8');
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };

function splitTopLevel(source, delimiter) {
    const parts = []; let start = 0, depth = 0, quote = '';
    for (let i = 0; i < source.length; i++) {
        const c = source[i];
        if (quote) { if (c === quote && source[i - 1] !== '\\') quote = ''; continue; }
        if (c === '"' || c === "'") { quote = c; continue; }
        if (c === '(' || c === '[') depth++;
        if (c === ')' || c === ']') depth--;
        if (c === delimiter && depth === 0) { parts.push(source.slice(start, i).trim()); start = i + 1; }
    }
    parts.push(source.slice(start).trim()); return parts.filter(Boolean);
}

// A small CSS rule reader, without a downloaded parser or browser dependency.
// It keeps media ancestry and splits selector/declaration lists outside strings
// and parentheses, so commas in a selector function cannot flatten a rule.
function parseRules(source, contexts = []) {
    const rules = []; let cursor = 0;
    while (cursor < source.length) {
        const open = source.indexOf('{', cursor);
        if (open < 0) { check(!source.slice(cursor).trim(), 'No dangling CSS tokens'); break; }
        const header = source.slice(cursor, open).trim();
        let depth = 1, quote = '', close = open + 1;
        for (; close < source.length && depth; close++) {
            const c = source[close];
            if (quote) { if (c === quote && source[close - 1] !== '\\') quote = ''; continue; }
            if (c === '"' || c === "'") { quote = c; continue; }
            if (c === '{') depth++;
            if (c === '}') depth--;
        }
        check(depth === 0, 'Every CSS block closes');
        const body = source.slice(open + 1, close - 1);
        if (/^@(media|supports|layer|keyframes)\b/.test(header)) rules.push(...parseRules(body, [...contexts, header]));
        else {
            const declarations = new Map();
            for (const declaration of splitTopLevel(body, ';')) {
                const colon = declaration.indexOf(':');
                check(colon > 0, 'CSS declarations have a property and value');
                declarations.set(declaration.slice(0, colon).trim(), declaration.slice(colon + 1).trim());
            }
            rules.push({selectors:splitTopLevel(header, ','), declarations, contexts});
        }
        cursor = close;
    }
    return rules;
}
const rules = parseRules(css);
// Syntax bookkeeping must not inflate the number of reported behavior checks.
checks = 0;
const matching = (selector, predicate = () => true) => rules.filter(rule => rule.selectors.includes(selector) && predicate(rule));
const merged = (selector, predicate = rule => !rule.contexts.length) => {
    const values = new Map();
    for (const rule of matching(selector, predicate)) for (const [name, value] of rule.declarations) values.set(name, value);
    return values;
};
const isMotionOverride = rule => rule.contexts.some(context => context.includes('prefers-reduced-motion:reduce'));

// A tap must not latch a desktop hover color. Disabled controls also cannot
// receive a live-button hover treatment. Check every shipped hover rule.
const hoverRules = rules.filter(rule => rule.selectors.some(selector => selector.includes(':hover')));
check(hoverRules.length > 20, 'Existing control families participate in hover checks');
for (const rule of hoverRules) {
    if (isMotionOverride(rule)) continue;
    check(rule.contexts.some(context => context.includes('(hover:hover)') && context.includes('(pointer:fine)')),
        `Hover is restricted to a real pointer: ${rule.selectors.join(', ')}`);
    for (const selector of rule.selectors.filter(value => value.includes(':hover'))) {
        if (/\.rp-(?:body button|icon-button|play(?:list)?|upload-button|sort-(?:trigger|option)|track-play|button|chapter-(?:play|marker)|catalog-(?:nav-button|text-button|card-play|save)|album-(?:title|artist|open)|artist-card|about|cover-button)/.test(selector)) {
            check(selector.includes(':not(:disabled)'), `Disabled controls are excluded: ${selector}`);
        }
    }
}
const button = merged('.rp-body button');
const transitionTimes = [...button.get('transition').matchAll(/([\d.]+)(ms|s)\b/g)].map(match => Number(match[1]) * (match[2] === 's' ? 1000 : 1));
check(transitionTimes.length >= 4 && transitionTimes.every(time => time >= 150 && time <= 250), 'Button response stays within 150–250 ms');
check(merged('.rp-body button', isMotionOverride).get('transition') === 'none', 'Reduced motion disables the shared button transitions');
check(merged('.rp-body button:focus-visible').get('box-shadow')?.includes('inset') && merged('.rp-body button:focus-visible').get('box-shadow')?.includes('var(--rp-accent)'), 'Keyboard focus remains visible inside the button bounds');
check(Number(merged('.rp-body button:not(:disabled):active').get('opacity')) < 1, 'Touch presses have visual feedback without hover');
check(merged('.rp-body button:disabled').get('cursor') === 'default', 'Disabled controls do not advertise an active pointer');

// Preserve shrinkable form content and visible vertical scrolling. In Safari,
// the native date value can otherwise impose its intrinsic inline width.
for (const selector of ['.rp-dialog form', '.rp-dialog [data-dialog-fields]', '.rp-dialog .rp-field', '.rp-dialog .rp-field input', '.rp-dialog .rp-field select', '.rp-dialog .rp-field textarea', '.rp-album-members']) {
    check(merged(selector).get('min-width') === '0', `Modal content can shrink: ${selector}`);
}
for (const selector of ['.rp-dialog .rp-field', '.rp-dialog .rp-field input', '.rp-dialog .rp-field select', '.rp-dialog .rp-field textarea', '.rp-album-members']) {
    check(merged(selector).get('max-width') === '100%', `Modal content is bounded: ${selector}`);
}
check(merged('.rp-dialog input[type=date]').get('min-inline-size') === '0', 'Safari native date control can shrink horizontally');
check(merged('.rp-dialog input[type=date]::-webkit-date-and-time-value').get('min-width') === '0', 'Safari date shadow value cannot impose a wider minimum');
check(merged('.rp-album-member').get('grid-template-columns').includes('minmax(0,1fr)'), 'Long song names do not enlarge the album grid column');
check(merged('.rp-album-members').get('overflow') === 'auto', 'Album songs retain their accessible scrolling region');
check(!rules.some(rule => rule.selectors.some(selector => selector.startsWith('.rp-dialog')) && rule.declarations.get('overflow-x') === 'hidden'), 'Modal width errors are not concealed by horizontal clipping');

// Saved status must take its background/text/border from the active palette,
// including the dark override, rather than the previous light green constants.
for (const selector of ['.rp-catalog-save.is-saved', '[data-theme=dark] .rp-catalog-save.is-saved']) {
    const saved = merged(selector);
    for (const property of ['background', 'color', 'border-color']) check(/^var\(--rp-/.test(saved.get(property)), `${property} follows the active collection palette`);
}
for (const theme of [':root', ':root[data-theme=dark]']) {
    const palette = merged(theme);
    for (const name of ['--rp-paper', '--rp-line', '--rp-accent', '--rp-ink']) check(palette.has(name), `${theme} provides ${name}`);
}

const dialog = merged('.rp-dialog');
function cssNumber(value, dimensions, variables, seen = new Set()) {
    let expression = value.replace(/var\((--[\w-]+)(?:,([^()]+))?\)/g, (_, name, fallback) => {
        assert.ok(!seen.has(name), `No circular dialog variable: ${name}`);
        const resolved = dimensions.variables?.[name] ?? variables.get(name) ?? fallback;
        assert.ok(resolved !== undefined, `Dialog variable is available: ${name}`);
        return String(cssNumber(String(resolved), dimensions, variables, new Set([...seen, name])));
    }).replace(/env\((safe-area-inset-[\w-]+),?([^)]*)\)/g, (_, name, fallback) => String(dimensions[name] ?? parseFloat(fallback) ?? 0));
    expression = expression.replace(/([\d.]+)dvh/g, (_, value) => String(Number(value) * dimensions.height / 100)).replace(/px\b/g, '')
        .replace(/calc\(/g, '(').replace(/max\(/g, 'Math.max(').replace(/min\(/g, 'Math.min(');
    assert.match(expression.replace(/Math\.(?:max|min)/g, ''), /^[\d.\s+\-*/(),]+$/, 'Only numeric CSS calculations are evaluated');
    return Function(`"use strict"; return (${expression});`)();
}
// Evaluate the actual safe-area/visual-viewport formula at full height,
// a keyboard-reduced viewport, and a scrolled visual viewport.
for (const fixture of [
    {height:844, top:0, 'safe-area-inset-top':59, 'safe-area-inset-bottom':34},
    {height:360, top:8, 'safe-area-inset-top':59, 'safe-area-inset-bottom':34},
    {height:568, top:18, 'safe-area-inset-top':0, 'safe-area-inset-bottom':0},
]) {
    fixture.variables = {'--rp-dialog-viewport-height':`${fixture.height}px`, '--rp-dialog-viewport-top':`${fixture.top}px`};
    const height = cssNumber(dialog.get('max-height'), fixture, dialog), center = cssNumber(dialog.get('top'), fixture, dialog);
    const top = center - height / 2, bottom = center + height / 2;
    check(height > 0, 'The visible modal has positive height even with an onscreen keyboard');
    check(top >= fixture.top + (fixture['safe-area-inset-top'] || 16), 'The visible modal stays below the top safe area');
    check(bottom <= fixture.top + fixture.height - (fixture['safe-area-inset-bottom'] || 16), 'The visible modal stays above the bottom safe area');
}
check(merged('html.rp-dialog-open').get('overflow') === 'hidden', 'The root layout viewport is locked together with the body');
check(merged('html.rp-dialog-open').get('overscroll-behavior') === 'none', 'The root cannot rubber-band behind an open modal');
check(merged('html').get('background-color') === 'var(--rp-paper)', 'Exposed safe-area canvas follows the selected theme');
check(merged('.rp-body').get('min-height') === '100dvh', 'The body paints the dynamic viewport when content is short');
const backdrop = merged('.rp-dialog::backdrop');
check(backdrop.get('position') === 'fixed' && backdrop.get('inset') === '0', 'Native modal backdrop covers every viewport edge');
check(merged('.rp-dialog::backdrop', rule => rule.contexts.includes('@media(display-mode:standalone)')).get('backdrop-filter') === 'none', 'Standalone mode avoids the native backdrop blur compositing seam');
check(merged('html.pwa-standalone .rp-dialog::backdrop').get('-webkit-backdrop-filter') === 'none', 'The iOS standalone fallback also avoids the blur layer');
check(/name="viewport"[^>]*viewport-fit=cover/.test(template), 'The template enables safe-area viewport coordinates');

const symbols = new Set([...template.matchAll(/<symbol id="rp-i-([\w-]+)"/g)].map(match => match[1]));
for (const [view, icon] of [['artists', 'microphone'], ['new', 'disc'], ['popular', 'trending']]) {
    check(symbols.has(icon), `The ${icon} exists in the native icon sprite`);
    const nav = template.match(new RegExp(`data-view="${view}"[^<]*<\\?= \\$icon\\('([^']+)'\\)`));
    check(nav?.[1] === icon, `${view} navigation references the correct thematic icon`);
}
for (const icon of ['microphone', 'disc', 'trending']) {
    const symbol = template.match(new RegExp(`<symbol id="rp-i-${icon}"([^>]+)>`));
    check(symbol?.[1].includes('viewBox="0 0 24 24"'), `${icon} shares the existing 24 px icon coordinate system`);
}
check(merged('.rp-icon').get('stroke-width') === '1.6', 'Navigation icons retain the shared line weight');
check(merged('.rp-artist-placeholder').get('width') === '100%' && merged('.rp-artist-placeholder').get('height') === '100%', 'Missing artist artwork fills its reserved circular geometry');
const albumBadge = merged('.rp-album-saved'), badgeIcon = merged('.rp-album-saved>.rp-icon');
check(albumBadge.get('width') === albumBadge.get('height'), 'Saved album badge stays square on mobile and desktop');
check(albumBadge.get('display') === 'grid' && albumBadge.get('place-items') === 'center', 'Saved album heart is centered independently of inline SVG baselines');
check(albumBadge.get('padding') === '0' && albumBadge.get('line-height') === '1', 'The badge does not inherit text padding or line-height offsets');
check(badgeIcon.get('display') === 'block' && badgeIcon.get('width') === badgeIcon.get('height'), 'The direct heart icon has a square block box');
check(parseFloat(albumBadge.get('width')) > parseFloat(badgeIcon.get('width')), 'The heart fits within the circular badge without touching its edge');
const artistEdit = merged('.rp-artist-detail .rp-button');
check(artistEdit.get('display') === 'inline-flex' && artistEdit.get('align-items') === 'center' && parseFloat(artistEdit.get('gap')) > 0, 'Artist editor icon and label have separate centered layout boxes');
check(parseFloat(artistEdit.get('min-height')) >= 44 && artistEdit.get('max-width') === '100%', 'Artist editor action remains bounded and touch accessible');

// Forms and keyboard controls keep a visible inset focus treatment. No focus
// rule may reintroduce the outside ring clipped by a scrolling dialog field.
for (const rule of rules.filter(rule => rule.selectors.some(selector => /:focus-(?:visible|within)/.test(selector)))) {
    if (rule.declarations.has('outline')) check(['0', 'none'].includes(rule.declarations.get('outline')), 'Focused controls do not draw outside their layout bounds');
    if (rule.declarations.has('box-shadow')) check(rule.declarations.get('box-shadow') === 'none' || rule.declarations.get('box-shadow').startsWith('inset'), 'Focus does not add an outside shadow');
}
for (const selector of ['input:focus-visible', 'select:focus-visible', 'textarea:focus-visible', 'a:focus-visible']) {
    check(merged(selector).get('box-shadow')?.includes('inset'), `${selector} retains a visible bounded focus indication`);
}
const light = ':root:not([data-theme=dark]) ';
for (const selector of ['.rp-track-play', '.rp-catalog-inline-play', '.rp-catalog-card-play', '.rp-catalog-save']) {
    const normal = merged(light + selector);
    check(normal.get('background') === 'var(--rp-paper)' && normal.get('color') === 'var(--rp-ink)', `${selector} starts light in the light theme`);
    const hovered = merged(light + selector + ':not(:disabled):hover', rule => rule.contexts.some(context => context.includes('(hover:hover)')));
    check(hovered.get('background') === 'var(--rp-ink)' && hovered.get('color') === 'var(--rp-paper)', `${selector} has dark readable pointer hover in the light theme`);
}
for (const selector of ['.rp-track.is-current .rp-track-play', '.rp-album-card.is-playing .rp-catalog-card-play', '.rp-catalog-save.is-saved', '.rp-sort-option[aria-selected=true]']) {
    check(merged(light + selector).get('background') === 'var(--rp-line)', `${selector} keeps a warm neutral selected state instead of green`);
}
check(merged('[data-theme=dark] .rp-track.is-current .rp-track-play').get('background') === '#bed0ad', 'Dark current-track emphasis is preserved');

// Evaluate the cascade for real menu class/attribute combinations. Old light
// and mobile selectors are deliberately retained elsewhere in the stylesheet;
// checking only the new policy rule would miss a specificity regression.
function functionEnd(source, open) {
    let depth = 1;
    for (let i = open + 1; i < source.length; i++) {
        if (source[i] === '(') depth++;
        if (source[i] === ')' && --depth === 0) return i;
    }
    throw new Error('Unclosed CSS selector function');
}
function compareWeight(left, right) {
    for (let i = 0; i < 3; i++) if (left[i] !== right[i]) return left[i] - right[i];
    return 0;
}
function specificity(selector) {
    const weight = [0, 0, 0];
    for (let i = 0; i < selector.length;) {
        const tail = selector.slice(i), functional = tail.match(/^:(is|not|has|where)\(/);
        if (functional) {
            const open = i + functional[0].length - 1, end = functionEnd(selector, open);
            if (functional[1] !== 'where') {
                const inner = splitTopLevel(selector.slice(open + 1, end), ',').map(specificity).sort(compareWeight).at(-1);
                for (let j = 0; j < 3; j++) weight[j] += inner[j];
            }
            i = end + 1; continue;
        }
        const token = tail.match(/^(\[[^\]]+\]|#[\w-]+|\.[\w-]+|::[\w-]+|:[\w-]+|[a-z][\w-]*)/i);
        if (token) {
            const value = token[1]; weight[value.startsWith('#') ? 0 : value.startsWith('::') || /^[a-z]/i.test(value) ? 2 : 1]++;
            i += value.length;
        } else i++;
    }
    return weight;
}
function compoundMatches(selector, node) {
    for (let i = 0; i < selector.length;) {
        const tail = selector.slice(i), functional = tail.match(/^:(is|not|where)\(/);
        if (functional) {
            const open = i + functional[0].length - 1, end = functionEnd(selector, open);
            const any = splitTopLevel(selector.slice(open + 1, end), ',').some(part => compoundMatches(part, node));
            if (functional[1] === 'not' ? any : !any) return false;
            i = end + 1; continue;
        }
        const token = tail.match(/^(\[[^\]]+\]|\.[\w-]+|:[\w-]+|\*|[a-z][\w-]*)/i);
        if (!token) return false;
        const value = token[1];
        if (value.startsWith('.')) { if (!node.classes.has(value.slice(1))) return false; }
        else if (value.startsWith('[')) {
            const attr = value.match(/^\[([\w-]+)(?:=(?:"([^"]*)"|'([^']*)'|([^\]]+)))?\]$/);
            if (!attr || !Object.hasOwn(node.attrs, attr[1])) return false;
            const expected = attr[2] ?? attr[3] ?? attr[4];
            if (expected !== undefined && node.attrs[attr[1]] !== expected) return false;
        } else if (value.startsWith(':')) {
            if (value === ':root' ? node.tag !== 'html' : !node.states.has(value.slice(1))) return false;
        } else if (value !== '*' && node.tag !== value) return false;
        i += value.length;
    }
    return true;
}
function selectorMatches(selector, node) {
    const parts = splitTopLevel(selector, ' ');
    if (!compoundMatches(parts.pop(), node)) return false;
    for (let i = parts.length - 1; i >= 0; i--) {
        node = node.parent;
        while (node && !compoundMatches(parts[i], node)) node = node.parent;
        if (!node) return false;
    }
    return true;
}
function mediaMatches(context, fixture) {
    if (!context.startsWith('@media')) return false;
    return splitTopLevel(context.slice(6).trim(), ',').some(group => {
        const conditions = [...group.matchAll(/\(([\w-]+):([^)]*)\)/g)];
        return conditions.every(([, name, value]) => {
            if (name === 'max-width') return fixture.width <= parseFloat(value);
            if (name === 'min-width') return fixture.width >= parseFloat(value);
            if (name === 'pointer') return value === fixture.pointer;
            if (name === 'hover') return value === (fixture.pointer === 'fine' ? 'hover' : 'none');
            if (name === 'prefers-reduced-motion') return value === (fixture.reduce ? 'reduce' : 'no-preference');
            return false;
        });
    });
}
function cascade(node, fixture) {
    const values = new Map(), weights = new Map();
    for (const rule of rules) {
        if (!rule.contexts.every(context => mediaMatches(context, fixture))) continue;
        for (const selector of rule.selectors) {
            if (!selectorMatches(selector, node)) continue;
            const weight = specificity(selector);
            for (const [name, value] of rule.declarations) {
                const declarations = [[name, value]];
                // A later border shorthand resets its longhands. Without this
                // expansion a stale selected border-color can falsely appear
                // active, or a higher-specificity press color can be missed.
                if (name === 'border') {
                    const tokens = splitTopLevel(value, ' ');
                    declarations.push(['border-width', tokens.find(token => /^(?:0|[\d.]+px)$/.test(token)) || 'medium']);
                    declarations.push(['border-style', tokens.find(token => /^(?:none|solid|dashed|dotted)$/.test(token)) || 'none']);
                    declarations.push(['border-color', tokens.find(token => /^var\(|^#|^transparent$|^currentColor$/i.test(token)) || 'currentColor']);
                }
                for (const [property, resolved] of declarations) {
                    if (!weights.has(property) || compareWeight(weight, weights.get(property)) >= 0) { values.set(property, resolved); weights.set(property, weight); }
                }
            }
        }
    }
    return values;
}
function menuNode(kind, theme, state) {
    const element = (tag, classes = [], attrs = {}, states = [], parent = null) => ({tag, classes:new Set(classes), attrs, states:new Set(states), parent});
    const html = element('html', [], {'data-theme':theme}), body = element('body', ['rp-body'], {}, [], html);
    const library = element('section', ['rp-library', 'rp-library--catalog'], {}, [], body);
    const aside = element('aside', ['rp-playlists'], {}, [], library);
    return element('button', [kind, ...(kind === 'rp-drive-button' ? ['rp-upload-button'] : []), ...(state === 'selected' ? ['is-selected'] : [])], {'data-action':kind}, state === 'selected' ? [] : [state], aside);
}
for (const theme of ['light', 'dark']) for (const width of [390, 1280]) for (const pointer of ['fine', 'coarse']) for (const kind of ['rp-playlist', 'rp-catalog-nav-button', 'rp-drive-button']) for (const state of ['normal', 'selected', 'active', 'hover', 'focus-visible']) {
    const computed = cascade(menuNode(kind, theme, state), {width, pointer});
    const soft = (state === 'selected' && kind !== 'rp-drive-button') || state === 'active' || state === 'focus-visible' || (state === 'hover' && pointer === 'fine');
    const framed = kind === 'rp-drive-button' || width <= 760;
    const context = `${kind}/${theme}/${width}/${pointer}/${state}`;
    check(computed.get('background') === (soft ? 'var(--rp-line)' : 'transparent'), `Original soft menu background is restored: ${context}`);
    check(computed.get('border-width') === '1px' && computed.get('border-style') === 'solid', `Menu reserves the original stable border geometry: ${context}`);
    check(computed.get('border-color') === (framed ? 'var(--rp-line)' : 'transparent'), `Only mobile tabs and Google Drive have visible frames: ${context}`);
    check(computed.get('box-shadow') === 'none' && computed.get('outline') === '0', `${kind} has no ring or outside shadow in ${state}`);
    if (state === 'focus-visible') check(computed.get('text-decoration') === 'underline', `${kind} has visible keyboard focus without a box`);
    if (state === 'selected' && kind !== 'rp-drive-button') check(computed.get('font-weight') === '500', `${kind} retains the original moderate selected text weight`);
}
const iconPolicy = merged('.rp-body .rp-library :is(.rp-playlist,.rp-catalog-nav-button,.rp-drive-button)[data-action] .rp-icon');
check(iconPolicy.get('animation') === 'none' && iconPolicy.get('transition') === 'none' && iconPolicy.get('transform') === 'none', 'Menu icons cannot jitter or animate independently');
const allIcons = merged('.rp-body .rp-icon');
for (const property of ['animation', 'transition', 'transform']) check(allIcons.get(property) === 'none!important', `Every control icon overrides ${property} motion, including expanded menu carets`);
for (const selector of ['.rp-body button', '.rp-body a']) {
    const properties = splitTopLevel(merged(selector).get('transition-property'), ',');
    check(properties.every(property => ['background-color', 'border-color', 'box-shadow'].includes(property)) && properties.includes('background-color'), `${selector} transitions backgrounds and boundaries without inherited icon-color or position interpolation`);
}
check(merged('.is-buffering .rp-play::after').get('animation')?.includes('rp-buffer-turn'), 'The independent playback buffering spinner remains animated');
check(merged('.rp-drive-loading::before').get('animation')?.includes('rp-buffer-turn'), 'The independent Google Drive loading spinner remains animated');
check(!rules.some(rule => rule.selectors.some(selector => selector.includes('.rp-rotor')) && /^none/.test(rule.declarations.get('transform') || '')), 'Freezing control icons does not freeze the physical reel transforms');
for (const rule of rules) for (const selector of rule.selectors) {
    if (!/:hover|:active/.test(selector) || !rule.declarations.has('transform')) continue;
    if (/\simg\b/.test(selector)) continue; // Artwork zoom is independent of the button box.
    check(rule.declarations.get('transform') === 'none', `Button hover/press does not shift its position: ${selector}`);
}

function luminance(hex) {
    const channels = hex.replace('#', '').match(/../g).map(value => parseInt(value, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return .2126 * channels[0] + .7152 * channels[1] + .0722 * channels[2];
}
function resolvedColor(value, palette) { return value.replace(/var\((--[\w-]+)\)/g, (_, name) => palette.get(name)); }
for (const [theme, state] of [['light', 'normal'], ['light', 'hover'], ['dark', 'normal'], ['dark', 'hover']]) {
    const node = menuNode('rp-catalog-save', theme, state); node.classes.add('is-saved'); node.classes.add('rp-button');
    const values = cascade(node, {width:1280, pointer:'fine'}), palette = merged(':root');
    for (const pair of merged(theme === 'dark' ? ':root[data-theme=dark]' : ':root:not([data-theme=dark])')) palette.set(...pair);
    const background = luminance(resolvedColor(values.get('background'), palette)), text = luminance(resolvedColor(values.get('color'), palette));
    check((Math.max(background, text) + .05) / (Math.min(background, text) + .05) >= 4.5, `Saved collection text is readable in ${theme}/${state}`);
    if (theme === 'dark' && state === 'normal') check(values.get('background') === 'var(--rp-accent)' && values.get('color') === 'var(--rp-paper)', 'Dark saved collection state uses a prominent cream background and dark text');
}

const playerSource = fs.readFileSync(path.join(root, 'assets/player.js'), 'utf8');
const catalogSource = fs.readFileSync(path.join(root, 'assets/catalog.js'), 'utf8');
const albumEditor = playerSource.slice(playerSource.indexOf('    function editAlbum('), playerSource.indexOf('    function editArtist('));
check(!albumEditor.includes('data-action="catalog-delete"'), 'Album edit form is free of the bright delete entry');
check(catalogSource.includes('class="rp-album-detail-management"') && catalogSource.includes('class="rp-catalog-delete"'), 'Album deletion is located alongside separate album management actions');
check(playerSource.includes("dialog.classList.add('rp-dialog--album-delete')"), 'Album deletion confirmation selects its neutral visual variant');
const deleteLink = merged('.rp-catalog-delete'), confirmDelete = merged('.rp-dialog--album-delete .rp-button--danger');
check(deleteLink.get('background') === 'transparent' && deleteLink.get('border') === '0' && deleteLink.get('color') === 'var(--rp-muted)', 'Album delete entry is a quiet text action');
check(confirmDelete.get('background') === 'var(--rp-paper)' && confirmDelete.get('color') === 'var(--rp-ink)' && confirmDelete.get('box-shadow') === 'none', 'Album deletion confirmation has readable neutral theme colors without glow');

console.log(`Tape Room interface polish: ${checks} checks passed (hover/touch/focus, themes, modal widths and safe areas, navigation icons).`);
