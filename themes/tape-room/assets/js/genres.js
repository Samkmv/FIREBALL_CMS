(() => {
    'use strict';
    const escape=value=>String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let sequence=0;
    class TapeRoomGenrePicker {
        constructor(genres){this.genres=genres.map(item=>({...item,id:Number(item.id)}));this.selected=[];this.id=`tr-genres-${++sequence}`;}
        markup(ids=[]){
            this.selected=[...new Set((Array.isArray(ids)?ids:[ids]).map(Number))].filter(id=>this.genres.some(item=>item.id===id));
            return `<div class="rp-field rp-genre-picker"><label for="${this.id}">Жанры</label><div class="rp-genre-chips" data-genre-chips aria-live="polite"></div><input class="form-control" id="${this.id}" type="search" data-genre-query placeholder="Найти и добавить жанр" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="${this.id}-options" aria-expanded="false"><div id="${this.id}-options" data-genre-options class="rp-genre-options" role="listbox" aria-label="Библиотека жанров" aria-multiselectable="true" hidden></div><small>Можно выбрать несколько жанров. Крестик убирает выбранную метку.</small></div>`;
        }
        mount(root){
            this.picker=root.querySelector('.rp-genre-picker');this.input=this.picker.querySelector('[data-genre-query]');this.options=this.picker.querySelector('[data-genre-options]');this.chips=this.picker.querySelector('[data-genre-chips]');
            this.renderChips();
            this.input.addEventListener('input',()=>this.render());this.input.addEventListener('focus',()=>this.render());
            this.input.addEventListener('keydown',event=>{
                if(event.key==='ArrowDown'){event.preventDefault();this.render();this.options.querySelector('button')?.focus();}
                if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.close();}
                if(event.key==='Enter'){event.preventDefault();const exact=this.genres.find(item=>item.name.toLocaleLowerCase()===this.input.value.trim().toLocaleLowerCase());if(exact)this.select(exact.id);}
                if(event.key==='Backspace' && !this.input.value && this.selected.length){this.remove(this.selected.at(-1));}
            });
            this.options.addEventListener('pointerdown',event=>{if(event.target.closest('[data-genre-id]'))event.preventDefault();});
            this.options.addEventListener('click',event=>{const button=event.target.closest('[data-genre-id]');if(button){event.preventDefault();event.stopPropagation();this.select(Number(button.dataset.genreId));}});
            this.chips.addEventListener('click',event=>{const button=event.target.closest('[data-genre-remove]');if(button){event.preventDefault();this.remove(Number(button.dataset.genreRemove));this.input.focus({preventScroll:true});}});
            this.options.addEventListener('keydown',event=>{
                const buttons=[...this.options.querySelectorAll('button')],index=buttons.indexOf(event.target);
                if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();buttons[event.key==='Home'?0:event.key==='End'?buttons.length-1:(index+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();}
                if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.input.focus({preventScroll:true});this.close();}
            });
            this.picker.addEventListener('focusout',event=>{if(event.relatedTarget && !this.picker.contains(event.relatedTarget))this.close();});
            root.addEventListener('pointerdown',event=>{if(!this.picker.contains(event.target))this.close();});
        }
        renderChips(){if(this.chips)this.chips.innerHTML=this.selected.map(id=>{const genre=this.genres.find(item=>item.id===id);return `<span class="rp-genre-chip badge rounded-pill"><span>${escape(genre.name)}</span><button class="btn rp-genre-remove" type="button" data-genre-remove="${id}" aria-label="Убрать жанр ${escape(genre.name)}"><i class="ci-close tr-icon" aria-hidden="true"></i></button></span>`;}).join('');}
        render(){
            const query=this.input.value.trim().toLocaleLowerCase(),list=this.genres.filter(item=>!this.selected.includes(item.id)&&item.name.toLocaleLowerCase().includes(query));
            this.options.innerHTML=list.length?list.map(item=>`<button class="dropdown-item" type="button" role="option" aria-selected="false" data-genre-id="${item.id}">${escape(item.name)}</button>`).join(''):'<p>Других жанров не найдено.</p>';
            this.options.hidden=false;this.input.setAttribute('aria-expanded','true');
        }
        close(){if(this.options)this.options.hidden=true;this.input?.setAttribute('aria-expanded','false');}
        select(id){if(!this.genres.some(item=>item.id===id)||this.selected.includes(id))return;if(this.selected.length>=50)throw new Error('Можно выбрать до 50 жанров.');this.selected.push(id);if(this.input)this.input.value='';this.renderChips();this.close();}
        remove(id){this.selected=this.selected.filter(value=>value!==id);this.renderChips();if(this.input && !this.options.hidden)this.render();}
        values(){if(this.input?.value.trim()){const genre=this.genres.find(item=>item.name.toLocaleLowerCase()===this.input.value.trim().toLocaleLowerCase());if(!genre)throw new Error('Выберите жанр из результатов поиска или очистите запрос.');this.select(genre.id);}return [...this.selected];}
        value(){return this.values()[0]||0;}
        match(name){const aliases={rock:'Рок',pop:'Поп',jazz:'Джаз',blues:'Блюз',classical:'Классическая музыка',electronic:'Электронная музыка',ambient:'Эмбиент',metal:'Метал',folk:'Фолк',country:'Кантри','hip-hop':'Хип-хоп',soul:'Соул',funk:'Фанк'};for(const part of String(name||'').split(/[;,]/)){const normalized=part.trim().toLocaleLowerCase(),target=(aliases[normalized]||normalized).toLocaleLowerCase(),genre=this.genres.find(item=>item.name.toLocaleLowerCase()===target);if(genre)this.select(genre.id);}}
    }
    window.TapeRoomGenrePicker=TapeRoomGenrePicker;
})();
