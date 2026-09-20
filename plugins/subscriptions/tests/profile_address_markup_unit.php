<?php

declare(strict_types=1);

// Checks the actual public profile template; no application/database bootstrap.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = 0;
function addressMarkupCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

function addressMarkupFixture(string $scenario, string $locale, string $theme): DOMXPath
{
    $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, __DIR__ . '/fixtures/profile-address.php', $scenario, $locale, $theme]));
    $html = shell_exec($command);
    if (!is_string($html) || $html === '') throw new RuntimeException('Unable to render isolated profile fixture');
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

$saved = ['city' => 'Железноводск', 'street' => 'Октябрьская', 'house' => '41'];
$scenarios = [
    'saved' => $saved,
    'empty' => array_fill_keys(['city', 'street', 'house'], ''),
    'foreign' => ['city' => 'Munich', 'street' => 'Hauptstraße', 'house' => '5'],
    'old' => ['city' => 'Ранее сохранённый город', 'street' => 'Редкая улица', 'house' => '17А/2'],
    'validation' => ['city' => 'Введённый город', 'street' => 'Введённая улица', 'house' => '9'],
    'unsafe' => array_fill_keys(['city', 'street', 'house'], '"><img src=x onerror=alert(1)>'),
];

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require __DIR__ . '/../lang/' . $locale . '.php';
    foreach (['light', 'dark'] as $theme) {
        foreach ($scenarios as $scenario => $values) {
            $context = $locale . '/' . $theme . '/' . $scenario;
            $xpath = addressMarkupFixture($scenario, $locale, $theme);
            $form = '//form[@data-subscriptions-address-form]';
            addressMarkupCheck($xpath->query($form . '[@method="post"]')->length === 1, $context . ': existing save form retained');
            addressMarkupCheck($xpath->query($form . '[@data-address-suggest-url="/profile/subscription-address/suggest"]')->length === 1, $context . ': local suggestion endpoint');
            addressMarkupCheck($xpath->query($form . '/input[@name="csrf" and @value="profile-address-test-only"]')->length === 1, $context . ': CSRF retained');
            addressMarkupCheck($xpath->query('//html[@data-bs-theme="' . $theme . '"]')->length === 1, $context . ': requested theme retained');
            addressMarkupCheck($xpath->query('//img[@onerror]')->length === 0, $context . ': unsafe saved text cannot create executable markup');
            addressMarkupCheck(!str_contains($xpath->document->textContent, 'subscriptions_'), $context . ': all visible strings translated');
            addressMarkupCheck(!preg_match('/(?:class="dropdown-menu|data-address-suggestions|FIREBALL_SUBSCRIPTIONS_BASIC_DROPDOWN)/', $xpath->document->textContent), $context . ': broken old list markup not printed');
            foreach ($values as $type => $value) {
                $field = $form . '//div[@data-address-field="' . $type . '"]';
                $select = $field . '/div[@data-address-enhanced]/select[@data-address-select="' . $type . '"]';
                $input = $field . '/input[@data-address-input="' . $type . '"]';
                addressMarkupCheck($xpath->query($field)->length === 1, $context . '/' . $type . ': one field wrapper');
                addressMarkupCheck($xpath->query($select . '[@name="' . $type . '" and @disabled and @required]')->length === 1, $context . '/' . $type . ': enhanced select initially disabled');
                addressMarkupCheck($xpath->query($field . '/div[@data-address-enhanced and @hidden]')->length === 1, $context . '/' . $type . ': select hidden before JS enhancement');
                addressMarkupCheck($xpath->query($input . '[@name="' . $type . '" and @required and not(@disabled) and not(@hidden)]')->length === 1, $context . '/' . $type . ': no-JS text fallback submits once');
                addressMarkupCheck($xpath->query($input)->item(0)->getAttribute('value') === $value, $context . '/' . $type . ': typed or saved fallback value retained');
                $selected = $xpath->query($select . '/option[@selected]');
                addressMarkupCheck($value === '' ? $selected->length === 0 : ($selected->length === 1 && $selected->item(0)->getAttribute('value') === $value), $context . '/' . $type . ': saved value available before remote lookup');
                addressMarkupCheck($xpath->query($select)->item(0)->getAttribute('aria-label') === $translations['subscriptions_profile_field_' . $type], $context . '/' . $type . ': select has translated accessible label');
                addressMarkupCheck($xpath->query($input)->item(0)->getAttribute('aria-label') === $translations['subscriptions_profile_field_' . $type], $context . '/' . $type . ': fallback has translated accessible label');
                $hint = $xpath->query($field . '/div[@data-address-status and @aria-live="polite"]');
                addressMarkupCheck($hint->length === 1 && trim($hint->item(0)->textContent) === $translations['subscriptions_' . $type . '_suggest_hint'], $context . '/' . $type . ': translated live status hint');
                addressMarkupCheck($xpath->query($select)->item(0)->getAttribute('aria-describedby') === $hint->item(0)->getAttribute('id'), $context . '/' . $type . ': select connected to status hint');
            }
            addressMarkupCheck($xpath->query($form . '//input[@name="postal_code" and @data-address-postal-code]')->length === 1, $context . ': postal code remains a normal editable field');
            addressMarkupCheck($xpath->query($form . '//input[@name="apartment"]')->length === 1, $context . ': apartment field preserved');
        }
    }
}

echo "Profile address markup tests passed: {$checks} checks." . PHP_EOL;
