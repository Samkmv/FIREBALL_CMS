(() => {
    'use strict';
    let nodes=[];
    const reduced=matchMedia('(prefers-reduced-motion: reduce)');
    const values=new WeakMap();
    let frame=0;
    function measure() {
        frame=0;
        for (const node of nodes) {
            let text=node.querySelector('[data-tr-marquee-text]');
            if (!text) {
                text=document.createElement('span'); text.className='tr-marquee-text'; text.dataset.trMarqueeText='';
                text.textContent=node.textContent; node.replaceChildren(text);
            }
            node.classList.add('tr-artist-marquee');
            const value=text.textContent;
            const changed=values.get(node)!==value;
            values.set(node,value);
            if (node.title!==value) node.title=value;
            const distance=Math.ceil(text.getBoundingClientRect().width-node.clientWidth);
            const moving=node.clientWidth>0 && distance>2 && !reduced.matches;
            node.classList.toggle('is-scrolling',moving);
            if (moving) {
                const offset=`-${distance+2}px`, duration=`${Math.max(8,2*distance/26+4).toFixed(2)}s`;
                if (node.style.getPropertyValue('--tr-marquee-offset')!==offset) node.style.setProperty('--tr-marquee-offset',offset);
                if (node.style.getPropertyValue('--tr-marquee-duration')!==duration) node.style.setProperty('--tr-marquee-duration',duration);
                if (changed) text.getAnimations().forEach(animation=>{if(animation.animationName==='tr-artist-travel')animation.currentTime=0;});
            }
        }
    }
    const schedule=()=>{if(!frame)frame=requestAnimationFrame(measure);};
    const mutation=new MutationObserver(schedule);
    const resize='ResizeObserver' in window ? new ResizeObserver(schedule) : null;
    function mount(){
        for(const node of nodes)if(!node.isConnected)resize?.unobserve(node);
        mutation.disconnect();
        nodes=[...document.querySelectorAll('.rp-now-playing [data-artist],[data-mini-artist],[data-tr-artist-marquee]')];
        nodes.forEach(node=>{mutation.observe(node,{childList:true,characterData:true,subtree:true});resize?.observe(node);});schedule();
    }
    mount();document.addEventListener('tape-room:page',mount);
    window.addEventListener('resize',schedule);
    reduced.addEventListener('change',schedule);
    document.fonts?.ready.then(schedule);
    document.fonts?.addEventListener('loadingdone',schedule);
    schedule();
})();
