<?php
$t = static fn(string $key): string => htmlSC(FireballPluginSubscriptions::t('business_' . $key));
$section = $section ?? 'overview';
$owner = $owner ?? [];
$business_stats = $business_stats ?? ['posts'=>0,'photos'=>0,'promotions'=>0,'reviews'=>0,'rating'=>null];
$business_subscription = $business_subscription ?? null;
$business_camera = $business_camera ?? null;
$latest_posts = $latest_posts ?? [];
$latest_promotions = $latest_promotions ?? [];
$url = static fn(string $tab): string => htmlSC(base_href('/account/business?section=' . $tab));
$pageForm = ($form_data['action'] ?? '') === 'page' ? array_replace(['is_published'=>0, 'show_camera'=>0], $form_data) : [];
$pageValues = array_replace($page, $pageForm);
$v = static fn(string $key): string => htmlSC((string)($pageValues[$key] ?? ''));
$socials = $pageValues['socials'] ?? json_decode($page['socials_json'] ?? '{}', true) ?? [];
$postForm = ($form_data['action'] ?? '') === 'post' ? $form_data : ['kind'=>match($section) { 'promotions'=>'promotion', 'gallery'=>'photo', default=>'news' }];
$businessName = (string)($page['name'] ?? $owner['name'] ?? FireballPluginSubscriptions::t('business_manage'));
$nav = ['overview'=>['overview','ci-user'], 'posts'=>['publications','ci-file-text'],
    'promotions'=>['offers','ci-tag'], 'camera'=>['camera','ci-camera'], 'gallery'=>['gallery','ci-image'],
    'statistics'=>['statistics','ci-bar-chart'], 'reviews'=>['reviews','ci-message-circle'], 'settings'=>['settings','ci-settings']];
?>
<section class="container profile-layout business-owner business-page">
<aside class="profile-sidebar business-owner-sidebar">
    <div class="profile-identity business-owner-identity">
        <?php if (!empty($page['avatar'])): ?><img src="<?= htmlSC(base_href('/' . ltrim($page['avatar'], '/'))) ?>" class="profile-avatar business-owner-avatar" alt="<?= htmlSC($businessName) ?>"><?php else: ?><span class="profile-avatar business-owner-avatar business-owner-avatar--empty"><i class="ci-shopping-bag" aria-hidden="true"></i></span><?php endif; ?>
        <h2 class="h6 mb-1 mt-2 text-break"><?= htmlSC($businessName) ?></h2>
        <?php if (!empty($owner['login'])): ?><p class="text-body-secondary mb-1 text-break">@<?= htmlSC($owner['login']) ?></p><?php endif; ?>
        <p class="profile-email text-body-secondary mb-0"><i class="ci-mail" aria-hidden="true"></i><span><?= htmlSC((string)($owner['email'] ?? $page['email'] ?? '')) ?></span></p>
    </div>
    <?php if (!empty($page['id'])): ?><dl class="profile-meta"><div><dt><i class="ci-id-card" aria-hidden="true"></i><?= $t('business_id') ?>:</dt><dd>#<?= (int)$page['id'] ?></dd></div></dl><?php endif; ?>
    <nav class="profile-nav business-owner-nav" aria-label="<?= $t('manage') ?>">
        <?php foreach ($nav as $key=>[$label,$icon]): ?><a href="<?= $url($key) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><i class="<?= $icon ?>" aria-hidden="true"></i><span><?= $t($label) ?></span></a><?php endforeach; ?>
    </nav>
    <nav class="profile-nav profile-nav-services business-owner-services" aria-label="<?= $t('my_services') ?>"><h3><?= $t('my_services') ?></h3>
        <a href="<?= htmlSC(base_href('/account/subscription')) ?>"><i class="ci-award" aria-hidden="true"></i><?= $t('my_subscription') ?></a>
        <a href="<?= htmlSC(base_href('/profile/subscription-details')) ?>"><i class="ci-id-card" aria-hidden="true"></i><?= $t('subscription_details') ?></a>
        <a href="<?= htmlSC(base_href('/chat')) ?>"><i class="ci-message-circle" aria-hidden="true"></i><?= $t('messages') ?></a>
        <a href="<?= htmlSC(base_href('/profile')) ?>"><i class="ci-user" aria-hidden="true"></i><?= $t('profile') ?></a>
    </nav>
</aside>
<main class="profile-content business-owner-main">
    <?php get_alerts(); ?>
    <header class="profile-heading business-owner-heading"><h1 class="h3 mb-1"><?= $t('manage') ?></h1><p class="text-body-secondary mb-0"><?= $t('dashboard_subtitle') ?></p></header>
    <?php if (!$allowed): ?>
        <div class="profile-panel business-panel"><p><?= $t('subscription_required') ?></p><a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/subscriptions')) ?>"><?= $t('plans') ?></a></div>
    <?php elseif ($section === 'overview'): ?>
        <?php require __DIR__ . '/business-overview.php'; ?>
    <?php elseif ($section === 'settings'): ?>
        <?php require __DIR__ . '/business-company-form.php'; ?>
    <?php elseif (in_array($section, ['posts','promotions','gallery'], true)): ?>
        <?php require __DIR__ . '/business-publications.php'; ?>
    <?php elseif ($section === 'camera'): ?>
        <section class="profile-panel business-panel"><h2 class="business-panel-title"><?= $t('camera') ?></h2><?php require __DIR__ . '/business-camera-preview.php'; ?></section>
        <?php if (!empty($page['id'])): ?>
        <form method="post" class="profile-panel business-panel">
            <?= get_csrf_field() ?><input type="hidden" name="action" value="camera"><h2 class="business-panel-title"><?= $t('camera_settings') ?></h2><p class="business-muted"><?= $t('camera_admin') ?></p>
            <label class="form-label" for="camera-title"><?= $t('camera_title') ?></label><input id="camera-title" class="form-control" name="camera_title" maxlength="190" value="<?= htmlSC((string)($form_data['camera_title'] ?? $page['camera_title'] ?? '')) ?>">
            <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="show_camera" value="1" <?= !empty(($form_data['action'] ?? '') === 'camera' ? ($form_data['show_camera'] ?? 0) : ($page['show_camera'] ?? 1)) ? 'checked' : '' ?>><span><?= $t('show_camera') ?></span></label><button class="btn btn-outline-secondary rounded-pill mt-3"><?= $t('save') ?></button>
        </form>
        <?php endif; ?>
    <?php elseif ($section === 'reviews'): ?>
        <?php require __DIR__ . '/business-owner-reviews.php'; ?>
    <?php elseif ($section === 'statistics'): ?>
        <section class="profile-panel business-panel"><h2 class="business-panel-title"><?= $t('statistics') ?></h2><p class="business-muted"><?= $t('statistics_hint') ?></p><?php require __DIR__ . '/business-statistics.php'; ?></section>
    <?php endif; ?>
</main>
</section>
