<?php

declare(strict_types=1);

$checks = 0;
function accountCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

function accountFixture(string $scenario, string $locale = 'ru'): DOMXPath
{
    $html = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/payment-admin.php') . ' ' . escapeshellarg($scenario) . ' ' . escapeshellarg($locale));
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $t = require __DIR__ . '/../lang/' . $locale . '.php';
    foreach (['account', 'account-recurring', 'account-renewal-off', 'account-utility', 'account-grace', 'account-cancelled', 'account-empty', 'account-long', 'account-no-permissions'] as $scenario) {
        $xpath = accountFixture($scenario, $locale);
        $body = $xpath->query('//body')->item(0)->textContent;
        $card = '//article[@aria-labelledby="subscription-plan-title"]';
        $context = $scenario . '/' . $locale;
        accountCheck(!str_contains($body, 'subscriptions_'), $context . ': no untranslated labels');
        accountCheck($xpath->query('//table//td')->length === 5, $context . ': existing payment table is retained');
        accountCheck(str_contains($body, '#120001'), $context . ': payment history remains visible');
        if ($scenario === 'account-empty') {
            accountCheck($xpath->query($card)->length === 0, $context . ': no active card for empty state');
            accountCheck(str_contains($body, $t['subscriptions_no_subscription_title']), $context . ': empty state is preserved');
            continue;
        }
        accountCheck($xpath->query('//article[@aria-labelledby="subscription-plan-title"]')->length === 1, $context . ': card has an accessible title');
        accountCheck($xpath->query($card . '//h2')->length === 1, $context . ': plan title exists');
        accountCheck($xpath->query($card . '//dl//dt')->length === 2, $context . ': date and renewal details remain available');
        accountCheck($xpath->query($card . '//a[@href="/profile/subscription-details"]')->length === 1, $context . ': profile action is preserved');
        accountCheck($xpath->query($card . '//ul/li')->length === ($scenario === 'account-no-permissions' ? 0 : ($scenario === 'account-long' ? 4 : 3)), $context . ': only enabled permissions are displayed');
        accountCheck(!str_contains($body, $t['subscriptions_permission_camera_archive_download']), $context . ': unavailable permission is not advertised');
        $recurring = $scenario === 'account-recurring';
        accountCheck($xpath->query('//article//form[@action="/account/subscription/auto-renew"]')->length === ($recurring ? 1 : 0), $context . ': renewal action uses subscription snapshot');
        if ($recurring) {
            accountCheck(str_contains($body, $t['subscriptions_disable_auto_renew']), $context . ': cancel renewal action is explicit');
            accountCheck(str_contains($body, $t['subscriptions_auto_renew_cancel_hint']), $context . ': retained access is explained before cancellation');
            accountCheck($xpath->query('//form/input[@name="csrf"]')->length === 1, $context . ': renewal form keeps CSRF');
            accountCheck($xpath->query('//form/input[@name="enabled" and @value="0"]')->length === 1, $context . ': action only disables auto-renew');
            accountCheck(str_contains($body, str_replace(':date', '11.09.2026', $t['subscriptions_account_next_payment'])), $context . ': actual scheduled billing date is shown');
        }
        if ($scenario === 'account-renewal-off') {
            accountCheck(str_contains($body, str_replace(':date', '11.09.2026', $t['subscriptions_auto_renew_cancelled_until'])), $context . ': opt-out explains the unchanged access date');
            accountCheck(str_contains($xpath->query($card . '//header')->item(0)->textContent, $t['subscriptions_subscription_status_active']), $context . ': subscription still shows active after opt-out');
        }
        if ($scenario === 'account-utility') {
            accountCheck(str_contains($body, $t['subscriptions_indefinite']), $context . ': utility access has no expiry');
            accountCheck(str_contains($body, $t['subscriptions_address_included_in_utilities']), $context . ': utility explanation is retained');
            accountCheck($xpath->query('//a[contains(@href,"/subscriptions/checkout/") or @href="/subscriptions/plans"]')->length === 0, $context . ': no purchase prompts for utilities');
        } else {
            accountCheck($xpath->query('//article//a[@href="/subscriptions/checkout/9"]')->length === 1, $context . ': renewal uses original plan endpoint');
        }
        if ($scenario === 'account-grace') {
            accountCheck(str_contains($xpath->query('//article//dl/dd|//article//dl/div/dd')->item(0)->textContent, '18.09.2026'), $context . ': grace period shows its actual access end date');
            accountCheck(str_contains($body, $t['subscriptions_status_grace_period']), $context . ': grace status is not presented as regular active');
        }
        if ($scenario === 'account-cancelled') accountCheck(str_contains($body, $t['subscriptions_status_cancelled']), $context . ': cancellation remains explicit');
        if ($scenario === 'account-long') accountCheck(str_contains($body, ': 30'), $context . ': numeric permission limit retained');
    }
}
echo "Account view tests passed: {$checks} checks." . PHP_EOL;
