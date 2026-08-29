<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class SshCameraTransport
{
    public function __construct(
        private readonly array $settings,
        private readonly ProcessRunner $processRunner = new ProcessRunner()
    ) {
    }

    /** @return array{success:bool, message:string, streams_file?:string, service?:string} */
    public function ping(): array
    {
        $result = $this->request("PING\n", 20);
        $payload = $this->decodeJsonResponse($result['stdout']);
        if ($result['exit_code'] !== 0 || empty($payload['success'])) {
            $agentMessage = trim((string)($payload['message'] ?? ''));
            throw new RuntimeException($agentMessage !== ''
                ? 'SSH-агент отклонил проверку: ' . $agentMessage
                : $this->connectionError($result['output'], $result['exit_code']));
        }

        return [
            'success' => true,
            'message' => (string)($payload['message'] ?? 'SSH connection established.'),
            'streams_file' => (string)($payload['streams_file'] ?? ''),
            'service' => (string)($payload['service'] ?? ''),
        ];
    }

    public function readStreamsFile(): string
    {
        $result = $this->request("READ\n", 30);
        if ($result['exit_code'] !== 0 || !str_contains($result['stdout'], '%cam')) {
            throw new RuntimeException('Не удалось прочитать удалённый streams.pl: ' . $this->safeOutput($result['output']));
        }

        return $result['stdout'];
    }

    /** @return array{success:bool, backup_path:string, restarted:bool, message:string} */
    public function publish(string $contents, bool $restart): array
    {
        if ($contents === '' || !str_contains($contents, '%cam')) {
            throw new RuntimeException('Refusing to publish an empty or invalid streams.pl payload.');
        }
        $request = 'PUBLISH ' . ($restart ? '1' : '0') . "\n" . $contents;
        if (!str_ends_with($request, "\n")) {
            $request .= "\n";
        }
        $result = $this->request($request, 60);
        $payload = $this->decodeJsonResponse($result['stdout']);
        if ($result['exit_code'] !== 0 || empty($payload['success'])) {
            $message = trim((string)($payload['message'] ?? ''));
            throw new RuntimeException('Удалённая публикация отклонена: ' . ($message !== '' ? $message : $this->safeOutput($result['output'])));
        }

        return [
            'success' => true,
            'backup_path' => (string)($payload['backup_path'] ?? ''),
            'restarted' => !empty($payload['restarted']),
            'message' => (string)($payload['message'] ?? 'Remote configuration published.'),
        ];
    }

    /** @return array{exit_code:int, output:string, stdout:string, stderr:string} */
    private function request(string $input, int $timeoutSeconds): array
    {
        return $this->processRunner->run($this->command(), $timeoutSeconds, $input);
    }

    private function command(): array
    {
        $sshBinary = $this->absoluteReadableFile('ssh_binary', true);
        $identityFile = $this->absoluteReadableFile('ssh_identity_file');
        $knownHostsFile = $this->absoluteReadableFile('ssh_known_hosts_file');
        $identityMode = fileperms($identityFile);
        if ($identityMode !== false && (($identityMode & 0777) & 0077) !== 0) {
            throw new RuntimeException('Приватный SSH-ключ должен иметь права 0600 или строже.');
        }
        $host = trim((string)($this->settings['ssh_host'] ?? ''));
        $user = trim((string)($this->settings['ssh_user'] ?? ''));
        $port = (int)($this->settings['ssh_port'] ?? 22);

        if (!preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?|\[[0-9a-fA-F:]+\])$/', $host)) {
            throw new RuntimeException('Некорректный адрес SSH-сервера.');
        }
        if (!preg_match('/^[a-z_][a-z0-9_-]{0,31}$/i', $user)) {
            throw new RuntimeException('Некорректный SSH-пользователь.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Некорректный SSH-порт.');
        }

        return [
            $sshBinary,
            '-T',
            '-p', (string)$port,
            '-i', $identityFile,
            '-o', 'BatchMode=yes',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'PasswordAuthentication=no',
            '-o', 'KbdInteractiveAuthentication=no',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile=' . $knownHostsFile,
            '-o', 'ConnectTimeout=10',
            '-o', 'ServerAliveInterval=10',
            '-o', 'ServerAliveCountMax=2',
            $user . '@' . $host,
        ];
    }

    private function absoluteReadableFile(string $key, bool $executable = false): string
    {
        $path = trim((string)($this->settings[$key] ?? ''));
        if ($path === '' || $path[0] !== '/' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Файл настройки ' . $key . ' не найден или недоступен PHP.');
        }
        if ($executable && !is_executable($path)) {
            throw new RuntimeException('SSH-клиент не является исполняемым файлом.');
        }

        return $path;
    }

    private function decodeJsonResponse(string $output): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $decoded = json_decode(trim($lines[$index]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function connectionError(string $output, int $exitCode): string
    {
        $safe = $this->safeOutput($output);

        return 'SSH-подключение не установлено (код ' . $exitCode . ')' . ($safe !== '' ? ': ' . $safe : '.');
    }

    private function safeOutput(string $output): string
    {
        $output = preg_replace('~(rtsp://)[^\s@]+@~i', '$1***:***@', trim($output)) ?? '';

        return mb_substr($output, 0, 1000);
    }
}
