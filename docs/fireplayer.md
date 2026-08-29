# FirePlayer 1.0

FirePlayer is the native FIREBALL CMS media component for video, audio, HLS VOD, and HLS LIVE. Existing Plyr content remains supported; only `.fire-player` or `[data-fire-player]` elements opt into FirePlayer.

## Declarative use

```html
<div
    class="fire-player"
    data-src="https://media.example.test/stream-camera/index.m3u8"
    data-poster="https://media.example.test/tn-camera.jpg"
    data-autoplay="false">
</div>
```

FirePlayer recognizes file extensions, `Content-Type`, and HLS manifest markers. `#EXT-X-ENDLIST` selects VOD; a rolling playlist selects LIVE. Safari/iOS use native HLS when available, while other supported browsers load the bundled hls.js engine.

## JavaScript API

```js
const player = new FirePlayer('#player', {
    src: '/media/movie.mp4',
    poster: '/media/movie.jpg'
});

await player.ready;
await player.play();
player.pause();
player.mute();
player.goLive();
player.fullscreen();
player.pictureInPicture();
await player.load('/media/song.mp3');
player.destroy();

player.on('error', ({ error }) => console.error(error));
player.on('reconnect', ({ reason }) => console.log(reason));
```

Source inspection is also public:

```js
const info = await FirePlayer.detect('/camera/index.m3u8');
// { media: 'video', protocol: 'hls', mode: 'live', ... }
```

## Player options

- `media`: `auto`, `video`, or `audio`.
- `protocol`: `auto`, `file`, `hls`, or `dash` (DASH is recognized; playback needs a registered adapter or native browser support).
- `mode`: `auto`, `vod`, `live`, or `event`.
- `autoplay`, `muted`, `loop`, `poster`, `preload`.
- `reconnect`, `reconnectDelay`, `stallTimeout`, `liveEdgeTolerance`.
- `rememberVolume`, `rememberPosition`, `keyboard`, `gestures`.
- `lazyStart` and `streamId` preserve the CMS `/api/streams/wake` flow for on-demand camera streams.

Camera Manager uses a single lazy modal player. Closing the modal unloads the HLS engine, so hidden cameras do not continue downloading segments.

## Verification

```bash
php tests/fireplayer_regression.php
node tests/fireplayer_browser.js
```
