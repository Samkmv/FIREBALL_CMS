<?php

namespace Fireball\Subscriptions\Payments;

interface PaymentGatewayInterface
{
    public function checkoutUrl(array $order, array $plan, array $profile): string;

    public function verifyResult(array $payload): bool;

    public function verifySuccess(array $payload): bool;

    public function expectedResultResponse(int|string $invoiceId): string;
}
