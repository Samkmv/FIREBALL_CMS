<?php
declare(strict_types=1);
require dirname(__DIR__) . '/components/icons.php';
$musicUrl = static fn(string $path = '', array $query = []): string => base_href('/music' . $path) . ($query ? '?' . http_build_query($query) : '');
$musicTitle = static fn(array $item): string => implode(' — ', array_filter([(string)($item['artist'] ?? ''), (string)($item['title'] ?? '')]));
$musicTime = static function (float $seconds): string {
    $seconds = (int)max(0, $seconds);
    return $seconds >= 3600 ? sprintf('%d:%02d:%02d', intdiv($seconds,3600), intdiv($seconds,60)%60, $seconds%60) : sprintf('%d:%02d',intdiv($seconds,60),$seconds%60);
};
$musicImage = static function (array $item, string $class, string $kind = 'cover', int $size = 320, bool $priority = false) use ($config): string {
    $source = (string)($item[$kind === 'artist' ? 'image' : 'cover'] ?? '');
    $fallback = $config[$kind === 'artist' ? 'defaultArtistVariants' : 'defaultCoverVariants']['light'][$size] ?? $config[$kind === 'artist' ? 'defaultArtists' : 'defaultCovers']['light'] ?? '';
    $src = $source ? $source . (str_contains($source,'?') ? '&' : '?') . 'size=' . $size : $fallback;
    return '<img class="' . htmlSC($class) . '" width="' . $size . '" height="' . $size . '" src="' . htmlSC($src) . '" alt="' . htmlSC($item['title'] ?? $item['name'] ?? 'Обложка') . '" loading="' . ($priority?'eager':'lazy') . '"' . ($priority?' fetchpriority="high"':'') . ' decoding="async" data-music-image="' . $kind . '" data-image-source="' . htmlSC($source) . '">';
};
$musicSave = static function (array $item, string $type, bool $text = false) use ($config,$icon): string {
    if (empty($config['viewer'])) return '';
    $saved = (bool)($item[$type === 'album' ? 'saved' : 'favorite'] ?? false);
    $label = $type === 'album' ? ($saved ? 'В коллекции' : 'В коллекцию') : ($saved ? 'В избранном' : 'В избранное');
    return '<button type="button" class="' . ($text ? 'tr-button' : 'tr-save-button') . ($saved ? ' is-saved' : '') . '" data-public-collection="' . ($type === 'album' ? 'album.save' : 'track.favorite') . '" data-id="' . (int)$item['id'] . '" aria-pressed="' . ($saved ? 'true' : 'false') . '" aria-label="' . $label . '">' . $icon('heart') . ($text ? '<span>' . $label . '</span>' : '') . '</button>';
};
$musicBadges = static function (array $item) use ($musicUrl): string {
    $html = '<div class="tr-badges">';
    foreach (['genres'=>'genre','tags'=>'tag'] as $key=>$filter) foreach ($item[$key] ?? [] as $tag) $html .= '<a class="tr-badge" href="' . htmlSC($musicUrl('/tracks',[$filter=>$tag['slug']])) . '" data-music-link>' . htmlSC($tag['name']) . '</a>';
    return $html . '</div>';
};
$musicTracks = static function (array $items) use ($musicUrl,$musicTime,$musicImage,$musicSave,$icon): string {
    $html = '<div class="tr-track-list">';
    foreach ($items as $item) {
        $html .= '<article class="tr-track-row" data-public-track="' . (int)$item['id'] . '">' . $musicImage($item,'tr-track-cover','cover',96) . '<div class="tr-track-info"><a href="' . htmlSC($musicUrl('/track/' . (int)$item['id'])) . '" data-music-link>' . htmlSC($item['title']) . '</a><span>' . htmlSC($item['artist'] ?: 'Неизвестный исполнитель') . '</span></div><span class="tr-track-duration">' . $musicTime((float)$item['duration']) . '</span><div class="tr-track-actions">';
        if (!empty($item['access']['stream'])) $html .= '<button type="button" class="tr-play-button" data-public-play="' . (int)$item['id'] . '" aria-label="Воспроизвести: ' . htmlSC($item['title']) . '">' . $icon('play') . '</button>';
        else $html .= '<span class="tr-badge">' . (($item['access']['reason'] ?? '') === 'authentication_required' ? 'Войдите в аккаунт' : 'Ограничен доступ') . '</span>';
        $html .= $musicSave($item,'track') . '</div></article>';
    }
    return $html . '</div>';
};
$musicAlbums = static function (array $items) use ($musicUrl,$musicImage,$musicSave,$musicBadges,$icon): string {
    $html = '<div class="tr-grid">';
    foreach ($items as $item) {
        $url = $musicUrl('/album/' . (int)$item['id']);
        $html .= '<article class="tr-card"><div class="tr-card-image"><a class="tr-card-art" href="' . htmlSC($url) . '" data-music-link>' . $musicImage($item,'','cover',320) . '</a>';
        if (!empty($item['first_track'])) $html .= '<button type="button" class="tr-play-button" data-public-album="' . (int)$item['id'] . '" aria-label="Слушать альбом: ' . htmlSC($item['title']) . '">' . $icon('play') . '</button>';
        $html .= $musicSave($item,'album') . '</div><a class="tr-card-title" href="' . htmlSC($url) . '" data-music-link>' . htmlSC($item['title']) . '</a><p class="tr-card-meta">' . htmlSC($item['artist'] ?: 'Неизвестный исполнитель') . '<br>' . (int)$item['track_count'] . ' композиций' . ($item['release_date'] ? ' · ' . htmlSC($item['release_date']) : '') . '</p>' . $musicBadges($item) . '</article>';
    }
    return $html . '</div>';
};
$musicPager = static function (array $pagination, string $path, array $filters = []) use ($musicUrl): string {
    $page = (int)($pagination['page'] ?? 1); $pages = (int)($pagination['total_pages'] ?? 0);
    if ($pages < 2) return '';
    $html = '<nav class="tr-pager" aria-label="Страницы каталога">';
    if ($page > 1) $html .= '<a href="' . htmlSC($musicUrl($path,[...$filters,'page'=>$page-1])) . '" data-music-link>Назад</a>';
    $html .= '<span aria-current="page">' . $page . ' / ' . $pages . '</span>';
    if ($page < $pages) $html .= '<a href="' . htmlSC($musicUrl($path,[...$filters,'page'=>$page+1])) . '" data-music-link>Дальше</a>';
    return $html . '</nav>';
};
$musicNavigation = static function (string $current) use ($musicUrl,$config,$icon): string {
    $items = ['overview'=>['Обзор','music'],'albums'=>['Альбомы','folder'],'artists'=>['Артисты','microphone'],'tracks'=>['Композиции','list'],'new'=>['Новинки','disc'],'popular'=>['Популярное','trending']];
    if (!empty($config['flags']['discovery'])) $items['genres'] = ['Жанры','list'];
    if (!empty($config['viewer'])) $items['collection'] = ['Моя коллекция','heart'];
    $html = '<nav class="tr-nav" aria-label="Музыкальная библиотека">';
    foreach ($items as $key=>[$label,$symbol]) $html .= '<a href="' . htmlSC($musicUrl($key==='overview'?'':'/'.$key)) . '" data-music-link' . ($key===$current?' aria-current="page"':'') . '>' . $icon($symbol) . htmlSC($label) . '</a>';
    return $html . '</nav>';
};
$musicData = static function (array $tracks, array $albums = []) use ($config): string {
    return '<script type="application/json" data-music-data>' . json_encode(['tracks'=>$tracks,'albums'=>$albums,'config'=>$config],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . '</script>';
};
