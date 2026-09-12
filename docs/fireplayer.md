# FirePlayer 1.0.3

FirePlayer is the native FIREBALL CMS media component for video, audio, HLS VOD, and HLS LIVE. New content uses `.fire-player` or `[data-fire-player]`; legacy video/audio inside post content is upgraded automatically while chat and background media remain on their own paths.

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

Video controls include a styled settings button with playback-speed choices and zoom (1–3×), plus mouse-drag panning. Loading and reconnect messages use a compact badge at the top left of video; audio messages occupy their own row above the controls. Neither covers the play button. LIVE appears only next to the timeline, in a fixed-width button; its tooltip and accessible label explain how to return to the live edge. In narrow player containers, the timeline gets a full-width row above the buttons.

On a desktop, the video panel appears while the pointer is over the player and disappears when it leaves, including after clicking a control. Keyboard focus keeps the controls accessible. Touch reveals the panel temporarily during playback; an open settings menu stays visible. Audio controls are always visible.

One-finger vertical swipes over video scroll the page, including when video zoom is enabled. Pinch gestures use native page zoom; video zoom remains available in settings. Stationary side double-taps seek video, while a scroll or cancelled gesture does not seek.

The volume popup clears the speaker button with a hover-safe gap and shares the settings menu surface. Settings only scroll when space is limited, using a thin themed scrollbar.

Only explicit volume/mute choices are saved as sound preferences. Muted camera autoplay does not silence subsequent players. Legacy mute values that could have been saved by autoplay are ignored; intentional mute choices saved by this version are respected. An explicit `muted: true` still takes precedence, and browsers may block autoplay with sound.

## Creator diagnostics

`fireplayer-diagnostics.js` adds a collapsible technical panel below the player only when the server-provided `window.canViewVideoDiagnostics` is exactly `true`. Both CMS layouts derive this flag from `can_view_video_diagnostics()` (the Creator role); there is no per-block override. Guests and other roles get no diagnostics DOM.

The panel shows source host, media/protocol/mode, engine/version, resolution, position/duration, buffer ahead, available HLS bitrate/codecs, dropped/total frames, reconnect count and the last error name/code. Unsupported browser metrics are marked with a dash. Values refresh once per second while expanded and the tab is visible. It makes no extra network requests and displays no URL credentials, query parameters, fragments or error stacks. Counters reset on a different source; unload hides the panel and destroy removes it and its listeners/timer.

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
await player.retry();
await player.load('/media/song.mp3');
player.destroy();

player.on('error', ({ error }) => console.error(error));
player.on('reconnect', ({ reason }) => console.log(reason));
```

`ready` means the source/adapter is attached, not that playback has started. `play()` may be called during preparation and waits for it; a later source change or unload cancels that intent. Handle rejected API promises when calling them from application code. Browser autoplay denial emits `autoplayblocked` without presenting a broken-source error.

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
- `reconnect`, `reconnectDelay`, `stallTimeout`, `startupTimeout` (30 seconds by default), `maxReconnectAttempts` (4), `liveEdgeTolerance`.
- `rememberVolume`, `keyboard`, `gestures`.
- `rememberPosition`: `'auto'` by default (video resumes, audio starts at zero). Set `true` to resume audio as well, for example for audiobooks, or `false` to disable position persistence.
- `streamId` identifies a managed camera; `/stream-ID/index.m3u8` URLs are recognized automatically. Managed cameras always require `/api/streams/wake` to return `ready: true` before attachment, including reconnect. `lazyStart` remains accepted for legacy markup compatibility.

Camera Manager uses a single lazy modal player. Closing the modal unloads the HLS engine, so hidden cameras do not continue downloading segments.

## Compatibility with the previous Plyr integration

- Native Safari/iOS HLS and bundled hls.js paths are retained, with backend readiness checks before camera playback.
- Reconnect, decoder recovery, LIVE stall detection, poster refresh, fullscreen/PiP, volume, playback speed, zoom/pan and keyboard/touch interaction are retained. Recovery is bounded and never overrides an explicit user pause.
- Existing post/page video and audio are upgraded, preserving playback attributes, posters, aspect ratios and text tracks. New blocks and the editor canvas use FirePlayer. Removed players are disposed; moving an existing DOM node does not destroy it.
- YouTube/Vimeo remain provider embeds, not direct media URLs. The isolated document-preview iframe deliberately blocks scripts; it uses native media controls and a source link instead of an empty FirePlayer mount. HLS playback there depends on native browser support. This does not change the editor canvas or published player.
- Chat/background media and remaining legacy provider integrations are outside this replacement. Plyr is still loaded for compatibility, but must not initialize FirePlayer media a second time. This is not a claim of complete parity with every optional Plyr feature.

## Verification

```bash
php tests/fireplayer_regression.php
php tests/hls_player_regression.php
php tests/editor2_unit.php
node tests/fireplayer_browser.js
```

The browser suite requires Node.js, Playwright and its Chromium binary. When dependencies are installed outside the repository, configure `NODE_PATH` and `PLAYWRIGHT_BROWSERS_PATH`. Missing dependencies or a missing browser fail the suite rather than reporting success. Playwright 1.48.2/Chromium is used on the macOS 13 test host.

The suite contains 17 check groups. It generates local WebM/WAV media for real playback checks, and uses deterministic media/HLS doubles for startup races, failed readiness, retry and decoder errors. It also checks HTTP source detection, the bundled hls.js script loader after a failed request, desktop/touch controls, audio session ownership, creator diagnostics permissions/cleanup, and editor/public/preview rendering. Browser assertions cover the volume popup gap, pointer transition, unnecessary desktop scrolling, narrow-screen diagnostics, compact LIVE controls, separate status messages, page scrolling at video zoom 1×/2×, audio starting at zero, sound preferences, and audio timeline styling in light/dark themes with the CMS stylesheets loaded.

This does not certify Safari/iPhone, actual camera connectivity, server-side stream readiness, or HLS decoding against a real segment feed; those require a deployment/device smoke test.
