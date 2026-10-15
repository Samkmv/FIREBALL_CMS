/* Stream browser-owned ranges over MessagePort; no network fallback or cache. */
self.addEventListener('install',event=>event.waitUntil(self.skipWaiting()));
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
self.addEventListener('message',event=>{
    if(event.data?.type==='tape-room-client-id')event.ports[0]?.postMessage(event.source.id);
});
self.addEventListener('fetch',event=>{
    if(!event.request.url.startsWith(self.registration.scope+'webtorrent/'))return;
    event.stopImmediatePropagation();
    event.respondWith(serveTorrent(event.request));
});
async function serveTorrent(request){
    const url=new URL(request.url), path=url.pathname;
    if(path.endsWith('/keepalive/'))return new Response();
    if(path.endsWith('/cancel/'))return new Response(new ReadableStream({cancel(){}}));
    const owner=await self.clients.get(url.searchParams.get('trClient')||'');
    if(!owner || owner.type!=='window')return new Response('Torrent is not active in this tab.',{status:410});
    const channel=new MessageChannel(),port=channel.port1;
    let timer,closed=false;
    const close=()=>{if(closed)return;closed=true;clearTimeout(timer);port.postMessage(false);port.close();};
    const next=()=>new Promise((resolve,reject)=>{
        timer=setTimeout(()=>{close();reject(new Error('Torrent stream timed out'));},30000);
        port.onmessage=event=>{clearTimeout(timer);resolve(event.data);};
        port.onmessageerror=()=>{close();reject(new Error('Torrent stream interrupted'));};
    });
    const response=next();
    owner.postMessage({type:'webtorrent',url:request.url,method:request.method,headers:Object.fromEntries(request.headers),destination:request.destination},[channel.port2]);
    let data;try{data=await response;}catch(_){return new Response('Torrent is unavailable.',{status:503});}
    const headers=new Headers(data.headers);headers.set('Cache-Control','private, no-store');
    if(data.body!=='STREAM'){close();return new Response(data.body||null,{status:data.status,headers});}
    return new Response(new ReadableStream({
        async pull(controller){
            try{const pending=next();port.postMessage(true);const bytes=await pending;if(bytes)controller.enqueue(bytes);else{close();controller.close();}}
            catch(exception){close();controller.error(exception);}
        },
        cancel(){close();}
    }),{status:data.status,headers});
}
