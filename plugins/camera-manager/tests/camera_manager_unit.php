<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/ProcessRunner.php';
require_once dirname(__DIR__) . '/src/StreamsFilePublisher.php';
require_once dirname(__DIR__) . '/src/SecretCipher.php';
require_once dirname(__DIR__) . '/src/SshCameraTransport.php';
require_once dirname(__DIR__) . '/src/RemoteStreamsPublisher.php';
require_once dirname(__DIR__) . '/src/PullSyncService.php';

use Fireball\CameraManager\RemoteStreamsPublisher;
use Fireball\CameraManager\ProcessRunner;
use Fireball\CameraManager\PullSyncService;
use Fireball\CameraManager\SecretCipher;
use Fireball\CameraManager\SshCameraTransport;
use Fireball\CameraManager\StreamsFilePublisher;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if (!defined('CHAT_ENCRYPTION_KEY')) {
    define('CHAT_ENCRYPTION_KEY', 'camera-manager-unit-test-key');
}
$secret = 'User_DEF_11!@#';
$encryptedSecret = SecretCipher::encrypt($secret);
$assert($encryptedSecret !== '' && !str_contains($encryptedSecret, $secret), 'The RTSP password was not encrypted.');
$assert(SecretCipher::decrypt($encryptedSecret) === $secret, 'The encrypted RTSP password cannot be decrypted.');

$temporaryDirectory = sys_get_temp_dir() . '/fireball-camera-manager-' . bin2hex(random_bytes(6));
if (!mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
    throw new RuntimeException('Unable to create test directory.');
}

$streamsPath = $temporaryDirectory . '/streams.pl';
$fixture = <<<'PERL'
#!/usr/bin/perl
use strict;
use warnings;

my %cam = (
    '01-01' => 'rtsp://legacy.example/channel=1',
);

print scalar(keys %cam);
PERL;
file_put_contents($streamsPath, $fixture);
chmod($streamsPath, 0600);

$publisher = new StreamsFilePublisher();
$streams = [
    ['stream_key' => '33-02', 'rtsp_url' => 'rtsp://user:secret@192.168.33.201:554/cam/realmonitor?channel=2\\&subtype=0'],
    ['stream_key' => '33-01', 'rtsp_url' => 'rtsp://user:secret@192.168.33.201:554/cam/realmonitor?channel=1\\&subtype=0'],
];
$settings = [
    'streams_file_path' => $streamsPath,
    'perl_binary' => is_executable('/usr/bin/perl') ? '/usr/bin/perl' : '/opt/homebrew/bin/perl',
    'service_name' => 'rtsp-streams.service',
    'restart_on_publish' => false,
    'use_sudo' => false,
];

$first = $publisher->publish($streams, $settings);
$published = (string)file_get_contents($streamsPath);
$assert($first['stream_count'] === 2, 'The publisher reported the wrong stream count.');
$assert(is_file($first['backup_path']), 'The first publication did not create a backup.');
$assert(substr_count($published, StreamsFilePublisher::BEGIN_MARKER) === 1, 'The managed block marker is missing or duplicated.');
$assert(str_contains($published, "'01-01' =>"), 'The legacy camera was removed.');
$assert(strpos($published, "'33-01' =>") < strpos($published, "'33-02' =>"), 'Managed streams are not naturally sorted.');

$verifier = dirname(__DIR__) . '/server/fireball-camera-verify';
$verifierCurrent = $temporaryDirectory . '/verifier-current.pl';
$verifierCandidate = $temporaryDirectory . '/verifier-candidate.pl';
file_put_contents($verifierCurrent, $fixture);
file_put_contents($verifierCandidate, $published);
$verification = (new ProcessRunner())->run([$verifier, $verifierCurrent, $verifierCandidate], 10);
$assert($verification['exit_code'] === 0, 'The remote verifier rejected a valid managed-block-only change: ' . $verification['output']);

$outsideMutation = str_replace('print scalar(keys %cam);', 'print "changed outside managed block";', $published);
file_put_contents($verifierCandidate, $outsideMutation);
$verification = (new ProcessRunner())->run([$verifier, $verifierCurrent, $verifierCandidate], 10);
$assert($verification['exit_code'] !== 0, 'The remote verifier accepted a change outside the managed block.');

$duplicateLegacyKey = preg_replace("/'33-01'\\s*=>/", "'01-01' =>", $published, 1);
file_put_contents($verifierCandidate, $duplicateLegacyKey);
$verification = (new ProcessRunner())->run([$verifier, $verifierCurrent, $verifierCandidate], 10);
$assert($verification['exit_code'] !== 0, 'The remote verifier accepted a managed key that duplicates a legacy camera.');

$unsupportedManagedLine = str_replace(
    StreamsFilePublisher::END_MARKER,
    "system('unauthorized');\n    " . StreamsFilePublisher::END_MARKER,
    $published
);
file_put_contents($verifierCandidate, $unsupportedManagedLine);
$verification = (new ProcessRunner())->run([$verifier, $verifierCurrent, $verifierCandidate], 10);
$assert($verification['exit_code'] !== 0, 'The remote verifier accepted executable code inside the managed block.');

$second = $publisher->publish([$streams[0]], $settings);
$republished = (string)file_get_contents($streamsPath);
$assert($second['stream_count'] === 1, 'The second publication reported the wrong stream count.');
$assert(substr_count($republished, StreamsFilePublisher::BEGIN_MARKER) === 1, 'Repeated publication duplicated the managed block.');
$assert(!str_contains($republished, "'33-01' =>"), 'A removed managed stream remained in streams.pl.');
$assert(str_contains($republished, "'33-02' =>"), 'The remaining managed stream disappeared.');

$preview = $publisher->renderManagedBlock($streams, true);
$assert(!str_contains($preview, 'secret'), 'The redacted preview exposed an RTSP password.');
$assert(str_contains($preview, 'rtsp://***:***@192.168.33.201'), 'The redacted preview is not useful.');

$collisionThrown = false;
try {
    $publisher->merge($fixture, $publisher->renderManagedBlock([
        ['stream_key' => '01-01', 'rtsp_url' => 'rtsp://user:pass@example.test/live'],
    ]), [
        ['stream_key' => '01-01', 'rtsp_url' => 'rtsp://user:pass@example.test/live'],
    ]);
} catch (RuntimeException) {
    $collisionThrown = true;
}
$assert($collisionThrown, 'A stream key collision outside the managed block was accepted.');

$beforeFailure = (string)file_get_contents($streamsPath);
$invalidPath = $temporaryDirectory . '/invalid-source';
file_put_contents($invalidPath, "my %cam = (\n");
$invalidThrown = false;
try {
    $publisher->merge((string)file_get_contents($invalidPath), $publisher->renderManagedBlock([]), []);
} catch (RuntimeException) {
    $invalidThrown = true;
}
$assert($invalidThrown, 'An incomplete %cam source was accepted.');
$assert((string)file_get_contents($streamsPath) === $beforeFailure, 'A failed merge changed the working streams.pl.');

$identityFile = $temporaryDirectory . '/identity';
$knownHostsFile = $temporaryDirectory . '/known_hosts';
$remoteStreamsFile = $temporaryDirectory . '/remote-streams.pl';
$capturedPublication = $temporaryDirectory . '/captured-publication';
$fakeSsh = $temporaryDirectory . '/fake-ssh';
file_put_contents($identityFile, 'unit-test-private-key-placeholder');
chmod($identityFile, 0600);
file_put_contents($knownHostsFile, 'example.test ssh-ed25519 unit-test-host-key');
file_put_contents($remoteStreamsFile, $fixture);
$fakeSshSource = '#!/bin/sh' . "\n"
    . 'IFS= read -r request || exit 2' . "\n"
    . 'case "$request" in' . "\n"
    . 'PING) printf \'{"success":true,"message":"Camera agent is ready.","streams_file":"/srv/streams.pl","service":"rtsp-streams.service"}\\n\' ;;' . "\n"
    . 'READ) cat -- ' . escapeshellarg($remoteStreamsFile) . ' ;;' . "\n"
    . '"PUBLISH 0"|"PUBLISH 1") cat > ' . escapeshellarg($capturedPublication)
        . '; cp ' . escapeshellarg($capturedPublication) . ' ' . escapeshellarg($remoteStreamsFile)
        . '; printf \'{"success":true,"backup_path":"/srv/backups/streams.pl.bak","restarted":true,"message":"published"}\\n\' ;;' . "\n"
    . '*) exit 3 ;;' . "\n"
    . 'esac' . "\n";
file_put_contents($fakeSsh, $fakeSshSource);
chmod($fakeSsh, 0700);

$sshSettings = [
    'ssh_binary' => $fakeSsh,
    'ssh_host' => 'example.test',
    'ssh_port' => 22,
    'ssh_user' => 'camera-sync',
    'ssh_identity_file' => $identityFile,
    'ssh_known_hosts_file' => $knownHostsFile,
];
$transport = new SshCameraTransport($sshSettings);
$ping = $transport->ping();
$assert($ping['success'] && $ping['streams_file'] === '/srv/streams.pl', 'The SSH camera agent handshake failed.');
$assert(str_contains($transport->readStreamsFile(), "'01-01' =>"), 'The SSH transport did not read streams.pl.');
$remoteResult = (new RemoteStreamsPublisher($transport))->publish([$streams[0]], true);
$remotePublished = (string)file_get_contents($capturedPublication);
$assert($remoteResult['restarted'] === true, 'The remote publisher did not report the service restart.');
$assert(str_contains($remotePublished, StreamsFilePublisher::BEGIN_MARKER), 'The SSH publisher did not send the managed block.');
$assert(str_contains($remotePublished, "'01-01' =>"), 'The SSH publisher removed a legacy camera.');
$assert(str_contains($remotePublished, "'33-02' =>"), 'The SSH publisher omitted the managed camera.');

$pullToken = str_repeat('ab', 32);
$pullSnapshotBlock = (new StreamsFilePublisher())->renderManagedBlock($streams);
$pullSnapshot = json_encode([
    'revision' => 7,
    'restart' => true,
    'stream_count' => count($streams),
    'managed_block' => $pullSnapshotBlock,
    'managed_block_sha256' => hash('sha256', $pullSnapshotBlock),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$GLOBALS['camera_manager_pull_settings'] = [
    'connection_mode' => 'pull',
    'pull_token_hash' => hash('sha256', $pullToken),
    'pull_payload_encrypted' => SecretCipher::encrypt($pullSnapshot),
    'pull_revision' => 7,
    'pull_last_revision' => 0,
    'pull_last_report_fingerprint' => '',
    'restart_on_publish' => true,
];
$GLOBALS['camera_manager_pull_streams'] = $streams;
$GLOBALS['camera_manager_pull_publications'] = [];

if (!function_exists('plugin_setting')) {
    function plugin_setting(string $slug, string $key, mixed $default = null): mixed
    {
        return $GLOBALS['camera_manager_pull_settings'][$key] ?? $default;
    }
}
if (!function_exists('plugin_setting_set')) {
    function plugin_setting_set(string $slug, string $key, mixed $value): void
    {
        $GLOBALS['camera_manager_pull_settings'][$key] = $value;
    }
}
if (!class_exists('FireballPluginCameraManager')) {
    final class FireballPluginCameraManager
    {
        public const SLUG = 'camera-manager';

        public static function settings(): array
        {
            return $GLOBALS['camera_manager_pull_settings'];
        }

        public static function activeStreams(): array
        {
            return $GLOBALS['camera_manager_pull_streams'];
        }

        public static function recordPublication(
            string $status,
            int $streamCount,
            ?string $backupPath,
            string $message
        ): void {
            $GLOBALS['camera_manager_pull_publications'][] = compact(
                'status',
                'streamCount',
                'backupPath',
                'message'
            );
        }
    }
}

$pullService = new PullSyncService();
$assert($pullService->authenticate($pullToken), 'The HTTPS pull token was not authenticated.');
$assert(!$pullService->authenticate(str_repeat('cd', 32)), 'An invalid HTTPS pull token was accepted.');
$pullFetch = $pullService->handle(['action' => 'fetch', 'current_revision' => 0]);
$assert(!empty($pullFetch['changed']) && $pullFetch['revision'] === 7, 'The HTTPS pull revision was not offered.');
$assert(
    str_contains((string)$pullFetch['managed_block'], "'33-01' =>")
        && str_contains((string)$pullFetch['managed_block'], "'33-02' =>"),
    'The HTTPS pull payload omitted managed cameras.'
);
$GLOBALS['camera_manager_pull_streams'] = [];
$pullSnapshotRetry = $pullService->handle(['action' => 'fetch', 'current_revision' => 0]);
$assert(
    $pullSnapshotRetry['managed_block'] === $pullFetch['managed_block'],
    'The queued HTTPS pull snapshot changed after later camera edits.'
);
$pullUnchanged = $pullService->handle(['action' => 'fetch', 'current_revision' => 7]);
$assert(empty($pullUnchanged['changed']), 'The HTTPS pull endpoint resent an applied revision.');

$pullReport = [
    'action' => 'report',
    'revision' => 7,
    'status' => 'success',
    'message' => 'configuration applied',
    'backup_path' => '/var/www/html/rtsp/.fireball-camera-manager-backups/streams.pl.20260829-test.bak',
    'stream_count' => 2,
];
$pullService->handle($pullReport);
$pullService->handle($pullReport);
$assert(
    $GLOBALS['camera_manager_pull_settings']['pull_last_revision'] === 7,
    'The HTTPS pull report did not update the applied revision.'
);
$assert(
    count($GLOBALS['camera_manager_pull_publications']) === 1,
    'A retried HTTPS pull report created a duplicate publication record.'
);

$cleanup = static function (string $directory) use (&$cleanup): void {
    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $directory . '/' . $item;
        if (is_dir($path)) {
            $cleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
};
$cleanup($temporaryDirectory);

echo "Camera Manager unit checks passed.\n";
