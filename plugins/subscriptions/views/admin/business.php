<?php $t = static fn(string $key): string => htmlSC(FireballPluginSubscriptions::t('business_' . $key)); ?>
<?php require __DIR__ . '/shell-open.php'; ?>
<div class="business-page">
<?php get_alerts(); ?>

<div class="d-grid gap-3 mt-4">
<?php if (!$pages): ?><p class="text-body-secondary"><?= $t('no_businesses') ?></p><?php endif; ?>
<?php foreach ($pages as $business): ?><article class="border rounded-4 p-4"><div class="d-flex flex-wrap gap-3 justify-content-between"><div><h2 class="h5"><?= htmlSC($business['name']) ?></h2><p><?= htmlSC($business['owner']) ?> · #<?= (int)$business['user_id'] ?> · <?= $t($business['is_published'] ? 'published' : 'draft') ?></p></div><div class="d-flex flex-wrap gap-2 align-items-start">
<?php if ($business['is_published']): ?><a class="btn btn-outline-primary btn-sm" href="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($business))) ?>"><?= $t('public') ?></a><form method="post"><?= get_csrf_field() ?><input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>"><button class="btn btn-outline-danger btn-sm" name="action" value="unpublish"><?= $t('unpublish') ?></button></form><?php endif; ?>
<a class="btn btn-outline-secondary btn-sm" href="<?= htmlSC(base_href('/admin/subscriptions/business?business=' . (int)$business['id'])) ?>"><?= $t('reviews') ?></a></div></div>
<form method="post" class="row g-3" data-business-camera-form data-missing-stream="<?= $t('camera_url_required') ?>">
    <?= get_csrf_field() ?><input type="hidden" name="action" value="camera-links"><input type="hidden" name="business_id" value="<?= (int)$business['id'] ?>">
    <div class="col-md-6"><label class="form-label" for="camera-url-<?= (int)$business['id'] ?>"><?= $t('camera_url') ?></label><input class="form-control" id="camera-url-<?= (int)$business['id'] ?>" type="url" name="camera_url" maxlength="500" value="<?= htmlSC((string)($business['camera_url'] ?? '')) ?>" placeholder="https://example.com/live/index.m3u8"></div>
    <div class="col-md-6"><label class="form-label" for="camera-poster-<?= (int)$business['id'] ?>"><?= $t('camera_poster') ?></label><input class="form-control" id="camera-poster-<?= (int)$business['id'] ?>" type="url" name="camera_poster" maxlength="500" value="<?= htmlSC((string)($business['camera_poster'] ?? '')) ?>" placeholder="https://example.com/camera.jpg"></div>
    <div class="col-12"><p class="form-text mb-2"><?= $t('camera_links_hint') ?></p><button class="btn btn-outline-secondary rounded-pill"><?= $t('save') ?></button></div>
</form></article><?php endforeach; ?>
</div><?= $pagination->getHtml() ?>
<?php if ($selected): ?><section class="border rounded-4 p-4 mt-4"><h2 class="h4"><?= $t('reviews') ?> · <?= htmlSC($selected['name']) ?></h2>
<?php foreach ($reviews as $review): ?><article class="border-top py-3"><strong><?= htmlSC($review['author']) ?> · <?= (int)$review['rating'] ?>/5</strong><p class="business-text"><?= htmlSC($review['body']) ?></p><form method="post"><?= get_csrf_field() ?><input type="hidden" name="business_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="moderate"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="is_hidden" value="<?= $review['is_hidden'] ? 0 : 1 ?>"><button class="btn btn-outline-secondary btn-sm"><?= $t($review['is_hidden'] ? 'restore_review' : 'hide_review') ?></button></form></article><?php endforeach; ?>
<?= $reviews_pagination->getHtml() ?>
</section><?php endif; ?>
</div>
<?php require __DIR__ . '/shell-close.php'; ?>
