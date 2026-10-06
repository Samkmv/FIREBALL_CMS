<section class="profile-account-section border rounded-5 p-3 p-md-4">
    <h2 class="h5 mb-4"><?= print_translation('account_favorites') ?></h2>
    <?php if (!is_array($user_favorites ?? null)): ?>
        <div class="alert alert-warning mb-0"><?= print_translation('account_feature_unavailable') ?></div>
    <?php elseif ($user_favorites['items'] === []): ?>
        <p class="text-body-secondary mb-0"><?= print_translation('account_favorites_empty') ?></p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($user_favorites['items'] as $favorite): ?>
                <div class="col-12 col-md-6"><article class="card h-100 overflow-hidden">
                    <?php if (!empty($favorite['show_post_image'])): ?><img class="card-img-top object-fit-cover" src="<?= htmlSC(get_image($favorite['image'])) ?>" alt="<?= htmlSC($favorite['title']) ?>" height="180" loading="lazy" decoding="async"><?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <div class="small text-body-secondary mb-2"><?= htmlSC($favorite['category']) ?> · <?= htmlSC(date('d.m.Y', strtotime($favorite['published_at']))) ?></div>
                        <h3 class="h6 text-break"><a href="<?= htmlSC($favorite['url']) ?>"><?= htmlSC($favorite['title']) ?></a></h3>
                        <?php if (trim((string)$favorite['excerpt']) !== ''): ?><p class="small text-body-secondary text-break"><?= htmlSC(mb_substr(strip_tags($favorite['excerpt']), 0, 220)) ?></p><?php endif; ?>
                        <div class="d-flex flex-wrap gap-2 mt-auto pt-2">
                            <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC($favorite['url']) ?>"><?= print_translation('account_favorite_open') ?></a>
                            <form method="post" action="<?= base_href('/profile/favorites/remove') ?>"><?= get_csrf_field() ?><input type="hidden" name="entity_type" value="post"><input type="hidden" name="entity_id" value="<?= (int)$favorite['id'] ?>"><button class="btn btn-outline-danger rounded-pill" type="submit"><?= print_translation('account_favorite_remove') ?></button></form>
                        </div>
                    </div>
                </article></div>
            <?php endforeach; ?>
        </div>
        <?php if ($user_favorites['pagination']['total_pages'] > 1): ?><div class="pt-4"><?= $user_favorites['pagination']->getHtml() ?></div><?php endif; ?>
    <?php endif; ?>
</section>
