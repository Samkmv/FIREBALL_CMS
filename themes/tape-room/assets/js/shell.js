(() => {
    'use strict';
    const roots=[...document.querySelectorAll('[data-tr-shell]')];
    const player=document.querySelector('[data-player]');
    const syncTheme=()=>{
        const theme=document.documentElement.dataset.theme==='dark'?'dark':'light';
        document.documentElement.dataset.bsTheme=theme;
        roots.forEach(root=>root.dataset.bsTheme=theme);
        document.querySelectorAll('[data-tr-theme-icons]').forEach(button=>{
            const label=theme==='dark'?'Включить светлую тему':'Включить тёмную тему';button.setAttribute('aria-label',label);button.title=label;button.setAttribute('aria-pressed',String(theme==='dark'));
            button.querySelector('[data-tr-theme-light]').hidden=theme==='dark';button.querySelector('[data-tr-theme-dark]').hidden=theme!=='dark';
        });
        document.querySelectorAll('meta[name="theme-color"]').forEach(meta=>meta.content=theme==='dark'?'#191c1a':'#f7f5f0');
        try{localStorage.setItem('tape-room:theme',theme);}catch(_){}
    };
    syncTheme();new MutationObserver(syncTheme).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
    document.addEventListener('click',event=>{
        const themeToggle=event.target.closest('[data-tr-theme-toggle]');
        if(themeToggle){document.documentElement.dataset.theme=document.documentElement.dataset.theme==='dark'?'light':'dark';return;}
        const visibility=event.target.closest('[data-tr-password]');
        if(visibility){const input=document.getElementById(visibility.dataset.trPassword);if(!input)return;const shown=input.type==='password';input.type=shown?'text':'password';visibility.setAttribute('aria-label',shown?'Скрыть пароль':'Показать пароль');visibility.setAttribute('aria-pressed',String(shown));const icon=visibility.querySelector('.tr-icon');icon.classList.toggle('ci-eye',!shown);icon.classList.toggle('ci-eye-off',shown);return;}
        if(event.target.closest('[data-tr-show-toast]'))bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
    });
    document.querySelectorAll('[data-tr-demo-form]').forEach(form=>form.addEventListener('submit',event=>{
        event.preventDefault();form.classList.add('was-validated');if(form.checkValidity())bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
    }));
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element=>new bootstrap.Tooltip(element,{container:element.closest('.tr-bootstrap')}));
    if(player){
        const menu=document.getElementById('tr-site-menu');
        menu.addEventListener('click',event=>{
            const button=event.target.closest('[data-action]');if(!button || !menu.classList.contains('show'))return;
            const drawer=bootstrap.Offcanvas.getOrCreateInstance(menu);
            if(['create-playlist','drive'].includes(button.dataset.action)){
                event.preventDefault();event.stopPropagation();menu.addEventListener('hidden.bs.offcanvas',()=>button.click(),{once:true});drawer.hide();return;
            }
            if(['catalog-view','select-playlist'].includes(button.dataset.action)){
                menu.addEventListener('hidden.bs.offcanvas',()=>player.querySelector('.rp-library').scrollIntoView({block:'start',behavior:'auto'}),{once:true});drawer.hide();
            }
        },true);
        // Side navigation delegates to the existing player's menu actions.
        // No new audio element, queue, dialog or playback lifecycle is created.
        const view=new URLSearchParams(location.hash.slice(1)).get('room');
        if(['overview','albums','artists','genres','new','popular','mine'].includes(view)){
            player.querySelector(`[data-catalog-nav] [data-view="${view}"]`)?.click();
            history.replaceState(null,'',location.pathname+location.search);
        }
    }
})();
