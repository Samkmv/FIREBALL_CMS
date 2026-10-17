(() => {
    'use strict';
    const marker=document.querySelector('meta[name="tr-site-navigation"]');
    if(!marker)return;
    const initial=JSON.parse(marker.content),home=new URL(initial.home,location.href),app=document.querySelector('[data-player]');
    const active=()=>window.TapeRoomPlayerView?.enabled();
    if(!app){
        // The preference cookie lets the next response include the native player.
        const mount=()=>{if(initial.player && active() && document.cookie.split(';').some(part=>part.trim()==='tr_site_player=1'))location.reload();};
        document.addEventListener('tape-room:player-view',mount);mount();return;
    }
    const audio=app.querySelector('[data-audio]'),homeTitle=app.dataset.sitePage==='1'?'Tape Room · Бета — личная музыкальная комната':document.title;
    let page=app.querySelector('[data-tr-page]'),pending=null,generation=0;
    const away=()=>app.dataset.sitePage==='1';
    const native=url=>{audio.pause();location.assign(url.href);};
    const eligible=url=>url.origin===home.origin && url.pathname.startsWith(home.pathname) &&
        !/^(?:admin(?:\/|$)|login(?:\/|$)|register(?:\/|$)|logout(?:\/|$)|forgot-password(?:\/|$)|reset-password(?:\/|$)|two-factor[^/]*(?:\/|$)|tape-room\/(?:api|embed|share|drive|torrent-worker)(?:[/.]|$)|(?:assets|uploads|storage)(?:\/|$))/i.test(url.pathname.slice(home.pathname.length)) &&
        !/\.(?:zip|torrent|mp3|flac|wav|m4a|ogg|opus|aac|webm|pdf|png|jpe?g|webp|svg|ico|js|css)$/i.test(url.pathname);
    function hideOverlays(){
        for(const element of app.querySelectorAll('.offcanvas.show'))bootstrap.Offcanvas.getInstance(element)?.hide();
        for(const element of app.querySelectorAll('.dropdown-menu.show'))bootstrap.Dropdown.getInstance(element.parentElement.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
    }
    async function closePageOverlays(){
        await Promise.all([...page.querySelectorAll('.modal.show,.offcanvas.show')].map(element=>new Promise(resolve=>{
            const modal=element.classList.contains('modal'),event=modal?'hidden.bs.modal':'hidden.bs.offcanvas';
            const done=()=>{clearTimeout(timer);element.removeEventListener(event,done);resolve();};
            const timer=setTimeout(done,600);element.addEventListener(event,done,{once:true});
            bootstrap[modal?'Modal':'Offcanvas'].getInstance(element)?.hide();
        })));
    }
    function disposePage(){
        for(const element of page.querySelectorAll('[data-bs-toggle="tooltip"],[data-bs-toggle="popover"],.collapse,.modal,.offcanvas')){
            for(const name of ['Tooltip','Popover','Collapse','Modal','Offcanvas'])bootstrap[name]?.getInstance(element)?.dispose();
        }
    }
    function state(isPage){
        app.dataset.sitePage=isPage?'1':'0';
        if(isPage)app.removeAttribute('role');else app.setAttribute('role','main');
        page.hidden=!isPage;document.body.classList.toggle('tr-body',isPage);
        window.TapeRoomPlayerView.refresh();
    }
    function announce(){document.dispatchEvent(new CustomEvent('tape-room:page'));}
    function historyTo(url,replace){
        if(replace)history.replaceState(history.state,'',url.href);else history.pushState({},'',url.href);
    }
    function restore(url,historyMode='push'){
        if(away()){
            const previous=page;document.dispatchEvent(new CustomEvent('tape-room:page-leave'));
            closePageOverlays().then(()=>{if(previous===page && page.hidden){disposePage();page.replaceChildren();}});
        }
        pending?.abort();pending=null;generation++;app.removeAttribute('aria-busy');hideOverlays();state(false);document.title=homeTitle;
        document.querySelector('[data-tr-navigation-error]')?.remove();
        if(historyMode!=='pop')historyTo(url,historyMode==='replace');
        app.dispatchEvent(new CustomEvent('tape-room:navigate',{detail:{url:url.href}}));
        announce();window.scrollTo(0,0);currentUrl=location.href;
    }
    async function navigate(url,{pop=false,body=null}={}){
        if(url.pathname===home.pathname && !body){restore(url,pop?'pop':'push');return;}
        pending?.abort();const controller=new AbortController(),job=++generation;pending=controller;
        const timer=setTimeout(()=>controller.abort(),20000);
        app.setAttribute('aria-busy','true');hideOverlays();
        try{
            const response=await fetch(url,{method:body?'POST':'GET',body,credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html'},signal:controller.signal});
            if(job!==generation)return;
            const destination=new URL(response.url);
            if(!eligible(destination)||!response.headers.get('Content-Type')?.includes('text/html')){native(destination);return;}
            if(!response.ok)throw new Error('Page unavailable');
            const parsed=new DOMParser().parseFromString(await response.text(),'text/html');
            if(job!==generation)return;
            const metadata=parsed.querySelector('meta[name="tr-site-navigation"]'),next=parsed.querySelector('[data-tr-page]');
            if(!metadata||!next||next.querySelector('script:not([type="application/json"]),[data-tr-native-page]')){native(destination);return;}
            const incoming=JSON.parse(metadata.content);
            // A sign-out or a different account always starts a fresh player/session.
            if(incoming.account!==initial.account || incoming.home!==initial.home){native(destination);return;}
            await closePageOverlays();if(job!==generation)return;
            document.dispatchEvent(new CustomEvent('tape-room:page-leave'));
            disposePage();
            page.replaceWith(document.importNode(next,true));page=app.querySelector('[data-tr-page]');
            document.title=parsed.title;marker.content=metadata.content;
            document.querySelector('[data-tr-navigation-error]')?.remove();
            if(incoming.csrf){
                document.querySelectorAll('[name="needCSRFToken"]').forEach(field=>field.value=incoming.csrf);
                app.dispatchEvent(new CustomEvent('tape-room:csrf',{detail:{token:incoming.csrf}}));
            }
            const account=parsed.querySelector('.tr-account-dropdown'),oldAccount=app.querySelector('.tr-account-dropdown');
            if(account){bootstrap.Dropdown.getInstance(oldAccount.querySelector('[data-bs-toggle="dropdown"]'))?.dispose();oldAccount.replaceChildren(...[...account.childNodes].map(node=>document.importNode(node,true)));}
            state(true);if(!pop)historyTo(destination,Boolean(body));
            announce();window.scrollTo(0,0);
            const heading=page.querySelector('h1');if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}
        }catch(error){
            if(job!==generation)return;
            const notice=document.createElement('div');notice.className='alert alert-warning tr-navigation-error';notice.dataset.trNavigationError='';notice.setAttribute('role','alert');
            notice.textContent=body?'Не удалось получить результат сохранения. Откройте страницу ещё раз, чтобы проверить изменения.':'Не удалось открыть страницу. Проверьте соединение и повторите переход. Музыка продолжает играть.';
            const retry=document.createElement('a');retry.href=url.href;retry.textContent='Открыть страницу';notice.append(' ',retry);
            document.querySelector('[data-tr-navigation-error]')?.remove();app.querySelector('.tr-site-navigation').after(notice);
            if(pop)history.replaceState({},'',currentUrl);
        }finally{clearTimeout(timer);if(job===generation){pending=null;app.removeAttribute('aria-busy');currentUrl=location.href;}}
    }
    let currentUrl=location.href;
    document.addEventListener('click',event=>{
        if(!active()||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
        const link=event.target.closest('a[href]');
        if(!link||link.target||link.hasAttribute('download')||link.hasAttribute('data-tr-native')||link.closest('[data-tr-native-page]')||link.matches('[data-bs-toggle]'))return;
        const url=new URL(link.href,location.href);if(!eligible(url))return;
        if(url.pathname===location.pathname && url.search===location.search && url.hash && !url.hash.startsWith('#room='))return;
        event.preventDefault();navigate(url);
    });
    document.addEventListener('click',event=>{
        if(!away())return;
        const control=event.target.closest('[data-action="catalog-view"],[data-action="select-playlist"]');
        if(control){if(control.matches('a'))event.preventDefault();restore(home);}
    },true);
    document.addEventListener('submit',event=>{
        const form=event.target;
        if(!active()||event.defaultPrevented||!form.closest('[data-tr-page]')||form.matches('[data-tr-demo-form]')||form.target)return;
        const url=new URL(form.action,location.href),action=form.querySelector('[name="profile_action"]')?.value;
        // Authentication and security actions keep their native document lifecycle.
        if(form.method.toLowerCase()!=='post'||url.pathname!==home.pathname+'profile/settings'||!['details','avatar'].includes(action))return;
        event.preventDefault();if(form.dataset.trSaving)return;
        form.dataset.trSaving='1';const body=new FormData(form);
        if(event.submitter?.name)body.append(event.submitter.name,event.submitter.value);
        const submit=event.submitter;if(submit)submit.disabled=true;
        navigate(url,{body}).finally(()=>{delete form.dataset.trSaving;if(submit)submit.disabled=false;});
    });
    window.addEventListener('popstate',()=>{
        const url=new URL(location.href),previous=new URL(currentUrl);
        if(url.pathname===previous.pathname && url.search===previous.search && !url.hash.startsWith('#room=')){currentUrl=url.href;return;}
        if(active())navigate(url,{pop:true});else{audio.pause();location.reload();}
    });
    document.addEventListener('tape-room:player-view',event=>{
        if(!event.detail.enabled && pending){pending.abort();pending=null;generation++;app.removeAttribute('aria-busy');}
        if(!away()||event.detail.enabled)return;
        if(event.detail.mode==='deck')restore(home);else audio.pause();
    });
    state(away());
})();
