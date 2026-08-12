<?php
$plans = is_array($plans ?? null) ? $plans : [];
$planCount = count($plans);
$planColumnClass = $planCount === 1
    ? 'col-md-8 col-lg-6 col-xl-5'
    : ($planCount === 2 ? 'col-md-6 col-xl-5' : 'col-md-6 col-xl-4');
$features = [
    ['posts.view_paid', 'subscriptions_feature_posts'],
    ['videos.view_paid', 'subscriptions_feature_video'],
    ['camera_archive.view', 'subscriptions_feature_archive'],
];
?>

<section class="container py-5 subscriptions-public subscriptions-plans-page">
    <div class="subscriptions-plans-hero text-center mx-auto mb-5">
        <span class="subscriptions-plans-hero__eyebrow badge rounded-pill px-3 py-2 mb-3">
            <i class="ci-award me-1"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_eyebrow')) ?>
        </span>
        <h1 class="display-5 fw-semibold mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_title')) ?></h1>
        <p class="lead text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_subtitle')) ?></p>
    </div>

    <div class="row g-4 justify-content-center align-items-stretch">
        <?php foreach ($plans as $plan): ?>
            <div class="<?= htmlSC($planColumnClass) ?>">
                <article class="subscriptions-plan-card border rounded-5 h-100 d-flex flex-column overflow-hidden">
                    <div class="subscriptions-plan-card__accent" aria-hidden="true"></div>
                    <div class="p-4 p-lg-5 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                            <span class="subscriptions-plan-card__icon d-inline-flex align-items-center justify-content-center rounded-circle"><i class="ci-award"></i></span>
                            <span class="subscriptions-plan-card__duration badge rounded-pill px-3 py-2">
                                <i class="ci-calendar me-1"></i><?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?>
                            </span>
                        </div>

                        <h2 class="h3 mb-2"><?= htmlSC((string)$plan['name']) ?></h2>
                        <?php if (trim((string)$plan['description']) !== ''): ?>
                            <p class="text-body-secondary mb-4"><?= nl2br(htmlSC((string)$plan['description'])) ?></p>
                        <?php endif; ?>

                        <div class="subscriptions-plan-card__price mb-4"><?= htmlSC((string)$plan['price_display']) ?></div>

                        <div class="subscriptions-plan-card__features border-top pt-4 mb-4 flex-grow-1">
                            <div class="small fw-semibold text-uppercase text-body-secondary mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_features')) ?></div>
                            <ul class="list-unstyled vstack gap-3 mb-0">
                                <?php foreach ($features as [$permission, $label]): ?>
                                    <?php if (!empty($plan['permissions'][$permission])): ?>
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="ci-check-circle text-success mt-1 flex-shrink-0"></i>
                                            <span><?= htmlSC(FireballPluginSubscriptions::t($label)) ?><?php if ($permission === 'camera_archive.view' && !empty($plan['permissions']['camera_archive.max_days'])): ?> — <?= (int)$plan['permissions']['camera_archive.max_days'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?><?php endif; ?></span>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <a class="btn btn-dark btn-lg rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" href="<?= check_auth() ? base_href('/subscriptions/checkout/' . (int)$plan['id']) : base_href('/login') ?>">
                            <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_subscribe')) ?><i class="ci-arrow-right"></i>
                        </a>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>

        <?php if (!$plans): ?>
            <div class="col-lg-7"><div class="alert alert-info rounded-4 text-center py-4"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_public_plans')) ?></div></div>
        <?php endif; ?>
    </div>
</section>
