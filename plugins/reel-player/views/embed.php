<!doctype html><html lang="ru" class="tr-embed-document" data-theme="<?= $appearance==='dark'?'dark':'light' ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="color-scheme" content="light dark">
<title><?= htmlSC($item['title']) ?> · Tape Room</title>
<link rel="stylesheet" href="<?= htmlSC($asset('bootstrap/bootstrap.scoped.css')) ?>"><link rel="stylesheet" href="<?= htmlSC($asset('icons/cartzilla-icons.min.css')) ?>"><link rel="stylesheet" href="<?= htmlSC($asset('css/shell.css')) ?>"><link rel="stylesheet" href="<?= htmlSC($asset('css/embed.css')) ?>">
</head><body class="tr-body <?= $embedded?'tr-embed-body':'tr-share-body' ?>">
<main class="tr-bootstrap tr-embed" data-embed-player data-appearance="<?= htmlSC($appearance) ?>" data-bs-theme="<?= $appearance==='dark'?'dark':'light' ?>">
    <header class="tr-embed-brand"><a href="<?= htmlSC(base_href('/')) ?>" target="_blank" rel="noopener">TAPE ROOM <span>БЕТА</span></a><a class="tr-embed-open" href="<?= htmlSC(base_href('/tape-room/share/'.$item['kind'].'/'.$item['id'])) ?>" target="_blank" rel="noopener" aria-label="Открыть на сайте"><i class="ci-external-link" aria-hidden="true"></i></a></header>
    <div class="tr-embed-now"><img src="<?= htmlSC($item['cover'] ?: $asset('images/cover-light-96.webp')) ?>" alt="" width="64" height="64"><div><h1><?= htmlSC($item['title']) ?></h1><p><?= htmlSC($item['artist'] ?: 'Исполнитель не указан') ?></p><small data-embed-song></small></div></div>
    <audio preload="none" data-embed-audio></audio>
    <div class="tr-embed-controls"><button type="button" class="btn tr-play-control" data-embed-previous aria-label="Предыдущая песня"><i class="ci-skip-back tr-icon" aria-hidden="true"></i></button><button type="button" class="btn tr-play-control tr-embed-play" data-embed-play aria-label="Воспроизвести"><i class="ci-play-filled tr-icon" aria-hidden="true"></i></button><button type="button" class="btn tr-play-control" data-embed-next aria-label="Следующая песня"><i class="ci-skip-forward tr-icon" aria-hidden="true"></i></button><div class="tr-embed-timeline"><label class="tr-playback-timeline"><span class="visually-hidden">Позиция воспроизведения</span><input type="range" class="tr-timeline-range" data-embed-seek min="0" max="1000" value="0"></label><div><span data-embed-time>0:00</span><span data-embed-duration>0:00</span></div></div></div>
    <?php if ($item['kind']==='album'): ?>
    <section class="tr-embed-songs" aria-label="Песни альбома">
        <div class="tr-embed-songs-heading"><h2>Песни альбома</h2><span><?= count($item['tracks']) ?> тр.</span></div>
        <ol class="tr-embed-tracks"><?php foreach ($item['tracks'] as $index=>$track): ?>
            <li><button type="button" data-embed-index="<?= $index ?>" aria-label="Воспроизвести: <?= htmlSC($track['title']) ?>">
                <span class="tr-embed-track-number"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
                <span class="tr-embed-track-info"><strong><?= htmlSC($track['title']) ?></strong><small><?= htmlSC($track['artist']) ?></small></span>
                <span class="tr-embed-track-duration"><?php if ($track['duration']>0): ?><?= (int)floor($track['duration']/60) ?>:<?= str_pad((string)((int)$track['duration']%60),2,'0',STR_PAD_LEFT) ?><?php endif; ?></span>
                <span class="tr-embed-track-control"><i class="ci-play-filled" aria-hidden="true"></i></span>
            </button></li>
        <?php endforeach; ?></ol>
    </section>
    <?php endif; ?>
    <p class="tr-embed-error" data-embed-error role="status" hidden></p>
    <script type="application/json" data-embed-config><?= json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
</main><script src="<?= htmlSC($asset('js/embed.js')) ?>" defer></script></body></html>
