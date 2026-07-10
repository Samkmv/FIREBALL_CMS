<?php
require_once __DIR__ . '/partials/helpers.php';

$instructions = is_array($instructions ?? null) ? $instructions : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_instructions_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-3">
        <?php foreach ($instructions as $instruction): ?>
            <div class="col-md-6">
                <div class="border rounded-5 p-3 p-md-4 h-100">
                    <div class="d-flex align-items-start gap-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0" style="width:2.5rem;height:2.5rem;"><i class="ci-book-open"></i></span>
                        <div>
                            <h2 class="h6 mb-1"><?= htmlSC((string)$instruction['platform']) ?></h2>
                            <div class="text-body-secondary"><?= htmlSC((string)$instruction['app']) ?></div>
                            <div class="small mt-2"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_instruction_prepared')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
