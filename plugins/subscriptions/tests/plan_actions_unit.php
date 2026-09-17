<?php

declare(strict_types=1);

// Render the real plugin/CMS templates without bootstrapping CMS or using a database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

final class FireballPluginSubscriptions
{
    public static array $translations = [];

    public static function t(string $key): string
    {
        return self::$translations[$key] ?? $key;
    }
}

function htmlSC(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_href(string $path = ''): string
{
    return '/cms' . $path;
}

function get_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="plan-actions-test-only">';
}

function return_translation(string $key): string
{
    return $GLOBALS['planActionsCoreTranslations'][$key] ?? $key;
}

function view(): object
{
    return new class {
        public function renderPartial(string $name, array $data = []): string
        {
            if ($name === 'admin/shell_open') return '<main class="subscriptions-admin">';
            if ($name === 'admin/shell_close') return '</main>';
            if (!in_array($name, ['admin/partials/table', 'admin/partials/responsive_table_cards'], true)) {
                throw new RuntimeException('Unexpected template: ' . $name);
            }
            extract($data, EXTR_SKIP);
            ob_start();
            require dirname(__DIR__, 3) . '/app/Views/themes/default/' . $name . '.php';
            return (string)ob_get_clean();
        }
    };
}

function planActionsFixture(array $plans, string $locale): DOMXPath
{
    FireballPluginSubscriptions::$translations = require __DIR__ . '/../lang/' . $locale . '.php';
    $GLOBALS['planActionsCoreTranslations'] = require dirname(__DIR__, 3) . '/public/lang/' . $locale . '.php';
    ob_start();
    require __DIR__ . '/../views/admin/plans.php';
    $html = (string)ob_get_clean();
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

$checks = 0;
function planActionsCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$fixtures = [
    ['id' => 17, 'name' => 'First plan', 'slug' => 'first', 'is_active' => 1, 'is_public' => 1, 'is_popular' => 1, 'duration_value' => 30, 'duration_unit' => 'days', 'price_display' => '150 RUB'],
    ['id' => 23, 'name' => '<script>alert("plan")</script>', 'slug' => 'private-"<plan>', 'is_active' => 0, 'is_public' => 0, 'duration_value' => 3, 'duration_unit' => 'months', 'price_display' => '500 RUB'],
];

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $xpath = planActionsFixture($fixtures, $locale);
    $t = FireballPluginSubscriptions::$translations;
    $body = $xpath->document->textContent;
    planActionsCheck(!str_contains($body, 'subscriptions_'), $locale . ': plugin labels are translated');
    planActionsCheck(!str_contains($body, 'admin_posts_col_actions'), $locale . ': mobile action label is translated');
    planActionsCheck($xpath->query('//script')->length === 0, $locale . ': plan text is escaped');
    planActionsCheck($xpath->query('//table/tbody/tr')->length === 2, $locale . ': desktop rows retained');
    planActionsCheck($xpath->query('//*[@data-admin-mobile-table-cards]/*')->length === 2, $locale . ': responsive CMS cards retained');
    planActionsCheck($xpath->query('//a[@href="/cms/admin/subscriptions/plans/create"]')->length === 1, $locale . ': create route retained');
    planActionsCheck($xpath->query('//table/tbody//a[contains(@class,"btn-")]')->length === 0, $locale . ': no separate edit button');
    planActionsCheck($xpath->query('//table/tbody//button[contains(@class,"btn-") and not(@data-bs-toggle="dropdown")]')->length === 0, $locale . ': no separate action buttons');

    foreach ($fixtures as $index => $plan) {
        foreach (['desktop' => '//table/tbody/tr[' . ($index + 1) . ']', 'mobile' => '//*[@data-admin-mobile-table-cards]/*[' . ($index + 1) . ']'] as $layout => $row) {
            $context = $locale . '/' . $layout . '/' . $plan['id'];
            $dropdown = $row . '//*[@data-admin-post-actions-dropdown]';
            planActionsCheck($xpath->query($dropdown)->length === 1, $context . ': one CMS dropdown');
            planActionsCheck($xpath->query($dropdown . '/button[@type="button" and @data-bs-toggle="dropdown" and @aria-expanded="false" and @data-bs-display="static" and @data-bs-boundary="viewport"]')->length === 1, $context . ': existing CMS dropdown behavior');
            $toggle = $xpath->query($dropdown . '/button')->item(0);
            planActionsCheck(($layout === 'desktop' ? trim($toggle->textContent) : $toggle->getAttribute('aria-label')) === ($layout === 'desktop' ? $t['subscriptions_actions'] : return_translation('admin_posts_col_actions')), $context . ': localized accessible action label');
            $menu = $dropdown . '/div[contains(@class,"dropdown-menu")]';
            planActionsCheck($xpath->query($menu)->length === 1, $context . ': menu container retained');
            planActionsCheck($xpath->query($menu . '//*[self::a or self::button]')->length === 4, $context . ': all four actions present');
            $edit = $xpath->query($menu . '/a[@href="/cms/admin/subscriptions/plans/edit/' . $plan['id'] . '"]');
            planActionsCheck($edit->length === 1 && trim($edit->item(0)->textContent) === $t['subscriptions_edit'], $context . ': edit link and translation retained');
            foreach (['toggle_active' => 'subscriptions_toggle', 'toggle_public' => 'subscriptions_visibility', 'clone' => 'subscriptions_clone'] as $action => $label) {
                $form = $menu . '/form[input[@name="action" and @value="' . $action . '"]]';
                planActionsCheck($xpath->query($form . '[@method="post" and @action="/cms/admin/subscriptions/plans/action"]')->length === 1, $context . ': ' . $action . ' stays POST');
                planActionsCheck($xpath->query($form . '/input[@type="hidden" and @name="id" and @value="' . $plan['id'] . '"]')->length === 1, $context . ': ' . $action . ' uses correct plan id');
                planActionsCheck($xpath->query($form . '/input[@name="csrf" and @value="plan-actions-test-only"]')->length === 1, $context . ': ' . $action . ' keeps CSRF');
                $button = $xpath->query($form . '/button[@type="submit" and contains(@class,"dropdown-item")]');
                planActionsCheck($button->length === 1 && trim($button->item(0)->textContent) === $t[$label], $context . ': ' . $action . ' label translated');
            }
            planActionsCheck(str_contains($xpath->query($row)->item(0)->textContent, $t[$plan['is_active'] ? 'subscriptions_status_active' : 'subscriptions_status_disabled']), $context . ': plan status is preserved');
        }
    }

    $empty = planActionsFixture([], $locale);
    planActionsCheck($empty->query('//*[@data-admin-post-actions-dropdown]')->length === 0, $locale . ': empty list has no action menus');
    planActionsCheck(str_contains($empty->document->textContent, $t['subscriptions_empty']), $locale . ': translated empty state retained');
}

echo "Plan actions view tests passed: {$checks} checks." . PHP_EOL;
