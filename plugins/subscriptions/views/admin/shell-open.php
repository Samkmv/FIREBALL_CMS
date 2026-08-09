<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginSubscriptions::t('subscriptions_admin_title'),
    'subtitle' => FireballPluginSubscriptions::t('subscriptions_admin_subtitle'),
    'container_class' => 'subscriptions-admin',
]) ?>

<?php require __DIR__ . '/tabs.php'; ?>
