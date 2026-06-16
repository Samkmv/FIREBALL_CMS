<?php

namespace App\Controllers;

final class StreamController extends BaseController
{
    public function wake(): void
    {
        $rawStreamId = request()->post('stream_id', '');
        $streamId = is_scalar($rawStreamId) ? trim((string)$rawStreamId) : '';

        if ($streamId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $streamId)) {
            response()->json([
                'success' => false,
                'stream_id' => $streamId,
                'woke' => false,
            ]);
        }

        response()->json([
            'success' => true,
            'stream_id' => $streamId,
            'woke' => $this->wakeStream($streamId),
        ]);
    }

    private function wakeStream(string $streamId): bool
    {
        // TODO: Connect stream wake-up through a whitelist/config mapping.
        return true;
    }
}
