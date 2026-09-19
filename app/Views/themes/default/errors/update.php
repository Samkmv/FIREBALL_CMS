<?php
$siteTitle = trim((string)site_setting('site_title', SITE_NAME));
$siteTitle = $siteTitle !== '' ? $siteTitle : SITE_NAME;
$pageTitle = return_translation('update_maintenance_title');
$retryAfter = max(5, (int)($retry_after ?? 12));
?>
<!doctype html>
<html
    lang="<?= htmlSC(current_locale()) ?>"
    data-bs-theme="dark"
    data-update-maintenance-page="1"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlSC($pageTitle) ?> · <?= htmlSC($siteTitle) ?></title>
    <?= pwa_head_tags() ?>
    <meta name="theme-color" content="#0f1724">

    <script src="<?= base_url('/assets/default/js/theme-switcher.js') ?>"></script>

    <link rel="preload" href="<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('/assets/default/icons/cartzilla-icons.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('/assets/default/icons/cartzilla-icons.min.css') ?>">

    <style>
        /* FIREBALL_UPDATE_PAGE_REDESIGN_V2 */

        @font-face {
            font-family: "FIREBALL Inter";
            src: url("<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>") format("woff2");
            font-display: swap;
            font-weight: 100 900;
        }

        :root {
            color-scheme: dark;
            --update-bg: #0f1724;
            --update-surface: rgba(22, 32, 46, .94);
            --update-surface-soft: rgba(255, 255, 255, .045);
            --update-border: rgba(181, 198, 222, .17);
            --update-border-strong: rgba(181, 198, 222, .26);
            --update-text: #f6f8fb;
            --update-muted: #97a5b9;
            --update-accent: #ff6654;
            --update-accent-2: #ef4f7b;
            --update-success: #49d394;
            --update-shadow: 0 28px 80px rgba(0, 0, 0, .28);
        }

        [data-bs-theme="light"] {
            color-scheme: light;
            --update-bg: #f3f6fa;
            --update-surface: rgba(255, 255, 255, .96);
            --update-surface-soft: rgba(32, 47, 72, .045);
            --update-border: rgba(41, 58, 85, .13);
            --update-border-strong: rgba(41, 58, 85, .2);
            --update-text: #182235;
            --update-muted: #66748a;
            --update-accent: #ef5d4d;
            --update-accent-2: #df4771;
            --update-success: #22a86f;
            --update-shadow: 0 28px 70px rgba(39, 55, 82, .14);
        }

        * { box-sizing: border-box; }

        html,
        body { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100svh;
            overflow-x: hidden;
            color: var(--update-text);
            background:
                radial-gradient(circle at 12% 5%, rgba(61, 133, 255, .12), transparent 34rem),
                radial-gradient(circle at 90% 94%, rgba(239, 79, 123, .10), transparent 32rem),
                var(--update-bg);
            font-family: "FIREBALL Inter", Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .update-page {
            display: grid;
            min-height: 100svh;
            place-items: center;
            padding:
                calc(2rem + env(safe-area-inset-top, 0px))
                1rem
                calc(2rem + env(safe-area-inset-bottom, 0px));
        }

        .update-shell {
            width: min(100%, 38rem);
        }

        .update-brand {
            display: flex;
            width: fit-content;
            align-items: center;
            gap: .75rem;
            margin: 0 auto 1.35rem;
            color: var(--update-text);
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .075em;
            text-decoration: none;
            text-transform: uppercase;
        }

        .update-brand__mark {
            display: inline-grid;
            width: 2.65rem;
            height: 2.65rem;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: .9rem;
            color: #fff;
            background: linear-gradient(145deg, var(--update-accent), var(--update-accent-2));
            box-shadow:
                0 .55rem 1.35rem rgba(239, 79, 123, .22),
                inset 0 1px 0 rgba(255, 255, 255, .25);
            font-size: 1.05rem;
        }

        .update-card {
            position: relative;
            overflow: hidden;
            padding: clamp(2rem, 5vw, 3rem);
            border: 1px solid var(--update-border);
            border-radius: 1.75rem;
            background: var(--update-surface);
            box-shadow: var(--update-shadow);
            text-align: center;
        }

        .update-card::before {
            position: absolute;
            top: 0;
            right: 18%;
            left: 18%;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--update-accent), var(--update-accent-2), transparent);
            content: "";
        }

        .update-loader {
            position: relative;
            display: grid;
            width: 4.5rem;
            height: 4.5rem;
            place-items: center;
            margin: 0 auto 1.35rem;
            border-radius: 50%;
        }

        .update-loader::before {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: conic-gradient(
                from 0deg,
                rgba(255, 102, 84, .06) 0 42%,
                var(--update-accent) 68%,
                var(--update-accent-2) 100%
            );
            content: "";
            animation: update-loader-spin 1.15s linear infinite;
        }

        .update-loader::after {
            position: absolute;
            inset: .38rem;
            border: 1px solid var(--update-border);
            border-radius: inherit;
            background: var(--update-surface);
            content: "";
        }

        .update-loader__core {
            position: relative;
            z-index: 1;
            display: block;
            width: .55rem;
            height: .55rem;
            border-radius: 50%;
            background: var(--update-accent);
            box-shadow: 0 0 1rem rgba(255, 102, 84, .55);
        }

        .update-status {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1rem;
            padding: .45rem .75rem;
            border: 1px solid var(--update-border);
            border-radius: 999px;
            color: var(--update-muted);
            background: var(--update-surface-soft);
            font-size: .76rem;
            font-weight: 750;
            letter-spacing: .075em;
            text-transform: uppercase;
        }

        .update-status__dot {
            width: .45rem;
            height: .45rem;
            flex: 0 0 auto;
            border-radius: 50%;
            background: var(--update-success);
            box-shadow: 0 0 .65rem rgba(73, 211, 148, .6);
        }

        .update-title {
            margin: 0;
            font-size: clamp(2rem, 6vw, 2.8rem);
            font-weight: 780;
            letter-spacing: -.045em;
            line-height: 1.08;
        }

        .update-message {
            max-width: 30rem;
            margin: 1rem auto 0;
            color: var(--update-muted);
            font-size: clamp(.98rem, 2.4vw, 1.08rem);
            line-height: 1.7;
        }

        .update-progress {
            position: relative;
            height: .48rem;
            margin: 2rem 0 .8rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(151, 165, 185, .17);
        }

        .update-progress::before {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background:
                linear-gradient(
                    90deg,
                    transparent 0%,
                    transparent 24%,
                    var(--update-accent) 44%,
                    var(--update-accent-2) 56%,
                    transparent 76%,
                    transparent 100%
                );
            background-size: 220% 100%;
            content: "";
            animation: update-progress-flow 1.65s linear infinite;
        }

        .update-hint {
            margin: 0;
            color: rgba(151, 165, 185, .8);
            font-size: .84rem;
            line-height: 1.55;
        }

        .update-actions {
            display: flex;
            justify-content: center;
            margin-top: 1.6rem;
        }

        .update-refresh {
            display: inline-flex;
            min-height: 3rem;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            padding: .72rem 1.25rem;
            border: 1px solid var(--update-border-strong);
            border-radius: 999px;
            color: var(--update-text);
            background: var(--update-surface-soft);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            transition:
                transform .18s ease,
                border-color .18s ease,
                background-color .18s ease;
        }

        .update-refresh:hover,
        .update-refresh:focus-visible {
            border-color: rgba(255, 102, 84, .55);
            background: rgba(255, 102, 84, .1);
            transform: translateY(-1px);
        }

        .update-refresh:focus-visible {
            outline: 3px solid rgba(255, 102, 84, .22);
            outline-offset: 3px;
        }

        .update-note {
            margin: 1.25rem 0 0;
            color: rgba(151, 165, 185, .58);
            font-size: .74rem;
        }

        @keyframes update-loader-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes update-progress-flow {
            from { background-position: 120% 0; }
            to { background-position: -120% 0; }
        }

        @media (max-width: 520px) {
            .update-page { padding-inline: .8rem; }

            .update-card {
                padding: 1.75rem 1.2rem;
                border-radius: 1.4rem;
            }

            .update-loader {
                width: 4rem;
                height: 4rem;
            }

            .update-brand {
                margin-bottom: 1rem;
                font-size: 1rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .update-loader::before,
            .update-progress::before {
                animation: none;
            }

            .update-progress::before {
                background-position: 50% 0;
            }

            .update-refresh {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <main class="update-page">
        <div class="update-shell">
            <a
                class="update-brand"
                href="<?= htmlSC(base_href('/')) ?>"
                aria-label="<?= htmlSC($siteTitle) ?>"
            >
                <span class="update-brand__mark" aria-hidden="true">
                    <i class="ci-refresh-cw"></i>
                </span>
                <span><?= htmlSC($siteTitle) ?></span>
            </a>

            <section
                class="update-card"
                aria-labelledby="update-title"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="update-loader" aria-hidden="true">
                    <span class="update-loader__core"></span>
                </div>

                <div class="update-status">
                    <span class="update-status__dot" aria-hidden="true"></span>
                    <span><?= print_translation('update_maintenance_status') ?></span>
                </div>

                <h1 class="update-title" id="update-title">
                    <?= htmlSC($pageTitle) ?>
                </h1>

                <p class="update-message">
                    <?= print_translation('update_maintenance_message') ?>
                </p>

                <div
                    class="update-progress"
                    role="progressbar"
                    aria-label="<?= htmlSC(return_translation('update_maintenance_status')) ?>"
                    aria-valuetext="<?= htmlSC(return_translation('update_maintenance_status')) ?>"
                ></div>

                <p class="update-hint">
                    <?= print_translation('update_maintenance_hint') ?>
                </p>

                <div class="update-actions">
                    <a class="update-refresh" href="<?= htmlSC(base_href('/')) ?>">
                        <i class="ci-refresh-cw" aria-hidden="true"></i>
                        <span><?= print_translation('update_maintenance_refresh') ?></span>
                    </a>
                </div>

                <p class="update-note"><?= htmlSC($siteTitle) ?></p>
            </section>
        </div>
    </main>

    <script>
        (function () {
            'use strict';

            var retryDelay = <?= (int)$retryAfter * 1000 ?>;
            var marker = 'data-update-maintenance-page="1"';
            var timer = null;

            function scheduleNextCheck() {
                window.clearTimeout(timer);
                timer = window.setTimeout(checkUpdateState, retryDelay);
            }

            async function checkUpdateState() {
                try {
                    var response = await window.fetch(window.location.href, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'X-Fireball-Update-Check': '1'
                        }
                    });

                    var html = await response.text();

                    if (html.indexOf(marker) === -1) {
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    // Во время обновления соединение может кратковременно пропадать.
                    // Не перезагружаем экран с ошибкой — просто проверим снова.
                }

                scheduleNextCheck();
            }

            scheduleNextCheck();
        })();
    </script>
</body>
</html>
