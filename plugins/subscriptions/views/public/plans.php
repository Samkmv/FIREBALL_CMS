<?php
$plans = is_array($plans ?? null) ? $plans : [];
$planCount = count($plans);
$planColumnClass = $planCount === 1
    ? 'col-md-8 col-lg-6 col-xl-5'
    : ($planCount === 2 ? 'col-md-6 col-xl-5' : 'col-md-6 col-xl-4');
$planIcons = ['ci-repeat', 'ci-star-filled', 'ci-briefcase'];
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

    <div class="row g-4 justify-content-center align-items-stretch subscriptions-plan-grid">
        <?php foreach ($plans as $planIndex => $plan): ?>
            <?php
            $isRecommended = !empty($plan['is_popular']);
            $planIcon = $planIcons[$planIndex % count($planIcons)];
            $cardClass = 'subscriptions-plan-card subscriptions-plan-card--tone-' . (($planIndex % 3) + 1) . ' border h-100 d-flex flex-column';
            if ($isRecommended) {
                $cardClass .= ' subscriptions-plan-card--recommended';
            }
            ?>
            <div class="<?= htmlSC($planColumnClass) ?>">
                <article class="<?= htmlSC($cardClass) ?>">
                    <?php if ($isRecommended): ?>
                        <span class="subscriptions-plan-card__popular badge rounded-pill">
                            <i class="ci-star-filled" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_popular')) ?>
                        </span>
                    <?php endif; ?>
                    <div class="subscriptions-plan-card__accent" aria-hidden="true"></div>
                    <div class="subscriptions-plan-card__body p-4 p-lg-5 d-flex flex-column flex-grow-1">
                        <div class="subscriptions-plan-card__heading d-flex align-items-start gap-3 mb-3">
                            <span class="subscriptions-plan-card__icon d-inline-flex align-items-center justify-content-center rounded-circle" aria-hidden="true"><i class="<?= htmlSC($planIcon) ?>"></i></span>
                            <div class="min-w-0 pt-1">
                                <h2 class="h3 mb-1"><?= htmlSC((string)$plan['name']) ?></h2>
                                <span class="subscriptions-plan-card__duration d-inline-flex align-items-center gap-1">
                                    <i class="ci-calendar" aria-hidden="true"></i><?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?>
                                </span>
                            </div>
                        </div>

                        <p class="subscriptions-plan-card__description text-body-secondary mb-4"><?php if (trim((string)$plan['description']) !== ''): ?><?= nl2br(htmlSC((string)$plan['description'])) ?><?php endif; ?></p>

                        <div class="subscriptions-plan-card__price-row d-flex align-items-end flex-wrap gap-2 mb-4">
                            <div class="subscriptions-plan-card__price"><?= htmlSC((string)$plan['price_display']) ?></div>
                            <span class="subscriptions-plan-card__period text-body-secondary">/ <?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?></span>
                        </div>

                        <div class="subscriptions-plan-card__features mb-4 flex-grow-1">
                            <div class="small fw-semibold mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_features')) ?></div>
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

                        <a class="subscriptions-plan-card__action btn btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2 <?= $isRecommended ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= check_auth() ? base_href('/subscriptions/checkout/' . (int)$plan['id']) : base_href('/login') ?>">
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

    <?php if ($plans): ?>
        <div class="subscriptions-plans-note d-flex align-items-center justify-content-center gap-2 text-body-secondary mt-4">
            <i class="ci-check-shield" aria-hidden="true"></i>
            <span><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_payment_note')) ?></span>
        </div>
    <?php endif; ?>
</section>
