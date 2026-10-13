(function(root){
    'use strict';
    const host=root.document.querySelector('[data-tape-room-embed]');if(!host || !root.TapeRoomPlaybackCore || !root.TapeRoomCompact)return;
    let config;try{config=JSON.parse(host.querySelector('[data-embed-config]').textContent);}catch(_){return;}
    const scheme=root.matchMedia('(prefers-color-scheme: dark)');const theme=()=>{root.document.documentElement.dataset.theme=config.theme==='system'?(scheme.matches?'dark':'light'):config.theme;core?.publish();};
    const core=new root.TapeRoomPlaybackCore({audio:host.querySelector('audio'),config,tracks:config.tracks});
    const compact=new root.TapeRoomCompact({core,host,config});compact.show(true);core.setQueue(config.tracks);if(config.tracks[0])core.load(config.tracks[0].id);theme();scheme.addEventListener('change',theme);
    root.addEventListener('pagehide',()=>core.pause());
})(window);
