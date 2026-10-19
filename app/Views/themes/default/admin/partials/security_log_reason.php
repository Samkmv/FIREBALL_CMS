<?php
// Shared by desktop rows and mobile cards. Nothing here is trusted HTML.
$reason = \App\Services\SecurityLogPresenter::reason((string)($log['reason'] ?? ''), (string)($log['result'] ?? ''));
?>
<div class="text-break"><?= htmlSC($reason['message']) ?></div>
<details class="small text-body-secondary mt-2">
    <summary class="cursor-pointer"><?= print_translation('security_details') ?></summary>
    <dl class="mb-0 mt-2 text-break">
        <?php foreach ([
            ['label' => return_translation('security_detail_event'), 'value' => $log['event'] ?? '—'],
            ['label' => return_translation('security_detail_result'), 'value' => $log['result'] ?? '—'],
            ['label' => return_translation('security_detail_reason'), 'value' => $reason['code'] ?: '—'],
            ...$reason['details'],
        ] as $detail): ?>
            <dt class="fw-normal"><?= htmlSC((string)$detail['label']) ?></dt>
            <dd class="mb-2"><?= htmlSC((string)$detail['value']) ?></dd>
        <?php endforeach; ?>
    </dl>
</details>
