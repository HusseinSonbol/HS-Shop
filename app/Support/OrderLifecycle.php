<?php

namespace App\Support;

final class OrderLifecycle
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'new' => ['confirmed'],
        'confirmed' => ['shipped'],
        'shipped' => ['received'],
        'received' => [],
    ];

    /** @var list<string> */
    public const FAILURE_REASONS = [
        'customer_cancelled',
        'customer_refused',
        'customer_unreachable',
        'incorrect_address',
        'product_unavailable',
        'duplicate_order',
        'other',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
