<?php
declare(strict_types=1);
require __DIR__ . '/components/icons.php';
$config = \Fireball\ReelPlayer\Services\PlatformAssets::privateConfig($config);
$appearance_css = \Fireball\ReelPlayer\Services\PlatformAssets::url('public.css');
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
            if (navigator.standalone === true || matchMedia('(display-mode: standalone)').matches) document.documentElement.classList.add('pwa-standalone');
            document.querySelector('meta[name="color-scheme"]').content = theme;
            document.querySelectorAll('meta[name="theme-color"]').forEach(meta => { meta.content = theme === 'dark' ? '#191c1a' : '#f7f5f0'; });
        })();
    </script>
    <link rel="stylesheet" href="<?= htmlSC($css_url) ?>">
    <?php if ($appearance_css !== ''): ?><link rel="stylesheet" href="<?= htmlSC($appearance_css) ?>"><?php endif; ?>
</head>
<body class="rp-body">
<?php require __DIR__ . '/components/symbols.php'; ?>
<main class="rp-app" data-player>
    <header class="rp-header">
        <a class="rp-back" href="<?= htmlSC(base_href('/admin')) ?>" aria-label="Вернуться в CMS" title="Вернуться в CMS"><span class="rp-icon-button rp-back-control"><?= $icon('back') ?></span><span class="rp-back-label">FIREBALL</span></a>
        <div class="rp-brand">TAPE ROOM<span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></div>
        <div class="rp-header-actions">
            <?= !empty($config['drive']['canManage']) ? $button('player-settings', 'Настройки плеера', 'settings') : $button('appearance', 'Внешний вид плеера', 'settings') ?>
            <?= $button('theme', 'Включить тёмную тему', 'moon') ?>
        </div>
    </header>
    <?php require __DIR__ . '/components/reel.php'; ?>
    <?php require __DIR__ . '/components/chapters.php'; ?>
    <section class="rp-library rp-library--catalog" aria-label="Музыкальная библиотека">
        <aside class="rp-playlists">
            <div class="rp-catalog-nav-heading">Библиотека</div>
            <nav class="rp-catalog-nav" data-catalog-nav aria-label="Музыкальный каталог">
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="overview"><?= $icon('music') ?><span>Обзор</span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="albums"><?= $icon('folder') ?><span>Альбомы</span><span class="rp-catalog-nav-count" data-catalog-album-count></span></button>
                <button type="button" class="rp-catalog-nav-button" data-action="catalog-view" data-view="artists"><?= $icon('microphone') ?><span>Артисты</span></button>
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
    <audio data-audio data-player-native preload="auto" playsinline></audio>
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
<?php foreach (['media-session.js','playback-core.js','preferences.js','compact.js','platform-admin.js'] as $module): $module_url = \Fireball\ReelPlayer\Services\PlatformAssets::url($module); if ($module_url !== ''): ?>
<script src="<?= htmlSC($module_url) ?>" defer></script>
<?php endif; endforeach; ?>
<script src="<?= htmlSC($js_url) ?>" defer></script>
</body>
</html>
