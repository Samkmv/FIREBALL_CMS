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
    }

    foreach (['app/Views/themes/default/auth/verify_email.php', 'themes/default/templates/auth/verify_email.php'] as $path) {
        foreach ([false, true] as $signedIn) {
            $dom = renderedDom($path);
            verifyUi(trim($dom->evaluate('string(//form/button)')) === return_translation('auth_verify_send'), "$locale $path: concise send button");
            verifyUi(trim($dom->evaluate('string(//*[@id="verify-email-hint"])')) === return_translation($signedIn ? 'auth_verify_resend_signed_hint' : 'auth_verify_resend_hint')
                && $dom->query('//form[@aria-describedby="verify-email-hint"]')->length === 1, "$locale $path: relevant accessible hint");
            verifyUi($dom->query('//form[@action="/cms/verify-email"][@method="post"]//input[@name="csrf_token"]')->length === 1
                && $dom->query('//input[@name="verification_action"][@value="resend"]')->length === 1, "$locale $path: protected resend action retained");
            verifyUi($dom->query('//input[@name="email"]')->length === (int)!$signedIn
                && ($signedIn || $dom->query('//input[@name="email"][@type="email"][@required][@autocomplete="email"][@maxlength="190"]')->length === 1), "$locale $path: guest email remains required");
        }

        $token = 'fixture-token-<>&"';
        $dom = renderedDom($path, ['token' => $token]);
        verifyUi(trim($dom->evaluate('string(//form/button)')) === return_translation('auth_verify_confirm')
            && $dom->evaluate('string(//input[@name="token"]/@value)') === $token, "$locale $path: token confirmation and escaping unchanged");
        verifyUi($dom->query('//input[@name="verification_action"]|//input[@name="email"]')->length === 0
            && $dom->query('//form//input[@name="csrf_token"]')->length === 1, "$locale $path: confirmation stays separate from resend");
    }
}

echo "PASS: $checks email-verification UI checks across 4 languages. No email sent or database changed.\n";
