<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Tape Room',
    'subtitle' => 'Обзор аудиоплеера',
]) ?>
<?php require __DIR__.'/admin-tabs.php'; ?>
<div class="row g-3 mb-4">
<?php foreach ([['albums','Всего альбомов'],['submitted','Отправлено на публикацию'],['pending','На модерации'],['approved','Одобрено']] as [$key,$label]): ?>
<div class="col-6 col-xl-3"><div class="border rounded-4 p-3 h-100"><div class="small text-body-secondary"><?= htmlSC($label) ?></div><strong class="fs-2"><?= (int)($stats[$key] ?? 0) ?></strong></div></div>
<?php endforeach; ?>
</div>
<form class="d-flex flex-wrap gap-2 mb-4" method="get" action="<?= htmlSC(base_href('/admin/reel-player')) ?>">
<label class="visually-hidden" for="album-owner">Пользователь</label><select class="form-select w-auto" id="album-owner" name="owner"><option value="0">Все пользователи</option><?php foreach ($users as $person): ?><option value="<?= (int)$person['id'] ?>" <?= $owner_filter===(int)$person['id'] ? 'selected' : '' ?>><?= htmlSC($person['name']) ?> · <?= (int)$person['albums'] ?> альбомов, отправлено <?= (int)$person['submitted'] ?></option><?php endforeach; ?></select>
<label class="visually-hidden" for="album-genre">Жанр</label><select class="form-select w-auto" id="album-genre" name="genre"><option value="0">Все жанры</option><?php foreach ($genres as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $genre_filter===(int)$item['id'] ? 'selected' : '' ?>><?= htmlSC($item['name']) ?></option><?php endforeach; ?></select><button class="btn btn-dark" type="submit">Показать</button>
</form>
<section class="border rounded-4 p-3 mb-4"><h2 class="h5">Отправленные альбомы</h2><?php require __DIR__.'/album-table.php'; $pagination_path='/admin/reel-player'; require __DIR__.'/album-pagination.php'; ?></section>
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
