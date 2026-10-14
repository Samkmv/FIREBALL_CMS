<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Tape Room',
    'subtitle' => 'Обзор аудиоплеера',
]) ?>
<div class="border rounded-5 p-3 p-md-4">
    <h2 class="h5 mb-3">Музыкальная комната</h2>
    <p class="text-body-secondary">Плеер, каталог альбомов, артисты и личные коллекции доступны на главной странице сайта после входа.</p>
    <?php if ($theme_active): ?>
        <a class="btn btn-dark rounded-pill" href="<?= htmlSC(base_href('/')) ?>">Открыть Tape Room</a>
    <?php else: ?>
        <p class="text-body-secondary">Выберите тему Tape Room, чтобы открыть музыкальную комнату на главной странице.</p>
        <a class="btn btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/themes')) ?>">Перейти к темам</a>
    <?php endif; ?>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
