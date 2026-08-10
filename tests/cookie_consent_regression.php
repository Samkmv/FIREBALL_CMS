<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/app/Components/CookieConsent.php');

function cookie_consent_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

cookie_consent_assert(
    str_contains($source, 'data-fireball-cookie-consent') && str_contains($source, 'hidden'),
    'The cookie banner must stay hidden until the browser checks stored consent.'
);
cookie_consent_assert(
    str_contains($source, 'if (hasStoredConsent())')
    && strpos($source, 'if (hasStoredConsent())') < strpos($source, 'banner.hidden = false'),
    'Stored consent must be checked before the banner becomes visible.'
);
cookie_consent_assert(
    str_contains($source, "cookieName + '_expires'")
    && str_contains($source, 'window.localStorage.setItem(storageKey, String(expiresAt))'),
    'Consent must have a cache-safe browser storage fallback.'
);
cookie_consent_assert(
    str_contains($source, "'; Expires='") && str_contains($source, '; Max-Age='),
    'The consent cookie must include compatible Max-Age and Expires lifetimes.'
);

echo json_encode([
    'status' => 'ok',
    'prepaint_check' => true,
    'cookie_lifetime' => true,
    'cache_safe_fallback' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
