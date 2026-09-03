<?php
$features = [
    ['posts.view_paid', 'subscriptions_feature_posts'],
    ['videos.view_paid', 'subscriptions_feature_video'],
    ['camera_archive.view', 'subscriptions_feature_archive'],
];
$fullName = trim((string)$profile['first_name'] . ' ' . (string)$profile['last_name']);
$address = implode(', ', array_filter([
    (string)$profile['country'],
    (string)$profile['region'],
    (string)$profile['city'],
    (string)$profile['street'],
    (string)$profile['house'],
    (string)$profile['apartment'],
    (string)$profile['postal_code'],
]));
$autoRenewEnabled = !empty($plan['auto_renew_enabled'] ?? $plan['is_recurring'] ?? false);
$renewalPeriod = '';
if ($autoRenewEnabled) {
    $durationValue = max(1, (int)$plan['duration_value']);
    $periodKey = $plan['duration_unit'] === 'months'
        ? ($durationValue === 1 ? 'subscriptions_renewal_period_month' : 'subscriptions_renewal_period_months')
        : ($durationValue === 1 ? 'subscriptions_renewal_period_day' : 'subscriptions_renewal_period_days');
    $renewalPeriod = str_replace(':count', (string)$durationValue, FireballPluginSubscriptions::t($periodKey));
}
?>

<section class="container py-5 subscriptions-public subscriptions-checkout-page">
    <?php get_alerts(); ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plans_eyebrow')) ?></div>
            <h1 class="h2 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_checkout_title')) ?></h1>
        </div>
        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/subscriptions/plans') ?>"><i class="ci-arrow-left"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_change_plan')) ?></a>
    </div>

    <div class="row g-4 align-items-start">
        <aside class="col-lg-5">
            <article class="subscriptions-checkout-summary subscriptions-plan-card border rounded-5 overflow-hidden">
                <div class="subscriptions-plan-card__accent" aria-hidden="true"></div>
                <div class="p-4 p-lg-5">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                        <span class="subscriptions-plan-card__icon d-inline-flex align-items-center justify-content-center rounded-circle"><i class="ci-award"></i></span>
                        <span class="subscriptions-plan-card__duration badge rounded-pill px-3 py-2"><i class="ci-calendar me-1"></i><?= (int)$plan['duration_value'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_' . $plan['duration_unit'])) ?></span>
                    </div>
                    <div class="small text-uppercase fw-semibold text-body-secondary mb-2"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_selected_plan')) ?></div>
                    <h2 class="h3 mb-2"><?= htmlSC((string)$plan['name']) ?></h2>
                    <?php if (trim((string)$plan['description']) !== ''): ?><p class="text-body-secondary mb-4"><?= nl2br(htmlSC((string)$plan['description'])) ?></p><?php endif; ?>
                    <div class="subscriptions-plan-card__price mb-4"><?= htmlSC((string)$plan['price_display']) ?></div>
                    <?php if ($autoRenewEnabled): ?>
                        <div class="alert alert-info rounded-4 small mb-4" role="note">
                            <strong><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_auto_renew')) ?>.</strong>
                            <?= htmlSC(str_replace(
                                [':amount', ':period'],
                                [(string)$plan['price_display'], $renewalPeriod],
                                FireballPluginSubscriptions::t('subscriptions_auto_renew_disclosure')
                            )) ?>
                        </div>
                    <?php endif; ?>
                    <div class="border-top pt-4">
                        <div class="small fw-semibold text-uppercase text-body-secondary mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan_features')) ?></div>
                        <ul class="list-unstyled vstack gap-3 mb-0">
                            <?php foreach ($features as [$permission, $label]): ?>
                                <?php if (!empty($plan['permissions'][$permission])): ?>
                                    <li class="d-flex align-items-start gap-2"><i class="ci-check-circle text-success mt-1 flex-shrink-0"></i><span><?= htmlSC(FireballPluginSubscriptions::t($label)) ?><?php if ($permission === 'camera_archive.view' && !empty($plan['permissions']['camera_archive.max_days'])): ?> — <?= (int)$plan['permissions']['camera_archive.max_days'] ?> <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_duration_days')) ?><?php endif; ?></span></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </article>
        </aside>

        <div class="col-lg-7">
            <article class="subscriptions-checkout-panel border rounded-5 p-4 p-lg-5 mb-4">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="subscriptions-checkout-panel__icon d-inline-flex align-items-center justify-content-center rounded-circle"><i class="ci-user"></i></span>
                        <div><div class="small text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_contact_details')) ?></div><h2 class="h5 mb-0"><?= htmlSC($fullName) ?></h2></div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/profile/subscription-details') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_change_details')) ?></a>
                </div>
                <div class="subscriptions-checkout-contact vstack gap-3">
                    <div class="d-flex align-items-start gap-3"><i class="ci-user text-body-secondary mt-1"></i><span><?= htmlSC((string)$profile['email']) ?><br><span class="text-body-secondary"><?= htmlSC((string)$profile['phone']) ?></span></span></div>
                    <?php if ($address !== ''): ?><div class="d-flex align-items-start gap-3"><i class="ci-map-pin text-body-secondary mt-1"></i><span class="text-body-secondary"><?= htmlSC($address) ?></span></div><?php endif; ?>
                </div>
            </article>

            <form class="subscriptions-checkout-panel border rounded-5 p-4 p-lg-5" action="<?= base_href('/subscriptions/payment/create') ?>" method="post">
                <?= get_csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="subscriptions-checkout-panel__icon d-inline-flex align-items-center justify-content-center rounded-circle"><i class="ci-check-shield"></i></span>
                    <div><h2 class="h5 mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_title')) ?></h2><p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_secure_payment')) ?></p></div>
                </div>

                <div class="subscriptions-checkout-consents rounded-4 p-3 p-md-4 mb-4">
                    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent_offer" value="1" required><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_offer')) ?></span></label>
                    <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="consent_privacy" value="1" required><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_privacy')) ?></span></label>
                    <?php if ($autoRenewEnabled): ?>
                        <hr class="my-3">
                        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="consent_recurring" value="1" required><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_recurring')) ?></span></label>
                    <?php endif; ?>
                </div>

                <button class="btn btn-dark btn-lg rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit"><i class="ci-credit-card"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_pay_robokassa')) ?></button>
            </form>
        </div>
    </div>
</section>
