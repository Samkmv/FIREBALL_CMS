<?php
$t = static fn(string $key): string => htmlSC(FireballPluginSubscriptions::t('business_' . $key));
$socials = json_decode($page['socials_json'], true) ?? [];
?>
<section class="container py-5 subscriptions-public business-page">
<?php get_alerts(); ?>
<header class="business-hero border rounded-4 overflow-hidden mb-4">
    <?php if ($page['cover']): ?><img class="business-cover" src="<?= htmlSC(base_href('/' . ltrim($page['cover'], '/'))) ?>" alt="<?= $t('cover') ?>"><?php endif; ?>
    <div class="p-4 d-flex flex-wrap align-items-center gap-4">
        <?php if ($page['avatar']): ?><img class="business-avatar" src="<?= htmlSC(base_href('/' . ltrim($page['avatar'], '/'))) ?>" alt="<?= htmlSC($page['name']) ?>"><?php endif; ?>
        <div class="flex-grow-1"><h1 class="h2 mb-2"><?= htmlSC($page['name']) ?></h1><span class="business-rating"><?= $rating['total'] ? '★ ' . number_format((float)$rating['average'], 1) . ' / 5 · ' . (int)$rating['total'] : $t('no_ratings') ?></span></div>
        <?php if ($can_manage): ?><a class="btn btn-outline-primary" href="<?= htmlSC(base_href('/account/business')) ?>"><?= $t('edit') ?></a><?php endif; ?>
    </div>
</header>
<div class="business-layout">
<aside class="business-sidebar border rounded-4 p-4"><h2 class="h5"><?= $t('contacts') ?></h2><dl class="business-contacts">
    <?php foreach (['address','phone','email','hours'] as $key): if (!$page[$key]) continue; ?><dt><?= $t($key) ?></dt><dd><?= htmlSC($page[$key]) ?></dd><?php endforeach; ?>
</dl>
<?php if ($page['website']): ?><a class="d-block mb-3" href="<?= htmlSC($page['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $t('website') ?></a><?php endif; ?>
<div class="d-flex flex-wrap gap-2"><?php foreach ($socials as $key=>$url): if (!$url) continue; ?><a class="btn btn-outline-secondary btn-sm" href="<?= htmlSC($url) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= htmlSC(ucfirst($key)) ?></a><?php endforeach; ?></div>
<nav class="d-grid gap-3 mt-4"><a href="#business-about"><?= $t('description') ?></a><a href="#business-posts"><?= $t('posts') ?></a><a href="#reviews"><?= $t('reviews') ?></a></nav>
</aside>
<div class="business-main">
<section id="business-about" class="border rounded-4 p-4 mb-4"><h2 class="h4"><?= $t('description') ?></h2><p class="business-text mb-0"><?= htmlSC($page['description']) ?></p></section>
<?php if ($camera): ?><section class="border rounded-4 p-4 mb-4"><h2 class="h4"><?= htmlSC($camera['title']) ?></h2><div class="fire-player" data-fire-player data-src="<?= htmlSC($camera['url']) ?>" data-poster="<?= htmlSC($camera['poster']) ?>" data-stream-id="<?= htmlSC($camera['stream_key']) ?>" data-media="video" data-mode="live" data-protocol="auto" data-controls="true" data-muted="true" data-aspect-ratio="16:9"></div></section><?php endif; ?>
<section id="business-posts" class="mb-4"><h2 class="h4 mb-3"><?= $t('posts') ?></h2><div class="business-posts">
<?php if (!$posts): ?><p class="text-body-secondary"><?= $t('no_posts') ?></p><?php endif; ?>
<?php foreach ($posts as $post): ?><article class="border rounded-4 overflow-hidden">
<?php if ($post['image']): ?><a href="<?= htmlSC(base_href('/' . ltrim($post['image'], '/'))) ?>"><img class="business-post-image" src="<?= htmlSC(base_href('/' . ltrim($post['image'], '/'))) ?>" alt="<?= htmlSC($post['title']) ?>" loading="lazy"></a><?php endif; ?>
<div class="p-4"><span class="badge text-bg-secondary mb-2"><?= $t($post['kind']) ?></span><h3 class="h5"><?= htmlSC($post['title']) ?></h3><p class="business-text"><?= htmlSC($post['body']) ?></p><time class="small text-body-secondary" datetime="<?= htmlSC($post['created_at']) ?>"><?= htmlSC(date('d.m.Y', strtotime($post['created_at']))) ?></time></div>
</article><?php endforeach; ?></div><?= $posts_pagination->getHtml() ?></section>
<section id="reviews" class="border rounded-4 p-4"><h2 class="h4"><?= $t('reviews') ?></h2>
<?php if (!$reviews): ?><p class="text-body-secondary"><?= $t('no_reviews') ?></p><?php endif; ?>
<?php foreach ($reviews as $review): ?><article class="border-top py-3"><div class="d-flex flex-wrap gap-2 justify-content-between"><strong><?= htmlSC($review['author']) ?> · <?= (int)$review['rating'] ?>/5</strong><time class="small text-body-secondary"><?= htmlSC(date('d.m.Y', strtotime($review['created_at']))) ?></time></div><p class="business-text mt-2"><?= htmlSC($review['body']) ?></p>
<?php if ($review['reply']): ?><div class="border-start border-3 ps-3"><strong><?= $t('owner_reply') ?></strong><p class="business-text mb-0"><?= htmlSC($review['reply']) ?></p></div><?php endif; ?></article><?php endforeach; ?>
<?= $reviews_pagination->getHtml() ?>
<?php if ($user_id > 0 && (int)$page['user_id'] !== $user_id): ?>
<form method="post" action="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page) . '/review')) ?>" class="border-top pt-3 mt-3"><?= get_csrf_field() ?><h3 class="h5"><?= $t('your_review') ?></h3>
<?php if (!empty($own_review['is_hidden'])): ?><p class="text-body-secondary"><?= $t('review_hidden') ?></p><?php endif; ?>
<label class="form-label" for="review-rating"><?= $t('rating') ?></label><select id="review-rating" class="form-select mb-3" name="rating" required><option value=""><?= $t('choose_rating') ?></option><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= (int)($own_review['rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?> / 5</option><?php endfor; ?></select>
<label class="form-label" for="review-body"><?= $t('review') ?></label><textarea id="review-body" class="form-control" name="body" rows="3" maxlength="3000"><?= htmlSC((string)($own_review['body'] ?? '')) ?></textarea><button class="btn btn-primary mt-3"><?= $t('save') ?></button></form>
<?php if ($own_review): ?><form method="post" action="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page) . '/review')) ?>" class="mt-2"><?= get_csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm"><?= $t('delete_review') ?></button></form><?php endif; ?>
<?php elseif (!$user_id): ?><a class="btn btn-outline-primary" href="<?= htmlSC(base_href('/login')) ?>"><?= $t('login_review') ?></a><?php endif; ?>
</section></div></div>
</section>
