(() => {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    class TapeRoomGenrePicker {
        constructor(genres) { this.genres=genres.map(item=>({...item,id:Number(item.id)})); this.selected=0; }
        markup(id) {
            this.selected=Number(id)||0;
            const genre=this.genres.find(item=>item.id===this.selected);
            return `<div class="rp-field rp-genre-picker"><label for="rp-genre-query">Жанр</label><input id="rp-genre-query" type="search" data-genre-query value="${escape(genre?.name || '')}" placeholder="Найти и выбрать жанр" autocomplete="off" aria-controls="rp-genre-options" aria-expanded="false"><div id="rp-genre-options" data-genre-options class="rp-genre-options" hidden></div><small>Выберите жанр из библиотеки или оставьте поле пустым.</small></div>`;
        }
        mount(dialog) {
            this.input=dialog.querySelector('[data-genre-query]'); this.options=dialog.querySelector('[data-genre-options]');
            this.input.addEventListener('input',()=>{this.selected=0;this.render();});
            this.input.addEventListener('focus',()=>this.render());
            this.input.addEventListener('keydown',event=>{
                if(event.key==='ArrowDown'){event.preventDefault();this.render();this.options.querySelector('button')?.focus();}
                if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.close();}
            });
            this.options.addEventListener('click',event=>{const button=event.target.closest('[data-genre-id]');if(button)this.select(Number(button.dataset.genreId));});
            this.options.addEventListener('keydown',event=>{
                const buttons=[...this.options.querySelectorAll('button')],index=buttons.indexOf(event.target);
                if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();buttons[event.key==='Home'?0:event.key==='End'?buttons.length-1:(index+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();}
                if(event.key==='Escape'){event.preventDefault();event.stopPropagation();this.close();this.input.focus();}
            });
            const picker=dialog.querySelector('.rp-genre-picker');
            // Touch browsers may blur the search field before delivering the
            // option click, with no relatedTarget. Keep that option available.
            picker.addEventListener('focusout',event=>{if(event.relatedTarget && !picker.contains(event.relatedTarget))this.close();});
            dialog.addEventListener('pointerdown',event=>{if(!picker.contains(event.target))this.close();});
        }
        render() {
            const query=this.selected ? '' : this.input.value.trim().toLocaleLowerCase(),list=this.genres.filter(item=>item.name.toLocaleLowerCase().includes(query));
            this.options.innerHTML=list.length ? list.map(item=>`<button type="button" data-genre-id="${item.id}">${escape(item.name)}</button>`).join('') : '<p>Жанр не найден. Измените запрос или оставьте поле пустым.</p>';
            this.options.hidden=false;this.input.setAttribute('aria-expanded','true');
        }
        close(){this.options.hidden=true;this.input.setAttribute('aria-expanded','false');}
        select(id){const genre=this.genres.find(item=>item.id===id);this.selected=genre?.id || 0;this.input.value=genre?.name || '';this.close();}
        value(){const name=this.input.value.trim();if(!name)return 0;if(!this.selected){const genre=this.genres.find(item=>item.name.toLocaleLowerCase()===name.toLocaleLowerCase());if(!genre)throw new Error('Выберите жанр из результатов поиска.');this.select(genre.id);}return this.selected;}
        match(name) {
            const aliases={rock:'Рок',pop:'Поп',jazz:'Джаз',blues:'Блюз',classical:'Классическая музыка',electronic:'Электронная музыка',ambient:'Эмбиент',metal:'Метал',folk:'Фолк',country:'Кантри','hip-hop':'Хип-хоп',soul:'Соул',funk:'Фанк'};
            const normalized=String(name||'').trim().toLocaleLowerCase(),target=(aliases[normalized] || name || '').toLocaleLowerCase();
            const genre=this.genres.find(item=>item.name.toLocaleLowerCase()===target);if(genre)this.select(genre.id);
        }
    }
    window.TapeRoomGenrePicker=TapeRoomGenrePicker;
})();
