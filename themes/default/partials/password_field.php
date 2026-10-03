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
$fieldFeedbackId = $fieldId . '-feedback';
$fieldHintId = $fieldId . '-hint';
$fieldDescribedBy = [];
if ($fieldHasError || $fieldErrorFallback !== '') {
    $fieldDescribedBy[] = $fieldFeedbackId;
}
if ($fieldHint !== '') {
    $fieldDescribedBy[] = $fieldHintId;
}
?>
<div class="<?= htmlSC($fieldWrapperClass) ?>">
    <?php if ($fieldLabel !== ''): ?>
        <label class="form-label" for="<?= htmlSC($fieldId) ?>"><?= htmlSC($fieldLabel) ?></label>
    <?php endif; ?>
    <div class="password-toggle password-field__control" data-password-toggle>
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
            <?php if ($fieldDescribedBy !== []): ?>aria-describedby="<?= htmlSC(implode(' ', $fieldDescribedBy)) ?>"<?php endif; ?>
            <?= $fieldHasError ? 'aria-invalid="true"' : '' ?>
        >
        <label
            class="password-toggle-button fs-lg"
            aria-label="<?= htmlSC(return_translation('password_field_toggle')) ?>"
        >
            <input
                type="checkbox"
                class="btn-check"
                aria-controls="<?= htmlSC($fieldId) ?>"
                <?= !empty($disabled) || !empty($readonly) ? 'disabled' : '' ?>
            >
        </label>
    </div>
    <?php if ($fieldHasError): ?>
        <div class="<?= htmlSC($fieldFeedbackClass) ?> d-block" id="<?= htmlSC($fieldFeedbackId) ?>">
            <ul class="list-unstyled mb-0">
                <?php foreach ($fieldErrorItems as $fieldError): ?>
                    <li><?= htmlSC((string)$fieldError) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($fieldErrorFallback !== ''): ?>
        <div class="<?= htmlSC($fieldFeedbackClass) ?>" id="<?= htmlSC($fieldFeedbackId) ?>">
            <?= htmlSC($fieldErrorFallback) ?>
        </div>
    <?php endif; ?>
    <?php if ($fieldHint !== ''): ?>
        <div class="form-text password-field__hint" id="<?= htmlSC($fieldHintId) ?>">
            <?= htmlSC($fieldHint) ?>
        </div>
    <?php endif; ?>
</div>
