<?php
declare(strict_types=1);
// View-only regression checks; no CMS bootstrap, database or provider requests.
$checks = 0;
function uiCheck(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function uiFixture(string $screen, string $locale, bool $empty = false): DOMXPath {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/payment-admin.php')
        . ' ' . escapeshellarg($screen) . ' ' . escapeshellarg($locale) . ' dark ' . ($empty ? 'empty' : 'full');
    $html = shell_exec($command);
    uiCheck(is_string($html) && !str_contains($html, 'Fatal error'), "$screen/$locale: rendered");
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
$screens = ['overview', 'plans', 'subscribers', 'exclusions', 'payments', 'content', 'fields', 'settings', 'plan-form', 'field-form', 'exclusion-form'];
foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require __DIR__ . '/../lang/' . $locale . '.php';
    foreach ($screens as $screen) {
        $xpath = uiFixture($screen, $locale);
        $context = "$screen/$locale";
        $nav = '//*[@data-subscriptions-admin-nav]';
        uiCheck($xpath->query($nav . '//a')->length === 8, "$context: every section reachable");
        uiCheck($xpath->query($nav . '/a[contains(@class,"btn") and contains(@class,"rounded-pill")]')->length === 5, "$context: five CMS navigation buttons");
        uiCheck($xpath->query($nav . '//a[@aria-current="page"]')->length === 1, "$context: current page marked once");
        uiCheck(str_contains($xpath->query($nav)->item(0)->textContent, $translations['subscriptions_admin_more']), "$context: More translated");
        foreach ($xpath->query('//form[@method="post"]') as $form) {
            uiCheck($xpath->query('.//input[@name="csrf"]', $form)->length === 1, "$context: POST retains CSRF");
        }
        if (in_array($screen, ['plans', 'fields', 'exclusions'], true)) {
            uiCheck($xpath->query('//header//a[contains(@class,"btn-dark")]')->length === 1, "$context: primary action in page header");
            $toggles = $xpath->query('//table//*[@data-admin-post-actions-dropdown]/button');
            uiCheck($toggles->length > 0, "$context: actions grouped");
            foreach ($toggles as $toggle) {
                uiCheck(str_contains($toggle->getAttribute('class'), 'btn-icon rounded-circle'), "$context: VPN-style action control");
                uiCheck($toggle->getAttribute('aria-label') === $translations['subscriptions_actions'], "$context: action has accessible label");
            }
        }
        if ($screen === 'overview') {
            uiCheck($xpath->query('//a[contains(@class,"fb-card") and contains(@class,"fb-vpn-stat-card")]')->length === 4, "$context: shared CMS/VPN metric cards");
        }
        if ($screen === 'exclusions') {
            uiCheck($xpath->query('//table//form[@data-admin-delete-form and @data-delete-message and @method="post"]')->length === 1, "$context: delete confirmation retained");
        }
        if ($screen === 'fields') {
            uiCheck($xpath->query('//table//form')->length === 1, "$context: only custom fields can be deleted");
        }
        if (in_array($screen, ['plan-form', 'field-form', 'exclusion-form', 'settings'], true)) {
            uiCheck($xpath->query('//form[@method="post" and contains(@class,"rounded-5") and contains(@class,"p-md-4")]')->length === 1, "$context: shared form card spacing");
        }
        if ($screen === 'settings') {
            uiCheck($xpath->query('//*[@data-address-import]//input[@type="file" and @disabled and not(@name)]')->length === 1, "$context: oversized native upload still prevented");
        }
    }
    foreach (['plans', 'fields', 'exclusions', 'content'] as $screen) {
        $xpath = uiFixture($screen, $locale, true);
        uiCheck($xpath->query('//*[@data-admin-post-actions-dropdown]')->length === 0, "$screen/$locale: empty list has no row actions");
        $label = $translations[$screen === 'exclusions' ? 'subscriptions_exclusions_empty' : 'subscriptions_empty'];
        uiCheck(str_contains($xpath->document->textContent, $label), "$screen/$locale: empty state retained");
    }
}
echo "Admin UI tests passed: {$checks} checks.\n";
