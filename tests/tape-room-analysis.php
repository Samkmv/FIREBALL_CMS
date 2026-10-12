<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/plugins/reel-player/src/Services/ChapterAnalysis.php';
require_once dirname(__DIR__) . '/plugins/reel-player/src/Services/ChapterRecognitionProvider.php';

use Fireball\ReelPlayer\Services\ChapterAnalysis;

$assertions = 0;
function check(bool $condition, string $message): void { global $assertions; $assertions++; if (!$condition) throw new RuntimeException($message); }
function fails(callable $action, string $message): void { $failed = false; try { $action(); } catch (InvalidArgumentException|RuntimeException $error) { $failed = true; } check($failed, $message); }
function headerWave(float $seconds, int $rate = 8000, int $channels = 1, int $bits = 16, int $format = 1): string {
    $align = $channels * intdiv($bits, 8); $length = (int)($seconds * $rate * $align);
    return 'RIFF' . pack('V', 36 + $length) . 'WAVEfmt ' . pack('VvvVVvv', 16, $format, $channels, $rate, $rate * $align, $align, $bits) . 'data' . pack('V', $length);
}
function makeWave(string $path, float $seconds, callable $signal, int $channels = 1, int $bits = 16, int $format = 1, int $rate = 8000): void {
    $stream = fopen($path, 'wb'); fwrite($stream, headerWave($seconds, $rate, $channels, $bits, $format));
    $buffer = '';
    for ($frame = 0; $frame < (int)($seconds * $rate); $frame++) {
        for ($channel = 0; $channel < $channels; $channel++) {
            $sample = $signal($frame / $rate, $channel);
            if ($format === 3) $bytes = pack($bits === 32 ? 'g' : 'e', $sample);
            elseif ($bits === 8) $bytes = chr((int)round(128 + $sample * 127));
            elseif ($bits === 16) $bytes = pack('v', ((int)round($sample * 32767)) & 65535);
            elseif ($bits === 24) { $value = ((int)round($sample * 8388607)) & 16777215; $bytes = chr($value & 255) . chr(($value >> 8) & 255) . chr(($value >> 16) & 255); }
            else $bytes = pack('V', ((int)round($sample * 2147483647)) & 4294967295);
            $buffer .= $bytes;
        }
        if (strlen($buffer) > 65536) { fwrite($stream, $buffer); $buffer = ''; }
    }
    fwrite($stream, $buffer); fclose($stream);
}

$base = __DIR__ . '/.tmp'; if (!is_dir($base)) mkdir($base, 0700, true);
$root = $base . '/tape-room-analysis-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
try {
    $analysis = new ChapterAnalysis('/nonexistent/ffmpeg');
    $mix = $root . '/mix.wav';
    makeWave($mix, 50, static fn(float $time): float => ($time < 1 || ($time >= 19 && $time < 20.5) || ($time >= 39 && $time < 40.5) || $time >= 48) ? 0.0 : 0.2 * sin(2 * M_PI * 223 * $time));
    $result = $analysis->analyze($mix, 50);
    check($result['method'] === 'wav-silence' && !$result['partial'], 'PCM signal is analyzed without FFmpeg or a full recording copy');
    check(array_column($result['chapters'], 'start') === [0.0,20.5,40.5], 'Real signal pauses detect track beginnings, excluding leading/trailing silence');
    check(array_column($result['chapters'], 'title') === ['', '', ''], 'Detector does not invent song titles');
    check(!$result['recognitionAvailable'] && count($result['warnings']) >= 1, 'Results explicitly state uncertainty and unavailable recognition');
    check(count($analysis->analyze($mix, 7200)['warnings']) >= 2, 'A duration mismatch is reported');

    $signal = $root . '/signal.wav';
    makeWave($signal, 5, static fn(float $time): float => 0.2 * sin(2 * M_PI * 223 * $time));
    check($analysis->analyze($signal)['chapters'] === [], 'Continuous audio without pauses does not create fake chapters');
    foreach ([8000,44100,48000] as $sampleRate) {
        $alias = $root . '/tone-' . $sampleRate . '.wav';
        makeWave($alias, 35, static fn(float $time): float => 0.3 * sin(2 * M_PI * ($time < 20 ? 1000 : 223) * $time), 1, 16, 1, $sampleRate);
        check($analysis->analyze($alias)['chapters'] === [], "A one-kHz tone at $sampleRate Hz is not mistaken for silence through fixed-stride aliasing");
    }
    $silent = $root . '/silent.wav'; makeWave($silent, 5, static fn(): float => 0.0);
    check($analysis->analyze($silent)['chapters'] === [], 'An entirely silent recording has no guessed composition boundaries');
    $stereo = $root . '/stereo.wav'; makeWave($stereo, 5, static fn(float $time, int $channel): float => $channel ? 0.2 * sin(2 * M_PI * 223 * $time) : 0.0, 2);
    check($analysis->analyze($stereo)['chapters'] === [], 'A silent left channel does not hide the audible right channel');
    foreach ([[8,1],[24,1],[32,1],[32,3],[64,3]] as [$bits,$format]) {
        $file = $root . '/' . $bits . '-' . $format . '.wav';
        makeWave($file, 2, static fn(float $time): float => 0.2 * sin(2 * M_PI * 223 * $time), 1, $bits, $format);
        check($analysis->analyze($file)['chapters'] === [], "PCM/float $bits-bit WAV is supported");
    }

    $bytes = file_get_contents($mix); $requests = [];
    $ranged = $analysis->analyzeRanges(static function(int $offset, int $length) use ($bytes, &$requests): string { $requests[] = [$offset,$length]; return substr($bytes, $offset, $length); }, strlen($bytes), 50);
    check($ranged['chapters'] === $result['chapters'] && !$ranged['partial'], 'Authorized range reader produces the same full-signal suggestions');
    check(count($requests) < 5 && $ranged['bytesAnalyzed'] < 2 * 1048576, 'Cloud analysis caches one bounded block instead of issuing one request per RMS window');

    // Virtual two-hour WAV: exercise the real long-duration scan without allocating
    // an entire file. Range data are generated in at most one-MiB blocks.
    $longHeader = headerWave(7200); $longSize = 44 + 7200 * 8000 * 2; $longRequests = 0; $longBytes = 0;
    $long = $analysis->analyzeRanges(static function(int $offset,int $length) use ($longHeader,&$longRequests,&$longBytes): string {
        $longRequests++; $longBytes += $length; $data = str_repeat("\x99\x19", (int)ceil($length / 2));
        if ($offset < 44) $data = substr_replace($data, substr($longHeader, $offset, min(44 - $offset, $length)), 0, min(44 - $offset, $length));
        return substr($data, 0, $length);
    }, $longSize, 7200);
    check($long['duration'] === 7200.0 && !$long['partial'] && $long['chapters'] === [], 'A two-hour source is scanned without fabricated chapter boundaries');
    check($longRequests <= ChapterAnalysis::RANGE_REQUESTS && $longBytes <= ChapterAnalysis::RANGE_BYTES, 'Two-hour scan respects cloud request and byte budgets');

    $largeHeader = headerWave(18000); $largeSize = 44 + 18000 * 8000 * 2; $largeRequests = 0;
    $sampled = $analysis->analyzeRanges(static function(int $offset,int $length) use ($largeHeader,&$largeRequests): string {
        $largeRequests++; $data = str_repeat("\x99\x19", (int)ceil($length / 2));
        if ($offset < 44) $data = substr_replace($data, substr($largeHeader,$offset,min(44-$offset,$length)), 0,min(44-$offset,$length));
        return substr($data,0,$length);
    }, $largeSize,18000);
    check($sampled['partial'] && $sampled['method'] === 'wav-sampled-silence' && $largeRequests <= 128, 'Large remote audio uses a bounded coarse scan and identifies it as partial');
    check(str_contains(implode(' ', $sampled['warnings']), 'приблизительны'), 'Partial scan warns that positions and coverage need review');

    fails(static fn() => $analysis->analyzeRanges(static fn(int $offset,int $length): string => '', strlen($bytes)), 'Incomplete Drive ranges are rejected');
    $broken = $root . '/broken.wav'; file_put_contents($broken, substr($bytes,0,100));
    fails(static fn() => $analysis->analyze($broken), 'Truncated WAV is rejected');
    fails(static fn() => $analysis->analyze($mix, INF), 'Non-finite duration is rejected');
    $unsupported = $root . '/not-audio.mp3'; file_put_contents($unsupported, 'not audio');
    fails(static fn() => (new ChapterAnalysis())->analyze($unsupported), 'Unsupported/corrupt compressed audio returns a clear error');
    fails(static fn() => $analysis->analyzeRanges(static fn(int $offset,int $length): string => str_repeat('x', $length), 100), 'Compressed Drive audio is not mislabeled as a successfully analyzed WAV');

    // Check the optional subprocess contract using a deterministic fake binary;
    // this is not a substitute for real FFmpeg validation on an enabled server.
    if (function_exists('proc_open')) {
        $binary = $root . '/fake-ffmpeg';
        file_put_contents($binary, "#!/bin/sh\nprintf '%s\\n' 'Duration: 00:00:50.00' 'silence_end: 20.5 | silence_duration: 1.5' 'silence_end: 40.5 | silence_duration: 1.5' >&2\nexit 0\n"); chmod($binary,0700);
        $external = (new ChapterAnalysis($binary))->analyze($unsupported,50);
        check(array_column($external['chapters'],'start') === [0.0,20.5,40.5] && $external['method'] === 'ffmpeg-silence', 'Optional process parses real silencedetect-shaped output and preserves blank titles');
        file_put_contents($binary,"#!/bin/sh\nexit 1\n");
        fails(static fn() => (new ChapterAnalysis($binary))->analyze($unsupported,50), 'Optional decoder failure cannot produce a successful preview');
        file_put_contents($binary,"#!/bin/sh\nexec sleep 3\n");
        $started = microtime(true);
        fails(static fn() => (new ChapterAnalysis($binary,1))->analyze($unsupported,50), 'Optional decoder timeout aborts safely');
        check(microtime(true)-$started < 2.5,'Timed-out process is terminated rather than left running');
    }
    echo "Tape Room chapter analysis: $assertions checks passed.\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $path) if (is_file($path)) unlink($path);
    rmdir($root);
}
