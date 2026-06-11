<?php
$fieldId = (string)($id ?? $name ?? '');
$fieldName = (string)($name ?? '');
$fieldLabel = (string)($label ?? '');
$fieldValue = (string)($value ?? '');
$fieldPlaceholder = (string)($placeholder ?? '');
$fieldAutocomplete = (string)($autocomplete ?? 'current-password');
$fieldInputClass = trim('form-control password-field__input ' . (string)($input_class ?? '') . ' ' . get_validation_class($fieldName));
$fieldWrapperClass = trim('password-field ' . (string)($wrapper_class ?? ''));
$fieldErrors = session()->get('form_errors') ?: [];
$fieldErrorItems = (array)($fieldErrors[$fieldName] ?? []);
$fieldErrorFallback = (string)($error_fallback ?? '');
$fieldHasError = !empty($fieldErrorItems);
$fieldHint = (string)($hint ?? '');
$fieldFeedbackClass = trim((string)($feedback_class ?? 'invalid-feedback'));
?>
<div class="<?= htmlSC($fieldWrapperClass) ?>">
    <?php if ($fieldLabel !== ''): ?>
        <label class="form-label" for="<?= htmlSC($fieldId) ?>"><?= htmlSC($fieldLabel) ?></label>
    <?php endif; ?>
    <div class="password-toggle password-field__control">
        <input
            id="<?= htmlSC($fieldId) ?>"
            type="password"
            name="<?= htmlSC($fieldName) ?>"
            class="<?= htmlSC($fieldInputClass) ?>"
            <?php if ($fieldValue !== ''): ?>value="<?= htmlSC($fieldValue) ?>"<?php endif; ?>
            <?php if ($fieldPlaceholder !== ''): ?>placeholder="<?= htmlSC($fieldPlaceholder) ?>"<?php endif; ?>
            autocomplete="<?= htmlSC($fieldAutocomplete) ?>"
            <?= !empty($required) ? 'required' : '' ?>
            <?= !empty($disabled) ? 'disabled' : '' ?>
            <?= !empty($readonly) ? 'readonly' : '' ?>
            <?php if (isset($minlength)): ?>minlength="<?= (int)$minlength ?>"<?php endif; ?>
        >
        <label
            class="password-toggle-button fs-lg"
            aria-label="<?= htmlSC(return_translation('password_field_toggle')) ?>"
        >
            <input type="checkbox" class="btn-check" <?= !empty($disabled) ? 'disabled' : '' ?>>
        </label>
        <?php if ($fieldHasError): ?>
            <div class="<?= htmlSC($fieldFeedbackClass) ?> d-block">
                <ul class="list-unstyled mb-0">
                    <?php foreach ($fieldErrorItems as $fieldError): ?>
                        <li><?= htmlSC((string)$fieldError) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($fieldErrorFallback !== ''): ?>
            <div class="<?= htmlSC($fieldFeedbackClass) ?>"><?= htmlSC($fieldErrorFallback) ?></div>
        <?php endif; ?>
    </div>
    <?php if ($fieldHint !== ''): ?>
        <div class="form-text password-field__hint"><?= htmlSC($fieldHint) ?></div>
    <?php endif; ?>
</div>
