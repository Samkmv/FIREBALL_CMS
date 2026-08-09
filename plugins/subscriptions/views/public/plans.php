<section class="container py-5 subscriptions-public">
    <div class="text-center mx-auto mb-5" style="max-width: 720px">
        <h1 class="display-5 fw-semibold mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_title')) ?></h1>
        <p class="lead text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_subtitle')) ?></p>
    </div>
    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-6 col-xl-4">
                <article class="subscriptions-plan-card border rounded-5 p-4 p-lg-5 h-100 d-flex flex-column">
                    <h2 class="h4"><?= htmlSC((string)$plan['name']) ?></h2>
                    <p class="text-body-secondary flex-grow-1"><?= nl2br(htmlSC((string)$plan['description'])) ?></p>
                    <div class="display-6 fw-semibold my-3"><?= htmlSC((string)$plan['price_display']) ?></div>
                    <div class="text-body-secondary mb-4"><?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?></div>
                    <ul class="list-unstyled vstack gap-2 mb-4">
                        <?php foreach ([['posts.view_paid', 'subscriptions_feature_posts'], ['videos.view_paid', 'subscriptions_feature_video'], ['camera_archive.view', 'subscriptions_feature_archive']] as [$permission, $label]): ?>
                            <?php if (!empty($plan['permissions'][$permission])): ?><li><i class="ci-check-circle text-success me-2"></i><?= htmlSC(FireballPluginSubscriptions::t($label)) ?><?php if ($permission === 'camera_archive.view' && !empty($plan['permissions']['camera_archive.max_days'])): ?> — <?= (int)$plan['permissions']['camera_archive.max_days'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?><?php endif; ?></li><?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn-dark rounded-pill w-100" href="<?= check_auth() ? base_href('/subscriptions/checkout/' . (int)$plan['id']) : base_href('/login') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_subscribe')) ?></a>
                </article>
            </div>
        <?php endforeach; ?>
        <?php if (!$plans): ?><div class="col-12"><div class="alert alert-info text-center"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_public_plans')) ?></div></div><?php endif; ?>
    </div>
</section>
