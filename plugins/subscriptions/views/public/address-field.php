<?php
$addressId = 'subscriptions-address-' . $key;
$addressHintId = $addressId . '-hint';
?>
<div class="subscriptions-address-field" data-address-field="<?= htmlSC($key) ?>">
    <div data-address-enhanced hidden>
        <select class="form-select" name="<?= htmlSC($name) ?>" data-address-select="<?= htmlSC($key) ?>"
                aria-label="<?= htmlSC((string)$field['label']) ?>" aria-describedby="<?= htmlSC($addressHintId) ?>"
                disabled <?= $field['is_required'] ? 'required' : '' ?>>
            <option value=""><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_address_choose')) ?></option>
            <?php if (trim((string)$fieldValue) !== ''): ?>
                <option value="<?= htmlSC((string)$fieldValue) ?>" selected><?= htmlSC((string)$fieldValue) ?></option>
            <?php endif; ?>
        </select>
    </div>
    <input class="form-control" id="<?= htmlSC($addressId) ?>" type="text" name="<?= htmlSC($name) ?>"
           value="<?= htmlSC((string)$fieldValue) ?>" placeholder="<?= htmlSC((string)$field['placeholder']) ?>"
           autocomplete="<?= $key === 'city' ? 'address-level2' : 'off' ?>" data-address-input="<?= htmlSC($key) ?>"
           aria-label="<?= htmlSC((string)$field['label']) ?>" aria-describedby="<?= htmlSC($addressHintId) ?>"
           <?= $field['is_required'] ? 'required' : '' ?>>
    <div class="form-text" id="<?= htmlSC($addressHintId) ?>" data-address-status aria-live="polite">
        <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_' . $key . '_suggest_hint')) ?>
    </div>
</div>
