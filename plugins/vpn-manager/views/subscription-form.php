<?php
use Fireball\VpnManager\Support\Formatter;

$users = is_array($users ?? null) ? $users : [];
$plans = is_array($plans ?? null) ? $plans : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$now = date('Y-m-d\TH:i');
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_subscription_create_title'),
    'subtitle' => $subtitle ?? '',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/subscriptions')) . '">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_back_to_list')) . '</a>',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/plugins/vpn-manager/subscriptions/create') ?>" method="post">
        <?= get_csrf_field() ?>

        <?php if (empty($plans)): ?>
            <div class="alert alert-warning">
                <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_empty_plans')) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_user')) ?></label>
                <select class="form-select" name="user_id" required>
                    <option value=""></option>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $label = trim((string)($user['name'] ?? ''));
                        $secondary = trim((string)($user['email'] ?? $user['login'] ?? ''));
                        if ($label === '') {
                            $label = $secondary !== '' ? $secondary : ('#' . (int)$user['id']);
                        }
                        ?>
                        <option value="<?= (int)$user['id'] ?>">
                            <?= htmlSC($label . ($secondary !== '' && $secondary !== $label ? ' · ' . $secondary : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_plan')) ?></label>
                <select class="form-select" name="plan_id" required>
                    <option value=""></option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int)$plan['id'] ?>">
                            <?= htmlSC((string)$plan['name']) ?> · <?= (int)$plan['duration_days'] ?> <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_days')) ?> · <?= htmlSC(Formatter::bytes((int)$plan['traffic_limit_bytes'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_starts_at')) ?></label>
                <input class="form-control" type="datetime-local" name="starts_at" value="<?= htmlSC($now) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_expires_at')) ?></label>
                <input class="form-control" type="datetime-local" name="expires_at" value="">
                <div class="form-text"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_duration_days')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_status')) ?></label>
                <select class="form-select" name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlSC((string)$status) ?>" <?= $status === 'active' ? 'selected' : '' ?>>
                            <?= htmlSC(Formatter::statusLabel((string)$status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="create_clients" value="1" id="vpnCreateClients" checked>
                    <label class="form-check-label fw-medium" for="vpnCreateClients"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_field_create_clients')) ?></label>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit" <?= empty($plans) ? 'disabled' : '' ?>>
                <?= htmlSC(FireballPluginVpnManager::t('vpn_manager_save')) ?>
            </button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins/vpn-manager/subscriptions') ?>"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
