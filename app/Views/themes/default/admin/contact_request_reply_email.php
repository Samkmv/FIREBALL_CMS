<?php
$siteTitle = trim((string)($site_title ?? SITE_NAME));
$siteTitle = $siteTitle !== '' ? $siteTitle : SITE_NAME;
$siteUrl = trim((string)($site_url ?? ''));
$siteLogoUrl = trim((string)($site_logo_url ?? ''));
$supportEmail = trim((string)($support_email ?? ''));
$requestId = max(0, (int)($request_id ?? 0));
$requestSubject = trim((string)($request_subject ?? ''));
$recipientName = trim((string)($recipient_name ?? ''));
$replyMessage = trim((string)($message ?? ''));
$brandColor = trim((string)($brand_color ?? '#181d25'));
if (preg_match('/^#[0-9a-f]{6}$/i', $brandColor) !== 1) {
    $brandColor = '#181d25';
}
$accentColor = '#f55266';
$preheader = mb_substr(preg_replace('/\s+/u', ' ', $replyMessage) ?: $replyMessage, 0, 140);
$greeting = str_replace(':name', $recipientName, return_translation('admin_support_reply_email_greeting'));
?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title><?= htmlSC($requestSubject !== '' ? $requestSubject : $siteTitle) ?></title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { padding: 16px 8px !important; }
            .email-card { border-radius: 18px !important; }
            .email-header { padding: 24px 20px !important; border-radius: 18px 18px 0 0 !important; }
            .email-content { padding: 28px 20px !important; }
            .email-footer { padding: 20px !important; }
            .email-title { font-size: 24px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef1f6;color:#4e5562;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;line-height:1px;font-size:1px;"><?= htmlSC($preheader) ?></div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#eef1f6;border-collapse:collapse;">
    <tr>
        <td class="email-shell" align="center" style="padding:36px 16px;">
            <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background-color:#ffffff;border:1px solid #e0e5eb;border-radius:24px;border-collapse:separate;overflow:hidden;box-shadow:0 16px 48px rgba(24,29,37,.10);">
                <tr>
                    <td class="email-header" style="padding:28px 32px;background-color:<?= htmlSC($brandColor) ?>;border-radius:24px 24px 0 0;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">
                            <tr>
                                <?php if ($siteLogoUrl !== ''): ?>
                                    <td width="58" valign="middle" style="width:58px;padding-right:14px;">
                                        <img src="<?= htmlSC($siteLogoUrl) ?>" width="44" height="44" alt="" style="display:block;width:44px;height:44px;object-fit:contain;border:0;border-radius:13px;background-color:#ffffff;padding:5px;box-sizing:border-box;">
                                    </td>
                                <?php endif; ?>
                                <td valign="middle">
                                    <div style="margin:0 0 4px;color:#ffffff;font-size:22px;line-height:1.2;font-weight:700;letter-spacing:-.3px;"><?= htmlSC($siteTitle) ?></div>
                                    <div style="margin:0;color:#cad0d9;font-size:13px;line-height:1.4;"><?= htmlSC(return_translation('admin_support_reply_title')) ?></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="height:4px;background-color:<?= htmlSC($accentColor) ?>;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td class="email-content" style="padding:38px 36px 34px;">
                        <div style="display:inline-block;margin:0 0 18px;padding:7px 12px;border:1px solid #fddce0;border-radius:999px;background-color:#feeef0;color:#f2223b;font-size:12px;line-height:1.2;font-weight:700;text-transform:uppercase;letter-spacing:.4px;">
                            <?= $requestId > 0 ? '#' . $requestId . ' · ' : '' ?><?= htmlSC(return_translation('admin_support_reply_email_badge')) ?>
                        </div>
                        <?php if ($requestSubject !== ''): ?>
                            <h1 class="email-title" style="margin:0 0 10px;color:#181d25;font-size:29px;line-height:1.25;font-weight:700;letter-spacing:-.5px;"><?= htmlSC($requestSubject) ?></h1>
                        <?php endif; ?>
                        <?php if ($recipientName !== ''): ?>
                            <p style="margin:0 0 24px;color:#6c727f;font-size:15px;line-height:1.6;"><?= htmlSC($greeting) ?></p>
                        <?php endif; ?>
                        <div style="margin:0;padding:22px 24px;border:1px solid #e0e5eb;border-left:4px solid <?= htmlSC($accentColor) ?>;border-radius:14px;background-color:#f8f9fb;color:#333d4c;font-size:16px;line-height:1.7;word-break:break-word;">
                            <?= nl2br(htmlSC($replyMessage)) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="email-footer" style="padding:22px 36px 26px;border-top:1px solid #eef1f6;background-color:#f8f9fb;border-radius:0 0 24px 24px;color:#9ca3af;font-size:12px;line-height:1.6;text-align:center;">
                        <div style="margin:0 0 6px;"><?= htmlSC(return_translation('admin_support_reply_email_footer')) ?></div>
                        <?php if ($siteUrl !== ''): ?>
                            <a href="<?= htmlSC($siteUrl) ?>" style="color:#6c727f;text-decoration:none;font-weight:700;"><?= htmlSC($siteTitle) ?></a>
                        <?php else: ?>
                            <strong style="color:#6c727f;"><?= htmlSC($siteTitle) ?></strong>
                        <?php endif; ?>
                        <?php if ($supportEmail !== ''): ?>
                            <span style="color:#cad0d9;"> &nbsp;•&nbsp; </span>
                            <a href="mailto:<?= htmlSC($supportEmail) ?>" style="color:#6c727f;text-decoration:none;"><?= htmlSC($supportEmail) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
