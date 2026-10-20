<?php
// Loaded only by the isolated auth fixture/test; never by production routing.
use App\Services\SecurityLogPresenter;

class SecurityLogTestView extends FBL\View
{
    public function renderPartial($view, $data = []): string
    {
        if ($view === 'admin/shell_open') return '<main class="container-fluid p-3 p-md-4"><h1 class="h3 mb-2">' . htmlSC($data['title']) . '</h1><p class="text-body-secondary mb-4">' . htmlSC($data['subtitle']) . '</p>';
        if ($view === 'admin/shell_close') return '</main>';
        return parent::renderPartial($view, $data);
    }
}

function securityLogSamples(): array
{
    return [
        ['id' => 431, 'event' => 'password_reset_complete', 'result' => 'failed', 'reason' => SecurityLogPresenter::encodeReason('token_claim_failed', ['stage' => 'claim', 'affected_rows' => 0]), 'actor_login' => '', 'target_login' => 'test-user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser', 'created_at' => '2026-10-19 23:37:00'],
        ['id' => 430, 'event' => 'password_reset_request', 'result' => 'success', 'reason' => '', 'actor_login' => '', 'target_login' => 'test-user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser', 'created_at' => '2026-10-19 23:36:00'],
        ['id' => 429, 'event' => 'password_reset_complete', 'result' => 'failed', 'reason' => 'token_used', 'actor_login' => '', 'target_login' => 'test-user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser', 'created_at' => '2026-10-19 23:35:00'],
        ['id' => 428, 'event' => 'session_revoked', 'result' => 'success', 'reason' => 'Record #47', 'actor_login' => 'creator', 'target_login' => 'creator', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser', 'created_at' => '2026-10-19 23:34:00'],
    ];
}

function renderSecurityLogFixture(): string
{
    $oldView = app()->view;
    app()->view = new SecurityLogTestView('default');
    try {
        return view()->renderPartial('admin/security_logs', ['logs' => securityLogSamples(), 'logs_total' => 0, 'total' => 4, 'pagination' => '', 'sort' => 'created_at', 'direction' => 'desc', 'search' => '']);
    } finally { app()->view = $oldView; }
}

function checkSecurityLogPresentation(): void
{
    $original = FBL\Language::$lang_data;
    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        FBL\Language::$lang_data = require APP . '/Languages/' . $locale . '.php';
        foreach (SecurityLogPresenter::EVENTS as $code) checkAuth('Localized audit event ' . $locale . '/' . $code, SecurityLogPresenter::event($code) !== 'security_event_' . $code && SecurityLogPresenter::event($code) !== $code);
        foreach (SecurityLogPresenter::RESULTS as $code) checkAuth('Localized audit result ' . $locale . '/' . $code, SecurityLogPresenter::result($code) !== 'security_result_' . $code && SecurityLogPresenter::result($code) !== $code);
        foreach (SecurityLogPresenter::REASONS as $code) checkAuth('Localized historical audit reason ' . $locale . '/' . $code, SecurityLogPresenter::reason($code)['message'] === return_translation('security_reason_' . $code) && SecurityLogPresenter::reason($code)['message'] !== 'security_reason_' . $code);
        checkAuth('Historical session reason translated ' . $locale, SecurityLogPresenter::reason('Record #47')['message'] === str_replace(':id', '47', return_translation('security_reason_session_record')));
        checkAuth('Missing old failure reason explained honestly ' . $locale, SecurityLogPresenter::reason('', 'failed')['message'] === return_translation('security_reason_not_recorded'));
        $html = renderSecurityLogFixture();
        $document = new DOMDocument(); @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($document);
        $events = $xpath->query('//td[contains(@class,"admin-security-log-table__event")]');
        checkAuth('Desktop event label localized ' . $locale, $events->length === 4 && trim($events[0]->textContent) === SecurityLogPresenter::event('password_reset_complete'));
        checkAuth('Desktop result label localized ' . $locale, $xpath->query('//td/span[contains(@class,"badge")]')[0]->textContent === SecurityLogPresenter::result('failed'));
        checkAuth('Mobile cards also localized ' . $locale, trim($xpath->query('//h3[contains(@class,"admin-mobile-table-card__title")]')[0]->textContent) === SecurityLogPresenter::event('password_reset_complete') && trim($xpath->query('//article//span[contains(@class,"badge")]')[0]->textContent) === SecurityLogPresenter::result('failed'));
        checkAuth('Reasons not clamped and diagnostics expandable ' . $locale, substr_count($html, '<details ') === 8 && str_contains($html, return_translation('security_detail_affected_rows')) && !str_contains($html, 'title="token_claim_failed"'));
        $dangerous = view()->renderPartial('admin/partials/security_log_reason', ['log' => ['event' => '<script>alert(1)</script>', 'result' => 'failed', 'reason' => '<img src=x onerror=alert(1)>']]);
        checkAuth('Audit fields escaped in visible reason and diagnostics ' . $locale, !str_contains($dangerous, '<script>') && !str_contains($dangerous, '<img') && str_contains($dangerous, '&lt;img'));
    }
    FBL\Language::$lang_data = $original;
    $safe = SecurityLogPresenter::safeDetails(['stage' => 'claim', 'affected_rows' => 0, 'sql_state' => '23000', 'driver_code' => 1062, 'token' => 'SECRET-TOKEN', 'password' => 'SECRET-PASSWORD', 'url' => 'https://secret.test', 'exception' => 'PRIVATE SQL']);
    checkAuth('Diagnostic fields use strict allowlist', array_keys($safe) === ['stage', 'affected_rows', 'sql_state', 'driver_code']);
    checkAuth('Unsafe values rejected even under allowed keys', SecurityLogPresenter::safeDetails(['stage' => 'SECRET-TOKEN', 'sql_state' => 'PASSWORD', 'driver_code' => 'private', 'affected_rows' => 'secret']) === []);
    $journal = new App\Models\SecurityLog();
    $journal->record('password_reset_complete', 'failed', null, null, 'storage_failure', $safe + ['password' => 'SECRET-PASSWORD']);
    $stored = db()->query('SELECT reason FROM security_logs ORDER BY id DESC LIMIT 1')->getColumn();
    checkAuth('Structured audit keeps only safe diagnostic details', !str_contains($stored, 'SECRET') && json_decode($stored, true)['details'] === $safe);
    authRequest([], [], 'GET');
    $found = $journal->getPaginated(['search' => 'Сброс пароля']);
    checkAuth('Search recognizes localized event label', $found['total'] > 0 && array_reduce($found['items'], fn($valid, $row) => $valid && $row['event'] === 'password_reset_complete', true));
    $found = $journal->getPaginated(['search' => 'Операция не завершена из-за ошибки базы данных']);
    checkAuth('Search recognizes localized structured reason', $found['total'] > 0);
}
