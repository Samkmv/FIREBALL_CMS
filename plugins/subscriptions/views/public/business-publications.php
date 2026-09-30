    <section id="publications" class="profile-panel business-panel"><h2 class="h4"><?= $t(match($section) { 'promotions'=>'offers', 'gallery'=>'gallery', default=>'publications' }) ?></h2>
    <?php if (empty($page['id'])): ?><p><?= $t('save_first') ?></p><?php else: ?>
        <?php $post = $postForm; require __DIR__ . '/business-post-form.php'; ?>
        <?php foreach ($posts as $post): if ((int)($postForm['post_id'] ?? 0) === (int)$post['id']) continue; ?><details id="post-<?= (int)$post['id'] ?>" class="border-top pt-3 mt-3"><summary><?= htmlSC($post['title']) ?> · <?= $t($post['kind']) ?></summary><div class="pt-3"><?php require __DIR__ . '/business-post-form.php'; ?>
            <form method="post" class="mt-2"><?= get_csrf_field() ?><input type="hidden" name="action" value="delete-post"><input type="hidden" name="kind" value="<?= htmlSC($post['kind']) ?>"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><button class="btn btn-outline-danger btn-sm rounded-pill"><?= $t('delete') ?></button></form></div></details><?php endforeach; ?>
        <?= $posts_pagination->getHtml() ?>
    <?php endif; ?></section>
