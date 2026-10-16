(() => {
    'use strict';
    const steps=current=>`<ol class="tr-import-steps" aria-label="Добавление музыки">${['Источник','Песни','Оформление'].map((label,index)=>`<li class="${index+1===current?'is-current':index+1<current?'is-complete':''}" ${index+1===current?'aria-current="step"':''}><span>${index+1}</span><strong>${label}</strong></li>`).join('')}</ol>`;
    window.TapeRoomDriveUi={steps,sort:files=>[...files].sort((a,b)=>Number(b.mimeType==='application/vnd.google-apps.folder')-Number(a.mimeType==='application/vnd.google-apps.folder')||a.name.localeCompare(b.name,'ru',{numeric:true,sensitivity:'base'}))};
})();
