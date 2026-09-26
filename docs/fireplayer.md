# FirePlayer 1.1.0

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

### Native HLS startup

Initial Safari/iOS startup follows `wake ready → attach source → play()`. The adapter assigns `src` only when different and calls `load()` only for a changed source or an absent `currentSrc`. It does not reset a healthy native source or wait for `loadedmetadata`/`canplay` before allowing `play()`. These events still drive the UI and recovery. A real native error/reconnect retains the bounded source-reset/readiness/retry path.

If native HLS video rejects audible playback with `NotAllowedError` after asynchronous preparation, FirePlayer tries `play()` once more muted, on the same source. Temporary mute never changes saved sound preferences or volume. An explicit Play, unmute or volume action restores normal control. It does not automatically unmute in `playing`, because [WebKit can pause playback when unmuted outside a user gesture](https://webkit.org/blog/6784/new-video-policies-for-ios/). If the muted attempt is also blocked, the existing Play prompt remains available. Other playback errors retain their existing recovery paths.

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
- `streamId` identifies a managed camera; `/stream-ID/index.m3u8` URLs are recognized automatically. Initial attachment requires `/api/streams/wake` to return `ready: true`. Reconnect first attempts local recovery; repeated recovery and manual retry can wake the backend again. `lazyStart` remains accepted for legacy markup compatibility.

Camera Manager uses a single lazy modal player. Closing the modal unloads the HLS engine, so hidden cameras do not continue downloading segments.

## Compatibility with the previous Plyr integration

- Native Safari/iOS HLS and bundled hls.js paths are retained, with backend readiness checks before camera playback.
- Reconnect, decoder recovery, LIVE stall detection, poster refresh, fullscreen/PiP, volume, playback speed, zoom/pan and keyboard/touch interaction are retained. Recovery is bounded and never overrides an explicit user pause.
- Existing post/page video and audio are upgraded, preserving playback attributes, posters, aspect ratios and text tracks. New blocks and the editor canvas use FirePlayer. Removed players are disposed; moving an existing DOM node does not destroy it.
- YouTube/Vimeo remain provider embeds, not direct media URLs. The isolated document-preview iframe deliberately blocks scripts; it uses native media controls and a source link instead of an empty FirePlayer mount. HLS playback there depends on native browser support. This does not change the editor canvas or published player.
- Chat/background media and remaining legacy provider integrations are outside this replacement. Plyr is still loaded for compatibility, but must not initialize FirePlayer media a second time. This is not a claim of complete parity with every optional Plyr feature.

## Verification

```bash
node tests/fireplayer_native_startup.cjs
node tests/fireplayer_reliability.cjs
php tests/fireplayer_backend.php
# Playwright may be installed outside the repository and supplied through NODE_PATH.
node tests/fireplayer_browser.cjs
```

This dependency-free regression test runs the production core and HLS adapter with controlled media, readiness and browser-policy events. It checks source attachment and the first `play()` without metadata/canplay, source reuse, readiness failures, cancellation, native recovery, the hls.js path, and the bounded muted fallback without changing saved sound preferences.

For the device comparison, keep a camera playing on phone 1, then open the same camera on phone 2 and compare legacy `/admin/posts/preview/17` with its FirePlayer page. On iPhone Safari, check first Play, sound enablement, pause/resume, reconnect and switching cameras. In a browser performance recording, compare the successful wake response with the first `media.play()` call; no native readiness wait should sit between them.

This does not certify Safari/iPhone, actual camera connectivity, server-side stream readiness, or HLS decoding against a real segment feed; those require a deployment/device smoke test.

## Reliability architecture (1.1.0)

Core owns source tokens, play intent, cancellation, persistence and `_transition(state, meta)`.
The HLS adapter owns exactly one native source or bundled Hls instance. The LIVE extension
observes frame progress; the audio extension owns Media Session only while it is the active
session owner. Video gestures/zoom and creator diagnostics remain separate extensions.
Optional playlists should be implemented through `FirePlayer.use`, not a second core/session owner.

```text
idle → lazy → detecting → waking → connecting → ready → playing
                                                playing ⇄ buffering
                                                playing → paused → idle detach
                                                playing → reconnecting → playing/error
                                                playing → ended
active → offline → reconnect with preserved intent (not after explicit Pause)
autoplay denied → awaiting-gesture → explicit Play
any state → destroyed
```

Normal loading, offline and playback prompts are always user-facing, independently of
`canViewVideoStatus`. Detailed diagnostics still require the creator flag. The state event is
`statechange`, with `state` and `previous`. Existing public methods and events remain supported.
User controls/status/zoom labels support `ru`, `en`, `de`, and `zh-cn`.

### Managed streams and wake coordination

Managed sources (canonical `/stream-ID/index.m3u8` or explicit `streamId`) are lazy unless
autoplay is explicitly enabled. No frontend manifest probe runs for managed sources. For the
wake endpoint the URL must still have the canonical camera path and matching ID; an explicit
ID is not permission to probe arbitrary URLs.

Cold start: one logical wake → backend readiness → source attach → Play. Warm start uses a
5-second positive readiness cache. The backend requests the manifest with GET and probes the
newest three EXTINF-associated segments, newest first. HEAD failures fall back to a one-byte
Range request; one available recent segment is sufficient. Readiness polling retains its
30-second polling budget, 1.5-second interval and 5-second per-request timeout; the last HTTP
probe can extend elapsed time beyond the polling budget.

PHP workers coordinate through exclusive `flock` slots under `CACHE/.locks/stream-xx`.
There are at most 256 small files, not one permanent file per user-supplied URL. The full
source hash validates positive cache entries. Slot collisions serialize unrelated requests
briefly but cannot reuse another source's result. Files are not unlinked while workers may
hold them; the OS releases locks on process exit. Expiry applies to ready data, not a persistent
"starting" flag. Failed readiness is not cached. This requires a shared filesystem with working
`flock`; separate servers with separate cache volumes do not share single-flight coordination.

The compatible API includes `success`, `stream_id`, `woke`, `ready`, `state`, `retryable`,
`code`, `message`. Busy workers return `state: starting`, `code: STARTING`, `retryable: true`.
The frontend retries that response inside its bounded shared wake operation. Legacy
`{success:true, ready:true}` responses still work. Other clients using the endpoint must also
handle STARTING rather than treating it as a terminal camera failure.

Wake consumers are keyed by stream ID plus full source URL. Removing one consumer does not
cancel another; removing the last aborts the shared fetch. Rejected promises leave the map.
Offline tears down the pending/active source and preserves intent; Online resumes only if
the user has not paused. `managedIdleDetach` defaults to 60000 ms; `0` disables it. After a
paused managed source is detached, the next Play performs readiness again.

Camera Manager unloads at `hide.bs.modal` (before the close animation finishes). Source tokens
prevent an old camera's late wake from attaching after a source switch.

### Recovery and frame health

Local hls.js network/media recovery precedes soft rebuild, then managed wake when necessary.
The existing buffer/worker/low-latency settings are unchanged. Backoff is exponential with
jitter, capped at 15 seconds. `maxReconnectAttempts` is four by default; Manual Retry resets
the budget. A resolved Play promise or a `playing` event does not reset recovery counters.
At least two seconds of observed healthy progress is required. Terminal errors stop playback
and HLS loading. An error received while paused waits for the next explicit Play.

LIVE health sampling runs every two seconds, preferring requestVideoFrameCallback, then
total decoded frames, WebKit decoded frame count, and finally currentTime. A moving clock
cannot mask frozen frames when frame metrics exist. Hidden, paused, seeking, offline,
native-preparing and gesture-blocked players are excluded. Visibility changes reset the
startup grace period, without an automatic Play/unmute. VOD does not use the LIVE watchdog.
LIVE seeks are clamped to the last seekable range and prefer the engine's liveSyncPosition.

Diagnostics add state, managed/online flags, live edge/latency/sync target, wake/manifest/
first-frame timings, frame age, recovery stage/reason. Unsupported native engine metrics
remain a dash; a sampled frame metric can be delayed by the two-second sampling interval.
No additional network requests are made by diagnostics.

### Settings and audio

Settings display Quality only for multi-level hls.js sources. Auto sets currentLevel to -1;
manual choices show resolution/bitrate. Native HLS controls ABR itself, so its quality selector
is hidden. Subtitles expose native textTracks (captions/subtitles), including Off and existing
default selection. HLS embedded subtitles are supported only when the engine exposes textTracks.

Audio accepts title, artist, album, poster and an artwork array. Media Session supports play,
pause, seekbackward, seekforward and seekto, testing each optional browser action separately.
Removing an inactive audio player never clears another player's metadata or controls.

### Security and deployment configuration

Camera Manager's administrator-configured `hls_base_url` is trusted automatically. Other
deployments must set `allowed_hls_bases` in `config/streams.php`, including scheme, host, port
and path prefix. Client Host headers are not an allowlist. URLs with credentials, mismatched
IDs, traversal/control characters, or untrusted origins are rejected. Segment probes must
also use trusted bases. Upstream HTTP redirects are intentionally not followed; configure
the final HLS base directly (including a trusted CDN base if segments live there). Response
reads are bounded. Restrict upstream egress in deployment as additional protection against
compromised trusted DNS/hosts.

Stable internal codes include CAMERA_WAKE_TIMEOUT, CAMERA_NOT_READY, NETWORK_OFFLINE,
AUTOPLAY_BLOCKED, FIRST_FRAME_TIMEOUT, RECOVERY_EXHAUSTED, UNSUPPORTED_FORMAT, HLS_MANIFEST_ERROR,
HLS_SEGMENT_ERROR, HLS_NETWORK_ERROR, HLS_MEDIA_ERROR, HLS_FATAL_ERROR and NATIVE_HLS_*.
Wake codes include READY, READY_CACHED, STARTING, MANIFEST_UNAVAILABLE, MANIFEST_INVALID,
SEGMENTS_NOT_READY, UPSTREAM_TIMEOUT, INVALID_STREAM, INVALID_HLS_URL and COORDINATION_UNAVAILABLE.
Public status text does not expose those internal codes, URLs, credentials or exception stacks.
DASH is detected but still requires an external registered adapter or native support.

### Automated verification scope

The native suite retains 12 groups. The reliability suite covers shared/cancelled wake,
structured STARTING, explicit stream IDs, VOD exclusion, offline intent, recovery budgets,
frozen frames, idle detach, HLS failures, Media Session ownership and 100 lifecycle cycles.
Its Hls/decoder are controlled doubles; timers and exposed event listeners are counted and
Node fails on unhandled rejections. The PHP test checks origin/path rejection, query handling,
lock contention/warm cache and a real local HTTP manifest/segment fixture (requires pcntl).
Browser smoke uses actual DOM in Chromium and Firefox with a mocked decoder/transport;
it covers quality, subtitles, lazy startup, source replacement, audio and error UI, not codecs.

### Required real-device acceptance (not certified by automated tests)

Run separately on iPhone Safari, iPad Safari, macOS Safari, macOS Chrome, Android Chrome and
Android Firefox. Record OS/browser version, camera ID, cold/warm state, wake-to-first-frame
time, result and any console/network errors without sharing secret query strings.

- Cold camera, warm camera, unavailable camera, then camera recovery.
- Wi-Fi → LTE and LTE → Wi-Fi; airplane mode/offline → online.
- Background → foreground and screen lock → return.
- Pause → wait over 60 seconds → Play; verify no automatic resume after explicit Pause.
- Ten quick modal open/close cycles and ten A/B/C camera switches.
- At least 30 minutes of LIVE playback with no growing Hls/timer/request count.
- Sound restoration on explicit gesture, fullscreen/PiP, zoom and vertical page scrolling.
- Audio/VOD timeline, volume and settings in light/dark themes and narrow viewports.

MAXIPAPA: open a page containing multiple managed cameras with autoplay disabled. Before
Play require **zero POST /api/streams/wake and zero GET index.m3u8**. Play one camera: require
one logical wake (STARTING polling may produce several HTTP POSTs), then ready → attach →
first frame → LIVE. Repeat while another device already watches the same camera; verify
fast warm readiness and no unnecessary 30-second wait. Compare against the legacy preview
on actual iPhone Safari; Chromium is not a Safari substitute.
