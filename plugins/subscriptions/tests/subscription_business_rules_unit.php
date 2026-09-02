<?php

declare(strict_types=1);

$businessDb = null;

final class FireballPluginSubscriptions
{
    public static function t(string $key): string
    {
        return [
            'subscriptions_address_included_in_utilities' => 'ADDRESS_INCLUDED',
            'subscriptions_subscriber_delete_active_error' => 'ACTIVE_SUBSCRIPTION',
            'subscriptions_error_subscription_not_found' => 'NOT_FOUND',
        ][$key] ?? $key;
    }
}

function db(): SubscriptionBusinessDb
{
    global $businessDb;

    return $businessDb;
}

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    return $default;
}

function base_url(string $path = ''): string
{
    return 'https://example.test' . $path;
}

function log_error_details(string $message, array $context = [], ?Throwable $exception = null): void
{
}

final class SubscriptionBusinessResult
{
    public function __construct(
        private readonly mixed $one = null,
        private readonly array $rows = [],
        private readonly mixed $column = null,
        private readonly int $affected = 0
    ) {
    }

    public function getOne(): mixed
    {
        return $this->one;
    }

    public function get(): array
    {
        return $this->rows;
    }

    public function getColumn(): mixed
    {
        return $this->column;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

final class SubscriptionBusinessDb
{
    public string $mode = 'excluded_checkout';
    public array $queries = [];
    public array $currentPlan = [];
    public array $insertedSubscription = [];
    private bool $transaction = false;
    private int $insertId = 800;

    public function query(string $sql, array $params = []): SubscriptionBusinessResult
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];

        if ($this->mode === 'excluded_checkout') {
            if (str_starts_with($sql, 'SELECT * FROM subscription_profiles WHERE user_id')) {
                return new SubscriptionBusinessResult([
                    'id' => 11,
                    'user_id' => 41,
                    'street' => 'ул. Октябрьская',
                    'house' => '10',
                    'apartment' => '',
                ]);
            }
            if (str_starts_with($sql, 'SELECT field_id, field_value FROM subscription_profile_values')) {
                return new SubscriptionBusinessResult(rows: []);
            }
            if (str_starts_with($sql, 'SELECT * FROM subscription_address_exclusions WHERE is_active')) {
                return new SubscriptionBusinessResult(rows: [[
                    'id' => 7,
                    'is_active' => 1,
                    'street_type' => null,
                    'normalized_street' => 'октябрьская',
                    'normalized_house' => null,
                    'normalized_apartment' => null,
                ]]);
            }

            return new SubscriptionBusinessResult();
        }

        if ($this->mode === 'archive_active') {
            if (str_contains($sql, "status IN ('active', 'cancelled')")) {
                return new SubscriptionBusinessResult(['id' => 99]);
            }

            return new SubscriptionBusinessResult();
        }

        if ($this->mode === 'archive_inactive') {
            if (str_contains($sql, "status IN ('active', 'cancelled')")) {
                return new SubscriptionBusinessResult();
            }
            if (str_starts_with($sql, 'SELECT id FROM subscriptions WHERE user_id')) {
                return new SubscriptionBusinessResult(rows: [['id' => 21], ['id' => 22]]);
            }

            return new SubscriptionBusinessResult();
        }

        if ($this->mode === 'activate') {
            if (str_starts_with($sql, 'SELECT * FROM subscription_plans WHERE id')) {
                return new SubscriptionBusinessResult($this->currentPlan);
            }
            if (str_contains($sql, 'WHERE user_id = ?') && str_contains($sql, 'FOR UPDATE')) {
                return new SubscriptionBusinessResult();
            }
            if (str_starts_with($sql, 'INSERT INTO subscriptions ')) {
                $this->insertId++;
                $this->insertedSubscription = [
                    'id' => $this->insertId,
                    'user_id' => (int)$params[0],
                    'plan_id' => (int)$params[1],
                    'status' => (string)$params[2],
                    'auto_renew' => (int)$params[5],
                ];

                return new SubscriptionBusinessResult();
            }
            if (str_starts_with($sql, 'SELECT * FROM subscriptions WHERE id = ?')) {
                return new SubscriptionBusinessResult($this->insertedSubscription);
            }

            return new SubscriptionBusinessResult();
        }

        return new SubscriptionBusinessResult();
    }

    public function beginTransaction(): void
    {
        $this->transaction = true;
    }

    public function commit(): void
    {
        $this->transaction = false;
    }

    public function rollBack(): void
    {
        $this->transaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function getInsertId(): int
    {
        return $this->insertId;
    }
}

require_once __DIR__ . '/../src/Support/Money.php';
require_once __DIR__ . '/../src/Support/AddressNormalizer.php';
require_once __DIR__ . '/../src/Support/AddressMatcher.php';
require_once __DIR__ . '/../src/Payments/PaymentGatewayInterface.php';
require_once __DIR__ . '/../src/Repositories/ProfileRepository.php';
require_once __DIR__ . '/../src/Repositories/PlanRepository.php';
require_once __DIR__ . '/../src/Repositories/AddressExclusionRepository.php';
require_once __DIR__ . '/../src/Services/AccessService.php';
require_once __DIR__ . '/../src/Services/SubscriptionService.php';
require_once __DIR__ . '/../src/Services/SubscriptionEligibilityService.php';
require_once __DIR__ . '/../src/Services/CheckoutService.php';

use Fireball\Subscriptions\Payments\PaymentGatewayInterface;
use Fireball\Subscriptions\Services\CheckoutService;
use Fireball\Subscriptions\Services\SubscriptionService;

final class SubscriptionProviderSpy implements PaymentGatewayInterface
{
    public int $checkoutCalls = 0;

    public function checkoutUrl(array $order, array $plan, array $profile): string
    {
        $this->checkoutCalls++;

        return 'https://provider.test/pay';
    }

    public function verifyResult(array $payload): bool
    {
        return true;
    }

    public function verifySuccess(array $payload): bool
    {
        return true;
    }

    public function expectedResultResponse(int|string $invoiceId): string
    {
        return 'OK' . $invoiceId;
    }
}

function businessAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hasSql(SubscriptionBusinessDb $database, string $needle): bool
{
    foreach ($database->queries as $query) {
        if (str_contains((string)$query['sql'], $needle)) {
            return true;
        }
    }

    return false;
}

$businessDb = new SubscriptionBusinessDb();
$provider = new SubscriptionProviderSpy();
$blocked = false;
try {
    (new CheckoutService(null, $provider))->create(41, 3, ['offer' => true, 'privacy' => true]);
} catch (DomainException $exception) {
    $blocked = $exception->getMessage() === 'ADDRESS_INCLUDED';
}
businessAssert($blocked, 'An excluded address must be rejected by the shared backend checkout service.');
businessAssert($provider->checkoutCalls === 0, 'The payment provider must not be called for an excluded address.');
businessAssert(!hasSql($businessDb, 'INSERT INTO subscription_orders'), 'No order may be created for an excluded address.');
businessAssert(!hasSql($businessDb, 'INSERT INTO subscription_payments'), 'No payment row may be created for an excluded address.');

$businessDb = new SubscriptionBusinessDb();
$businessDb->mode = 'archive_active';
$activeBlocked = false;
try {
    (new SubscriptionService())->archiveInactiveSubscriber(41, 1);
} catch (DomainException $exception) {
    $activeBlocked = $exception->getMessage() === 'ACTIVE_SUBSCRIPTION';
}
businessAssert($activeBlocked, 'A subscriber with another active subscription must not be removed.');
businessAssert(!hasSql($businessDb, 'SET archived_at ='), 'Blocked deletion must not archive subscriptions.');

$businessDb = new SubscriptionBusinessDb();
$businessDb->mode = 'archive_inactive';
$archived = (new SubscriptionService())->archiveInactiveSubscriber(41, 1);
businessAssert($archived === 2, 'All inactive subscription rows for the subscriber must be archived.');
businessAssert(hasSql($businessDb, 'SET archived_at ='), 'Inactive subscriber deletion must use safe archiving.');
businessAssert(!hasSql($businessDb, 'DELETE FROM subscription_payments'), 'Subscriber removal must preserve payment history.');
businessAssert(!hasSql($businessDb, 'DELETE FROM subscription_orders'), 'Subscriber removal must preserve order history.');

$activationOrder = static function (bool $snapshotAutoRenew): array {
    return [
        'id' => 501,
        'user_id' => 41,
        'plan_id' => 3,
        'amount_minor' => 99000,
        'currency' => 'RUB',
        'plan_snapshot' => json_encode([
            'id' => 3,
            'price_minor' => 99000,
            'currency' => 'RUB',
            'duration_unit' => 'months',
            'duration_value' => 1,
            'auto_renew_enabled' => $snapshotAutoRenew,
        ], JSON_THROW_ON_ERROR),
        'consent_snapshot' => json_encode([
            'recurring' => true,
            'auto_renew' => true,
        ], JSON_THROW_ON_ERROR),
    ];
};

$businessDb = new SubscriptionBusinessDb();
$businessDb->mode = 'activate';
$businessDb->currentPlan = ['id' => 3, 'auto_renew_enabled' => 0];
$autoSubscription = (new SubscriptionService())->activatePaidOrder($activationOrder(true), 601);
businessAssert((int)$autoSubscription['auto_renew'] === 1, 'An auto-renew plan snapshot must create an auto-renewing subscription.');

$businessDb = new SubscriptionBusinessDb();
$businessDb->mode = 'activate';
$businessDb->currentPlan = ['id' => 3, 'auto_renew_enabled' => 1];
$manualSubscription = (new SubscriptionService())->activatePaidOrder($activationOrder(false), 602);
businessAssert((int)$manualSubscription['auto_renew'] === 0, 'A non-recurring plan snapshot must create a non-recurring subscription.');
businessAssert(
    (int)$manualSubscription['auto_renew'] === 0 && !empty($businessDb->currentPlan['auto_renew_enabled']),
    'Changing the plan setting after checkout must not change the saved subscription snapshot behavior.'
);

echo "Subscription business rule unit tests passed.\n";
