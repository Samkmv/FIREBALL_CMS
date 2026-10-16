(() => {
    'use strict';
    const app=document.querySelector('[data-player]');if(!app)return;
    function decorate(root){
        const nodes=selector=>[...(root.matches?.(selector)?[root]:[]),...root.querySelectorAll(selector)];
        for(const button of nodes('button')){
            if(button.closest('[data-player-shell]') || button.matches('.btn-close,.dropdown-item'))continue;
            button.classList.add('btn');
            if(button.matches('.btn-primary,.btn-secondary,.btn-outline-secondary,.btn-danger,.btn-outline-danger,.btn-link'))continue;
            if(button.matches('.rp-button--primary,[data-action="upload"],[data-action="auth"]'))button.classList.add('btn-primary');
            else if(button.matches('.rp-button,.rp-upload-button,.rp-catalog-save'))button.classList.add('btn-outline-secondary');
        }
        for(const field of nodes('input,textarea,select')){
            if(field.type==='hidden' || field.closest('[data-player-shell]'))continue;
            if(field.type==='checkbox' || field.type==='radio')field.classList.add('form-check-input');
            else if(field.tagName==='SELECT')field.classList.add('form-select');
            else if(field.type==='range'){if(field.closest('[data-dialog]'))field.classList.add('form-range');}
            else if(field.type!=='file')field.classList.add('form-control');
        }
        for(const label of nodes('.rp-field'))label.classList.add('form-label');
        for(const search of nodes('.rp-search'))search.classList.add('input-group');
    }
    decorate(app);
    new MutationObserver(changes=>{
        for(const change of changes)for(const node of change.addedNodes)if(node.nodeType===1)decorate(node);
    }).observe(app,{childList:true,subtree:true});
    window.TapeRoomUi={decorate};
})();
