<?php
$toggleId = $toggle_id ?? 'tr-mini-player-toggle';
?>
<label class="tr-player-mode form-check form-switch" for="<?= htmlSC($toggleId) ?>">
    <span class="tr-player-mode-copy"><strong>Мини-плеер</strong><small>Управление внизу экрана</small></span>
    <input class="form-check-input" type="checkbox" role="switch" id="<?= htmlSC($toggleId) ?>" data-tr-mini-toggle>
</label>
