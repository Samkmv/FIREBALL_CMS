<?php

namespace Fireball\CameraManager;

use RuntimeException;

final class ProcessRunner
{
    /** @return array{exit_code:int, output:string, stdout:string, stderr:string} */
    public function run(array $command, int $timeoutSeconds = 30, string $standardInput = ''): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is required for syntax checks and service control.');
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptorSpec, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the required process.');
        }

        if ($standardInput !== '') {
            $offset = 0;
            $length = strlen($standardInput);
            while ($offset < $length) {
                $written = fwrite($pipes[0], substr($standardInput, $offset));
                if ($written === false || $written === 0) {
                    fclose($pipes[0]);
                    proc_terminate($process, 15);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    throw new RuntimeException('Unable to send data to the required process.');
                }
                $offset += $written;
            }
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $startedAt = microtime(true);
        $stdout = '';
        $stderr = '';

        while (true) {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) - $startedAt > $timeoutSeconds) {
                proc_terminate($process, 15);
                usleep(200000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9);
                }
                $stdout .= (string)stream_get_contents($pipes[1]);
                $stderr .= (string)stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new RuntimeException('The process exceeded the time limit.');
            }
            usleep(50000);
        }

        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = (int)$status['exitcode'];
        $closedCode = proc_close($process);
        if ($exitCode < 0 && is_int($closedCode)) {
            $exitCode = $closedCode;
        }

        return [
            'exit_code' => $exitCode,
            'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
