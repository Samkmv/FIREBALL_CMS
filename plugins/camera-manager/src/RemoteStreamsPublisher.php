<?php

namespace Fireball\CameraManager;

final class RemoteStreamsPublisher
{
    public function __construct(
        private readonly SshCameraTransport $transport,
        private readonly StreamsFilePublisher $streamsPublisher = new StreamsFilePublisher()
    ) {
    }

    /** @return array{stream_count:int, backup_path:string, syntax_output:string, restarted:bool, restart_output:string} */
    public function publish(array $streams, bool $restart): array
    {
        $source = $this->transport->readStreamsFile();
        $block = $this->streamsPublisher->renderManagedBlock($streams);
        $updated = $this->streamsPublisher->merge($source, $block, $streams);
        $result = $this->transport->publish($updated, $restart);

        return [
            'stream_count' => count($streams),
            'backup_path' => $result['backup_path'],
            'syntax_output' => 'Checked on the remote RTSP server.',
            'restarted' => $result['restarted'],
            'restart_output' => $result['message'],
        ];
    }
}
