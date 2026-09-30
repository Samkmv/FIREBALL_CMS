<?php $postId = (int)($post['id'] ?? $post['post_id'] ?? 0); ?>
<form method="post" enctype="multipart/form-data" class="row g-3">
    <?= get_csrf_field() ?><input type="hidden" name="action" value="post"><input type="hidden" name="post_id" value="<?= $postId ?>">
    <div class="col-md-4"><label class="form-label" for="post-kind-<?= $postId ?>"><?= $t('kind') ?></label><select id="post-kind-<?= $postId ?>" class="form-select" name="kind"><?php foreach (['news','promotion','photo'] as $kind): ?><option value="<?= $kind ?>" <?= ($post['kind'] ?? 'news') === $kind ? 'selected' : '' ?>><?= $t($kind) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-8"><label class="form-label" for="post-title-<?= $postId ?>"><?= $t('title') ?></label><input id="post-title-<?= $postId ?>" class="form-control" name="title" value="<?= htmlSC((string)($post['title'] ?? '')) ?>" maxlength="190" required></div>
    <div class="col-12"><label class="form-label" for="post-body-<?= $postId ?>"><?= $t('body') ?></label><textarea id="post-body-<?= $postId ?>" class="form-control" name="body" rows="3" maxlength="10000"><?= htmlSC((string)($post['body'] ?? '')) ?></textarea></div>
    <div class="col-12"><label class="form-label" for="post-image-<?= $postId ?>"><?= $t('photo') ?></label><input id="post-image-<?= $postId ?>" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp"><div class="form-text"><?= $t('image_hint') ?></div>
    <?php if (!empty($post['image'])): ?><img class="business-image-preview my-2" src="<?= htmlSC(base_href('/' . ltrim($post['image'], '/'))) ?>" alt="<?= htmlSC($post['title']) ?>"><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1"><span><?= $t('remove_image') ?></span></label><?php endif; ?></div>
    <div class="col-12"><button class="btn btn-outline-secondary rounded-pill"><?= $t($postId ? 'save' : 'add_post') ?></button></div>
</form>
