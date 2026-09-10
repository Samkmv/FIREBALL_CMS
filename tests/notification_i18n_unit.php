<?php

declare(strict_types=1);

// Render only the real templates' opening tags. No CMS bootstrap, database or network.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$notificationI18nRoot = dirname(__DIR__);
$notificationI18nDictionary = [];
$notificationI18nAssertions = 0;

function notificationI18nAssert(bool $condition, string $message): void
{
    global $notificationI18nAssertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $notificationI18nAssertions++;
}

function htmlSC(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function return_translation(string $key): string
{
    global $notificationI18nDictionary;
    if (!isset($notificationI18nDictionary[$key])) {
        throw new RuntimeException('Missing translation: ' . $key);
    }
    return $notificationI18nDictionary[$key];
}

function base_url(string $path): string { return $path; }
function base_href(string $path): string { return $path; }

function notificationI18nOpeningTag(string $file, string $tag, string $marker): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('Cannot read template: ' . $file);
    }
    // PHP expressions may contain > and quotes; consume each expression atomically.
    preg_match_all('~<' . preg_quote($tag, '~') . '\b(?:[^<>]|<\?.*?\?>)*>~s', $source, $matches);
    foreach ($matches[0] as $fragment) {
        if (str_contains($fragment, $marker)) {
            return $fragment;
        }
    }
    throw new RuntimeException('Missing ' . $marker . ' in ' . $file);
}

/** @return array{0: DOMElement, 1: string} */
function notificationI18nRender(string $fragment, string $tag): array
{
    $isAdminArea = false;
    $pwaHeadData = [];
    $currentUser = [];
    ob_start();
    try {
        eval('?>' . $fragment);
        $html = (string)ob_get_contents();
    } finally {
        ob_end_clean();
    }
    $document = new DOMDocument();
    $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head>'
        . ($tag === 'body' ? $html . '</body>' : '<body>' . $html . '</' . $tag . '></body>') . '</html>');
    $element = $document->getElementsByTagName($tag)->item(0);
    if (!$element instanceof DOMElement) {
        throw new RuntimeException('Rendered tag is missing: ' . $tag);
    }
    return [$element, $html];
}

$notificationAttributes = [
    'data-open-label' => 'notification_open',
    'data-mark-read-label' => 'notification_mark_read',
    'data-load-error' => 'notification_load_error',
    'data-retry-label' => 'notification_retry',
    'data-clear-confirm' => 'notification_clear_confirm',
    'data-clear-success' => 'notification_cleared',
    'data-empty-text' => 'notification_empty',
    'data-chat-source-label' => 'notification_source_chat',
];
$notificationTemplates = [
    'app/Views/layouts/default.php',
    'app/Views/themes/default/admin/topbar.php',
    'themes/default/partials/header.php',
];
$bodyTemplates = ['app/Views/layouts/default.php', 'themes/default/templates/layout.php'];
$notificationScripts = ['public/assets/default/js/main.js', 'themes/default/assets/js/main.js'];
$expectedClose = ['ru' => 'Закрыть', 'en' => 'Close', 'de' => 'Schließen', 'zh-cn' => '关闭'];

try {
    $testedLocales = [];
    foreach (glob($notificationI18nRoot . '/app/Languages/*.php') as $dictionaryFile) {
        $locale = basename($dictionaryFile, '.php');
        $testedLocales[] = $locale;
        $notificationI18nDictionary = require $dictionaryFile;
        notificationI18nAssert(is_array($notificationI18nDictionary), $locale . ': invalid dictionary');
        if (isset($expectedClose[$locale])) {
            notificationI18nAssert(return_translation('notification_close') === $expectedClose[$locale], $locale . ': close label is not translated');
        }
        foreach ($notificationTemplates as $template) {
            $fragment = notificationI18nOpeningTag($notificationI18nRoot . '/' . $template, 'div', 'data-notifications-center');
            [$element] = notificationI18nRender($fragment, 'div');
            foreach ($notificationAttributes as $attribute => $key) {
                notificationI18nAssert($element->hasAttribute($attribute), $locale . '/' . $template . ': missing ' . $attribute);
                notificationI18nAssert($element->getAttribute($attribute) === return_translation($key), $locale . '/' . $template . ': incorrect ' . $attribute);
            }
        }
        foreach ($bodyTemplates as $template) {
            $fragment = notificationI18nOpeningTag($notificationI18nRoot . '/' . $template, 'body', 'data-toast-close-label');
            [$element] = notificationI18nRender($fragment, 'body');
            notificationI18nAssert($element->getAttribute('data-toast-close-label') === return_translation('notification_close'), $locale . '/' . $template . ': untranslated toast close button');
        }
    }
    foreach (array_keys($expectedClose) as $locale) {
        notificationI18nAssert(in_array($locale, $testedLocales, true), 'Required locale was not tested: ' . $locale);
    }
    foreach ($notificationScripts as $script) {
        $source = file_get_contents($notificationI18nRoot . '/' . $script);
        notificationI18nAssert(str_contains($source, "const closeLabel = body.dataset.toastCloseLabel || 'Close';"), $script . ': toast does not use the translated body attribute');
        $closeControls = substr_count($source, 'data-bs-dismiss="toast"');
        notificationI18nAssert($closeControls >= 2 && substr_count($source, 'aria-label="${escapeHtml(closeLabel)}"') === $closeControls, $script . ': all toast close controls must use the escaped translation');
    }

    // A translation containing punctuation or markup must remain one text attribute.
    $escapedValue = 'Открыть " onmouseover="alert(1)" <script>alert(2)</script> & \'中文\'';
    foreach (array_merge(array_values($notificationAttributes), ['notification_close']) as $key) {
        $notificationI18nDictionary[$key] = $escapedValue;
    }
    foreach ($notificationTemplates as $template) {
        $fragment = notificationI18nOpeningTag($notificationI18nRoot . '/' . $template, 'div', 'data-notifications-center');
        [$element, $html] = notificationI18nRender($fragment, 'div');
        foreach ($notificationAttributes as $attribute => $key) {
            notificationI18nAssert($element->getAttribute($attribute) === $escapedValue, $template . ': escaped translation changed ' . $key);
            notificationI18nAssert(str_contains($html, $attribute . '="' . htmlSC($escapedValue) . '"'), $template . ': unescaped ' . $key);
        }
        notificationI18nAssert(!$element->hasAttribute('onmouseover'), $template . ': translation injected an event handler');
        notificationI18nAssert(!str_contains($html, '<script>'), $template . ': translation injected markup');
    }
    foreach ($bodyTemplates as $template) {
        $fragment = notificationI18nOpeningTag($notificationI18nRoot . '/' . $template, 'body', 'data-toast-close-label');
        [$element, $html] = notificationI18nRender($fragment, 'body');
        notificationI18nAssert($element->getAttribute('data-toast-close-label') === $escapedValue, $template . ': escaped toast label changed');
        notificationI18nAssert(str_contains($html, 'data-toast-close-label="' . htmlSC($escapedValue) . '"'), $template . ': unescaped toast label');
        notificationI18nAssert(!$element->hasAttribute('onmouseover'), $template . ': toast label injected an event handler');
    }
    echo 'Notification i18n checks passed: ' . $notificationI18nAssertions . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Notification i18n failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
