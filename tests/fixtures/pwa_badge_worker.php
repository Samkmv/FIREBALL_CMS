<?php

declare(strict_types=1);

namespace App\Models {
    class SiteSetting {}
}

namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__, 2) . '/app/Services/PwaService.php';
    function base_url(string $path = ''): string { return 'https://example.test' . $path; }
    function return_translation(string $key): string { return $key; }
    final class BadgeWorkerFixture extends \App\Services\PwaService
    {
        public function icons(): array { return ['sizes' => [192 => base_url('/icon.png'), 72 => base_url('/badge.png')]]; }
        protected function cacheVersion(): string { return 'badge-test'; }
        protected function appName(): string { return 'Badge test'; }
    }
    echo (new BadgeWorkerFixture(new \App\Models\SiteSetting()))->serviceWorkerScript();
}
