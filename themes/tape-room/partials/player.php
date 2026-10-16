<?php
declare(strict_types=1);
$iconMap = require dirname(__DIR__).'/includes/icon-map.php';
$icon = static function (string $name, string $class = '') use ($iconMap): string {
    return '<i class="rp-icon ci-' . htmlSC($iconMap[$name] ?? 'music') . ' ' . htmlSC($class) . '" data-icon="' . htmlSC($name) . '" aria-hidden="true"></i>';
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

<main class="rp-app tr-bootstrap" data-player data-tr-shell data-bs-theme="light">
    <header class="rp-header">
        <a class="rp-back" href="<?= htmlSC($back_url) ?>" aria-label="Вернуться в CMS" title="Вернуться в CMS"><span class="rp-icon-button rp-back-control"><?= $icon('back') ?></span><span class="rp-back-label">FIREBALL</span></a>
        <div class="rp-brand"><b class="rp-brand-name">TAPE ROOM<small class="rp-beta">БЕТА</small></b><span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></div>
        <div class="rp-header-actions">
            <a class="rp-icon-button" href="<?= htmlSC(base_href('/profile')) ?>" aria-label="Аккаунт" title="Аккаунт"><?= $icon('user') ?></a>
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
                    <label class="rp-seek-wrap tr-playback-timeline"><span class="rp-sr-only">Позиция воспроизведения</span><input class="rp-range rp-seek tr-timeline-range" data-seek type="range" min="0" max="1000" value="0" disabled></label>
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
                <div class="rp-library-actions"><div data-playlist-actions hidden><?= $button('rename-playlist', 'Переименовать плейлист', 'edit') ?><?= $button('delete-playlist', 'Удалить плейлист', 'trash') ?></div><button type="button" class="rp-button rp-catalog-create" data-action="catalog-create" data-catalog-create hidden><?= $icon('plus') ?><span>Создать альбом</span></button><button type="button" class="rp-upload-button" data-action="<?= empty($config['authenticated'])?'auth':'upload' ?>"><?= $icon(empty($config['authenticated'])?'login':'plus') ?><span><?= empty($config['authenticated'])?'Войти, чтобы добавить музыку':'Добавить музыку' ?></span></button></div>
            </div>
            <div class="rp-library-filters">
                <label class="rp-search"><?= $icon('search') ?><input type="search" data-search placeholder="Найти трек или исполнителя" aria-label="Найти трек или исполнителя"></label>
                <div class="rp-sort dropdown" data-sort>
                    <button type="button" class="rp-sort-trigger" data-sort-trigger aria-label="Сортировка музыки: сначала новые" aria-haspopup="listbox" aria-expanded="false" aria-controls="rp-sort-options"><span data-sort-label>Сначала новые</span><?= $icon('down') ?></button>
                    <div class="rp-sort-options dropdown-menu" id="rp-sort-options" data-sort-options role="listbox" aria-label="Сортировка музыки" hidden></div>
                </div>
            </div>
            <div class="rp-catalog-content" data-catalog-content hidden></div>
            <div class="rp-track-list" data-tracks></div>
            <div class="rp-empty" data-empty>
                <div class="rp-empty-icon"><?= $icon('music') ?></div><h3 data-empty-title>Здесь начинается ваша коллекция</h3><p data-empty-description>Перетащите аудиофайлы сюда или добавьте их кнопкой выше.</p><span>MP3 · FLAC · WAV · M4A · OGG · OPUS · AAC · WEBM</span>
            </div>
            <div class="rp-drop-hint" <?= empty($config['authenticated'])?'hidden':'' ?>>Перетащите музыку в эту область · <span data-upload-limit></span> на файл</div>
        </div>
    </section>
    <footer class="rp-footer"><button type="button" class="rp-about" data-action="meter-report" aria-label="Проверить индикаторы и версию плеера">TAPE ROOM · <?= htmlSC($config['version'] ?? '') ?></button><span>Пробел — пауза · ← / → — перемотка · Shift + ← / → — глава / трек</span><span>СОЗДАНО ДЛЯ ПРОСЛУШИВАНИЯ</span></footer>
    <div class="rp-drop-overlay" hidden data-drop-overlay><?= $icon('upload') ?><strong>Положите музыку на ленту</strong><span>Файлы добавятся в открытый плейлист</span></div>
    <div class="rp-upload-progress" hidden data-upload-progress><span data-upload-message></span><progress max="100" value="0" aria-label="Загрузка музыки"></progress></div>
    <div class="toast-container position-fixed bottom-0 end-0 p-3"><div class="toast align-items-center" role="status" aria-live="polite" aria-atomic="true" data-toast><div class="d-flex"><div class="toast-body" data-toast-text></div><button class="btn-close m-auto me-2" type="button" data-bs-dismiss="toast" aria-label="Закрыть уведомление"><?= $icon('close') ?></button></div></div></div>
    <audio data-audio preload="auto" playsinline></audio>
    <input class="rp-sr-only" tabindex="-1" aria-hidden="true" type="file" data-audio-files accept=".mp3,.wav,.flac,.m4a,.ogg,.opus,.aac,.webm" multiple>
    <div class="modal fade" data-player-modal tabindex="-1" aria-labelledby="rp-dialog-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content rp-dialog" data-dialog>
        <form data-dialog-form>
            <div class="modal-header rp-dialog-header"><h2 class="modal-title" id="rp-dialog-title" data-dialog-title></h2><button class="btn-close" type="button" data-action="close-dialog" aria-label="Закрыть"><?= $icon('close') ?></button></div>
            <div class="modal-body"><p class="small text-body-secondary" data-dialog-description hidden></p><div data-dialog-fields></div><p class="alert alert-danger mt-3" role="alert" data-dialog-error hidden></p></div>
            <div class="modal-footer rp-dialog-buttons"><button class="btn btn-outline-secondary rp-button" type="button" data-action="close-dialog">Отмена</button><button class="btn btn-primary rp-button rp-button--primary" type="submit" data-dialog-submit>Сохранить</button></div>
        </form>
    </div></div></div>
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
<script src="<?= htmlSC(theme_asset_versioned('js/media-recovery.js')) ?>" defer></script>
<script src="<?= htmlSC($js_url) ?>" defer></script>
</body>
</html>
