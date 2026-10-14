<?php $statusLabels=['pending'=>'На модерации','approved'=>'Одобрен','rejected'=>'Отклонён','draft'=>'Черновик']; ?>
<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Альбом</th><th>Пользователь</th><th>Жанр</th><th>Песни</th><th>Статус</th></tr></thead>
    <tbody><?php foreach ($albums as $item): ?><tr>
        <td><a href="<?= htmlSC(base_href('/admin/reel-player/albums/'.(int)$item['id'])) ?>"><?= htmlSC($item['title']) ?></a><div class="small text-body-secondary"><?= htmlSC($item['artist']) ?></div></td>
        <td><?= htmlSC($item['owner_name']) ?><div class="small text-body-secondary"><?= htmlSC($item['owner_login']) ?></div></td>
        <td><?= htmlSC($item['genre_name'] ?: 'Не указан') ?></td><td><?= (int)$item['tracks'] ?></td>
        <td><?= htmlSC($statusLabels[$item['moderation_status']] ?? '') ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php if (!$albums): ?><p class="text-body-secondary">Отправленных альбомов пока нет.</p><?php endif; ?>
