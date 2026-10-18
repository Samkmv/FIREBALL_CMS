<?php
$modeDemo=!empty($demo);
$toggleId = $toggle_id ?? 'tr-mini-player-toggle';
?>
<label class="tr-player-mode form-check form-switch" for="<?= htmlSC($toggleId) ?>">
    <span class="tr-player-mode-copy"><strong>Мини-плеер</strong><small>Управление внизу экрана</small></span>
    <input class="form-check-input" type="checkbox" role="switch" id="<?= htmlSC($toggleId) ?>" <?= $modeDemo?'data-tr-demo-mode checked':'data-tr-mini-toggle' ?>>
</label>
<label class="tr-player-mode tr-player-site-mode form-check form-switch" for="<?= htmlSC($toggleId.'-site') ?>">
    <span class="tr-player-mode-copy"><strong>На всех страницах</strong><small>Продолжать музыку при переходах по сайту</small></span>
    <input class="form-check-input" type="checkbox" role="switch" id="<?= htmlSC($toggleId.'-site') ?>" <?= $modeDemo?'data-tr-demo-mode checked':'data-tr-site-player-toggle' ?>>
</label>
