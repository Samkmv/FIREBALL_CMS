<?php if (!is_array($user_sessions ?? null)): ?>
    <div class="alert alert-warning"><?= print_translation('account_feature_unavailable') ?></div>
<?php else: ?>
<section class="profile-account-section border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-2"><?= print_translation('account_sessions') ?></h2>
    <p class="text-body-secondary mb-4"><?= print_translation('account_sessions_hint') ?></p>
    <div class="table-responsive position-relative">
        <table class="table align-middle mb-0">
            <thead><tr><th><?= print_translation('account_device') ?></th><th><?= print_translation('account_last_activity') ?></th><th>IP</th><th><?= print_translation('account_login_time') ?></th><th><span class="visually-hidden"><?= print_translation('account_sign_out') ?></span></th></tr></thead>
            <tbody>
            <?php foreach ($user_sessions['items'] as $device): ?>
                <tr>
                    <td><div class="fw-medium text-nowrap"><?= htmlSC($device['browser'] . ($device['browser_version'] !== '' ? ' ' . $device['browser_version'] : '') . ' · ' . $device['os']) ?></div><?php if ($device['is_current']): ?><span class="badge bg-success-subtle text-success mt-1"><?= print_translation('account_this_device') ?></span><?php endif; ?></td>
                    <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime($device['last_activity_at']))) ?></td>
                    <td class="text-nowrap"><?= htmlSC($device['ip_address']) ?></td>
                    <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime($device['created_at']))) ?></td>
                    <td><form action="<?= base_href('/profile/sessions/revoke') ?>" method="post"><?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$device['id'] ?>"><button type="submit" class="btn btn-outline-danger rounded-pill text-nowrap"><?= print_translation('account_sign_out') ?></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($user_sessions['pagination']['total_pages'] > 1): ?><div class="pt-3"><?= $user_sessions['pagination']->getHtml() ?></div><?php endif; ?>
</section>
<form class="profile-account-section border rounded-5 p-3 p-md-4" method="post" action="<?= base_href('/profile/sessions/others') ?>">
    <?= get_csrf_field() ?>
    <label class="form-label" for="sessions-current-password"><?= print_translation('auth_profile_current_password') ?></label>
    <input class="form-control mb-3" id="sessions-current-password" name="current_password" type="password" autocomplete="current-password" required>
    <p class="small text-body-secondary"><?= print_translation('account_sessions_password_hint') ?></p>
    <div class="d-flex flex-wrap gap-3">
        <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= print_translation('account_sign_out_others') ?></button>
        <button class="btn btn-outline-danger rounded-pill" type="submit" formaction="<?= base_href('/profile/sessions/all') ?>"><?= print_translation('account_sign_out_all') ?></button>
    </div>
</form>
<?php endif; ?>
