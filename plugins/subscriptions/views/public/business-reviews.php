<section id="reviews" class="profile-panel business-public-panel"><h2 class="h4"><?= $t('reviews') ?></h2>
<?php if (!$reviews): ?><p class="text-body-secondary"><?= $t('no_reviews') ?></p><?php endif; ?>
<?php foreach ($reviews as $review): ?><article class="border-top py-3"><div class="d-flex flex-wrap gap-2 justify-content-between"><strong><?= htmlSC($review['author']) ?> · <?= (int)$review['rating'] ?>/5</strong><time class="small text-body-secondary"><?= htmlSC(date('d.m.Y', strtotime($review['created_at']))) ?></time></div><p class="business-text mt-2"><?= htmlSC($review['body']) ?></p>
<?php if ($review['reply']): ?><div class="border-start border-3 ps-3"><strong><?= $t('owner_reply') ?></strong><p class="business-text mb-0"><?= htmlSC($review['reply']) ?></p></div><?php endif; ?></article><?php endforeach; ?>
<?= $reviews_pagination->getHtml() ?>
<?php if ($user_id > 0 && (int)$page['user_id'] !== $user_id): ?>
<form method="post" action="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page) . '/review')) ?>" class="border-top pt-3 mt-3"><?= get_csrf_field() ?><h3 class="h5"><?= $t('your_review') ?></h3>
<?php if (!empty($own_review['is_hidden'])): ?><p class="text-body-secondary"><?= $t('review_hidden') ?></p><?php endif; ?>
<label class="form-label" for="review-rating"><?= $t('rating') ?></label><select id="review-rating" class="form-select mb-3" name="rating" required><option value=""><?= $t('choose_rating') ?></option><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= (int)($own_review['rating'] ?? 0) === $i ? 'selected' : '' ?>><?= $i ?> / 5</option><?php endfor; ?></select>
<label class="form-label" for="review-body"><?= $t('review') ?></label><textarea id="review-body" class="form-control" name="body" rows="3" maxlength="3000"><?= htmlSC((string)($own_review['body'] ?? '')) ?></textarea><button class="btn btn-outline-secondary rounded-pill mt-3"><?= $t('save') ?></button></form>
<?php if ($own_review): ?><form method="post" action="<?= htmlSC(base_href(\Fireball\Subscriptions\Repositories\BusinessRepository::publicPath($page) . '/review')) ?>" class="mt-2"><?= get_csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm"><?= $t('delete_review') ?></button></form><?php endif; ?>
<?php elseif (!$user_id): ?><a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/login')) ?>"><?= $t('login_review') ?></a><?php endif; ?>
</section>
