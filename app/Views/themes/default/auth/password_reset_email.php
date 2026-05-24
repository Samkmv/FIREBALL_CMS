<div style="font-family: Arial, sans-serif; color: #222; line-height: 1.6;">
    <h2 style="margin-bottom: 16px;"><?= htmlSC(return_translation('auth_reset_email_heading')) ?></h2>
    <p><?= htmlSC(return_translation('auth_reset_email_text')) ?></p>
    <p>
        <a href="<?= htmlSC($reset_url ?? '') ?>" style="display: inline-block; padding: 12px 20px; background: #222; color: #fff; text-decoration: none; border-radius: 999px;">
            <?= htmlSC(return_translation('auth_reset_email_button')) ?>
        </a>
    </p>
    <p><?= htmlSC(str_replace(':minutes', (string)($expires_in_minutes ?? 60), return_translation('auth_reset_email_expire'))) ?></p>
    <p style="word-break: break-all;"><?= htmlSC($reset_url ?? '') ?></p>
</div>
