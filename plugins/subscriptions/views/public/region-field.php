<?php
$regionCatalog = new \Fireball\Subscriptions\Support\RussianRegionCatalog();
$russianAddress = $regionCatalog->appliesToCountry((string)$old('country'));
$selectedRegion = $regionCatalog->resolve((string)$fieldValue);
$regionSelectConfig = [
    'labelId' => 'subscriptions-region-label',
    'searchEnabled' => true,
    'searchFields' => ['label', 'customProperties.aliases'],
    'searchPlaceholderValue' => FireballPluginSubscriptions::t('subscriptions_region_search'),
    'noResultsText' => FireballPluginSubscriptions::t('subscriptions_region_not_found'),
    'noChoicesText' => FireballPluginSubscriptions::t('subscriptions_region_not_found'),
    'searchResultLimit' => 15,
    'fuseOptions' => ['threshold' => 0.3, 'ignoreLocation' => true],
];
$encodeRegionData = static fn(mixed $value): string => htmlSC(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
?>
<div data-subscriptions-region data-country-value="<?= htmlSC((string)$old('country')) ?>" data-country-aliases="<?= $encodeRegionData($regionCatalog::countryAliases()) ?>">
    <div data-region-russian <?= $russianAddress ? '' : 'hidden' ?>>
        <select class="form-select" name="region" data-region-select data-select="<?= $encodeRegionData($regionSelectConfig) ?>" data-select-clear="false" aria-label="<?= htmlSC((string)$field['label']) ?>" aria-describedby="subscriptions-region-hint" <?= $russianAddress ? '' : 'disabled' ?> <?= $field['is_required'] ? 'required' : '' ?>>
            <option value="" <?= $selectedRegion === null ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_region_select')) ?></option>
            <?php foreach ($regionCatalog->entries() as $region): ?>
                <option value="<?= htmlSC($region['name']) ?>" data-custom-properties="<?= $encodeRegionData(['aliases' => implode(' ', $region['aliases'])]) ?>" <?= $selectedRegion === $region['name'] ? 'selected' : '' ?>><?= htmlSC($region['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text" id="subscriptions-region-hint"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_region_hint')) ?></div>
        <?php if ($selectedRegion === null && trim((string)$fieldValue) !== ''): ?>
            <div class="form-text text-warning"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_region_previous')) ?> <?= htmlSC((string)$fieldValue) ?></div>
        <?php endif; ?>
    </div>
    <div data-region-foreign <?= $russianAddress ? 'hidden' : '' ?>>
        <input class="form-control" name="region" data-region-input type="text" value="<?= htmlSC((string)$fieldValue) ?>" autocomplete="address-level1" aria-label="<?= htmlSC((string)$field['label']) ?>" placeholder="<?= htmlSC((string)$field['placeholder']) ?>" <?= $russianAddress ? 'disabled' : '' ?> <?= $field['is_required'] ? 'required' : '' ?>>
    </div>
</div>
