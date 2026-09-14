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

## Poster resolution and adaptive native HLS (September 2026)

The player now resolves a preview before source detection or wake. Priority is wrapper `data-poster` (or an explicit programmatic poster), video `poster`, video `data-poster`, stream metadata, then the established Camera Manager path `/stream-ID/index.m3u8` → sibling `/tn-ID.jpg`. No media domain is hardcoded. Other URL structures without metadata remain posterless. Invalid URLs and failed images leave an empty preview rather than a broken-image indicator.

The reusable `FirePlayer.inferStreamId(source)` is also used by the wake adapter. `FirePlayer.resolvePoster(source, options, media)` supports metadata keyed by source URL or stream ID in `firePlayerConfig.streams` or `hlsStreamConfig.streams`. Metadata fields can be `poster`, `posterUrl` or `poster_url`. An optional `posterUrlTemplate` containing `{id}` overrides the default path convention. These are public image URLs only; do not put API secrets into frontend configuration.

```js
// Extend the existing config; preserve hlsScriptUrl and other supplied settings.
window.firePlayerConfig.streams = {
  '123': { poster: 'https://media.example.test/thumbnails/camera-123.jpg' }
};
window.firePlayerConfig.nativeStartupTimeout = 7500;
```

A preview image layer is retained through source assignment, native cleanup, HLS attachment and reconnect. It hides on `playing` or advancing playback time, rather than metadata or loadstart. A reconnect after a playable frame does not bring the overlay back. Both editor asset variants preserve explicit posters and include resolved posters in generated video markup. Existing post HTML is not migrated. FirePlayer assets have a single source of truth under `public/assets/default/`; both layouts load those same files, so no mirrored FirePlayer bundle was created.

For legacy live/auto HLS in `.post-content`, a known poster and disabled autoplay select `preload="none"` and deferred start. Bootstrap renders the preview without waking the stream or fetching a playlist/vendor. Play prepares the source; autoplay bypasses the deferral. `data-player-native` is explicitly excluded by the upgrader. File media and explicit VOD retain their preload behavior.

### Engine selection

Safari/iPhone/iPad still tries native HLS first. The new `nativeStartupTimeout` option defaults to **7500 ms**, configurable through constructor options, `data-native-startup-timeout`, or `firePlayerConfig.nativeStartupTimeout`. The watchdog starts after wake and source preparation, only while playback is requested. Wake latency is separate from this budget. The existing 30000 ms startup timeout remains the general failure deadline; metadata alone no longer cancels it.

`playing` or advancing currentTime establishes success. If native fails to start, the adapter clears the native source/pipeline while preserving the same media element, poster, muted state, volume, playsinline and play intent. It dynamically loads the existing hls.js vendor and attaches an instance only if `typeof Hls === 'function'` and `Hls.isSupported() === true`. No additional wake is made for this switch. The deadline initiates fallback around 7.5 seconds; vendor download and buffering take additional time.

The hls.js preference stays on the player instance across reconnect/source preparation. There is no automatic switch back to native. Previous Hls instances are destroyed before replacement. A destroyed/aborted player cannot attach a late-loaded engine. If the vendor fails or MSE is unsupported on an older iPhone, native gets a bounded retry and then the existing generic error/Retry UI; hls.js is never instantiated speculatively.

Native live stalls receive one native reload after `stallTimeout` (default 7000 ms). If time still fails to advance for another interval, hls.js is attempted. Actual progress resets the retry budget. Short buffering, user pause, seek, ended media, hidden documents and destroyed players do not trigger failover. The existing live health loop delegates native recovery to the adapter, avoiding duplicate retries. Chrome/Firefox/Android retain immediate dynamic hls.js selection without an Apple watchdog delay.

The internal `startuptimeout`, `nativefallback`, and `enginechange` events are emitted only when the existing `canViewVideoDiagnostics` permission flag is exactly true. The controller's `engine` getter reflects the selected engine, so the existing diagnostics panel stays current. No technical switching text is added to normal player UI.

Poster cache busting remains opt-in. Refresh checks respect `posterRefreshInterval`, use stable 30-second URL buckets, skip hidden/offscreen players, and stop after the first frame. Destroy removes timers. The interval no longer creates a unique timestamp URL every few seconds.

### Regression verification

`node tests/fireplayer_runtime.mjs` runs the real core helpers, legacy upgrade, HLS adapter and live module with mocked media/engine APIs and a deterministic clock. It covers poster priorities/empty state/first-frame visibility; metadata-only startup; fast native success; timeout switch; unsupported MSE; single-engine attachment; sticky engine selection; immediate non-Apple selection; retained poster/sound/inline settings; wake count; destroy during timeout/vendor loading; pause/hidden/seek; short and prolonged stalls; deferred/autoplay starts; permitted diagnostics; poster refresh lifecycle; and recovery from a failed deferred wake.

Validation on this patch:

- FirePlayer runtime: 47 checks passed.
- Background/PWA/legacy-upgrade tests: 34 checks passed.
- Notification polling: 38 checks passed.
- Performance runtime/assets/search hot path: 79 checks passed.
- Search schema regression: 9 checks passed.
- Migration/search integration: 24 checks passed on a disposable MySQL database; database removed.
- Changed JavaScript files: syntax checked; `git diff --check` clean.

These are deterministic regression tests, not device playback certification. Before releasing to MAXIPAPA, verify a real article and camera in Safari macOS, iPhone/iPad, Firefox, Chrome, Android and Yandex: poster before Play; normal native playback without vendor download; intentionally stalled native startup; CORS/codec support during MSE switch; unsupported old iOS; muted autoplay; pause/seek/hidden-tab behavior; prolonged stall/reconnect; and permitted diagnostics. Confirm `tn-ID.jpg` or configured metadata URLs are available and return images. Preserve PWA navigation/offline behavior.

After deployment rebuild content versions with `php bin/cms.php assets:rebuild` (the standard Update Center already performs this), then reload the page so the new JS/CSS version URLs are used. No database migration or bulk post rewrite is required. This patch has not been deployed to MAXIPAPA by the coding task.

Changed files in this patch:

- `docs/fireplayer.md`
- `public/assets/default/css/fireplayer.css`
- `public/assets/default/js/block-editor.js`
- `public/assets/default/js/fireplayer-hls.js`
- `public/assets/default/js/fireplayer-init.js`
- `public/assets/default/js/fireplayer-live.js`
- `public/assets/default/js/fireplayer.js`
- `tests/background_requests.mjs`
- `tests/fireplayer_runtime.mjs`
- `themes/default/assets/js/block-editor.js`
