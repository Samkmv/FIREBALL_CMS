<?php
$siteTitle = trim((string)site_setting('site_title', SITE_NAME));
$siteTitle = $siteTitle !== '' ? $siteTitle : SITE_NAME;
$pageTitle = return_translation('update_maintenance_title');
$retryAfter = max(5, (int)($retry_after ?? 12));
?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>" data-bs-theme="dark" data-update-maintenance-page="1">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlSC($pageTitle) ?> · <?= htmlSC($siteTitle) ?></title>
    <?= pwa_head_tags() ?>
    <meta name="theme-color" content="#111721">
    <script src="<?= base_url('/assets/default/js/theme-switcher.js') ?>"></script>
    <link rel="preload" href="<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('/assets/default/icons/cartzilla-icons.min.css') ?>">
    <style>
        @font-face {
            font-family: "FIREBALL Inter";
            src: url("<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>") format("woff2");
            font-display: swap;
            font-weight: 100 900;
        }
        :root {
            color-scheme: dark;
            --update-bg: #111721;
            --update-surface: #19222f;
            --update-soft: #202c3b;
            --update-border: #303c4d;
            --update-text: #f0f4fa;
            --update-muted: #a6b3c6;
            --update-accent: #ff684b;
        }
        [data-bs-theme="light"] {
            color-scheme: light;
            --update-bg: #f4f6f9;
            --update-surface: #fff;
            --update-soft: #f3f5f8;
            --update-border: #e0e5ec;
            --update-text: #182235;
            --update-muted: #647187;
            --update-accent: #d9472c;
        }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--update-text); background: var(--update-bg); font-family: "FIREBALL Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; }
        .update-page { display: grid; min-height: 100svh; place-items: center; padding: calc(32px + env(safe-area-inset-top, 0px)) 20px calc(32px + env(safe-area-inset-bottom, 0px)); }
        .update-shell { width: min(100%, 520px); min-width: 0; }
        .update-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 28px; color: var(--update-text); text-decoration: none; font-weight: 700; font-size: .9rem; }
        .update-brand__mark { display: inline-grid; flex: 0 0 auto; width: 32px; height: 32px; place-items: center; border: 1px solid var(--update-border); border-radius: 10px; color: var(--update-accent); }
        .update-brand > span:last-child { overflow-wrap: anywhere; }
        .update-card { padding: clamp(28px, 6vw, 44px); border: 1px solid var(--update-border); border-radius: 24px; background: var(--update-surface); text-align: center; box-shadow: 0 16px 48px rgba(0, 0, 0, .06); }
        .update-loader { position: relative; display: grid; width: 76px; height: 76px; place-items: center; margin: 0 auto 26px; color: var(--update-accent); font-size: 26px; }
        /* Only the outer ring rotates. Its center and geometry remain fixed. */
        .update-loader::before { content: ""; position: absolute; inset: 0; border: 3px solid var(--update-border); border-top-color: var(--update-accent); border-radius: 50%; animation: update-spin 1.8s linear infinite; }
        .update-loader > i { display: grid; place-items: center; width: 50px; height: 50px; border-radius: 50%; background: var(--update-soft); }
        .update-status { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px; color: var(--update-muted); font-size: .78rem; font-weight: 600; line-height: 1.5; }
        .update-status__dot { width: 6px; height: 6px; flex: 0 0 auto; border-radius: 50%; background: var(--update-accent); }
        .update-title { margin: 0; font-size: clamp(1.8rem, 6vw, 2.35rem); font-weight: 700; letter-spacing: -.045em; line-height: 1.15; overflow-wrap: anywhere; }
        .update-message { margin: 18px 0 24px; color: var(--update-muted); font-size: .95rem; line-height: 1.75; }
        .update-hint { display: flex; align-items: flex-start; gap: 10px; padding: 16px; margin: 0; border-radius: 12px; background: var(--update-soft); color: var(--update-muted); font-size: .8rem; line-height: 1.65; text-align: left; }
        .update-hint > i { flex-shrink: 0; margin-top: 3px; color: var(--update-text); }
        .update-refresh { display: inline-flex; min-height: 46px; width: 100%; align-items: center; justify-content: center; gap: 10px; margin-top: 24px; padding: 12px 16px; border: 1px solid var(--update-border); border-radius: 12px; color: var(--update-text); background: var(--update-surface); font-size: .875rem; font-weight: 600; text-decoration: none; transition: background-color .15s ease, border-color .15s ease; }
        .update-refresh:hover { border-color: var(--update-accent); background: var(--update-soft); }
        .update-refresh[aria-disabled="true"] { opacity: .65; pointer-events: none; }
        a:focus-visible { outline: 3px solid var(--update-accent); outline-offset: 4px; }
        .update-note { margin: 22px 0 0; text-align: center; color: var(--update-muted); font-size: .75rem; overflow-wrap: anywhere; }
        @keyframes update-spin { to { transform: rotate(360deg); } }
        @media (max-width: 380px) { .update-page { padding-inline: 12px; } .update-card { padding: 28px 20px; } }
        @media (prefers-reduced-motion: reduce) { .update-loader::before { animation: none; } .update-refresh { transition: none; } }
    </style>
</head>
<body>
    <main class="update-page">
        <div class="update-shell">
            <a class="update-brand" href="<?= htmlSC(base_href('/')) ?>" aria-label="<?= htmlSC($siteTitle) ?>">
                <span class="update-brand__mark" aria-hidden="true"><i class="ci-layers"></i></span>
                <span><?= htmlSC($siteTitle) ?></span>
            </a>
            <section class="update-card" aria-labelledby="update-title">
                <div class="update-loader" aria-hidden="true"><i class="ci-refresh-cw"></i></div>
                <div class="update-status" role="status">
                    <span class="update-status__dot" aria-hidden="true"></span>
                    <span><?= print_translation('update_maintenance_status') ?></span>
                </div>
                <h1 class="update-title" id="update-title"><?= htmlSC($pageTitle) ?></h1>
                <p class="update-message"><?= print_translation('update_maintenance_message') ?></p>
                <p class="update-hint"><i class="ci-info" aria-hidden="true"></i><span><?= print_translation('update_maintenance_hint') ?></span></p>
                <a class="update-refresh" href="" data-update-refresh>
                    <i class="ci-refresh-cw" aria-hidden="true"></i><span><?= print_translation('update_maintenance_refresh') ?></span>
                </a>
            </section>
            <p class="update-note"><?= htmlSC($siteTitle) ?></p>
        </div>
    </main>
    <script>
        (function () {
            'use strict';
            var retryDelay = <?= (int)$retryAfter * 1000 ?>;
            var marker = 'data-update-maintenance-page="1"';
            var timer = null;
            var pending = false;
            var refresh = document.querySelector('[data-update-refresh]');

            function scheduleNextCheck() {
                window.clearTimeout(timer);
                if (!document.hidden) timer = window.setTimeout(checkUpdateState, retryDelay);
            }

            async function checkUpdateState() {
                if (pending) return;
                window.clearTimeout(timer);
                pending = true;
                refresh.setAttribute('aria-disabled', 'true');
                refresh.setAttribute('aria-busy', 'true');
                var controller = new AbortController();
                var timeout = window.setTimeout(function () { controller.abort(); }, 10000);
                try {
                    var response = await window.fetch(window.location.href, {
                        method: 'GET', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                        headers: {'X-Fireball-Update-Check': '1'}
                    });
                    var html = await response.text();
                    // A temporary 5xx response is not evidence that the update has finished.
                    if (response.ok && /text\/html/i.test(response.headers.get('content-type') || '') && html.indexOf(marker) === -1) {
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    // Keep the stable screen during a restart, timeout or loss of connection.
                } finally {
                    window.clearTimeout(timeout);
                    pending = false;
                    refresh.removeAttribute('aria-disabled');
                    refresh.removeAttribute('aria-busy');
                }
                scheduleNextCheck();
            }
            refresh.addEventListener('click', function (event) { event.preventDefault(); checkUpdateState(); });
            document.addEventListener('visibilitychange', function () {
                window.clearTimeout(timer);
                if (!document.hidden) scheduleNextCheck();
            });
            window.addEventListener('pagehide', function () { window.clearTimeout(timer); });
            scheduleNextCheck();
        })();
    </script>
</body>
</html>
