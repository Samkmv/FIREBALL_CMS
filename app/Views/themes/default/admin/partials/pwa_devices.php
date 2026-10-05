<?php
$devices = is_array($devices ?? null) ? $devices : [];
$deviceRows = [];
$deviceCards = [];
foreach ($devices as $device) {
    $id = (int)$device['id'];
    $deviceName = trim((string)($device['platform'] ?? '') . ' / ' . (string)($device['browser'] ?? ''), ' /');
    $deviceName = $deviceName !== '' ? $deviceName : return_translation('admin_pwa_device');
    $active = !empty($device['is_active']) && empty($device['revoked_at']);
    $badge = '<span class="badge rounded-pill ' . ($active ? 'text-success bg-success-subtle' : 'text-secondary bg-secondary-subtle') . '">' . htmlSC(return_translation($active ? 'admin_pwa_device_linked' : 'admin_pwa_device_inactive')) . '</span>';
    $owner = !empty($device['user_id']) ? '#' . (int)$device['user_id'] : '—';
    $lastSeen = !empty($device['last_seen_at']) ? (string)$device['last_seen_at'] : '—';
    ob_start();
    ?>
    <form action="<?= base_href('/admin/settings/pwa/devices/detach') ?>" method="post"
          data-admin-delete-form
          data-confirm-title="<?= htmlSC(return_translation('admin_pwa_devices_detach_title')) ?>"
          data-delete-message="<?= htmlSC(return_translation('admin_pwa_device_detach_confirm')) ?>"
          data-delete-item="<?= htmlSC($deviceName . ' #' . $id) ?>"
          data-confirm-item-label="<?= htmlSC(return_translation('admin_pwa_device')) ?>"
          data-confirm-hint="<?= htmlSC(return_translation('admin_pwa_devices_detach_hint')) ?>"
          data-delete-confirm-label="<?= htmlSC(return_translation('admin_pwa_device_detach')) ?>">
        <?= get_csrf_field() ?>
        <input type="hidden" name="scope" value="one">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger rounded-pill text-nowrap" style="min-height: 44px;" type="submit">
            <i class="ci-log-out me-2" aria-hidden="true"></i><?= print_translation('admin_pwa_device_detach') ?>
        </button>
    </form>
    <?php
    $actions = ob_get_clean();
    $deviceHtml = '<div class="fw-medium text-break">' . htmlSC($deviceName) . '</div>';
    $mobileBrowserDetails = '';
    if (!empty($device['user_agent'])) {
        $deviceHtml .= '<details class="small text-body-secondary mt-1"><summary>' . htmlSC(return_translation('admin_pwa_device_details')) . '</summary><div class="text-break mt-1">' . htmlSC($device['user_agent']) . '</div></details>';
        $mobileBrowserDetails = '<details class="small"><summary>' . htmlSC((string)($device['browser'] ?: $deviceName)) . '</summary><div class="text-break mt-1">' . htmlSC($device['user_agent']) . '</div></details>';
    }
    $deviceRows[] = ['cells' => [
        ['value' => '#' . $id],
        ['html' => $deviceHtml],
        ['value' => $owner],
        ['html' => $badge],
        ['value' => $lastSeen, 'class' => 'text-nowrap'],
        ['html' => $actions],
    ]];
    $deviceCards[] = [
        'id' => '#' . $id, 'title' => $deviceName, 'status' => ['html' => $badge],
        'status_label' => return_translation('admin_pwa_status'),
        'extra_fields' => [
            ['label' => return_translation('admin_pwa_device_user'), 'value' => $owner],
            ['label' => return_translation('admin_pwa_last_seen'), 'value' => $lastSeen],
            ['label' => return_translation('admin_pwa_device_details'), 'html' => $mobileBrowserDetails],
            ['label' => return_translation('admin_posts_col_actions'), 'html' => $actions],
        ],
    ];
}
?>
<section class="border rounded-5 p-3 p-md-4 admin-table-card" id="pwa-devices">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 mb-0"><?= print_translation('admin_pwa_devices_title') ?></h2>
        <?php if (!empty($devices_total)): ?>
            <form action="<?= base_href('/admin/settings/pwa/devices/detach') ?>" method="post"
                  data-admin-delete-form
                  data-confirm-title="<?= htmlSC(return_translation('admin_pwa_devices_detach_title')) ?>"
                  data-delete-message="<?= htmlSC(return_translation('admin_pwa_devices_detach_all_confirm')) ?>"
                  data-delete-item="<?= (int)$devices_total ?>"
                  data-confirm-item-label="<?= htmlSC(return_translation('admin_pwa_devices_title')) ?>"
                  data-confirm-hint="<?= htmlSC(return_translation('admin_pwa_devices_detach_hint')) ?>"
                  data-delete-confirm-label="<?= htmlSC(return_translation('admin_pwa_devices_detach_all')) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="scope" value="all">
                <button class="btn btn-outline-danger rounded-pill" style="min-height: 44px;" type="submit"><i class="ci-log-out me-2" aria-hidden="true"></i><?= print_translation('admin_pwa_devices_detach_all') ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => 'ID'], ['label' => return_translation('admin_pwa_device')],
            ['label' => return_translation('admin_pwa_device_user')], ['label' => return_translation('admin_pwa_status')],
            ['label' => return_translation('admin_pwa_last_seen')], ['label' => return_translation('admin_posts_col_actions')],
        ],
        'rows' => $deviceRows, 'mobile_cards' => $deviceCards,
    ]) ?>
    <?= view()->renderPartial('admin/partials/table_footer', [
        'visible' => count($devices), 'total' => (int)($devices_total ?? 0), 'pagination' => $devices_pagination ?? null,
    ]) ?>
</section>
