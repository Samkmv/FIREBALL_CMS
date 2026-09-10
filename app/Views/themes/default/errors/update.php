<?php
$siteTitle = trim((string)site_setting('site_title', SITE_NAME));
$siteTitle = $siteTitle !== '' ? $siteTitle : SITE_NAME;
$pageTitle = return_translation('update_maintenance_title');
$retryAfter = max(5, (int)($retry_after ?? 12));
?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlSC($pageTitle) ?> · <?= htmlSC($siteTitle) ?></title>
    <?= pwa_head_tags() ?>
    <meta name="theme-color" content="#101722">
    <script src="<?= base_url('/assets/default/js/theme-switcher.js') ?>"></script>
    <link rel="preload" href="<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= base_url('/assets/default/icons/cartzilla-icons.woff2') ?>" as="font" type="font/woff2" crossorigin>
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
            --update-bg: #101722;
            --update-panel: rgba(27, 36, 50, .74);
            --update-border: rgba(184, 201, 226, .18);
            --update-muted: #9aa8bc;
            --update-accent: #ff6250;
            --update-accent-2: #ef3f70;
        }

        [data-bs-theme="light"] {
            color-scheme: light;
            --update-bg: #f2f5fa;
            --update-panel: rgba(255, 255, 255, .74);
            --update-border: rgba(53, 71, 101, .16);
            --update-muted: #5d6b80;
            --update-accent: #ef5b4a;
            --update-accent-2: #e83d70;
        }

        * { box-sizing: border-box; }

        html,
        body { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100svh;
            overflow-x: hidden;
            color: #f7f9fc;
            background: var(--update-bg);
            font-family: "FIREBALL Inter", Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        [data-bs-theme="light"] body {
            color: #172033;
            background: var(--update-bg);
        }

        .update-page {
            position: relative;
            display: flex;
            min-height: 100svh;
            align-items: center;
            justify-content: center;
            padding: calc(2.5rem + env(safe-area-inset-top, 0px)) 1.25rem calc(2.5rem + env(safe-area-inset-bottom, 0px));
            isolation: isolate;
        }

        .update-page::before,
        .update-page::after {
            position: fixed;
            z-index: -1;
            width: min(48rem, 80vw);
            height: min(48rem, 80vw);
            border-radius: 50%;
            content: "";
            filter: blur(8px);
            opacity: .44;
            pointer-events: none;
        }

        .update-page::before {
            top: -24rem;
            left: -13rem;
            background: radial-gradient(circle, rgba(61, 133, 255, .38), transparent 68%);
        }

        .update-page::after {
            right: -19rem;
            bottom: -25rem;
            background: radial-gradient(circle, rgba(239, 63, 112, .28), transparent 68%);
        }

        .update-shell {
            width: min(100%, 34rem);
            text-align: center;
        }

        .update-brand {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: 2rem;
            color: #fff;
            font-size: clamp(1.05rem, 2vw, 1.3rem);
            font-weight: 750;
            letter-spacing: .08em;
            text-decoration: none;
            text-transform: uppercase;
        }

        [data-bs-theme="light"] .update-brand { color: #172033; }

        .update-brand__mark {
            display: inline-flex;
            width: 2.5rem;
            height: 2.5rem;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: .85rem;
            color: #fff;
            background: linear-gradient(145deg, var(--update-accent), var(--update-accent-2));
            box-shadow: 0 .6rem 1.4rem rgba(239, 63, 112, .28), inset 0 1px 0 rgba(255, 255, 255, .26);
            font-size: 1.2rem;
        }

        .update-card {
            position: relative;
            overflow: hidden;
            padding: clamp(2rem, 6vw, 3.5rem) clamp(1.5rem, 6vw, 3.25rem) 2rem;
            border: 1px solid var(--update-border);
            border-radius: 2rem;
            background: var(--update-panel);
            box-shadow: 0 2rem 5rem rgba(0, 0, 0, .28), inset 0 1px 0 rgba(255, 255, 255, .08);
            -webkit-backdrop-filter: saturate(170%) blur(24px);
            backdrop-filter: saturate(170%) blur(24px);
        }

        [data-bs-theme="light"] .update-card {
            box-shadow: 0 2rem 5rem rgba(45, 62, 91, .14), inset 0 1px 0 rgba(255, 255, 255, .72);
        }

        .update-card::before {
            position: absolute;
            top: 0;
            right: 12%;
            left: 12%;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--update-accent), var(--update-accent-2), transparent);
            box-shadow: 0 0 1.5rem rgba(255, 98, 80, .72);
            content: "";
        }

        .update-icon {
            display: inline-flex;
            width: 5rem;
            height: 5rem;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.75rem;
            border: 1px solid rgba(255, 255, 255, .17);
            border-radius: 1.5rem;
            color: #ff8d7d;
            background: rgba(255, 98, 80, .12);
            box-shadow: 0 1rem 2rem rgba(255, 98, 80, .12), inset 0 1px 0 rgba(255, 255, 255, .08);
            font-size: 2rem;
        }

        .update-icon i {
            display: inline-block;
            animation: update-spin 1.8s linear infinite;
        }

        .update-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: 1rem;
            color: #ff9c90;
            font-size: .78rem;
            font-weight: 750;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .update-kicker::before {
            width: .45rem;
            height: .45rem;
            border-radius: 50%;
            background: #43d28a;
            box-shadow: 0 0 .7rem rgba(67, 210, 138, .85);
            content: "";
        }

        h1 {
            margin: 0;
            font-size: clamp(1.8rem, 5vw, 2.65rem);
            font-weight: 760;
            letter-spacing: -.045em;
            line-height: 1.1;
        }

        .update-message {
            max-width: 27rem;
            margin: 1rem auto 0;
            color: var(--update-muted);
            font-size: clamp(1rem, 2.5vw, 1.12rem);
            line-height: 1.65;
        }

        .update-progress {
            height: .45rem;
            margin: 2rem 0 .85rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(154, 168, 188, .16);
        }

        .update-progress::before {
            display: block;
            width: 42%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--update-accent), var(--update-accent-2));
            box-shadow: 0 0 .9rem rgba(255, 98, 80, .55);
            content: "";
            animation: update-progress 2.2s ease-in-out infinite;
        }

        .update-hint {
            margin: 0;
            color: rgba(154, 168, 188, .76);
            font-size: .86rem;
        }

        .update-refresh {
            display: inline-flex;
            min-height: 3.1rem;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            margin-top: 1.7rem;
            padding: .75rem 1.35rem;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 999px;
            color: #fff;
            background: rgba(255, 255, 255, .06);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .08);
            font: inherit;
            font-weight: 650;
            text-decoration: none;
            transition: border-color .2s ease, background-color .2s ease, transform .2s ease;
        }

        .update-refresh:hover,
        .update-refresh:focus-visible {
            border-color: rgba(255, 141, 125, .75);
            background: rgba(255, 98, 80, .16);
            transform: translateY(-1px);
        }

        [data-bs-theme="light"] .update-refresh {
            border-color: rgba(53, 71, 101, .2);
            color: #172033;
            background: rgba(255, 255, 255, .45);
        }

        [data-bs-theme="light"] .update-refresh:hover,
        [data-bs-theme="light"] .update-refresh:focus-visible {
            border-color: rgba(214, 69, 97, .5);
            background: rgba(239, 91, 74, .1);
        }

        .update-footer {
            margin: 1.5rem 0 0;
            color: rgba(154, 168, 188, .6);
            font-size: .76rem;
        }

        [data-bs-theme="light"] .update-footer { color: rgba(93, 107, 128, .72); }

        @keyframes update-spin { to { transform: rotate(360deg); } }
        @keyframes update-progress {
            0% { transform: translateX(-115%); }
            55%, 100% { transform: translateX(255%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .update-icon i,
            .update-progress::before { animation: none; }
        }

        @media (max-width: 480px) {
            .update-page { padding-inline: .85rem; }
            .update-card { border-radius: 1.5rem; }
            .update-brand { margin-bottom: 1.25rem; }
        }
    </style>
</head>
<body>
    <main class="update-page">
        <div class="update-shell">
            <a class="update-brand" href="<?= htmlSC(base_href('/')) ?>" aria-label="<?= htmlSC($siteTitle) ?>">
                <span class="update-brand__mark" aria-hidden="true"><i class="ci-refresh-cw"></i></span>
                <span><?= htmlSC($siteTitle) ?></span>
            </a>

            <section class="update-card" aria-labelledby="update-title">
                <div class="update-icon" aria-hidden="true"><i class="ci-refresh-cw"></i></div>
                <div class="update-kicker"><?= print_translation('update_maintenance_status') ?></div>
                <h1 id="update-title"><?= htmlSC($pageTitle) ?></h1>
                <p class="update-message"><?= print_translation('update_maintenance_message') ?></p>
                <div class="update-progress" role="progressbar" aria-label="<?= htmlSC(return_translation('update_maintenance_status')) ?>"></div>
                <p class="update-hint"><?= print_translation('update_maintenance_hint') ?></p>
                <a class="update-refresh" href="<?= htmlSC(base_href('/')) ?>">
                    <i class="ci-refresh-cw" aria-hidden="true"></i>
                    <?= print_translation('update_maintenance_refresh') ?>
                </a>
                <p class="update-footer"><?= htmlSC($siteTitle) ?></p>
            </section>
        </div>
    </main>
    <script>
        window.setTimeout(function () { window.location.reload(); }, <?= $retryAfter * 1000 ?>);
    </script>
</body>
</html>
