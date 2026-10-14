<label class="tr-field" for="tr-<?= htmlSC($name) ?>">
    <span><?= htmlSC($label) ?></span>
    <input id="tr-<?= htmlSC($name) ?>" name="<?= htmlSC($name) ?>" type="<?= htmlSC($type ?? 'text') ?>" value="<?= ($type ?? '') === 'password' ? '' : htmlSC((string)((session()->get('form_data', []) ?: [])[$name] ?? $value ?? '')) ?>" autocomplete="<?= htmlSC($autocomplete ?? 'off') ?>" <?= empty($optional) ? 'required' : '' ?><?php if (isset($minlength)): ?> minlength="<?= (int)$minlength ?>"<?php endif; ?><?php if (isset($pattern)): ?> pattern="<?= htmlSC($pattern) ?>"<?php endif; ?>>
</label>
