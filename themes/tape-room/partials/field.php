<?php
$fieldId=$id??'tr-'.$name;$inputType=$type??'text';$fieldErrors=(array)((session()->get('form_errors',[])?:[])[$name]??[]);
$fieldValue=$inputType==='password'?'':(string)((session()->get('form_data',[])?:[])[$name]??$value??'');
?>
<div class="tr-field">
    <label class="form-label" for="<?= htmlSC($fieldId) ?>"><?= htmlSC($label) ?></label>
    <?php if ($inputType==='password'): ?><div class="input-group"><?php endif; ?>
    <input class="form-control <?= $fieldErrors?'is-invalid':'' ?>" id="<?= htmlSC($fieldId) ?>" name="<?= htmlSC($name) ?>" type="<?= htmlSC($inputType) ?>" value="<?= htmlSC($fieldValue) ?>" autocomplete="<?= htmlSC($autocomplete??'off') ?>" <?= empty($optional)?'required':'' ?><?php if(isset($minlength)): ?> minlength="<?= (int)$minlength ?>"<?php endif; ?><?php if(isset($pattern)): ?> pattern="<?= htmlSC($pattern) ?>"<?php endif; ?><?php if($fieldErrors): ?> aria-invalid="true" aria-describedby="<?= htmlSC($fieldId) ?>-error"<?php endif; ?>>
    <?php if ($inputType==='password'): ?><button class="btn btn-outline-secondary tr-password-toggle" type="button" data-tr-password="<?= htmlSC($fieldId) ?>" aria-label="Показать пароль" aria-controls="<?= htmlSC($fieldId) ?>" aria-pressed="false"><?= $this->partial('site-icon',['name'=>'eye']) ?></button></div><?php endif; ?>
    <?php if($fieldErrors): ?><div class="invalid-feedback d-block" id="<?= htmlSC($fieldId) ?>-error"><?= htmlSC(implode(' ',array_map('strval',$fieldErrors))) ?></div><?php endif; ?>
</div>
