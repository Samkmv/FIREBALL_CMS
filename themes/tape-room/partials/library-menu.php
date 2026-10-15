<?php $siteIcon=fn(string $name):string=>$this->partial('site-icon',['name'=>$name]); ?>
<section class="tr-library-menu" aria-label="Библиотека">
    <h2 class="tr-menu-label">Библиотека</h2>
    <nav class="nav flex-column tr-side-links" data-catalog-nav aria-label="Музыкальный каталог">
        <?php foreach($room_links as [$view,$label,$symbol]): ?><button class="nav-link text-start" type="button" data-action="catalog-view" data-view="<?= $view ?>"><?= $siteIcon($symbol) ?><span><?= $label ?></span><?php if($view==='albums'): ?><span class="tr-library-count" data-catalog-album-count></span><?php elseif($view==='mine'): ?><span class="tr-library-count" data-catalog-saved-count></span><?php endif; ?></button><?php endforeach; ?>
    </nav>
    <div class="tr-menu-divider"></div>
    <div class="d-flex align-items-center justify-content-between tr-library-heading"><h2 class="tr-menu-label mb-0">Мои ленты</h2><button class="btn tr-nav-icon" type="button" data-action="create-playlist" aria-label="Создать плейлист" title="Создать плейлист"><?= $siteIcon('plus') ?></button></div>
    <nav class="nav flex-column tr-side-links" data-playlists aria-label="Личные песни и плейлисты"></nav>
    <div class="tr-menu-divider"></div>
    <nav class="nav flex-column tr-side-links" aria-label="Добавление музыки">
        <button class="nav-link text-start" type="button" data-action="drive"><?= $siteIcon('cloud') ?><span>Google Drive</span></button>
        <button class="nav-link text-start" type="button" data-tr-torrent-open><?= $siteIcon('download') ?><span>Музыка из torrent</span></button>
    </nav>
</section>
