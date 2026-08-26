<?php

function supportReplyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$projectRoot = dirname(__DIR__);
$controller = (string)file_get_contents($projectRoot . '/app/Controllers/AdminController.php');
$view = (string)file_get_contents($projectRoot . '/app/Views/themes/default/admin/contact_request_reply.php');
$emailView = (string)file_get_contents($projectRoot . '/app/Views/themes/default/admin/contact_request_reply_email.php');
$javascript = (string)file_get_contents($projectRoot . '/public/assets/default/js/main.js');

$replyStart = strpos($controller, 'public function contactRequestReply()');
$nextAction = strpos($controller, 'public function supportFaq()', $replyStart ?: 0);
supportReplyAssert($replyStart !== false && $nextAction !== false, 'The support reply action could not be inspected.');
$replyAction = substr($controller, $replyStart, $nextAction - $replyStart);

supportReplyAssert(
    str_contains($replyAction, "'support_reply_tokens.' . \$requestId")
    && str_contains($replyAction, "request()->post('reply_token', '')")
    && str_contains($replyAction, 'hash_equals($replyToken, $submittedReplyToken)')
    && strpos($replyAction, 'session()->remove($replyTokenKey)') < strpos($replyAction, '$mail->send('),
    'Support replies must consume a one-time token before sending email.'
);
supportReplyAssert(
    str_contains($replyAction, 'session()->set($replyTokenKey, $replyToken)')
    && str_contains($replyAction, "'reply_token' => \$replyToken"),
    'Support reply retries must receive a fresh one-time token.'
);
supportReplyAssert(
    str_contains($replyAction, "view('admin/contact_request_reply_email'")
    && str_contains($replyAction, "'site_logo_url' => site_favicon_url()")
    && str_contains($replyAction, "'brand_color' => \$this->siteSettings->get('pwa_theme_color', '#181d25')")
    && str_contains($replyAction, '$emailHtml,')
    && !str_contains($replyAction, '<div style="font-family:Arial'),
    'Support replies must use the branded HTML email template.'
);
supportReplyAssert(
    str_contains($emailView, '<!doctype html>')
    && str_contains($emailView, 'role="presentation"')
    && str_contains($emailView, 'max-width:640px')
    && str_contains($emailView, '#f55266')
    && str_contains($emailView, 'nl2br(htmlSC($replyMessage))')
    && str_contains($emailView, "return_translation('admin_support_reply_email_badge')")
    && str_contains($emailView, "return_translation('admin_support_reply_email_greeting')")
    && str_contains($emailView, "return_translation('admin_support_reply_email_footer')")
    && str_contains($emailView, '@media only screen and (max-width: 640px)'),
    'The branded support email must be responsive, escaped, and compatible with email clients.'
);
supportReplyAssert(
    str_contains($view, 'data-support-reply-form')
    && str_contains($view, 'name="reply_token"')
    && str_contains($view, 'data-support-reply-submit'),
    'The support reply form is missing duplicate-submission controls.'
);
supportReplyAssert(
    str_contains($javascript, "form.hasAttribute('data-support-reply-submitting')")
    && str_contains($javascript, "form.setAttribute('data-support-reply-submitting', 'true')")
    && str_contains($javascript, 'submitButton.disabled = true'),
    'The support reply submit button is not locked after the first valid submission.'
);

echo "Support reply regression checks passed.\n";
