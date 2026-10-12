'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const previousLocation = global.location;
global.location = {href:'https://maxipapa.ru/cms/admin/reel-player',origin:'https://maxipapa.ru'};
const images = require('../plugins/reel-player/assets/images.js');
let checks = 0;
const check = (value,message) => { assert.ok(value,message); checks++; };
const equal = (actual,expected,message) => { assert.deepEqual(actual,expected,message); checks++; };
const config = {
    defaultCover:'/plugins/reel-player/assets/cover-light.png?v=source-cover-light',
    defaultCovers:{light:'/plugins/reel-player/assets/cover-light.png?v=source-cover-light',dark:'/plugins/reel-player/assets/cover-dark.png?v=source-cover-dark'},
    defaultArtists:{light:'/plugins/reel-player/assets/artist-light.png?v=source-artist-light',dark:'/plugins/reel-player/assets/artist-dark.png?v=source-artist-dark'},
    defaultCoverVariants:{},defaultArtistVariants:{}
};
for (const [kind,variants] of [['cover',config.defaultCoverVariants],['artist',config.defaultArtistVariants]]) {
    for (const theme of ['light','dark']) {
        variants[theme] = Object.fromEntries([96,320,640].map(size => [size,`/plugins/reel-player/assets/${kind}-${theme}-${size}.webp?v=${kind}-${theme}-${size}`]));
    }
}

class ImageNode {
    constructor(kind = 'cover') {
        this.attributes = new Map([['data-library-image',kind]]);
        this.dataset = {libraryImage:kind}; this.operations = []; this.hidden = false;
        this.placeholder = {hidden:true};
        this.parentElement = {querySelector:() => this.placeholder};
        this.classList = {contains:() => false};
    }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    hasAttribute(name) { return this.attributes.has(name); }
    setAttribute(name,value) { this.operations.push(['set',name,String(value)]); this.attributes.set(name,String(value)); }
    removeAttribute(name) { if (this.attributes.has(name)) this.operations.push(['remove',name]); this.attributes.delete(name); }
    get src() { return this.getAttribute('src') || ''; }
    set src(value) { this.setAttribute('src',value); }
    matches(selectors) { return selectors.split(',').some(selector => /^\[([^\]]+)\]$/.test(selector.trim()) && this.hasAttribute(selector.trim().slice(1,-1))); }
}

try {
    for (const prefix of ['', '/cms', 'https://maxipapa.ru/cms']) {
        for (const endpoint of ['cover','catalog/cover','catalog/album-cover','catalog/artist-image']) {
            const source = `${prefix}/admin/reel-player/${endpoint}/42?v=abcdef&token=a%2Bb#art`;
            const output = images.thumbnail(source,320);
            const parsed = new URL(output,global.location.href);
            check(parsed.pathname.endsWith(`/admin/reel-player/${endpoint}/42`),'Known private image paths keep their endpoint and CMS subdirectory');
            equal(parsed.searchParams.get('v'),'abcdef','The source replacement version remains intact');
            equal(parsed.searchParams.get('token'),'a+b','Other existing query values remain intact');
            equal(parsed.searchParams.get('size'),'320','Only a bounded display size is appended');
            equal(parsed.hash,'#art','Image fragments are preserved');
        }
    }
    const sized = images.thumbnail('/admin/reel-player/cover/42?v=new&size=96',640);
    equal(new URL(sized,global.location.href).searchParams.getAll('size'),['640'],'An existing size parameter is replaced rather than duplicated');
    const complex = images.thumbnail('/admin/reel-player/cover/42?v=abc?def&token=a%26b#one#two',96);
    const complexUrl = new URL(complex,global.location.href);
    equal(complexUrl.searchParams.get('v'),'abc?def','A valid literal question mark inside an existing query value is not truncated');
    equal(complexUrl.searchParams.get('token'),'a&b','Encoded query separators survive thumbnail URL construction');
    equal(complexUrl.hash,'#one#two','Multiple hash characters survive thumbnail URL construction');
    for (const source of [
        '/admin/reel-player/media/42','/admin/reel-player/catalog/media/42',
        '/plugins/reel-player/assets/cover-light.png?v=original',
        '/admin/reel-player/cover/42/more','/admin/reel-player/cover/not-a-number',
        'https://external.example/album.jpg?v=unchanged',
        'https://external.example/admin/reel-player/cover/42?v=unchanged',
        '//external.example/admin/reel-player/catalog/artist-image/42?v=unchanged',
        'blob:https://maxipapa.ru/4bff','data:image/png;base64,abcd',''
    ]) equal(images.thumbnail(source,320),source,'Unknown, remote, blob, media and static URLs are left untouched');
    for (const size of [0,-1,1,95,321,10000,Infinity,NaN,'320',null]) equal(images.thumbnail('/admin/reel-player/cover/42?v=1',size),'/admin/reel-player/cover/42?v=1','Unsupported sizes cannot cause arbitrary image rendering');
    equal(images.thumbnail(null,320),'','A missing source remains empty');

    for (const kind of ['cover','artist']) {
        const variants = kind === 'artist' ? config.defaultArtistVariants : config.defaultCoverVariants;
        for (const theme of ['light','dark']) {
            equal(images.sources('',config,theme,kind),{src:variants[theme][320],srcset:`${variants[theme][320]} 1x, ${variants[theme][640]} 2x`},'Card and player placeholders use the active theme display-size artwork');
            equal(images.sources('',config,theme,kind,true),{src:variants[theme][96],srcset:`${variants[theme][96]} 1x, ${variants[theme][320]} 2x`},'Compact placeholders use the smallest suitable sources');
            const source = '/cms/admin/reel-player/catalog/artist-image/42?v=photo-2';
            const value = images.sources(source,config,theme,kind);
            equal(value.src,images.thumbnail(source,320),'Uploaded photo source remains independent of theme defaults');
            check(value.srcset.includes('size=640') && value.srcset.includes('v=photo-2') && !value.srcset.includes('/assets/'),'Uploaded photo retains its replacement version in both density candidates');
        }
    }
    equal(images.sources('',{},'dark','artist'),{src:'',srcset:''},'Absent owner-supplied artist assets produce an empty source for the neutral icon');
    equal(images.sources('',{defaultCover:'/legacy.png'},'dark'),{src:'/legacy.png',srcset:''},'Older configuration without display variants retains its original cover');
    equal(images.sources('blob:photo-preview',config,'dark','artist'),{src:'blob:photo-preview',srcset:''},'New upload previews do not carry stale thumbnail candidates');
    equal(images.sources('https://external.example/photo.jpg',config,'dark','artist'),{src:'https://external.example/photo.jpg',srcset:''},'Unknown remote sources remain intact without invented srcset URLs');

    const image = new ImageNode('artist');
    images.apply(image,'/admin/reel-player/catalog/artist-image/42?v=photo-2',config,'light','artist');
    equal(image.operations.map(operation => operation[1]),['srcset','src'],'Responsive candidates are assigned before src to avoid an intermediate original request');
    const originalOperations = image.operations.length;
    images.apply(image,'/admin/reel-player/catalog/artist-image/42?v=photo-2',config,'dark','artist');
    equal(image.operations.length,originalOperations,'Repeated rendering and theme switching do not rewrite unchanged uploaded image sources');
    images.apply(image,'blob:replacement-preview',config,'dark','artist');
    equal(image.operations.slice(-2).map(operation => operation[1]),['srcset','src'],'Changing to a local upload preview removes old responsive candidates before setting the blob source');
    check(!image.hasAttribute('srcset'),'A local upload preview cannot load an old cover through stale srcset');
    images.apply(image,'',config,'light','artist',true);
    const light = image.src;
    images.apply(image,'',config,'dark','artist',true);
    check(image.src !== light && image.src.includes('artist-dark-96.webp') && !image.getAttribute('srcset').includes('artist-light'),'Theme change updates compact artist placeholders and every candidate together');

    const context = {window:{},Map,Set,Date,Number,String,Boolean};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../plugins/reel-player/assets/catalog.js'),'utf8'),context);
    const ui = Object.create(context.window.TapeRoomCatalogUI.prototype);
    const escape = value => String(value).replace(/[&<>"']/g,char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    ui.escape = escape; ui.icon = name => `<i data-icon="${name}"></i>`;
    ui.imageSources = (source,kind,compact) => images.sources(source,config,'dark',kind,compact);
    const card = ui.image('','', 'artist');
    check(card.includes('artist-dark-320.webp') && card.includes('data-default-artist') && card.includes('data-library-image="artist"'),'Actual artist card renderer uses independently themed microphone artwork');
    check(card.indexOf('srcset=') < card.indexOf(' src='),'Generated card markup lists responsive candidates before its fallback source');
    check(card.includes('loading="lazy"') && card.includes('width="240" height="240"'),'Grid artwork is lazy and reserves dimensions to avoid layout shifts');
    check(ui.image('','', 'artist',true).includes('loading="eager"'),'Artist detail artwork is available immediately');
    check(ui.image('/admin/reel-player/catalog/cover/2?v=2','rp-catalog-song-cover').includes('size=96') && ui.image('/admin/reel-player/catalog/cover/2?v=2','rp-catalog-song-cover').includes('data-compact-image'),'Small catalogue song rows use compact candidates');
    check(!ui.image('/admin/reel-player/catalog/artist-image/2?v=2','','artist').includes('data-default-artist'),'A real artist photo never becomes a theme placeholder');
    const hostile = ui.image('https://external.example/a.png?x=" onerror="alert(1)');
    check(hostile.includes('&quot;') && !hostile.includes(' onerror="'),'Image sources remain escaped in generated markup');
    ui.imageSources = () => ({src:'',srcset:''});
    check(ui.image('','','artist').includes('data-icon="microphone"') && !ui.image('','','artist').includes('<img'),'Missing prepared assets show a neutral icon without a broken image element');

    // Execute the real player's delegated image-error listener with a small DOM
    // double. This catches a repeated failed src that otherwise leaves iOS with
    // a broken image rather than advancing to the original artwork or icon.
    const player = fs.readFileSync(path.join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
    const start = player.indexOf("app.addEventListener('error',event=>{");
    const end = player.indexOf('    let dragDepth',start);
    check(start >= 0 && end > start,'The real player image fallback listener is found');
    let handler;
    const errorContext = {
        app:{addEventListener:(name,callback) => { handler = callback; }},window:{TapeRoomImages:images},config,theme:'dark',
        defaultArtist:() => config.defaultArtists[errorContext.theme],defaultCover:() => config.defaultCovers[errorContext.theme]
    };
    vm.runInNewContext(player.slice(start,end),errorContext);
    const missingPhoto = new ImageNode('artist');
    images.apply(missingPhoto,'/admin/reel-player/catalog/artist-image/404?v=removed',config,'dark','artist');
    handler({target:missingPhoto});
    check(missingPhoto.src.includes('artist-dark-') && missingPhoto.hasAttribute('data-default-artist'),'Unavailable artist photo becomes a theme-aware owner-provided placeholder');
    const firstFallback = missingPhoto.src;
    handler({target:missingPhoto});
    check(missingPhoto.src !== firstFallback && missingPhoto.src === config.defaultArtists.dark && !missingPhoto.hasAttribute('srcset'),'A failed artist WebP advances to the untouched original PNG rather than retrying the same broken candidate');
    handler({target:missingPhoto});
    check(missingPhoto.hidden && !missingPhoto.placeholder.hidden,'If original artwork also fails, the actual player hides the broken image and shows the neutral mic');
    const missingDefault = new ImageNode('artist'); missingDefault.setAttribute('data-default-artist','');
    images.apply(missingDefault,'',config,'light','artist'); errorContext.theme = 'light';
    handler({target:missingDefault});
    check(missingDefault.src === config.defaultArtists.light && !missingDefault.hasAttribute('srcset'),'A failure starting from a default artist placeholder immediately moves to a different original image URL');

    process.stdout.write(`Tape Room image UI: ${checks} checks passed.\n`);
} finally {
    if (previousLocation === undefined) delete global.location;
    else global.location = previousLocation;
}
