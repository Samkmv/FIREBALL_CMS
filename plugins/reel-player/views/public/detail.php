<?php
declare(strict_types=1);
require __DIR__ . '/helpers.php';
$album = $entityType==='album';
$tracks = $album ? $entity['tracks'] : [$entity];
if (empty($fragment)) echo \Fireball\ReelPlayer\Services\PlatformHost::markup($config);
?>
<section class="tr-public" data-music-page tabindex="-1">
    <?= $musicNavigation($album?'albums':'tracks') ?>
    <div class="tr-detail">
        <?= $musicImage($entity,'tr-detail-art','cover',640,true) ?>
        <div class="tr-detail-info"><p><?= $album?'АЛЬБОМ':'КОМПОЗИЦИЯ' ?></p><h1><?= htmlSC($entity['title']) ?></h1><p><?= htmlSC($entity['artist'] ?: 'Неизвестный исполнитель') ?></p>
            <?php if ($album && !empty($entity['description'])): ?><p><?= nl2br(htmlSC($entity['description'])) ?></p><?php endif; ?>
            <?= $musicBadges($entity) ?>
            <div class="tr-card-actions">
                <?php if ($album && !empty($entity['first_track'])): ?><button type="button" class="tr-button tr-button--primary" data-public-album="<?= (int)$entity['id'] ?>"><?= $icon('play') ?> Слушать альбом</button>
                <?php elseif (!$album && !empty($entity['access']['stream'])): ?><button type="button" class="tr-button tr-button--primary" data-public-play="<?= (int)$entity['id'] ?>"><?= $icon('play') ?> Воспроизвести</button><?php endif; ?>
                <?= $musicSave($entity,$entityType,true) ?>
                <?php if (!$album && !empty($entity['download_url'])): ?><a class="tr-button" href="<?= htmlSC($entity['download_url']) ?>">Скачать оригинал</a><?php endif; ?>
                <?php if (!$album && !empty($entity['purchase_url'])): ?><a class="tr-button" href="<?= htmlSC($entity['purchase_url']) ?>" rel="noopener noreferrer" target="_blank">Купить</a><?php endif; ?>
                <button type="button" class="tr-button tr-button--quiet" data-public-preferences><?= $icon('settings') ?> Вид плеера</button>
            </div>
            <?php if ($album ? empty($entity['access']['allowed']) : empty($entity['access']['stream'])): ?><div class="tr-message"><p><?= empty($config['viewer'])?'Для этой записи может потребоваться вход в аккаунт или подходящая подписка.':'Для этой записи требуется разрешение или подходящая подписка.' ?></p><?php if (empty($config['viewer'])): ?><a href="<?= htmlSC(base_href('/login')) ?>">Войти в аккаунт</a><?php endif; ?></div><?php endif; ?>
        </div>
    </div>
    <?php if ($album): ?><header class="tr-heading"><h2>Композиции альбома</h2><span class="tr-card-meta"><?= (int)$entity['track_count'] ?> композиций</span></header><?= $musicTracks($tracks) ?><?= $musicPager($entity['tracks_pagination'],'/album/'.(int)$entity['id']) ?>
    <?php elseif (!empty($config['flags']['discovery'])): ?><section data-music-recommendations data-track-id="<?= (int)$entity['id'] ?>" aria-label="Похожие композиции"></section><?php endif; ?>
    <p class="tr-error" role="alert" data-music-error hidden></p>
    <?= $musicData($tracks,$album?[$entity]:[]) ?>
</section>
