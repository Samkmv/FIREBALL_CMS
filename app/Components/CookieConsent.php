<?php

namespace App\Components;

use App\Models\Page;
use App\Models\SiteSetting;

/**
 * Renders the CMS-owned cookie consent UI independently from the active theme.
 */
class CookieConsent
{
    public const COOKIE_NAME = 'fireball_cookie_consent';

    public function render(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        $settings = (new SiteSetting())->all();
        if (($settings['cookie_enabled'] ?? '0') !== '1') {
            return '';
        }

        $message = trim((string)($settings['cookie_message'] ?? ''));
        $buttonText = trim((string)($settings['cookie_button_text'] ?? ''));
        if ($message === '' || $buttonText === '') {
            return '';
        }

        $style = in_array(($settings['cookie_style'] ?? ''), ['card', 'bar'], true)
            ? (string)$settings['cookie_style']
            : 'card';
        $position = in_array(($settings['cookie_position'] ?? ''), ['bottom_right', 'bottom_left', 'bottom_center', 'top'], true)
            ? (string)$settings['cookie_position']
            : 'bottom_right';
        $expirationDays = max(1, min(3650, (int)($settings['cookie_expiration_days'] ?? 365)));
        $policy = $this->resolvePolicyPage((int)($settings['cookie_policy_page_id'] ?? 0));
        $secure = $this->isHttps() ? 'true' : 'false';

        ob_start();
        ?>
        <div
            class="fireball-cookie-consent fireball-cookie-consent--<?= htmlSC($style) ?> fireball-cookie-consent--<?= htmlSC(str_replace('_', '-', $position)) ?>"
            data-fireball-cookie-consent
            data-expiration-days="<?= $expirationDays ?>"
            role="dialog"
            aria-live="polite"
            aria-label="<?= htmlSC(return_translation('cookie_consent_aria_label')) ?>"
        >
            <div class="fireball-cookie-consent__inner">
                <p class="fireball-cookie-consent__message"><?= nl2br(htmlSC($message)) ?></p>
                <div class="fireball-cookie-consent__actions">
                    <button type="button" class="fireball-cookie-consent__accept" data-cookie-consent-accept>
                        <?= htmlSC($buttonText) ?>
                    </button>
                    <?php if ($policy !== null): ?>
                        <a class="fireball-cookie-consent__policy" href="<?= htmlSC($policy['url']) ?>">
                            <?= htmlSC(return_translation('cookie_consent_more')) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <style>
            .fireball-cookie-consent{position:fixed;z-index:2147483000;box-sizing:border-box;color:var(--bs-body-color,#1f2937);font:400 15px/1.5 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
            .fireball-cookie-consent *{box-sizing:border-box}
            .fireball-cookie-consent--card{width:min(420px,calc(100vw - 32px))}
            .fireball-cookie-consent--bottom-right{right:max(16px,env(safe-area-inset-right));bottom:max(16px,env(safe-area-inset-bottom))}
            .fireball-cookie-consent--bottom-left{left:max(16px,env(safe-area-inset-left));bottom:max(16px,env(safe-area-inset-bottom))}
            .fireball-cookie-consent--bottom-center{left:50%;bottom:max(16px,env(safe-area-inset-bottom));transform:translateX(-50%)}
            .fireball-cookie-consent--top{top:max(16px,env(safe-area-inset-top));left:50%;transform:translateX(-50%)}
            .fireball-cookie-consent--bar{left:0;right:0;width:100%}
            .fireball-cookie-consent--bar.fireball-cookie-consent--bottom-right,.fireball-cookie-consent--bar.fireball-cookie-consent--bottom-left,.fireball-cookie-consent--bar.fireball-cookie-consent--bottom-center{bottom:0;transform:none}
            .fireball-cookie-consent--bar.fireball-cookie-consent--top{top:0;transform:none}
            .fireball-cookie-consent__inner{max-height:min(70vh,520px);overflow:auto;padding:20px;border:1px solid rgba(128,128,128,.25);border-radius:20px;background:var(--bs-body-bg,#fff);background:color-mix(in srgb,var(--bs-body-bg,#fff) 88%,transparent);box-shadow:0 18px 55px rgba(15,23,42,.18);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
            .fireball-cookie-consent--bar .fireball-cookie-consent__inner{display:flex;align-items:center;justify-content:center;gap:24px;width:100%;border-radius:0;padding:16px max(20px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(20px,env(safe-area-inset-left))}
            .fireball-cookie-consent__message{flex:0 1 760px;margin:0}
            .fireball-cookie-consent__actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:16px}
            .fireball-cookie-consent--bar .fireball-cookie-consent__actions{flex:0 0 auto;margin-top:0}
            .fireball-cookie-consent__accept,.fireball-cookie-consent__policy{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 18px;border-radius:999px;font:600 14px/1.2 inherit;text-decoration:none;cursor:pointer}
            .fireball-cookie-consent__accept{border:1px solid var(--bs-primary,#dc2626);background:var(--bs-primary,#dc2626);color:#fff}
            .fireball-cookie-consent__policy{border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit}
            .fireball-cookie-consent__accept:focus-visible,.fireball-cookie-consent__policy:focus-visible{outline:3px solid color-mix(in srgb,var(--bs-primary,#dc2626) 35%,transparent);outline-offset:2px}
            @media(max-width:767.98px){.fireball-cookie-consent{font-size:14px}.fireball-cookie-consent--card{width:calc(100vw - 24px)}.fireball-cookie-consent--bottom-right{right:12px;bottom:max(12px,env(safe-area-inset-bottom))}.fireball-cookie-consent--bottom-left{left:12px;bottom:max(12px,env(safe-area-inset-bottom))}.fireball-cookie-consent__inner{padding:16px;border-radius:16px}.fireball-cookie-consent--bar .fireball-cookie-consent__inner{align-items:stretch;flex-direction:column;gap:14px}.fireball-cookie-consent--bar .fireball-cookie-consent__actions{margin-top:0}.fireball-cookie-consent__actions>*{flex:1 1 auto}}
            @media(prefers-reduced-motion:no-preference){.fireball-cookie-consent{animation:fireball-cookie-consent-in .28s ease-out both}@keyframes fireball-cookie-consent-in{from{opacity:0}to{opacity:1}}.fireball-cookie-consent--card.fireball-cookie-consent--bottom-center,.fireball-cookie-consent--card.fireball-cookie-consent--top{animation-name:fireball-cookie-consent-center-in}@keyframes fireball-cookie-consent-center-in{from{opacity:0;transform:translate(-50%,12px)}to{opacity:1;transform:translate(-50%,0)}}}
        </style>
        <script>
            (function () {
                var banner = document.querySelector('[data-fireball-cookie-consent]');
                var button = banner && banner.querySelector('[data-cookie-consent-accept]');
                if (!banner || !button) return;
                button.addEventListener('click', function () {
                    var days = Math.max(1, parseInt(banner.getAttribute('data-expiration-days') || '365', 10));
                    var cookie = '<?= self::COOKIE_NAME ?>=true; Path=/; Max-Age=' + (days * 86400) + '; SameSite=Lax';
                    if (<?= $secure ?>) cookie += '; Secure';
                    document.cookie = cookie;
                    banner.remove();
                });
            })();
        </script>
        <?php

        return trim((string)ob_get_clean());
    }

    protected function shouldRender(): bool
    {
        if (($_COOKIE[self::COOKIE_NAME] ?? '') === 'true') {
            return false;
        }

        $path = '/' . trim((string)request()->getPath(), '/');
        $basePath = trim((string)(parse_url(app_base_url(), PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '') {
            $basePrefix = '/' . $basePath;
            if ($path === $basePrefix) {
                $path = '/';
            } elseif (str_starts_with($path, $basePrefix . '/')) {
                $path = substr($path, strlen($basePrefix));
            }
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (isset($segments[0]) && array_key_exists((string)$segments[0], LANGS)) {
            array_shift($segments);
        }

        return !in_array((string)($segments[0] ?? ''), ['admin', 'install'], true);
    }

    protected function resolvePolicyPage(int $pageId): ?array
    {
        $page = $pageId > 0 ? (new Page())->findPublishedById($pageId) : false;

        return $page ? ['id' => (int)$page['id'], 'url' => (string)$page['url']] : null;
    }

    protected function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
