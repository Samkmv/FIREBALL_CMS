<?php
declare(strict_types=1);
$icon = static function (string $name, string $class = ''): string {
    return '<svg class="rp-icon ' . htmlSC($class) . '" aria-hidden="true"><use href="#rp-i-' . htmlSC($name) . '"></use></svg>';
};
$button = static function (string $action, string $label, string $name, string $class = '') use ($icon): string {
    return '<button type="button" class="rp-icon-button ' . $class . '" data-action="' . $action . '" aria-label="' . htmlSC($label) . '" title="' . htmlSC($label) . '">' . $icon($name) . '</button>';
};
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <?= $pwa_head ?? '' ?>
    <meta name="theme-color" content="#f7f5f0">
    <title>Tape Room · Бета — личная музыкальная комната</title>
    <script>
        (() => {
            let theme = 'light';
            try { if (localStorage.getItem(<?= json_encode($config['storageKey'] . ':theme', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>) === 'dark') theme = 'dark'; } catch (_) {}
            document.documentElement.dataset.theme = theme;
            if (navigator.standalone === true || matchMedia('(display-mode: standalone)').matches) document.documentElement.classList.add('pwa-standalone');
            document.querySelector('meta[name="color-scheme"]').content = theme;
            document.querySelectorAll('meta[name="theme-color"]').forEach(meta => { meta.content = theme === 'dark' ? '#191c1a' : '#f7f5f0'; });
        })();
    </script>
    <link rel="stylesheet" href="<?= htmlSC($css_url) ?>">
</head>
<body class="rp-body">
<svg class="rp-symbols" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
    <symbol id="rp-i-play" viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z" fill="currentColor" stroke="none"/></symbol>
    <symbol id="rp-i-pause" viewBox="0 0 24 24"><path d="M8 5v14M16 5v14" stroke-width="3.5"/></symbol>
    <symbol id="rp-i-prev" viewBox="0 0 24 24"><path d="M5 5v14M19 5 7 12l12 7z" fill="currentColor"/></symbol>
    <symbol id="rp-i-next" viewBox="0 0 24 24"><path d="M19 5v14M5 5l12 7-12 7z" fill="currentColor"/></symbol>
    <symbol id="rp-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="rp-i-upload" viewBox="0 0 24 24"><path d="M12 15V3m-5 5 5-5 5 5M4 15v5h16v-5"/></symbol>
    <symbol id="rp-i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
    <symbol id="rp-i-shuffle" viewBox="0 0 24 24"><path d="M3 6h3c4 0 7 12 11 12h4m-4-4 4 4-4 4M3 18h3c1.5 0 3-2 4-4m4-4c1-2 2-4 4-4h3m-4-4 4 4-4 4"/></symbol>
    <symbol id="rp-i-repeat" viewBox="0 0 24 24"><path d="M4 10V8a3 3 0 0 1 3-3h13m-4-4 4 4-4 4M20 14v2a3 3 0 0 1-3 3H4m4-4-4 4 4 4"/></symbol>
    <symbol id="rp-i-volume" viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4zM17 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/></symbol>
    <symbol id="rp-i-mute" viewBox="0 0 24 24"><path d="M4 9h4l5-4v14l-5-4H4zM17 9l5 6m0-6-5 6"/></symbol>
    <symbol id="rp-i-list" viewBox="0 0 24 24"><path d="M8 5h13M8 12h13M8 19h13M3 5h.1M3 12h.1M3 19h.1"/></symbol>
    <symbol id="rp-i-folder" viewBox="0 0 24 24"><path d="M3 7V5a1 1 0 0 1 1-1h5l2 3h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="rp-i-edit" viewBox="0 0 24 24"><path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-4-4L5 15z"/></symbol>
    <symbol id="rp-i-trash" viewBox="0 0 24 24"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></symbol>
    <symbol id="rp-i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
    <symbol id="rp-i-up" viewBox="0 0 24 24"><path d="m6 15 6-6 6 6"/></symbol>
    <symbol id="rp-i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="rp-i-back" viewBox="0 0 24 24"><path d="m10 5-7 7 7 7M3 12h18"/></symbol>
    <symbol id="rp-i-moon" viewBox="0 0 24 24"><path d="M20.9 13.2A9 9 0 0 1 10.8 3.1 9 9 0 1 0 20.9 13.2Z"/></symbol>
    <symbol id="rp-i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="rp-i-music" viewBox="0 0 24 24"><path d="M9 18V5l11-2v13M9 8l11-2"/><ellipse cx="6" cy="18" rx="3" ry="3"/><ellipse cx="17" cy="16" rx="3" ry="3"/></symbol>
    <symbol id="rp-i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></symbol>
    <symbol id="rp-i-microphone" viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="13" rx="4"/><path d="M5 10v1a7 7 0 0 0 14 0v-1M12 18v4M8 22h8"/></symbol>
    <symbol id="rp-i-disc" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><path d="M7 7a7 7 0 0 1 5-2m0 14a7 7 0 0 0 5-2"/></symbol>
    <symbol id="rp-i-trending" viewBox="0 0 24 24"><path d="m3 17 6-6 4 4 8-11M15 4h6v6"/></symbol>
    <symbol id="rp-i-heart" viewBox="0 0 24 24"><path d="M20.3 5.4a5.3 5.3 0 0 0-7.5 0L12 6.2l-.8-.8a5.3 5.3 0 0 0-7.5 7.5L12 21l8.3-8.1a5.3 5.3 0 0 0 0-7.5Z"/></symbol>
    <symbol id="rp-i-settings" viewBox="0 0 24 24"><path d="m10 3-.5 2-2 .9-1.8-.6-2 3.4 1.4 1.5v2.3L3.7 14l2 3.4 1.8-.6 2 .9.5 2.3h4l.5-2.3 2-.9 1.8.6 2-3.4-1.4-1.5v-2.3l1.4-1.5-2-3.4-1.8.6-2-.9L14 3z"/><circle cx="12" cy="11.5" r="3"/></symbol>
</defs></svg>
<main class="rp-app" data-player>
    <header class="rp-header">
        <a class="rp-back" href="<?= htmlSC($back_url) ?>" aria-label="Вернуться в CMS" title="Вернуться в CMS"><span class="rp-icon-button rp-back-control"><?= $icon('back') ?></span><span class="rp-back-label">FIREBALL</span></a>
        <div class="rp-brand"><b class="rp-brand-name">TAPE ROOM<small class="rp-beta">БЕТА</small></b><span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></div>
        <div class="rp-header-actions">
            <a class="rp-icon-button" href="<?= htmlSC(base_href('/profile')) ?>" aria-label="Аккаунт" title="Аккаунт"><?= $icon('user') ?></a>
            <?php if (!empty($config['drive']['canManage'])): ?><?= $button('player-settings', 'Настройки плеера', 'settings') ?><?php endif; ?>
            <?= $button('theme', 'Включить тёмную тему', 'moon') ?>
        </div>
    </header>
    <section class="rp-machine" aria-label="Катушечный аудиоплеер">
        <div class="rp-machine-caption"><span class="rp-status-dot"></span><span data-play-status>ГОТОВ К ПРОСЛУШИВАНИЮ</span><span class="rp-model" data-audio-quality>АУДИО</span></div>
        <div class="rp-deck">
            <svg class="rp-deck-svg" viewBox="0 0 1200 460" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="rp-metal" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a8a5a0"/><stop offset=".18" stop-color="#f5f3ee"/><stop offset=".34" stop-color="#c2beb7"/><stop offset=".51" stop-color="#faf8f3"/><stop offset=".72" stop-color="#b8b4ad"/><stop offset=".89" stop-color="#eeeae3"/><stop offset="1" stop-color="#a8a39b"/></linearGradient>
                    <linearGradient id="rp-clear-face" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#e7e3da" stop-opacity=".10"/><stop offset=".18" stop-color="#e7e3da" stop-opacity=".16"/><stop offset=".4" stop-color="#dedbd1" stop-opacity=".11"/><stop offset=".7" stop-color="#e2ded4" stop-opacity=".12"/><stop offset="1" stop-color="#e7e3da" stop-opacity=".14"/></linearGradient>
                    <linearGradient id="rp-clear-edge" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#fffef9" stop-opacity=".85"/><stop offset=".35" stop-color="#dedad0" stop-opacity=".35"/><stop offset=".7" stop-color="#88877e" stop-opacity=".48"/><stop offset="1" stop-color="#fffef9" stop-opacity=".75"/></linearGradient>
                    <linearGradient id="rp-hub" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#3f4140"/><stop offset="1" stop-color="#161918"/></linearGradient>
                    <radialGradient id="rp-tape"><stop stop-color="#211810"/><stop offset=".46" stop-color="#302419"/><stop offset=".75" stop-color="#3b2e21"/><stop offset="1" stop-color="#1f1812"/></radialGradient>
                    <radialGradient id="rp-glass"><stop offset=".65" stop-color="#e7e3da" stop-opacity=".04"/><stop offset=".9" stop-color="#d2cdc3" stop-opacity=".12"/><stop offset="1" stop-color="#e5e0d7" stop-opacity=".22"/></radialGradient>
                    <filter id="rp-shadow" x="-30%" y="-20%" width="170%" height="165%"><feDropShadow dx="6" dy="17" stdDeviation="12" flood-color="#6a5b42" flood-opacity=".21"/></filter>
                    <linearGradient id="rp-tape-feed-left" data-tape-fade="left" gradientUnits="userSpaceOnUse" x1="163.88" y1="372.85" x2="180.63" y2="379.43"><stop class="rp-tape-ink" stop-opacity="0"/><stop class="rp-tape-ink" offset=".55" stop-opacity=".75"/><stop class="rp-tape-ink" offset="1" stop-opacity="1"/></linearGradient>
                    <linearGradient id="rp-tape-feed-right" data-tape-fade="right" gradientUnits="userSpaceOnUse" x1="1027.24" y1="265.57" x2="1014.38" y2="278.16"><stop class="rp-tape-ink" stop-opacity="0"/><stop class="rp-tape-ink" offset=".55" stop-opacity=".75"/><stop class="rp-tape-ink" offset="1" stop-opacity="1"/></linearGradient>
                    <mask id="rp-tape-visible" maskUnits="userSpaceOnUse" x="0" y="0" width="1200" height="460">
                        <rect width="1200" height="460" fill="white"/>
                        <circle cx="230" cy="210" r="174" fill="black" data-tape-mask="left"/>
                        <circle cx="970" cy="210" r="78" fill="black" data-tape-mask="right"/>
                    </mask>
                </defs>
                <g mask="url(#rp-tape-visible)">
                    <path data-tape-left d="M160.49 365.07 C164.21 366.53 162.02 372.12 165.74 373.58 C199.30 386.77 232.87 405.95 266.43 419.14 C300.00 432.32 333.56 439.51 367.12 452.69 A29.75 29.75 0 0 0 407.75 425 L378 425" class="rp-tape-line" style="--rp-tape-stroke:url(#rp-tape-feed-left)"/>
                    <path data-tape-right d="M1027.33 257.08 C1024.47 259.88 1028.67 264.17 1025.81 266.97 C995.31 296.85 964.81 332.73 934.32 362.61 C903.82 392.49 873.32 416.37 842.82 446.25 A29.75 29.75 0 0 1 792.25 425 L822 425" class="rp-tape-line" style="--rp-tape-stroke:url(#rp-tape-feed-right)"/>
                </g>
                <?php foreach (['left' => 20, 'right' => 760] as $side => $x): ?>
                <svg x="<?= $x ?>" y="0" width="420" height="420" viewBox="0 0 420 420" class="rp-reel" data-reel="<?= $side ?>">
                    <defs>
                        <clipPath id="rp-pack-<?= $side ?>"><circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" data-pack-clip="<?= $side ?>"/></clipPath>
                        <mask id="rp-pack-annulus-<?= $side ?>" maskUnits="userSpaceOnUse" x="0" y="0" width="420" height="420"><circle cx="210" cy="210" r="180" fill="white"/><circle cx="210" cy="210" r="78" fill="black"/></mask>
                    </defs>
                    <circle cx="210" cy="210" r="197" fill="url(#rp-glass)" stroke="#b4afa6" stroke-width="1.5" filter="url(#rp-shadow)"/>
                    <circle cx="210" cy="210" r="78" fill="url(#rp-metal)" stroke="#817c70" stroke-width="1"/>
                    <g mask="url(#rp-pack-annulus-<?= $side ?>)">
                        <circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" fill="url(#rp-tape)" data-pack="<?= $side ?>"/>
                        <g clip-path="url(#rp-pack-<?= $side ?>)" opacity=".5"><?php for ($n = 0; $n < 88; $n++): ?><circle cx="210" cy="210" r="<?= 79 + $n * 1.08 ?>" fill="none" stroke="<?= $n % 3 ? '#6a5036' : '#15120e' ?>" stroke-width=".35"/><?php endfor; ?></g>
                    </g>
                </svg>
                <?php endforeach; ?>
                <?php foreach ([378, 822] as $x): ?><g class="rp-roller"><circle cx="<?= $x ?>" cy="425" r="28" fill="#252623" stroke="#9b978d" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="22" fill="url(#rp-metal)" stroke="#d7d1c7" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="3" fill="#8e897e"/></g><?php endforeach; ?>
            </svg>
            <?php foreach (['left','right'] as $side): ?>
                <!-- Clear reinforced arms rotate above the real tape annulus.
                     Face reflections and the single acrylic rim stay in the light. -->
                <div class="rp-rotor-mount rp-rotor-mount--<?= $side ?>" aria-hidden="true"><div class="rp-rotor" data-rotor="<?= $side ?>">
                    <svg viewBox="0 0 420 420" xmlns="http://www.w3.org/2000/svg">
                        <?php foreach ([22.5, 142.5, 262.5] as $angle): ?>
                        <path class="rp-reel-spoke" transform="rotate(<?= $angle ?> 210 210)" d="M187.79 143.62 L149.71 29.81 A190 190 0 0 1 270.29 29.81 L232.21 143.62 A70 70 0 0 0 187.79 143.62 Z" fill="#e8e3d7" fill-opacity=".09"/>
                        <?php endforeach; ?>
                        <circle cx="210" cy="210" r="74" fill="url(#rp-metal)" stroke="#77756e" stroke-width="1.5"/>
                        <circle cx="210" cy="210" r="65" fill="url(#rp-hub)" stroke="#0e100e" stroke-width="2"/>
                        <circle cx="210" cy="210" r="68" fill="none" stroke="#f3f0e9" stroke-width="1.2"/>
                        <?php foreach ([0, 120, 240] as $angle): ?><g transform="rotate(<?= $angle ?> 210 210)"><rect x="204" y="134" width="12" height="6" rx="2" fill="#8c8880" stroke="#282825" stroke-width="1"/><circle cx="210" cy="281" r="1.8" fill="#161916"/></g><?php endforeach; ?>
                    </svg>
                </div>
                    <svg class="rp-reel-rim" viewBox="0 0 420 420" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="210" cy="210" r="193" fill="url(#rp-clear-face)" stroke="url(#rp-clear-edge)" stroke-width="5"/>
                        <circle cx="210" cy="210" r="188" fill="none" stroke="#e6e3d8" stroke-opacity=".28" stroke-width=".8"/>
                        <circle cx="210" cy="210" r="195" fill="none" stroke="#fffefa" stroke-opacity=".65" stroke-width="1.2"/>
                    </svg>
                </div>
            <?php endforeach; ?>
            <div class="rp-now-playing">
                <button type="button" class="rp-cover-button" data-action="edit-current" title="Изменить название и обложку" aria-label="Изменить название и обложку">
                    <img class="rp-custom-cover" data-cover alt="Обложка текущей композиции" width="280" height="280">
                    <?php foreach (['light','dark'] as $coverTheme): $variants = $config['defaultCoverVariants'][$coverTheme] ?? []; ?>
                    <img class="rp-theme-cover rp-theme-cover--<?= $coverTheme ?>" data-theme-cover src="<?= htmlSC($variants[320] ?? $config['defaultCovers'][$coverTheme]) ?>" <?php if (!empty($variants[320]) && !empty($variants[640])): ?>srcset="<?= htmlSC($variants[320]) ?> 1x, <?= htmlSC($variants[640]) ?> 2x"<?php endif; ?> alt="Стандартная обложка" width="280" height="280" decoding="async" fetchpriority="<?= $coverTheme === 'light' ? 'high' : 'auto' ?>">
                    <?php endforeach; ?>
                    <span class="rp-cover-edit"><?= $icon('edit') ?></span>
                </button>
                <h1 data-title>Ваша первая лента</h1>
                <p data-artist>ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ</p>
            </div>
        </div>
        <div class="rp-transport">
            <?php foreach (['left' => 'L', 'right' => 'R'] as $side => $label): ?>
            <div class="rp-meter rp-meter--<?= $side ?>" aria-label="Уровень <?= $label === 'L' ? 'левого' : 'правого' ?> канала">
                <span class="rp-channel"><?= $label ?></span>
                <div class="rp-meter-body"><div class="rp-meter-bars" data-meter="<?= $side ?>"><?php for ($i=0; $i<15; $i++): ?><i style="--bar-height:<?= min(100, 44 + $i * 6) ?>%"></i><?php endfor; ?></div><div class="rp-meter-labels"><span>−20</span><span>−10</span><span>−5</span><span>0</span><span>+3</span></div></div>
            </div>
            <?php endforeach; ?>
            <div class="rp-main-controls">
                <div class="rp-timeline">
                    <label class="rp-seek-wrap"><span class="rp-sr-only">Позиция воспроизведения</span><input class="rp-range rp-seek" data-seek type="range" min="0" max="1000" value="0" disabled></label>
                    <div class="rp-chapter-markers" data-chapter-markers data-action="chapter-marker-seek" role="group" aria-label="Таймкоды треков в аудиофайле" hidden></div>
                </div>
                <div class="rp-times"><span data-elapsed>0:00</span><span data-duration>0:00</span></div>
                <p class="rp-chapter-current" data-chapter-current aria-live="polite" aria-atomic="true" hidden></p>
                <div class="rp-play-controls"><?= $button('prev', 'Предыдущий трек', 'prev') ?><?= $button('play', 'Воспроизвести', 'play', 'rp-play') ?><?= $button('next', 'Следующий трек', 'next') ?></div>
            </div>
        </div>
        <div class="rp-secondary-controls">
            <div class="rp-playback-options"><?= $button('shuffle', 'Случайный порядок: выключен', 'shuffle') ?><span class="rp-repeat-wrap"><?= $button('repeat', 'Повтор: выключен', 'repeat') ?><span data-repeat-one hidden>1</span></span></div>
            <span class="rp-source" data-source>ВАША МУЗЫКА. ВАШ РИТМ.</span>
            <div class="rp-volume"><?= $button('mute', 'Выключить звук', 'volume') ?><label><span class="rp-sr-only">Громкость</span><input type="range" class="rp-range" data-volume min="0" max="1" step="0.01" value="0.8"></label></div>
        </div>
        <div class="rp-meter-recovery" data-meter-recovery hidden><button type="button" class="rp-button" data-action="meters">Включить индикаторы</button></div>
    </section>
    <section class="rp-chapter-panel" data-chapter-panel aria-labelledby="rp-chapter-heading" hidden>
        <div class="rp-chapter-toolbar">
            <div class="rp-chapter-heading">
                <h2 id="rp-chapter-heading">Список треков</h2>
                <p class="rp-chapter-source"><span class="rp-sr-only">Запись: </span><span data-chapter-source></span></p>
            </div>
            <div class="rp-chapter-tools">
                <button type="button" class="rp-upload-button" data-action="chapter-add"><?= $icon('plus') ?><span>Добавить таймкод</span></button>
                <button type="button" class="rp-button" data-action="chapter-tools">CUE и автопоиск</button>
            </div>
        </div>
        <label class="rp-search rp-chapter-search"><?= $icon('search') ?><input type="search" data-chapter-search placeholder="Найти трек или исполнителя" aria-label="Найти трек внутри аудиофайла"></label>
        <div class="rp-chapter-list" data-chapter-list></div>
        <p class="rp-chapter-empty" data-chapter-empty hidden>Ничего не найдено. Попробуйте другое название или имя исполнителя.</p>
    </section>
    <section class="rp-library rp-library--catalog" aria-label="Музыкальная библиотека">
        <aside class="rp-playlists">
            <div class="rp-catalog-nav-heading">Библиотека</div>
            <nav class="rp-catalog-nav" data-catalog-nav aria-label="Музыкальный каталог">
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="overview"><?= $icon('music') ?><span>Обзор</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="albums"><?= $icon('folder') ?><span>Альбомы</span><span class="rp-catalog-nav-count" data-catalog-album-count></span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="artists"><?= $icon('microphone') ?><span>Артисты</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="genres"><?= $icon('list') ?><span>Жанры</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="new"><?= $icon('disc') ?><span>Новинки</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="popular"><?= $icon('trending') ?><span>Популярное</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="mine"><?= $icon('heart') ?><span>Моя музыка</span><span class="rp-catalog-nav-count" data-catalog-saved-count></span></button>
            </nav>
            <div class="rp-section-heading rp-personal-heading"><h2>Мои ленты</h2><?= $button('create-playlist', 'Создать плейлист', 'plus') ?></div>
            <nav data-playlists aria-label="Личные песни и плейлисты"></nav>
            <button class="rp-upload-button rp-drive-button" type="button" data-action="drive"><?= $icon('music') ?><span>Google Drive</span></button>
            <div class="rp-private-note"><span class="rp-status-dot"></span>Личная коллекция<span>Сохраняйте альбомы и свою музыку</span></div>
        </aside>
        <div class="rp-track-panel">
            <div class="rp-library-toolbar">
                <div class="rp-list-heading"><h2 data-list-title>Обзор</h2><span data-list-summary></span></div>
                <div class="rp-library-actions"><div data-playlist-actions hidden><?= $button('rename-playlist', 'Переименовать плейлист', 'edit') ?><?= $button('delete-playlist', 'Удалить плейлист', 'trash') ?></div><button type="button" class="rp-button rp-catalog-create" data-action="catalog-create" data-catalog-create hidden><?= $icon('plus') ?><span>Создать альбом</span></button><button type="button" class="rp-upload-button" data-action="upload"><?= $icon('plus') ?><span>Добавить музыку</span></button></div>
            </div>
            <div class="rp-library-filters">
                <label class="rp-search"><?= $icon('search') ?><input type="search" data-search placeholder="Найти трек или исполнителя" aria-label="Найти трек или исполнителя"></label>
                <div class="rp-sort" data-sort>
                    <button type="button" class="rp-sort-trigger" data-sort-trigger aria-label="Сортировка музыки: сначала новые" aria-haspopup="listbox" aria-expanded="false" aria-controls="rp-sort-options"><span data-sort-label>Сначала новые</span><?= $icon('down') ?></button>
                    <div class="rp-sort-options" id="rp-sort-options" data-sort-options role="listbox" aria-label="Сортировка музыки" hidden></div>
                </div>
            </div>
            <div class="rp-catalog-content" data-catalog-content hidden></div>
            <div class="rp-track-list" data-tracks></div>
            <div class="rp-empty" data-empty>
                <div class="rp-empty-icon"><?= $icon('music') ?></div><h3 data-empty-title>Здесь начинается ваша коллекция</h3><p data-empty-description>Перетащите аудиофайлы сюда или добавьте их кнопкой выше.</p><span>MP3 · FLAC · WAV · M4A · OGG · OPUS · AAC · WEBM</span>
            </div>
            <div class="rp-drop-hint">Перетащите музыку в эту область · <span data-upload-limit></span> на файл</div>
        </div>
    </section>
    <footer class="rp-footer"><button type="button" class="rp-about" data-action="meter-report" aria-label="Проверить индикаторы и версию плеера">TAPE ROOM · <?= htmlSC($config['version'] ?? '') ?></button><span>Пробел — пауза · ← / → — перемотка · Shift + ← / → — глава / трек</span><span>СОЗДАНО ДЛЯ ПРОСЛУШИВАНИЯ</span></footer>
    <div class="rp-drop-overlay" hidden data-drop-overlay><?= $icon('upload') ?><strong>Положите музыку на ленту</strong><span>Файлы добавятся в открытый плейлист</span></div>
    <div class="rp-upload-progress" hidden data-upload-progress><span data-upload-message></span><progress max="100" value="0" aria-label="Загрузка музыки"></progress></div>
    <div class="rp-toast" role="status" data-toast hidden></div>
    <audio data-audio preload="auto" playsinline></audio>
    <input class="rp-sr-only" tabindex="-1" aria-hidden="true" type="file" data-audio-files accept=".mp3,.wav,.flac,.m4a,.ogg,.opus,.aac,.webm" multiple>
    <dialog class="rp-dialog" data-dialog aria-labelledby="rp-dialog-title">
        <form data-dialog-form>
            <div class="rp-dialog-header"><h2 id="rp-dialog-title" data-dialog-title></h2><?= $button('close-dialog', 'Закрыть', 'close') ?></div>
            <p data-dialog-description hidden></p>
            <div data-dialog-fields></div>
            <p class="rp-dialog-error" role="alert" data-dialog-error hidden></p>
            <div class="rp-dialog-buttons"><button class="rp-button" type="button" data-action="close-dialog">Отмена</button><button class="rp-button rp-button--primary" type="submit" data-dialog-submit>Сохранить</button></div>
        </form>
    </dialog>
</main>
<script type="application/json" data-player-config><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= htmlSC($preload_url) ?>" defer></script>
<script src="<?= htmlSC($meters_url) ?>" defer></script>
<?php if (!empty($chapters_url)): ?><script src="<?= htmlSC($chapters_url) ?>" defer></script><?php endif; ?>
<script src="<?= htmlSC($transport_url) ?>" defer></script>
<script src="<?= htmlSC($quality_url) ?>" defer></script>
<script src="<?= htmlSC($collections_url) ?>" defer></script>
<script src="<?= htmlSC($images_url) ?>" defer></script>
<script src="<?= htmlSC($catalog_url) ?>" defer></script>
<script src="<?= htmlSC($genres_url) ?>" defer></script>
<script src="<?= htmlSC($js_url) ?>" defer></script>
</body>
</html>
