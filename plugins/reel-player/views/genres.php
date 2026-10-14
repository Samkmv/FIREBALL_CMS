<?= view()->renderPartial('admin/shell_open',['title'=>'Tape Room · Жанры','subtitle'=>'Библиотека музыкальных жанров']) ?>
<?php require __DIR__.'/admin-tabs.php'; ?>
<form class="d-flex gap-2 mb-4" method="get" action="<?= htmlSC(base_href('/admin/reel-player/genres')) ?>"><input class="form-control" type="search" name="q" value="<?= htmlSC($query) ?>" placeholder="Найти жанр" aria-label="Найти жанр"><button class="btn btn-dark" type="submit">Найти</button></form>
<div class="row g-4">
    <div class="col-lg-8"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Жанр</th><th>Альбомы</th><th>Песни</th></tr></thead><tbody>
        <?php foreach ($genres as $item): ?><tr><td><a href="<?= htmlSC(base_href('/admin/reel-player/genres?id='.(int)$item['id'])) ?>"><?= htmlSC($item['name']) ?></a></td><td><?= (int)$item['albums'] ?></td><td><?= (int)$item['tracks'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div><p class="small text-body-secondary">Состав жанра включает альбомы, отправленные пользователями на публикацию.</p></div>
    <div class="col-lg-4"><form class="border rounded-4 p-3" action="<?= htmlSC(base_href('/admin/reel-player/genres/save')) ?>" method="post">
        <h2 class="h5"><?= $genre ? 'Изменить жанр' : 'Добавить жанр' ?></h2><?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)($genre['id'] ?? 0) ?>">
        <label class="form-label" for="genre-name">Название</label><input class="form-control mb-3" id="genre-name" name="name" value="<?= htmlSC($genre['name'] ?? '') ?>" maxlength="100" required>
        <label class="form-label" for="genre-description">Описание</label><textarea class="form-control mb-3" id="genre-description" name="description" maxlength="1000" rows="3"><?= htmlSC($genre['description'] ?? '') ?></textarea>
        <button class="btn btn-dark" type="submit">Сохранить</button><?php if ($genre): ?><a class="btn btn-link" href="<?= htmlSC(base_href('/admin/reel-player/genres')) ?>">Новый жанр</a><?php endif; ?>
    </form></div>
</div>
<?php if ($genre): ?><section class="mt-4"><h2 class="h5">Альбомы · <?= htmlSC($genre['name']) ?></h2><?php require __DIR__.'/album-table.php'; $pagination_path='/admin/reel-player/genres'; require __DIR__.'/album-pagination.php'; ?></section><?php endif; ?>
<?php if ($genre): ?><section class="mt-4"><h2 class="h5">Песни · <?= htmlSC($genre['name']) ?></h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Песня</th><th>Исполнитель</th><th>Альбом</th></tr></thead><tbody><?php foreach ($tracks as $item): ?><tr><td><?= htmlSC($item['title']) ?></td><td><?= htmlSC($item['artist']) ?></td><td><a href="<?= htmlSC(base_href('/admin/reel-player/albums/'.(int)$item['album_id'])) ?>"><?= htmlSC($item['album_title']) ?></a></td></tr><?php endforeach; ?><?php if (!$tracks): ?><tr><td colspan="3" class="text-body-secondary">Песен этого жанра в отправленных альбомах пока нет.</td></tr><?php endif; ?></tbody></table></div><p class="small text-body-secondary">Жанр песни задаётся отдельно. Если он не указан, используется жанр альбома. Показано до 200 связей песен с альбомами.</p></section><?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
