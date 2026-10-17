(() => {
    'use strict';
    const key = 'tape-room:player-view';
    const siteKey = 'tape-room:site-player';
    const read = () => { try { return localStorage.getItem(key) === 'mini' ? 'mini' : 'deck'; } catch (_) { return 'deck'; } };
    const readSite = () => { try { return localStorage.getItem(siteKey) === '1'; } catch (_) { return false; } };
    const app = document.querySelector('[data-player]');
    const mini = app?.querySelector('[data-mini-player]');
    let mode = read();
    let site = readSite();
    const enabled = () => site && mode === 'mini';
    function cookie() {
        const home=JSON.parse(document.querySelector('meta[name="tr-site-navigation"]')?.content || '{}').home || '/';
        document.cookie=`tr_site_player=${site?'1':'0'}; Path=${new URL(home,location.href).pathname}; Max-Age=31536000; SameSite=Lax${location.protocol==='https:'?'; Secure':''}`;
    }
    // Move the existing transport, keeping its listeners, focus, queue and audio intact.
    const mounts = mini ? [
        [app.querySelector('.rp-main-controls'), mini.querySelector('[data-mini-transport]')],
        [app.querySelector('.rp-secondary-controls'), mini.querySelector('[data-mini-secondary]')]
    ].map(([node, target]) => {
        const anchor = document.createComment('Tape Room transport home');
        node.before(anchor);
        return {node, target, anchor};
    }) : [];
    const measure = () => {
        if (mini && !mini.hidden) document.documentElement.style.setProperty('--tr-mini-player-height', `${Math.ceil(mini.getBoundingClientRect().height)}px`);
    };
    function apply(value, persist = false) {
        mode = value === 'mini' ? 'mini' : 'deck';
        const focused = document.activeElement;
        if (mini) {
            for (const {node, target, anchor} of mounts) {
                if (mode === 'mini' && node.parentNode !== target) target.append(node);
                else if (mode === 'deck' && node.previousSibling !== anchor) anchor.after(node);
            }
            mini.hidden = mode !== 'mini' || (app.dataset.sitePage === '1' && !enabled());
            if (mode !== 'mini') bootstrap.Offcanvas.getInstance(app.querySelector('[data-playback-queue]'))?.hide();
            app.dataset.playerView = mode;
            document.body.classList.toggle('tr-mini-active', !mini.hidden);
            // append() may clear focus in Safari; keep keyboard control on the same button.
            if (focused && mounts.some(({node}) => node.contains(focused))) focused.focus({preventScroll:true});
            measure();
        }
        document.documentElement.dataset.playerView = mode;
        document.querySelectorAll('[data-tr-mini-toggle]').forEach(toggle => { toggle.checked = mode === 'mini'; });
        document.querySelectorAll('[data-tr-site-player-toggle]').forEach(toggle => { toggle.checked = site; });
        if (persist) { try { localStorage.setItem(key, mode); } catch (_) {} }
    }
    if (mini) {
        const cover = app.querySelector('.rp-cover-button');
        const artwork = mini.querySelector('[data-mini-artwork]');
        const originals = [...cover.querySelectorAll('img')];
        const copies = originals.map(original => {
            const image = original.cloneNode(false);
            for (const attribute of [...image.attributes]) if (attribute.name.startsWith('data-') || attribute.name === 'id') image.removeAttribute(attribute.name);
            image.alt = ''; image.width = image.height = 48; image.removeAttribute('fetchpriority');
            artwork.append(image);
            return image;
        });
        const textPairs = [['[data-title]','[data-mini-title]'],['[data-artist]','[data-mini-artist]'],['[data-audio-quality]','[data-mini-status]']].map(([from,to]) => [app.querySelector(from),mini.querySelector(to)]);
        function sync() {
            for (const [source, target] of textPairs) {
                const text=target.querySelector('[data-tr-marquee-text]') || target;
                if (source.textContent !== target.textContent) text.textContent = source.textContent;
                if (target.title !== source.textContent) target.title = source.textContent;
            }
            artwork.classList.toggle('has-custom-cover', cover.classList.contains('has-custom-cover'));
            originals.forEach((original, index) => {
                for (const name of ['src','srcset']) {
                    const value = original.getAttribute(name);
                    if (value === copies[index].getAttribute(name)) continue;
                    if (value === null) copies[index].removeAttribute(name); else copies[index].setAttribute(name, value);
                }
            });
        }
        const observer = new MutationObserver(sync);
        textPairs.forEach(([source]) => observer.observe(source, {childList:true,characterData:true,subtree:true}));
        observer.observe(cover, {attributes:true,subtree:true,attributeFilter:['src','srcset','class']});
        sync();
        if ('ResizeObserver' in window) new ResizeObserver(measure).observe(mini);
        window.addEventListener('resize', measure);
    }
    document.addEventListener('change',event=>{
        if(event.target.matches('[data-tr-mini-toggle]'))apply(event.target.checked?'mini':'deck',true);
        else if(event.target.matches('[data-tr-site-player-toggle]')){
            site=event.target.checked;
            try{localStorage.setItem(siteKey,site?'1':'0');}catch(_){}
            cookie();apply(site?'mini':mode,true);
        }else return;
        document.dispatchEvent(new CustomEvent('tape-room:player-view',{detail:{mode,site,enabled:enabled()}}));
    });
    window.addEventListener('storage', event => { if ([key,siteKey,null].includes(event.key)){site=readSite();cookie();apply(read());document.dispatchEvent(new CustomEvent('tape-room:player-view',{detail:{mode,site,enabled:enabled()}}));} });
    document.addEventListener('tape-room:page',()=>apply(mode));
    window.TapeRoomPlayerView={enabled,refresh:()=>apply(mode)};
    cookie();
    apply(mode);
})();
