<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Technical labels from a bounded header, never from decoding an entire song. */
final class AudioQuality
{
    public const HEADER_BYTES = 262144;

    public static function format(string $filename, string $mime = ''): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($extension) {
            'mp3' => 'MP3', 'flac' => 'FLAC', 'wav' => 'WAV', 'm4a' => 'M4A',
            'ogg' => 'OGG', 'opus' => 'OPUS', 'aac' => 'AAC', 'webm' => 'WEBM',
            default => match (strtolower(trim(explode(';', $mime, 2)[0]))) {
                'audio/mpeg', 'audio/mp3' => 'MP3', 'audio/flac', 'audio/x-flac', 'application/x-flac' => 'FLAC',
                'audio/wav', 'audio/x-wav', 'audio/vnd.wave' => 'WAV', 'audio/mp4', 'audio/x-m4a' => 'M4A',
                'audio/ogg', 'application/ogg' => 'OGG', 'audio/aac', 'audio/x-aac' => 'AAC',
                'audio/webm', 'video/webm' => 'WEBM', default => '',
            },
        };
    }

    public static function local(string $path, string $filename, string $mime = '', int $size = 0, float $duration = 0): array
    {
        $stream = @fopen($path, 'rb');
        if (!$stream) return self::describe('', $filename, $mime, $size, $duration);
        try { $header = fread($stream, self::HEADER_BYTES); }
        finally { fclose($stream); }
        return self::describe(is_string($header) ? $header : '', $filename, $mime, $size, $duration);
    }

    public static function describe(string $header, string $filename, string $mime = '', int $size = 0, float $duration = 0): array
    {
        $header = substr($header, 0, self::HEADER_BYTES);
        $info = ['format' => self::format($filename, $mime)];
        if (str_starts_with($header, 'fLaC')) $info = [...$info, ...self::flac($header)];
        elseif (strlen($header) >= 12 && in_array(substr($header, 0, 4), ['RIFF', 'RF64'], true) && substr($header, 8, 4) === 'WAVE') $info = [...$info, ...self::wave($header)];
        elseif (($info['format'] ?? '') === 'MP3') $info = [...$info, ...self::mp3($header)];
        // File size includes tags/container overhead, so an average is explicitly approximate.
        $seconds = (float)($info['duration'] ?? $duration);
        if (!isset($info['bitrate']) && $size > 0 && is_finite($seconds) && $seconds > 0) {
            $average = $size * 8 / $seconds;
            if (is_finite($average) && $average > 0 && $average <= 1e9) {
                $info['bitrate'] = (int)round($average); $info['bitrateKind'] = 'average';
            }
        }
        return $info;
    }

    private static function flac(string $header): array
    {
        // STREAMINFO layout: https://www.rfc-editor.org/rfc/rfc9639.html#section-8.2
        // STREAMINFO is required to be the first block and exactly 34 bytes.
        if (strlen($header) < 42 || (ord($header[4]) & 127) !== 0 || substr($header, 5, 3) !== "\0\0\x22") return [];
        $block = substr($header, 8, 34);
        $rate = (ord($block[10]) << 12) | (ord($block[11]) << 4) | (ord($block[12]) >> 4);
        $channels = ((ord($block[12]) >> 1) & 7) + 1;
        $bits = (((ord($block[12]) & 1) << 4) | (ord($block[13]) >> 4)) + 1;
        $samples = ((ord($block[13]) & 15) << 32) | unpack('N', substr($block, 14, 4))[1];
        if ($rate < 1 || $rate > 1048575 || $bits < 4 || $bits > 32) return [];
        $info = ['format' => 'FLAC', 'sampleRate' => $rate, 'bitDepth' => $bits, 'channels' => $channels, 'lossless' => true];
        if ($samples > 0) $info['duration'] = $samples / $rate;
        return $info;
    }

    private static function wave(string $header): array
    {
        // Valid bits vs container size: https://learn.microsoft.com/en-us/windows/win32/api/mmreg/ns-mmreg-waveformatextensible
        $length = strlen($header);
        for ($offset = 12, $chunks = 0; $offset + 8 <= $length && $chunks < 128; $chunks++) {
            $name = substr($header, $offset, 4); $size = unpack('V', substr($header, $offset + 4, 4))[1];
            $offset += 8;
            if ($name === 'fmt ') {
                if ($size < 16 || $offset + min($size, 40) > $length) return [];
                $fmt = unpack('vencoding/vchannels/Vrate/Vbytes/vblock/vbits', substr($header, $offset, 16));
                if ($fmt['channels'] < 1 || $fmt['channels'] > 64 || $fmt['rate'] < 1 || $fmt['rate'] > 768000) return [];
                $encoding = $fmt['encoding']; $bits = $fmt['bits'];
                if ($encoding === 65534 && $size >= 40) {
                    $extension = unpack('vlength/vvalidBits', substr($header, $offset + 16, 4));
                    $guid = substr($header, $offset + 24, 16);
                    if ($extension['length'] >= 22 && substr($guid, 2) === "\0\0\0\0\x10\0\x80\0\0\xaa\0\x38\x9b\x71") {
                        $encoding = unpack('v', $guid)[1];
                        if ($extension['validBits'] > 0 && $extension['validBits'] <= $bits) $bits = $extension['validBits'];
                    }
                }
                $info = ['format' => 'WAV', 'sampleRate' => $fmt['rate'], 'channels' => $fmt['channels']];
                if (in_array($encoding, [1, 3], true) && $bits > 0 && $bits <= 64 && $fmt['block'] === $fmt['channels'] * (int)ceil($fmt['bits'] / 8) && $fmt['bytes'] === $fmt['rate'] * $fmt['block']) {
                    $info += ['bitDepth' => $bits, 'lossless' => true, 'bitrate' => $fmt['bytes'] * 8, 'bitrateKind' => 'exact'];
                }
                return $info;
            }
            if ($size > $length - $offset) return [];
            $offset += $size + ($size & 1);
        }
        return [];
    }

    private static function mp3(string $header): array
    {
        $offset = 0; $length = strlen($header);
        if ($length >= 10 && substr($header, 0, 3) === 'ID3') {
            $tagSize = 0;
            for ($index = 6; $index < 10; $index++) { $byte = ord($header[$index]); if ($byte > 127) return []; $tagSize = ($tagSize << 7) | $byte; }
            $offset = 10 + $tagSize + ((ord($header[5]) & 16) ? 10 : 0);
        }
        // Verify the next frame before trusting a sync word inside arbitrary tags.
        for ($scanned = 0; $offset + 4 <= $length && $scanned < 65536; $offset++, $scanned++) {
            $frame = self::mpegFrame(substr($header, $offset, 4));
            if (!$frame || $offset + $frame['length'] + 4 > $length) continue;
            $next = self::mpegFrame(substr($header, $offset + $frame['length'], 4));
            if (!$next || $next['version'] !== $frame['version'] || $next['layer'] !== $frame['layer'] || $next['rate'] !== $frame['rate']) continue;
            // One or two equal frame bitrates cannot establish CBR over the file.
            return ['format' => 'MP3', 'sampleRate' => $frame['rate'], 'channels' => $frame['channels'], 'frameBitrate' => $frame['bitrate']];
        }
        return [];
    }

    private static function mpegFrame(string $header): ?array
    {
        if (strlen($header) < 4) return null;
        $bytes = array_map('ord', str_split($header));
        if ($bytes[0] !== 255 || ($bytes[1] & 224) !== 224) return null;
        $version = ($bytes[1] >> 3) & 3; $layerBits = ($bytes[1] >> 1) & 3;
        $index = ($bytes[2] >> 4) & 15; $rateIndex = ($bytes[2] >> 2) & 3;
        if ($version === 1 || !$layerBits || !$index || $index === 15 || $rateIndex === 3) return null;
        $layer = 4 - $layerBits;
        $rates = [44100, 48000, 32000]; $rate = (int)($rates[$rateIndex] / ($version === 3 ? 1 : ($version === 2 ? 2 : 4)));
        $tables = $version === 3 ? [
            1 => [0,32,64,96,128,160,192,224,256,288,320,352,384,416,448],
            2 => [0,32,48,56,64,80,96,112,128,160,192,224,256,320,384],
            3 => [0,32,40,48,56,64,80,96,112,128,160,192,224,256,320],
        ] : [1 => [0,32,48,56,64,80,96,112,128,144,160,176,192,224,256], 2 => [0,8,16,24,32,40,48,56,64,80,96,112,128,144,160], 3 => [0,8,16,24,32,40,48,56,64,80,96,112,128,144,160]];
        $bitrate = $tables[$layer][$index] * 1000; $padding = ($bytes[2] >> 1) & 1;
        $length = $layer === 1 ? ((int)floor(12 * $bitrate / $rate) + $padding) * 4 : (int)floor(($layer === 3 && $version !== 3 ? 72 : 144) * $bitrate / $rate) + $padding;
        return ['version' => $version, 'layer' => $layer, 'rate' => $rate, 'bitrate' => $bitrate, 'length' => $length, 'channels' => (($bytes[3] >> 6) & 3) === 3 ? 1 : 2];
    }
}
