<?php

function pwaPushAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$projectRoot = dirname(__DIR__);
$layout = (string)file_get_contents($projectRoot . '/app/Views/layouts/default.php');
$controller = (string)file_get_contents($projectRoot . '/app/Controllers/PwaController.php');
$service = (string)file_get_contents($projectRoot . '/app/Services/PwaService.php');
$javascript = (string)file_get_contents($projectRoot . '/public/assets/default/js/pwa.js');
$chatController = (string)file_get_contents($projectRoot . '/app/Controllers/ChatController.php');

pwaPushAssert(
    str_contains($layout, 'data-pwa-auth-user-id="<?= (int)($currentUser[\'id\'] ?? 0) ?>"'),
    'The PWA client cannot identify the currently authenticated account.'
);
pwaPushAssert(
    str_contains($javascript, "const currentUserId = Number.parseInt(body.dataset.pwaAuthUserId || '0', 10) || 0;")
    && str_contains($javascript, "if (currentUserId <= 0) return { status: false, reason: 'auth' };")
    && str_contains($javascript, "if (currentUserId <= 0) return 'disabled';"),
    'Anonymous pages can still register or report an active push subscription.'
);
pwaPushAssert(
    str_contains($javascript, 'const syncExistingSubscription = async (subscription) =>')
    && str_contains($javascript, 'postJson(body.dataset.pwaSubscribeUrl, subscription.toJSON())')
    && str_contains($javascript, 'if (!response.ok) return false;'),
    'An existing phone subscription is not rebound to the currently authenticated user.'
);
pwaPushAssert(
    str_contains($javascript, "setPushStatusText(await syncExistingSubscription(subscription) ? 'enabled' : 'disabled');")
    && str_contains($javascript, "setPushStatusText('disabled');"),
    'Push status still confuses another device subscription with this phone.'
);
pwaPushAssert(
    str_contains($controller, "'user_id' => \$userId")
    && str_contains($controller, "'push' => \$this->pwa->pushStatusForUser(\$userId)"),
    'The service worker cannot verify the active authenticated user.'
);
pwaPushAssert(
    str_contains($service, "'statusUrl' => base_url('/api/pwa/status')")
    && str_contains($service, 'credentials: "include"')
    && str_contains($service, 'Number(status.user_id || 0) !== targetUserId'),
    'The service worker does not suppress notifications for logged-out or different accounts.'
);
pwaPushAssert(
    str_contains($service, "\$subscriptionPayload['data']['user_id'] = (int)\$subscription['user_id'];")
    && str_contains($service, '$this->sendWebPush($subscription, $subscriptionPayload)'),
    'Push payloads are not marked with their actual recipient account.'
);
pwaPushAssert(
    str_contains($service, 'syncUserPushEnabledFromSubscriptions')
    && str_contains($service, 'SELECT COUNT(*) FROM pwa_subscriptions WHERE user_id = ? AND is_active = 1 AND revoked_at IS NULL'),
    'Disabling one device can still disable push for every device belonging to the user.'
);
pwaPushAssert(
    str_contains($chatController, '$this->notifyChatRecipient($currentUserId, $contactId, $message, $attachments);')
    && str_contains($chatController, "'user_id' => \$receiverId")
    && str_contains($chatController, "'source' => 'chat'"),
    'Chat messages are not dispatched to the recipient push pipeline.'
);

echo "PWA push authentication regression checks passed.\n";
