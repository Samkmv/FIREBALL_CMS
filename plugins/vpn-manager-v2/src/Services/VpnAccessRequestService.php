<?php

namespace Fireball\VpnManagerV2\Services;

use App\Services\MailService;
use App\Services\NotificationService;
use Fireball\VpnManagerV2\Repositories\VpnAccessRequestRepository;

final class VpnAccessRequestService
{
    public function __construct(
        private readonly ?VpnAccessRequestRepository $repository = null,
        private readonly ?\Closure $notificationDispatcher = null,
        private readonly ?\Closure $mailDispatcher = null,
    ) {
    }

    public function requestForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid VPN access request user.');
        }

        $repository = $this->repository ?? new VpnAccessRequestRepository();
        $result = $repository->createOrFindPending($userId);
        if (empty($result['created'])) {
            return $result;
        }

        $request = (array)$result['request'];
        $user = (array)$result['user'];
        $requestId = (int)($request['id'] ?? 0);
        $userName = trim((string)($user['name'] ?? '')) ?: trim((string)($user['login'] ?? ''));
        $message = sprintf(
            \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_admin_message'),
            $userName,
            $userId,
            trim((string)($user['email'] ?? ''))
        );
        $actionUrl = '/admin/plugins/vpn-manager-v2/subscriptions/create?'
            . http_build_query(['user_id' => $userId, 'request_id' => $requestId]);
        $payload = [
            'title' => \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_admin_title'),
            'message' => $message,
            'type' => 'vpn_v2_access_request',
            'action_url' => $actionUrl,
            'icon' => 'ci-server',
            'source' => \FireballPluginVpnManagerV2::SLUG,
            'priority' => 'high',
            'metadata' => ['request_id' => $requestId, 'user_id' => $userId],
            'store_unread' => true,
        ];

        try {
            if ($this->notificationDispatcher !== null) {
                ($this->notificationDispatcher)($payload);
            } else {
                NotificationService::createForAdmins($payload);
            }
        } catch (\Throwable $exception) {
            log_error_details('VPN access request administrator notification failed', [
                'Request' => $requestId,
                'User' => $userId,
            ], $exception);
        }

        try {
            $recipients = array_values(array_unique(array_filter(array_map(
                static fn(array $admin): string => trim((string)($admin['email'] ?? '')),
                $repository->adminRecipients()
            ), static fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
            if ($recipients !== []) {
                $subject = \FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_email_subject');
                $html = '<p>' . htmlSC($message) . '</p><p><a href="'
                    . htmlSC(base_href($actionUrl)) . '">'
                    . htmlSC(\FireballPluginVpnManagerV2::t('vpn_manager_v2_access_request_email_action'))
                    . '</a></p>';
                if ($this->mailDispatcher !== null) {
                    ($this->mailDispatcher)($recipients, $subject, $html, $message);
                } else {
                    $mail = new MailService();
                    if ($mail->isEnabled()) {
                        foreach ($recipients as $recipient) {
                            $mail->send([$recipient], $subject, $html, $message);
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            log_error_details('VPN access request administrator email failed', [
                'Request' => $requestId,
                'User' => $userId,
            ], $exception);
        }

        return $result;
    }
}
