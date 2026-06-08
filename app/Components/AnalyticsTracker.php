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

        return <<<HTML
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
    }
}
