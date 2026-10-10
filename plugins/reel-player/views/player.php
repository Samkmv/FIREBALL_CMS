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
    <title>Tape Room — личная музыкальная комната</title>
    <script>
        (() => {
            let theme = 'light';
            try { if (localStorage.getItem(<?= json_encode($config['storageKey'] . ':theme', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>) === 'dark') theme = 'dark'; } catch (_) {}
            document.documentElement.dataset.theme = theme;
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
    <symbol id="rp-i-heart" viewBox="0 0 24 24"><path d="M20.3 5.4a5.3 5.3 0 0 0-7.5 0L12 6.2l-.8-.8a5.3 5.3 0 0 0-7.5 7.5L12 21l8.3-8.1a5.3 5.3 0 0 0 0-7.5Z"/></symbol>
    <symbol id="rp-i-settings" viewBox="0 0 24 24"><path d="m10 3-.5 2-2 .9-1.8-.6-2 3.4 1.4 1.5v2.3L3.7 14l2 3.4 1.8-.6 2 .9.5 2.3h4l.5-2.3 2-.9 1.8.6 2-3.4-1.4-1.5v-2.3l1.4-1.5-2-3.4-1.8.6-2-.9L14 3z"/><circle cx="12" cy="11.5" r="3"/></symbol>
</defs></svg>
<main class="rp-app" data-player>
    <header class="rp-header">
        <a class="rp-back" href="<?= htmlSC(base_href('/admin')) ?>" aria-label="Вернуться в CMS" title="Вернуться в CMS"><span class="rp-icon-button rp-back-control"><?= $icon('back') ?></span><span class="rp-back-label">FIREBALL</span></a>
        <div class="rp-brand">TAPE ROOM<span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></div>
        <div class="rp-header-actions">
            <?php if (!empty($config['drive']['canManage'])): ?><?= $button('player-settings', 'Настройки плеера', 'settings') ?><?php endif; ?>
            <?= $button('theme', 'Включить тёмную тему', 'moon') ?>
        </div>
    </header>
    <section class="rp-machine" aria-label="Катушечный аудиоплеер">
        <div class="rp-machine-caption"><span class="rp-status-dot"></span><span data-play-status>ГОТОВ К ПРОСЛУШИВАНИЮ</span><span class="rp-model">STEREO / 01</span></div>
        <div class="rp-deck">
            <svg class="rp-deck-svg" viewBox="0 0 1200 460" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="rp-metal" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a8a5a0"/><stop offset=".18" stop-color="#f5f3ee"/><stop offset=".34" stop-color="#c2beb7"/><stop offset=".51" stop-color="#faf8f3"/><stop offset=".72" stop-color="#b8b4ad"/><stop offset=".89" stop-color="#eeeae3"/><stop offset="1" stop-color="#a8a39b"/></linearGradient>
                    <linearGradient id="rp-hub" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#3f4140"/><stop offset="1" stop-color="#161918"/></linearGradient>
                    <radialGradient id="rp-tape"><stop stop-color="#29231e"/><stop offset=".65" stop-color="#40362b"/><stop offset="1" stop-color="#26231f"/></radialGradient>
                    <radialGradient id="rp-glass"><stop offset=".65" stop-color="#faf8f3" stop-opacity="0"/><stop offset=".9" stop-color="#d2cdc3" stop-opacity=".16"/><stop offset="1" stop-color="#e5e0d7" stop-opacity=".7"/></radialGradient>
                    <filter id="rp-shadow" x="-30%" y="-20%" width="170%" height="165%"><feDropShadow dx="6" dy="17" stdDeviation="12" flood-color="#6a5b42" flood-opacity=".21"/></filter>
                    <mask id="rp-tape-visible" maskUnits="userSpaceOnUse" x="0" y="0" width="1200" height="460">
                        <rect width="1200" height="460" fill="white"/>
                        <circle cx="230" cy="210" r="198" fill="black"/>
                        <circle cx="970" cy="210" r="198" fill="black"/>
                    </mask>
                </defs>
                <g mask="url(#rp-tape-visible)">
                    <path data-tape-left d="M168.01 376.86 L368.25 451.25 A28 28 0 0 0 406 425 L378 425" class="rp-tape-line"/>
                    <path data-tape-right d="M1026.46 269.46 L841.28 445.30 A28 28 0 0 1 794 425 L822 425" class="rp-tape-line"/>
                </g>
                <?php foreach (['left' => 20, 'right' => 760] as $side => $x): ?>
                <svg x="<?= $x ?>" y="0" width="420" height="420" viewBox="0 0 420 420" class="rp-reel" data-reel="<?= $side ?>">
                    <defs><clipPath id="rp-pack-<?= $side ?>"><circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" data-pack-clip="<?= $side ?>"/></clipPath></defs>
                    <circle cx="210" cy="210" r="197" fill="url(#rp-glass)" stroke="#b4afa6" stroke-width="1.5" filter="url(#rp-shadow)"/>
                    <circle cx="210" cy="210" r="<?= $side === 'left' ? 174 : 78 ?>" fill="url(#rp-tape)" data-pack="<?= $side ?>"/>
                    <g clip-path="url(#rp-pack-<?= $side ?>)" opacity=".45"><?php for ($r = 80; $r < 180; $r += 2): ?><circle cx="210" cy="210" r="<?= $r ?>" fill="none" stroke="<?= $r % 4 ? '#645747' : '#151512' ?>" stroke-width=".6"/><?php endfor; ?></g>
                    <g class="rp-rotor" data-rotor="<?= $side ?>">
                        <circle cx="210" cy="210" r="190" fill="none" stroke="url(#rp-metal)" stroke-width="11" opacity=".84"/>
                        <?php
                        for ($angle = -86; $angle < 240; $angle += 120) {
                            $p = static fn(float $r, float $a): string => round(210 + $r * cos(deg2rad($a)), 2) . ',' . round(210 + $r * sin(deg2rad($a)), 2);
                            $a = $angle; $b = $angle + 37;
                            $d = 'M' . $p(70, $a) . ' L' . $p(182, $a + 5) . ' A182,182 0 0,1 ' . $p(182, $b + 5) . ' L' . $p(70, $b) . ' A70,70 0 0,0 ' . $p(70, $a) . 'Z';
                        ?><path d="<?= $d ?>" fill="url(#rp-metal)" stroke="#e4e0d8" stroke-width="1" opacity=".95"/><?php } ?>
                        <circle cx="210" cy="210" r="74" fill="url(#rp-metal)" stroke="#77756e" stroke-width="1.5"/>
                        <circle cx="210" cy="210" r="65" fill="url(#rp-hub)" stroke="#0e100e" stroke-width="2"/>
                        <circle cx="210" cy="210" r="68" fill="none" stroke="#f3f0e9" stroke-width="1.2"/>
                        <?php foreach ([0, 120, 240] as $angle): ?><g transform="rotate(<?= $angle ?> 210 210)"><rect x="204" y="134" width="12" height="6" rx="2" fill="#8c8880" stroke="#282825" stroke-width="1"/><circle cx="210" cy="281" r="1.8" fill="#161916"/></g><?php endforeach; ?>
                    </g>
                    <circle cx="210" cy="210" r="195" fill="none" stroke="#fffefa" stroke-opacity=".9" stroke-width="2"/>
                </svg>
                <?php endforeach; ?>
                <?php foreach ([378, 822] as $x): ?><g class="rp-roller"><circle cx="<?= $x ?>" cy="425" r="28" fill="#252623" stroke="#9b978d" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="22" fill="url(#rp-metal)" stroke="#d7d1c7" stroke-width="2"/><circle cx="<?= $x ?>" cy="425" r="3" fill="#8e897e"/></g><?php endforeach; ?>
            </svg>
            <div class="rp-now-playing">
                <button type="button" class="rp-cover-button" data-action="edit-current" title="Изменить название и обложку" aria-label="Изменить название и обложку">
                    <img data-cover src="<?= htmlSC($config['defaultCover']) ?>" alt="Обложка текущей композиции" width="280" height="280">
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
                <label class="rp-seek-wrap"><span class="rp-sr-only">Позиция воспроизведения</span><input class="rp-range rp-seek" data-seek type="range" min="0" max="1000" value="0" disabled></label>
                <div class="rp-times"><span data-elapsed>0:00</span><span data-duration>0:00</span></div>
                <div class="rp-play-controls"><?= $button('prev', 'Предыдущий трек', 'prev') ?><?= $button('play', 'Воспроизвести', 'play', 'rp-play') ?><?= $button('next', 'Следующий трек', 'next') ?></div>
            </div>
        </div>
        <div class="rp-secondary-controls">
            <div class="rp-playback-options"><?= $button('shuffle', 'Случайный порядок: выключен', 'shuffle') ?><span class="rp-repeat-wrap"><?= $button('repeat', 'Повтор: выключен', 'repeat') ?><span data-repeat-one hidden>1</span></span></div>
            <span class="rp-source" data-source>ВАША МУЗЫКА. ВАШ РИТМ.</span>
            <div class="rp-volume"><?= $button('mute', 'Выключить звук', 'volume') ?><label><span class="rp-sr-only">Громкость</span><input type="range" class="rp-range" data-volume min="0" max="1" step="0.01" value="0.8"></label></div>
        </div>
    </section>
    <section class="rp-library" aria-label="Музыкальная библиотека">
        <aside class="rp-playlists">
            <div class="rp-section-heading"><h2>Мои ленты</h2><?= $button('create-playlist', 'Создать плейлист', 'plus') ?></div>
            <nav data-playlists aria-label="Плейлисты"></nav>
            <button class="rp-upload-button rp-drive-button" type="button" data-action="drive"><?= $icon('music') ?><span>Google Drive</span></button>
            <div class="rp-private-note"><span class="rp-status-dot"></span>Только для вас<span>Музыка хранится в вашей CMS</span></div>
        </aside>
        <div class="rp-track-panel">
            <div class="rp-library-toolbar">
                <div class="rp-list-heading"><h2 data-list-title>Вся музыка</h2><span data-list-summary>0 треков</span></div>
                <div class="rp-library-actions"><div data-playlist-actions hidden><?= $button('rename-playlist', 'Переименовать плейлист', 'edit') ?><?= $button('delete-playlist', 'Удалить плейлист', 'trash') ?></div><button type="button" class="rp-upload-button" data-action="upload"><?= $icon('plus') ?><span>Добавить музыку</span></button></div>
            </div>
            <div class="rp-library-filters">
                <label class="rp-search"><?= $icon('search') ?><input type="search" data-search placeholder="Найти трек или исполнителя" aria-label="Найти трек или исполнителя"></label>
                <div class="rp-sort" data-sort>
                    <button type="button" class="rp-sort-trigger" data-sort-trigger aria-label="Сортировка музыки: сначала новые" aria-haspopup="listbox" aria-expanded="false" aria-controls="rp-sort-options"><span data-sort-label>Сначала новые</span><?= $icon('down') ?></button>
                    <div class="rp-sort-options" id="rp-sort-options" data-sort-options role="listbox" aria-label="Сортировка музыки" hidden></div>
                </div>
            </div>
            <div class="rp-track-list" data-tracks></div>
            <div class="rp-empty" data-empty>
                <div class="rp-empty-icon"><?= $icon('music') ?></div><h3 data-empty-title>Здесь начинается ваша коллекция</h3><p data-empty-description>Перетащите аудиофайлы сюда или добавьте их кнопкой выше.</p><span>MP3 · FLAC · WAV · M4A · OGG · OPUS · AAC · WEBM</span>
            </div>
            <div class="rp-drop-hint">Перетащите музыку в эту область · <span data-upload-limit></span> на файл</div>
        </div>
    </section>
    <footer class="rp-footer"><span>TAPE ROOM</span><span>Пробел — пауза · ← / → — перемотка · Shift + ← / → — трек</span><span>СОЗДАНО ДЛЯ ПРОСЛУШИВАНИЯ</span></footer>
    <div class="rp-drop-overlay" hidden data-drop-overlay><?= $icon('upload') ?><strong>Положите музыку на ленту</strong><span>Файлы добавятся в открытый плейлист</span></div>
    <div class="rp-upload-progress" hidden data-upload-progress><span data-upload-message></span><progress max="100" value="0"></progress></div>
    <div class="rp-toast" role="status" data-toast hidden></div>
    <audio data-audio preload="auto" playsinline></audio>
    <input class="rp-sr-only" tabindex="-1" aria-hidden="true" type="file" data-audio-files accept=".mp3,.wav,.flac,.m4a,.ogg,.opus,.aac,.webm" multiple>
    <dialog class="rp-dialog" data-dialog>
        <form data-dialog-form>
            <div class="rp-dialog-header"><h2 data-dialog-title></h2><?= $button('close-dialog', 'Закрыть', 'close') ?></div>
            <p data-dialog-description hidden></p>
            <div data-dialog-fields></div>
            <p class="rp-dialog-error" role="alert" data-dialog-error hidden></p>
            <div class="rp-dialog-buttons"><button class="rp-button" type="button" data-action="close-dialog">Отмена</button><button class="rp-button rp-button--primary" type="submit" data-dialog-submit>Сохранить</button></div>
        </form>
    </dialog>
</main>
<script type="application/json" data-player-config><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= htmlSC($preload_url) ?>" defer></script>
<script src="<?= htmlSC($js_url) ?>" defer></script>
</body>
</html>
