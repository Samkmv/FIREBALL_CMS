<h1><?= print_translation('auth_verify_mail_subject') ?></h1>
<p><?= print_translation('auth_identifier_login') ?>: <?= htmlSC($account_login ?? '') ?></p>
<?php if (!empty($verification_code)): ?>
<p><?= print_translation('auth_verify_code_mail_body') ?></p>
<p style="font-size: 32px; font-weight: 700; letter-spacing: 8px; font-family: monospace;"><?= htmlSC($verification_code) ?></p>
<p><?= htmlSC(sprintf(return_translation('auth_verify_code_mail_expiry'), (int)($expires_in_minutes ?? 10))) ?></p>
<p><?= print_translation('auth_verify_code_mail_ignore') ?></p>
<?php elseif (!empty($verification_url)): ?>
<p><?= print_translation('auth_verify_mail_body') ?></p>
<p><a href="<?= htmlSC($verification_url) ?>"><?= print_translation('auth_verify_confirm') ?></a></p>
<p><?= print_translation('auth_verify_mail_expiry') ?></p>
<?php endif; ?>
