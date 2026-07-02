<?php

namespace App\Services;

use App\Models\SiteSetting;

class PwaService
{
    protected SiteSetting $settings;
    protected bool $schemaReady = false;

    protected array $iconSizes = [48, 72, 96, 128, 144, 152, 180, 192, 384, 512];

    public function __construct(?SiteSetting $settings = null)
    {
        $this->settings = $settings ?: new SiteSetting();
    }

    public function isEnabled(): bool
    {
        return $this->settings->get('pwa_enabled', '1') === '1';
    }

    public function isPushEnabled(): bool
    {
        return $this->settings->get('pwa_push_enabled', '0') === '1';
    }

    public function manifest(): array
    {
        $siteTitle = $this->appName();
        $shortName = $this->shortName($siteTitle);

        return [
            'name' => $siteTitle,
            'short_name' => $shortName,
            'description' => $this->appDescription(),
            'start_url' => base_url('/'),
            'scope' => base_url('/'),
            'display' => 'standalone',
            'orientation' => $this->oneOf('pwa_orientation', ['any', 'portrait', 'portrait-primary', 'landscape', 'landscape-primary'], 'any'),
            'theme_color' => $this->color('pwa_theme_color', '#181d25'),
            'background_color' => $this->color('pwa_background_color', '#ffffff'),
            'lang' => app()->get('lang')['code'] ?? $this->settings->get('default_locale', DEFAULT_LOCALE),
            'icons' => $this->manifestIcons(),
        ];
    }

    public function headData(): array
    {
        $icons = $this->icons();
        $name = $this->appName();

        return [
            'enabled' => $this->isEnabled(),
            'push_enabled' => $this->isPushEnabled(),
            'app_name' => $name,
            'short_name' => $this->shortName($name),
            'manifest_url' => base_url('/manifest.webmanifest?v=' . rawurlencode($this->cacheVersion())),
            'service_worker_url' => base_url('/service-worker.js?v=' . rawurlencode($this->cacheVersion())),
            'theme_color' => $this->color('pwa_theme_color', '#181d25'),
            'background_color' => $this->color('pwa_background_color', '#ffffff'),
            'favicon_url' => $icons['favicon']['src'],
            'favicon_type' => $icons['favicon']['type'],
            'apple_touch_icon_url' => $icons['apple']['src'],
            'startup_image_url' => $this->startupImageUrl(),
            'vapid_public_key' => $this->settings->get('pwa_vapid_public_key', ''),
        ];
    }

    public function manifestIcons(): array
    {
        $set = $this->icons();
        $icons = [];

        foreach ([48, 72, 96, 128, 144, 152, 192, 384, 512] as $size) {
            if (!empty($set['sizes'][$size])) {
                $icons[] = [
                    'src' => $set['sizes'][$size],
                    'sizes' => $size . 'x' . $size,
                    'type' => 'image/png',
                    'purpose' => 'any',
                ];
            }
        }

        if (!empty($set['maskable'])) {
            $icons[] = [
                'src' => $set['maskable'],
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        if ($icons === [] && !empty($set['svg'])) {
            $icons[] = [
                'src' => $set['svg'],
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ];
        }

        return $icons;
    }

    public function icons(): array
    {
        $source = $this->resolveIconSource();
        if (($source['type'] ?? '') === 'svg') {
            $url = $this->withVersion($this->assetUrlFromLocalPath($source['path']) ?: base_url('/assets/img/fbl_logo.png'), $source['path']);

            return [
                'source' => $source,
                'favicon' => ['src' => $url, 'type' => 'image/svg+xml'],
                'apple' => ['src' => $url, 'type' => 'image/svg+xml'],
                'sizes' => [],
                'maskable' => $url,
                'svg' => $url,
            ];
        }

        $publicDir = ($source['kind'] ?? 'default') === 'default' ? '/assets/default/pwa' : '/uploads/pwa';
        $targetDir = WWW . $publicDir;

        if (!$this->iconSetComplete($targetDir)) {
            $this->generateRasterIcons((string)$source['path'], $targetDir);
        }

        if (!$this->iconSetComplete($targetDir)) {
            $this->generateRasterIcons(WWW . '/assets/img/fbl_logo.png', WWW . '/assets/default/pwa');
            $publicDir = '/assets/default/pwa';
            $targetDir = WWW . $publicDir;
        }

        $sizes = [];
        foreach ([48, 72, 96, 128, 144, 152, 192, 384, 512] as $size) {
            $file = 'icon-' . $size . '.png';
            if (is_file($targetDir . '/' . $file)) {
                $sizes[$size] = $this->withVersion(base_url($publicDir . '/' . $file), $targetDir . '/' . $file);
            }
        }

        return [
            'source' => $source,
            'favicon' => [
                'src' => $this->withVersion(base_url($publicDir . '/favicon-32x32.png'), $targetDir . '/favicon-32x32.png'),
                'type' => 'image/png',
            ],
            'apple' => [
                'src' => $this->withVersion(base_url($publicDir . '/apple-touch-icon.png'), $targetDir . '/apple-touch-icon.png'),
                'type' => 'image/png',
            ],
            'sizes' => $sizes,
            'maskable' => is_file($targetDir . '/icon-maskable-512.png')
                ? $this->withVersion(base_url($publicDir . '/icon-maskable-512.png'), $targetDir . '/icon-maskable-512.png')
                : '',
            'svg' => '',
        ];
    }

    public function syncIcons(): void
    {
        $source = $this->resolveIconSource();
        if (($source['type'] ?? '') !== 'svg') {
            $target = ($source['kind'] ?? 'default') === 'default' ? WWW . '/assets/default/pwa' : WWW . '/uploads/pwa';
            $this->generateRasterIcons((string)$source['path'], $target);
        }

        $this->settings->setMany(['pwa_cache_version' => (string)time()]);
    }

    public function serviceWorkerScript(): string
    {
        $icons = $this->icons();
        $payload = [
            'cacheName' => 'fireball-pwa-' . $this->cacheVersion(),
            'offlineUrl' => base_url('/offline'),
            'homeUrl' => base_url('/'),
            'defaultTitle' => $this->appName(),
            'defaultBody' => return_translation('pwa_push_default_body'),
            'icon' => $icons['sizes'][192] ?? $icons['apple']['src'] ?? base_url('/assets/img/fbl_logo.png'),
            'badge' => $icons['sizes'][72] ?? $icons['favicon']['src'] ?? base_url('/assets/img/fbl_logo.png'),
            'privatePrefixes' => [
                base_url('/admin'),
                base_url('/login'),
                base_url('/logout'),
                base_url('/profile'),
                base_url('/cart'),
                base_url('/checkout'),
                base_url('/orders'),
                base_url('/api/'),
                base_url('/notifications/'),
                base_url('/chat'),
            ],
        ];

        return 'const FIREBALL_PWA = ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(FIREBALL_PWA.cacheName).then((cache) => cache.add(FIREBALL_PWA.offlineUrl)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith("fireball-pwa-") && key !== FIREBALL_PWA.cacheName).map((key) => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== location.origin) return;

  const isPrivate = FIREBALL_PWA.privatePrefixes.some((prefix) => request.url.indexOf(prefix) === 0);
  if (isPrivate) return;

  if (request.mode === "navigate") {
    event.respondWith(fetch(request).catch(() => caches.match(FIREBALL_PWA.offlineUrl)));
  }
});

self.addEventListener("push", (event) => {
  let payload = {};
  if (event.data) {
    try { payload = event.data.json(); } catch (error) { payload = { body: event.data.text() }; }
  }

  const title = payload.title || FIREBALL_PWA.defaultTitle;
  const options = {
    body: payload.body || FIREBALL_PWA.defaultBody,
    icon: payload.icon || FIREBALL_PWA.icon,
    badge: payload.badge || FIREBALL_PWA.badge,
    image: payload.image || undefined,
    tag: payload.tag || undefined,
    data: Object.assign({ url: payload.url || FIREBALL_PWA.homeUrl }, payload.data || {}),
    vibrate: payload.vibrate || [120, 60, 120],
    timestamp: payload.timestamp || Date.now(),
    actions: Array.isArray(payload.actions) ? payload.actions : []
  };

  event.waitUntil((async () => {
    if (self.registration.setAppBadge) {
      try { await self.registration.setAppBadge(1); } catch (error) {}
    }
    await self.registration.showNotification(title, options);
  })());
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || FIREBALL_PWA.homeUrl;

  event.waitUntil((async () => {
    if (self.registration.clearAppBadge) {
      try { await self.registration.clearAppBadge(); } catch (error) {}
    }

    const clientsList = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of clientsList) {
      if ("focus" in client) {
        await client.focus();
        if ("navigate" in client) return client.navigate(targetUrl);
        return;
      }
    }
    if (self.clients.openWindow) return self.clients.openWindow(targetUrl);
  })());
});

self.addEventListener("notificationclose", () => {});
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") self.skipWaiting();
  if (event.data && event.data.type === "CLEAR_BADGE" && self.registration.clearAppBadge) {
    self.registration.clearAppBadge().catch(() => {});
  }
});
self.addEventListener("sync", () => {});
';
    }

    public function ensureTables(): void
    {
        if ($this->schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS pwa_subscriptions (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NULL,
                endpoint_hash CHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT NULL,
                auth TEXT NULL,
                platform VARCHAR(60) NULL,
                browser VARCHAR(60) NULL,
                user_agent VARCHAR(255) NULL,
                subscription_json MEDIUMTEXT NOT NULL,
                last_seen_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY endpoint_hash (endpoint_hash),
                KEY user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->query(
            "CREATE TABLE IF NOT EXISTS pwa_notifications (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                payload MEDIUMTEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'queued',
                sent_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                failed_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->upgradeSchema();

        $this->schemaReady = true;
    }

    protected function upgradeSchema(): void
    {
        $this->addColumnIfMissing('pwa_subscriptions', 'user_id', 'INT(10) UNSIGNED NULL AFTER id');
        $this->addColumnIfMissing('pwa_subscriptions', 'endpoint_hash', 'CHAR(64) NULL AFTER user_id');
        $this->addColumnIfMissing('pwa_subscriptions', 'p256dh', 'TEXT NULL AFTER endpoint');
        $this->addColumnIfMissing('pwa_subscriptions', 'auth', 'TEXT NULL AFTER p256dh');
        $this->addColumnIfMissing('pwa_subscriptions', 'platform', 'VARCHAR(60) NULL AFTER auth');
        $this->addColumnIfMissing('pwa_subscriptions', 'browser', 'VARCHAR(60) NULL AFTER platform');
        $this->addColumnIfMissing('pwa_subscriptions', 'user_agent', 'VARCHAR(255) NULL AFTER browser');
        $this->addColumnIfMissing('pwa_subscriptions', 'subscription_json', 'MEDIUMTEXT NULL AFTER user_agent');
        $this->addColumnIfMissing('pwa_subscriptions', 'last_seen_at', 'DATETIME NULL AFTER subscription_json');
        $this->addColumnIfMissing('pwa_subscriptions', 'created_at', 'DATETIME NULL AFTER last_seen_at');
        $this->addColumnIfMissing('pwa_subscriptions', 'updated_at', 'DATETIME NULL AFTER created_at');

        $this->addColumnIfMissing('pwa_notifications', 'user_id', 'INT(10) UNSIGNED NULL AFTER id');
        $this->addColumnIfMissing('pwa_notifications', 'body', 'TEXT NULL AFTER title');
        $this->addColumnIfMissing('pwa_notifications', 'payload', 'MEDIUMTEXT NULL AFTER body');
        $this->addColumnIfMissing('pwa_notifications', 'status', "VARCHAR(30) NOT NULL DEFAULT 'queued' AFTER payload");
        $this->addColumnIfMissing('pwa_notifications', 'sent_count', 'INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER status');
        $this->addColumnIfMissing('pwa_notifications', 'failed_count', 'INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER sent_count');
        $this->addColumnIfMissing('pwa_notifications', 'created_at', 'DATETIME NULL AFTER failed_count');
        $this->addColumnIfMissing('pwa_notifications', 'sent_at', 'DATETIME NULL AFTER created_at');

        db()->query("UPDATE pwa_subscriptions SET endpoint_hash = SHA2(endpoint, 256) WHERE endpoint_hash IS NULL OR endpoint_hash = ''");
        db()->query("UPDATE pwa_subscriptions SET subscription_json = '{}' WHERE subscription_json IS NULL OR subscription_json = ''");
        db()->query("UPDATE pwa_subscriptions SET last_seen_at = COALESCE(last_seen_at, updated_at, created_at, NOW())");
        db()->query("UPDATE pwa_subscriptions SET created_at = COALESCE(created_at, last_seen_at, NOW())");
        db()->query("UPDATE pwa_subscriptions SET updated_at = COALESCE(updated_at, last_seen_at, created_at, NOW())");
        db()->query("UPDATE pwa_notifications SET created_at = COALESCE(created_at, sent_at, NOW())");
    }

    protected function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        if (
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)
            || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)
        ) {
            throw new \InvalidArgumentException('Unsafe schema identifier.');
        }

        $exists = db()->query("SHOW COLUMNS FROM {$table} LIKE ?", [$column])->getOne();
        if (!$exists) {
            db()->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    public function saveSubscription(?int $userId, array $subscription, string $userAgent = ''): bool
    {
        $endpoint = trim((string)($subscription['endpoint'] ?? ''));
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL) || empty($keys['p256dh']) || empty($keys['auth'])) {
            return false;
        }

        $platform = $this->detectPlatform($userAgent);
        $browser = $this->detectBrowser($userAgent);
        $now = date('Y-m-d H:i:s');

        $this->ensureTables();
        db()->query(
            "INSERT INTO pwa_subscriptions
                (user_id, endpoint_hash, endpoint, p256dh, auth, platform, browser, user_agent, subscription_json, last_seen_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                platform = VALUES(platform),
                browser = VALUES(browser),
                user_agent = VALUES(user_agent),
                subscription_json = VALUES(subscription_json),
                last_seen_at = VALUES(last_seen_at),
                updated_at = VALUES(updated_at)",
            [
                $userId,
                hash('sha256', $endpoint),
                $endpoint,
                (string)$keys['p256dh'],
                (string)$keys['auth'],
                $platform,
                $browser,
                mb_substr($userAgent, 0, 255),
                json_encode($subscription, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $now,
                $now,
                $now,
            ]
        );

        return true;
    }

    public function deleteSubscription(?int $userId, string $endpoint): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return;
        }

        $this->ensureTables();
        $params = [hash('sha256', $endpoint)];
        $where = 'endpoint_hash = ?';
        if ($userId !== null) {
            $where .= ' AND user_id = ?';
            $params[] = $userId;
        }

        db()->query('DELETE FROM pwa_subscriptions WHERE ' . $where, $params);
    }

    public function devices(int $limit = 100): array
    {
        $this->ensureTables();

        return db()->query('SELECT * FROM pwa_subscriptions ORDER BY last_seen_at DESC LIMIT ' . max(1, min(500, $limit)))->get() ?: [];
    }

    public function recentNotifications(int $limit = 30): array
    {
        $this->ensureTables();

        return db()->query('SELECT * FROM pwa_notifications ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)))->get() ?: [];
    }

    public function generateVapidKeys(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (!$resource) {
            throw new \RuntimeException('Unable to generate VAPID keys.');
        }

        $details = openssl_pkey_get_details($resource);
        $ec = $details['ec'] ?? [];
        if (empty($ec['x']) || empty($ec['y'])) {
            throw new \RuntimeException('OpenSSL did not return VAPID public key.');
        }

        $privatePem = '';
        openssl_pkey_export($resource, $privatePem);
        $keys = [
            'public' => $this->base64UrlEncode("\x04" . $ec['x'] . $ec['y']),
            'private' => $privatePem,
        ];

        $this->settings->setMany([
            'pwa_vapid_public_key' => $keys['public'],
            'pwa_vapid_private_key' => $keys['private'],
            'pwa_cache_version' => (string)time(),
        ]);

        return $keys;
    }

    public function send(array $payload, array $options = []): array
    {
        $this->ensureTables();
        if (!$this->isPushEnabled()) {
            return ['sent' => 0, 'failed' => 0, 'total' => 0, 'disabled' => true];
        }

        $where = '';
        $params = [];
        if (isset($options['user_id'])) {
            $where = 'WHERE user_id = ?';
            $params[] = (int)$options['user_id'];
        } elseif (!empty($options['user_ids']) && is_array($options['user_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $options['user_ids'])));
            if ($ids !== []) {
                $where = 'WHERE user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = $ids;
            }
        }

        $subscriptions = db()->query('SELECT * FROM pwa_subscriptions ' . $where . ' ORDER BY last_seen_at DESC', $params)->get() ?: [];
        $payload = $this->normalizePayload($payload);
        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->sendWebPush($subscription, $payload);
                $sent++;
            } catch (\Throwable $exception) {
                $failed++;
                log_error_details('PWA push send failed', [
                    'Endpoint hash' => hash('sha256', (string)$subscription['endpoint']),
                ], $exception);
            }
        }

        $now = date('Y-m-d H:i:s');
        db()->query(
            'INSERT INTO pwa_notifications (user_id, title, body, payload, status, sent_count, failed_count, created_at, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                isset($options['user_id']) ? (int)$options['user_id'] : null,
                (string)$payload['title'],
                (string)($payload['body'] ?? ''),
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $failed > 0 && $sent === 0 ? 'failed' : 'sent',
                $sent,
                $failed,
                $now,
                $now,
            ]
        );

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($subscriptions), 'disabled' => false];
    }

    public function status(): array
    {
        $manifest = $this->manifest();
        $iconsOk = true;
        foreach ($manifest['icons'] as $icon) {
            $path = $this->localPath((string)($icon['src'] ?? ''));
            if ($path === null || !is_file($path)) {
                $iconsOk = false;
                break;
            }
        }

        return [
            'https' => $this->isSecureContext(),
            'manifest' => !empty($manifest['name']) && !empty($manifest['icons']) && $iconsOk,
            'service_worker' => true,
            'push' => $this->isPushEnabled() && $this->settings->get('pwa_vapid_public_key', '') !== '',
            'vapid' => $this->settings->get('pwa_vapid_public_key', '') !== '' && $this->settings->get('pwa_vapid_private_key', '') !== '',
        ];
    }

    protected function normalizePayload(array $payload): array
    {
        $icons = $this->icons();

        return [
            'title' => trim((string)($payload['title'] ?? $this->settings->get('site_title', SITE_NAME))),
            'body' => trim((string)($payload['body'] ?? '')),
            'icon' => (string)($payload['icon'] ?? ($icons['sizes'][192] ?? $icons['apple']['src'] ?? '')),
            'badge' => (string)($payload['badge'] ?? ($icons['sizes'][72] ?? $icons['favicon']['src'] ?? '')),
            'image' => (string)($payload['image'] ?? ''),
            'url' => (string)($payload['url'] ?? base_url('/')),
            'tag' => (string)($payload['tag'] ?? ''),
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'vibrate' => is_array($payload['vibrate'] ?? null) ? $payload['vibrate'] : [120, 60, 120],
            'timestamp' => (int)($payload['timestamp'] ?? time() * 1000),
            'actions' => is_array($payload['actions'] ?? null) ? $payload['actions'] : [],
        ];
    }

    protected function sendWebPush(array $subscription, array $payload): void
    {
        $endpoint = (string)$subscription['endpoint'];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Invalid push payload.');
        }

        $encrypted = $this->encryptPayload($body, (string)$subscription['p256dh'], (string)$subscription['auth']);
        $headers = [
            'TTL: 2419200',
            'Urgency: normal',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: vapid t=' . $this->vapidJwt($endpoint) . ', k=' . $this->settings->get('pwa_vapid_public_key', ''),
        ];

        $code = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encrypted,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($code === 404 || $code === 410) {
                $this->deleteInvalidEndpoint($endpoint);
                return;
            }
            if ($code < 200 || $code >= 300) {
                throw new \RuntimeException($error !== '' ? $error : 'Push endpoint returned HTTP ' . $code);
            }
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $encrypted,
                'timeout' => 10,
            ],
        ]);
        $result = @file_get_contents($endpoint, false, $context);
        if ($result === false) {
            throw new \RuntimeException('Push endpoint request failed.');
        }
    }

    protected function encryptPayload(string $payload, string $receiverPublicKey, string $authSecret): string
    {
        $receiverPublic = $this->base64UrlDecode($receiverPublicKey);
        $auth = $this->base64UrlDecode($authSecret);
        if (strlen($receiverPublic) !== 65 || strlen($auth) < 16) {
            throw new \RuntimeException('Invalid push subscription keys.');
        }

        $sender = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $senderDetails = openssl_pkey_get_details($sender);
        $senderPublic = "\x04" . $senderDetails['ec']['x'] . $senderDetails['ec']['y'];
        $receiverPem = $this->publicKeyPem($receiverPublic);
        $sharedSecret = openssl_pkey_derive($receiverPem, $sender, 32);
        if (!is_string($sharedSecret) || strlen($sharedSecret) === 0) {
            throw new \RuntimeException('Unable to derive push encryption secret.');
        }

        $salt = random_bytes(16);
        $keyInfo = "WebPush: info\x00" . $receiverPublic . $senderPublic;
        $prkKey = hash_hmac('sha256', $sharedSecret, $auth, true);
        $ikm = $this->hkdfExpand($prkKey, $keyInfo, 32);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = $this->hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);

        $tag = '';
        $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt push payload.');
        }

        return $salt . pack('N', 4096) . chr(strlen($senderPublic)) . $senderPublic . $ciphertext . $tag;
    }

    protected function vapidJwt(string $endpoint): string
    {
        $privateKey = $this->settings->get('pwa_vapid_private_key', '');
        $publicKey = $this->settings->get('pwa_vapid_public_key', '');
        if ($privateKey === '' || $publicKey === '') {
            throw new \RuntimeException('VAPID keys are not configured.');
        }

        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $audience,
            'exp' => time() + 3600,
            'sub' => 'mailto:admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        ];

        $unsigned = $this->base64UrlEncode(json_encode($header)) . '.' . $this->base64UrlEncode(json_encode($payload));
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Unable to sign VAPID JWT.');
        }

        return $unsigned . '.' . $this->base64UrlEncode($this->derToJose($signature));
    }

    protected function derToJose(string $der): string
    {
        $offset = 3;
        $rLength = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLength);
        $offset += 1 + $rLength + 1;
        $sLength = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLength);

        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
            . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    protected function hkdfExpand(string $prk, string $info, int $length): string
    {
        $output = '';
        $block = '';
        $counter = 1;
        while (strlen($output) < $length) {
            $block = hash_hmac('sha256', $block . $info . chr($counter), $prk, true);
            $output .= $block;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    protected function publicKeyPem(string $raw): string
    {
        $der = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200') . $raw;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    protected function deleteInvalidEndpoint(string $endpoint): void
    {
        $this->ensureTables();
        db()->query('DELETE FROM pwa_subscriptions WHERE endpoint_hash = ?', [hash('sha256', $endpoint)]);
    }

    protected function resolveIconSource(): array
    {
        foreach (['site_favicon' => 'favicon', 'pwa_app_icon' => 'app_icon', 'pwa_logo' => 'logo'] as $setting => $kind) {
            $value = trim($this->settings->get($setting, ''));
            $path = $this->localPath($value);
            if ($path !== null && is_file($path)) {
                return [
                    'kind' => $kind,
                    'path' => $path,
                    'type' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' ? 'svg' : 'raster',
                ];
            }
        }

        return [
            'kind' => 'default',
            'path' => WWW . '/assets/img/fbl_logo.png',
            'type' => 'raster',
        ];
    }

    protected function appName(): string
    {
        $siteTitle = trim($this->settings->get('site_title', ''));
        $pwaName = trim($this->settings->get('pwa_app_name', ''));

        if ($pwaName !== '' && !($pwaName === SITE_NAME && $siteTitle !== '' && $siteTitle !== SITE_NAME)) {
            return $pwaName;
        }

        if ($siteTitle !== '') {
            return $siteTitle;
        }

        $host = trim((string)(parse_url(base_url('/'), PHP_URL_HOST) ?: ''));

        return $host !== '' ? $host : 'Fireball site';
    }

    protected function shortName(string $appName): string
    {
        $shortName = trim($this->settings->get('pwa_short_name', ''));
        if ($shortName !== '' && !($shortName === SITE_NAME && $appName !== SITE_NAME)) {
            return $shortName;
        }

        return mb_substr($appName, 0, 24);
    }

    protected function appDescription(): string
    {
        $description = trim($this->settings->get('pwa_description', ''));
        if ($description !== '') {
            return $description;
        }

        return trim($this->settings->get('site_description', ''));
    }

    protected function startupImageUrl(): string
    {
        $path = $this->localPath($this->settings->get('pwa_startup_image', ''));
        if ($path !== null && is_file($path)) {
            return $this->withVersion($this->assetUrlFromLocalPath($path) ?: '', $path);
        }

        return $this->icons()['apple']['src'] ?? '';
    }

    protected function generateRasterIcons(string $source, string $targetDir): bool
    {
        if (!extension_loaded('gd') || !is_file($source)) {
            return false;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        foreach ($this->iconSizes as $size) {
            $file = $size === 180 ? 'apple-touch-icon.png' : 'icon-' . $size . '.png';
            $this->resizePng($source, $targetDir . '/' . $file, $size, false);
        }
        $this->resizePng($source, $targetDir . '/favicon-16x16.png', 16, false);
        $this->resizePng($source, $targetDir . '/favicon-32x32.png', 32, false);
        $this->resizePng($source, $targetDir . '/icon-maskable-512.png', 512, true);
        $this->writeIco([$targetDir . '/favicon-16x16.png', $targetDir . '/favicon-32x32.png'], $targetDir . '/favicon.ico');

        return true;
    }

    protected function iconSetComplete(string $targetDir): bool
    {
        foreach ([48, 72, 96, 128, 144, 152, 192, 384, 512] as $size) {
            if (!is_file($targetDir . '/icon-' . $size . '.png')) {
                return false;
            }
        }

        return is_file($targetDir . '/apple-touch-icon.png')
            && is_file($targetDir . '/favicon-16x16.png')
            && is_file($targetDir . '/favicon-32x32.png')
            && is_file($targetDir . '/favicon.ico')
            && is_file($targetDir . '/icon-maskable-512.png');
    }

    protected function resizePng(string $source, string $target, int $size, bool $maskable): bool
    {
        $image = $this->createImage($source);
        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        $padding = $maskable ? (int)round($size * 0.12) : 0;
        $available = $size - ($padding * 2);
        $scale = min($available / $width, $available / $height);
        $targetWidth = max(1, (int)round($width * $scale));
        $targetHeight = max(1, (int)round($height * $scale));
        $x = (int)floor(($size - $targetWidth) / 2);
        $y = (int)floor(($size - $targetHeight) / 2);

        imagecopyresampled($canvas, $image, $x, $y, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $saved = imagepng($canvas, $target, 9);
        imagedestroy($image);
        imagedestroy($canvas);

        return $saved;
    }

    protected function createImage(string $source)
    {
        return match (strtolower(pathinfo($source, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'gif' => @imagecreatefromgif($source),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            'png' => @imagecreatefrompng($source),
            default => false,
        };
    }

    protected function writeIco(array $pngFiles, string $target): void
    {
        $entries = [];
        $data = '';
        $offset = 6 + (16 * count($pngFiles));
        foreach ($pngFiles as $file) {
            $size = is_file($file) ? getimagesize($file) : false;
            $blob = is_file($file) ? file_get_contents($file) : false;
            if (!$size || !is_string($blob)) {
                continue;
            }
            $width = (int)$size[0];
            $height = (int)$size[1];
            $entries[] = pack('CCCCvvVV', $width >= 256 ? 0 : $width, $height >= 256 ? 0 : $height, 0, 0, 1, 32, strlen($blob), $offset);
            $data .= $blob;
            $offset += strlen($blob);
        }
        if ($entries !== []) {
            file_put_contents($target, pack('vvv', 0, 1, count($entries)) . implode('', $entries) . $data);
        }
    }

    protected function localPath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $path = (string)(parse_url($value, PHP_URL_PATH) ?: $value);
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $baseHost = strtolower((string)(parse_url(base_url('/'), PHP_URL_HOST) ?? ''));
            $valueHost = strtolower((string)(parse_url($value, PHP_URL_HOST) ?? ''));
            if ($baseHost === '' || $valueHost !== $baseHost) {
                return null;
            }
        }

        $full = realpath(WWW . '/' . ltrim($path, '/'));
        $root = realpath(WWW);
        if (!$full || !$root || !str_starts_with($full, $root)) {
            return null;
        }

        return $full;
    }

    protected function assetUrlFromLocalPath(string $path): ?string
    {
        $root = realpath(WWW);
        $full = realpath($path);
        if (!$root || !$full || !str_starts_with($full, $root)) {
            return null;
        }

        return base_url(str_replace(DIRECTORY_SEPARATOR, '/', substr($full, strlen($root))));
    }

    protected function withVersion(string $url, ?string $path = null): string
    {
        $version = $path && is_file($path) ? (string)filemtime($path) : $this->cacheVersion();

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
    }

    protected function cacheVersion(): string
    {
        $source = $this->resolveIconSource();
        $startup = $this->localPath($this->settings->get('pwa_startup_image', ''));
        $settings = [];

        foreach ([
            'site_title',
            'site_description',
            'site_favicon',
            'pwa_enabled',
            'pwa_push_enabled',
            'pwa_app_name',
            'pwa_short_name',
            'pwa_description',
            'pwa_theme_color',
            'pwa_background_color',
            'pwa_orientation',
            'pwa_app_icon',
            'pwa_logo',
            'pwa_startup_image',
            'pwa_cache_version',
            'default_locale',
        ] as $key) {
            $settings[$key] = $this->settings->get($key, '');
        }

        $settings['icon_source_path'] = (string)($source['path'] ?? '');
        $settings['icon_source_mtime'] = is_file((string)($source['path'] ?? '')) ? (string)filemtime((string)$source['path']) : '';
        $settings['startup_mtime'] = $startup !== null && is_file($startup) ? (string)filemtime($startup) : '';

        return substr(hash('sha256', json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    protected function color(string $key, string $default): string
    {
        $value = trim($this->settings->get($key, $default));

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $default;
    }

    protected function oneOf(string $key, array $allowed, string $default): string
    {
        $value = trim($this->settings->get($key, $default));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function isSecureContext(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true);
    }

    protected function detectPlatform(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        return str_contains($ua, 'iphone') || str_contains($ua, 'ipad') ? 'ios'
            : (str_contains($ua, 'android') ? 'android'
                : (str_contains($ua, 'windows') ? 'windows'
                    : (str_contains($ua, 'mac') ? 'macos' : 'unknown')));
    }

    protected function detectBrowser(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        return str_contains($ua, 'edg/') ? 'edge'
            : (str_contains($ua, 'samsungbrowser') ? 'samsung'
                : (str_contains($ua, 'firefox') ? 'firefox'
                    : (str_contains($ua, 'yabrowser') ? 'yandex'
                        : (str_contains($ua, 'chrome') ? 'chrome'
                            : (str_contains($ua, 'safari') ? 'safari' : 'unknown')))));
    }

    protected function base64UrlEncode(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string)$value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        return (string)base64_decode(strtr($value, '-_', '+/'));
    }
}
