(function(root){
    'use strict';
    /** Only explicitly marked catalog links enter the persistent playback shell. */
    function musicURL(value,base,current){
        let url,music,here;try{here=new URL(current);url=new URL(value,here);music=new URL(base || '/music',here);}catch(_){return null;}
        if(!['http:','https:'].includes(url.protocol) || url.origin!==here.origin || music.origin!==here.origin)return null;
        const prefix=music.pathname.replace(/\/$/,''),path=url.pathname;
        if(path!==prefix && !path.startsWith(prefix+'/'))return null;
        const relative=path.slice(prefix.length).replace(/^\//,'').replace(/\/$/,'');
        if(relative && !/^(?:albums|artists|tracks|new|popular|genres|collection|(?:album|track)\/[1-9][0-9]*(?:-[a-z0-9-]+)?)$/.test(relative))return null;
        if(url.searchParams.has('fragment'))url.searchParams.delete('fragment');return url;
    }
    function searchURL(data,action,base,current){
        const url=musicURL(action,base,current);if(!url)return null;
        url.search='';let facets=0;
        for(const kind of ['genre','tag']){
            const values=[...data.getAll(kind),...data.getAll(kind+'[]')],slugs=[];
            for(const value of values){if(typeof value!=='string')throw new Error('Некорректный музыкальный фильтр.');for(const part of value.split(',')){const slug=part.trim();if(!slug)continue;if(slug.length>120 || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug))throw new Error('Укажите адресные имена жанров и тегов, например jazz или retro.');if(!slugs.includes(slug))slugs.push(slug);}}
            facets+=slugs.length;if(facets>10)throw new Error('Можно выбрать не более 10 жанров и тегов.');
            for(const slug of slugs)url.searchParams.append(kind+(slugs.length>1?'[]':''),slug);
        }
        const allowed=new Set(['q','artist','sort','from','to','kind','per_page']);
        for(const [name,value]of data)if(allowed.has(name) && typeof value==='string' && value.trim())url.searchParams.append(name,value.trim());
        return url;
    }
    function fragmentPage(html,document=root.document){
        if(typeof html!=='string' || html.length>5*1024*1024)throw new Error('Недопустимый фрагмент музыкальной страницы.');
        const parsed=new root.DOMParser().parseFromString(html,'text/html'),pages=parsed.querySelectorAll('[data-music-page]');
        if(pages.length!==1 || pages[0].querySelector('[data-tape-room-host]'))throw new Error('Страница не поддерживает музыкальную навигацию.');
        const page=pages[0],configs=page.querySelectorAll('script[type="application/json"][data-music-data]');
        if(configs.length!==1)throw new Error('Отсутствует безопасное состояние музыкальной страницы.');
        JSON.parse(configs[0].textContent);
        for(const script of page.querySelectorAll('script'))if(script!==configs[0])script.remove();
        for(const node of page.querySelectorAll('iframe,object,embed,audio,video,base,style,link,meta,foreignObject'))node.remove();
        for(const node of [page,...page.querySelectorAll('*')])for(const attribute of [...node.attributes]){
            if(/^on/i.test(attribute.name))node.removeAttribute(attribute.name);
            if(['href','src','action','xlink:href'].includes(attribute.name.toLowerCase())){
                if(attribute.value.trim().startsWith('#'))continue;
                let url;try{url=new URL(attribute.value,root.location?.href || 'http://localhost/');}catch(_){node.removeAttribute(attribute.name);continue;}
                const allowed=attribute.name.toLowerCase()==='href'?['http:','https:','mailto:','tel:']:['http:','https:'];
                if(!allowed.includes(url.protocol))node.removeAttribute(attribute.name);
            }
        }
        return document.importNode(page,true);
    }
    class TapeRoomNavigation {
        constructor(document=root.document){this.document=document;this.ticket=0;this.onClick=event=>this.click(event);this.onSubmit=event=>this.submit(event);this.onPop=()=>this.pop();document.addEventListener('click',this.onClick);document.addEventListener('submit',this.onSubmit);root.addEventListener('popstate',this.onPop);const page=document.querySelector('[data-music-page]');if(page && this.url(root.location.href)){const old=root.history.state;root.history.replaceState({...((old && typeof old==='object')?old:{}),tapeRoom:true},'',root.location.href);}}
        base(){try{return JSON.parse(this.document.querySelector('[data-music-data]')?.textContent || '{}').config?.base || '/music';}catch(_){return '/music';}}
        url(value){return musicURL(value,this.base(),root.location.href);}
        click(event){
            if(event.defaultPrevented || event.button!==0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)return;
            const link=event.target?.closest?.('a[data-music-link]');if(!link || link.hasAttribute('download') || link.target && link.target!=='_self')return;
            const url=this.url(link.href);if(!url || !this.document.querySelector('[data-music-page]'))return;
            if(url.pathname===root.location.pathname && url.search===root.location.search && url.hash)return;
            event.preventDefault();this.go(url,{history:true});
        }
        submit(event){
            const form=event.target?.closest?.('form[data-music-search]');
            if(event.defaultPrevented || !form || String(form.method).toLowerCase()!=='get' || !this.document.querySelector('[data-music-page]') || !this.url(form.action))return;
            try{const url=searchURL(new FormData(form),form.action,this.base(),root.location.href);if(!url)return;event.preventDefault();this.go(url,{history:true});}
            catch(error){event.preventDefault();root.TapeRoomPublicUI?.message(error.message);}
        }
        pop(){const url=this.url(root.location.href);if(url && this.document.querySelector('[data-music-page]'))this.go(url,{history:false});else root.location.assign(root.location.href);}
        async go(url,{history=true}={}){
            this.controller?.abort();const controller=this.controller=new AbortController(),ticket=++this.ticket,old=this.document.querySelector('[data-music-page]');
            if(!old){root.location.assign(url.href);return;}
            old.setAttribute('aria-busy','true');const request=new URL(url);request.searchParams.set('fragment','1');const timeout=root.setTimeout(()=>controller.abort(),20000);
            try{
                const response=await root.fetch(request,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
                const result=await response.json();if(!response.ok || result.status!==true)throw new Error('Не удалось открыть музыкальную страницу.');
                const page=fragmentPage(result.html,this.document);if(ticket!==this.ticket)return;
                old.replaceWith(page);this.seo(result);if(history)root.history.pushState({tapeRoom:true},'',url.href);
                root.scrollTo(0,0);const heading=page.querySelector('h1,h2');if(heading){heading.setAttribute('tabindex','-1');heading.focus({preventScroll:true});heading.addEventListener('blur',()=>heading.removeAttribute('tabindex'),{once:true});}
                this.document.dispatchEvent(new CustomEvent('tape-room:navigation',{detail:{url:url.href,page}}));
            }catch(error){if(ticket===this.ticket){old.removeAttribute('aria-busy');if(error.name!=='AbortError' || !controller.signal.aborted || this.controller===controller)root.location.assign(url.href);}}
            finally{root.clearTimeout(timeout);if(ticket===this.ticket)this.controller=null;}
        }
        meta(name,value,property=false){const selector=property?`meta[property="${name}"]`:`meta[name="${name}"]`;let node=this.document.head.querySelector(selector);if(!value){node?.remove();return;}if(!node){node=this.document.createElement('meta');node.setAttribute(property?'property':'name',name);this.document.head.append(node);}node.setAttribute('content',String(value));}
        seo(result){
            if(typeof result.title==='string')this.document.title=result.title;
            this.meta('description',result.description || '');this.meta('og:title',result.title || '',true);this.meta('og:description',result.description || '',true);this.meta('twitter:title',result.title || '');this.meta('twitter:description',result.description || '');
            let canonical=this.document.head.querySelector('link[rel=canonical]');if(result.canonical){if(!canonical){canonical=this.document.createElement('link');canonical.rel='canonical';this.document.head.append(canonical);}canonical.href=result.canonical;}else canonical?.remove();
            this.meta('og:url',result.canonical || '',true);this.meta('twitter:url',result.canonical || '');
            this.meta('og:image',result.image || '',true);this.meta('twitter:image',result.image || '');
            for(const name of ['og:image:url','og:image:secure_url','og:image:width','og:image:height','og:image:alt'])this.meta(name,'',true);
            this.document.head.querySelector('link[rel=image_src]')?.remove();
        }
        destroy(){this.controller?.abort();this.ticket++;this.document.removeEventListener('click',this.onClick);this.document.removeEventListener('submit',this.onSubmit);root.removeEventListener('popstate',this.onPop);}
    }
    if(typeof module!=='undefined' && module.exports)module.exports={musicURL,searchURL,fragmentPage,TapeRoomNavigation};
    root.TapeRoomNavigation=TapeRoomNavigation;
    if(root.document && !root.TapeRoomNavigator){const start=()=>{if(root.document.querySelector('[data-music-page]'))root.TapeRoomNavigator=new TapeRoomNavigation();};if(root.document.readyState==='loading')root.document.addEventListener('DOMContentLoaded',start,{once:true});else start();}
})(typeof window!=='undefined'?window:globalThis);
