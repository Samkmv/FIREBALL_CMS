<section aria-labelledby="commerce-reviews-title">
    <h2 class="h4 mb-3" id="commerce-reviews-title"><?= $t('reviews') ?></h2>
    <?php if ($rating['total'] > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-sm-5">
                <div class="bg-body-tertiary rounded-4 p-3 h-100 d-flex flex-column align-items-center justify-content-center" aria-label="<?= htmlSC($ratingLabel) ?>">
                    <strong class="display-6"><?= htmlSC(number_format($rating['average'], 1)) ?></strong>
                    <?php $stars = $rating['average']; require __DIR__ . '/product-stars.php'; ?>
                    <span class="text-body-secondary fs-sm mt-1"><?= htmlSC(sprintf(FireballPluginCommerce::t('review_count'), $rating['total'])) ?></span>
                </div>
            </div>
            <div class="col-sm-7 d-grid align-content-center gap-2">
                <?php foreach ($rating['counts'] as $score => $count): ?>
                    <div class="d-flex align-items-center gap-2 fs-sm">
                        <span class="text-nowrap"><?= $score ?> <i class="ci-star-filled text-warning" aria-hidden="true"></i></span>
                        <progress class="commerce-rating-progress flex-grow-1" max="<?= (int)$rating['total'] ?>" value="<?= $count ?>" aria-label="<?= htmlSC(sprintf(FireballPluginCommerce::t('rating_count'), $score, $count)) ?>"></progress>
                        <span><?= $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p class="text-body-secondary"><?= $t('empty_reviews') ?></p>
    <?php endif; ?>

    <?php if ($canReview): ?>
        <form action="<?= $url($productPath . '/reviews') ?>" class="border rounded-4 p-3 mb-4" method="post">
            <?= get_csrf_field() ?>
            <h3 class="h6"><?= $t('write_review') ?></h3>
            <label class="form-label" for="commerce-review-rating"><?= $t('review_rating') ?></label>
            <select id="commerce-review-rating" name="rating" class="form-select w-auto mb-3" required>
                <option value=""><?= $t('review_choose_rating') ?></option>
                <?php foreach (range(5, 1) as $score): ?><option value="<?= $score ?>"><?= $score ?> / 5</option><?php endforeach; ?>
            </select>
            <label class="form-label" for="commerce-review-body"><?= $t('review_body') ?></label>
            <textarea id="commerce-review-body" name="body" class="form-control mb-3" rows="4" maxlength="2000" required></textarea>
            <button class="btn btn-primary" type="submit"><?= $t('send_review') ?></button>
        </form>
    <?php elseif ($reviewsReady && !check_auth()): ?>
        <p class="text-body-secondary mb-4"><?= $t('review_login_hint') ?> <a href="<?= $url('/login') ?>"><?= htmlSC(return_translation('tpl_auth_login')) ?></a></p>
    <?php endif; ?>

    <?php foreach ($reviews as $review): ?>
        <article class="border-top py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h6 mb-0 text-break"><?= htmlSC($review['author']) ?></h3>
                <time class="text-body-secondary fs-sm" datetime="<?= htmlSC(str_replace(' ', 'T', $review['created_at'])) ?>"><?= htmlSC(substr($review['created_at'], 0, 10)) ?></time>
            </div>
            <div class="mt-1 mb-2" aria-label="<?= htmlSC(sprintf(FireballPluginCommerce::t('rating_value'), (int)$review['rating'])) ?>">
                <?php $stars = (int)$review['rating']; require __DIR__ . '/product-stars.php'; ?>
            </div>
            <p class="commerce-copy mb-0"><?= htmlSC($review['body']) ?></p>
        </article>
    <?php endforeach; ?>
    <?php
    $paginationPath = $productPath . '/reviews';
    $hasNext = $page * \Fireball\Commerce\Reviews::PER_PAGE < $rating['total'];
    require __DIR__ . '/../pagination.php';
    ?>
</section>
