<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/AddressNormalizer.php';
require_once __DIR__ . '/../src/Support/AddressMatcher.php';

use Fireball\Subscriptions\Support\AddressMatcher;
use Fireball\Subscriptions\Support\AddressNormalizer;

function addressAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$normalizer = new AddressNormalizer();
$matcher = new AddressMatcher();

$rule = ['id' => 1, 'is_active' => 1, ...$normalizer->normalizeRule('Октябрьская')];
addressAssert(
    $matcher->match($normalizer->normalizeRule('ул. Октябрьская, д. 10'), [$rule]) !== null,
    'A whole-street rule must match a house on that street.'
);
addressAssert(
    $matcher->match($normalizer->normalizeRule('УЛИЦА ОКТЯБРЬСКАЯ 10'), [$rule]) !== null,
    'Address matching must be case-insensitive.'
);
addressAssert(
    $matcher->match($normalizer->normalizeRule('  улица   Октябрьская,   10  '), [$rule]) !== null,
    'Repeated spaces and punctuation must not affect matching.'
);

$yoRule = ['id' => 2, 'is_active' => 1, ...$normalizer->normalizeRule('Ёлочная')];
addressAssert(
    $matcher->match($normalizer->normalizeRule('ул. Елочная 8'), [$yoRule]) !== null,
    'Russian yo/e variants must normalize to the same street.'
);

$fullRule = ['id' => 3, 'is_active' => 1, ...$normalizer->normalizeRule('ул. Калинина, д. 12')];
addressAssert(
    $matcher->match($normalizer->normalizeRule('Улица Калинина 12'), [$fullRule]) !== null,
    'Street abbreviations must not affect full-address matching.'
);
addressAssert(
    $matcher->match($normalizer->normalizeRule('Калинина 13'), [$fullRule]) === null,
    'A full-address rule must not match another house.'
);
addressAssert(
    $matcher->match($normalizer->normalizeRule('Малая Калинина 12'), [$fullRule]) === null,
    'Structured comparison must not produce substring false positives.'
);

$inactiveRule = ['id' => 4, 'is_active' => 0, ...$normalizer->normalizeRule('Октябрьская')];
addressAssert(
    $matcher->match($normalizer->normalizeRule('Октябрьская 10'), [$inactiveRule]) === null,
    'Inactive exclusions must not block checkout.'
);

$avenueRule = ['id' => 5, 'is_active' => 1, ...$normalizer->normalizeRule('пр-т Мира 12')];
addressAssert(
    $matcher->match($normalizer->normalizeRule('проспект Мира, д. 12'), [$avenueRule]) !== null,
    'Avenue abbreviations must normalize consistently.'
);

$profileAddress = $normalizer->normalizeProfile([
    'street' => 'ул. Октябрьская',
    'house' => ' 15 ',
    'apartment' => '7',
]);
addressAssert(
    $profileAddress['normalized_street'] === 'октябрьская'
    && $profileAddress['normalized_house'] === '15'
    && $profileAddress['normalized_apartment'] === '7',
    'Structured profile fields must preserve normalized street, house, and apartment components.'
);

echo "Subscription address exclusion unit tests passed.\n";
