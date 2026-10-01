<?php $businessEnd = ($business_subscription['status'] ?? '') === 'grace_period' ? ($business_subscription['grace_ends_at'] ?? $business_subscription['ends_at'] ?? null) : ($business_subscription['ends_at'] ?? null); ?>
<section class="profile-panel business-panel business-subscription-panel">
    <h2 class="business-panel-title"><?= $t('active_subscription') ?></h2>
    <a class="profile-subscription business-subscription-summary" href="<?= htmlSC(base_href('/account/subscription')) ?>">
        <span class="profile-subscription-icon"><i class="ci-award" aria-hidden="true"></i></span>
        <div class="profile-subscription-body"><div class="profile-card-title"><h3><?= htmlSC((string)($business_subscription['plan_name'] ?? '')) ?></h3><span class="badge rounded-pill text-bg-success"><?= $t(($business_subscription['status'] ?? '') === 'grace_period' ? 'grace' : 'active') ?></span></div>
        <p class="profile-subscription-description"><?= $t('subscription_access') ?></p><div class="profile-subscription-meta"><span><i class="ci-calendar" aria-hidden="true"></i><?= $t('valid_until') ?>: <?= $businessEnd ? htmlSC(date('d.m.Y', strtotime($businessEnd))) : $t('unlimited') ?></span><span><i class="ci-tag" aria-hidden="true"></i><?= htmlSC((string)($business_subscription['plan_name'] ?? '')) ?></span></div></div>
        <i class="ci-chevron-right business-summary-arrow" aria-hidden="true"></i>
    </a>
</section>
<section class="profile-panel business-panel business-summary-panel">
    <div class="business-summary-identity">
        <?php $summaryImage = !empty($page['cover']) ? $page['cover'] : ($page['avatar'] ?? ''); if ($summaryImage): ?><img src="<?= htmlSC(base_href('/' . ltrim($summaryImage, '/'))) ?>" alt="<?= htmlSC($businessName) ?>"><?php else: ?><span class="business-summary-image-empty"><i class="ci-shopping-bag" aria-hidden="true"></i></span><?php endif; ?>
        <div><h2><?= htmlSC($businessName) ?></h2><span class="business-status <?= empty($page['is_published']) ? 'business-status--muted' : '' ?>"><?= $t(!empty($page['is_published']) ? 'published' : 'draft') ?></span>
        <?php if (!empty($page['hours'])): ?><p class="business-muted mt-2 mb-1"><?= htmlSC($page['hours']) ?></p><?php endif; ?>
        <?php if (!empty($page['address'])): ?><p class="business-muted mb-0"><i class="ci-map-pin" aria-hidden="true"></i> <?= htmlSC($page['address']) ?></p><?php endif; ?></div>
    </div>
    <div class="business-summary-right"><div class="business-summary-metrics">
        <div><strong><i class="ci-star" aria-hidden="true"></i> <?= $business_stats['rating'] !== null ? number_format((float)$business_stats['rating'],1) : '—' ?></strong><span><?= $t('rating') ?></span></div>
        <div><strong><i class="ci-message-circle" aria-hidden="true"></i> <?= (int)$business_stats['reviews'] ?></strong><span><?= $t('reviews') ?></span></div>
        <div><strong><i class="ci-file-text" aria-hidden="true"></i> <?= (int)$business_stats['posts'] ?></strong><span><?= $t('publications') ?></span></div>
    </div><div class="business-summary-actions"><a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('settings') ?>"><i class="ci-edit" aria-hidden="true"></i><?= $t('edit') ?></a>
    <?php if (!empty($page['is_published'])): ?><a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page))) ?>"><i class="ci-external-link" aria-hidden="true"></i><?= $t('public') ?></a><?php endif; ?></div></div>
</section>
<div class="business-dashboard-middle">
    <section class="profile-panel business-panel business-camera-panel"><div class="business-card-heading"><span class="business-camera-dot <?= $business_camera ? 'is-assigned' : '' ?>"></span><div><h2><?= $t('online_camera') ?></h2><p><?= $t('camera_preview_hint') ?></p></div></div>
        <?php require __DIR__ . '/business-camera-preview.php'; ?>
        <div class="business-camera-actions"><a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('camera') ?>"><i class="ci-camera" aria-hidden="true"></i><?= $t('manage_camera') ?></a>
        <?php if ($business_camera): ?><button type="button" class="business-button btn btn-sm btn-outline-secondary rounded-pill" data-business-camera-refresh data-unavailable="<?= $t('camera_retry_error') ?>"><i class="ci-refresh-cw" aria-hidden="true"></i><?= $t('refresh_stream') ?></button><?php endif; ?></div>
    </section>
    <section class="profile-panel business-panel business-recent-panel"><div class="business-card-heading"><i class="ci-file-text" aria-hidden="true"></i><div><h2><?= $t('latest_posts') ?></h2><p><?= $t('latest_posts_hint') ?></p></div><a class="business-card-link" href="<?= $url('posts') ?>"><?= $t('all_posts') ?> <i class="ci-chevron-right" aria-hidden="true"></i></a></div>
    <div class="business-recent-list">
    <?php if (!$latest_posts): ?><p class="business-empty"><?= $t('no_posts') ?></p><?php endif; ?>
    <?php foreach ($latest_posts as $item): ?><a class="business-recent-item" href="<?= $url(match($item['kind']) { 'promotion'=>'promotions','photo'=>'gallery',default=>'posts' }) ?>#post-<?= (int)$item['id'] ?>">
        <?php if ($item['image']): ?><img src="<?= htmlSC(base_href('/' . ltrim($item['image'], '/'))) ?>" alt="" loading="lazy"><?php else: ?><span class="business-recent-icon"><i class="ci-file-text" aria-hidden="true"></i></span><?php endif; ?>
        <span class="business-post-kind business-post-kind--<?= htmlSC($item['kind']) ?>"><?= $t($item['kind']) ?></span><span class="business-recent-copy"><strong><?= htmlSC($item['title']) ?></strong><time><?= htmlSC(date('d.m.Y, H:i', strtotime($item['created_at']))) ?></time></span><i class="ci-chevron-right" aria-hidden="true"></i>
    </a><?php endforeach; ?></div><a class="business-button business-recent-create btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('posts') ?>#publications"><i class="ci-plus-circle" aria-hidden="true"></i><?= $t('create_post') ?></a>
    </section>
</div>
<div class="business-dashboard-bottom">
    <section class="profile-panel business-panel"><div class="business-card-heading"><i class="ci-bar-chart" aria-hidden="true"></i><h2><?= $t('statistics') ?></h2><a class="business-card-link" href="<?= $url('statistics') ?>"><i class="ci-chevron-right" aria-hidden="true"></i></a></div><?php require __DIR__ . '/business-statistics.php'; ?></section>
    <section class="profile-panel business-panel"><div class="business-card-heading"><i class="ci-tag" aria-hidden="true"></i><h2><?= $t('offers') ?></h2><a class="business-card-link" href="<?= $url('promotions') ?>"><?= $t('all_offers') ?></a></div>
    <?php if (!$latest_promotions): ?><p class="business-empty"><?= $t('no_offers') ?></p><?php endif; ?>
    <?php foreach ($latest_promotions as $offer): ?><a class="business-offer-preview" href="<?= $url('promotions') ?>#post-<?= (int)$offer['id'] ?>"><?php if ($offer['image']): ?><img src="<?= htmlSC(base_href('/' . ltrim($offer['image'], '/'))) ?>" alt="" loading="lazy"><?php endif; ?><div><span class="business-post-kind business-post-kind--promotion"><?= $t('promotion') ?></span><strong><?= htmlSC($offer['title']) ?></strong><time class="business-muted"><?= htmlSC(date('d.m.Y', strtotime($offer['created_at']))) ?></time></div></a><?php endforeach; ?></section>
    <section class="profile-panel business-panel"><div class="business-card-heading"><i class="ci-zap" aria-hidden="true"></i><h2><?= $t('quick_actions') ?></h2></div><div class="business-quick-actions">
        <a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('posts') ?>#publications"><i class="ci-file-text" aria-hidden="true"></i><?= $t('add_news') ?></a>
        <a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('gallery') ?>#publications"><i class="ci-image" aria-hidden="true"></i><?= $t('upload_photo') ?></a>
        <a class="business-button btn btn-sm btn-outline-secondary rounded-pill" href="<?= $url('settings') ?>#business-address"><i class="ci-settings" aria-hidden="true"></i><?= $t('edit_contacts') ?></a>
    </div></section>
</div>
