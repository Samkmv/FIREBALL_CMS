(() => {
    'use strict';
    function followState(result) {
        const id=Number(result.target);if(!Number.isSafeInteger(id)||id<1)return;
        for(const form of document.querySelectorAll('[data-tr-follow-user]')){
            if(Number(form.dataset.trFollowUser)!==id)continue;
            const following=Boolean(result.following),button=form.querySelector('button[type="submit"]');
            form.querySelector('[name="following"]').value=following?'0':'1';
            button.classList.toggle('btn-secondary',following);button.classList.toggle('is-following',following);button.classList.toggle('btn-primary',!following);
            button.setAttribute('aria-pressed',String(following));button.setAttribute('aria-label',`${following?'Отписаться от':'Подписаться на'} ${form.dataset.trFollowName}`);
            form.querySelector('[data-tr-follow-label]').textContent=following?'Вы подписаны':'Подписаться';
            const icon=button.querySelector('i');if(icon)icon.className=`ci-${following?'user-check':'user-plus'} tr-icon`;
        }
        if(Number.isSafeInteger(result.person?.followersCount)){
            const total=result.person.followersCount;
            for(const count of document.querySelectorAll('[data-tr-follow-count]'))if(Number(count.dataset.trFollowCount)===id)count.textContent=String(total);
            const n=total%100,label=n>=11&&n<=14?'подписчиков':n%10===1?'подписчик':n%10>=2&&n%10<=4?'подписчика':'подписчиков';
            for(const noun of document.querySelectorAll('[data-tr-follow-noun]'))if(Number(noun.dataset.trFollowNoun)===id)noun.textContent=label;
        }
    }
    document.addEventListener('submit',async event=>{
        const form=event.target;if(!form.matches('[data-tr-social-form]')||event.defaultPrevented)return;
        event.preventDefault();if(form.dataset.trSocialBusy)return;
        const body=new FormData(form),button=form.querySelector('button[type="submit"]'),feedback=form.querySelector('[data-tr-social-feedback]');
        form.dataset.trSocialBusy='1';button.disabled=true;feedback.textContent='';feedback.classList.remove('is-error');
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);
        try{
            const response=await fetch(form.action,{method:'POST',body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'},signal:controller.signal});
            if(!response.headers.get('Content-Type')?.includes('application/json'))throw new Error('Не удалось получить ответ. Обновите страницу и проверьте результат.');
            const result=await response.json();if(!response.ok||!result.status)throw new Error(result.message||'Не удалось сохранить изменения.');
            if(form.hasAttribute('data-tr-follow-user'))followState(result);
            if(form.isConnected&&!form.hasAttribute('data-tr-follow-user'))feedback.textContent='Настройки профиля сохранены.';
        }catch(error){if(form.isConnected){feedback.classList.add('is-error');feedback.textContent=error.name==='AbortError'?'Ответ задерживается. Обновите страницу и проверьте результат.':error.message;}}
        finally{clearTimeout(timeout);button.disabled=false;delete form.dataset.trSocialBusy;}
    });
    function covers(){const dark=document.documentElement.dataset.theme==='dark';for(const image of document.querySelectorAll('[data-tr-social-cover]')){const src=image.dataset[dark?'trSocialCoverDark':'trSocialCoverLight'];if(src&&image.getAttribute('src')!==src)image.src=src;}}
    new MutationObserver(covers).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
    document.addEventListener('tape-room:page',covers);covers();
})();
