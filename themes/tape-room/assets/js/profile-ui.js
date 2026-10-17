(() => {
    'use strict';
    const mounted=new WeakSet(),releases=new Set();
    function mount(){document.querySelectorAll('[data-tr-avatar-editor]').forEach(form=>{
        if(mounted.has(form))return;mounted.add(form);
        const input=form.querySelector('[data-tr-avatar-file]'),preview=form.querySelector('[data-tr-avatar-preview]'),save=form.querySelector('[data-tr-avatar-save]'),error=form.querySelector('[data-tr-avatar-error]');
        const original=preview.src;let url='';
        const release=()=>{if(url)URL.revokeObjectURL(url);url='';};
        input.addEventListener('change',()=>{
            release();error.hidden=true;error.textContent='';save.disabled=true;preview.src=original;
            const file=input.files[0];if(!file)return;
            if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type)||file.size>Number(form.dataset.maxBytes)){
                error.textContent='Выберите JPG, PNG, WebP или GIF в пределах указанного размера.';error.hidden=false;input.value='';form.querySelector('[data-tr-file-name]').textContent='Файл не выбран';return;
            }
            url=URL.createObjectURL(file);preview.src=url;save.disabled=false;
        });
        preview.addEventListener('error',()=>{
            if(!url)return;release();preview.src=original;save.disabled=true;input.value='';error.textContent='Не удалось открыть изображение. Выберите другой файл.';error.hidden=false;
        });
        releases.add(release);
    });}
    const releaseAll=()=>{releases.forEach(release=>release());releases.clear();};
    window.addEventListener('pagehide',releaseAll);document.addEventListener('tape-room:page-leave',releaseAll);
    mount();document.addEventListener('tape-room:page',mount);
})();
