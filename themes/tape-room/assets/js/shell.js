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
    document.querySelectorAll('[data-tr-demo-seek]').forEach(range=>range.addEventListener('input',()=>{const progress=Number(range.value)/10,seconds=Math.round(222*progress/100);range.style.setProperty('--fill',`${progress}%`);range.style.setProperty('--buffered',`${Math.max(68,progress)}%`);document.querySelector('[data-tr-demo-elapsed]').textContent=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')}`;}));
    document.addEventListener('click',event=>{
        const filePicker=event.target.closest('[data-tr-file-pick]');
        if(filePicker){filePicker.closest('[data-tr-file-picker]').querySelector('input[type=file]').click();return;}
        const demoSort=event.target.closest('[data-tr-demo-sort]');
        if(demoSort){const dropdown=demoSort.closest('.dropdown');dropdown.querySelector('[data-tr-demo-sort-label]').textContent=demoSort.firstElementChild.textContent;dropdown.querySelectorAll('[data-tr-demo-sort]').forEach(option=>{const selected=option===demoSort;option.setAttribute('aria-selected',String(selected));option.querySelector('[data-tr-demo-sort-check]').hidden=!selected;});return;}
        const demoPlay=event.target.closest('[data-tr-demo-play]');
        if(demoPlay){const active=demoPlay.getAttribute('aria-pressed')!=='true';demoPlay.setAttribute('aria-pressed',String(active));demoPlay.setAttribute('aria-label',active?'Пауза — пример':'Воспроизвести — пример');demoPlay.querySelector('.tr-icon').className=`ci-${active?'pause':'play-filled'} tr-icon`;return;}
        const demoFavorite=event.target.closest('[data-tr-demo-favorite]');
        if(demoFavorite){const active=demoFavorite.getAttribute('aria-pressed')!=='true';demoFavorite.setAttribute('aria-pressed',String(active));demoFavorite.setAttribute('aria-label',active?'Убрать из избранного — пример':'Добавить в избранное — пример');demoFavorite.querySelector('.tr-icon').className=`ci-${active?'heart-filled':'heart'} tr-icon`;return;}
        const themeToggle=event.target.closest('[data-tr-theme-toggle]');
        if(themeToggle){document.documentElement.dataset.theme=document.documentElement.dataset.theme==='dark'?'light':'dark';return;}
        const visibility=event.target.closest('[data-tr-password]');
        if(visibility){const input=document.getElementById(visibility.dataset.trPassword);if(!input)return;const shown=input.type==='password';input.type=shown?'text':'password';visibility.setAttribute('aria-label',shown?'Скрыть пароль':'Показать пароль');visibility.setAttribute('aria-pressed',String(shown));const icon=visibility.querySelector('.tr-icon');icon.classList.toggle('ci-eye',!shown);icon.classList.toggle('ci-eye-off',shown);return;}
        if(event.target.closest('[data-tr-show-toast]'))bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
    });
    document.addEventListener('change',event=>{if(event.target.matches('[data-tr-file-picker] input[type=file]'))event.target.closest('[data-tr-file-picker]').querySelector('[data-tr-file-name]').textContent=event.target.files[0]?.name||'Файл не выбран';});
    document.querySelectorAll('[data-tr-demo-form]').forEach(form=>form.addEventListener('submit',event=>{
        event.preventDefault();form.classList.add('was-validated');if(form.checkValidity())bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
    }));
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element=>new bootstrap.Tooltip(element,{container:element.closest('.tr-bootstrap')}));
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(element=>new bootstrap.Popover(element,{container:element.closest('.tr-bootstrap'),trigger:'focus'}));
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
