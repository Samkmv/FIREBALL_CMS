<?php

namespace App\Services;

/**
 * Stores chat media outside the public directory using chunked authenticated encryption.
 */
class ChatMediaStorage
{
    protected const MAGIC = "FBCM01\n";
    protected const CIPHER = 'aes-256-gcm';
    protected const TAG_LENGTH = 16;
    protected const IV_LENGTH = 12;
    protected const CHUNK_SIZE = 1048576;
    protected const MAX_HEADER_LENGTH = 16384;

    protected string $storageRoot;
    protected string $masterSecret;

    public function __construct(?string $storageRoot = null, ?string $masterSecret = null)
    {
        $defaultRoot = defined('STORAGE')
            ? rtrim((string)STORAGE, '/') . '/chat-media'
            : rtrim(sys_get_temp_dir(), '/') . '/fireball-chat-media';

        $this->storageRoot = rtrim($storageRoot ?? $defaultRoot, '/');
        $this->masterSecret = (string)($masterSecret ?? (defined('CHAT_ENCRYPTION_KEY') ? CHAT_ENCRYPTION_KEY : ''));

        if ($this->storageRoot === '' || trim($this->masterSecret) === '') {
            throw new \RuntimeException('Chat media encryption is not configured.');
        }
    }

    /**
     * Encrypts a file and returns the opaque path saved in the message record.
     */
    public function store(string $sourcePath): string
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException('Chat media source file is unavailable.');
        }

        $this->ensureStorageDirectory();

        $token = bin2hex(random_bytes(24));
        $relativePath = 'chat-media/' . $token . '.fbcm';
        $targetPath = $this->absolutePath($relativePath);
        $temporaryPath = $targetPath . '.tmp';
        $source = @fopen($sourcePath, 'rb');
        $target = @fopen($temporaryPath, 'xb');

        if (!is_resource($source) || !is_resource($target)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            @unlink($temporaryPath);
            throw new \RuntimeException('Could not create protected chat media.');
        }

        try {
            $size = (int)(filesize($sourcePath) ?: 0);
            $salt = random_bytes(16);
            $header = json_encode([
                'version' => 1,
                'chunk_size' => self::CHUNK_SIZE,
                'original_size' => $size,
                'salt' => base64_encode($salt),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $this->writeAll($target, self::MAGIC);
            $this->writeAll($target, pack('N', strlen($header)));
            $this->writeAll($target, $header);

            $key = $this->deriveKey($salt);
            $headerHash = hash('sha256', $header, true);
            $chunkIndex = 0;
            $processedBytes = 0;

            while (!feof($source)) {
                $plainText = fread($source, self::CHUNK_SIZE);
                if ($plainText === false) {
                    throw new \RuntimeException('Could not read chat media source file.');
                }
                if ($plainText === '') {
                    break;
                }

                $iv = random_bytes(self::IV_LENGTH);
                $tag = '';
                $cipherText = openssl_encrypt(
                    $plainText,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    $this->buildChunkAad($headerHash, $chunkIndex),
                    self::TAG_LENGTH
                );
                if ($cipherText === false || strlen($tag) !== self::TAG_LENGTH) {
                    throw new \RuntimeException('Could not encrypt chat media.');
                }

                $this->writeAll($target, pack('N', strlen($cipherText)));
                $this->writeAll($target, $iv);
                $this->writeAll($target, $tag);
                $this->writeAll($target, $cipherText);
                $processedBytes += strlen($plainText);
                $chunkIndex++;
            }

            if ($processedBytes !== $size) {
                throw new \RuntimeException('Chat media source changed while it was being encrypted.');
            }

            fflush($target);
            fclose($source);
            fclose($target);
            $source = null;
            $target = null;

            @chmod($temporaryPath, 0600);
            if (!@rename($temporaryPath, $targetPath)) {
                throw new \RuntimeException('Could not publish protected chat media.');
            }
            @chmod($targetPath, 0600);

            return $relativePath;
        } catch (\Throwable $exception) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            @unlink($temporaryPath);
            @unlink($targetPath);
            throw $exception;
        }
    }

    /**
     * Decrypts a byte range. Intended for streaming and integrity tests.
     */
    public function readRange(string $relativePath, int $start = 0, ?int $end = null): string
    {
        $output = '';
        $this->processRange($relativePath, $start, $end, static function (string $bytes) use (&$output): void {
            $output .= $bytes;
        });

        return $output;
    }

    /**
     * Streams decrypted media, including single HTTP byte range support.
     */
    public function stream(string $relativePath, string $name, string $mimeType, int $expectedSize = 0): never
    {
        $meta = $this->readMetadata($relativePath);
        $size = (int)$meta['original_size'];
        if ($expectedSize > 0 && $size !== $expectedSize) {
            throw new \RuntimeException('Chat media metadata mismatch.');
        }

        [$start, $end, $isPartial] = $this->resolveRequestedRange($size, (string)($_SERVER['HTTP_RANGE'] ?? ''));
        $length = $size === 0 ? 0 : $end - $start + 1;

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($isPartial ? 206 : 200);
        header('Content-Type: ' . $this->normalizeMimeType($mimeType));
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $this->asciiFileName($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        if ($isPartial) {
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        if ($size > 0) {
            $this->processRange($relativePath, $start, $end, static function (string $bytes): void {
                echo $bytes;
                flush();
            });
        }
        exit;
    }

    public function delete(string $relativePath): void
    {
        if (!self::isProtectedPath($relativePath)) {
            return;
        }

        $path = $this->absolutePath($relativePath);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function isProtectedPath(string $path): bool
    {
        return preg_match('#^chat-media/[a-f0-9]{48}\.fbcm$#D', ltrim(trim($path), '/')) === 1;
    }

    /**
     * @return array{version:int,chunk_size:int,original_size:int,salt:string,header_hash:string,data_offset:int}
     */
    protected function readMetadata(string $relativePath): array
    {
        $path = $this->absolutePath($relativePath);
        $stream = @fopen($path, 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Protected chat media was not found.');
        }

        try {
            return $this->readHeader($stream);
        } finally {
            fclose($stream);
        }
    }

    protected function processRange(string $relativePath, int $start, ?int $end, callable $consumer): void
    {
        $path = $this->absolutePath($relativePath);
        $stream = @fopen($path, 'rb');
        if (!is_resource($stream)) {
            throw new \RuntimeException('Protected chat media was not found.');
        }

        try {
            $meta = $this->readHeader($stream);
            $size = (int)$meta['original_size'];
            if ($size === 0) {
                if ($start !== 0 || ($end !== null && $end !== -1)) {
                    throw new \OutOfRangeException('Invalid chat media range.');
                }
                return;
            }

            $end ??= $size - 1;
            if ($start < 0 || $end < $start || $end >= $size) {
                throw new \OutOfRangeException('Invalid chat media range.');
            }

            $key = $this->deriveKey($meta['salt']);
            $chunkSize = (int)$meta['chunk_size'];
            $firstChunk = intdiv($start, $chunkSize);
            $lastChunk = intdiv($end, $chunkSize);
            $chunkIndex = 0;

            while ($chunkIndex <= $lastChunk) {
                $cipherLengthData = $this->readExact($stream, 4);
                $cipherLength = unpack('Nlength', $cipherLengthData)['length'] ?? 0;
                if ($cipherLength <= 0 || $cipherLength > $chunkSize) {
                    throw new \RuntimeException('Protected chat media is corrupted.');
                }

                if ($chunkIndex < $firstChunk) {
                    if (fseek($stream, self::IV_LENGTH + self::TAG_LENGTH + $cipherLength, SEEK_CUR) !== 0) {
                        throw new \RuntimeException('Protected chat media is truncated.');
                    }
                    $chunkIndex++;
                    continue;
                }

                $iv = $this->readExact($stream, self::IV_LENGTH);
                $tag = $this->readExact($stream, self::TAG_LENGTH);
                $cipherText = $this->readExact($stream, $cipherLength);
                $plainText = openssl_decrypt(
                    $cipherText,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    $this->buildChunkAad($meta['header_hash'], $chunkIndex)
                );
                if ($plainText === false) {
                    throw new \RuntimeException('Protected chat media integrity check failed.');
                }

                $chunkStart = $chunkIndex * $chunkSize;
                $sliceStart = max(0, $start - $chunkStart);
                $sliceEnd = min(strlen($plainText) - 1, $end - $chunkStart);
                if ($sliceEnd >= $sliceStart) {
                    $consumer(substr($plainText, $sliceStart, $sliceEnd - $sliceStart + 1));
                }

                $chunkIndex++;
            }
        } finally {
            fclose($stream);
        }
    }

    protected function readHeader($stream): array
    {
        if ($this->readExact($stream, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new \RuntimeException('Invalid protected chat media format.');
        }

        $headerLength = unpack('Nlength', $this->readExact($stream, 4))['length'] ?? 0;
        if ($headerLength <= 0 || $headerLength > self::MAX_HEADER_LENGTH) {
            throw new \RuntimeException('Invalid protected chat media header.');
        }

        $headerJson = $this->readExact($stream, $headerLength);
        $header = json_decode($headerJson, true, 8, JSON_THROW_ON_ERROR);
        $salt = base64_decode((string)($header['salt'] ?? ''), true);
        $chunkSize = (int)($header['chunk_size'] ?? 0);
        $size = (int)($header['original_size'] ?? -1);

        if (($header['version'] ?? null) !== 1
            || $chunkSize !== self::CHUNK_SIZE
            || $size < 0
            || $salt === false
            || strlen($salt) !== 16) {
            throw new \RuntimeException('Invalid protected chat media metadata.');
        }

        return [
            'version' => 1,
            'chunk_size' => $chunkSize,
            'original_size' => $size,
            'salt' => $salt,
            'header_hash' => hash('sha256', $headerJson, true),
            'data_offset' => strlen(self::MAGIC) + 4 + $headerLength,
        ];
    }

    protected function resolveRequestedRange(int $size, string $rangeHeader): array
    {
        $rangeHeader = trim($rangeHeader);
        if ($rangeHeader === '' || $size === 0) {
            return [0, max(-1, $size - 1), false];
        }

        if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $rangeHeader, $matches)
            || ($matches[1] === '' && $matches[2] === '')) {
            $this->rangeNotSatisfiable($size);
        }

        if ($matches[1] === '') {
            $suffixLength = (int)$matches[2];
            if ($suffixLength <= 0) {
                $this->rangeNotSatisfiable($size);
            }
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int)$matches[1];
            $end = $matches[2] === '' ? $size - 1 : (int)$matches[2];
        }

        if ($start < 0 || $start >= $size || $end < $start) {
            $this->rangeNotSatisfiable($size);
        }

        return [$start, min($end, $size - 1), true];
    }

    protected function rangeNotSatisfiable(int $size): never
    {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        header('Cache-Control: private, no-store, max-age=0');
        exit;
    }

    protected function absolutePath(string $relativePath): string
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if (!self::isProtectedPath($relativePath)) {
            throw new \InvalidArgumentException('Invalid protected chat media path.');
        }

        return $this->storageRoot . '/' . basename($relativePath);
    }

    protected function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageRoot) && !@mkdir($this->storageRoot, 0700, true) && !is_dir($this->storageRoot)) {
            throw new \RuntimeException('Could not create protected chat media directory.');
        }
        @chmod($this->storageRoot, 0700);
    }

    protected function deriveKey(string $salt): string
    {
        $inputKey = hash('sha256', $this->masterSecret, true);
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $inputKey, 32, 'fireball-chat-media:v1', $salt);
        }

        return hash_hmac('sha256', 'fireball-chat-media:v1|' . bin2hex($salt), $inputKey, true);
    }

    protected function buildChunkAad(string $headerHash, int $chunkIndex): string
    {
        return $headerHash . pack('N', $chunkIndex);
    }

    protected function writeAll($stream, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('Could not write protected chat media.');
            }
            $offset += $written;
        }
    }

    protected function readExact($stream, int $length): string
    {
        $output = '';
        while (strlen($output) < $length) {
            $part = fread($stream, $length - strlen($output));
            if ($part === false || $part === '') {
                throw new \RuntimeException('Protected chat media is truncated.');
            }
            $output .= $part;
        }

        return $output;
    }

    protected function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        return preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#D', $mimeType) === 1
            ? $mimeType
            : 'application/octet-stream';
    }

    protected function asciiFileName(string $name): string
    {
        $name = str_replace(["\r", "\n", '"', '\\'], '_', trim($name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';
        return mb_substr($name, 0, 150);
    }
}
