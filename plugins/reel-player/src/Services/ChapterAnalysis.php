<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Suggestions only: this service never changes an audio file or saves chapters. */
final class ChapterAnalysis
{
    public const RANGE_REQUESTS = 128;
    public const RANGE_BYTES = 128 * 1048576;
    private const MAX_OUTPUT = 1048576;
    private const MAX_DURATION = 604800.0;
    private const QUIET = 0.012589254; // -38 dBFS, matching the optional FFmpeg detector.
    private const MIN_PAUSE = 0.8;
    private const MIN_SPACING = 15.0;
    private ?string $ffmpeg;

    public function __construct(?string $ffmpeg = null, private float $timeout = 45.0)
    {
        $this->ffmpeg = $ffmpeg === null ? self::findFfmpeg() : (is_file($ffmpeg) && is_executable($ffmpeg) ? realpath($ffmpeg) ?: null : null);
        $this->timeout = is_finite($timeout) ? max(1.0, min(55.0, $timeout)) : 45.0;
    }

    public function capabilities(): array
    {
        return ['wav' => true, 'ffmpeg' => $this->ffmpeg !== null && function_exists('proc_open'), 'recognition' => false];
    }

    public function analyze(string $path, float $duration = 0): array
    {
        self::assertDuration($duration);
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) throw new \InvalidArgumentException('Аудиофайл недоступен для анализа.');
        $handle = @fopen($real, 'rb');
        if (!$handle) throw new \RuntimeException('Не удалось открыть аудиофайл для анализа.');
        try {
            $size = (int)filesize($real);
            $reader = static function(int $offset, int $length) use ($handle): string {
                if (fseek($handle, $offset) !== 0) throw new \RuntimeException('Не удалось прочитать аудиофайл.');
                $bytes = fread($handle, $length);
                if ($bytes === false || strlen($bytes) !== $length) throw new \InvalidArgumentException('Аудиофайл повреждён или обрезан.');
                return $bytes;
            };
            if ($size >= 12 && substr($reader(0, 12), 0, 4) === 'RIFF') {
                try {
                    $wave = self::wave($reader, $size);
                    return $this->waveAnalysis($reader, $wave, $duration, false, microtime(true) + $this->timeout);
                } catch (\UnexpectedValueException $error) {
                    if ($this->ffmpeg === null) throw new \InvalidArgumentException($error->getMessage());
                }
            }
        } finally { fclose($handle); }
        if (!$this->capabilities()['ffmpeg']) throw new \InvalidArgumentException('Для автопоиска в этом формате нужен FFmpeg на сервере. Без него доступен анализ PCM WAV. Таймкоды и импорт CUE работают для всех поддерживаемых форматов.');
        return $this->ffmpegAnalysis($real, $duration);
    }

    /** Reader must return exact bytes from an already authorized, version-pinned source. */
    public function analyzeRanges(callable $reader, int $size, float $duration = 0): array
    {
        self::assertDuration($duration);
        if ($size < 44) throw new \InvalidArgumentException('Аудиофайл пустой или повреждён.');
        $requests = 0; $received = 0; $deadline = microtime(true) + $this->timeout;
        $limited = static function(int $offset, int $length) use ($reader, $size, &$requests, &$received, $deadline): string {
            if (microtime(true) > $deadline) throw new \RuntimeException('Время анализа истекло. Попробуйте CUE или ручные таймкоды.');
            if ($offset < 0 || $length < 1 || $offset > $size - $length || $length > 2 * 1048576) throw new \InvalidArgumentException('Недопустимый диапазон аудиофайла.');
            if (++$requests > self::RANGE_REQUESTS || $received + $length > self::RANGE_BYTES) throw new \RuntimeException('Достигнут безопасный лимит анализа Google Drive. Используйте CUE или ручные таймкоды.');
            $bytes = $reader($offset, $length);
            if (!is_string($bytes) || strlen($bytes) !== $length) throw new \RuntimeException('Google Drive вернул неполный диапазон. Повторите анализ.');
            $received += $length;
            return $bytes;
        };
        // Header cache also bounds pathological files containing many tiny chunks.
        $header = $limited(0, min($size, 65536));
        $headerReader = static function(int $offset, int $length) use ($header): string {
            if ($offset < 0 || $offset + $length > strlen($header)) throw new \UnexpectedValueException('WAV с большим служебным заголовком требует FFmpeg или CUE.');
            return substr($header, $offset, $length);
        };
        try { $wave = self::wave($headerReader, $size); }
        catch (\UnexpectedValueException $error) {
            throw new \InvalidArgumentException('Для автопоиска Google Drive сейчас поддерживается PCM WAV. Для MP3, FLAC и других форматов используйте CUE или ручные таймкоды.');
        }
        $partial = $size > 127 * 1048576;
        if ($partial) {
            $result = $this->waveAnalysis($limited, $wave, $duration, true, $deadline);
        } else {
            // A single block is retained, never the complete recording. Sequential
            // analysis needs at most 127 one-MiB requests plus the header request.
            $blockOffset = -1; $block = '';
            $cached = static function(int $offset, int $length) use ($limited, $size, &$blockOffset, &$block): string {
                $end = $offset + $length; $bytes = '';
                while ($offset < $end) {
                    $nextBlock = intdiv($offset, 1048576) * 1048576;
                    if ($blockOffset !== $nextBlock) { $blockOffset = $nextBlock; $block = $limited($nextBlock, min(1048576, $size - $nextBlock)); }
                    $count = min($end - $offset, strlen($block) - ($offset - $blockOffset));
                    $bytes .= substr($block, $offset - $blockOffset, $count); $offset += $count;
                }
                return $bytes;
            };
            $result = $this->waveAnalysis($cached, $wave, $duration, false, $deadline);
        }
        $result['requests'] = $requests; $result['bytesAnalyzed'] = $received;
        return $result;
    }

    private static function assertDuration(float $duration): void
    {
        if (!is_finite($duration) || $duration < 0 || $duration > self::MAX_DURATION) throw new \InvalidArgumentException('Некорректная длительность записи.');
    }

    private static function wave(callable $read, int $size): array
    {
        $header = $read(0, 12);
        if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') throw new \UnexpectedValueException('Нужен PCM WAV либо установленный FFmpeg.');
        $declared = unpack('V', substr($header, 4, 4))[1] + 8;
        if ($declared > $size) throw new \InvalidArgumentException('WAV-файл обрезан.');
        $format = null; $offset = 12;
        while ($offset + 8 <= min($size, 65536)) {
            $chunk = $read($offset, 8); $length = unpack('V', substr($chunk, 4, 4))[1]; $offset += 8;
            if ($length > $size - $offset) throw new \InvalidArgumentException('Повреждённый WAV-файл.');
            if (substr($chunk, 0, 4) === 'fmt ') {
                if ($length < 16 || $length > 4096) throw new \UnexpectedValueException('Неподдерживаемый заголовок WAV.');
                $fmt = $read($offset, min($length, 40));
                $format = unpack('vformat/vchannels/Vrate/VbyteRate/valign/vbits', substr($fmt, 0, 16));
                if ($format['format'] === 65534 && strlen($fmt) >= 40 && substr($fmt, 26, 14) === hex2bin('000000001000800000aa00389b71')) $format['format'] = unpack('v', substr($fmt, 24, 2))[1];
            }
            if (substr($chunk, 0, 4) === 'data') {
                if (!$format) throw new \InvalidArgumentException('WAV не содержит корректного описания сигнала.');
                if (!in_array($format['format'], [1, 3], true) || !in_array($format['bits'], $format['format'] === 1 ? [8,16,24,32] : [32,64], true)) throw new \UnexpectedValueException('Сжатый WAV требует FFmpeg.');
                if ($format['channels'] < 1 || $format['channels'] > 8 || $format['rate'] < 8000 || $format['rate'] > 192000 || $format['align'] !== $format['channels'] * intdiv($format['bits'], 8) || $format['byteRate'] !== $format['rate'] * $format['align']) throw new \InvalidArgumentException('Некорректные параметры WAV-сигнала.');
                $format['offset'] = $offset; $format['length'] = $length;
                $format['duration'] = floor($length / $format['align']) / $format['rate'];
                self::assertDuration($format['duration']);
                return $format;
            }
            $offset += $length + ($length % 2);
        }
        throw new \UnexpectedValueException('Не найден аудиосигнал WAV в доступном заголовке.');
    }

    private function waveAnalysis(callable $reader, array $wave, float $expected, bool $partial, float $deadline): array
    {
        $duration = $wave['duration']; $boundaries = []; $quietStart = null;
        $interval = $partial ? max(2.0, $duration / 120) : 0.25;
        $window = $partial ? 2.0 : 0.12;
        for ($time = 0.0; $time + $window < $duration; $time += $interval) {
            if (microtime(true) > $deadline || connection_aborted()) throw new \RuntimeException('Анализ остановлен по лимиту времени. Используйте CUE или ручные таймкоды.');
            $frame = (int)floor($time * $wave['rate']); $frames = (int)floor($window * $wave['rate']);
            $signal = $reader($wave['offset'] + $frame * $wave['align'], $frames * $wave['align']);
            // Remote coarse scans still inspect consecutive 120ms samples within
            // each two-second window; they do not treat unsampled spans as silence.
            if ($partial) $quietStart = null;
            for ($within = 0.0; $within < $window; $within += $partial ? 0.25 : $window) {
                $from = (int)floor($within * $wave['rate']);
                $count = min((int)floor(0.12 * $wave['rate']), $frames - $from);
                $quiet = self::rms($signal, $from, $count, $wave) < self::QUIET;
                $at = $time + $within;
                if ($quiet && $quietStart === null) $quietStart = $at;
                elseif (!$quiet && $quietStart !== null) {
                    if ($at - $quietStart >= self::MIN_PAUSE) $boundaries[] = $at;
                    $quietStart = null;
                }
            }
            // A quiet sampled window is an approximate suggestion even if its
            // actual ending lies outside the window; never claim exact coverage.
            if ($partial && $quietStart !== null && $time + $window - $quietStart >= self::MIN_PAUSE) $boundaries[] = $time + $window;
        }
        $warnings = self::warnings($duration, $expected);
        if ($partial) $warnings[] = 'Google Drive: проверены только короткие окна записи. Переходы могут быть пропущены, а времена приблизительны. Проверьте и исправьте каждый результат перед сохранением.';
        return self::result($boundaries, $duration, $partial ? 'wav-sampled-silence' : 'wav-silence', $warnings, $partial);
    }

    private static function rms(string $bytes, int $first, int $count, array $wave): float
    {
        $step = max(1, (int)round($wave['rate'] / 1000)); $width = intdiv($wave['bits'], 8); $sum = 0.0; $n = 0; $sampleIndex = 0;
        // A fixed one-kHz stride can sample a one-kHz tone at the same zero
        // crossing forever. Deterministic nonuniform spacing breaks that alias
        // while keeping the number of inspected frames and memory bounded.
        $jitter = [-2, 1, 3, 0, -1];
        for ($frame = $first; $frame < $first + $count; $frame += max(1, $step + $jitter[$sampleIndex++ % 5])) {
            for ($channel = 0; $channel < $wave['channels']; $channel++) {
                $offset = $frame * $wave['align'] + $channel * $width;
                $raw = substr($bytes, $offset, $width);
                if (strlen($raw) !== $width) throw new \InvalidArgumentException('Обрезанный WAV-сигнал.');
                if ($wave['format'] === 3) $sample = unpack($width === 4 ? 'g' : 'e', $raw)[1];
                elseif ($width === 1) $sample = (ord($raw) - 128) / 128;
                elseif ($width === 2) { $v = unpack('v', $raw)[1]; $sample = ($v >= 32768 ? $v - 65536 : $v) / 32768; }
                elseif ($width === 3) { $v = ord($raw[0]) | (ord($raw[1]) << 8) | (ord($raw[2]) << 16); $sample = ($v >= 8388608 ? $v - 16777216 : $v) / 8388608; }
                else { $v = unpack('V', $raw)[1]; $sample = ($v >= 2147483648 ? $v - 4294967296 : $v) / 2147483648; }
                if (!is_finite($sample)) throw new \InvalidArgumentException('WAV содержит некорректные значения сигнала.');
                $sum += $sample * $sample; $n++;
            }
        }
        return $n ? sqrt($sum / $n) : 0.0;
    }

    private function ffmpegAnalysis(string $path, float $expected): array
    {
        $format = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) { 'mp3' => 'mp3', 'flac' => 'flac', 'wav' => 'wav', 'm4a' => 'mov', 'ogg', 'opus' => 'ogg', 'aac' => 'aac', 'webm' => 'matroska', default => '' };
        if ($format === '') throw new \InvalidArgumentException('Формат аудио не поддерживает автопоиск.');
        $process = @proc_open([$this->ffmpeg, '-nostdin', '-hide_banner', '-nostats', '-threads', '1', '-protocol_whitelist', 'file,pipe', '-f', $format, '-i', $path, '-map', '0:a:0', '-af', 'aresample=8000,silencedetect=noise=-38dB:d=0.8', '-f', 'null', '-'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Не удалось запустить FFmpeg.');
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + $this->timeout; $output = ''; $timedOut = false; $overflow = false; $status = null;
        try {
            do {
                $read = array_values(array_filter([$pipes[1], $pipes[2]], static fn($pipe): bool => !feof($pipe))); $write = null; $except = null;
                if ($read) @stream_select($read, $write, $except, 0, 100000);
                else usleep(100000);
                foreach ($read as $pipe) $output .= (string)stream_get_contents($pipe, 65536);
                $overflow = strlen($output) > self::MAX_OUTPUT;
                $timedOut = microtime(true) > $deadline || connection_aborted();
                $status = proc_get_status($process);
            } while ($status['running'] && !$timedOut && !$overflow);
            if ($status['running']) { proc_terminate($process); usleep(20000); if (proc_get_status($process)['running']) proc_terminate($process, 9); }
            $output .= (string)stream_get_contents($pipes[1], 65536) . (string)stream_get_contents($pipes[2], 65536);
        } finally { fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); }
        if ($timedOut) throw new \RuntimeException('Анализ превысил лимит времени сервера. Используйте CUE или ручные таймкоды.');
        if ($overflow) throw new \RuntimeException('Слишком много пауз для безопасного анализа. Используйте CUE.');
        if (($status['exitcode'] ?? $exit) !== 0) throw new \InvalidArgumentException('FFmpeg не смог прочитать аудиосигнал. Файл может быть повреждён или иметь неподдерживаемую кодировку.');
        $duration = $expected;
        if (preg_match('/Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)/', $output, $m)) $duration = (int)$m[1] * 3600 + (int)$m[2] * 60 + (float)$m[3];
        self::assertDuration($duration);
        preg_match_all('/silence_end:\s*(\d+(?:\.\d+)?)/', $output, $matches);
        return self::result(array_map('floatval', $matches[1]), $duration, 'ffmpeg-silence', self::warnings($duration, $expected), false);
    }

    private static function warnings(float $actual, float $expected): array
    {
        $warnings = ['Найдены предполагаемые переходы по паузам. Они не гарантируют границы композиций; непрерывные DJ-переходы могут не определиться. Названия и исполнителей укажите самостоятельно.'];
        if ($expected > 0 && abs($actual - $expected) > max(2, $expected * 0.01)) $warnings[] = 'Длительность сигнала отличается от сохранённой длительности записи. Проверьте таймкоды.';
        return $warnings;
    }

    private static function result(array $times, float $duration, string $method, array $warnings, bool $partial): array
    {
        $chapters = []; $previous = -self::MIN_SPACING;
        sort($times, SORT_NUMERIC);
        foreach ($times as $time) {
            if ($time < 5 || ($duration > 0 && $time >= $duration - 5) || $time - $previous < self::MIN_SPACING) continue;
            if (!$chapters) $chapters[] = ['title' => '', 'artist' => '', 'start' => 0.0];
            $chapters[] = ['title' => '', 'artist' => '', 'start' => round($time, 3)]; $previous = $time;
            if (count($chapters) >= 500) { $warnings[] = 'Предложены первые 500 глав. Добавляйте оставшиеся вручную.'; break; }
        }
        if (!$chapters) $warnings[] = 'Убедительных пауз не найдено. Добавьте главы вручную или импортируйте CUE.';
        return ['chapters' => $chapters, 'method' => $method, 'duration' => $duration, 'partial' => $partial, 'warnings' => $warnings, 'recognitionAvailable' => false];
    }

    private static function findFfmpeg(): ?string
    {
        if (!function_exists('proc_open')) return null;
        $directories = array_unique(array_merge(['/usr/local/bin', '/opt/homebrew/bin', '/usr/bin'], array_slice(explode(PATH_SEPARATOR, (string)getenv('PATH')), 0, 32)));
        foreach ($directories as $directory) {
            $path = $directory . '/ffmpeg';
            if (is_file($path) && is_executable($path)) return realpath($path) ?: null;
        }
        return null;
    }
}
