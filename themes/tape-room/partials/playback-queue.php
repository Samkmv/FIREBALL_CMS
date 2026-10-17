<?php
$queueDemo = !empty($demo);
$queueId = $queueDemo ? 'tr-demo-playback-queue' : 'tr-playback-queue';
$queueIcon = fn(string $name): string => $this->partial('site-icon', ['name'=>$name]);
?>
<aside class="offcanvas offcanvas-bottom tr-playback-queue<?= $queueDemo ? ' tr-playback-queue--demo' : '' ?>" tabindex="-1" id="<?= $queueId ?>" <?= $queueDemo ? '' : 'data-playback-queue' ?> data-bs-scroll="true" data-bs-backdrop="false" aria-labelledby="<?= $queueId ?>-title">
    <div class="offcanvas-header"><div><h2 id="<?= $queueId ?>-title">Плейлист</h2><p <?= $queueDemo ? '' : 'data-queue-name' ?>><?= $queueDemo ? 'Вечерняя коллекция' : '' ?></p><small <?= $queueDemo ? '' : 'data-queue-summary' ?>><?= $queueDemo ? '3 песни · 11:29' : '' ?></small></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть плейлист"><?= $queueIcon('close') ?></button></div>
    <div class="offcanvas-body">
        <ol class="tr-queue-list" <?= $queueDemo ? '' : 'data-queue-list' ?>>
            <?php if ($queueDemo): foreach (['Вечерняя лента','Тёплый звук','Ещё одна любимая песня'] as $index=>$title): ?>
            <li><button type="button" class="btn tr-queue-song<?= $index === 0 ? ' is-current' : '' ?>" data-tr-demo-play aria-pressed="false" aria-label="Воспроизвести <?= htmlSC($title) ?> — пример"><span class="tr-queue-play"><?= $queueIcon('play') ?></span><img class="tr-queue-cover" src="<?= htmlSC(theme_asset('images/cover-light-96.webp')) ?>" alt="" width="36" height="36"><span class="tr-queue-info"><strong><?= htmlSC($title) ?></strong><small>Tape Room</small></span><span class="tr-queue-duration">3:42</span></button></li>
            <?php endforeach; endif; ?>
        </ol>
        <?php if (!$queueDemo): ?><p class="tr-queue-empty" data-queue-empty hidden>Здесь появятся песни выбранного альбома или плейлиста.</p><?php endif; ?>
    </div>
</aside>
