<?php

return static function (array $payer, array $profileFields = [], array $customFieldDetails = []): string {
    if ($payer === []) {
        return '<div class="subscriptions-payer-empty text-body-secondary">'
            . '<i class="ci-user-x" aria-hidden="true"></i>'
            . '<span>' . htmlSC(FireballPluginSubscriptions::t('subscriptions_no_payer_data')) . '</span>'
            . '</div>';
    }

    $address = is_array($payer['address'] ?? null) ? $payer['address'] : [];
    $systemFields = [
        'first_name', 'last_name', 'middle_name', 'email', 'phone', 'country',
        'region', 'city', 'street', 'house', 'apartment', 'postal_code',
    ];
    $fieldMeta = [];
    foreach ($profileFields as $field) {
        $key = (string)($field['field_key'] ?? '');
        if ($key !== '') {
            $fieldMeta[$key] = [
                'label' => (string)($field['label'] ?? $key),
                'type' => (string)($field['field_type'] ?? 'text'),
            ];
        }
    }
    foreach ($customFieldDetails as $key => $field) {
        if (!isset($fieldMeta[$key])) {
            $fieldMeta[$key] = [
                'label' => (string)($field['label'] ?? $key),
                'type' => (string)($field['type'] ?? 'text'),
            ];
        }
    }

    $renderValue = static function (mixed $value, string $type = 'text'): string {
        if (in_array($type, ['checkbox', 'boolean'], true)) {
            return htmlSC(FireballPluginSubscriptions::t((string)$value === '1'
                ? 'subscriptions_value_yes'
                : 'subscriptions_value_no'));
        }
        $value = trim(is_scalar($value) ? (string)$value : '');

        return $value === '' ? '<span class="text-body-secondary">—</span>' : nl2br(htmlSC($value));
    };

    ob_start();
    ?>
    <div class="subscriptions-payer-grid">
        <?php foreach ($systemFields as $key): ?>
            <?php $value = array_key_exists($key, $payer) ? $payer[$key] : ($address[$key] ?? ''); ?>
            <div class="subscriptions-payer-field">
                <span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_field_' . $key)) ?></span>
                <span class="subscriptions-payer-field__value"><?= $renderValue($value) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php $customFields = is_array($payer['custom_fields'] ?? null) ? $payer['custom_fields'] : []; ?>
    <?php if ($customFields !== []): ?>
        <h4 class="h6 mt-4 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_custom_fields')) ?></h4>
        <div class="subscriptions-payer-grid">
            <?php foreach ($customFields as $key => $value): ?>
                <?php $meta = $fieldMeta[(string)$key] ?? ['label' => (string)$key, 'type' => 'text']; ?>
                <div class="subscriptions-payer-field">
                    <span class="subscriptions-payer-field__label"><?= htmlSC((string)$meta['label']) ?></span>
                    <span class="subscriptions-payer-field__value"><?= $renderValue($value, (string)$meta['type']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php

    return trim((string)ob_get_clean());
};
