<?php

namespace App\Components;

final class AnalyticsTracker
{
    public function render(): string
    {
        $path = '/' . trim((string)uri_without_lang(), '/');
        if (preg_match('~^/(?:admin|api|assets|uploads|install)(?:/|$)~', $path) === 1) {
            return '';
        }

        $endpoint = json_encode(base_href('/api/analytics/track'), JSON_UNESCAPED_SLASHES);

        $internalTracker = <<<HTML
<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    var page = window.location.pathname + window.location.search;
    var landingPage = page;
    try {
        landingPage = sessionStorage.getItem('fireballAnalyticsLandingPage') || page;
        sessionStorage.setItem('fireballAnalyticsLandingPage', landingPage);
    } catch (error) {
        landingPage = page;
    }

    var payload = JSON.stringify({
        page: page,
        landing_page: landingPage,
        referer: document.referrer || '',
        utm_source: params.get('utm_source') || '',
        utm_medium: params.get('utm_medium') || '',
        utm_campaign: params.get('utm_campaign') || '',
        utm_content: params.get('utm_content') || '',
        utm_term: params.get('utm_term') || ''
    });
    var endpoint = {$endpoint};

    if (navigator.sendBeacon) {
        navigator.sendBeacon(endpoint, new Blob([payload], {type: 'application/json'}));
        return;
    }

    fetch(endpoint, {
        method: 'POST',
        body: payload,
        headers: {'Content-Type': 'application/json'},
        keepalive: true
    }).catch(function () {});
})();
</script>
HTML;

        return $internalTracker . $this->renderYandexMetrika();
    }

    private function renderYandexMetrika(): string
    {
        if (site_setting('yandex_metrika_enabled', '0') !== '1') {
            return '';
        }
        $counterId = trim(site_setting('yandex_metrika_id', ''));
        if (preg_match('/^\d{1,20}$/', $counterId) !== 1) {
            return '';
        }
        $id = (int)$counterId;
        $imageUrl = 'https://mc.yandex.ru/watch/' . rawurlencode($counterId);
        $additionalCode = trim(site_setting('yandex_metrika_code', ''));

        $tracker = <<<HTML
<!-- Yandex.Metrika counter -->
<script>
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
ym({$id},"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});
</script>
<noscript><div><img src="{$imageUrl}" style="position:absolute;left:-9999px" alt=""></div></noscript>
<!-- /Yandex.Metrika counter -->
HTML;

        return $tracker . ($additionalCode !== '' ? "\n" . $additionalCode : '');
    }
}
