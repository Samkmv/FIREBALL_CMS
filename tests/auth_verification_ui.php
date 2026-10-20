<?php
declare(strict_types=1);

// Render-only regression: no database, sessions, SMTP or production bootstrap.
$project = dirname(__DIR__);
require $project . '/app/Services/AuthSettings.php';
$messages = [];
$signedIn = false;
$checks = 0;

function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function return_translation(string $key): string {
    global $messages;
    if (!isset($messages[$key])) throw new RuntimeException('Missing translation: ' . $key);
    return $messages[$key];
}
function print_translation(string $key): void { echo htmlSC(return_translation($key)); }
function base_href(string $path): string { return '/cms' . $path; }
function old(string $field): string { return ''; }
function get_validation_class(string $field): string { return ''; }
function get_errors(string $field): string { return ''; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; }
function check_auth(): bool { return $GLOBALS['signedIn']; }
function session(): object { return new class { public function get(string $key): array { return []; } }; }
function view(): VerificationUiRenderer { return new VerificationUiRenderer(); }

final class VerificationUiRenderer
{
    public function render(string $path, array $variables = []): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            include dirname(__DIR__) . '/' . $path;
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
    public function partial(string $name, array $variables = []): string
    {
        return '<input type="password" name="password" required autocomplete="current-password">';
    }
    public function renderPartial(string $name, array $variables = []): string
    {
        return $name === 'auth/verify_email'
            ? $this->render('app/Views/themes/default/auth/verify_email.php', $variables)
            : $this->partial($name, $variables);
    }
}

function verifyUi(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAILED: ' . $label);
    $checks++;
}
function renderedDom(string $path, array $variables = []): DOMXPath
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . view()->render($path, $variables));
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($dom);
}

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $messages = array_merge(require $project . '/app/Languages/' . $locale . '.php',
        require $project . '/app/Languages/' . $locale . '/auth/login.php');
    verifyUi(return_translation('auth_verify_send') !== return_translation('auth_verify_resend'), "$locale: send and resend are separate labels");
    foreach (['auth_email_not_verified', 'auth_verify_code_label', 'auth_verify_code_hint', 'auth_verify_code_expiry', 'auth_verify_code_submit', 'auth_verify_code_resend', 'auth_verify_code_invalid', 'auth_verify_code_sent', 'auth_verify_optional_hint'] as $key) {
        verifyUi(trim(return_translation($key)) !== '', "$locale: code-flow translation $key");
    }

    foreach (['app/Views/themes/default/auth/login.php', 'themes/default/templates/auth/login.php'] as $path) {
        foreach ([true, false] as $recoveryAvailable) {
            $dom = renderedDom($path, ['auth_policy' => App\Services\AuthSettings::DEFAULTS,
                'password_recovery_available' => $recoveryAvailable]);
            $link = $dom->query('//a[@href="/cms/verify-email"]')->item(0);
            verifyUi($link !== null && trim($link->textContent) === return_translation('auth_verify_resend'), "$locale $path: resend link retained");
            verifyUi($link !== null && str_contains($link->parentNode->textContent, return_translation('auth_verify_login_hint')), "$locale $path: hint next to resend link");
            verifyUi($dom->query('//form[@action="/cms/login"]//input[@name="csrf_token"]')->length === 1
                && $dom->query('//form[@action="/cms/forgot-password"]')->length === (int)$recoveryAvailable,
                "$locale $path: login and recovery forms unchanged");
        }
        $dom = renderedDom($path, ['auth_policy' => array_replace(App\Services\AuthSettings::DEFAULTS,
            ['auth_email_verification_required' => '0']), 'password_recovery_available' => true]);
        verifyUi($dom->query('//a[@href="/cms/verify-email"]')->length === 0
            && $dom->query('//form[@action="/cms/login"]|//form[@action="/cms/forgot-password"]')->length === 2,
            "$locale $path: disabling verification hides only the confirmation link");
    }

    foreach (['1', '0'] as $required) {
        $dom = renderedDom('app/Views/themes/default/admin/authentication_settings.php', [
            'settings' => array_replace(App\Services\AuthSettings::DEFAULTS, ['auth_email_verification_required' => $required]),
            'auth_schema_ready' => true,
        ]);
        verifyUi($dom->query('//form[@action="/cms/admin/settings/authentication"]//input[@name="csrf_token"]')->length === 1
            && $dom->query('//input[@name="auth_email_verification_required"][@type="hidden"][@value="0"]')->length === 1
            && $dom->query('//input[@name="auth_email_verification_required"][@type="checkbox"][@value="1"]')->length === 1,
            "$locale admin: protected switch submits both enabled and disabled states");
        verifyUi($dom->query('//input[@name="auth_email_verification_required"][@checked]')->length === (int)($required === '1')
            && trim($dom->evaluate('string(//label[@for="auth-email-verification-required"])')) === return_translation('auth_policy_email_verification')
            && trim($dom->evaluate('string(//*[@id="auth-email-verification-hint"])')) === return_translation('auth_policy_email_verification_hint'),
            "$locale admin: switch and explanation reflect the saved policy");
    }

    foreach (['app/Views/themes/default/auth/verify_email.php', 'themes/default/templates/auth/verify_email.php'] as $path) {
        foreach ([false, true] as $signedIn) {
            $dom = renderedDom($path);
            verifyUi(trim($dom->evaluate('string(//form/button)')) === return_translation('auth_verify_send'), "$locale $path: concise send button");
            verifyUi(trim($dom->evaluate('string(//*[@id="verify-email-hint"])')) === return_translation($signedIn ? 'auth_verify_resend_signed_hint' : 'auth_verify_resend_hint')
                && $dom->query('//form[@aria-describedby="verify-email-hint"]')->length === 1, "$locale $path: relevant accessible hint");
            verifyUi($dom->query('//form[@action="/cms/verify-email"][@method="post"]//input[@name="csrf_token"]')->length === 1
                && $dom->query('//input[@name="verification_action"][@value="resend"]')->length === 1, "$locale $path: protected resend action retained");
            verifyUi($dom->query('//input[@name="email"][@type="email"]')->length === (int)!$signedIn
                && ($signedIn || $dom->query('//input[@name="email"][@type="email"][@required][@autocomplete="email"][@maxlength="190"]')->length === 1), "$locale $path: guest email remains required");
        }

        foreach ([false, true] as $signedIn) foreach ([false, true] as $required) {
            $email = 'owner+code@example.test';
            $variables = ['verification_email' => $email, 'verification_code_ready' => true, 'verification_required' => $required];
            $dom = renderedDom($path, $variables);
            verifyUi($dom->query('//form//input[@name="csrf_token"]')->length === 2
                && $dom->query('//input[@name="verification_action"][@value="confirm_code"]')->length === 1
                && $dom->query('//input[@name="verification_action"][@value="resend"]')->length === 1, "$locale $path: code and resend are distinct protected actions");
            verifyUi($dom->query('//form[input[@name="verification_action"][@value="confirm_code"]]//input[@name="email"][@readonly]')->length === 1
                && $dom->evaluate('string(//input[@id="verify-code-email"]/@value)') === $email
                && $dom->query('//input[@name="email"]')->length === 2, "$locale $path: remembered email appears in both forms");
            verifyUi($dom->query('//input[@name="verification_code"][@type="text"][@inputmode="numeric"][@autocomplete="one-time-code"][@minlength="6"][@maxlength="6"][@pattern="[0-9]{6}"][@required]')->length === 1,
                "$locale $path: mobile-friendly code preserves leading zeros and six-digit constraints");
            verifyUi(trim($dom->evaluate('string(//form[input[@name="verification_action"][@value="confirm_code"]]/button)')) === return_translation('auth_verify_code_submit')
                && trim($dom->evaluate('string(//form[input[@name="verification_action"][@value="resend"]]/button)')) === return_translation('auth_verify_code_resend'), "$locale $path: relevant code buttons");
            verifyUi(str_contains($dom->document->textContent, return_translation('auth_verify_optional_hint')) === !$required,
                "$locale $path: optional confirmation explained only when disabled");
        }

        $unsafeEmail = 'owner"><img src=x onerror=alert(1)>@example.test';
        $dom = renderedDom($path, ['verification_email' => $unsafeEmail, 'verification_code_ready' => true]);
        verifyUi($dom->evaluate('string(//input[@id="verify-code-email"]/@value)') === $unsafeEmail
            && $dom->query('//img|//script')->length === 0, "$locale $path: email cannot inject markup");

        $token = 'fixture-token-<>&"';
        $dom = renderedDom($path, ['token' => $token]);
        verifyUi(trim($dom->evaluate('string(//form/button)')) === return_translation('auth_verify_confirm')
            && $dom->evaluate('string(//input[@name="token"]/@value)') === $token, "$locale $path: token confirmation and escaping unchanged");
        verifyUi($dom->query('//input[@name="verification_action"]|//input[@name="email"]')->length === 0
            && $dom->query('//form//input[@name="csrf_token"]')->length === 1, "$locale $path: confirmation stays separate from resend");
    }

    $signedIn = false;
    $mail = view()->render('app/Views/themes/default/auth/email_verification_email.php', ['verification_code' => '012345', 'expires_in_minutes' => 10, 'account_login' => '<owner>']);
    verifyUi(str_contains($mail, '012345') && str_contains($mail, htmlSC('<owner>')) && !str_contains($mail, '<a ')
        && str_contains($mail, htmlSC(sprintf(return_translation('auth_verify_code_mail_expiry'), 10))), "$locale: code email displays exact digits, expiry and escaped username without login link");
    $mail = view()->render('app/Views/themes/default/auth/email_verification_email.php', ['verification_url' => '/verify-email?token=legacy', 'account_login' => 'owner']);
    verifyUi(str_contains($mail, 'href="/verify-email?token=legacy"') && str_contains($mail, htmlSC(return_translation('auth_verify_confirm'))), "$locale: old link-mail rendering remains compatible");
}

$settings = new App\Services\AuthSettings();
verifyUi($settings->emailVerificationRequired(App\Services\AuthSettings::DEFAULTS)
    && !$settings->emailVerificationRequired(['auth_email_verification_required' => '0']), 'Verification defaults on and explicit off is honored');
verifyUi($settings->normalize(['auth_email_verification_required' => '1'])['auth_email_verification_required'] === '1'
    && $settings->normalize([])['auth_email_verification_required'] === '0', 'Settings checkbox normalizes enabled and disabled states');

echo "PASS: $checks email-verification UI checks across 4 languages. No email sent or database changed.\n";
