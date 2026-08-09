<section class="container py-5 subscriptions-public">
    <div class="row justify-content-center"><div class="col-lg-8">
        <?php get_alerts(); ?>
        <h1 class="h3 mb-4"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_checkout_title')) ?></h1>
        <div class="border rounded-5 p-4 p-lg-5 mb-4">
            <div class="d-flex justify-content-between gap-3"><div><h2 class="h4"><?= htmlSC((string)$plan['name']) ?></h2><p class="text-body-secondary mb-0"><?= htmlSC((string)$plan['description']) ?></p></div><strong class="h4 text-nowrap"><?= htmlSC((string)$plan['price_display']) ?></strong></div>
            <hr>
            <h3 class="h6"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_contact_details')) ?></h3>
            <p class="mb-1"><?= htmlSC(trim((string)$profile['first_name'] . ' ' . (string)$profile['last_name'])) ?></p>
            <p class="mb-1"><?= htmlSC((string)$profile['email']) ?> · <?= htmlSC((string)$profile['phone']) ?></p>
            <p class="text-body-secondary"><?= htmlSC(implode(', ', array_filter([(string)$profile['country'], (string)$profile['region'], (string)$profile['city'], (string)$profile['street'], (string)$profile['house'], (string)$profile['apartment'], (string)$profile['postal_code']]))) ?></p>
            <a href="<?= base_href('/profile/subscription-details') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_change_details')) ?></a>
        </div>
        <form class="border rounded-5 p-4 p-lg-5" action="<?= base_href('/subscriptions/payment/create') ?>" method="post">
            <?= get_csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent_offer" value="1" required><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_offer')) ?></span></label>
            <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent_privacy" value="1" required><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_privacy')) ?></span></label>
            <?php if ($plan['is_recurring'] && !empty($recurring_available)): ?><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent_recurring" value="1"><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_recurring')) ?></span></label><label class="form-check mb-4"><input class="form-check-input" type="checkbox" name="auto_renew" value="1"><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_enable_auto_renew')) ?></span></label><?php endif; ?>
            <button class="btn btn-dark btn-lg rounded-pill w-100" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_pay_robokassa')) ?></button>
        </form>
    </div></div>
</section>
