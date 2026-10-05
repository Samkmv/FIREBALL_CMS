<?php
$notifications = is_array($notifications ?? null) ? $notifications : [];
$notificationRows = [];
$notificationCards = [];
$statusLabels = [
    'queued', 'sent', 'failed', 'invalid', 'no_subscriptions', 'disabled', 'user_disabled',
];
foreach ($notifications as $notification) {
    $privateChat = !empty($notification['private_chat']) || \App\Services\NotificationPrivacy::isChat($notification);
    $title = $privateChat ? return_translation('admin_pwa_private_chat_title') : (string)($notification['title'] ?? '');
    $body = $privateChat ? return_translation('admin_pwa_private_chat_hint') : (string)($notification['body'] ?? '');
    $status = (string)($notification['status'] ?? '');
    $sent = (int)($notification['sent_count'] ?? 0);
    $failed = (int)($notification['failed_count'] ?? 0);
    $statusKey = $status === 'sent' && $failed > 0 ? 'partial' : $status;
    $statusLabel = in_array($statusKey, [...$statusLabels, 'partial'], true)
        ? return_translation('admin_pwa_notification_status_' . $statusKey) : $status;
    $statusColor = match ($statusKey) {
        'sent' => 'success', 'failed', 'invalid' => 'danger', 'partial' => 'warning', default => 'secondary',
    };
    $badge = '<span class="badge rounded-pill text-' . $statusColor . ' bg-' . $statusColor . '-subtle">' . htmlSC($statusLabel) . '</span>';
    $description = '<div class="fw-medium text-break">' . htmlSC($title) . '</div><div class="small text-body-secondary text-break">' . htmlSC($body) . '</div>';
    $created = (string)($notification['created_at'] ?? '—');
    $notificationRows[] = ['cells' => [
        ['value' => '#' . (int)$notification['id']], ['html' => $description], ['html' => $badge],
        ['value' => $sent . ' / ' . $failed, 'class' => 'text-nowrap'], ['value' => $created, 'class' => 'text-nowrap'],
    ]];
    $notificationCards[] = [
        'id' => '#' . (int)$notification['id'], 'title' => $title, 'status' => ['html' => $badge],
        'status_label' => return_translation('admin_pwa_status'),
        'extra_fields' => [
            ['label' => return_translation('admin_pwa_notification'), 'value' => $body],
            ['label' => return_translation('admin_pwa_sent'), 'value' => $sent . ' / ' . $failed],
            ['label' => return_translation('admin_pwa_date'), 'value' => $created],
        ],
    ];
}
?>
<section class="border rounded-5 p-3 p-md-4 admin-table-card" id="pwa-notifications">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 mb-0"><?= print_translation('admin_pwa_notifications_title') ?></h2>
        <?php if (!empty($notifications_total)): ?>
            <form action="<?= base_href('/admin/settings/pwa/notifications/clear') ?>" method="post"
                  data-admin-delete-form
                  data-confirm-title="<?= htmlSC(return_translation('admin_pwa_notifications_clear')) ?>"
                  data-delete-message="<?= htmlSC(return_translation('admin_pwa_notifications_clear_confirm')) ?>"
                  data-delete-item="<?= (int)$notifications_total ?>"
                  data-confirm-item-label="<?= htmlSC(return_translation('admin_pwa_notifications_title')) ?>"
                  data-confirm-hint="<?= htmlSC(return_translation('admin_pwa_notifications_clear_hint')) ?>"
                  data-delete-confirm-label="<?= htmlSC(return_translation('admin_pwa_notifications_clear')) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="scope" value="all">
                <input type="hidden" name="devices_page" value="<?= max(1, (int)request()->get('devices_page', 1)) ?>">
                <button type="submit" class="btn btn-outline-danger rounded-pill" style="min-height: 44px;"><i class="ci-trash me-2" aria-hidden="true"></i><?= print_translation('admin_pwa_notifications_clear') ?></button>
            </form>
        <?php endif; ?>
    </div>
    <p class="small text-body-secondary mb-3"><?= print_translation('admin_pwa_notifications_privacy_hint') ?></p>
    <p class="small text-body-secondary mb-3"><?= print_translation('admin_pwa_notifications_delivery_hint') ?></p>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => 'ID'], ['label' => return_translation('admin_pwa_notification')], ['label' => return_translation('admin_pwa_status')],
            ['label' => return_translation('admin_pwa_sent')], ['label' => return_translation('admin_pwa_date')],
        ],
        'rows' => $notificationRows, 'mobile_cards' => $notificationCards,
    ]) ?>
    <?= view()->renderPartial('admin/partials/table_footer', [
        'visible' => count($notifications), 'total' => (int)($notifications_total ?? 0), 'pagination' => $notifications_pagination ?? null,
    ]) ?>
</section>
