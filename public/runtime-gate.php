<?php

// Keep requests out of partially replaced application code, before autoload/config/SQL.
$fireballStorage = dirname(__DIR__) . '/storage';
$fireballUnavailable = static function () use ($fireballStorage): never {
    http_response_code(503);
    header('Retry-After: 12');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // This screen must work while application files/dependencies are being replaced.
    // Use only built-in PHP, inline assets and public presentation metadata.
    $basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    if (basename($basePath) === 'public') $basePath = dirname($basePath);
    if ($basePath === '.' || $basePath === '/') $basePath = '';
    $path = explode('?', (string)($_SERVER['REQUEST_URI'] ?? '/'), 2)[0];
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) $path = substr($path, strlen($basePath));
    $language = explode('/', trim($path, '/'))[0];
    $locale = in_array($language, ['en', 'de', 'zh-cn'], true) ? $language : 'ru';
    $copy = match ($locale) {
        'en' => ['The site is updating', 'Update in progress', 'We are installing the latest CMS version. This will only take a moment.', 'This page will refresh automatically when the update is complete.', 'Refresh now'],
        'de' => ['Die Website wird aktualisiert', 'Aktualisierung läuft', 'Wir installieren die neueste CMS-Version. Dies dauert nur einen Moment.', 'Diese Seite wird nach Abschluss automatisch aktualisiert.', 'Jetzt aktualisieren'],
        'zh-cn' => ['网站正在更新', '更新进行中', '我们正在安装最新版本的 CMS，请稍候片刻。', '更新完成后，此页面会自动刷新。', '立即刷新'],
        default => ['Сайт обновляется', 'Обновление выполняется', 'Мы устанавливаем свежую версию CMS. Это займёт немного времени.', 'Страница обновится автоматически после завершения работ.', 'Обновить сейчас'],
    };
    $metadata = json_decode((string)@file_get_contents($fireballStorage . '/update.maintenance', false, null, 0, 8192), true);
    $siteTitle = is_string($metadata['site_title'] ?? null) ? trim(substr($metadata['site_title'], 0, 512)) : '';
    if ($siteTitle === '') $siteTitle = 'FIREBALL CMS';
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
<!doctype html>
<html lang="<?= $locale ?>" data-bs-theme="light" data-update-maintenance-page="1">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $escape($copy[0]) ?> · <?= $escape($siteTitle) ?></title>
    <meta name="theme-color" content="#111721">
    <script>
        (function () {
            var theme = 'light';
            try { theme = localStorage.getItem('theme') || theme; } catch (error) {}
            if (theme === 'auto') theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            theme = theme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.querySelector('meta[name="theme-color"]').content = theme === 'dark' ? '#111721' : '#f4f6f9';
        })();
    </script>
    <style>
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
        body { margin: 0; color: var(--update-text); background: var(--update-bg); font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; }
        .update-page { display: grid; min-height: 100svh; place-items: center; padding: calc(32px + env(safe-area-inset-top, 0px)) 20px calc(32px + env(safe-area-inset-bottom, 0px)); }
        .update-shell { width: min(100%, 520px); min-width: 0; }
        .update-brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 28px; color: var(--update-text); text-decoration: none; font-weight: 700; font-size: .9rem; }
        .update-brand__mark { display: inline-grid; flex: 0 0 auto; width: 32px; height: 32px; place-items: center; border: 1px solid var(--update-border); border-radius: 10px; color: var(--update-accent); }
        .update-brand > span:last-child { overflow-wrap: anywhere; }
        .update-card { padding: clamp(28px, 6vw, 44px); border: 1px solid var(--update-border); border-radius: 24px; background: var(--update-surface); text-align: center; box-shadow: 0 16px 48px rgba(0, 0, 0, .06); }
        .update-loader { position: relative; display: grid; width: 76px; height: 76px; place-items: center; margin: 0 auto 26px; color: var(--update-accent); font-size: 26px; }
        /* Only the outer ring rotates. Its center and geometry remain fixed. */
        .update-loader::before { content: ""; position: absolute; inset: 0; border: 3px solid var(--update-border); border-top-color: var(--update-accent); border-radius: 50%; animation: update-spin 1.8s linear infinite; }
        .update-loader__center { display: grid; place-items: center; width: 50px; height: 50px; border-radius: 50%; background: var(--update-soft); }
        .update-status { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px; color: var(--update-muted); font-size: .78rem; font-weight: 600; line-height: 1.5; }
        .update-status__dot { width: 6px; height: 6px; flex: 0 0 auto; border-radius: 50%; background: var(--update-accent); }
        .update-title { margin: 0; font-size: clamp(1.8rem, 6vw, 2.35rem); font-weight: 700; letter-spacing: -.045em; line-height: 1.15; overflow-wrap: anywhere; }
        .update-message { margin: 18px 0 24px; color: var(--update-muted); font-size: .95rem; line-height: 1.75; }
        .update-hint { display: flex; align-items: flex-start; gap: 10px; padding: 16px; margin: 0; border-radius: 12px; background: var(--update-soft); color: var(--update-muted); font-size: .8rem; line-height: 1.65; text-align: left; }
        .update-hint > svg { flex-shrink: 0; margin-top: 3px; color: var(--update-text); }
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
            <a class="update-brand" href="<?= $escape($basePath . '/') ?>" aria-label="<?= $escape($siteTitle) ?>">
                <span class="update-brand__mark" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg></span>
                <span><?= $escape($siteTitle) ?></span>
            </a>
            <section class="update-card" aria-labelledby="update-title">
                <div class="update-loader" aria-hidden="true"><span class="update-loader__center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 6.1a8 8 0 0 1 13.2 2.8M4.7 15.1a8 8 0 0 0 13.2 2.8"/></svg></span></div>
                <div class="update-status" role="status">
                    <span class="update-status__dot" aria-hidden="true"></span>
                    <span><?= $escape($copy[1]) ?></span>
                </div>
                <h1 class="update-title" id="update-title"><?= $escape($copy[0]) ?></h1>
                <p class="update-message"><?= $escape($copy[2]) ?></p>
                <p class="update-hint"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></svg><span><?= $escape($copy[3]) ?></span></p>
                <a class="update-refresh" href="" data-update-refresh>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 6.1a8 8 0 0 1 13.2 2.8M4.7 15.1a8 8 0 0 0 13.2 2.8"/></svg><span><?= $escape($copy[4]) ?></span>
                </a>
            </section>
            <p class="update-note"><?= $escape($siteTitle) ?></p>
        </div>
    </main>
    <script>
        (function () {
            'use strict';
            var retryDelay = 12000;
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
                        window.location.replace(window.location.href);
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

    <?php
    exit;
};
if (is_file($fireballStorage . '/update.maintenance')) $fireballUnavailable();
$GLOBALS['fireball_runtime_lock'] = @fopen($fireballStorage . '/update.lock', 'c+');
if (!is_resource($GLOBALS['fireball_runtime_lock']) || !flock($GLOBALS['fireball_runtime_lock'], LOCK_SH | LOCK_NB)) $fireballUnavailable();
if (is_file($fireballStorage . '/update.maintenance')) $fireballUnavailable();
register_shutdown_function(static function (): void {
    if (is_resource($GLOBALS['fireball_runtime_lock'] ?? null)) {
        flock($GLOBALS['fireball_runtime_lock'], LOCK_UN);
        fclose($GLOBALS['fireball_runtime_lock']);
    }
});
