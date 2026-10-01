<?php
$t = static fn(string $key): string => htmlSC(FireballPluginSubscriptions::t('business_' . $key));
$socials = json_decode($page['socials_json'], true) ?? [];
$publicSection = $public_section ?? 'home';
$publicPath = \Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page);
$sectionUrl = static fn(string $section): string => htmlSC(base_href($publicPath . ($section === 'home' ? '' : '?section=' . $section)));
$imageUrl = static fn(string $path): string => htmlSC(base_href('/' . ltrim($path, '/')));
$phone = preg_replace('/[^0-9+]/', '', (string)$page['phone']);
$mapUrl = $page['address'] ? 'https://yandex.ru/maps/?text=' . rawurlencode($page['address']) : '';
$featured_promotion = $featured_promotion ?? null;
$latest_news = $latest_news ?? [];
$photos = $photos ?? [];
?>
<section class="container business-public business-page">
<?php get_alerts(); ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= htmlSC(base_href('/')) ?>" class="d-flex align-items-center"><i class="ci-home fs-base me-2" aria-hidden="true"></i><?= $t('home') ?></a></li>
        <li class="breadcrumb-item"><a href="<?= htmlSC(base_href('/business')) ?>"><?= $t('directory') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlSC($page['name']) ?></li>
    </ol>
</nav>
<header class="profile-panel business-public-hero">
    <div class="business-public-banner <?= $page['cover'] ? 'has-cover' : '' ?>">
        <?php if ($page['cover']): ?><img class="business-public-cover" src="<?= $imageUrl($page['cover']) ?>" alt="" fetchpriority="high"><?php endif; ?>
        <div class="business-public-identity">
            <?php if ($page['avatar']): ?><img class="business-public-logo" src="<?= $imageUrl($page['avatar']) ?>" alt="<?= htmlSC($page['name']) ?>"><?php else: ?><span class="business-public-logo business-public-logo-empty"><i class="ci-shopping-bag" aria-hidden="true"></i></span><?php endif; ?>
            <div class="business-public-heading"><h1 class="h2 mb-2"><?= htmlSC($page['name']) ?></h1>
                <div class="business-public-facts"><a href="#reviews" class="business-public-rating"><i class="ci-star" aria-hidden="true"></i><?= $rating['total'] ? number_format((float)$rating['average'], 1) . ' / 5 · ' . $t('reviews') . ': ' . (int)$rating['total'] : $t('no_ratings') ?></a>
                    <?php if ($page['hours']): ?><span><i class="ci-clock" aria-hidden="true"></i><?= htmlSC($page['hours']) ?></span><?php endif; ?>
                </div>
                <?php if ($page['address']): ?><p class="business-public-address mb-0"><i class="ci-map-pin" aria-hidden="true"></i><?= htmlSC($page['address']) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="business-public-actions">
            <?php if ($phone): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC('tel:' . $phone) ?>"><i class="ci-phone" aria-hidden="true"></i><?= $t('call') ?></a><?php endif; ?>
            <?php if ($mapUrl): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC($mapUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="ci-map-pin" aria-hidden="true"></i><?= $t('directions') ?></a><?php endif; ?>
            <?php if ($page['website']): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC($page['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><i class="ci-external-link" aria-hidden="true"></i><?= $t('website') ?></a><?php endif; ?>
            <?php if ($can_manage): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/account/business?section=settings')) ?>"><i class="ci-edit" aria-hidden="true"></i><?= $t('edit') ?></a><?php endif; ?>
        </div>
    </div>
    <nav class="business-public-tabs" aria-label="<?= $t('navigation') ?>">
        <?php foreach (['home'=>['home','ci-home'],'promotions'=>['offers','ci-tag'],'news'=>['news','ci-file-text'],'gallery'=>['photos','ci-image']] as $key=>[$label,$icon]): ?><a href="<?= $sectionUrl($key) ?>" <?= $publicSection === $key ? 'aria-current="page"' : '' ?>><i class="<?= $icon ?>" aria-hidden="true"></i><?= $t($label) ?></a><?php endforeach; ?>
        <a href="#business-about"><i class="ci-info" aria-hidden="true"></i><?= $t('about') ?></a><a href="#business-contacts"><i class="ci-map-pin" aria-hidden="true"></i><?= $t('contacts') ?></a><a href="#reviews"><i class="ci-star" aria-hidden="true"></i><?= $t('reviews') ?></a>
    </nav>
</header>
<?php if ($publicSection === 'home'): ?>
<div class="business-public-content-grid">
    <div class="business-public-primary">
        <?php if ($camera): ?><section class="profile-panel business-public-panel business-public-camera">
            <div class="business-public-card-heading"><i class="ci-camera" aria-hidden="true"></i><div><h2><?= $t('online_camera') ?></h2><p><?= htmlSC($camera['title']) ?></p></div></div>
            <div class="business-camera-frame"><div class="fire-player" data-fire-player data-src="<?= htmlSC($camera['url']) ?>" data-poster="<?= htmlSC($camera['poster']) ?>" data-stream-id="<?= htmlSC($camera['stream_key']) ?>" data-media="video" data-mode="live" data-protocol="auto" data-controls="true" data-muted="true" data-aspect-ratio="16:9" aria-label="<?= htmlSC($camera['title']) ?>"><?php if ($camera['poster']): ?><img class="business-camera-fallback" src="<?= htmlSC($camera['poster']) ?>" alt="" loading="lazy"><?php endif; ?></div></div>
        </section><?php endif; ?>
        <section class="profile-panel business-public-panel"><div class="business-public-card-heading"><i class="ci-image" aria-hidden="true"></i><h2><?= $t('photos') ?></h2><a class="business-public-more" href="<?= $sectionUrl('gallery') ?>"><?= $t('all_photos') ?><?php if (!empty($photo_total)): ?> (<?= (int)$photo_total ?>)<?php endif; ?><i class="ci-chevron-right" aria-hidden="true"></i></a></div>
            <?php if (!$photos): ?><p class="text-body-secondary mb-0"><?= $t('no_photos') ?></p><?php else: ?><div class="business-public-photos"><?php foreach ($photos as $photo): ?><a href="<?= $imageUrl($photo['image']) ?>" aria-label="<?= htmlSC($photo['title']) ?>"><img src="<?= $imageUrl($photo['image']) ?>" alt="<?= htmlSC($photo['title']) ?>" loading="lazy"></a><?php endforeach; ?></div><?php endif; ?>
        </section>
    </div>
    <aside class="business-public-secondary" aria-label="<?= $t('publications') ?>">
        <section class="profile-panel business-public-panel"><div class="business-public-card-heading"><i class="ci-tag" aria-hidden="true"></i><h2><?= $t('featured_offer') ?></h2><a class="business-public-more" href="<?= $sectionUrl('promotions') ?>"><?= $t('all_offers') ?><i class="ci-chevron-right" aria-hidden="true"></i></a></div>
            <?php if (!$featured_promotion): ?><p class="text-body-secondary mb-0"><?= $t('no_offers') ?></p><?php else: $item = $featured_promotion; ?><a class="business-public-feature" href="<?= $sectionUrl('promotions') ?>#business-post-<?= (int)$item['id'] ?>">
                <?php if ($item['image']): ?><img src="<?= $imageUrl($item['image']) ?>" alt="" loading="lazy"><?php endif; ?><div><span class="badge text-bg-secondary rounded-pill mb-2"><?= $t('promotion') ?></span><h3><?= htmlSC($item['title']) ?></h3><p><?= htmlSC($item['body']) ?></p><time datetime="<?= htmlSC($item['created_at']) ?>"><?= htmlSC(date('d.m.Y', strtotime($item['created_at']))) ?></time></div></a><?php endif; ?>
        </section>
        <section class="profile-panel business-public-panel"><div class="business-public-card-heading"><i class="ci-file-text" aria-hidden="true"></i><h2><?= $t('latest_news') ?></h2><a class="business-public-more" href="<?= $sectionUrl('news') ?>"><?= $t('all_news') ?><i class="ci-chevron-right" aria-hidden="true"></i></a></div>
            <?php if (!$latest_news): ?><p class="text-body-secondary mb-0"><?= $t('no_news') ?></p><?php endif; ?>
            <div class="business-public-news"><?php foreach ($latest_news as $item): ?><a href="<?= $sectionUrl('news') ?>#business-post-<?= (int)$item['id'] ?>"><?php if ($item['image']): ?><img src="<?= $imageUrl($item['image']) ?>" alt="" loading="lazy"><?php endif; ?><div><h3><?= htmlSC($item['title']) ?></h3><p><?= htmlSC($item['body']) ?></p><time datetime="<?= htmlSC($item['created_at']) ?>"><?= htmlSC(date('d.m.Y, H:i', strtotime($item['created_at']))) ?></time></div><i class="ci-chevron-right" aria-hidden="true"></i></a><?php endforeach; ?></div>
        </section>
    </aside>
</div>
<?php else: ?>
<section id="business-publications" class="profile-panel business-public-panel"><h2 class="h4 mb-3"><?= $t(match ($publicSection) { 'promotions'=>'offers','gallery'=>'photos',default=>'news' }) ?></h2>
    <?php if (!$posts): ?><p class="text-body-secondary mb-0"><?= $t(match ($publicSection) { 'promotions'=>'no_offers','gallery'=>'no_photos',default=>'no_news' }) ?></p><?php endif; ?>
    <div class="business-public-posts"><?php foreach ($posts as $post): ?><article id="business-post-<?= (int)$post['id'] ?>" class="business-public-post">
        <?php if ($post['image']): ?><a href="<?= $imageUrl($post['image']) ?>"><img src="<?= $imageUrl($post['image']) ?>" alt="<?= htmlSC($post['title']) ?>" loading="lazy"></a><?php endif; ?>
        <div class="p-3"><h3 class="h5"><?= htmlSC($post['title']) ?></h3><p class="business-text"><?= htmlSC($post['body']) ?></p><time class="small text-body-secondary" datetime="<?= htmlSC($post['created_at']) ?>"><?= htmlSC(date('d.m.Y', strtotime($post['created_at']))) ?></time></div>
    </article><?php endforeach; ?></div><?= $posts_pagination->getHtml() ?>
</section>
<?php endif; ?>
<div class="business-public-details-grid">
    <section id="business-about" class="profile-panel business-public-panel"><div class="business-public-card-heading"><i class="ci-info" aria-hidden="true"></i><h2><?= $t('about') ?></h2></div><p class="business-text text-body-secondary mb-0"><?= $page['description'] ? htmlSC($page['description']) : $t('no_description') ?></p></section>
    <section id="business-contacts" class="profile-panel business-public-panel"><div class="business-public-card-heading"><i class="ci-map-pin" aria-hidden="true"></i><h2><?= $t('contacts') ?></h2></div>
        <dl class="business-public-contacts">
            <?php foreach (['phone'=>'ci-phone','address'=>'ci-map-pin','hours'=>'ci-clock','email'=>'ci-mail'] as $key=>$icon): if (!$page[$key]) continue; ?><div><dt><i class="<?= $icon ?>" aria-hidden="true"></i><span class="visually-hidden"><?= $t($key) ?></span></dt><dd><?php if ($key === 'phone' && $phone): ?><a href="<?= htmlSC('tel:' . $phone) ?>"><?= htmlSC($page[$key]) ?></a><?php elseif ($key === 'email'): ?><a href="<?= htmlSC('mailto:' . $page[$key]) ?>"><?= htmlSC($page[$key]) ?></a><?php else: ?><?= htmlSC($page[$key]) ?><?php endif; ?></dd></div><?php endforeach; ?>
            <?php if ($page['website']): ?><div><dt><i class="ci-globe" aria-hidden="true"></i><span class="visually-hidden"><?= $t('website') ?></span></dt><dd><a href="<?= htmlSC($page['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= htmlSC($page['website']) ?><i class="ci-external-link ms-2" aria-hidden="true"></i></a></dd></div><?php endif; ?>
        </dl>
        <div class="business-public-contact-actions"><?php if ($mapUrl): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC($mapUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $t('show_map') ?><i class="ci-chevron-right ms-2" aria-hidden="true"></i></a><?php endif; ?>
        <?php foreach ($socials as $key=>$url): if (!$url) continue; ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC($url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= htmlSC(ucfirst($key)) ?></a><?php endforeach; ?></div>
    </section>
</div>
<?php require __DIR__ . '/business-reviews.php'; ?>
</section>
