<div class="border rounded-4 p-3" data-features-item>
    <label class="d-block mb-3">
        <span class="form-label d-block"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_features_title')) ?></span>
        <input class="form-control" name="features[<?= htmlSC((string)$index) ?>][title]" maxlength="255" value="<?= htmlSC((string)($feature['title'] ?? '')) ?>" required>
    </label>
    <label class="d-block mb-3">
        <span class="form-label d-block"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_description')) ?></span>
        <textarea class="form-control" name="features[<?= htmlSC((string)$index) ?>][description]" maxlength="2000" rows="3"><?= htmlSC((string)($feature['description'] ?? '')) ?></textarea>
    </label>
    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-features-remove><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_delete')) ?></button>
</div>
