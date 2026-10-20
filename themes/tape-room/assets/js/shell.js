(() => {
    'use strict';
    let modalLayout=null;
    const isTyping=element=>element?.matches('textarea,input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]):not([type=hidden]):not([type=button]):not([type=submit])');
    const syncViewport=()=>{
        const viewport=window.visualViewport,visibleHeight=Math.min(window.innerHeight,viewport?.height||window.innerHeight);
        document.documentElement.style.setProperty('--tr-viewport-height',`${visibleHeight}px`);
        document.documentElement.style.setProperty('--tr-viewport-top',`${viewport?.offsetTop||0}px`);
        if(!modalLayout)return;
        const {modal}=modalLayout,field=document.activeElement,editing=modal.contains(field)&&isTyping(field);
        if(modalLayout.width!==window.innerWidth){modalLayout.width=window.innerWidth;modalLayout.height=window.innerHeight;modalLayout.top=0;modal.classList.remove('tr-modal-editing');}
        // Each open modal keeps its layout viewport; the keyboard only changes the scrollable form area.
        modal.style.setProperty('--tr-viewport-height',`${modalLayout.height}px`);
        modal.style.setProperty('--tr-viewport-top',`${modalLayout.top}px`);
        const covered=Math.max(0,modalLayout.height-visibleHeight);
        modal.style.setProperty('--tr-keyboard-space',`${covered>120?covered:0}px`);
        if(editing&&covered>120){
            const body=field.closest('.modal-body');
            if(body){const rect=field.getBoundingClientRect(),bottom=(viewport?.offsetTop||0)+visibleHeight-20;if(rect.bottom>bottom)body.scrollTop+=rect.bottom-bottom;}
        }else if(!editing&&covered<120){modal.classList.remove('tr-modal-editing');modal.style.removeProperty('--tr-modal-content-height');}
    };
    document.addEventListener('show.bs.modal',event=>{
        const modal=event.target;if(!modal.closest('.tr-bootstrap'))return;
        modalLayout={modal,width:window.innerWidth,height:window.innerHeight,top:window.visualViewport?.offsetTop||0};
        modal.classList.add('tr-modal-locked');syncViewport();
    });
    document.addEventListener('hidden.bs.modal',event=>{
        if(modalLayout?.modal!==event.target)return;
        const modal=event.target;modal.classList.remove('tr-modal-locked','tr-modal-editing');
        for(const name of ['--tr-viewport-height','--tr-viewport-top','--tr-keyboard-space','--tr-modal-content-height'])modal.style.removeProperty(name);
        modalLayout=null;
    });
    for (const event of ['show.bs.offcanvas','hidden.bs.offcanvas']) document.addEventListener(event,item=>{
        if(item.target.id!=='tr-demo-playback-queue')return;
        document.querySelector('[aria-controls="tr-demo-playback-queue"]')?.setAttribute('aria-expanded',String(event==='show.bs.offcanvas'));
    });
    document.addEventListener('focusin',event=>{
        if(!modalLayout||!modalLayout.modal.contains(event.target)||!isTyping(event.target))return;
        const modal=modalLayout.modal;
        if(!modal.classList.contains('tr-modal-editing')){modal.style.setProperty('--tr-modal-content-height',`${modal.querySelector('.modal-content').getBoundingClientRect().height}px`);modal.classList.add('tr-modal-editing');}
        requestAnimationFrame(syncViewport);
    });
    document.addEventListener('focusout',()=>{if(modalLayout)requestAnimationFrame(syncViewport);});
    syncViewport();
    window.addEventListener('resize',syncViewport);window.addEventListener('pageshow',syncViewport);
    window.visualViewport?.addEventListener('resize',syncViewport);window.visualViewport?.addEventListener('scroll',syncViewport);
    const player=document.querySelector('[data-player]');
    const syncTheme=()=>{
        const theme=document.documentElement.dataset.theme==='dark'?'dark':'light';
        document.documentElement.dataset.bsTheme=theme;
        document.querySelectorAll('[data-tr-ui-cover]').forEach(image=>{const src=image.dataset[theme==='dark'?'trUiCoverDark':'trUiCoverLight'];if(src&&image.getAttribute('src')!==src)image.src=src;});
        document.querySelectorAll('[data-tr-shell]').forEach(root=>root.dataset.bsTheme=theme);
        document.querySelectorAll('[data-tr-theme-icons]').forEach(button=>{
            const label=theme==='dark'?'Включить светлую тему':'Включить тёмную тему';button.setAttribute('aria-label',label);button.title=label;button.setAttribute('aria-pressed',String(theme==='dark'));
            button.querySelector('[data-tr-theme-light]').hidden=theme==='dark';button.querySelector('[data-tr-theme-dark]').hidden=theme!=='dark';
        });
        document.querySelectorAll('meta[name="theme-color"]').forEach(meta=>meta.content=theme==='dark'?'#191c1a':'#f7f5f0');
        try{localStorage.setItem('tape-room:theme',theme);}catch(_){}
    };
    syncTheme();new MutationObserver(syncTheme).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
    document.addEventListener('click',event=>{
        const filePicker=event.target.closest('[data-tr-file-pick]');
        if(filePicker){filePicker.closest('[data-tr-file-picker]').querySelector('input[type=file]').click();return;}
        const demoSort=event.target.closest('[data-tr-demo-sort]');
        if(demoSort){const dropdown=demoSort.closest('.dropdown');dropdown.querySelector('[data-tr-demo-sort-label]').textContent=demoSort.firstElementChild.textContent;dropdown.querySelectorAll('[data-tr-demo-sort]').forEach(option=>{const selected=option===demoSort;option.setAttribute('aria-selected',String(selected));option.querySelector('[data-tr-demo-sort-check]').hidden=!selected;});return;}
        const demoPlay=event.target.closest('[data-tr-demo-play]');
        if(demoPlay){
            const active=demoPlay.getAttribute('aria-pressed')!=='true',queue=demoPlay.closest('.tr-playback-queue--demo');
            if(queue)queue.querySelectorAll('[data-tr-demo-play]').forEach(button=>{
                button.classList.toggle('is-current',button===demoPlay);
                if(button!==demoPlay){button.setAttribute('aria-pressed','false');button.setAttribute('aria-label',`Воспроизвести ${button.querySelector('.tr-queue-info strong').textContent} — пример`);button.querySelector('.tr-icon').className='ci-play-filled tr-icon';}
            });
            demoPlay.setAttribute('aria-pressed',String(active));demoPlay.setAttribute('aria-label',active?'Пауза — пример':'Воспроизвести — пример');const glyph=demoPlay.querySelector('.tr-icon');glyph.classList.toggle('ci-pause',active);glyph.classList.toggle('ci-play-filled',!active);if(glyph.hasAttribute('data-icon'))glyph.dataset.icon=active?'pause':'play';return;
        }
        const demoShuffle=event.target.closest('[data-tr-demo-shuffle]');
        if(demoShuffle){const active=demoShuffle.getAttribute('aria-pressed')!=='true';demoShuffle.setAttribute('aria-pressed',String(active));demoShuffle.classList.toggle('is-active',active);demoShuffle.setAttribute('aria-label',`Случайный порядок: ${active?'включён':'выключен'} — пример`);return;}
        const demoRepeat=event.target.closest('[data-tr-demo-repeat]');
        if(demoRepeat){const mode={off:'all',all:'one',one:'off'}[demoRepeat.dataset.trDemoRepeat];demoRepeat.dataset.trDemoRepeat=mode;demoRepeat.classList.toggle('is-active',mode!=='off');demoRepeat.setAttribute('aria-pressed',String(mode!=='off'));demoRepeat.setAttribute('aria-label',`Повтор: ${mode==='off'?'выключен':mode==='one'?'одного трека':'плейлиста'} — пример`);demoRepeat.parentElement.querySelector('[data-tr-demo-repeat-one]').hidden=mode!=='one';return;}
        const demoFavorite=event.target.closest('[data-tr-demo-favorite]');
        if(demoFavorite){const active=demoFavorite.getAttribute('aria-pressed')!=='true';demoFavorite.setAttribute('aria-pressed',String(active));demoFavorite.setAttribute('aria-label',active?'Убрать из избранного — пример':'Добавить в избранное — пример');demoFavorite.querySelector('.tr-icon').className='ci-bookmark tr-icon';return;}
        const demoCollection=event.target.closest('[data-tr-demo-collection]');
        if(demoCollection){const saved=demoCollection.getAttribute('aria-pressed')!=='true';demoCollection.classList.toggle('is-saved',saved);demoCollection.setAttribute('aria-pressed',String(saved));demoCollection.querySelector('span').textContent=saved?'В коллекции':'В коллекцию';return;}
        const themeToggle=event.target.closest('[data-tr-theme-toggle]');
        if(themeToggle){document.documentElement.dataset.theme=document.documentElement.dataset.theme==='dark'?'light':'dark';return;}
        const visibility=event.target.closest('[data-tr-password]');
        if(visibility){const input=document.getElementById(visibility.dataset.trPassword);if(!input)return;const shown=input.type==='password';input.type=shown?'text':'password';visibility.setAttribute('aria-label',shown?'Скрыть пароль':'Показать пароль');visibility.setAttribute('aria-pressed',String(shown));const icon=visibility.querySelector('.tr-icon');icon.classList.toggle('ci-eye',!shown);icon.classList.toggle('ci-eye-off',shown);return;}
        if(event.target.closest('[data-tr-show-toast]'))bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
    });
    document.addEventListener('change',event=>{
        if(event.target.matches('[data-tr-file-picker] input[type=file]'))event.target.closest('[data-tr-file-picker]').querySelector('[data-tr-file-name]').textContent=event.target.files[0]?.name||'Файл не выбран';
        if(event.target.matches('[data-tr-demo-buffering]')){const demo=document.querySelector('.tr-mini-player--demo');demo.classList.toggle('is-buffering',event.target.checked);demo.querySelector('.rp-play').setAttribute('aria-busy',String(event.target.checked));}
    });
    function mountPage(){
        syncTheme();
        const page=document.querySelector('[data-tr-page]');if(!page || page.hidden || page.dataset.trMounted)return;
        page.dataset.trMounted='1';
        document.querySelectorAll('[data-tr-demo-genres]').forEach(root=>{const genres=JSON.parse(root.dataset.genres),picker=new window.TapeRoomGenrePicker(genres);root.innerHTML=picker.markup(genres.slice(0,2).map(item=>item.id));picker.mount(root);});
        const demoSteps=document.querySelector('[data-tr-demo-drive-steps]');
        if(demoSteps){let step=1;const descriptions=['Выбор папки Google Drive или ссылки.','Песни показываются по порядку, по одной в строке.','Название альбома, исполнитель и несколько жанров перед сохранением.'];const render=()=>{demoSteps.innerHTML=window.TapeRoomDriveUi.steps(step);document.querySelector('[data-tr-demo-drive-description]').textContent=descriptions[step-1];document.querySelector('[data-tr-demo-drive-back]').disabled=step===1;document.querySelector('[data-tr-demo-drive-next]').textContent=step===3?'Сначала':'Далее';};document.querySelector('[data-tr-demo-drive-back]').onclick=()=>{step=Math.max(1,step-1);render();};document.querySelector('[data-tr-demo-drive-next]').onclick=()=>{step=step%3+1;render();};render();}
        document.querySelectorAll('[data-tr-demo-seek]').forEach(range=>range.addEventListener('input',()=>{const progress=Number(range.value)/10,seconds=Math.round(222*progress/100);range.style.setProperty('--fill',`${progress}%`);range.style.setProperty('--buffered',`${Math.max(68,progress)}%`);document.querySelector('[data-tr-demo-elapsed]').textContent=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')}`;}));
        document.querySelectorAll('[data-tr-demo-form]').forEach(form=>form.addEventListener('submit',event=>{
            event.preventDefault();form.classList.add('was-validated');if(form.checkValidity())bootstrap.Toast.getOrCreateInstance(document.getElementById('tr-demo-toast')).show();
        }));
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element=>new bootstrap.Tooltip(element,{container:element.closest('.tr-bootstrap')}));
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(element=>new bootstrap.Popover(element,{container:element.closest('.tr-bootstrap'),trigger:'focus'}));
    }
    mountPage();document.addEventListener('tape-room:page',mountPage);
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
