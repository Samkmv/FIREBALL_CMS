<?php
$values = array_replace((array)($exclusion ?? []), (array)($form_data ?? []));
$isEditing = !empty($exclusion['id']);
$isActive = array_key_exists('is_active', (array)($form_data ?? []))
    ? !empty($form_data['is_active'])
    : ($isEditing ? !empty($exclusion['is_active']) : true);
$matchedUsers = is_array($matched_users ?? null) ? $matched_users : [];
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="border rounded-4 p-4 p-lg-5" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="subscriptionExclusionAddress"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_address')) ?></label>
                <input class="form-control" id="subscriptionExclusionAddress" name="address" maxlength="500" value="<?= htmlSC((string)($values['address'] ?? '')) ?>" placeholder="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_address_placeholder')) ?>" required>
                <div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_address_hint')) ?></div>
            </div>
            <?php if (trim((string)($values['normalized_address'] ?? '')) !== ''): ?>
                <div class="col-12">
                    <label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_normalized')) ?></label>
                    <input class="form-control font-monospace" value="<?= htmlSC((string)$values['normalized_address']) ?>" readonly>
                </div>
            <?php endif; ?>
            <div class="col-12">
                <label class="form-label" for="subscriptionExclusionComment"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_comment')) ?></label>
                <textarea class="form-control" id="subscriptionExclusionComment" name="comment" rows="4"><?= htmlSC((string)($values['comment'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_active')) ?></span>
                </label>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/subscriptions/exclusions')) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_cancel')) ?></a>
        </div>
    </form>

    <?php if ($isEditing): ?>
        <section class="border rounded-4 p-4 mt-4">
            <h2 class="h5 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_matched_users')) ?>: <?= count($matchedUsers) ?></h2>
            <?php if ($matchedUsers): ?>
                <div class="vstack gap-2">
                    <?php foreach ($matchedUsers as $user): ?>
                        <div class="d-flex flex-wrap justify-content-between gap-2 border rounded-3 p-3">
                            <div><strong><?= htmlSC((string)$user['name']) ?></strong><div class="small text-body-secondary"><?= htmlSC((string)$user['email']) ?></div></div>
                            <div class="text-body-secondary"><?= htmlSC(implode(', ', array_filter([(string)$user['street'], (string)$user['house'], (string)$user['apartment']]))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_exclusion_no_matched_users')) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php require __DIR__ . '/shell-close.php'; ?>
