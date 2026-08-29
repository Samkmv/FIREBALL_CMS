<?php

use App\Services\SqlFileRunner;
use FBL\Plugins\PluginInterface;
use Fireball\CameraManager\RemoteStreamsPublisher;
use Fireball\CameraManager\SecretCipher;
use Fireball\CameraManager\SshCameraTransport;
use Fireball\CameraManager\StreamsFilePublisher;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\CameraManager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if ($relative === '' || str_contains($relative, '..')) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

final class FireballPluginCameraManager implements PluginInterface
{
    public const SLUG = 'camera-manager';

    public function install(): void
    {
        self::ensureDatabaseSchema();
        self::synchronizeSettingRows();
        foreach (self::defaultSettings() as $key => $value) {
            if (self::settingValue($key, null) === null) {
                self::setSettingValue($key, $value);
            }
        }
    }

    public function uninstall(): void
    {
        // Database records and generated server configuration are intentionally preserved.
    }

    public function activate(): void
    {
        self::ensureDatabaseSchema();
        self::synchronizeSettingRows();
    }

    public function deactivate(): void
    {
    }

    public function boot(): void
    {
        try {
            self::ensureDatabaseSchema();
            self::synchronizeSettingRows();
        } catch (Throwable $exception) {
            log_error_details('Camera Manager schema check failed', [], $exception);
        }

        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('camera_manager_menu'),
                'href' => base_href('/admin/camera-manager'),
                'icon' => 'ci-video',
                'plugin_menu' => true,
                'order' => 20,
            ];

            return $menu;
        });

        add_filter('admin_dashboard_widgets', static function (array $widgets): array {
            try {
                $stats = self::stats();
                $widgets[] = [
                    'plugin' => self::SLUG,
                    'title' => self::t('camera_manager_title'),
                    'subtitle' => self::t('camera_manager_dashboard_subtitle'),
                    'icon' => 'ci-video',
                    'href' => base_href('/admin/camera-manager'),
                    'metrics' => [
                        ['label' => self::t('camera_manager_sites'), 'value' => $stats['sites'], 'tone' => 'info'],
                        ['label' => self::t('camera_manager_cameras'), 'value' => $stats['cameras'], 'tone' => 'success'],
                        ['label' => self::t('camera_manager_offline'), 'value' => $stats['offline'], 'tone' => $stats['offline'] > 0 ? 'warning' : 'success'],
                    ],
                ];
            } catch (Throwable $exception) {
                log_error_details('Camera Manager dashboard widget failed', [], $exception);
            }

            return $widgets;
        });
    }

    public static function defaultSettings(): array
    {
        return [
            'connection_mode' => 'pull',
            'streams_file_path' => '/var/www/html/rtsp/streams.pl',
            'perl_binary' => '/usr/bin/perl',
            'service_name' => 'rtsp-streams.service',
            'restart_on_publish' => true,
            'use_sudo' => true,
            'hls_base_url' => 'https://rtsp.ddns.net/rtsp',
            'ssh_binary' => '/usr/bin/ssh',
            'ssh_host' => 'rtsp.ddns.net',
            'ssh_port' => 22,
            'ssh_user' => 'camera-sync',
            'ssh_identity_file' => '/var/www/.ssh/fireball-camera-manager',
            'ssh_known_hosts_file' => '/var/www/.ssh/known_hosts',
            'pull_token_hash' => '',
            'pull_payload_encrypted' => '',
            'pull_revision' => 0,
            'pull_requested_at' => '',
            'pull_last_seen_at' => '',
            'pull_last_revision' => 0,
            'pull_last_status' => '',
            'pull_last_message' => '',
            'pull_last_backup_path' => '',
            'pull_last_report_fingerprint' => '',
        ];
    }

    public static function settings(): array
    {
        $settings = [];
        foreach (self::defaultSettings() as $key => $default) {
            $settings[$key] = self::settingValue($key, $default);
        }
        $settings['streams_file_path'] = trim((string)$settings['streams_file_path']);
        $settings['perl_binary'] = trim((string)$settings['perl_binary']);
        $settings['service_name'] = trim((string)$settings['service_name']);
        $settings['hls_base_url'] = rtrim(trim((string)$settings['hls_base_url']), '/');
        $connectionMode = (string)$settings['connection_mode'];
        $settings['connection_mode'] = in_array($connectionMode, ['pull', 'ssh', 'local'], true)
            ? $connectionMode
            : 'pull';
        $settings['ssh_binary'] = trim((string)$settings['ssh_binary']);
        $settings['ssh_host'] = trim((string)$settings['ssh_host']);
        $settings['ssh_port'] = max(1, min(65535, (int)$settings['ssh_port']));
        $settings['ssh_user'] = trim((string)$settings['ssh_user']);
        $settings['ssh_identity_file'] = trim((string)$settings['ssh_identity_file']);
        $settings['ssh_known_hosts_file'] = trim((string)$settings['ssh_known_hosts_file']);
        $settings['restart_on_publish'] = (bool)$settings['restart_on_publish'];
        $settings['use_sudo'] = (bool)$settings['use_sudo'];
        $settings['pull_token_configured'] = preg_match('/^[a-f0-9]{64}$/', (string)$settings['pull_token_hash']) === 1;
        $settings['pull_revision'] = max(0, (int)$settings['pull_revision']);
        $settings['pull_last_revision'] = max(0, (int)$settings['pull_last_revision']);
        unset($settings['pull_token_hash'], $settings['pull_payload_encrypted']);

        return $settings;
    }

    public static function saveSettings(array $data): void
    {
        $connectionMode = (string)($data['connection_mode'] ?? 'pull');
        if (!in_array($connectionMode, ['pull', 'ssh', 'local'], true)) {
            $connectionMode = 'pull';
        }
        $streamsPath = trim((string)($data['streams_file_path'] ?? ''));
        $perlBinary = trim((string)($data['perl_binary'] ?? ''));
        $serviceName = trim((string)($data['service_name'] ?? ''));
        $hlsBaseUrl = rtrim(trim((string)($data['hls_base_url'] ?? '')), '/');
        $sshBinary = trim((string)($data['ssh_binary'] ?? ''));
        $sshHost = trim((string)($data['ssh_host'] ?? ''));
        $sshPort = (int)($data['ssh_port'] ?? 22);
        $sshUser = trim((string)($data['ssh_user'] ?? ''));
        $identityFile = trim((string)($data['ssh_identity_file'] ?? ''));
        $knownHostsFile = trim((string)($data['ssh_known_hosts_file'] ?? ''));
        $pullToken = strtolower(trim((string)($data['pull_token'] ?? '')));
        $existingPullTokenHash = trim((string)self::settingValue('pull_token_hash', ''));

        if ($connectionMode === 'local' && ($streamsPath === '' || $streamsPath[0] !== '/' || basename($streamsPath) !== 'streams.pl')) {
            throw new RuntimeException('Укажите абсолютный путь к файлу streams.pl.');
        }
        if ($connectionMode === 'local' && ($perlBinary === '' || $perlBinary[0] !== '/')) {
            throw new RuntimeException('Укажите абсолютный путь к Perl.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.@-]{1,128}\.service$/', $serviceName)) {
            throw new RuntimeException('Некорректное имя systemd-службы.');
        }
        if (!filter_var($hlsBaseUrl, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $hlsBaseUrl)) {
            throw new RuntimeException('Некорректный базовый HLS URL.');
        }
        if ($connectionMode === 'ssh') {
            if ($sshBinary === '' || $sshBinary[0] !== '/') {
                throw new RuntimeException('Укажите абсолютный путь к SSH-клиенту.');
            }
            if (!preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?|\[[0-9a-fA-F:]+\])$/', $sshHost)) {
                throw new RuntimeException('Некорректный адрес SSH-сервера.');
            }
            if ($sshPort < 1 || $sshPort > 65535) {
                throw new RuntimeException('Некорректный SSH-порт.');
            }
            if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/i', $sshUser)) {
                throw new RuntimeException('Некорректный SSH-пользователь.');
            }
            foreach (['путь к SSH-ключу' => $identityFile, 'путь к known_hosts' => $knownHostsFile] as $label => $path) {
                if ($path === '' || $path[0] !== '/') {
                    throw new RuntimeException('Укажите абсолютный ' . $label . '.');
                }
            }
        }
        if ($pullToken !== '' && preg_match('/^[a-f0-9]{64}$/', $pullToken) !== 1) {
            throw new RuntimeException('HTTPS-токен должен состоять ровно из 64 шестнадцатеричных символов.');
        }
        if ($connectionMode === 'pull' && $pullToken === '' && preg_match('/^[a-f0-9]{64}$/', $existingPullTokenHash) !== 1) {
            throw new RuntimeException('Создайте и укажите HTTPS-токен для агента синхронизации.');
        }

        $pullTokenHash = $pullToken !== '' ? hash('sha256', $pullToken) : $existingPullTokenHash;

        foreach ([
            'connection_mode' => $connectionMode,
            'streams_file_path' => $streamsPath,
            'perl_binary' => $perlBinary,
            'service_name' => $serviceName,
            'hls_base_url' => $hlsBaseUrl,
            'restart_on_publish' => !empty($data['restart_on_publish']),
            'use_sudo' => !empty($data['use_sudo']),
            'ssh_binary' => $sshBinary,
            'ssh_host' => $sshHost,
            'ssh_port' => $sshPort,
            'ssh_user' => $sshUser,
            'ssh_identity_file' => $identityFile,
            'ssh_known_hosts_file' => $knownHostsFile,
            'pull_token_hash' => $pullTokenHash,
        ] as $key => $value) {
            self::setSettingValue($key, $value);
        }

        if ($pullToken !== '') {
            self::assertStoredPullTokenHash($pullTokenHash);
        }
    }

    public static function savePullToken(string $token): void
    {
        $token = strtolower(trim($token));
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new RuntimeException('HTTPS-токен должен состоять ровно из 64 шестнадцатеричных символов.');
        }

        $hash = hash('sha256', $token);
        self::setSettingValue('pull_token_hash', $hash);
        self::assertStoredPullTokenHash($hash);
    }

    public static function settingValue(string $key, mixed $default = null): mixed
    {
        return plugin_setting(self::SLUG, $key, $default);
    }

    public static function setSettingValue(string $key, mixed $value): void
    {
        if (!function_exists('db')) {
            plugin_setting_set(self::SLUG, $key, $value);
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stored = $encoded !== false ? $encoded : 'null';
        $existing = (int)db()->query(
            'SELECT COUNT(*) FROM plugin_settings WHERE plugin_slug = ? AND setting_key = ?',
            [self::SLUG, $key]
        )->getColumn();

        if ($existing > 0) {
            db()->query(
                'UPDATE plugin_settings SET setting_value = ?, updated_at = ? WHERE plugin_slug = ? AND setting_key = ?',
                [$stored, date('Y-m-d H:i:s'), self::SLUG, $key]
            );
            return;
        }

        db()->query(
            'INSERT INTO plugin_settings (plugin_slug, setting_key, setting_value, updated_at) VALUES (?, ?, ?, ?)',
            [self::SLUG, $key, $stored, date('Y-m-d H:i:s')]
        );
    }

    public static function tabs(string $active): array
    {
        $tabs = [
            'dashboard' => ['Камеры', '/admin/camera-manager', 'ci-video'],
            'sites' => ['Объекты', '/admin/camera-manager/sites', 'ci-map-pin'],
            'preview' => ['Конфигурация', '/admin/camera-manager/preview', 'ci-code'],
            'settings' => ['Настройки', '/admin/camera-manager/settings', 'ci-settings'],
        ];

        return array_map(static fn(array $tab, string $key): array => [
            'key' => $key,
            'label' => $tab[0],
            'href' => base_href($tab[1]),
            'icon' => $tab[2],
            'active' => $key === $active,
        ], array_values($tabs), array_keys($tabs));
    }

    public static function viewData(string $active, array $data = []): array
    {
        return array_merge([
            'tabs' => self::tabs($active),
            'settings' => self::settings(),
        ], $data);
    }

    public static function stats(): array
    {
        return [
            'sites' => (int)db()->query('SELECT COUNT(*) FROM camera_manager_sites WHERE enabled = 1')->getColumn(),
            'cameras' => (int)db()->query('SELECT COUNT(*) FROM camera_manager_cameras WHERE enabled = 1')->getColumn(),
            'offline' => (int)db()->query("SELECT COUNT(*) FROM camera_manager_cameras WHERE enabled = 1 AND last_health_status = 'offline'")->getColumn(),
        ];
    }

    public static function sites(): array
    {
        return db()->query(
            'SELECT s.*, COUNT(c.id) AS camera_count,
                    SUM(CASE WHEN c.enabled = 1 THEN 1 ELSE 0 END) AS enabled_camera_count
             FROM camera_manager_sites s
             LEFT JOIN camera_manager_cameras c ON c.site_id = s.id
             GROUP BY s.id
             ORDER BY s.code ASC, s.id ASC'
        )->get() ?: [];
    }

    public static function site(int $id): ?array
    {
        $site = db()->query('SELECT * FROM camera_manager_sites WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($site) ? $site : null;
    }

    public static function saveSite(array $data, ?int $id = null): int
    {
        $existing = $id !== null ? self::site($id) : null;
        if ($id !== null && $existing === null) {
            throw new RuntimeException('Объект не найден.');
        }

        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $recorderIp = trim((string)($data['recorder_ip'] ?? ''));
        $routerIp = trim((string)($data['router_ip'] ?? ''));
        $vpnIp = trim((string)($data['vpn_ip'] ?? ''));
        $username = trim((string)($data['rtsp_username'] ?? ''));
        $password = (string)($data['rtsp_password'] ?? '');
        $template = trim((string)($data['rtsp_path_template'] ?? ''));

        if (!preg_match('/^[A-Z0-9_-]{1,32}$/', $code)) {
            throw new RuntimeException('Код объекта: только латинские буквы, цифры, дефис и подчёркивание.');
        }
        if ($name === '' || mb_strlen($name) > 190) {
            throw new RuntimeException('Укажите название объекта.');
        }
        foreach (['IP регистратора' => $recorderIp, 'IP роутера' => $routerIp, 'VPN IP' => $vpnIp] as $label => $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new RuntimeException($label . ' указан некорректно.');
            }
        }
        if ($recorderIp === '' || $username === '') {
            throw new RuntimeException('IP регистратора и RTSP-логин обязательны.');
        }
        if ($template === '' || !str_starts_with($template, '/') || str_contains($template, "\n") || str_contains($template, "\r")) {
            throw new RuntimeException('RTSP-шаблон должен начинаться с /.');
        }
        if (!str_contains($template, '{channel}')) {
            throw new RuntimeException('RTSP-шаблон должен содержать {channel}.');
        }
        if ($id === null && $password === '') {
            throw new RuntimeException('Пароль RTSP обязателен для нового объекта.');
        }

        $encryptedPassword = $password !== ''
            ? SecretCipher::encrypt($password)
            : (string)$existing['rtsp_password_encrypted'];
        $values = [
            $code,
            $name,
            mb_substr(trim((string)($data['address'] ?? '')), 0, 255),
            $routerIp !== '' ? $routerIp : null,
            $recorderIp,
            $vpnIp !== '' ? $vpnIp : null,
            mb_substr(trim((string)($data['wireguard_public_key'] ?? '')), 0, 190),
            self::port($data['rtsp_port'] ?? 554, 554),
            self::port($data['management_port'] ?? 37777, 37777),
            mb_substr($username, 0, 190),
            $encryptedPassword,
            mb_substr($template, 0, 500),
            !empty($data['enabled']) ? 1 : 0,
        ];
        $now = date('Y-m-d H:i:s');

        if ($id !== null) {
            db()->query(
                'UPDATE camera_manager_sites
                 SET code = ?, name = ?, address = ?, router_ip = ?, recorder_ip = ?, vpn_ip = ?,
                     wireguard_public_key = ?, rtsp_port = ?, management_port = ?, rtsp_username = ?,
                     rtsp_password_encrypted = ?, rtsp_path_template = ?, enabled = ?, updated_at = ?
                 WHERE id = ?',
                array_merge($values, [$now, $id])
            );

            return $id;
        }

        db()->query(
            'INSERT INTO camera_manager_sites
             (code, name, address, router_ip, recorder_ip, vpn_ip, wireguard_public_key, rtsp_port,
              management_port, rtsp_username, rtsp_password_encrypted, rtsp_path_template, enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($values, [$now, $now])
        );

        return (int)db()->getInsertId();
    }

    public static function cameras(): array
    {
        return db()->query(
            'SELECT c.*, s.code AS site_code, s.name AS site_name, s.enabled AS site_enabled,
                    s.recorder_ip, s.rtsp_port
             FROM camera_manager_cameras c
             INNER JOIN camera_manager_sites s ON s.id = c.site_id
             ORDER BY s.code ASC, c.channel_number ASC, c.id ASC'
        )->get() ?: [];
    }

    public static function camera(int $id): ?array
    {
        $camera = db()->query('SELECT * FROM camera_manager_cameras WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($camera) ? $camera : null;
    }

    public static function saveCamera(array $data, ?int $id = null): int
    {
        if ($id !== null && self::camera($id) === null) {
            throw new RuntimeException('Камера не найдена.');
        }
        $siteId = (int)($data['site_id'] ?? 0);
        $site = self::site($siteId);
        if ($site === null) {
            throw new RuntimeException('Выберите объект.');
        }
        $channel = (int)($data['channel_number'] ?? 0);
        $subtype = (int)($data['subtype'] ?? 0);
        if ($channel < 1 || $channel > 4096 || $subtype < 0 || $subtype > 99) {
            throw new RuntimeException('Некорректный номер канала или субпотока.');
        }
        $streamKey = trim((string)($data['stream_key'] ?? ''));
        if ($streamKey === '') {
            $streamKey = strtolower((string)$site['code']) . '-' . str_pad((string)$channel, 2, '0', STR_PAD_LEFT);
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $streamKey)) {
            throw new RuntimeException('Некорректный ключ потока. Пример: 33-01.');
        }
        $name = trim((string)($data['name'] ?? '')) ?: 'Камера ' . $channel;
        $override = trim((string)($data['rtsp_path_override'] ?? ''));
        if ($override !== '' && (!str_starts_with($override, '/') || str_contains($override, "\n") || str_contains($override, "\r"))) {
            throw new RuntimeException('Индивидуальный RTSP-путь должен начинаться с /.');
        }
        $now = date('Y-m-d H:i:s');
        $values = [
            $siteId,
            $streamKey,
            mb_substr($name, 0, 190),
            $channel,
            $subtype,
            $override !== '' ? mb_substr($override, 0, 500) : null,
            !empty($data['enabled']) ? 1 : 0,
        ];

        if ($id !== null) {
            db()->query(
                'UPDATE camera_manager_cameras
                 SET site_id = ?, stream_key = ?, name = ?, channel_number = ?, subtype = ?,
                     rtsp_path_override = ?, enabled = ?, updated_at = ? WHERE id = ?',
                array_merge($values, [$now, $id])
            );

            return $id;
        }
        db()->query(
            "INSERT INTO camera_manager_cameras
             (site_id, stream_key, name, channel_number, subtype, rtsp_path_override, enabled,
              last_health_status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'unknown', ?, ?)",
            array_merge($values, [$now, $now])
        );

        return (int)db()->getInsertId();
    }

    public static function toggleCamera(int $id): void
    {
        if (self::camera($id) === null) {
            throw new RuntimeException('Камера не найдена.');
        }
        db()->query(
            'UPDATE camera_manager_cameras SET enabled = IF(enabled = 1, 0, 1), updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public static function activeStreams(): array
    {
        $rows = db()->query(
            'SELECT c.stream_key, c.channel_number, c.subtype, c.rtsp_path_override,
                    s.recorder_ip, s.rtsp_port, s.rtsp_username, s.rtsp_password_encrypted,
                    s.rtsp_path_template
             FROM camera_manager_cameras c
             INNER JOIN camera_manager_sites s ON s.id = c.site_id
             WHERE c.enabled = 1 AND s.enabled = 1
             ORDER BY s.code ASC, c.channel_number ASC, c.id ASC'
        )->get() ?: [];

        $streams = [];
        foreach ($rows as $row) {
            $path = trim((string)($row['rtsp_path_override'] ?: $row['rtsp_path_template']));
            $path = str_replace(
                ['{channel}', '{subtype}'],
                [(string)(int)$row['channel_number'], (string)(int)$row['subtype']],
                $path
            );
            $path = str_replace('&', '\\&', $path);
            $streams[] = [
                'stream_key' => (string)$row['stream_key'],
                'rtsp_url' => 'rtsp://'
                    . rawurlencode((string)$row['rtsp_username']) . ':'
                    . rawurlencode(SecretCipher::decrypt((string)$row['rtsp_password_encrypted'])) . '@'
                    . (string)$row['recorder_ip'] . ':' . (int)$row['rtsp_port'] . $path,
            ];
        }

        return $streams;
    }

    public static function preview(): string
    {
        return (new StreamsFilePublisher())->renderManagedBlock(self::activeStreams(), true);
    }

    public static function publish(): array
    {
        $streams = self::activeStreams();
        $settings = self::settings();
        try {
            if ($settings['connection_mode'] === 'pull') {
                $revision = max(0, (int)$settings['pull_revision']) + 1;
                $managedBlock = (new StreamsFilePublisher())->renderManagedBlock($streams);
                $snapshot = json_encode([
                    'revision' => $revision,
                    'restart' => !empty($settings['restart_on_publish']),
                    'stream_count' => count($streams),
                    'managed_block' => $managedBlock,
                    'managed_block_sha256' => hash('sha256', $managedBlock),
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                self::setSettingValue('pull_payload_encrypted', SecretCipher::encrypt($snapshot));
                self::setSettingValue('pull_revision', $revision);
                self::setSettingValue('pull_requested_at', date('Y-m-d H:i:s'));
                self::recordPublication(
                    'warning',
                    count($streams),
                    null,
                    'Ревизия ' . $revision . ' ожидает безопасного получения RTSP-сервером.'
                );

                return [
                    'stream_count' => count($streams),
                    'backup_path' => '',
                    'syntax_output' => 'Ожидает проверки на RTSP-сервере.',
                    'restarted' => false,
                    'restart_output' => 'Агент заберёт конфигурацию по HTTPS.',
                    'queued' => true,
                    'revision' => $revision,
                ];
            }

            if ($settings['connection_mode'] === 'ssh') {
                $transport = new SshCameraTransport($settings);
                $result = (new RemoteStreamsPublisher($transport))->publish(
                    $streams,
                    !empty($settings['restart_on_publish'])
                );
            } else {
                $result = (new StreamsFilePublisher())->publish($streams, $settings);
            }
            $warning = !empty($settings['restart_on_publish']) && !$result['restarted'];
            self::recordPublication($warning ? 'warning' : 'success', count($streams), $result['backup_path'], $warning
                ? 'streams.pl обновлён, но служба не перезапущена: ' . $result['restart_output']
                : 'Конфигурация опубликована.');

            return $result;
        } catch (Throwable $exception) {
            self::recordPublication('failed', count($streams), null, $exception->getMessage());
            throw $exception;
        }
    }

    public static function testConnection(): array
    {
        $settings = self::settings();
        if ($settings['connection_mode'] === 'pull') {
            $lastSeen = trim((string)$settings['pull_last_seen_at']);
            if ($lastSeen === '') {
                throw new RuntimeException('RTSP-агент ещё не обращался к HTTPS endpoint. Запустите его проверку на RTSP-сервере.');
            }

            return [
                'success' => true,
                'message' => 'RTSP-агент обращался к CMS: ' . $lastSeen
                    . ((string)$settings['pull_last_status'] !== '' ? ' · ' . (string)$settings['pull_last_status'] : ''),
                'streams_file' => '/var/www/html/rtsp/streams.pl',
                'service' => 'rtsp-streams.service',
            ];
        }
        if ($settings['connection_mode'] === 'ssh') {
            return (new SshCameraTransport($settings))->ping();
        }

        $path = (string)$settings['streams_file_path'];
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            throw new RuntimeException('Локальный streams.pl недоступен для чтения или записи.');
        }
        if (!is_executable((string)$settings['perl_binary'])) {
            throw new RuntimeException('Локальный Perl недоступен.');
        }

        return [
            'success' => true,
            'message' => 'Локальный streams.pl и Perl доступны.',
            'streams_file' => $path,
            'service' => (string)$settings['service_name'],
        ];
    }

    public static function latestPublication(): ?array
    {
        $row = db()->query('SELECT * FROM camera_manager_publications ORDER BY id DESC LIMIT 1')->getOne();

        return is_array($row) ? $row : null;
    }

    public static function hlsUrl(string $streamKey): string
    {
        return self::settings()['hls_base_url'] . '/stream-' . rawurlencode($streamKey) . '/index.m3u8';
    }

    public static function posterUrl(string $streamKey): string
    {
        return self::settings()['hls_base_url'] . '/tn-' . rawurlencode($streamKey) . '.jpg';
    }

    public static function probeCamera(int $id): array
    {
        $camera = self::camera($id);
        if ($camera === null) {
            throw new RuntimeException('Камера не найдена.');
        }
        $url = self::hlsUrl((string)$camera['stream_key']);
        $result = self::httpGet($url, 8);
        $segmentUrl = $result['status'] === 200 ? self::firstSegmentUrl($result['body'], $url) : '';
        $segment = $segmentUrl !== '' ? self::httpGet($segmentUrl, 8, true) : ['status' => 0, 'body' => ''];
        $online = $result['status'] === 200
            && str_contains($result['body'], '#EXTM3U')
            && $segmentUrl !== ''
            && in_array($segment['status'], [200, 206], true);
        $message = $online
            ? 'HLS-плейлист и первый медиасегмент доступны.'
            : 'HLS не готов: плейлист HTTP ' . $result['status'] . ', сегмент HTTP ' . $segment['status'] . '.';
        db()->query(
            'UPDATE camera_manager_cameras
             SET last_health_status = ?, last_health_message = ?, last_checked_at = ?, updated_at = ? WHERE id = ?',
            [$online ? 'online' : 'offline', $message, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]
        );

        return ['online' => $online, 'message' => $message, 'url' => $url];
    }

    public static function t(string $key): string
    {
        $value = \FBL\Language::get($key);

        return $value === $key ? $key : $value;
    }

    private static function httpGet(string $url, int $timeout, bool $firstByteOnly = false): array
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                return ['status' => 0, 'body' => ''];
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'FIREBALL-CMS/camera-manager',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($firstByteOnly) {
                curl_setopt($handle, CURLOPT_RANGE, '0-0');
            }
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            $body = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return ['status' => $status, 'body' => is_string($body) ? $body : ''];
        }

        $headers = $firstByteOnly ? "Range: bytes=0-0\r\n" : '';
        $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => $headers]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $match)) {
                $status = (int)$match[1];
            }
        }

        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }

    private static function firstSegmentUrl(string $manifest, string $manifestUrl): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $manifest) ?: [];
        $expectSegment = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXTINF')) {
                $expectSegment = true;
                continue;
            }
            if (!$expectSegment || $line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (filter_var($line, FILTER_VALIDATE_URL)) {
                return $line;
            }
            $base = parse_url($manifestUrl);
            if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
                return '';
            }
            $port = isset($base['port']) ? ':' . (int)$base['port'] : '';
            if (str_starts_with($line, '/')) {
                $path = $line;
            } else {
                $directory = preg_replace('~/[^/]*$~', '/', (string)($base['path'] ?? '/')) ?: '/';
                $path = $directory . $line;
            }

            return $base['scheme'] . '://' . $base['host'] . $port . $path;
        }

        return '';
    }

    public static function recordPublication(string $status, int $streamCount, ?string $backupPath, string $message): void
    {
        $user = (array)get_user();
        db()->query(
            'INSERT INTO camera_manager_publications
             (user_id, stream_count, status, backup_path, message, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                !empty($user['id']) ? (int)$user['id'] : null,
                $streamCount,
                $status,
                $backupPath,
                mb_substr($message, 0, 1000),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    private static function port(mixed $value, int $default): int
    {
        $port = (int)$value;

        return $port >= 1 && $port <= 65535 ? $port : $default;
    }

    private static function assertStoredPullTokenHash(string $expectedHash): void
    {
        $storedHash = strtolower(trim((string)self::settingValue('pull_token_hash', '')));
        if (preg_match('/^[a-f0-9]{64}$/', $storedHash) !== 1 || !hash_equals($expectedHash, $storedHash)) {
            throw new RuntimeException('SprintHost не сохранил HTTPS-токен в настройках плагина. Проверьте доступ базы данных на запись.');
        }
    }

    private static function synchronizeSettingRows(): void
    {
        if (!function_exists('db')) {
            return;
        }

        $rows = db()->query(
            'SELECT setting_key, setting_value, updated_at FROM plugin_settings WHERE plugin_slug = ? ORDER BY id DESC',
            [self::SLUG]
        )->get() ?: [];
        $latest = [];
        $counts = [];
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if (!isset($latest[$key])) {
                $latest[$key] = [
                    'value' => (string)($row['setting_value'] ?? 'null'),
                    'updated_at' => (string)($row['updated_at'] ?? date('Y-m-d H:i:s')),
                ];
            }
        }

        foreach ($latest as $key => $row) {
            if (($counts[$key] ?? 0) < 2) {
                continue;
            }
            db()->query(
                'UPDATE plugin_settings SET setting_value = ?, updated_at = ? WHERE plugin_slug = ? AND setting_key = ?',
                [$row['value'], $row['updated_at'], self::SLUG, $key]
            );
        }
    }

    private static function ensureDatabaseSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $required = ['camera_manager_sites', 'camera_manager_cameras', 'camera_manager_publications'];
        $missing = false;
        foreach ($required as $table) {
            $exists = (int)db()->query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->getColumn() === 1;
            $missing = $missing || !$exists;
        }
        if ($missing) {
            $file = __DIR__ . '/migrations/001_create_camera_manager_tables.sql';
            $sql = is_file($file) ? (string)file_get_contents($file) : '';
            if ($sql === '') {
                throw new RuntimeException('Camera Manager migration is missing.');
            }
            (new SqlFileRunner())->executeDatabase($sql);
        }
        $ensured = true;
    }
}
