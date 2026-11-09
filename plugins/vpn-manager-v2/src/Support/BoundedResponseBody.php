<?php

namespace Fireball\VpnManagerV2\Support;

final class BoundedResponseBody
{
    public string $body = '';
    public bool $exceeded = false;

    public function __construct(private readonly int $limit)
    {
    }

    public function append(mixed $handle, string $chunk): int
    {
        $size = strlen($chunk);
        if ($size > $this->limit - strlen($this->body)) {
            $this->exceeded = true;

            return 0;
        }
        $this->body .= $chunk;

        return $size;
    }
}
