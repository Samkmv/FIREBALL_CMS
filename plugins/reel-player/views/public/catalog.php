<?php
declare(strict_types=1);
require __DIR__ . '/helpers.php';
$labels = ['overview'=>'Музыка в Tape Room','albums'=>'Альбомы','artists'=>'Артисты','tracks'=>'Композиции','new'=>'Новые релизы','popular'=>'Популярное','genres'=>'Жанры','collection'=>'Моя коллекция'];
$items = $catalog['items'] ?? [];
$filterText = static fn(mixed $value): string => is_array($value) ? (string)($value[0] ?? '') : (is_scalar($value) ? (string)$value : '');
$albumView = in_array($section,['overview','albums'],true) || ($section==='collection' && ($filters['kind'] ?? 'albums')==='albums');
$tracks = $albumView ? ($catalog['tracks'] ?? []) : (in_array($section,['artists','genres'],true) ? [] : $items);
if (empty($fragment)) echo \Fireball\ReelPlayer\Services\PlatformHost::markup($config);
?>
<section class="tr-public" data-music-page tabindex="-1">
    <header class="tr-heading"><div><h1><?= htmlSC($labels[$section] ?? 'Музыка') ?></h1><p>Альбомы, композиции и любимые исполнители.</p></div><button type="button" class="tr-button" data-public-preferences><?= $icon('settings') ?> Внешний вид плеера</button></header>
    <?= $musicNavigation($section) ?>
    <?php if ($section==='collection'): ?><nav class="tr-nav" aria-label="Коллекция"><a href="<?= htmlSC($musicUrl('/collection',['kind'=>'albums'])) ?>" data-music-link<?= ($filters['kind'] ?? 'albums')==='albums'?' aria-current="page"':'' ?>>Сохранённые альбомы</a><a href="<?= htmlSC($musicUrl('/collection',['kind'=>'tracks'])) ?>" data-music-link<?= ($filters['kind'] ?? '')==='tracks'?' aria-current="page"':'' ?>>Избранные песни</a></nav><?php endif; ?>
    <?php if ($section!=='genres'): ?><form class="tr-tools" method="get" action="<?= htmlSC($musicUrl($section==='overview'?'':'/'.$section)) ?>" data-music-search>
        <?php if ($section==='collection'): ?><input type="hidden" name="kind" value="<?= htmlSC($filters['kind'] ?? 'albums') ?>"><?php endif; ?>
        <label class="tr-search"><?= $icon('search') ?><input type="search" name="q" value="<?= htmlSC($filters['q'] ?? '') ?>" maxlength="240" aria-label="Найти музыку" placeholder="Название, исполнитель, жанр или тег"></label>
        <?php if (!in_array($section,['artists','genres'],true)): ?><div class="tr-filters">
            <?php if (!empty($config['flags']['discovery'])): ?>
            <?php foreach (['genre'=>'Жанр','tag'=>'Тег'] as $facet=>$caption): $selected=$filterText($filters[$facet] ?? ''); ?>
            <label><?= $caption ?><select name="<?= $facet ?><?= is_array($filters[$facet] ?? null)?'[]':'' ?>"><option value="">Все</option><?php foreach ($taxonomy[$facet.'s'] ?? [] as $item): ?><option value="<?= htmlSC($item['slug']) ?>"<?= $selected===$item['slug']?' selected':'' ?>><?= htmlSC($item['name']) ?></option><?php endforeach; ?></select></label>
            <?php if (is_array($filters[$facet] ?? null)): foreach (array_slice($filters[$facet],1) as $extra): ?><input type="hidden" name="<?= $facet ?>[]" value="<?= htmlSC($extra) ?>"><?php endforeach; endif; endforeach; ?>
            <?php endif; ?>
            <label>Порядок<select name="sort"><?php foreach (['new'=>'Сначала новые','old'=>'Сначала старые','popular'=>'Популярные'] as $value=>$label): ?><option value="<?= $value ?>"<?= ($filters['sort'] ?? ($section==='popular'?'popular':'new'))===$value?' selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label>Релиз от<input type="date" name="from" value="<?= htmlSC($filters['from'] ?? '') ?>"></label><label>До<input type="date" name="to" value="<?= htmlSC($filters['to'] ?? '') ?>"></label>
        </div><?php endif; ?>
        <button class="tr-button" type="submit">Найти</button>
    </form><?php endif; ?>
    <?php if (!$items): ?><div class="tr-empty"><h2>Пока ничего не найдено</h2><p>Попробуйте изменить поиск или вернитесь к каталогу позже.</p></div>
    <?php elseif ($albumView): echo $musicAlbums($items);
    elseif ($section==='artists'): ?><div class="tr-grid"><?php foreach ($items as $item): ?><article class="tr-card"><a class="tr-card-image" href="<?= htmlSC($musicUrl('/tracks',['artist'=>$item['name']])) ?>" data-music-link><?= $musicImage($item,'','artist') ?></a><a class="tr-card-title" href="<?= htmlSC($musicUrl('/tracks',['artist'=>$item['name']])) ?>" data-music-link><?= htmlSC($item['name']) ?></a><p class="tr-card-meta"><?= (int)$item['music_count'] ?> записей</p></article><?php endforeach; ?></div>
    <?php elseif ($section==='genres'): ?><div class="tr-grid"><?php foreach ($items as $item): ?><a class="tr-card" href="<?= htmlSC($musicUrl('/tracks',['genre'=>$item['slug']])) ?>" data-music-link><strong class="tr-card-title"><?= htmlSC($item['name']) ?></strong><p class="tr-card-meta"><?= htmlSC($item['description']) ?><br><?= (int)$item['count'] ?> записей</p></a><?php endforeach; ?></div>
    <?php else: echo $musicTracks($items); endif; ?>
    <?= $musicPager($catalog['pagination'] ?? [],$section==='overview'?'':'/'.$section,$filters) ?>
    <?php if ($section==='overview'): ?><section<?= !empty($config['flags']['discovery'])?' data-music-recommendations':'' ?> aria-label="Рекомендации"><?php if ($tracks): ?><header class="tr-heading" style="margin-top:36px"><h2>Вам может понравиться</h2><a class="tr-button tr-button--quiet" href="<?= htmlSC($musicUrl('/tracks')) ?>" data-music-link>Все композиции</a></header><?= $musicTracks($tracks) ?><?php endif; ?></section><?php endif; ?>
    <p class="tr-error" role="alert" data-music-error hidden></p>
    <?= $musicData($tracks,$albumView?$items:[]) ?>
</section>
