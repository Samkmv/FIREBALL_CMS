<?php
$isDemo = !empty($demo);
$miniIcon = static function (string $name): string {
    $map = require dirname(__DIR__) . '/includes/icon-map.php';
    return '<i class="rp-icon ci-' . htmlSC($map[$name] ?? 'music') . '" aria-hidden="true"></i>';
};
?>
<section class="tr-mini-player<?= $isDemo ? ' tr-mini-player--demo' : '' ?>" <?= $isDemo ? '' : 'data-mini-player hidden' ?> aria-label="Мини-плеер Tape Room">
    <div class="tr-mini-inner">
        <div class="tr-mini-info">
            <div class="tr-mini-artwork" <?= $isDemo ? '' : 'data-mini-artwork' ?>><?php if ($isDemo): ?><img class="rp-theme-cover rp-theme-cover--light" src="<?= htmlSC(theme_asset('images/cover-light-320.webp')) ?>" alt="" width="48" height="48"><img class="rp-theme-cover rp-theme-cover--dark" src="<?= htmlSC(theme_asset('images/cover-dark-320.webp')) ?>" alt="" width="48" height="48"><?php endif; ?></div>
            <div class="tr-mini-copy"><strong <?= $isDemo ? '' : 'data-mini-title' ?>><?= $isDemo ? 'Вечерняя лента' : 'Ваша первая лента' ?></strong><span <?= $isDemo ? '' : 'data-mini-artist' ?>><?= $isDemo ? 'Tape Room · Личная коллекция' : 'Выберите музыку в библиотеке' ?></span><small <?= $isDemo ? '' : 'data-mini-status' ?>><?= $isDemo ? 'FLAC · 16 БИТ · 44,1 КГЦ · ≈ 962 КБИТ/С · СТЕРЕО' : 'АУДИО' ?></small></div>
        </div>
        <div class="tr-mini-transport" <?= $isDemo ? '' : 'data-mini-transport' ?>>
            <?php if ($isDemo): ?><div class="rp-main-controls">
                <div class="rp-timeline"><label class="rp-seek-wrap tr-playback-timeline"><span class="visually-hidden">Позиция — пример</span><input class="rp-range rp-seek tr-timeline-range" type="range" min="0" max="1000" value="350" style="--fill:35%;--buffered:68%"></label></div>
                <div class="rp-times"><span>1:17</span><span>3:42</span></div>
                <div class="rp-play-controls"><button class="btn rp-icon-button" type="button" data-tr-show-toast aria-label="Предыдущая песня — пример"><?= $miniIcon('prev') ?></button><button class="btn rp-icon-button rp-play" type="button" data-tr-demo-play aria-label="Воспроизвести — пример" aria-pressed="false"><i class="rp-icon tr-icon ci-play-filled" aria-hidden="true"></i></button><button class="btn rp-icon-button" type="button" data-tr-show-toast aria-label="Следующая песня — пример"><?= $miniIcon('next') ?></button></div>
            </div><?php endif; ?>
        </div>
        <div class="tr-mini-secondary" <?= $isDemo ? '' : 'data-mini-secondary' ?>><?php if ($isDemo): ?><div class="rp-secondary-controls"><div class="rp-volume"><button class="btn rp-icon-button" type="button" data-tr-show-toast aria-label="Громкость — пример"><?= $miniIcon('volume') ?></button><label><span class="visually-hidden">Громкость — пример</span><input class="rp-range" type="range" min="0" max="1" step="0.01" value="0.8" style="--fill:80%"></label></div></div><?php endif; ?></div>
        <div class="tr-mini-actions" role="group" aria-label="Порядок воспроизведения и плейлист">
            <div class="tr-mini-options" <?= $isDemo ? '' : 'data-mini-options' ?>><?php if ($isDemo): ?><div class="rp-playback-options"><button class="btn rp-icon-button" type="button" data-tr-demo-shuffle aria-label="Случайный порядок: выключен — пример" aria-pressed="false"><?= $miniIcon('shuffle') ?></button><span class="rp-repeat-wrap"><button class="btn rp-icon-button" type="button" data-tr-demo-repeat="off" aria-label="Повтор: выключен — пример" aria-pressed="false"><?= $miniIcon('repeat') ?></button><span data-tr-demo-repeat-one hidden>1</span></span></div><?php endif; ?></div>
        <button class="btn rp-icon-button tr-mini-queue-toggle" type="button" <?= $isDemo ? 'data-bs-toggle="offcanvas" data-bs-target="#tr-demo-playback-queue"' : 'data-action="playback-queue"' ?> aria-controls="<?= $isDemo ? 'tr-demo-playback-queue' : 'tr-playback-queue' ?>" aria-expanded="false" aria-label="Открыть плейлист" title="Открыть плейлист"><?= $miniIcon('queue') ?></button>
        </div>
    </div>
</section>
<?= $this->partial('playback-queue',['demo'=>$isDemo]) ?>
