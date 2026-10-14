<?= view()->renderPartial('admin/shell_open',['title'=>$album['title'],'subtitle'=>'Tape Room · Проверка альбома']) ?>
<?php require __DIR__.'/admin-tabs.php'; ?>
<section class="border rounded-4 p-3 p-md-4">
    <?php if ($album['cover_available']): ?><img class="rounded-4 mb-3" src="<?= htmlSC(base_href('/admin/reel-player/review-cover/'.(int)$album['id'])) ?>" alt="Обложка альбома" width="240" height="240" style="object-fit:cover;max-width:100%;height:auto"><?php endif; ?>
    <p><strong>Пользователь:</strong> <?= htmlSC($album['owner_name']) ?> · <strong>Исполнитель:</strong> <?= htmlSC($album['artist']) ?> · <strong>Жанр:</strong> <?= htmlSC($album['genre_name'] ?: 'Не указан') ?></p>
    <p><?= nl2br(htmlSC($album['description'])) ?></p>
    <h2 class="h5">Песни</h2><div class="vstack gap-3">
        <?php foreach ($album['tracks'] as $item): ?><div><strong><?= htmlSC($item['title']) ?></strong><span class="text-body-secondary"> · <?= htmlSC($item['artist']) ?><?= ($item['genre'] ?: $album['genre_name']) ? ' · '.htmlSC($item['genre'] ?: $album['genre_name']) : '' ?></span><audio class="d-block w-100 mt-2" controls preload="none" src="<?= htmlSC(base_href('/admin/reel-player/review-media/'.(int)$album['id'].'/'.(int)$item['id'])) ?>"></audio></div><?php endforeach; ?>
    </div>
    <?php if ($album['moderation_status']==='pending'): ?>
    <form class="mt-4" action="<?= htmlSC(base_href('/admin/reel-player/albums/review')) ?>" method="post">
        <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$album['id'] ?>"><input type="hidden" name="revision" value="<?= htmlSC($revision) ?>">
        <label class="form-label" for="review-note">Комментарий пользователю</label><textarea class="form-control mb-3" id="review-note" name="note" rows="3" maxlength="1000"></textarea>
        <button class="btn btn-success" name="decision" value="approved" type="submit">Одобрить публикацию</button><button class="btn btn-outline-danger" name="decision" value="rejected" type="submit">Отклонить</button>
    </form>
    <?php else: ?><p class="mt-4"><strong><?= $album['moderation_status']==='approved' ? 'Альбом одобрен' : 'Альбом отклонён' ?></strong> · <?= htmlSC($album['reviewed_at'] ?? '') ?></p><p><?= htmlSC($album['moderation_note']) ?></p><?php endif; ?>
</section>
<?= view()->renderPartial('admin/shell_close') ?>
