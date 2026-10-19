<h1><?= print_translation('auth_verify_mail_subject') ?></h1>
<p><?= print_translation('auth_identifier_login') ?>: <?= htmlSC($account_login ?? '') ?></p>
<p><?= print_translation('auth_verify_mail_body') ?></p>
<p><a href="<?= htmlSC($verification_url) ?>"><?= print_translation('auth_verify_confirm') ?></a></p>
<p><?= print_translation('auth_verify_mail_expiry') ?></p>
